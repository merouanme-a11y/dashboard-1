<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove all modules except profile (utilisateur)';
    }

    public function up(Schema $schema): void
    {
        // Delete all modules except profile
        $this->addSql("DELETE FROM module WHERE name != 'profile'");
        
        // Ensure profile has sort_order 0
        $this->addSql("UPDATE module SET sort_order = 0 WHERE name = 'profile'");
    }

    public function down(Schema $schema): void
    {
        // Restore modules on rollback
        $modules = [
            ['name' => 'dashboard', 'label' => 'Accueil', 'icon' => 'bi bi-house-door', 'route' => 'app_dashboard', 'sort' => 0],
            ['name' => 'rh', 'label' => 'RH', 'icon' => 'bi bi-people', 'route' => 'app_rh', 'sort' => 1],
            ['name' => 'compta', 'label' => 'Compta', 'icon' => 'bi bi-file-earmark-pdf', 'route' => 'app_compta', 'sort' => 2],
            ['name' => 'production', 'label' => 'Production', 'icon' => 'bi bi-factory', 'route' => 'app_production', 'sort' => 3],
            ['name' => 'prestation', 'label' => 'Prestation', 'icon' => 'bi bi-briefcase', 'route' => 'app_prestation', 'sort' => 4],
            ['name' => 'sinistre', 'label' => 'Sinistre', 'icon' => 'bi bi-exclamation-triangle', 'route' => 'app_sinistre', 'sort' => 5],
            ['name' => 'serviceentreprise', 'label' => 'Service Entreprise', 'icon' => 'bi bi-building', 'route' => 'app_serviceentreprise', 'sort' => 6],
            ['name' => 'controleinterne', 'label' => 'Contrôle Interne', 'icon' => 'bi bi-shield-check', 'route' => 'app_controleinterne', 'sort' => 7],
            ['name' => 'communication', 'label' => 'Communication', 'icon' => 'bi bi-megaphone', 'route' => 'app_communication', 'sort' => 8],
            ['name' => 'relationclient', 'label' => 'Relation Client', 'icon' => 'bi bi-handshake', 'route' => 'app_relationclient', 'sort' => 9],
            ['name' => 'marketing', 'label' => 'Marketing', 'icon' => 'bi bi-graph-up', 'route' => 'app_marketing', 'sort' => 10],
            ['name' => 'vente', 'label' => 'Vente', 'icon' => 'bi bi-cart', 'route' => 'app_vente', 'sort' => 11],
            ['name' => 'annuaire', 'label' => 'Annuaire', 'icon' => 'bi bi-book', 'route' => 'app_annuaire', 'sort' => 13],
        ];

        foreach ($modules as $module) {
            $this->addSql(
                'INSERT INTO module (name, label, icon, route_name, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)',
                [$module['name'], $module['label'], $module['icon'], $module['route'], $module['sort']]
            );
        }
    }
}
