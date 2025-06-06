<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250520195934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__community_group AS SELECT id, name, community_ids, user_id FROM community_group
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE community_group
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE community_group (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, community_ids CLOB NOT NULL --(DC2Type:json)
            , user_id VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO community_group (id, name, community_ids, user_id) SELECT id, name, community_ids, user_id FROM __temp__community_group
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__community_group
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_16B03E81A76ED395 ON community_group (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_16B03E815E237E06A76ED395 ON community_group (name, user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__counter AS SELECT id, name, value, increment_by, user_id FROM counter
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE counter
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE counter (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value INTEGER NOT NULL, increment_by INTEGER NOT NULL, user_id VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO counter (id, name, value, increment_by, user_id) SELECT id, name, value, increment_by, user_id FROM __temp__counter
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__counter
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C1229478A76ED395 ON counter (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_C12294785E237E06A76ED395 ON counter (name, user_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__community_group AS SELECT id, name, community_ids, user_id FROM community_group
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE community_group
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE community_group (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, community_ids CLOB NOT NULL --(DC2Type:json)
            , user_id VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO community_group (id, name, community_ids, user_id) SELECT id, name, community_ids, user_id FROM __temp__community_group
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__community_group
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_16B03E81A76ED395 ON community_group (user_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__counter AS SELECT id, name, value, increment_by, user_id FROM counter
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE counter
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE counter (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value INTEGER NOT NULL, increment_by INTEGER NOT NULL, user_id VARCHAR(255) NOT NULL)
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO counter (id, name, value, increment_by, user_id) SELECT id, name, value, increment_by, user_id FROM __temp__counter
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE __temp__counter
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C1229478A76ED395 ON counter (user_id)
        SQL);
    }
}
