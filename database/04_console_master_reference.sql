-- ============================================================================
-- MariaDB / MySQL Schema Migration: 04_console_master_reference.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds master_reference_id to consoles table with foreign key constraint
--          referencing consoles(id) for reference-only platforms.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Check and add column `master_reference_id` to `consoles`
-- --------------------------------------------------------
SET @has_master_reference_id = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'master_reference_id'
);

SET @sql_add_master_col = IF(@has_master_reference_id = 0, 
    'ALTER TABLE `consoles` ADD COLUMN `master_reference_id` int(11) NULL DEFAULT NULL AFTER `is_for_reference`', 
    'SELECT 1'
);
PREPARE stmt_add_master_col FROM @sql_add_master_col;
EXECUTE stmt_add_master_col;
DEALLOCATE PREPARE stmt_add_master_col;

-- --------------------------------------------------------
-- 2. Check and add Index & Foreign Key Constraint
-- --------------------------------------------------------
SET @has_fk_master = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND CONSTRAINT_NAME = 'fk_consoles_master_reference'
);

SET @sql_add_fk_master = IF(@has_fk_master = 0,
    'ALTER TABLE `consoles` ADD CONSTRAINT `fk_consoles_master_reference` FOREIGN KEY (`master_reference_id`) REFERENCES `consoles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_add_fk_master FROM @sql_add_fk_master;
EXECUTE stmt_add_fk_master;
DEALLOCATE PREPARE stmt_add_fk_master;

SET FOREIGN_KEY_CHECKS = 1;
