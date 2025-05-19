<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519140742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE community_group (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, community_ids CLOB NOT NULL --(DC2Type:json)
            , user_id VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_16B03E81A76ED395 ON community_group (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__create_post_stored_job AS SELECT id, image_id, community_id, title, url, text, language, nsfw, pin_to_community, pin_to_instance, unpin_at, file_provider_id, check_for_url_duplicates, comments, thumbnail_url, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE create_post_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image_id INTEGER DEFAULT NULL, community_id INTEGER NOT NULL, title CLOB NOT NULL, url CLOB DEFAULT NULL, text CLOB DEFAULT NULL, language INTEGER NOT NULL, nsfw BOOLEAN NOT NULL, pin_to_community BOOLEAN NOT NULL, pin_to_instance BOOLEAN NOT NULL, unpin_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
            , file_provider_id VARCHAR(255) DEFAULT NULL, check_for_url_duplicates BOOLEAN NOT NULL, comments CLOB NOT NULL --(DC2Type:json)
            , thumbnail_url VARCHAR(255) DEFAULT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL, schedule_expression VARCHAR(255) DEFAULT NULL, schedule_timezone VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_D2016F953DA5256D FOREIGN KEY (image_id) REFERENCES stored_file (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO create_post_stored_job (id, image_id, community_id, title, url, text, language, nsfw, pin_to_community, pin_to_instance, unpin_at, file_provider_id, check_for_url_duplicates, comments, thumbnail_url, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone) SELECT id, image_id, community_id, title, url, text, language, nsfw, pin_to_community, pin_to_instance, unpin_at, file_provider_id, check_for_url_duplicates, comments, thumbnail_url, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM __temp__create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_D2016F953DA5256D ON create_post_stored_job (image_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_D2016F95A76ED395 ON create_post_stored_job (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__post_pin_unpin_stored_job AS SELECT id, post_id, pin_type, jwt, instance, user_id, scheduled_at, timezone_name FROM post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE post_pin_unpin_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, pin_type INTEGER NOT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO post_pin_unpin_stored_job (id, post_id, pin_type, jwt, instance, user_id, scheduled_at, timezone_name) SELECT id, post_id, pin_type, jwt, instance, user_id, scheduled_at, timezone_name FROM __temp__post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9FBAD882A76ED395 ON post_pin_unpin_stored_job (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__unread_post_report_stored_job AS SELECT id, community_id, person_id, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM unread_post_report_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE unread_post_report_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE unread_post_report_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, community_id INTEGER DEFAULT NULL, person_id INTEGER DEFAULT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL, schedule_expression VARCHAR(255) DEFAULT NULL, schedule_timezone VARCHAR(255) DEFAULT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO unread_post_report_stored_job (id, community_id, person_id, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone) SELECT id, community_id, person_id, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM __temp__unread_post_report_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__unread_post_report_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_333BE259A76ED395 ON unread_post_report_stored_job (user_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE community_group
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__create_post_stored_job AS SELECT id, image_id, community_id, title, url, text, language, nsfw, pin_to_community, pin_to_instance, unpin_at, file_provider_id, check_for_url_duplicates, comments, thumbnail_url, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE create_post_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, image_id INTEGER DEFAULT NULL, community_id INTEGER NOT NULL, title CLOB NOT NULL, url CLOB DEFAULT NULL, text CLOB DEFAULT NULL, language INTEGER NOT NULL, nsfw BOOLEAN NOT NULL, pin_to_community BOOLEAN NOT NULL, pin_to_instance BOOLEAN NOT NULL, unpin_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
            , file_provider_id VARCHAR(255) DEFAULT NULL, check_for_url_duplicates BOOLEAN NOT NULL, comments CLOB NOT NULL --(DC2Type:json)
            , thumbnail_url VARCHAR(255) DEFAULT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL, schedule_expression VARCHAR(255) DEFAULT NULL, schedule_timezone VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_D2016F953DA5256D FOREIGN KEY (image_id) REFERENCES stored_file (id) NOT DEFERRABLE INITIALLY IMMEDIATE)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO create_post_stored_job (id, image_id, community_id, title, url, text, language, nsfw, pin_to_community, pin_to_instance, unpin_at, file_provider_id, check_for_url_duplicates, comments, thumbnail_url, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone) SELECT id, image_id, community_id, title, url, text, language, nsfw, pin_to_community, pin_to_instance, unpin_at, file_provider_id, check_for_url_duplicates, comments, thumbnail_url, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM __temp__create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__create_post_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_D2016F953DA5256D ON create_post_stored_job (image_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__post_pin_unpin_stored_job AS SELECT id, post_id, pin_type, jwt, instance, user_id, scheduled_at, timezone_name FROM post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE post_pin_unpin_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, post_id INTEGER NOT NULL, pin_type INTEGER NOT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO post_pin_unpin_stored_job (id, post_id, pin_type, jwt, instance, user_id, scheduled_at, timezone_name) SELECT id, post_id, pin_type, jwt, instance, user_id, scheduled_at, timezone_name FROM __temp__post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__post_pin_unpin_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__unread_post_report_stored_job AS SELECT id, community_id, person_id, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM unread_post_report_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE unread_post_report_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE unread_post_report_stored_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, community_id INTEGER DEFAULT NULL, person_id INTEGER DEFAULT NULL, jwt VARCHAR(255) NOT NULL, instance VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, scheduled_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , timezone_name VARCHAR(255) NOT NULL, schedule_expression VARCHAR(255) DEFAULT NULL, schedule_timezone VARCHAR(255) DEFAULT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO unread_post_report_stored_job (id, community_id, person_id, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone) SELECT id, community_id, person_id, jwt, instance, user_id, scheduled_at, timezone_name, schedule_expression, schedule_timezone FROM __temp__unread_post_report_stored_job
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__unread_post_report_stored_job
        SQL);
    }
}
