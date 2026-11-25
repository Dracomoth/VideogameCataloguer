-- ============================================================================
-- MariaDB / MySQL Schema Migration: 17_game_custom_core.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds uses_custom_core (boolean) and custom_core (string) columns
--          to the games table to allow specific ROMs to override the console's
--          default web emulation core.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Check and add column `uses_custom_core` to `games`
-- --------------------------------------------------------
SET @has_uses_custom_core = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'uses_custom_core'
);

SET @sql_add_uses_custom_core = IF(@has_uses_custom_core = 0, 
    'ALTER TABLE `games` ADD COLUMN `uses_custom_core` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_translation`', 
    'SELECT 1'
);
PREPARE stmt_add_uses_custom_core FROM @sql_add_uses_custom_core;
EXECUTE stmt_add_uses_custom_core;
DEALLOCATE PREPARE stmt_add_uses_custom_core;

-- --------------------------------------------------------
-- 2. Check and add column `custom_core` to `games`
-- --------------------------------------------------------
SET @has_custom_core = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'custom_core'
);

SET @sql_add_custom_core = IF(@has_custom_core = 0, 
    'ALTER TABLE `games` ADD COLUMN `custom_core` varchar(100) DEFAULT NULL AFTER `uses_custom_core`', 
    'SELECT 1'
);
PREPARE stmt_add_custom_core FROM @sql_add_custom_core;
EXECUTE stmt_add_custom_core;
DEALLOCATE PREPARE stmt_add_custom_core;

-- --------------------------------------------------------
-- 3. Update system_info
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '17.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '5.2.1', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
