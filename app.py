import os
import time
import re
import json
import uuid
import traceback
import tempfile
import logging
import re
import json

from flask import Flask, request, jsonify
import chromadb

# ====== LangChain imports (vector store & docs) ======
try:
    from langchain_chroma import Chroma
except Exception:
    from langchain_community.vectorstores import Chroma  # type: ignore

try:
    from langchain_text_splitters import RecursiveCharacterTextSplitter  # type: ignore
except Exception:
    from langchain.text_splitter import RecursiveCharacterTextSplitter  # type: ignore

try:
    from langchain_core.documents import Document
except Exception:
    from langchain.schema import Document  # type: ignore

try:
    from langchain_huggingface import HuggingFaceEmbeddings
except Exception:
    from langchain_community.embeddings import HuggingFaceEmbeddings  # type: ignore

# ====== Google Gemini (direct, NO LangChain wrapper) ======
import google.generativeai as genai


# ================== CONFIG GEMINI API KEY ==================
# ⚠️ Put your real key here, but NEVER commit it in a public repo.
GOOGLE_API_KEY = "----"

os.environ["GOOGLE_API_KEY"] = GOOGLE_API_KEY
os.environ["GEMINI_API_KEY"] = GOOGLE_API_KEY
genai.configure(api_key=GOOGLE_API_KEY)

# ================== GLOBALS ==================
EMBEDDINGS = HuggingFaceEmbeddings(model_name="sentence-transformers/all-MiniLM-L6-v2")
DEFAULT_MODEL = "gemini-2.5-flash"


# ================== PDF → text ==================
def extract_text_from_pdf(pdf_path: str) -> str:
    import fitz  # PyMuPDF

    doc = fitz.open(pdf_path)
    text = []
    for page in doc:
        text.append(page.get_text("text"))
    return "\n".join(text).strip()


# ================== Vectorstore build ==================
def build_vectorstore_from_text(text: str, resume_id: str):
    splitter = RecursiveCharacterTextSplitter(chunk_size=1000, chunk_overlap=180)
    chunks = splitter.split_text(text)
    docs = [
        Document(
            page_content=c,
            metadata={"source": "resume", "resume_id": resume_id},
        )
        for c in chunks
    ]

    client = chromadb.EphemeralClient()
    vs = Chroma.from_documents(
        documents=docs,
        embedding=EMBEDDINGS,
        client=client,
        collection_name=f"resume-{resume_id}",
    )
    return vs, len(chunks)


# ================== Priority sections ==================
SECTION_PATTERNS = [
    r"\bcomp[eé]tences?\b",
    r"\bskills?\b",
    r"\btechnolog(?:ie|y|ies)\b|\boutils?\b",
    r"\bstack\b",
    r"\blangages?\b|\bprogramming languages?\b",
]


def extract_priority_sections(resume_text: str, resume_id: str):
    blocks = re.split(r"\n{2,}", resume_text)
    priority = []
    for i, b in enumerate(blocks):
        header = b.strip().lower()
        if any(re.search(p, header) for p in SECTION_PATTERNS):
            snip = b.strip()
            if i + 1 < len(blocks):
                snip = snip + "\n\n" + blocks[i + 1].strip()
            priority.append(
                Document(
                    page_content=snip[:1800],
                    metadata={
                        "source": "resume-priority",
                        "section": "skills",
                        "resume_id": resume_id,
                    },
                )
            )
    return priority[:3]


# ================== JSON parsing helper ==================
def parse_model_json(raw: str, log) -> dict:
    """Try really hard to get a JSON object out of the model output."""
    raw = (raw or "").strip()
    log(f"Raw model output (first 400 chars): {raw[:400]}")

    if not raw:
        log("Empty model output, cannot parse JSON.")
        return {"raw_model_output": ""}

    # Strip markdown fences ```json ... ```
    if raw.startswith("```"):
        log("Detected markdown code fence. Stripping it.")
        raw = re.sub(r"^```[a-zA-Z0-9]*\s*", "", raw)
        if raw.endswith("```"):
            raw = raw[:-3].strip()

    # 1) Direct JSON
    try:
        parsed = json.loads(raw)
        log("Successfully parsed JSON from cleaned output.")
        return parsed
    except Exception as e:
        log(f"Direct json.loads failed: {e}")

    # 2) Fallback: first {...} block
    m = re.search(r"\{.*\}", raw, flags=re.S)
    if m:
        candidate = m.group(0)
        log("Trying json.loads on {…} substring.")
        try:
            parsed = json.loads(candidate)
            log("Successfully parsed JSON from {…} substring.")
            return parsed
        except Exception as e2:
            log(f"json.loads on substring failed: {e2}")

    log("Could not parse JSON, returning raw output.")
    return {"raw_model_output": raw}


# ================== Gemini call (forced JSON) ==================
def call_gemini_json(job_offer_text: str, context_text: str, log, model_name: str):
    """
    Call Gemini directly with response_mime_type='application/json'
    so we get clean JSON back.
    """
    system_instructions = """
Tu es un évaluateur d'embauche très strict.

Tu reçois :
1) Une offre d'emploi.
2) Des extraits de CV (contexte).

Règles :
- Utilise UNIQUEMENT ce qui apparaît dans le texte du CV (contexte).
- Si une dimension (skills, education, experience) n'est pas clairement supportée,
  tu mets 0 pour cette dimension.
- Tu calcules des scores entre 0 et 100 pour :
    - skills
    - education
    - experience
  avec overall = 0.5*skills + 0.25*education + 0.25*experience.

Règle de décision :
- Si overall < 70 OU si une dimension < 50 => "Reject"
- Sinon => "Hire"

FORMAT DE RÉPONSE OBLIGATOIRE (JSON valide, sans commentaire, sans texte autour) :
{
  "decision": "Hire" | "Reject",
  "match_scores": {
    "skills": int,
    "education": int,
    "experience": int,
    "overall": int
  },
  "missing_requirements": [string],
  "evidence": {
    "skills": [string],
    "education": [string],
    "experience": [string]
  },
  "notes": string
}
"""

    user_prompt = f"""
OFFRE D'EMPLOI :
{job_offer_text}

=====================
CONTEXTE CV (EXTRAITS) :
{context_text}

Rappelle-toi : tu dois répondre UNIQUEMENT avec un JSON valide conforme au schéma demandé.
Pas de texte avant, pas de texte après.
"""

    log("Instantiating Gemini GenerativeModel with JSON response_mime_type…")
    model = genai.GenerativeModel(
        model_name=model_name,
        generation_config={
            "response_mime_type": "application/json",
        },
    )

    log("Calling model.generate_content()…")
    response = model.generate_content(
        system_instructions + "\n\n" + user_prompt
    )

    # response.text should be pure JSON (because of response_mime_type)
    raw = getattr(response, "text", None) or str(response)
    log("Gemini call finished, parsing JSON…")

    return parse_model_json(raw, log)


# ================== Core evaluation ==================
def evaluate_candidate(
    job_offer_text: str,
    resume_pdf_path: str,
    log,
    model_name: str = DEFAULT_MODEL,
):
    t0 = time.time()
    log("=== evaluate_candidate() START ===")
    log(f"Model: {model_name}")
    log(f"PDF path: {resume_pdf_path}")

    # 1) Extract text from PDF
    try:
        resume_text = extract_text_from_pdf(resume_pdf_path)
    except Exception as e:
        log(f"ERROR extracting text from PDF: {e}")
        return {"error": f"Could not read PDF: {e}"}

    log(f"PDF text length: {len(resume_text):,} chars")

    if not resume_text:
        log("No text extracted from resume.")
        return {"error": "Could not extract text from the resume PDF."}

    # 2) Build vectorstore
    resume_id = str(uuid.uuid4())
    log(f"Resume run id: {resume_id}")

    log("Building Chroma vector store from resume text…")
    vs, n_chunks = build_vectorstore_from_text(resume_text, resume_id)
    log(f"Chunks created: {n_chunks}")

    # 3) Priority sections
    log("Extracting priority sections (skills/technologies)…")
    priority_docs = extract_priority_sections(resume_text, resume_id)
    log(f"Priority docs found: {len(priority_docs)}")

    # 4) Retrieve relevant chunks via MMR
    log("Configuring retriever (MMR)…")
    retriever = vs.as_retriever(
        search_type="mmr",
        search_kwargs={
            "k": 12,
            "fetch_k": 64,
            "lambda_mult": 0.6,
            "filter": {"resume_id": resume_id},
        },
    )
    try:
        log("Calling retriever.invoke(job_offer_text)…")
        retrieved = retriever.invoke(job_offer_text)
    except Exception as e:
        log(f"retriever.invoke failed: {e}, falling back to get_relevant_documents()")
        retrieved = retriever.get_relevant_documents(job_offer_text)

    log(f"Retrieved docs: {len(retrieved) if retrieved else 0}")

    # 5) Merge + dedupe priority + retrieved docs
    log("Merging + deduplicating priority and retrieved docs…")
    seen = set()
    ordered_docs = []
    for d in (priority_docs or []) + (retrieved or []):
        key = (d.page_content or "").strip()
        if key and key not in seen:
            seen.add(key)
            ordered_docs.append(d)

    log(f"Context docs after merge/dedupe: {len(ordered_docs)}")

    # Build a single context string for the model
    context_blocks = []
    for idx, d in enumerate(ordered_docs):
        snippet = (d.page_content or "").strip()
        if not snippet:
            continue
        context_blocks.append(f"=== EXTRACT {idx+1} ===\n{snippet}")
    context_text = "\n\n".join(context_blocks)
    log(f"Context text length (chars): {len(context_text)}")

    # 6) Call Gemini with forced JSON
    result = call_gemini_json(job_offer_text, context_text, log, model_name)

    # 7) Cleanup
    try:
        log("Cleaning up: deleting Chroma docs for this run…")
        vs._collection.delete(where={"resume_id": resume_id})
        log("Cleanup success.")
    except Exception as e:
        log(f"Cleanup skipped / failed (non-fatal): {e}")

    total = time.time() - t0
    log(f"=== evaluate_candidate() END. Total time: {total:.2f}s ===")
    return result


# ================== Optional pretty rendering ==================
def _md_badge(decision: str) -> str:
    if not decision:
        return ""
    dec = str(decision).strip().lower()
    if dec == "hire":
        return (
            "<span style='background:#16a34a;color:white;padding:4px 10px;"
            "border-radius:999px;font-weight:600'>✅ Hire</span>"
        )
    if dec == "reject":
        return (
            "<span style='background:#dc2626;color:white;padding:4px 10px;"
            "border-radius:999px;font-weight:600'>❌ Reject</span>"
        )
    return (
        "<span style='background:#64748b;color:white;padding:4px 10px;"
        f"border-radius:999px;font-weight:600'>ℹ️ {decision}</span>"
    )


def _fmt_list(items):
    if not items:
        return "- —"
    lines = []
    for x in items:
        s = str(x).strip()
        if not s:
            continue
        s = re.sub(r"\s+", " ", s)
        lines.append("- " + s)
    return "\n".join(lines)


def render_pretty(res: dict) -> str:
    if not isinstance(res, dict):
        return "⚠️ Unexpected output."

    if "error" in res:
        return f"### ❗ Error\n{res['error']}"

    if "raw_model_output" in res:
        raw = str(res["raw_model_output"]).strip()
        return f"### ℹ️ Raw model output\n```json\n{raw}\n```"

    scores = res.get("match_scores", {}) or {}
    skills = int(scores.get("skills", 0) or 0)
    edu = int(scores.get("education", 0) or 0)
    exp = int(scores.get("experience", 0) or 0)
    overall = int(scores.get(
        "overall",
        round(0.5 * skills + 0.25 * edu + 0.25 * exp),
    ))

    decision = res.get("decision", "")
    missing = res.get("missing_requirements", []) or []
    evidence = res.get("evidence", {}) or {}
    ev_sk = evidence.get("skills", []) or []
    ev_ed = evidence.get("education", []) or []
    ev_ex = evidence.get("experience", []) or []
    notes = res.get("notes", "")

    badge = _md_badge(decision)

    md = []
    md.append(f"## Decision\n{badge}")
    md.append("\n### Match scores")
    md.append("| Skills | Education | Experience | Overall |")
    md.append("|:-----:|:---------:|:----------:|:------:|")
    md.append(f"| {skills} | {edu} | {exp} | **{overall}** |")

    md.append("\n### Missing requirements")
    md.append(_fmt_list(missing))

    md.append("\n### Evidence — Skills")
    md.append(_fmt_list(ev_sk))
    md.append("\n### Evidence — Education")
    md.append(_fmt_list(ev_ed))
    md.append("\n### Evidence — Experience")
    md.append(_fmt_list(ev_ex))

    if notes:
        md.append("\n### Notes")
        md.append(re.sub(r"\s+", " ", str(notes)).strip())

    raw_json = json.dumps(res, ensure_ascii=False, indent=2)
    md.append(
        "\n<details><summary>Show raw JSON</summary>\n\n```json\n"
        + raw_json
        + "\n```\n</details>"
    )

    return "\n".join(md)


# ================== Flask app ==================
app = Flask(__name__)

logging.basicConfig(level=logging.INFO)
app.logger.setLevel(logging.INFO)


@app.route("/health", methods=["GET"])
def health():
    app.logger.info("Health check hit: /health")
    return jsonify({"status": "ok", "message": "Smart hiring engine up"}), 200
def parse_json_like(raw: str, log):
    """
    Try to turn a 'JSON-like' string from Gemini into a real dict.

    - Extracts the longest {...} block.
    - Tries json.loads directly.
    - If it fails, removes trailing commas before '}' or ']' and tries again.
    Returns a dict on success, or None on failure.
    """
    if not isinstance(raw, str):
        log("parse_json_like: raw_model_output is not a string.")
        return None

    text = raw.strip()
    if not text:
        log("parse_json_like: empty raw_model_output.")
        return None

    # Extract substring from first '{' to last '}'
    start = text.find("{")
    end = text.rfind("}")
    if start == -1 or end == -1 or end <= start:
        log("parse_json_like: no '{...}' block found in raw_model_output.")
        return None

    candidate = text[start : end + 1]

    # 1) Direct attempt
    try:
        parsed = json.loads(candidate)
        log("parse_json_like: direct json.loads(...) succeeded.")
        return parsed
    except Exception as e:
        log(f"parse_json_like: direct json.loads failed: {e}")

    # 2) Try to fix trailing commas:  , }  or  , ]
    fixed = re.sub(r",(\s*[\]}])", r"\1", candidate)
    try:
        parsed = json.loads(fixed)
        log("parse_json_like: json.loads after removing trailing commas succeeded.")
        return parsed
    except Exception as e:
        log(f"parse_json_like: json.loads(fixed) still failed: {e}")
        return None


@app.route("/evaluate", methods=["POST"])
def evaluate_endpoint():
    """
    POST /evaluate
    Content-Type: multipart/form-data

    Fields:
      - job_offer: texte de l'offre d'emploi
      - resume: fichier PDF

    Optionnel:
      - model_name: gemini-2.0-flash | gemini-2.5-flash | gemini-2.5-pro
        (par défaut: DEFAULT_MODEL)
    """
    request_id = str(uuid.uuid4())[:8]
    app.logger.info(f"[{request_id}] /evaluate called")

    logs = []

    def log(msg: str):
        stamp = time.strftime("%H:%M:%S")
        line = f"[{stamp}] [{request_id}] {msg}"
        logs.append(line)
        app.logger.info(line)

    log("Incoming /evaluate request.")
    log(f"Content-Type: {request.content_type}")
    log(f"Form keys: {list(request.form.keys())}")
    log(f"File keys: {list(request.files.keys())}")

    job_offer = (request.form.get("job_offer") or "").strip()
    model_name = (request.form.get("model_name") or DEFAULT_MODEL).strip() or DEFAULT_MODEL
    resume_file = request.files.get("resume")

    log(f"Received job_offer length: {len(job_offer)} characters")
    log(f"Requested model_name: {model_name}")

    if resume_file:
        log(f"Received resume file: filename={resume_file.filename}, mimetype={resume_file.mimetype}")
    else:
        log("No 'resume' file in request.files")

    if not job_offer:
        log("ERROR: Missing 'job_offer' text.")
        return jsonify({"error": "Missing 'job_offer' text", "logs": logs}), 400

    if not resume_file:
        log("ERROR: Missing 'resume' PDF file.")
        return jsonify({"error": "Missing 'resume' PDF file", "logs": logs}), 400

    tmp_path = None
    try:
        # 1) Save uploaded PDF to a temp file
        log("Saving uploaded resume to temporary .pdf file…")
        with tempfile.NamedTemporaryFile(delete=False, suffix=".pdf") as tmp:
            resume_file.save(tmp)
            tmp_path = tmp.name
        log(f"Temporary PDF saved at: {tmp_path}")

        # 2) Call core evaluation
        log("Calling evaluate_candidate()…")
        res = evaluate_candidate(
            job_offer_text=job_offer,
            resume_pdf_path=tmp_path,
            log=log,
            model_name=model_name,
        )
        log("evaluate_candidate() finished.")

        # 3) POST-PROCESSING:
        #    If evaluate_candidate returned {"raw_model_output": "..."},
        #    try our robust JSON fixer to get a proper dict.
        if isinstance(res, dict) and "raw_model_output" in res:
            raw = res.get("raw_model_output")
            log("Post-processing: detected raw_model_output, trying to parse as JSON-like string…")
            parsed = parse_json_like(raw, log)
            if parsed is not None:
                res = parsed  # overwrite with parsed JSON (has decision, scores, etc.)
                log("Post-processing: using parsed JSON as final result.")
            else:
                log("Post-processing: still could not parse raw_model_output as JSON; keeping raw text.")

        log("Preparing JSON response for client (Symfony)…")

        return jsonify(
            {
                "result": res,
                "logs": logs,
                "pretty_markdown": render_pretty(res),
            }
        )

    except Exception as e:
        log("Exception during evaluation, see traceback in server logs.")
        app.logger.error("Exception during evaluation:\n" + traceback.format_exc())
        return jsonify({"error": str(e), "logs": logs}), 500

    finally:
        if tmp_path and os.path.exists(tmp_path):
            try:
                os.remove(tmp_path)
                app.logger.info(f"[{request_id}] Temp file deleted: {tmp_path}")
            except Exception as e:
                app.logger.warning(f"[{request_id}] Failed to delete temp file {tmp_path}: {e}")


if __name__ == "__main__":
    app.logger.info("🚀 Starting Smart Hiring Flask app on http://0.0.0.0:5000")
    app.run(host="0.0.0.0", port=5000, debug=True)
