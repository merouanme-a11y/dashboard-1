<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add page icon color and seed admin modules for page titles and page icons.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_icon ADD color VARCHAR(7) DEFAULT NULL');

        $this->addSql("
            INSERT INTO theme_setting (setting_key, setting_value)
            SELECT 'page_icon_library',
                   COALESCE(
                       (SELECT icon_library FROM page_icon WHERE icon_library IS NOT NULL AND icon_library <> '' ORDER BY id ASC LIMIT 1),
                       'bootstrap'
                   )
            WHERE NOT EXISTS (SELECT 1 FROM theme_setting WHERE setting_key = 'page_icon_library')
        ");

        $this->addSql("
            INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'page_title_icons', 'Icones des pages', 'bi-images', 'admin_page_icons', 1, 200
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'page_title_icons')
        ");

        $this->addSql("
            INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'page_titles', 'Libelles des pages', 'bi-pencil-square', 'admin_page_titles', 1, 210
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'page_titles')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module WHERE name IN ('page_title_icons', 'page_titles')");
        $this->addSql("DELETE FROM theme_setting WHERE setting_key = 'page_icon_library'");
        $this->addSql('ALTER TABLE page_icon DROP color');
    }
}
