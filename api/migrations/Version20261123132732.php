<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123132732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Limit of active downvotes per guest in the event settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD settings_max_active_downvotes_per_guest INT DEFAULT 3 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP settings_max_active_downvotes_per_guest');
    }
}
