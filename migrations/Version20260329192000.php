<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329192000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module Stats Symfony';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'stats', 'Statistiques', 'bi-bar-chart', 'app_stats', 1, 31
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'stats')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module WHERE name = 'stats'");
    }
}
