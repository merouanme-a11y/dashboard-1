-- ============================================================
-- DASHBOARD - Script de déploiement base de données PROD
-- Généré le : 2026-04-04
-- À importer via phpMyAdmin sur le serveur de production
-- URL prod : https://merouan.meha0348.odns.fr/
-- ============================================================
-- INSTRUCTIONS :
--   1. Ouvrir phpMyAdmin sur la prod
--   2. Sélectionner votre base de données
--   3. Onglet "Importer" → choisir ce fichier → Exécuter
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- TABLES PRINCIPALES
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `department` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `color` VARCHAR(7) DEFAULT '#6C757D' NOT NULL,
  UNIQUE INDEX `UNIQ_7332E1695E237E06` (`name`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `module` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(255) NOT NULL,
  `route_name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT NOT NULL,
  `sort_order` INT NOT NULL,
  UNIQUE INDEX `UNIQ_C2426285E237E06` (`name`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `nom` VARCHAR(255) NOT NULL,
  `prenom` VARCHAR(255) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `adresse` VARCHAR(255) DEFAULT NULL,
  `code_postal` VARCHAR(20) DEFAULT NULL,
  `service` VARCHAR(255) DEFAULT NULL,
  `telephone` VARCHAR(50) DEFAULT NULL,
  `numero_court` VARCHAR(20) DEFAULT NULL,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `roles` JSON NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `reset_password_token` VARCHAR(64) DEFAULT NULL,
  `reset_password_expires_at` DATETIME DEFAULT NULL,
  `agence` VARCHAR(255) DEFAULT NULL,
  `departement` VARCHAR(255) DEFAULT NULL,
  `darkmode` TINYINT NOT NULL,
  `force_password_change` TINYINT NOT NULL,
  `profile_type` VARCHAR(255) NOT NULL,
  UNIQUE INDEX `UNIQ_1D1C63B3E7927C74` (`email`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `page` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `keywords` VARCHAR(500) DEFAULT NULL,
  `is_active` TINYINT NOT NULL,
  `show_title` TINYINT DEFAULT 1 NOT NULL,
  `show_breadcrumb` TINYINT DEFAULT 1 NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `created_by_id` INT DEFAULT NULL,
  UNIQUE INDEX `UNIQ_140AB620989D9B62` (`slug`),
  INDEX `IDX_140AB620B03A8386` (`created_by_id`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `page_icon` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `page_path` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(255) NOT NULL,
  `icon_library` VARCHAR(100) NOT NULL,
  `color` VARCHAR(7) DEFAULT NULL,
  UNIQUE INDEX `UNIQ_E25035018C9097D5` (`page_path`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `page_title` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `page_path` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  UNIQUE INDEX `UNIQ_4DB787BD8C9097D5` (`page_path`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `permission` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `page_path` VARCHAR(100) NOT NULL,
  `role` VARCHAR(255) DEFAULT NULL,
  `is_allowed` TINYINT NOT NULL,
  `utilisateur_id` INT DEFAULT NULL,
  INDEX `IDX_E04992AAFB88E14F` (`utilisateur_id`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `service_color` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `color` VARCHAR(7) NOT NULL,
  UNIQUE INDEX `UNIQ_CF24A50E5E237E06` (`name`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `theme_setting` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  UNIQUE INDEX `UNIQ_C617BDBB5FA1E697` (`setting_key`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `user_page_preference` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `utilisateur_id` INT NOT NULL,
  `page_key` VARCHAR(100) NOT NULL,
  `preference_payload` JSON NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  INDEX `IDX_4455ED42FB88E14F` (`utilisateur_id`),
  UNIQUE INDEX `uniq_user_page_pref_user_page` (`utilisateur_id`, `page_key`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` BIGINT AUTO_INCREMENT NOT NULL,
  `body` LONGTEXT NOT NULL,
  `headers` LONGTEXT NOT NULL,
  `queue_name` VARCHAR(190) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `available_at` DATETIME NOT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  INDEX `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`, `available_at`, `delivered_at`, `id`),
  PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- ------------------------------------------------------------
-- CLÉS ÉTRANGÈRES
-- ------------------------------------------------------------

ALTER TABLE `page`
  ADD CONSTRAINT `FK_140AB620B03A8386`
  FOREIGN KEY (`created_by_id`) REFERENCES `utilisateur` (`id`) ON DELETE SET NULL;

ALTER TABLE `permission`
  ADD CONSTRAINT `FK_E04992AAFB88E14F`
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`);

ALTER TABLE `user_page_preference`
  ADD CONSTRAINT `FK_4455ED42FB88E14F`
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE;

-- ------------------------------------------------------------
-- TABLE DE SUIVI DES MIGRATIONS DOCTRINE
-- (indique à Doctrine que toutes les migrations ont été jouées)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` VARCHAR(191) NOT NULL,
  `executed_at` DATETIME DEFAULT NULL,
  `execution_time` INT DEFAULT NULL,
  PRIMARY KEY (`version`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

INSERT IGNORE INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260322175412', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260322184218', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260322184711', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328103047', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328110000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328120000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328123000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328140000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328170000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328183000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328193000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328200000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328213000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328233000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260328235900', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260329192000', '2026-04-04 10:00:00', 100),
('DoctrineMigrations\\Version20260330101000', '2026-04-04 10:00:00', 77);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DU SCRIPT
-- ============================================================
