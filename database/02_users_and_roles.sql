-- ============================================================================
-- MariaDB / MySQL Schema Migration: 02_users_and_roles.sql
-- Project: Videogame Vault / Cataloguer
-- Purpose: In-app Authentication, RBAC, Dual-Device Screen Permissions Matrix
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table structure for table: roles
-- --------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_super` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: screens
-- Registered views/screens subject to access controls
-- --------------------------------------------------------
DROP TABLE IF EXISTS `screens`;
CREATE TABLE `screens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `screen_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Core',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_screens_key` (`screen_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: users
-- Single assigned role per user, strict admin-only creation
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table: role_permissions
-- Granular screen permissions per role, doubled for PC vs Other Devices
-- Permission levels: 'none', 'read', 'write'
-- --------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `screen_key` varchar(50) NOT NULL,
  `access_pc` enum('none','read','write') NOT NULL DEFAULT 'none',
  `access_other` enum('none','read','write') NOT NULL DEFAULT 'none',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_screen` (`role_id`, `screen_key`),
  KEY `fk_permissions_role` (`role_id`),
  CONSTRAINT `fk_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Initial Seed Data (Minimal: Super Admin & System Screens Registry)
-- ============================================================================

-- 1. Initial Super Admin Role (Bypasses all checks)
INSERT INTO `roles` (`id`, `name`, `description`, `is_super`) VALUES
(1, 'Super Admin', 'Full unrestricted access to all screens, actions, and system settings.', 1);

-- 2. Registered Screens & Views Registry
INSERT INTO `screens` (`screen_key`, `name`, `category`, `sort_order`) VALUES
('dashboard',    'Dashboard',               'Overview',       10),
('collection',   'Player Hub',              'Overview',       20),
('games',        'Games Catalog',           'Management',     30),
('consoles',     'Consoles & Hardware',     'Management',     40),
('publishers',   'Publishers & Studios',    'Taxonomy',       50),
('categories',   'Genre Categories',        'Taxonomy',       60),
('subcategories','Subcategories',           'Taxonomy',       70),
('languages',    'Languages & Regions',     'Taxonomy',       80),
('reports',      'Reports & Telemetry',     'System',         90),
('bulk_upload',  'Bulk Upload & Importer',  'System',        100),
('users',        'User Management',         'Administration', 110),
('roles',        'Roles & Permissions',     'Administration', 120);

-- 3. Initial Super User Account
-- Default password: Password#2026!
-- Hash generated via password_hash('Password#2026!', PASSWORD_BCRYPT, ['cost' => 12])
INSERT INTO `users` (`id`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `is_active`) VALUES
(1, 1, 'Super', 'Admin', 'admin@videogamevault.local', '$2y$12$Letu1LHUw8kJ0MYocKiJMe3zM04oU8JMO4ydubljGY5QcmOA5Ucjy', 1);

SET FOREIGN_KEY_CHECKS = 1;
