-- ============================================================================
-- MariaDB / MySQL Schema Migration: 09_publisher_details.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds 'description' and 'logo_path' fields to publishers table,
--          and updates system_info database version to 9.0.0 and system to 4.0.0.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Add description and logo_path to publishers table
-- --------------------------------------------------------
ALTER TABLE `publishers`
  ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL AFTER `is_console_maker`,
  ADD COLUMN IF NOT EXISTS `logo_path` varchar(255) DEFAULT NULL AFTER `description`;

-- --------------------------------------------------------
-- Ensure system_info table exists
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item` varchar(100) NOT NULL,
  `version` varchar(50) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_info_item` (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Update system_info versions:
-- database -> 9.0.0
-- system   -> 4.0.0
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '9.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '4.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
