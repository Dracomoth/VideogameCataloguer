-- ============================================================================
-- MariaDB / MySQL Schema Migration: 07_system_info.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Creates system_info table to track environment versions and metadata.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table: system_info
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
-- Seed initial version record for database
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '7.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '2.5.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
