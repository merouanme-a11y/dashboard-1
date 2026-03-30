<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default modules and theme settings';
    }

    public function up(Schema $schema): void
    {
        // Seed modules - only profile (utilisateur)
        $modules = [
            ['name' => 'profile', 'label' => 'Mon Profil', 'icon' => 'bi bi-person-circle', 'route' => 'app_profile', 'sort' => 0],
        ];

        foreach ($modules as $module) {
            $this->addSql(
                'INSERT INTO module (name, label, icon, route_name, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)',
                [$module['name'], $module['label'], $module['icon'], $module['route'], $module['sort']]
            );
        }

        // Seed default theme settings
        $themeSettings = [
            ['key' => 'active_template', 'value' => 'template2'],
            ['key' => 'site_title', 'value' => 'Dashboard ADEP'],
            ['key' => 'site_tagline', 'value' => 'Tableau de bord d\'entreprise'],
            ['key' => 'logo_path', 'value' => ''],
            ['key' => 'logo_size', 'value' => '40'],
            ['key' => 'sticky_header_enabled', 'value' => '1'],
            ['key' => 'primary_button_color', 'value' => '#3b82f6'],
            ['key' => 'dark_primary_button_color', 'value' => '#3b82f6'],
        ];

        foreach ($themeSettings as $setting) {
            $this->addSql(
                'INSERT INTO theme_setting (setting_key, setting_value) VALUES (?, ?)',
                [$setting['key'], $setting['value']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM module');
        $this->addSql('DELETE FROM theme_setting');
    }
}
