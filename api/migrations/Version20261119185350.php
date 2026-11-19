<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119185350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema on MariaDB: Messenger queue, host, event, guest, queue item and vote';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE event (
              id BINARY(16) NOT NULL,
              name VARCHAR(120) NOT NULL,
              join_code VARCHAR(12) NOT NULL,
              status VARCHAR(255) DEFAULT 'draft' NOT NULL,
              scheduled_for DATE DEFAULT NULL,
              spotify_device_id VARCHAR(128) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              ended_at DATETIME DEFAULT NULL,
              settings_allow_downvotes TINYINT DEFAULT 1 NOT NULL,
              settings_rejection_threshold INT DEFAULT -5 NOT NULL,
              settings_max_pending_per_guest INT DEFAULT 5 NOT NULL,
              settings_replay_delay_minutes INT DEFAULT 120 NOT NULL,
              settings_block_explicit TINYINT DEFAULT 0 NOT NULL,
              settings_max_track_duration_ms INT DEFAULT 600000 NOT NULL,
              settings_host_approval TINYINT DEFAULT 0 NOT NULL,
              settings_lock_lead_ms INT DEFAULT 15000 NOT NULL,
              playback_is_playing TINYINT DEFAULT 0 NOT NULL,
              playback_track_id VARCHAR(64) DEFAULT NULL,
              playback_track_name VARCHAR(255) DEFAULT NULL,
              playback_track_artist VARCHAR(255) DEFAULT NULL,
              playback_cover_url LONGTEXT DEFAULT NULL,
              playback_duration_ms INT DEFAULT 0 NOT NULL,
              playback_progress_ms INT DEFAULT 0 NOT NULL,
              playback_updated_at DATETIME DEFAULT NULL,
              playback_from_queue TINYINT DEFAULT 0 NOT NULL,
              playback_device_name VARCHAR(255) DEFAULT NULL,
              playback_error VARCHAR(64) DEFAULT NULL,
              host_id BINARY(16) NOT NULL,
              INDEX idx_event_status (status),
              UNIQUE INDEX uniq_event_join_code (join_code),
              INDEX IDX_3BAE0AA71FB8D185 (host_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE guest (
              id BINARY(16) NOT NULL,
              pseudo VARCHAR(40) NOT NULL,
              session_token VARCHAR(64) NOT NULL,
              banned TINYINT DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL,
              last_seen_at DATETIME NOT NULL,
              event_id BINARY(16) NOT NULL,
              INDEX idx_guest_event (event_id),
              UNIQUE INDEX uniq_guest_session_token (session_token),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE host (
              id BINARY(16) NOT NULL,
              email VARCHAR(180) NOT NULL,
              password VARCHAR(255) NOT NULL,
              roles JSON NOT NULL,
              spotify_user_id VARCHAR(64) DEFAULT NULL,
              spotify_product VARCHAR(32) DEFAULT NULL,
              spotify_access_token LONGTEXT DEFAULT NULL,
              spotify_refresh_token LONGTEXT DEFAULT NULL,
              spotify_token_expires_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_host_email (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE queue_item (
              id BINARY(16) NOT NULL,
              status VARCHAR(255) DEFAULT 'pending' NOT NULL,
              pinned TINYINT DEFAULT 0 NOT NULL,
              score INT DEFAULT 0 NOT NULL,
              upvotes INT DEFAULT 0 NOT NULL,
              downvotes INT DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL,
              locked_at DATETIME DEFAULT NULL,
              played_at DATETIME DEFAULT NULL,
              track_spotify_id VARCHAR(64) NOT NULL,
              track_name VARCHAR(255) NOT NULL,
              track_artist VARCHAR(255) NOT NULL,
              track_album VARCHAR(255) DEFAULT NULL,
              track_cover_url LONGTEXT DEFAULT NULL,
              track_duration_ms INT DEFAULT 0 NOT NULL,
              track_explicit TINYINT DEFAULT 0 NOT NULL,
              event_id BINARY(16) NOT NULL,
              proposed_by_id BINARY(16) DEFAULT NULL,
              INDEX idx_queue_item_ranking (
                event_id, status, pinned, score, created_at
              ),
              INDEX idx_queue_item_track (event_id, track_spotify_id),
              INDEX IDX_BA4B6DE871F7E88B (event_id),
              INDEX IDX_BA4B6DE8DAB5A938 (proposed_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE vote (
              id BINARY(16) NOT NULL,
              value SMALLINT NOT NULL,
              updated_at DATETIME NOT NULL,
              queue_item_id BINARY(16) NOT NULL,
              guest_id BINARY(16) NOT NULL,
              UNIQUE INDEX uniq_vote_item_guest (queue_item_id, guest_id),
              INDEX IDX_5A108564F0EDC960 (queue_item_id),
              INDEX IDX_5A1085649A4AA658 (guest_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
              id BIGINT AUTO_INCREMENT NOT NULL,
              body LONGTEXT NOT NULL,
              headers LONGTEXT NOT NULL,
              queue_name VARCHAR(190) NOT NULL,
              created_at DATETIME NOT NULL,
              available_at DATETIME NOT NULL,
              delivered_at DATETIME DEFAULT NULL,
              INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (
                queue_name, available_at, delivered_at,
                id
              ),
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              event
            ADD
              CONSTRAINT FK_3BAE0AA71FB8D185 FOREIGN KEY (host_id) REFERENCES host (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              guest
            ADD
              CONSTRAINT FK_ACB79A3571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              queue_item
            ADD
              CONSTRAINT FK_BA4B6DE871F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              queue_item
            ADD
              CONSTRAINT FK_BA4B6DE8DAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES guest (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              vote
            ADD
              CONSTRAINT FK_5A108564F0EDC960 FOREIGN KEY (queue_item_id) REFERENCES queue_item (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              vote
            ADD
              CONSTRAINT FK_5A1085649A4AA658 FOREIGN KEY (guest_id) REFERENCES guest (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA71FB8D185');
        $this->addSql('ALTER TABLE guest DROP FOREIGN KEY FK_ACB79A3571F7E88B');
        $this->addSql('ALTER TABLE queue_item DROP FOREIGN KEY FK_BA4B6DE871F7E88B');
        $this->addSql('ALTER TABLE queue_item DROP FOREIGN KEY FK_BA4B6DE8DAB5A938');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564F0EDC960');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A1085649A4AA658');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE guest');
        $this->addSql('DROP TABLE host');
        $this->addSql('DROP TABLE queue_item');
        $this->addSql('DROP TABLE vote');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
