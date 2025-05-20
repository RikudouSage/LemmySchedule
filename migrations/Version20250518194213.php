<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250518194213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE create_post_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image_id INTEGER DEFAULT NULL, community_id INTEGER NOT NULL, title CLOB NOT NULL, url CLOB DEFAULT NULL, text CLOB DEFAULT NULL, language INTEGER NOT NULL, nsfw BOOLEAN NOT NULL, pin_to_community BOOLEAN NOT NULL, pin_to_instance BOOLEAN NOT NULL, unpin_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
            , file_provider_id VARCHAR(255) DEFAULT NULL, check_for_url_duplicates BOOLEAN NOT NULL, comments CLOB NOT NULL --(DC2Type:json)
            , thumbnail_url VARCHAR(255) DEFAULT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL, schedule_expression VARCHAR(255) DEFAULT NULL, schedule_timezone VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_D2016F953DA5256D FOREIGN KEY (image_id) REFERENCES stored_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_D2016F953DA5256D ON create_post_stored_job (image_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE post_pin_unpin_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, pin_type INTEGER NOT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE stored_file (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, path VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE unread_post_report_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, community_id INTEGER DEFAULT NULL, person_id INTEGER DEFAULT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL, schedule_expression VARCHAR(255) DEFAULT NULL, schedule_timezone VARCHAR(255) DEFAULT NULL)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE stored_file
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE unread_post_report_stored_job
        SQL);
    }
}
