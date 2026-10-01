-- ============================================================================
-- MariaDB / MySQL Schema Migration: 06_console_timestamps.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds created and updated timestamp fields to consoles and games tables,
--          initializes all existing records with current timestamp, and creates
--          the downloadable_files table with foreign keys to consoles and games.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Check and add column `created` to `consoles`
-- --------------------------------------------------------
SET @has_created = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'created'
);

SET @sql_add_created = IF(@has_created = 0, 
    'ALTER TABLE `consoles` ADD COLUMN `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 
    'SELECT 1'
);
PREPARE stmt_add_created FROM @sql_add_created;
EXECUTE stmt_add_created;
DEALLOCATE PREPARE stmt_add_created;

-- --------------------------------------------------------
-- 2. Check and add column `updated` to `consoles`
-- --------------------------------------------------------
SET @has_updated = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'updated'
);

SET @sql_add_updated = IF(@has_updated = 0, 
    'ALTER TABLE `consoles` ADD COLUMN `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 
    'SELECT 1'
);
PREPARE stmt_add_updated FROM @sql_add_updated;
EXECUTE stmt_add_updated;
DEALLOCATE PREPARE stmt_add_updated;

-- --------------------------------------------------------
-- 3. Initialize both fields with current timestamp for all existing records in `consoles`
-- --------------------------------------------------------
UPDATE `consoles` 
SET `created` = CURRENT_TIMESTAMP, 
    `updated` = CURRENT_TIMESTAMP;

-- --------------------------------------------------------
-- 4. Check and add column `created` to `games`
-- --------------------------------------------------------
SET @has_games_created = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'created'
);

SET @sql_add_games_created = IF(@has_games_created = 0, 
    'ALTER TABLE `games` ADD COLUMN `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 
    'SELECT 1'
);
PREPARE stmt_add_games_created FROM @sql_add_games_created;
EXECUTE stmt_add_games_created;
DEALLOCATE PREPARE stmt_add_games_created;

-- --------------------------------------------------------
-- 5. Check and add column `updated` to `games`
-- --------------------------------------------------------
SET @has_games_updated = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'updated'
);

SET @sql_add_games_updated = IF(@has_games_updated = 0, 
    'ALTER TABLE `games` ADD COLUMN `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 
    'SELECT 1'
);
PREPARE stmt_add_games_updated FROM @sql_add_games_updated;
EXECUTE stmt_add_games_updated;
DEALLOCATE PREPARE stmt_add_games_updated;

-- --------------------------------------------------------
-- 6. Initialize both fields with current timestamp for all existing records in `games`
-- --------------------------------------------------------
UPDATE `games` 
SET `created` = CURRENT_TIMESTAMP, 
    `updated` = CURRENT_TIMESTAMP;

-- --------------------------------------------------------
-- 7. Table structure for table: downloadable_files
-- Pointers to downloadable files (BIOS, configs, patches, manuals) for consoles and games
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `downloadable_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `console_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `display_name` varchar(255) NOT NULL,
  `storage_provider` enum('blackblaze','external') NOT NULL DEFAULT 'external',
  `file_key_or_url` text NOT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `last_download` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_downloadable_files_console` (`console_id`),
  KEY `fk_downloadable_files_game` (`game_id`),
  CONSTRAINT `fk_downloadable_files_console` FOREIGN KEY (`console_id`) REFERENCES `consoles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_downloadable_files_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

