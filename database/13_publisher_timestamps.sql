-- ============================================================================
-- MariaDB / MySQL Schema Migration: 13_publisher_timestamps.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds created and updated timestamp fields to publishers table,
--          initializes existing records, and updates system_info version.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Check and add column `created` to `publishers`
-- --------------------------------------------------------
SET @has_pub_created = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'publishers' 
      AND COLUMN_NAME = 'created'
);

SET @sql_add_pub_created = IF(@has_pub_created = 0, 
    'ALTER TABLE `publishers` ADD COLUMN `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP', 
    'SELECT 1'
);
PREPARE stmt_add_pub_created FROM @sql_add_pub_created;
EXECUTE stmt_add_pub_created;
DEALLOCATE PREPARE stmt_add_pub_created;

-- --------------------------------------------------------
-- 2. Check and add column `updated` to `publishers`
-- --------------------------------------------------------
SET @has_pub_updated = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'publishers' 
      AND COLUMN_NAME = 'updated'
);

SET @sql_add_pub_updated = IF(@has_pub_updated = 0, 
    'ALTER TABLE `publishers` ADD COLUMN `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 
    'SELECT 1'
);
PREPARE stmt_add_pub_updated FROM @sql_add_pub_updated;
EXECUTE stmt_add_pub_updated;
DEALLOCATE PREPARE stmt_add_pub_updated;

-- --------------------------------------------------------
-- 3. Initialize both fields with current timestamp for existing records in `publishers`
-- --------------------------------------------------------
UPDATE `publishers` 
SET `created` = CURRENT_TIMESTAMP, 
    `updated` = CURRENT_TIMESTAMP
WHERE `created` IS NULL OR `updated` IS NULL;

-- --------------------------------------------------------
-- 4. Update system_info
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '13.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '4.7.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
