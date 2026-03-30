<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les colonnes de reinitialisation de mot de passe sur utilisateur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD reset_password_token VARCHAR(64) DEFAULT NULL, ADD reset_password_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur DROP reset_password_token, DROP reset_password_expires_at');
    }
}
