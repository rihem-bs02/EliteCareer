<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106102010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jobs CHANGE description description LONGTEXT');
        $this->addSql('DROP INDEX uq_match_app_algo ON match_results');
        $this->addSql('ALTER TABLE match_results ADD engine_name VARCHAR(100) NOT NULL, ADD decision VARCHAR(50) NOT NULL, ADD overall_score INT NOT NULL, ADD raw_payload JSON DEFAULT NULL, DROP algorithm_version, DROP match_score, DROP notes, CHANGE matched_keywords scores JSON DEFAULT NULL, CHANGE computed_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jobs CHANGE description description LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE match_results ADD algorithm_version VARCHAR(50) DEFAULT \'v1\' NOT NULL, ADD match_score NUMERIC(5, 2) NOT NULL, ADD matched_keywords JSON DEFAULT NULL, ADD notes LONGTEXT DEFAULT NULL, DROP engine_name, DROP decision, DROP overall_score, DROP scores, DROP raw_payload, CHANGE created_at computed_at DATETIME NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_match_app_algo ON match_results (application_id, algorithm_version)');
    }
}
