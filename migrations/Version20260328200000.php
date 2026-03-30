<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module de gestion des utilisateurs Symfony';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'utilisateurs', 'Utilisateurs', 'bi-people', 'admin_utilisateurs', 1, 230
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'utilisateurs')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module WHERE name = 'utilisateurs'");
    }
}
