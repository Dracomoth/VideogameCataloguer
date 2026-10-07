-- ============================================================================
-- MariaDB / MySQL Schema Migration: 11_broken_links.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Creates broken_links table to track reported broken download links,
--          registers catalog_services screen in RBAC matrix, and updates
--          system_info database version to 11.0.0.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Table structure for table: broken_links
-- Stores user reports of broken downloadable file links
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `broken_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_broken_links_file` (`file_id`),
  KEY `fk_broken_links_user` (`user_id`),
  KEY `idx_broken_links_status` (`status`),
  CONSTRAINT `fk_broken_links_file` FOREIGN KEY (`file_id`) REFERENCES `downloadable_files` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_broken_links_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Register catalog_services screen in screens table
-- --------------------------------------------------------
INSERT INTO `screens` (`screen_key`, `name`, `category`, `sort_order`) VALUES
('catalog_services', 'Catalog Services', 'Tools', 150)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`), 
  `category` = VALUES(`category`), 
  `sort_order` = VALUES(`sort_order`);

-- --------------------------------------------------------
-- 3. Grant Super Admin write permission for catalog_services
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`) VALUES
(1, 'catalog_services', 'write', 'write')
ON DUPLICATE KEY UPDATE 
  `access_pc` = VALUES(`access_pc`), 
  `access_other` = VALUES(`access_other`);

-- --------------------------------------------------------
-- 4. Grant write permissions for all existing roles by default
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`)
SELECT `r`.`id`, 'catalog_services', 'write', 'write'
FROM `roles` `r`
WHERE `r`.`id` != 1
ON DUPLICATE KEY UPDATE 
  `access_pc` = VALUES(`access_pc`), 
  `access_other` = VALUES(`access_other`);

-- --------------------------------------------------------
-- 5. Update system_info database version to 11.0.0
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '11.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

-- --------------------------------------------------------
-- 6. Update screen categories and sort orders to mirror sidebar navigation
-- --------------------------------------------------------
UPDATE `screens` SET `category` = 'Overview',   `name` = 'Dashboard',  `sort_order` = 10  WHERE `screen_key` = 'dashboard';
UPDATE `screens` SET `category` = 'Overview',   `name` = 'Player Hub',  `sort_order` = 20  WHERE `screen_key` = 'collection';
UPDATE `screens` SET `category` = 'Portals',    `name` = 'Games (Portal)',  `sort_order` = 30  WHERE `screen_key` = 'games_portal';
UPDATE `screens` SET `category` = 'Portals',    `name` = 'Consoles (Portal)',  `sort_order` = 40  WHERE `screen_key` = 'consoles_portal';
UPDATE `screens` SET `category` = 'Portals',    `name` = 'Publishers (Portal)',  `sort_order` = 50  WHERE `screen_key` = 'publishers_portal';
UPDATE `screens` SET `category` = 'Catalog',    `name` = 'Games (Catalog)',  `sort_order` = 60  WHERE `screen_key` = 'games';
UPDATE `screens` SET `category` = 'Catalog',    `name` = 'Consoles (Catalog)',  `sort_order` = 70  WHERE `screen_key` = 'consoles';
UPDATE `screens` SET `category` = 'Catalog',    `name` = 'Publishers (Catalog)',  `sort_order` = 80  WHERE `screen_key` = 'publishers';
UPDATE `screens` SET `category` = 'Metadata',   `name` = 'Console Types',  `sort_order` = 90  WHERE `screen_key` = 'console_types';
UPDATE `screens` SET `category` = 'Metadata',   `name` = 'Categories',  `sort_order` = 100 WHERE `screen_key` = 'categories';
UPDATE `screens` SET `category` = 'Metadata',   `name` = 'Subcategories',  `sort_order` = 110 WHERE `screen_key` = 'subcategories';
UPDATE `screens` SET `category` = 'Metadata',   `name` = 'Languages',  `sort_order` = 120 WHERE `screen_key` = 'languages';
UPDATE `screens` SET `category` = 'Tools',      `name` = 'Reports',  `sort_order` = 130 WHERE `screen_key` = 'reports';
UPDATE `screens` SET `category` = 'Tools',      `name` = 'Bulk Upload',  `sort_order` = 140 WHERE `screen_key` = 'bulk_upload';
UPDATE `screens` SET `category` = 'Tools',      `name` = 'Catalog Services',  `sort_order` = 150 WHERE `screen_key` = 'catalog_services';
UPDATE `screens` SET `category` = 'Admin',      `name` = 'Users',  `sort_order` = 160 WHERE `screen_key` = 'users';
UPDATE `screens` SET `category` = 'Admin',      `name` = 'Roles',  `sort_order` = 170 WHERE `screen_key` = 'roles';

SET FOREIGN_KEY_CHECKS = 1;
