-- ============================================================================
-- MariaDB / MySQL Schema Migration: 14_user_username.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Add unique username column to users table and backfill existing users
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Add username column
-- --------------------------------------------------------
ALTER TABLE `users` 
  ADD COLUMN `username` VARCHAR(50) NOT NULL DEFAULT '' AFTER `role_id`;

-- --------------------------------------------------------
-- 2. Populate username for existing records
-- --------------------------------------------------------
UPDATE `users` 
SET `username` = LOWER(SUBSTRING_INDEX(`email`, '@', 1)) 
WHERE `username` = '' OR `username` IS NULL;

-- Fallback in case of empty username
UPDATE `users` 
SET `username` = CONCAT('user_', `id`) 
WHERE `username` = '' OR `username` IS NULL;

-- --------------------------------------------------------
-- 3. Add unique index on username
-- --------------------------------------------------------
ALTER TABLE `users` 
  ADD UNIQUE KEY `uk_users_username` (`username`);

-- --------------------------------------------------------
-- 4. Update system_info
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '14.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '4.8.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
