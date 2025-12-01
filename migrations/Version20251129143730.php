<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251129143730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE applications (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, status VARCHAR(20) DEFAULT \'SUBMITTED\' NOT NULL, cover_letter LONGTEXT DEFAULT NULL, applied_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, job_id BIGINT UNSIGNED NOT NULL, candidate_user_id BIGINT UNSIGNED NOT NULL, resume_id BIGINT UNSIGNED NOT NULL, INDEX IDX_F7C966F0BE04EA9 (job_id), INDEX IDX_F7C966F028BF7E34 (candidate_user_id), INDEX IDX_F7C966F0D262AF09 (resume_id), UNIQUE INDEX uq_job_candidate (job_id, candidate_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE auth_access_token_blacklist (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, jti VARCHAR(36) NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME NOT NULL, reason VARCHAR(255) DEFAULT NULL, user_id BIGINT UNSIGNED NOT NULL, INDEX IDX_C469F722A76ED395 (user_id), UNIQUE INDEX uq_blacklist_jti (jti), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE auth_refresh_tokens (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, issued_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, device_label VARCHAR(100) DEFAULT NULL, user_id BIGINT UNSIGNED NOT NULL, INDEX IDX_861C6459A76ED395 (user_id), UNIQUE INDEX uq_refresh_token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE candidate_profiles (first_name VARCHAR(80) DEFAULT NULL, last_name VARCHAR(80) DEFAULT NULL, phone VARCHAR(30) DEFAULT NULL, location VARCHAR(120) DEFAULT NULL, headline VARCHAR(160) DEFAULT NULL, summary LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL, user_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE companies (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, website VARCHAR(255) DEFAULT NULL, industry VARCHAR(120) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uq_company_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company_members (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, role_in_company VARCHAR(20) DEFAULT \'HR\' NOT NULL, created_at DATETIME NOT NULL, company_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, INDEX IDX_65F2C828979B1AD6 (company_id), INDEX IDX_65F2C828A76ED395 (user_id), UNIQUE INDEX uq_company_user (company_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE jobs (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, location VARCHAR(160) DEFAULT NULL, work_mode VARCHAR(20) DEFAULT \'ONSITE\' NOT NULL, employment_type VARCHAR(20) DEFAULT \'FULL_TIME\' NOT NULL, description LONGTEXT, requirements LONGTEXT, status VARCHAR(20) DEFAULT \'DRAFT\' NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id BIGINT UNSIGNED NOT NULL, posted_by_user_id BIGINT UNSIGNED NOT NULL, INDEX IDX_A8936DC5979B1AD6 (company_id), INDEX IDX_A8936DC512CA0262 (posted_by_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE match_results (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, algorithm_version VARCHAR(50) DEFAULT \'v1\' NOT NULL, match_score NUMERIC(5, 2) NOT NULL, matched_keywords JSON DEFAULT NULL, notes LONGTEXT DEFAULT NULL, computed_at DATETIME NOT NULL, application_id BIGINT UNSIGNED NOT NULL, INDEX IDX_E805BB7B3E030ACD (application_id), UNIQUE INDEX uq_match_app_algo (application_id, algorithm_version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE resumes (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, original_filename VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) DEFAULT \'application/pdf\' NOT NULL, file_size_bytes BIGINT UNSIGNED NOT NULL, sha256 VARCHAR(64) NOT NULL, extracted_text LONGTEXT, parsed_at DATETIME DEFAULT NULL, is_default TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, candidate_user_id BIGINT UNSIGNED NOT NULL, INDEX IDX_CDB8AD3328BF7E34 (candidate_user_id), UNIQUE INDEX uq_resume_sha (candidate_user_id, sha256), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, roles JSON NOT NULL, status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, UNIQUE INDEX uq_users_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE applications ADD CONSTRAINT FK_F7C966F0BE04EA9 FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE applications ADD CONSTRAINT FK_F7C966F028BF7E34 FOREIGN KEY (candidate_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE applications ADD CONSTRAINT FK_F7C966F0D262AF09 FOREIGN KEY (resume_id) REFERENCES resumes (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE auth_access_token_blacklist ADD CONSTRAINT FK_C469F722A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE auth_refresh_tokens ADD CONSTRAINT FK_861C6459A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidate_profiles ADD CONSTRAINT FK_2A6EC7E3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_members ADD CONSTRAINT FK_65F2C828979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_members ADD CONSTRAINT FK_65F2C828A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE jobs ADD CONSTRAINT FK_A8936DC5979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE jobs ADD CONSTRAINT FK_A8936DC512CA0262 FOREIGN KEY (posted_by_user_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE match_results ADD CONSTRAINT FK_E805BB7B3E030ACD FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE resumes ADD CONSTRAINT FK_CDB8AD3328BF7E34 FOREIGN KEY (candidate_user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE applications DROP FOREIGN KEY FK_F7C966F0BE04EA9');
        $this->addSql('ALTER TABLE applications DROP FOREIGN KEY FK_F7C966F028BF7E34');
        $this->addSql('ALTER TABLE applications DROP FOREIGN KEY FK_F7C966F0D262AF09');
        $this->addSql('ALTER TABLE auth_access_token_blacklist DROP FOREIGN KEY FK_C469F722A76ED395');
        $this->addSql('ALTER TABLE auth_refresh_tokens DROP FOREIGN KEY FK_861C6459A76ED395');
        $this->addSql('ALTER TABLE candidate_profiles DROP FOREIGN KEY FK_2A6EC7E3A76ED395');
        $this->addSql('ALTER TABLE company_members DROP FOREIGN KEY FK_65F2C828979B1AD6');
        $this->addSql('ALTER TABLE company_members DROP FOREIGN KEY FK_65F2C828A76ED395');
        $this->addSql('ALTER TABLE jobs DROP FOREIGN KEY FK_A8936DC5979B1AD6');
        $this->addSql('ALTER TABLE jobs DROP FOREIGN KEY FK_A8936DC512CA0262');
        $this->addSql('ALTER TABLE match_results DROP FOREIGN KEY FK_E805BB7B3E030ACD');
        $this->addSql('ALTER TABLE resumes DROP FOREIGN KEY FK_CDB8AD3328BF7E34');
        $this->addSql('DROP TABLE applications');
        $this->addSql('DROP TABLE auth_access_token_blacklist');
        $this->addSql('DROP TABLE auth_refresh_tokens');
        $this->addSql('DROP TABLE candidate_profiles');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE company_members');
        $this->addSql('DROP TABLE jobs');
        $this->addSql('DROP TABLE match_results');
        $this->addSql('DROP TABLE resumes');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
