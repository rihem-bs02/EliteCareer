<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106200609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE applications ADD interview_at DATETIME DEFAULT NULL, ADD interview_mode VARCHAR(50) DEFAULT NULL, ADD interview_location VARCHAR(255) DEFAULT NULL, ADD interview_notes LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(30) DEFAULT \'SUBMITTED\' NOT NULL');
        $this->addSql('ALTER TABLE jobs CHANGE description description LONGTEXT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE applications DROP interview_at, DROP interview_mode, DROP interview_location, DROP interview_notes, CHANGE status status VARCHAR(20) DEFAULT \'SUBMITTED\' NOT NULL');
        $this->addSql('ALTER TABLE jobs CHANGE description description LONGTEXT DEFAULT NULL');
    }
}
