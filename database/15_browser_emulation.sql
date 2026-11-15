-- ============================================================================
-- MariaDB / MySQL Schema Migration: 15_browser_emulation.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds is_browser_emulable flag and web_core name to consoles table
--          for RetroArch web emulation, and updates system_info to system 5.0.0
--          and database 15.0.0.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Check and add column `is_browser_emulable` to `consoles`
-- --------------------------------------------------------
SET @has_is_browser_emulable = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'is_browser_emulable'
);

SET @sql_add_browser_emulable = IF(@has_is_browser_emulable = 0, 
    'ALTER TABLE `consoles` ADD COLUMN `is_browser_emulable` tinyint(1) NOT NULL DEFAULT 0 AFTER `core_link`', 
    'SELECT 1'
);
PREPARE stmt_add_browser_emulable FROM @sql_add_browser_emulable;
EXECUTE stmt_add_browser_emulable;
DEALLOCATE PREPARE stmt_add_browser_emulable;

-- --------------------------------------------------------
-- 2. Check and add column `web_core` to `consoles`
-- --------------------------------------------------------
SET @has_web_core = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'web_core'
);

SET @sql_add_web_core = IF(@has_web_core = 0, 
    'ALTER TABLE `consoles` ADD COLUMN `web_core` varchar(255) DEFAULT NULL AFTER `is_browser_emulable`', 
    'SELECT 1'
);
PREPARE stmt_add_web_core FROM @sql_add_web_core;
EXECUTE stmt_add_web_core;
DEALLOCATE PREPARE stmt_add_web_core;

-- --------------------------------------------------------
-- 3. Check and add column `is_main` to `downloadable_files`
-- --------------------------------------------------------
SET @has_is_main = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'downloadable_files' 
      AND COLUMN_NAME = 'is_main'
);

SET @sql_add_is_main = IF(@has_is_main = 0, 
    'ALTER TABLE `downloadable_files` ADD COLUMN `is_main` tinyint(1) NOT NULL DEFAULT 0 AFTER `file_key_or_url`', 
    'SELECT 1'
);
PREPARE stmt_add_is_main FROM @sql_add_is_main;
EXECUTE stmt_add_is_main;
DEALLOCATE PREPARE stmt_add_is_main;

-- Set all existing items to is_main = 1
UPDATE `downloadable_files` SET `is_main` = 1;

-- --------------------------------------------------------
-- 4. Register web player screen in screens table
-- --------------------------------------------------------
INSERT INTO `screens` (`screen_key`, `name`, `category`, `sort_order`) VALUES
('player', 'Web Player', 'Portals', 35)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`), 
  `category` = VALUES(`category`), 
  `sort_order` = VALUES(`sort_order`);

-- --------------------------------------------------------
-- 5. Grant permissions for Super Admin role (role_id = 1)
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`) VALUES
(1, 'player', 'write', 'write')
ON DUPLICATE KEY UPDATE 
  `access_pc` = VALUES(`access_pc`), 
  `access_other` = VALUES(`access_other`);

-- --------------------------------------------------------
-- 6. Grant default read permissions to all other existing roles
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`)
SELECT `r`.`id`, 'player', 'read', 'read'
FROM `roles` `r`
WHERE `r`.`id` != 1
ON DUPLICATE KEY UPDATE 
  `access_pc` = VALUES(`access_pc`), 
  `access_other` = VALUES(`access_other`);

-- --------------------------------------------------------
-- 7. Update system_info
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '15.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '5.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
