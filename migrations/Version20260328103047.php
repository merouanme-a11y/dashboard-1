<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328103047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE page_icon (id INT AUTO_INCREMENT NOT NULL, page_path VARCHAR(100) NOT NULL, icon VARCHAR(255) NOT NULL, icon_library VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_E25035018C9097D5 (page_path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE page_title (id INT AUTO_INCREMENT NOT NULL, page_path VARCHAR(100) NOT NULL, display_name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_4DB787BD8C9097D5 (page_path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE permission (id INT AUTO_INCREMENT NOT NULL, page_path VARCHAR(100) NOT NULL, role VARCHAR(255) DEFAULT NULL, is_allowed TINYINT NOT NULL, utilisateur_id INT DEFAULT NULL, INDEX IDX_E04992AAFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AAFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE permission DROP FOREIGN KEY FK_E04992AAFB88E14F');
        $this->addSql('DROP TABLE page_icon');
        $this->addSql('DROP TABLE page_title');
        $this->addSql('DROP TABLE permission');
    }
}
