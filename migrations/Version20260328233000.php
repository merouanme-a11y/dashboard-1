<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260328233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les options d affichage du titre et du fil d ariane pour les pages dynamiques';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('page')) {
            return;
        }

        $table = $schema->getTable('page');

        if (!$table->hasColumn('show_title')) {
            $this->addSql('ALTER TABLE page ADD show_title TINYINT(1) NOT NULL DEFAULT 1');
        }

        if (!$table->hasColumn('show_breadcrumb')) {
            $this->addSql('ALTER TABLE page ADD show_breadcrumb TINYINT(1) NOT NULL DEFAULT 1');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('page')) {
            return;
        }

        $table = $schema->getTable('page');

        if ($table->hasColumn('show_breadcrumb')) {
            $this->addSql('ALTER TABLE page DROP show_breadcrumb');
        }

        if ($table->hasColumn('show_title')) {
            $this->addSql('ALTER TABLE page DROP show_title');
        }
    }
}
