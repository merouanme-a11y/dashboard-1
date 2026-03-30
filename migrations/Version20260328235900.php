<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module Tickets Symfony';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'tickets', 'Tickets', 'bi-ticket-perforated', 'app_tickets', 1, 30
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'tickets')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module WHERE name = 'tickets'");
    }
}
