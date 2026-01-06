<?php
declare(strict_types=1);

namespace App\Controller\Web;
use App\Entity\Resume;
use App\Entity\Job;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\AuthAccessTokenBlacklistRepository;
use App\Security\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Repository\JobRepository;
use App\Entity\Application;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\MatchResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class JobController extends AbstractController
{
    /**
     * Helper: fetch the authenticated User from the JWT (cookie or Bearer).
     */
    private function resolveUserFromJwt(
        Request $request,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo
    ): ?User {
        $token = null;

        // 1) Authorization: Bearer ...
        $authHeader = $request->headers->get('Authorization', '');
        if (\preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = \trim($m[1]);
        } else {
            // 2) Fallback to cookie set by /login
            $token = (string) $request->cookies->get('access_token', '');
        }

        if ($token === '') {
            return null;
        }

        $payload = $jwt->decodeAndVerify($token);

        if (($payload['typ'] ?? null) !== 'access') {
            throw new \RuntimeException('Not an access token.');
        }

        $jti = (string) ($payload['jti'] ?? '');
        if ($jti === '' || $blacklistRepo->isBlacklisted($jti)) {
            throw new \RuntimeException('Token revoked or invalid.');
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            throw new \RuntimeException('Missing email in token.');
        }

        $user = $users->findOneBy(['email' => $email]);

        return $user instanceof User ? $user : null;
    }

    #[Route('/jobs/new', name: 'web_job_new', methods: ['GET', 'POST'])]
    #[Route('/jobs/new', name: 'job_new', methods: ['GET', 'POST'])] // alias, optional
    public function new(
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        // 1) Resolve user from JWT (cookie / Authorization)
        try {
            $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('web_login');
        }

        if (!$user) {
            return $this->redirectToRoute('web_login');
        }

        // 2) Role check
        $roles = $user->getRoles();
        if (!\in_array(User::ROLE_HR, $roles, true) && !\in_array(User::ROLE_ADMIN, $roles, true)) {
            $this->addFlash('error', 'Only HR or Admin users can create job postings.');
            return $this->redirectToRoute('web_dashboard');
        }

        // 3) Handle POST (create job)
        if ($request->isMethod('POST')) {
            // CSRF check: ID "job_create" must match the token we generate for the form
            $submittedToken = (string) $request->request->get('_csrf_token', '');
            $tokenObj = new CsrfToken('job_create', $submittedToken);

            if (!$csrf->isTokenValid($tokenObj)) {
                $this->addFlash('error', 'Invalid form token, please try again.');
                return $this->redirectToRoute('web_job_new');
            }

            $title        = \trim((string) $request->request->get('title', ''));
            $description  = \trim((string) $request->request->get('description', ''));
            $location     = $request->request->get('location') ?: null;
            $requirements = $request->request->get('requirements') ?: null;

            // IMPORTANT: use same field names as in jobs/new.html.twig
            $workMode       = (string) $request->request->get('work_mode', Job::WORK_ONSITE);
            $employmentType = (string) $request->request->get('employment_type', Job::TYPE_FULL_TIME);

            if ($title === '' || $description === '') {
                $this->addFlash('error', 'Title and description are required.');
                return $this->redirectToRoute('web_job_new');
            }

            $membership = $user->getCompanyMemberships()->first();
            if (!$membership) {
                $this->addFlash('error', 'You must belong to a company to create jobs.');
                return $this->redirectToRoute('web_dashboard');
            }

            $job = (new Job())
                ->setCompany($membership->getCompany())
                ->setPostedBy($user)
                ->setTitle($title)
                ->setDescription($description)
                ->setLocation($location)
                ->setRequirements($requirements)
                ->setWorkMode($workMode)
                ->setEmploymentType($employmentType)
                ->setStatus(Job::STATUS_PUBLISHED)
                ->setPublishedAt(new \DateTime()); 

            $em->persist($job);
            $em->flush();

            $this->addFlash('success', \sprintf('Job "%s" published successfully.', $title));

            return $this->redirectToRoute('web_employer_jobs');
        }

        // 4) GET: render form with CSRF token
        $csrfToken = $csrf->getToken('job_create')->getValue();

        return $this->render('jobs/new.html.twig', [
            'user'       => $user,
            'csrf_token' => $csrfToken,
        ]);
    }
#[Route('/employer/jobs', name: 'web_employer_jobs', methods: ['GET'])]
public function employerJobs(
    Request $request,
    JwtService $jwt,
    UserRepository $users,
    AuthAccessTokenBlacklistRepository $blacklistRepo,
    EntityManagerInterface $em,
    CsrfTokenManagerInterface $csrf,
): Response {
    // 1) Resolve user from JWT
    try {
        $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
    } catch (\Throwable $e) {
        return $this->redirectToRoute('web_login');
    }

    if (!$user) {
        return $this->redirectToRoute('web_login');
    }

    // 2) Role check
    $roles = $user->getRoles();
    if (!\in_array(User::ROLE_HR, $roles, true) && !\in_array(User::ROLE_ADMIN, $roles, true)) {
        $this->addFlash('error', 'Only HR or Admin users can manage job postings.');
        return $this->redirectToRoute('web_dashboard');
    }

    // 3) Get company membership
    $membership = $user->getCompanyMemberships()->first();
    if (!$membership) {
        $this->addFlash('error', 'You must belong to a company to manage jobs.');
        return $this->redirectToRoute('web_dashboard');
    }

    $company = $membership->getCompany();

    // 4) Fetch jobs for that company
    $jobRepo = $em->getRepository(Job::class);
    /** @var Job[] $jobList */
    $jobList = $jobRepo->findBy(
        ['company' => $company],
        ['publishedAt' => 'DESC']
    );

    // 5) Generate CSRF token for actions (e.g., delete, edit)
    $csrfToken = $csrf->getToken('manage_jobs')->getValue();

    return $this->render('employer/jobs.html.twig', [
        'user'        => $user,
        'jobs'        => $jobList,
        'csrf_token'  => $csrfToken,
    ]);
}

    #[Route('/employer/jobs/{id}/edit', name: 'web_employer_job_edit', methods: ['GET', 'POST'])]
    public function editJob(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        // Auth
        try {
            $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('web_login');
        }
        if (!$user) {
            return $this->redirectToRoute('web_login');
        }

        $roles = $user->getRoles();
        if (!\in_array(User::ROLE_HR, $roles, true) && !\in_array(User::ROLE_ADMIN, $roles, true)) {
            $this->addFlash('error', 'Only HR or Admin users can edit job postings.');
            return $this->redirectToRoute('web_dashboard');
        }

        /** @var Job|null $job */
        $job = $em->getRepository(Job::class)->find($id);
        if (!$job) {
            $this->addFlash('error', 'Job not found.');
            return $this->redirectToRoute('web_employer_jobs');
        }

        // Ensure job belongs to this HR's company
        $membership = $user->getCompanyMemberships()->first();
        if (!$membership || $job->getCompany()->getId() !== $membership->getCompany()->getId()) {
            $this->addFlash('error', 'You are not allowed to edit this job.');
            return $this->redirectToRoute('web_employer_jobs');
        }

        if ($request->isMethod('POST')) {
            // Use the same CSRF id as the list: "manage_jobs"
            $submittedToken = (string) $request->request->get('_csrf_token', '');
            if (!$csrf->isTokenValid(new CsrfToken('manage_jobs', $submittedToken))) {
                $this->addFlash('error', 'Invalid form token, please try again.');
                return $this->redirectToRoute('web_employer_job_edit', ['id' => $id]);
            }

            $title        = \trim((string) $request->request->get('title', ''));
            $description  = \trim((string) $request->request->get('description', ''));
            $location     = $request->request->get('location') ?: null;
            $requirements = $request->request->get('requirements') ?: null;
            $status       = (string) $request->request->get('status', Job::STATUS_PUBLISHED);
            $workMode       = (string) $request->request->get('work_mode', Job::WORK_ONSITE);
            $employmentType = (string) $request->request->get('employment_type', Job::TYPE_FULL_TIME);

            if ($title === '' || $description === '') {
                $this->addFlash('error', 'Title and description are required.');
                return $this->redirectToRoute('web_employer_job_edit', ['id' => $id]);
            }

            $job
                ->setTitle($title)
                ->setDescription($description)
                ->setLocation($location)
                ->setRequirements($requirements)
                ->setStatus($status)
                ->setWorkMode($workMode)
                ->setEmploymentType($employmentType);

            // If setting to PUBLISHED and there is no publishedAt yet, set it
            if ($status === Job::STATUS_PUBLISHED && $job->getPublishedAt() === null) {
                $job->setPublishedAt(new \DateTime());
            }

            $em->flush();

            $this->addFlash('success', 'Job updated successfully.');
            return $this->redirectToRoute('web_employer_jobs');
        }

        // GET: show edit form
        $csrfToken = $csrf->getToken('manage_jobs')->getValue();

        return $this->render('employer/job_edit.html.twig', [
            'user'       => $user,
            'job'        => $job,
            'csrf_token' => $csrfToken,
        ]);
    }

    #[Route('/employer/jobs/{id}/delete', name: 'web_employer_job_delete', methods: ['POST'])]
    public function deleteJob(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        // Auth
        try {
            $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('web_login');
        }
        if (!$user) {
            return $this->redirectToRoute('web_login');
        }

        $roles = $user->getRoles();
        if (!\in_array(User::ROLE_HR, $roles, true) && !\in_array(User::ROLE_ADMIN, $roles, true)) {
            $this->addFlash('error', 'Only HR or Admin users can delete job postings.');
            return $this->redirectToRoute('web_dashboard');
        }

        /** @var Job|null $job */
        $job = $em->getRepository(Job::class)->find($id);
        if (!$job) {
            $this->addFlash('error', 'Job not found.');
            return $this->redirectToRoute('web_employer_jobs');
        }

        // Ensure job belongs to this HR's company
        $membership = $user->getCompanyMemberships()->first();
        if (!$membership || $job->getCompany()->getId() !== $membership->getCompany()->getId()) {
            $this->addFlash('error', 'You are not allowed to delete this job.');
            return $this->redirectToRoute('web_employer_jobs');
        }

        // CSRF
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$csrf->isTokenValid(new CsrfToken('manage_jobs', $submittedToken))) {
            $this->addFlash('error', 'Invalid form token, please try again.');
            return $this->redirectToRoute('web_employer_jobs');
        }

        $title = $job->getTitle();

        $em->remove($job);
        $em->flush();

        $this->addFlash('success', \sprintf('Job "%s" deleted successfully.', $title));

        return $this->redirectToRoute('web_employer_jobs');
    }
    #[Route('/jobs', name: 'web_jobs_index', methods: ['GET'])]
    public function listJobs(JobRepository $jobs): Response
    {
        // Afficher uniquement les offres publiées, les plus récentes en premier
        $publishedJobs = $jobs->findBy(
            ['status' => Job::STATUS_PUBLISHED],
            ['publishedAt' => 'DESC']
        );

        return $this->render('jobs/index.html.twig', [
            'jobs' => $publishedJobs,
        ]);
    }
    #[Route('/jobs/{id}', name: 'web_job_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showJob(
        int $id,
        Request $request,
        JobRepository $jobs,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo,
    ): Response {
        $job = $jobs->find($id);

        // 404 si l’offre n’existe pas ou n’est pas publiée
        if (!$job || $job->getStatus() !== Job::STATUS_PUBLISHED) {
            throw $this->createNotFoundException('Job not found.');
        }

        // On essaie de récupérer l’utilisateur connecté, sans casser la page si JWT invalide
        $currentUser = null;
        try {
            $currentUser = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
        } catch (\Throwable $e) {
            $currentUser = null;
        }

        return $this->render('jobs/show.html.twig', [
            'job'  => $job,
            'user' => $currentUser,
        ]);
    }
#[Route('/jobs/{id}/apply', name: 'web_jobs_apply', methods: ['GET', 'POST'])]
public function apply(
    int $id,
    Request $request,
    EntityManagerInterface $em,
    JwtService $jwt,
    UserRepository $users,
    AuthAccessTokenBlacklistRepository $blacklistRepo,
    CsrfTokenManagerInterface $csrf,
): Response {
    try {
        $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
    } catch (\Throwable $e) {
        return $this->redirectToRoute('web_login');
    }

    if (!$user instanceof User) {
        return $this->redirectToRoute('web_login');
    }

    /** @var Job|null $job */
    $job = $em->getRepository(Job::class)->find($id);
    if (!$job) {
        throw $this->createNotFoundException('Job not found.');
    }

    $tokenId = 'job_apply_'.$job->getId();

    if ($request->isMethod('POST')) {
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$csrf->isTokenValid(new CsrfToken($tokenId, $submittedToken))) {
            $this->addFlash('error', 'Invalid form token, please try again.');
            return $this->redirectToRoute('web_jobs_apply', ['id' => $job->getId()]);
        }

        $coverLetter = \trim((string) $request->request->get('cover_letter', ''));
        if ($coverLetter === '') {
            $this->addFlash('error', 'Please add a short message or cover letter.');
            return $this->redirectToRoute('web_jobs_apply', ['id' => $job->getId()]);
        }

        // Check duplicate application
        $existing = $em->getRepository(Application::class)->findOneBy([
            'job'       => $job,
            'candidate' => $user,
        ]);

        if ($existing instanceof Application) {
            $this->addFlash('error', 'You have already applied to this job.');
            return $this->redirectToRoute('web_jobs_show', ['id' => $job->getId()]);
        }

        // Get default resume for this candidate
        $defaultResume = $em->getRepository(Resume::class)->findOneBy([
            'candidate' => $user,
            'isDefault' => true,
        ]);

        if (!$defaultResume instanceof Resume) {
            $this->addFlash('error', 'Please upload a resume before applying to jobs.');
            return $this->redirectToRoute('web_candidate_resume_new');
        }

        // Create application
        $application = (new Application())
            ->setJob($job)
            ->setCandidate($user)
            ->setResume($defaultResume)
            ->setCoverLetter($coverLetter)
            ->setStatus(Application::STATUS_SUBMITTED);

        $em->persist($application);
        $em->flush();

        $this->addFlash('success', 'Your application has been sent successfully 🎉');

        return $this->redirectToRoute('web_jobs_show', ['id' => $job->getId()]);
    }

    $csrfToken = $csrf->getToken($tokenId)->getValue();

    return $this->render('jobs/apply.html.twig', [
        'user'       => $user,
        'job'        => $job,
        'csrf_token' => $csrfToken,
    ]);
}



    #[Route('/jobs/{id}', name: 'web_jobs_show', methods: ['GET'])]
    public function showForCandidates(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo,
    ): Response {
        $job = $em->getRepository(Job::class)->find($id);
        if (!$job || $job->getStatus() !== Job::STATUS_PUBLISHED) {
            throw $this->createNotFoundException('Job not found.');
        }

        // Optional: candidate (if logged in) – for header / personalization
        $currentUser = null;
        try {
            $currentUser = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
        } catch (\Throwable $e) {
            $currentUser = null;
        }

        return $this->render('jobs/show.html.twig', [
            'job'  => $job,
            'user' => $currentUser,
        ]);
    }
 #[Route('/candidate/resumes/new', name: 'web_candidate_resume_new', methods: ['GET', 'POST'])]
public function uploadResume(
    Request $request,
    EntityManagerInterface $em,
    JwtService $jwt,
    UserRepository $users,
    AuthAccessTokenBlacklistRepository $blacklistRepo,
    CsrfTokenManagerInterface $csrf,
): Response {
    // 1) Auth via JWT
    try {
        $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
    } catch (\Throwable $e) {
        return $this->redirectToRoute('web_login');
    }

    if (!$user instanceof User) {
        return $this->redirectToRoute('web_login');
    }

    // 2) CSRF id
    $tokenId = 'resume_upload_'.$user->getId();

    // 3) POST: handle upload
    if ($request->isMethod('POST')) {
        $submittedToken = (string) $request->request->get('_csrf_token', '');
        if (!$csrf->isTokenValid(new CsrfToken($tokenId, $submittedToken))) {
            $this->addFlash('error', 'Invalid form token, please try again.');
            return $this->redirectToRoute('web_candidate_resume_new');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('resume_file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Please choose a valid file to upload.');
            return $this->redirectToRoute('web_candidate_resume_new');
        }

        // --- IMPORTANT: do NOT call $file->getMimeType() or guessExtension() ---

        // Client mime type (from browser headers)
        $clientMime = $file->getClientMimeType() ?: 'application/octet-stream';

        // Allowed mime types
        $allowedMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        if (!\in_array($clientMime, $allowedMime, true)) {
            $this->addFlash('error', 'Please upload a PDF or Word document.');
            return $this->redirectToRoute('web_candidate_resume_new');
        }

        // Optional size check (10 MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file->getSize() !== null && $file->getSize() > $maxSize) {
            $this->addFlash('error', 'File is too large. Max 10 MB.');
            return $this->redirectToRoute('web_candidate_resume_new');
        }

        // 4) Build a safe filename using the client original name (no guessExtension)
        $originalName = $file->getClientOriginalName();
        $basename     = \pathinfo($originalName, \PATHINFO_FILENAME);
        $originalExt  = \strtolower((string) \pathinfo($originalName, \PATHINFO_EXTENSION));

        // Accept only specific extensions (extra safety)
        $allowedExt = ['pdf', 'doc', 'docx'];
        if ($originalExt === '' || !\in_array($originalExt, $allowedExt, true)) {
            // If extension is missing or weird, map from mime type
            if ($clientMime === 'application/pdf') {
                $originalExt = 'pdf';
            } elseif ($clientMime === 'application/msword') {
                $originalExt = 'doc';
            } elseif ($clientMime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                $originalExt = 'docx';
            } else {
                // Fallback
                $originalExt = 'pdf';
            }
        }

        $safeBase    = \preg_replace('/[^a-zA-Z0-9_\-]/', '_', $basename) ?: 'cv';
        $unique      = \bin2hex(\random_bytes(6));
        $newFilename = $safeBase.'-'.$unique.'.'.$originalExt;

        // 5) Upload dir
        /** @var string $uploadDir */
        $uploadDir = (string) $this->getParameter('resumes_dir'); // e.g. var/resumes

        if (!\is_dir($uploadDir)) {
            @\mkdir($uploadDir, 0775, true);
        }

        // Move the file (after this, tmp file can disappear, but we don't care)
        $file->move($uploadDir, $newFilename);

        // 6) Compute size + sha256 from the final stored file (no mime guessing)
        $fullPath      = \rtrim($uploadDir, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.$newFilename;
        $fileSizeBytes = \is_file($fullPath) ? (\filesize($fullPath) ?: 0) : 0;
        $sha256        = \is_file($fullPath) ? \hash_file('sha256', $fullPath) : '';

        // 7) Unset any previous default resume
        $existingDefault = $em->getRepository(Resume::class)->findOneBy([
            'candidate' => $user,
            'isDefault' => true,
        ]);
        if ($existingDefault instanceof Resume) {
            $existingDefault->setIsDefault(false);
        }

        // 8) Create Resume entity (compatible with your mapping)
        $resume = (new Resume())
            ->setCandidate($user)
            ->setOriginalFilename($originalName)
            ->setStoragePath($newFilename)   // store relative path / filename
            ->setMimeType($clientMime)      // from client, no fileinfo
            ->setFileSizeBytes($fileSizeBytes)
            ->setSha256($sha256 ?: \bin2hex(\random_bytes(32))) // fallback if needed
            ->setIsDefault(true);

        $em->persist($resume);
        $em->flush();

        $this->addFlash('success', 'Your resume has been uploaded successfully ✅');

        return $this->redirectToRoute('web_dashboard');
    }

    // 4) GET: show upload form
    $csrfToken = $csrf->getToken($tokenId)->getValue();

    return $this->render('candidate/resume_new.html.twig', [
        'user'       => $user,
        'csrf_token' => $csrfToken,
    ]);
}

    #[Route('/candidate/applications', name: 'web_candidate_applications', methods: ['GET'])]
    public function listCandidateApplications(
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo,
    ): Response {
        // 1) Auth via JWT (comme sur les autres routes)
        try {
            $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('web_login');
        }

        if (!$user instanceof User) {
            return $this->redirectToRoute('web_login');
        }

        // 2) Récupérer les candidatures du candidat connecté
        $qb = $em->getRepository(Application::class)
            ->createQueryBuilder('a')
            ->addSelect('j')
            ->leftJoin('a.job', 'j')
            ->where('a.candidate = :u')
            ->setParameter('u', $user)
            ->orderBy('a.appliedAt', 'DESC');

        /** @var Application[] $applications */
        $applications = $qb->getQuery()->getResult();

        return $this->render('candidate/applications.html.twig', [
            'user'         => $user,
            'applications' => $applications,
        ]);
    }

    #[Route('/employer/applicants', name: 'web_employer_applicants', methods: ['GET'])]
    public function employerApplicants(
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwt,
        UserRepository $users,
        AuthAccessTokenBlacklistRepository $blacklistRepo,
    ): Response {
        // 1) Auth via JWT (same logic as your other web routes)
        try {
            $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('web_login');
        }

        if (!$user) {
            return $this->redirectToRoute('web_login');
        }

        // 2) Fetch applications for jobs posted by this employer
        $qb = $em->createQueryBuilder()
            ->select('a', 'j', 'candidate', 'resume', 'company')
            ->from(Application::class, 'a')
            ->join('a.job', 'j')
            ->join('j.company', 'company')
            ->leftJoin('a.candidate', 'candidate')
            ->leftJoin('a.resume', 'resume')
            // 🔴 HERE is the important part: use "postedBy" (from your Job entity)
            ->where('j.postedBy = :employer')
            ->setParameter('employer', $user)
            ->orderBy('a.appliedAt', 'DESC');

        $applications = $qb->getQuery()->getResult();

        return $this->render('employer/applicants.html.twig', [
            'user'         => $user,
            'applications' => $applications,
        ]);
    }
#[Route('/employer/applicants/{id}', name: 'web_employer_applicant_show', methods: ['GET', 'POST'])]
public function employerApplicantShow(
    int $id,
    Request $request,
    EntityManagerInterface $em,
    JwtService $jwt,
    UserRepository $users,
    AuthAccessTokenBlacklistRepository $blacklistRepo,
    HttpClientInterface $httpClient,
): Response {
    // 1) Auth via JWT
    try {
        $user = $this->resolveUserFromJwt($request, $jwt, $users, $blacklistRepo);
    } catch (\Throwable $e) {
        error_log('[AI-EVAL] JWT error: '.$e->getMessage());
        return $this->redirectToRoute('web_login');
    }

    if (!$user) {
        error_log('[AI-EVAL] No user resolved from JWT');
        return $this->redirectToRoute('web_login');
    }

    // 2) Load application + ensure it belongs to a job posted by this employer
    $dql = '
        SELECT a, j, c, r, mr
        FROM App\Entity\Application a
        JOIN a.job j
        JOIN a.candidate c
        LEFT JOIN a.resume r
        LEFT JOIN a.matchResults mr
        WHERE a.id = :id
          AND j.postedBy = :employer
    ';

    $query = $em->createQuery($dql)
        ->setParameter('id', $id)
        ->setParameter('employer', $user);

    /** @var Application|null $application */
    $application = $query->getOneOrNullResult();

    if (!$application) {
        error_log('[AI-EVAL] Application not found or not belonging to employer. id='.$id);
        throw $this->createNotFoundException('Application not found or not belonging to you.');
    }

    $job       = $application->getJob();
    $resume    = $application->getResume();
    $candidate = $application->getCandidate();

    // Variables for Twig
    $aiResult = null;
    $aiError  = null;
    $aiLogs   = [];
    $aiPretty = null;

    // 3) Decide if we trigger the AI
    //    - there is a resume
    //    - and: POST OR ?refresh=1
    $shouldRefresh =
        $resume
        && (
            $request->isMethod('POST')
            || $request->query->getBoolean('refresh', false)
        );

    error_log('[AI-EVAL] shouldRefresh='.($shouldRefresh ? 'true' : 'false').', method='.$request->getMethod());

    if ($shouldRefresh) {
        try {
            // 3.a) Build job offer text
            $jobOfferText =
                $job->getTitle()."\n\n".
                ($job->getDescription() ?? '')."\n\n".
                "Requirements:\n".($job->getRequirements() ?? '');

            error_log('[AI-EVAL] Job offer text length='.strlen($jobOfferText));

            // 3.b) Resolve resume PDF path on disk
            $projectDir  = $this->getParameter('kernel.project_dir'); // e.g. C:\xampp\htdocs\jobhub\jobhub
            $storagePath = $resume->getStoragePath();                 // e.g. "resume-6291.pdf" or "var/resumes/resume-6291.pdf"

            // Normalise slashes and remove leading slash
            $storagePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storagePath);
            $storagePath = ltrim($storagePath, DIRECTORY_SEPARATOR);

            // If it does not already start with "var\resumes", prepend it
            if (!preg_match('#^var'.preg_quote(DIRECTORY_SEPARATOR, '#').'resumes#', $storagePath)) {
                $storagePath = 'var'.DIRECTORY_SEPARATOR.'resumes'.DIRECTORY_SEPARATOR.$storagePath;
            }

            // Final absolute path, e.g.:
            // C:\xampp\htdocs\jobhub\jobhub\var\resumes\resume-6291662b8751.pdf
            $resumePath = $projectDir.DIRECTORY_SEPARATOR.$storagePath;

            error_log('[AI-EVAL] Resume path='.$resumePath);

            if (!is_readable($resumePath)) {
                throw new \RuntimeException('Resume file not found or not readable: '.$resumePath);
            }

            // 3.c) Open the file for HttpClient
            $fileHandle = fopen($resumePath, 'r');
            if (!$fileHandle) {
                throw new \RuntimeException('Could not open resume file: '.$resumePath);
            }

            error_log('[AI-EVAL] About to call Python server http://127.0.0.1:5000/evaluate');

            // 3.d) Call Flask /evaluate
            // Symfony HttpClient: using "body" with a resource => multipart/form-data
            $response = $httpClient->request('POST', 'http://127.0.0.1:5000/evaluate', [
                'body' => [
                    'job_offer'  => $jobOfferText,
                    'model_name' => 'gemini-2.5-flash',
                    'resume'     => $fileHandle,
                ],
                'timeout' => 120,
            ]);

            $statusCode = $response->getStatusCode();
            error_log('[AI-EVAL] Python response status='.$statusCode);

            $rawContent = $response->getContent(false);
            error_log('[AI-EVAL] Python raw response (first 500 chars)='.substr($rawContent, 0, 500));

            // 3.e) Parse JSON (Flask returns {result, logs, pretty_markdown})
            $data = json_decode($rawContent, true);

            if (!is_array($data)) {
                throw new \RuntimeException('Invalid JSON returned by Python server');
            }

            $aiResult = $data['result'] ?? null;
            $aiLogs   = $data['logs'] ?? [];
            $aiPretty = $data['pretty_markdown'] ?? null;

            error_log('[AI-EVAL] Parsed JSON from Python. logs_count='.count($aiLogs));
        } catch (\Throwable $e) {
            $aiError = $e->getMessage();
            error_log('[AI-EVAL] Error during Python call: '.$aiError);
        }
    } else {
        error_log('[AI-EVAL] AI evaluation not triggered (no resume or no POST/refresh=1)');
    }

    return $this->render('employer/applicant_show.html.twig', [
        'user'        => $user,
        'application' => $application,
        'candidate'   => $candidate,
        'job'         => $job,
        'aiResult'    => $aiResult,
        'aiError'     => $aiError,
        'ai_logs'     => $aiLogs,
        'ai_pretty'   => $aiPretty,
    ]);
}
    #[Route('/employer/applicant/{id}/accept', name: 'web_employer_applicant_accept', methods: ['POST'])]
    public function employerApplicantAccept(
        Application $application,
        Request $request,
        EntityManagerInterface $em
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('hr_decision_' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        // You can pick any status you like; here I use SHORTLISTED as "accepted for interview"
        $application->setStatus('ACCEPTED'); // or Application::STATUS_SHORTLISTED
        $em->flush();

        $this->addFlash('success', 'Application accepted. You can now schedule an interview.');

        return $this->redirectToRoute('web_employer_applicant_show', [
            'id' => $application->getId(),
        ]);
    }

    #[Route('/employer/applicant/{id}/reject', name: 'web_employer_applicant_reject', methods: ['POST'])]
    public function employerApplicantReject(
        Application $application,
        Request $request,
        EntityManagerInterface $em
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('hr_decision_' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $application->setStatus('REJECTED'); // or Application::STATUS_REJECTED
        $em->flush();

        $this->addFlash('success', 'Application rejected.');

        return $this->redirectToRoute('web_employer_applicant_show', [
            'id' => $application->getId(),
        ]);
    }

    #[Route('/employer/applicant/{id}/schedule-interview', name: 'web_employer_applicant_schedule', methods: ['POST'])]
    public function employerApplicantScheduleInterview(
        Application $application,
        Request $request,
        EntityManagerInterface $em
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('schedule_interview_' . $application->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $dateString = $request->request->get('interview_at');           // from <input type="datetime-local">
        $mode       = $request->request->get('interview_mode');
        $location   = $request->request->get('interview_location');
        $notes      = $request->request->get('interview_notes');

        if ($dateString) {
            // HTML datetime-local gives "YYYY-MM-DDTHH:MM"
            $application->setInterviewAt(new \DateTimeImmutable($dateString));
        }

        $application->setInterviewMode($mode ?: null);
        $application->setInterviewLocation($location ?: null);
        $application->setInterviewNotes($notes ?: null);

        // Optional: mark as interview scheduled
        $application->setStatus('INTERVIEW_SCHEDULED');
        $em->flush();

        $this->addFlash('success', 'Interview saved for this applicant.');

        return $this->redirectToRoute('web_employer_applicant_show', [
            'id' => $application->getId(),
        ]);
    }



} 
