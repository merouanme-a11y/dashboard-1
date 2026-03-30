<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enregistre les modules profile et annuaire pour le dashboard Symfony.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'annuaire', 'Annuaire', 'bi bi-person-lines-fill', 'app_annuaire', 1, 10
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'annuaire')");

        $this->addSql("INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'profile', 'Mon Profil', 'bi bi-person-circle', 'app_profile', 1, 20
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'profile')");

        $this->addSql("UPDATE module
            SET label = CASE name
                WHEN 'annuaire' THEN 'Annuaire'
                WHEN 'profile' THEN 'Mon Profil'
                ELSE label
            END,
            icon = CASE name
                WHEN 'annuaire' THEN 'bi bi-person-lines-fill'
                WHEN 'profile' THEN 'bi bi-person-circle'
                ELSE icon
            END,
            route_name = CASE name
                WHEN 'annuaire' THEN 'app_annuaire'
                WHEN 'profile' THEN 'app_profile'
                ELSE route_name
            END,
            sort_order = CASE name
                WHEN 'annuaire' THEN 10
                WHEN 'profile' THEN 20
                ELSE sort_order
            END
            WHERE name IN ('annuaire', 'profile')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module WHERE name IN ('annuaire', 'profile')");
    }
}
