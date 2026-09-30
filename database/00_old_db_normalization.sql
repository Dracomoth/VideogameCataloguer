-- ============================================================================
-- MariaDB / MySQL Schema Normalization Script
-- Project: Videogame Vault / Cataloguer
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. DROP EXISTING CONSTRAINTS (Required before renaming target columns)
-- ----------------------------------------------------------------------------
ALTER TABLE `Consoles` 
  DROP FOREIGN KEY `fk_PublishersConsoles`;

ALTER TABLE `Subcategories` 
  DROP FOREIGN KEY `fk_CategoriesSubcategories`;

ALTER TABLE `Games` 
  DROP FOREIGN KEY `fk_CategoriesGames`,
  DROP FOREIGN KEY `fk_ConsolesGames`,
  DROP FOREIGN KEY `fk_LanguagesGames`,
  DROP FOREIGN KEY `fk_PublishersGames`,
  DROP FOREIGN KEY `fk_SubcategoriesGames`;

-- ----------------------------------------------------------------------------
-- 2. RENAME & NORMALIZE: Categories
-- ----------------------------------------------------------------------------
RENAME TABLE `Categories` TO `categories`;

ALTER TABLE `categories`
  RENAME COLUMN `ID` TO `id`,
  RENAME COLUMN `Category` TO `name`;

-- ----------------------------------------------------------------------------
-- 3. RENAME & NORMALIZE: Languages
-- ----------------------------------------------------------------------------
RENAME TABLE `Languages` TO `languages`;

ALTER TABLE `languages`
  RENAME COLUMN `ID` TO `id`,
  RENAME COLUMN `Language` TO `name`;

-- ----------------------------------------------------------------------------
-- 4. RENAME & NORMALIZE: Publishers
-- ----------------------------------------------------------------------------
RENAME TABLE `Publishers` TO `publishers`;

ALTER TABLE `publishers`
  RENAME COLUMN `ID` TO `id`,
  RENAME COLUMN `Publisher` TO `name`,
  RENAME COLUMN `Console Maker` TO `is_console_maker`;

-- ----------------------------------------------------------------------------
-- 5. RENAME & NORMALIZE: Subcategories
-- ----------------------------------------------------------------------------
RENAME TABLE `Subcategories` TO `subcategories`;

ALTER TABLE `subcategories`
  RENAME COLUMN `ID` TO `id`,
  RENAME COLUMN `Category ID` TO `category_id`,
  RENAME COLUMN `Subcategory` TO `name`;

-- ----------------------------------------------------------------------------
-- 6. RENAME & NORMALIZE: Consoles
-- ----------------------------------------------------------------------------
RENAME TABLE `Consoles` TO `consoles`;

ALTER TABLE `consoles`
  RENAME COLUMN `ID` TO `id`,
  RENAME COLUMN `Console` TO `name`,
  RENAME COLUMN `Publisher ID` TO `publisher_id`,
  RENAME COLUMN `Year` TO `year`,
  RENAME COLUMN `Generation` TO `generation`,
  RENAME COLUMN `IsHandheld` TO `is_handheld`,
  RENAME COLUMN `IsComputer` TO `is_computer`,
  RENAME COLUMN `IsArcade` TO `is_arcade`,
  RENAME COLUMN `Image` TO `image_path`,
  RENAME COLUMN `Logo` TO `logo_path`,
  RENAME COLUMN `Comments` TO `comments`,
  RENAME COLUMN `Emulator` TO `emulator`,
  RENAME COLUMN `Emulator Link` TO `emulator_link`,
  RENAME COLUMN `EmulatorAndroid` TO `emulator_android`,
  RENAME COLUMN `EmulatorAndroid Link` TO `emulator_android_link`,
  RENAME COLUMN `RetroArchCore` TO `retroarch_core`,
  RENAME COLUMN `Core Link` TO `core_link`,
  RENAME COLUMN `IsForReference` TO `is_for_reference`;

-- ----------------------------------------------------------------------------
-- 7. RENAME & NORMALIZE: Games
-- ----------------------------------------------------------------------------
RENAME TABLE `Games` TO `games`;

ALTER TABLE `games`
  RENAME COLUMN `ID` TO `id`,
  RENAME COLUMN `Game` TO `title`,
  RENAME COLUMN `Console ID` TO `console_id`,
  RENAME COLUMN `Category ID` TO `category_id`,
  RENAME COLUMN `Subcategory ID` TO `subcategory_id`,
  RENAME COLUMN `Language ID` TO `language_id`,
  RENAME COLUMN `Publisher ID` TO `publisher_id`,
  RENAME COLUMN `Year` TO `year`,
  RENAME COLUMN `Tags` TO `tags`,
  RENAME COLUMN `Image` TO `screenshot_path`,
  RENAME COLUMN `BoxArt` TO `boxart_path`,
  RENAME COLUMN `InCollection` TO `in_collection`,
  RENAME COLUMN `Played` TO `is_played`,
  RENAME COLUMN `Won` TO `is_won`,
  RENAME COLUMN `Comments` TO `comments`;
  
-- ----------------------------------------------------------------------------
-- 8. RENAME & NORMALIZE: Orphans
-- ----------------------------------------------------------------------------
RENAME TABLE `Orphans` TO `orphans`;

-- ----------------------------------------------------------------------------
-- 9. RE-ATTACH FOREIGN KEYS WITH STANDARD CONSTRAINTS
-- ----------------------------------------------------------------------------
ALTER TABLE `consoles`
  ADD CONSTRAINT `fk_consoles_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `subcategories`
  ADD CONSTRAINT `fk_subcategories_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `games`
  ADD CONSTRAINT `fk_games_console` FOREIGN KEY (`console_id`) REFERENCES `consoles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_games_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_games_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_games_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_games_language` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;