<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261016132908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Core domain: host, event, guest, queue item and vote';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event (id UUID NOT NULL, name VARCHAR(120) NOT NULL, join_code VARCHAR(12) NOT NULL, status VARCHAR(255) DEFAULT \'draft\' NOT NULL, scheduled_for DATE DEFAULT NULL, spotify_device_id VARCHAR(128) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, settings_allow_downvotes BOOLEAN DEFAULT true NOT NULL, settings_rejection_threshold INT DEFAULT -5 NOT NULL, settings_max_pending_per_guest INT DEFAULT 5 NOT NULL, settings_replay_delay_minutes INT DEFAULT 120 NOT NULL, settings_block_explicit BOOLEAN DEFAULT false NOT NULL, settings_max_track_duration_ms INT DEFAULT 600000 NOT NULL, settings_host_approval BOOLEAN DEFAULT false NOT NULL, settings_lock_lead_ms INT DEFAULT 15000 NOT NULL, playback_is_playing BOOLEAN DEFAULT false NOT NULL, playback_track_id VARCHAR(64) DEFAULT NULL, playback_track_name VARCHAR(255) DEFAULT NULL, playback_track_artist VARCHAR(255) DEFAULT NULL, playback_cover_url TEXT DEFAULT NULL, playback_duration_ms INT DEFAULT 0 NOT NULL, playback_progress_ms INT DEFAULT 0 NOT NULL, playback_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, playback_from_queue BOOLEAN DEFAULT false NOT NULL, playback_device_name VARCHAR(255) DEFAULT NULL, playback_error VARCHAR(64) DEFAULT NULL, host_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_event_status ON event (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_event_join_code ON event (join_code)');
        $this->addSql('CREATE INDEX IDX_3BAE0AA71FB8D185 ON event (host_id)');
        $this->addSql('CREATE TABLE guest (id UUID NOT NULL, pseudo VARCHAR(40) NOT NULL, session_token VARCHAR(64) NOT NULL, banned BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, event_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_guest_event ON guest (event_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_guest_session_token ON guest (session_token)');
        $this->addSql('CREATE TABLE host (id UUID NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, spotify_user_id VARCHAR(64) DEFAULT NULL, spotify_product VARCHAR(32) DEFAULT NULL, spotify_access_token TEXT DEFAULT NULL, spotify_refresh_token TEXT DEFAULT NULL, spotify_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_host_email ON host (email)');
        $this->addSql('CREATE TABLE queue_item (id UUID NOT NULL, status VARCHAR(255) DEFAULT \'pending\' NOT NULL, pinned BOOLEAN DEFAULT false NOT NULL, score INT DEFAULT 0 NOT NULL, upvotes INT DEFAULT 0 NOT NULL, downvotes INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, locked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, played_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, track_spotify_id VARCHAR(64) NOT NULL, track_name VARCHAR(255) NOT NULL, track_artist VARCHAR(255) NOT NULL, track_album VARCHAR(255) DEFAULT NULL, track_cover_url TEXT DEFAULT NULL, track_duration_ms INT DEFAULT 0 NOT NULL, track_explicit BOOLEAN DEFAULT false NOT NULL, event_id UUID NOT NULL, proposed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_queue_item_ranking ON queue_item (event_id, status, pinned, score, created_at)');
        $this->addSql('CREATE INDEX idx_queue_item_track ON queue_item (event_id, track_spotify_id)');
        $this->addSql('CREATE INDEX IDX_BA4B6DE871F7E88B ON queue_item (event_id)');
        $this->addSql('CREATE INDEX IDX_BA4B6DE8DAB5A938 ON queue_item (proposed_by_id)');
        $this->addSql('CREATE TABLE vote (id UUID NOT NULL, value SMALLINT NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, queue_item_id UUID NOT NULL, guest_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_vote_item_guest ON vote (queue_item_id, guest_id)');
        $this->addSql('CREATE INDEX IDX_5A108564F0EDC960 ON vote (queue_item_id)');
        $this->addSql('CREATE INDEX IDX_5A1085649A4AA658 ON vote (guest_id)');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA71FB8D185 FOREIGN KEY (host_id) REFERENCES host (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE guest ADD CONSTRAINT FK_ACB79A3571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE queue_item ADD CONSTRAINT FK_BA4B6DE871F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE queue_item ADD CONSTRAINT FK_BA4B6DE8DAB5A938 FOREIGN KEY (proposed_by_id) REFERENCES guest (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564F0EDC960 FOREIGN KEY (queue_item_id) REFERENCES queue_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A1085649A4AA658 FOREIGN KEY (guest_id) REFERENCES guest (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA71FB8D185');
        $this->addSql('ALTER TABLE guest DROP CONSTRAINT FK_ACB79A3571F7E88B');
        $this->addSql('ALTER TABLE queue_item DROP CONSTRAINT FK_BA4B6DE871F7E88B');
        $this->addSql('ALTER TABLE queue_item DROP CONSTRAINT FK_BA4B6DE8DAB5A938');
        $this->addSql('ALTER TABLE vote DROP CONSTRAINT FK_5A108564F0EDC960');
        $this->addSql('ALTER TABLE vote DROP CONSTRAINT FK_5A1085649A4AA658');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE guest');
        $this->addSql('DROP TABLE host');
        $this->addSql('DROP TABLE queue_item');
        $this->addSql('DROP TABLE vote');
    }
}
