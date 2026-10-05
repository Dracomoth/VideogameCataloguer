-- ============================================================================
-- MariaDB / MySQL Schema Migration: 10_portal_permissions.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Adds games_portal, consoles_portal, and publishers_portal screens
--          to the screens registry and role_permissions, and updates
--          system_info database version to 10.0.0.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- 1. Register portal screens in screens table
-- --------------------------------------------------------
INSERT INTO `screens` (`screen_key`, `name`, `category`, `sort_order`) VALUES
('games_portal',      'Games Portal',      'Portals', 21),
('consoles_portal',   'Consoles Portal',   'Portals', 22),
('publishers_portal', 'Publishers Portal', 'Portals', 23)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`), 
  `category` = VALUES(`category`), 
  `sort_order` = VALUES(`sort_order`);

-- --------------------------------------------------------
-- 2. Grant permissions for Super Admin role (role_id = 1)
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`) VALUES
(1, 'games_portal',      'write', 'write'),
(1, 'consoles_portal',   'write', 'write'),
(1, 'publishers_portal', 'write', 'write')
ON DUPLICATE KEY UPDATE 
  `access_pc` = VALUES(`access_pc`), 
  `access_other` = VALUES(`access_other`);

-- --------------------------------------------------------
-- 3. Grant default read permissions to all other existing roles
-- --------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `screen_key`, `access_pc`, `access_other`)
SELECT `r`.`id`, `s`.`screen_key`, 'read', 'read'
FROM `roles` `r`
CROSS JOIN (
  SELECT 'games_portal' AS `screen_key`
  UNION ALL SELECT 'consoles_portal'
  UNION ALL SELECT 'publishers_portal'
) `s`
WHERE `r`.`id` != 1
ON DUPLICATE KEY UPDATE 
  `access_pc` = VALUES(`access_pc`), 
  `access_other` = VALUES(`access_other`);

-- --------------------------------------------------------
-- 4. Ensure system_info table exists
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
-- 5. Update system_info versions:
-- database -> 10.0.0
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '10.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
