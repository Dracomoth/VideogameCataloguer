-- ============================================================================
-- MariaDB / MySQL Schema Migration: 03_console_types.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Console Types dynamic taxonomy table, console type foreign key on consoles,
--          screen registration and permissions.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Table structure for table: console_types
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `console_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `badge_bg_color` varchar(7) NOT NULL DEFAULT '#1e3a8a',
  `badge_font_color` varchar(7) NOT NULL DEFAULT '#93c5fd',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_console_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Prefill initial console types with specified colors
-- Order: Home (Blue), Arcade (Amber), Microcomputer (Green), Handheld (Purple)
-- Font brighter than background
-- --------------------------------------------------------
INSERT INTO `console_types` (`id`, `name`, `badge_bg_color`, `badge_font_color`) VALUES
(1, 'Home', '#1e3a8a', '#93c5fd'),
(2, 'Arcade', '#78350f', '#fde68a'),
(3, 'Microcomputer', '#064e3b', '#6ee7b7'),
(4, 'Handheld', '#581c87', '#d8b4fe')
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `badge_bg_color` = VALUES(`badge_bg_color`),
  `badge_font_color` = VALUES(`badge_font_color`);

-- --------------------------------------------------------
-- 3. Modify consoles table:
-- Add console_type_id, migrate data from flags, add FK, drop old flags
-- --------------------------------------------------------
SET @has_console_type_id = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'console_type_id'
);

-- Add column if missing
SET @sql_add_col = IF(@has_console_type_id = 0, 
    'ALTER TABLE `consoles` ADD COLUMN `console_type_id` int(11) NULL AFTER `generation`', 
    'SELECT 1'
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- Migrate data from flags if columns still exist
SET @has_is_handheld = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'is_handheld'
);

SET @sql_migrate = IF(@has_is_handheld > 0,
    'UPDATE `consoles` SET `console_type_id` = CASE 
        WHEN `is_arcade` = 1 THEN 2 
        WHEN `is_computer` = 1 THEN 3 
        WHEN `is_handheld` = 1 THEN 4 
        ELSE 1 
     END WHERE `console_type_id` IS NULL',
    'UPDATE `consoles` SET `console_type_id` = 1 WHERE `console_type_id` IS NULL'
);
PREPARE stmt_migrate FROM @sql_migrate;
EXECUTE stmt_migrate;
DEALLOCATE PREPARE stmt_migrate;

-- Set NOT NULL and default 1
ALTER TABLE `consoles` MODIFY COLUMN `console_type_id` int(11) NOT NULL DEFAULT 1;

-- Add index & foreign key constraint if missing
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND CONSTRAINT_NAME = 'fk_consoles_console_type'
);

SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE `consoles` ADD CONSTRAINT `fk_consoles_console_type` FOREIGN KEY (`console_type_id`) REFERENCES `console_types` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT',
    'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Drop obsolete flag columns
SET @sql_drop_handheld = IF(@has_is_handheld > 0, 'ALTER TABLE `consoles` DROP COLUMN `is_handheld`', 'SELECT 1');
PREPARE stmt_drop_handheld FROM @sql_drop_handheld;
EXECUTE stmt_drop_handheld;
DEALLOCATE PREPARE stmt_drop_handheld;

SET @has_is_computer = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'is_computer'
);
SET @sql_drop_computer = IF(@has_is_computer > 0, 'ALTER TABLE `consoles` DROP COLUMN `is_computer`', 'SELECT 1');
PREPARE stmt_drop_computer FROM @sql_drop_computer;
EXECUTE stmt_drop_computer;
DEALLOCATE PREPARE stmt_drop_computer;

SET @has_is_arcade = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'consoles' 
      AND COLUMN_NAME = 'is_arcade'
);
SET @sql_drop_arcade = IF(@has_is_arcade > 0, 'ALTER TABLE `consoles` DROP COLUMN `is_arcade`', 'SELECT 1');
PREPARE stmt_drop_arcade FROM @sql_drop_arcade;
EXECUTE stmt_drop_arcade;
DEALLOCATE PREPARE stmt_drop_arcade;

-- --------------------------------------------------------
-- 4. Register screen in screens table
-- --------------------------------------------------------
INSERT INTO `screens` (`screen_key`, `name`, `category`, `sort_order`) VALUES
('console_types', 'Console Types', 'Taxonomy', 45)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`), 
  `category` = VALUES(`category`), 
  `sort_order` = VALUES(`sort_order`);

-- --------------------------------------------------------
-- 5. Grant permissions to Super Admin role (role_id = 1)
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`) VALUES
(1, 'console_types', 'write', 'write')
ON DUPLICATE KEY UPDATE 
  `access_pc` = 'write', 
  `access_other` = 'write';

SET FOREIGN_KEY_CHECKS = 1;
