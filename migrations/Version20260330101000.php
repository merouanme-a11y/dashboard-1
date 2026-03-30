<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260330101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le stockage BDD des preferences utilisateur par page pour les statistiques';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_page_preference (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, page_key VARCHAR(100) NOT NULL, preference_payload JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_USER_PAGE_PREF_USER (utilisateur_id), UNIQUE INDEX uniq_user_page_pref_user_page (utilisateur_id, page_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_page_preference ADD CONSTRAINT FK_USER_PAGE_PREF_USER FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_page_preference');
    }
}
