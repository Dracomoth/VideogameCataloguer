-- ============================================================================
-- MariaDB / MySQL Schema Migration: 08_player_games.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: Creates player_games table to track player statistics per game
--          (downloads, play sessions, and completion/wins), and bumps system_info
--          database version to 8.0.0 and system version to 3.0.0.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table: player_games
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `player_games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `is_downloaded` tinyint(1) NOT NULL DEFAULT 0,
  `first_download_date` datetime DEFAULT NULL,
  `last_download_date` datetime DEFAULT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `is_played` tinyint(1) NOT NULL DEFAULT 0,
  `played_date` datetime DEFAULT NULL,
  `is_won` tinyint(1) NOT NULL DEFAULT 0,
  `win_date` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_player_game` (`player_id`, `game_id`),
  KEY `fk_player_games_player` (`player_id`),
  KEY `fk_player_games_game` (`game_id`),
  CONSTRAINT `fk_player_games_player` FOREIGN KEY (`player_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_player_games_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- database -> 8.0.0
-- system   -> 3.0.0
-- --------------------------------------------------------
INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('database', '8.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

INSERT INTO `system_info` (`item`, `version`, `created`, `updated`)
VALUES ('system', '3.0.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE 
  `version` = VALUES(`version`),
  `updated` = CURRENT_TIMESTAMP;

SET FOREIGN_KEY_CHECKS = 1;
