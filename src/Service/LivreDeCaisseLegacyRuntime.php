<?php

namespace App\Service;

use App\Entity\Utilisateur;
use PDO;
use Symfony\Component\HttpKernel\KernelInterface;

class LivreDeCaisseLegacyRuntime
{
    private bool $booted = false;

    public function __construct(private KernelInterface $kernel) {}

    public function bootForUser(?Utilisateur $user): PDO
    {
        $this->boot();

        if ($user instanceof Utilisateur) {
            app_set_current_livre_de_caisse_user($this->createFrontendUserPayload($user));
        } else {
            app_set_current_livre_de_caisse_user(null);
        }

        $pdo = \Database::connect();
        $this->ensureSchema($pdo);

        return $pdo;
    }

    public function createFrontendUserPayload(Utilisateur $user): array
    {
        $displayName = trim((string) ($user->getPrenom() ?? '') . ' ' . (string) ($user->getNom() ?? ''));
        $email = trim((string) ($user->getEmail() ?? ''));

        return [
            'id' => (string) ($user->getId() ?? ''),
            'username' => $email !== '' ? $email : (string) ($user->getId() ?? ''),
            'displayName' => $displayName !== '' ? $displayName : ($email !== '' ? $email : 'Utilisateur'),
            'email' => $email,
            'prenom' => (string) ($user->getPrenom() ?? ''),
            'nom' => (string) ($user->getNom() ?? ''),
            'agence' => trim((string) ($user->getAgence() ?? '')),
            'departement' => trim((string) ($user->getDepartement() ?? '')),
        ];
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $projectDir = $this->kernel->getProjectDir();

        require_once $projectDir . '/src/Legacy/LivreDeCaisse/runtime.php';
        require_once $projectDir . '/src/Legacy/LivreDeCaisse/module.php';

        $this->booted = true;
    }

    private function ensureSchema(PDO $pdo): void
    {
        static $schemaReady = false;

        if ($schemaReady) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS livredecaisse (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                record_type ENUM("entry", "daily_state", "attachment") NOT NULL DEFAULT "entry",
                reference_key VARCHAR(64) DEFAULT NULL,
                attachment_entry_id BIGINT UNSIGNED DEFAULT NULL,
                business_date DATE NOT NULL,
                date_saisie DATETIME DEFAULT NULL,
                chrono INT DEFAULT NULL,
                type_affaire VARCHAR(64) NOT NULL DEFAULT "",
                risque VARCHAR(128) NOT NULL DEFAULT "",
                nom_adherent VARCHAR(160) NOT NULL DEFAULT "",
                prenom_adherent VARCHAR(160) NOT NULL DEFAULT "",
                saisie_oa VARCHAR(3) NOT NULL DEFAULT "Non",
                type_encaissement VARCHAR(64) NOT NULL DEFAULT "",
                montant DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                num_cheque VARCHAR(120) NOT NULL DEFAULT "",
                num_contrat VARCHAR(120) NOT NULL DEFAULT "",
                date_reglement DATE DEFAULT NULL,
                mois_anticipation VARCHAR(255) NOT NULL DEFAULT "",
                reglement_avis VARCHAR(3) NOT NULL DEFAULT "Non",
                avenant VARCHAR(3) NOT NULL DEFAULT "Non",
                regul_impaye VARCHAR(3) NOT NULL DEFAULT "Non",
                regul_mise_demeure VARCHAR(3) NOT NULL DEFAULT "Non",
                num_adhesion VARCHAR(120) NOT NULL DEFAULT "",
                date_effet DATE DEFAULT NULL,
                formule_produit VARCHAR(255) NOT NULL DEFAULT "",
                mandataire VARCHAR(255) NOT NULL DEFAULT "",
                dsu VARCHAR(255) NOT NULL DEFAULT "",
                especes DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                cheque DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                cb DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                comptant_prelever DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                comptant_offert DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                appel_cotisation DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                num_remise_especes VARCHAR(120) NOT NULL DEFAULT "",
                num_remise_cheque VARCHAR(120) NOT NULL DEFAULT "",
                attachment_file_name VARCHAR(255) NOT NULL DEFAULT "",
                attachment_mime VARCHAR(120) NOT NULL DEFAULT "",
                attachment_size INT UNSIGNED NOT NULL DEFAULT 0,
                attachment_blob LONGBLOB DEFAULT NULL,
                fond_caisse_debut DECIMAL(12,2) DEFAULT NULL,
                fond_caisse_confirme_at DATETIME DEFAULT NULL,
                fond_caisse_fin DECIMAL(12,2) DEFAULT NULL,
                bordereau_num INT DEFAULT NULL,
                bordereau_suivant INT DEFAULT NULL,
                depot_on TINYINT(1) NOT NULL DEFAULT 0,
                depot_espece TINYINT(1) NOT NULL DEFAULT 0,
                depot_cheque TINYINT(1) NOT NULL DEFAULT 0,
                montant_remise_especes DECIMAL(12,2) DEFAULT NULL,
                montant_remise_cheque DECIMAL(12,2) DEFAULT NULL,
                journee_cloturee TINYINT(1) NOT NULL DEFAULT 0,
                journee_cloturee_at DATETIME DEFAULT NULL,
                journee_cloturee_by VARCHAR(255) DEFAULT NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                updated_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_livredecaisse_daily_state (business_date, record_type, reference_key),
                INDEX idx_livredecaisse_record_type_date (record_type, business_date),
                INDEX idx_livredecaisse_attachment_entry (attachment_entry_id),
                INDEX idx_livredecaisse_chrono (chrono),
                INDEX idx_livredecaisse_type_affaire (type_affaire),
                INDEX idx_livredecaisse_date_reglement (date_reglement)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->tryExec($pdo, "ALTER TABLE livredecaisse MODIFY COLUMN record_type ENUM('entry','daily_state','attachment') NOT NULL DEFAULT 'entry'");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN attachment_entry_id BIGINT UNSIGNED DEFAULT NULL AFTER reference_key");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN num_remise_especes VARCHAR(120) NOT NULL DEFAULT '' AFTER appel_cotisation");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN num_remise_cheque VARCHAR(120) NOT NULL DEFAULT '' AFTER num_remise_especes");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN attachment_file_name VARCHAR(255) NOT NULL DEFAULT '' AFTER num_remise_cheque");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN attachment_mime VARCHAR(120) NOT NULL DEFAULT '' AFTER attachment_file_name");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN attachment_size INT UNSIGNED NOT NULL DEFAULT 0 AFTER attachment_mime");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN attachment_blob LONGBLOB DEFAULT NULL AFTER attachment_size");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN fond_caisse_fin DECIMAL(12,2) DEFAULT NULL AFTER fond_caisse_confirme_at");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN bordereau_num INT DEFAULT NULL AFTER fond_caisse_fin");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN bordereau_suivant INT DEFAULT NULL AFTER bordereau_num");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN depot_on TINYINT(1) NOT NULL DEFAULT 0 AFTER bordereau_suivant");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN depot_espece TINYINT(1) NOT NULL DEFAULT 0 AFTER depot_on");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN depot_cheque TINYINT(1) NOT NULL DEFAULT 0 AFTER depot_espece");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN montant_remise_especes DECIMAL(12,2) DEFAULT NULL AFTER depot_cheque");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN montant_remise_cheque DECIMAL(12,2) DEFAULT NULL AFTER montant_remise_especes");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN journee_cloturee TINYINT(1) NOT NULL DEFAULT 0 AFTER fond_caisse_confirme_at");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN journee_cloturee_at DATETIME DEFAULT NULL AFTER journee_cloturee");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD COLUMN journee_cloturee_by VARCHAR(255) DEFAULT NULL AFTER journee_cloturee_at");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse ADD INDEX idx_livredecaisse_attachment_entry (attachment_entry_id)");

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS livredecaisse_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                livredecaisse_entry_id BIGINT UNSIGNED NOT NULL,
                reference_key VARCHAR(64) DEFAULT NULL,
                business_date DATE NOT NULL,
                attachment_file_name VARCHAR(255) NOT NULL DEFAULT "",
                attachment_mime VARCHAR(120) NOT NULL DEFAULT "",
                attachment_size INT UNSIGNED NOT NULL DEFAULT 0,
                attachment_blob LONGBLOB DEFAULT NULL,
                created_by VARCHAR(255) DEFAULT NULL,
                updated_by VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_livredecaisse_attachments_reference_key (reference_key),
                INDEX idx_livredecaisse_attachments_entry (livredecaisse_entry_id),
                INDEX idx_livredecaisse_attachments_date (business_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN reference_key VARCHAR(64) DEFAULT NULL AFTER livredecaisse_entry_id");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN business_date DATE NOT NULL AFTER reference_key");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN attachment_file_name VARCHAR(255) NOT NULL DEFAULT '' AFTER business_date");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN attachment_mime VARCHAR(120) NOT NULL DEFAULT '' AFTER attachment_file_name");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN attachment_size INT UNSIGNED NOT NULL DEFAULT 0 AFTER attachment_mime");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN attachment_blob LONGBLOB DEFAULT NULL AFTER attachment_size");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN created_by VARCHAR(255) DEFAULT NULL AFTER attachment_blob");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN updated_by VARCHAR(255) DEFAULT NULL AFTER created_by");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_by");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD UNIQUE INDEX uniq_livredecaisse_attachments_reference_key (reference_key)");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD INDEX idx_livredecaisse_attachments_entry (livredecaisse_entry_id)");
        $this->tryExec($pdo, "ALTER TABLE livredecaisse_attachments ADD INDEX idx_livredecaisse_attachments_date (business_date)");
        $this->tryExec(
            $pdo,
            "ALTER TABLE livredecaisse_attachments
             ADD CONSTRAINT fk_livredecaisse_attachments_entry
             FOREIGN KEY (livredecaisse_entry_id) REFERENCES livredecaisse(id)
             ON DELETE CASCADE"
        );

        $this->tryExec(
            $pdo,
            "INSERT IGNORE INTO livredecaisse_attachments (
                id,
                livredecaisse_entry_id,
                reference_key,
                business_date,
                attachment_file_name,
                attachment_mime,
                attachment_size,
                attachment_blob,
                created_by,
                updated_by,
                created_at,
                updated_at
            )
            SELECT
                legacy.id,
                legacy.attachment_entry_id,
                NULLIF(legacy.reference_key, ''),
                legacy.business_date,
                legacy.attachment_file_name,
                legacy.attachment_mime,
                legacy.attachment_size,
                legacy.attachment_blob,
                legacy.created_by,
                legacy.updated_by,
                legacy.created_at,
                legacy.updated_at
            FROM livredecaisse legacy
            INNER JOIN livredecaisse entry_row
                ON entry_row.id = legacy.attachment_entry_id
               AND entry_row.record_type = 'entry'
            WHERE legacy.record_type = 'attachment'
              AND legacy.attachment_entry_id IS NOT NULL
              AND legacy.attachment_entry_id > 0
              AND legacy.attachment_file_name <> ''"
        );

        $schemaReady = true;
    }

    private function tryExec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (\Throwable) {
        }
    }
}
