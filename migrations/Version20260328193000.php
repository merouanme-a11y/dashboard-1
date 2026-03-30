<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328193000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Ajoute le catalogue local des services et recopie les services connus';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('services')) {
            $this->addSql("CREATE TABLE services (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL DEFAULT '#6C757D', UNIQUE INDEX UNIQ_49A6B9A75E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }

        if ($schema->hasTable('service_color')) {
            $this->addSql("
                INSERT IGNORE INTO services (name, color)
                SELECT TRIM(name), CASE
                    WHEN UPPER(TRIM(color)) REGEXP '^#[0-9A-F]{6}$' THEN UPPER(TRIM(color))
                    ELSE '#6C757D'
                END
                FROM service_color
                WHERE name IS NOT NULL AND TRIM(name) <> ''
            ");
        }

        if ($schema->hasTable('utilisateur')) {
            $this->addSql("
                INSERT IGNORE INTO services (name, color)
                SELECT DISTINCT TRIM(service), '#6C757D'
                FROM utilisateur
                WHERE service IS NOT NULL AND TRIM(service) <> ''
            ");
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('services')) {
            $this->addSql('DROP TABLE services');
        }
    }
}
