-- ============================================================================
-- MariaDB / MySQL Schema Migration: 16_game_translation.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds is_translation flag to games table to mark games that are
--          translations of an original game in another language, and updates
--          system_info to database 16.0.0 and system 5.2.0.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Check and add column `is_translation` to `games`
-- --------------------------------------------------------
SET @has_is_translation = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'is_translation'
);

SET @sql_add_is_translation = IF(@has_is_translation = 0, 
    'ALTER TABLE `games` ADD COLUMN `is_translation` tinyint(1) NOT NULL DEFAULT 0 AFTER `language_id`', 
    'SELECT 1'
);
PREPARE stmt_add_is_translation FROM @sql_add_is_translation;
EXECUTE stmt_add_is_translation;
DEALLOCATE PREPARE stmt_add_is_translation;

-- --------------------------------------------------------
-- 2. Update system_info
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '16.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '5.2.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
