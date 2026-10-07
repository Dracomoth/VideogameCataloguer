-- ============================================================================
-- MariaDB / MySQL Schema Migration: 12_button_mappings.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds gamepad, cellphone adapter, and ROG Ally button mapping image
--          path fields to consoles table, and updates system_info version.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Add button mapping image path fields to table: consoles
-- --------------------------------------------------------
ALTER TABLE `consoles`
  ADD COLUMN `mapping_gamepad_path` varchar(255) DEFAULT NULL AFTER `logo_path`,
  ADD COLUMN `mapping_cellphone_path` varchar(255) DEFAULT NULL AFTER `mapping_gamepad_path`,
  ADD COLUMN `mapping_rog_ally_path` varchar(255) DEFAULT NULL AFTER `mapping_cellphone_path`;

-- --------------------------------------------------------
-- 2. Update system_info
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '12.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '4.6.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
