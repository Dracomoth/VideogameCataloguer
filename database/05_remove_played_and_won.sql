-- ============================================================================
-- MariaDB / MySQL Schema Migration: 05_remove_played_and_won.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Remove game-level played and won status fields (`is_played`, `is_won`)
--          from the `games` table for multi-user environment readiness.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Check and drop `is_played` from `games` table
-- --------------------------------------------------------
SET @has_is_played = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'is_played'
);

SET @sql_drop_is_played = IF(@has_is_played > 0, 
    'ALTER TABLE `games` DROP COLUMN `is_played`', 
    'SELECT 1'
);
PREPARE stmt_drop_is_played FROM @sql_drop_is_played;
EXECUTE stmt_drop_is_played;
DEALLOCATE PREPARE stmt_drop_is_played;

-- Drop legacy un-normalized column `Played` if it still exists
SET @has_legacy_played = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'Played'
);

SET @sql_drop_legacy_played = IF(@has_legacy_played > 0, 
    'ALTER TABLE `games` DROP COLUMN `Played`', 
    'SELECT 1'
);
PREPARE stmt_drop_legacy_played FROM @sql_drop_legacy_played;
EXECUTE stmt_drop_legacy_played;
DEALLOCATE PREPARE stmt_drop_legacy_played;

-- --------------------------------------------------------
-- 2. Check and drop `is_won` from `games` table
-- --------------------------------------------------------
SET @has_is_won = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'is_won'
);

SET @sql_drop_is_won = IF(@has_is_won > 0, 
    'ALTER TABLE `games` DROP COLUMN `is_won`', 
    'SELECT 1'
);
PREPARE stmt_drop_is_won FROM @sql_drop_is_won;
EXECUTE stmt_drop_is_won;
DEALLOCATE PREPARE stmt_drop_is_won;

-- Drop legacy un-normalized column `Won` if it still exists
SET @has_legacy_won = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'games' 
      AND COLUMN_NAME = 'Won'
);

SET @sql_drop_legacy_won = IF(@has_legacy_won > 0, 
    'ALTER TABLE `games` DROP COLUMN `Won`', 
    'SELECT 1'
);
PREPARE stmt_drop_legacy_won FROM @sql_drop_legacy_won;
EXECUTE stmt_drop_legacy_won;
DEALLOCATE PREPARE stmt_drop_legacy_won;

SET FOREIGN_KEY_CHECKS = 1;
