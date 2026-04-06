-- ============================================================
-- DASHBOARD - Synchronisation des donnees PROD
-- Genere automatiquement depuis la base locale
-- Genere le : 2026-04-04 19:51:05
-- Base source : meha0348_dashboard
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `user_page_preference`;
DELETE FROM `permission`;
DELETE FROM `page_icon`;
DELETE FROM `page_title`;
DELETE FROM `page`;
DELETE FROM `theme_setting`;
DELETE FROM `service_color`;
DELETE FROM `services`;
DELETE FROM `department`;
DELETE FROM `module`;
DELETE FROM `utilisateur`;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `department` WRITE;
/*!40000 ALTER TABLE `department` DISABLE KEYS */;
/*!40000 ALTER TABLE `department` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` (`id`, `name`, `color`) VALUES (1,'Agence','#b88463');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (2,'COM','#6c757d');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (3,'Communication','#f019f0');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (4,'Comptabilité','#ab3df5');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (5,'Conformité','#fa00ab');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (6,'Contrôle Interne','#f26f6f');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (7,'IT','#4a6fc0');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (8,'Marketing','#9be90c');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (9,'PATCH','#b0007a');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (10,'Prestations','#05cdff');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (11,'Production','#ffae00');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (12,'Relation Client','#b95ad3');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (13,'RH','#2f4e8c');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (14,'Service Entreprise','#ff6a00');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (15,'Sinistre','#a10f67');
INSERT INTO `services` (`id`, `name`, `color`) VALUES (1522,'Multi Services','#1d6f74');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `module` WRITE;
/*!40000 ALTER TABLE `module` DISABLE KEYS */;
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (1,'profile','Mon Profil','bi bi-person-circle','app_profile',1,0);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (2,'tickets','Tickets','bi-ticket-perforated','app_tickets',1,30);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (3,'stats','Statistiques','bi-bar-chart','app_stats',1,31);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (4,'page_title_icons','Icones des pages','bi-images','admin_page_icons',1,200);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (5,'page_titles','Libelles des pages','bi-pencil-square','admin_page_titles',1,210);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (6,'menus','Gestion du menu','bi-folder','admin_menus',1,220);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (7,'pages','Pages','bi-window-stack','admin_pages',1,225);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (8,'utilisateurs','Utilisateurs','bi-people','admin_utilisateurs',1,230);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (9,'annuaire','Annuaire des contacts','bi bi-person-lines-fill','app_annuaire',1,10);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (10,'gantt_projects','Planning projets','bi-diagram-3','app_gantt_projects',1,32);
INSERT INTO `module` (`id`, `name`, `label`, `icon`, `route_name`, `is_active`, `sort_order`) VALUES (11,'livre_de_caisse','Livre de caisse','bi-cash-stack','app_livre_de_caisse',1,110);
/*!40000 ALTER TABLE `module` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `utilisateur` WRITE;
/*!40000 ALTER TABLE `utilisateur` DISABLE KEYS */;
INSERT INTO `utilisateur` (`id`, `nom`, `prenom`, `email`, `adresse`, `code_postal`, `service`, `telephone`, `numero_court`, `mot_de_passe`, `photo`, `roles`, `created_at`, `updated_at`, `reset_password_token`, `reset_password_expires_at`, `agence`, `departement`, `darkmode`, `force_password_change`, `profile_type`) VALUES (1,'Hamzaoui','Merouan','merouan@me.com',NULL,'34500','IT','0651510899','4540','$2y$13$atd8tY31nkRZAm6bC.RYve9fC3wLRpmKRKaeY.F08VtFHHWxCENWK',NULL,'[\"ROLE_ADMIN\"]','2026-03-30 07:47:03','2026-04-03 19:41:56',NULL,NULL,'Baie-Mahault','971 - Guadeloupe',0,0,'Admin');
INSERT INTO `utilisateur` (`id`, `nom`, `prenom`, `email`, `adresse`, `code_postal`, `service`, `telephone`, `numero_court`, `mot_de_passe`, `photo`, `roles`, `created_at`, `updated_at`, `reset_password_token`, `reset_password_expires_at`, `agence`, `departement`, `darkmode`, `force_password_change`, `profile_type`) VALUES (2,'AT','Sébastien','test@test.fr',NULL,'34500','IT','0651510899','4518','$2y$13$eN/T6jTFmhgFYyQDw4vxeO/uJ3lE5hsZvVXrE4Z/DZw5r9FEfFIjW',NULL,'[]','2026-04-02 08:47:47',NULL,NULL,NULL,NULL,'34 - Hérault',0,1,'Employe');
/*!40000 ALTER TABLE `utilisateur` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `page` WRITE;
/*!40000 ALTER TABLE `page` DISABLE KEYS */;
INSERT INTO `page` (`id`, `created_by_id`, `slug`, `title`, `content`, `keywords`, `is_active`, `created_at`, `updated_at`, `show_title`, `show_breadcrumb`) VALUES (1,1,'page-test','Page test','<p>test</p>\r\n<p><img style=\"width: auto !important; max-width: 100% !important; height: auto !important\" src=\"/dashboard/public/uploads/images/editor/editor_image_20260330094808_photo-Merouan.png\" alt=\"photo-Merouan.png\"></p>',NULL,1,'2026-03-30 09:48:13','2026-03-30 09:49:14',0,0);
/*!40000 ALTER TABLE `page` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `page_icon` WRITE;
/*!40000 ALTER TABLE `page_icon` DISABLE KEYS */;
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (1,'admin_menus','bi-folder','bootstrap','#FFC800');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (2,'admin_page_icons','bi-images','bootstrap','#3BF751');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (3,'admin_page_titles','bi-pencil-square','bootstrap','#003B99');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (4,'admin_modules','bi-puzzle','bootstrap','#FF0000');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (5,'admin_pages','bi-file-earmark-text','bootstrap','#00FFAA');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (6,'admin_parametrage','bi-gear','bootstrap','#FF7300');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (7,'admin_services','bi-file-earmark-text','bootstrap','#B8EC27');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (8,'admin_theme','bi-palette','bootstrap','#FF00F7');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (9,'admin_utilisateurs','bi-file-earmark-text','bootstrap','#00D5FF');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (10,'app_annuaire','bi-people','bootstrap','#0091FF');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (11,'app_profile_create','bi-people','bootstrap','#F73B6A');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (12,'app_gantt_projects','bi-diagram-3','bootstrap','#FB00FF');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (13,'app_stats','bi-bar-chart','bootstrap','#FE0606');
INSERT INTO `page_icon` (`id`, `page_path`, `icon`, `icon_library`, `color`) VALUES (14,'app_dashboard','bi-house','bootstrap','#FFFFFF');
/*!40000 ALTER TABLE `page_icon` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `page_title` WRITE;
/*!40000 ALTER TABLE `page_title` DISABLE KEYS */;
/*!40000 ALTER TABLE `page_title` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `permission` WRITE;
/*!40000 ALTER TABLE `permission` DISABLE KEYS */;
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (1,'admin_menus',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (2,'admin_page_icons',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (3,'admin_page_titles',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (4,'admin_modules',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (5,'admin_pages',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (6,'admin_parametrage',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (7,'admin_services',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (8,'admin_theme',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (9,'admin_utilisateurs',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (10,'app_annuaire',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (11,'app_communication',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (12,'app_compta',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (13,'app_controleinterne',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (14,'app_profile_create',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (15,'app_marketing',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (16,'app_profile',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (17,'dynamic_page_1',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (18,'app_gantt_projects',NULL,1,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (19,'app_prestation',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (20,'app_production',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (21,'app_gantt_projects_api_projects',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (22,'app_relationclient',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (23,'app_rh',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (24,'app_serviceentreprise',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (25,'app_sinistre',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (26,'app_stats',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (27,'app_tickets',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (28,'app_vente',NULL,0,2);
INSERT INTO `permission` (`id`, `page_path`, `role`, `is_allowed`, `utilisateur_id`) VALUES (29,'app_dashboard',NULL,0,2);
/*!40000 ALTER TABLE `permission` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `service_color` WRITE;
/*!40000 ALTER TABLE `service_color` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_color` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `theme_setting` WRITE;
/*!40000 ALTER TABLE `theme_setting` DISABLE KEYS */;
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (1,'active_template','template2');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (2,'site_title','');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (3,'site_tagline','');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (4,'logo_path','uploads/images/theme/site_logo_20260330083735_logo-adep-bleu.svg');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (5,'logo_size','65');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (6,'sticky_header_enabled','1');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (7,'primary_button_color','#3B82F6');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (8,'dark_primary_button_color','#3B82F6');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (9,'page_icon_library','bootstrap');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (10,'user_info','1');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (11,'header_right_menu','1');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (12,'dark_mode_toggle','1');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (13,'app_background_color','#FFFFFF');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (14,'header_background_color','#F8FAFC');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (15,'menu_background_color','#F8FAFC');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (16,'menu_text_color','#475569');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (17,'heading_color','#0F172A');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (18,'primary_button_text_color','#FFFFFF');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (19,'dark_app_background_color','#0F172A');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (20,'dark_header_background_color','#0F172A');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (21,'dark_menu_background_color','#1E293B');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (22,'dark_menu_text_color','#CBD5E1');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (23,'dark_heading_color','#F8FAFC');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (24,'dark_primary_button_text_color','#FFFFFF');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (25,'body_font','system-ui');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (26,'body_font_size','16');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (27,'menu_font','system-ui');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (28,'menu_font_size','15');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (29,'heading_font','system-ui');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (30,'heading_font_size','32');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (31,'button_font','system-ui');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (32,'button_font_size','15');
INSERT INTO `theme_setting` (`id`, `setting_key`, `setting_value`) VALUES (33,'button_radius','10');
/*!40000 ALTER TABLE `theme_setting` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `user_page_preference` WRITE;
/*!40000 ALTER TABLE `user_page_preference` DISABLE KEYS */;
INSERT INTO `user_page_preference` (`id`, `utilisateur_id`, `page_key`, `preference_payload`, `created_at`, `updated_at`) VALUES (1,1,'stats','{\"defaultProject\":\"MTN\",\"projects\":{\"MTN\":{\"layout\":[{\"id\":\"card-state-a-faire\",\"fraction\":\"2\\/8\"},{\"id\":\"card-state-en-cours\",\"fraction\":\"1\\/8\"},{\"id\":\"card-state-en-cours-snl\",\"fraction\":\"1\\/8\"},{\"id\":\"card-state-en-attente\",\"fraction\":\"1\\/8\"},{\"id\":\"card-state-standby\",\"fraction\":\"1\\/8\"},{\"id\":\"card-state-fait\",\"fraction\":\"1\\/8\"},{\"id\":\"card-total\",\"fraction\":\"1\\/8\"},{\"id\":\"card-states\",\"fraction\":\"4\\/8\"},{\"id\":\"card-services\",\"fraction\":\"4\\/8\"},{\"id\":\"card-users\",\"fraction\":\"8\\/8\"},{\"id\":\"card-table\",\"fraction\":\"8\\/8\"}],\"visibility\":[{\"id\":\"card-state-a-faire\",\"hidden\":false},{\"id\":\"card-state-en-cours\",\"hidden\":false},{\"id\":\"card-state-en-cours-snl\",\"hidden\":false},{\"id\":\"card-state-en-attente\",\"hidden\":false},{\"id\":\"card-state-standby\",\"hidden\":false},{\"id\":\"card-state-fait\",\"hidden\":false},{\"id\":\"card-total\",\"hidden\":false},{\"id\":\"card-states\",\"hidden\":false},{\"id\":\"card-services\",\"hidden\":false},{\"id\":\"card-users\",\"hidden\":false},{\"id\":\"card-table\",\"hidden\":false}],\"colors\":[{\"id\":\"card-state-a-faire\",\"bgColor\":\"#3476df\",\"textColor\":\"#ffffff\"},{\"id\":\"card-state-en-cours\",\"bgColor\":\"#ffae00\",\"textColor\":\"#ffffff\"},{\"id\":\"card-state-en-cours-snl\",\"bgColor\":\"#ff6600\",\"textColor\":\"#ffffff\"},{\"id\":\"card-state-en-attente\",\"bgColor\":\"#9900ff\",\"textColor\":\"#ffffff\"},{\"id\":\"card-state-standby\",\"bgColor\":\"#3a0061\",\"textColor\":\"#ffffff\"},{\"id\":\"card-state-fait\",\"bgColor\":\"#b5c200\",\"textColor\":\"#ffffff\"},{\"id\":\"card-total\",\"bgColor\":null,\"textColor\":\"#ffffff\"}]}}}','2026-03-30 09:28:29','2026-04-04 08:21:03');
/*!40000 ALTER TABLE `user_page_preference` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- FIN DU SCRIPT
-- ============================================================
