<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les pages dynamiques Symfony avec leur module d administration';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('page')) {
            $this->addSql('CREATE TABLE page (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, slug VARCHAR(160) NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, keywords VARCHAR(500) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_140AB620989D9B62 (slug), INDEX IDX_140AB620B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $this->addSql("
            INSERT INTO module (name, label, icon, route_name, is_active, sort_order)
            SELECT 'pages', 'Pages', 'bi-window-stack', 'admin_pages', 1, 225
            WHERE NOT EXISTS (SELECT 1 FROM module WHERE name = 'pages')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM module WHERE name = 'pages'");

        if ($schema->hasTable('page')) {
            $this->addSql('DROP TABLE page');
        }
    }
}
