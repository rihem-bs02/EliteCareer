-- JobHub schema (MySQL/MariaDB - XAMPP)
-- Save as: jobhub_schema.sql  |  Import with phpMyAdmin or mysql CLI

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS jobhub
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jobhub;

-- =========================
-- Core identity / auth
-- =========================
DROP TABLE IF EXISTS auth_access_token_blacklist;
DROP TABLE IF EXISTS auth_refresh_tokens;
DROP TABLE IF EXISTS match_results;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS resumes;
DROP TABLE IF EXISTS candidate_profiles;
DROP TABLE IF EXISTS company_members;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(255) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('CANDIDATE','HR','ADMIN') NOT NULL DEFAULT 'CANDIDATE',
  status          ENUM('ACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at   DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refresh tokens are stored hashed (recommended). Access tokens are usually JWT and not stored,
-- but you can blacklist revoked JWT "jti" (token id) in auth_access_token_blacklist.
CREATE TABLE auth_refresh_tokens (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  token_hash    CHAR(64) NOT NULL,         -- store SHA-256 of the refresh token
  issued_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NOT NULL,
  revoked_at    DATETIME NULL,
  user_agent    VARCHAR(255) NULL,
  ip_address    VARCHAR(45) NULL,          -- IPv4/IPv6
  device_label  VARCHAR(100) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refresh_token_hash (token_hash),
  KEY idx_refresh_user (user_id),
  KEY idx_refresh_exp (expires_at),
  CONSTRAINT fk_refresh_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_access_token_blacklist (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  jti         CHAR(36) NOT NULL,           -- JWT ID (UUID) or any unique token id
  expires_at  DATETIME NOT NULL,
  revoked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason      VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blacklist_jti (jti),
  KEY idx_blacklist_user (user_id),
  KEY idx_blacklist_exp (expires_at),
  CONSTRAINT fk_blacklist_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Companies & HR
-- =========================
CREATE TABLE companies (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(200) NOT NULL,
  website     VARCHAR(255) NULL,
  industry    VARCHAR(120) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links HR users to a company (keep it simple; one HR can belong to multiple companies if needed).
CREATE TABLE company_members (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id       BIGINT UNSIGNED NOT NULL,
  user_id          BIGINT UNSIGNED NOT NULL,
  role_in_company  ENUM('HR','COMPANY_ADMIN') NOT NULL DEFAULT 'HR',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_user (company_id, user_id),
  KEY idx_company_members_user (user_id),
  CONSTRAINT fk_member_company
    FOREIGN KEY (company_id) REFERENCES companies(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_member_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Candidates & resumes (PDF upload)
-- =========================
CREATE TABLE candidate_profiles (
  user_id     BIGINT UNSIGNED NOT NULL,
  first_name  VARCHAR(80) NULL,
  last_name   VARCHAR(80) NULL,
  phone       VARCHAR(30) NULL,
  location    VARCHAR(120) NULL,
  headline    VARCHAR(160) NULL,
  summary     TEXT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_candidate_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resumes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  candidate_user_id BIGINT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  storage_path      VARCHAR(500) NOT NULL,     -- store file path; keep PDFs in /uploads/resumes/
  mime_type         VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
  file_size_bytes   BIGINT UNSIGNED NOT NULL,
  sha256            CHAR(64) NOT NULL,         -- checksum to avoid duplicates
  extracted_text    LONGTEXT NULL,             -- optional: store parsed text for matching
  parsed_at         DATETIME NULL,
  is_default        TINYINT(1) NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_resume_candidate (candidate_user_id),
  KEY idx_resume_default (candidate_user_id, is_default),
  UNIQUE KEY uq_resume_sha (candidate_user_id, sha256),
  CONSTRAINT fk_resume_candidate
    FOREIGN KEY (candidate_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- Jobs, applications, and match results
-- =========================
CREATE TABLE jobs (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id      BIGINT UNSIGNED NOT NULL,
  posted_by_user_id BIGINT UNSIGNED NOT NULL,  -- HR user who posted
  title           VARCHAR(200) NOT NULL,
  location        VARCHAR(160) NULL,
  work_mode       ENUM('ONSITE','HYBRID','REMOTE') NOT NULL DEFAULT 'ONSITE',
  employment_type ENUM('FULL_TIME','PART_TIME','CONTRACT','INTERN','TEMP') NOT NULL DEFAULT 'FULL_TIME',
  description     LONGTEXT NOT NULL,
  requirements    LONGTEXT NULL,
  status          ENUM('DRAFT','PUBLISHED','CLOSED') NOT NULL DEFAULT 'DRAFT',
  published_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jobs_company (company_id),
  KEY idx_jobs_status_pub (status, published_at),
  KEY idx_jobs_posted_by (posted_by_user_id),
  CONSTRAINT fk_jobs_company
    FOREIGN KEY (company_id) REFERENCES companies(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_jobs_posted_by
    FOREIGN KEY (posted_by_user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applications (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id            BIGINT UNSIGNED NOT NULL,
  candidate_user_id BIGINT UNSIGNED NOT NULL,
  resume_id         BIGINT UNSIGNED NOT NULL,
  status            ENUM('SUBMITTED','IN_REVIEW','SHORTLISTED','REJECTED','HIRED','WITHDRAWN')
                    NOT NULL DEFAULT 'SUBMITTED',
  cover_letter      TEXT NULL,
  applied_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job_candidate (job_id, candidate_user_id),
  KEY idx_app_candidate (candidate_user_id),
  KEY idx_app_job (job_id),
  KEY idx_app_status (status),
  CONSTRAINT fk_app_job
    FOREIGN KEY (job_id) REFERENCES jobs(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_app_candidate
    FOREIGN KEY (candidate_user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_app_resume
    FOREIGN KEY (resume_id) REFERENCES resumes(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stores the matching output between job description and resume for an application.
CREATE TABLE match_results (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id    BIGINT UNSIGNED NOT NULL,
  algorithm_version VARCHAR(50) NOT NULL DEFAULT 'v1',
  match_score       DECIMAL(5,2) NOT NULL,     -- e.g. 0.00 to 100.00
  matched_keywords  JSON NULL,                 -- e.g. {"skills":["python","sql"],"missing":["docker"]}
  notes             TEXT NULL,
  computed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_match_app_algo (application_id, algorithm_version),
  KEY idx_match_score (match_score),
  CONSTRAINT fk_match_application
    FOREIGN KEY (application_id) REFERENCES applications(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helpful: ensure at most one default resume per candidate (enforce in app code; MySQL lacks partial unique indexes)
-- You can also create a BEFORE INSERT/UPDATE trigger if you want strict enforcement.
