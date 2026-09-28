-- ============================================================================
-- MariaDB / MySQL Clean Base Schema (Sanitized From Scratch)
-- Project: Videogame Vault / Cataloguer
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table: categories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: languages
-- --------------------------------------------------------
DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: publishers
-- --------------------------------------------------------
DROP TABLE IF EXISTS `publishers`;
CREATE TABLE `publishers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `is_console_maker` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: subcategories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `subcategories`;
CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_subcategories_category` (`category_id`),
  CONSTRAINT `fk_subcategories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: consoles
-- --------------------------------------------------------
DROP TABLE IF EXISTS `consoles`;
CREATE TABLE `consoles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `publisher_id` int(11) DEFAULT NULL,
  `year` varchar(255) DEFAULT NULL,
  `generation` varchar(255) DEFAULT NULL,
  `is_handheld` tinyint(1) DEFAULT 0,
  `is_computer` tinyint(1) DEFAULT 0,
  `is_arcade` tinyint(1) DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `emulator` varchar(255) DEFAULT NULL,
  `emulator_link` text DEFAULT NULL,
  `emulator_android` varchar(255) DEFAULT NULL,
  `emulator_android_link` text DEFAULT NULL,
  `retroarch_core` varchar(255) DEFAULT NULL,
  `core_link` text DEFAULT NULL,
  `is_for_reference` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_consoles_publisher` (`publisher_id`),
  CONSTRAINT `fk_consoles_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: games
-- --------------------------------------------------------
DROP TABLE IF EXISTS `games`;
CREATE TABLE `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `console_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `language_id` int(11) DEFAULT NULL,
  `publisher_id` int(11) DEFAULT NULL,
  `year` varchar(255) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `screenshot_path` varchar(255) DEFAULT NULL,
  `boxart_path` varchar(255) DEFAULT NULL,
  `in_collection` tinyint(1) DEFAULT 0,
  `is_played` tinyint(1) DEFAULT 0,
  `is_won` tinyint(1) DEFAULT 0,
  `comments` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_games_console` (`console_id`),
  KEY `fk_games_category` (`category_id`),
  KEY `fk_games_subcategory` (`subcategory_id`),
  KEY `fk_games_publisher` (`publisher_id`),
  KEY `fk_games_language` (`language_id`),
  CONSTRAINT `fk_games_console` FOREIGN KEY (`console_id`) REFERENCES `consoles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_games_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_games_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_games_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_games_language` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: orphans
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orphans`;
CREATE TABLE `orphans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `filepath` varchar(500) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filepath` (`filepath`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;