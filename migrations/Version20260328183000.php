<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module de gestion des menus Symfony';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'menus', 'Gestion du menu', 'bi-folder', 'admin_menus', 1, 220
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'menus')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module WHERE name = 'menus'");
    }
}
