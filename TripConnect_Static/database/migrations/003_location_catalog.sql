-- TripConnect - Phase 3 verified location catalog
--
-- IMPORTANT:
-- 1. Run 003_location_catalog_preflight.sql first and review every result.
-- 2. Back up the tripconnect database before executing this file.
-- 3. Run once while the tripconnect database is selected in phpMyAdmin.
-- 4. This migration does not use or modify information_schema.
-- 5. It does not change, delete, or rewrite existing rows.
-- 6. It deliberately preserves locations.name UNIQUE because locations currently
--    represents the 65 area/destination records. Specific places, including names
--    that repeat in different addresses, are stored in location_catalog.

CREATE TABLE IF NOT EXISTS `tripconnect`.`location_catalog` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `area_location_id` INT NULL,
    `name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `category` VARCHAR(50) NULL,
    `latitude` DECIMAL(10,8) NULL,
    `longitude` DECIMAL(11,8) NULL,
    `image_url` VARCHAR(500) NULL,
    `image_source_url` VARCHAR(500) NULL,
    `image_attribution` VARCHAR(500) NULL,
    `image_license` VARCHAR(100) NULL,
    `location_source` VARCHAR(30) NOT NULL DEFAULT 'user',
    `location_source_ref` VARCHAR(255) NULL,
    `source_url` VARCHAR(500) NULL,
    `location_verified_at` DATETIME NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_location_catalog_area` (`area_location_id`),
    KEY `idx_location_catalog_name` (`name`),
    KEY `idx_location_catalog_category` (`category`),
    KEY `idx_location_catalog_status` (`status`),
    UNIQUE KEY `uq_location_catalog_source_ref` (`location_source`, `location_source_ref`),
    CONSTRAINT `location_catalog_ibfk_area`
        FOREIGN KEY (`area_location_id`) REFERENCES `tripconnect`.`locations` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `location_catalog_ibfk_creator`
        FOREIGN KEY (`created_by`) REFERENCES `tripconnect`.`users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `tripconnect`.`activities`
    ADD COLUMN `location_catalog_id` INT NULL AFTER `trip_day_id`;

CREATE INDEX `idx_activities_location_catalog`
    ON `tripconnect`.`activities` (`location_catalog_id`);

ALTER TABLE `tripconnect`.`activities`
    ADD CONSTRAINT `activities_ibfk_location_catalog`
        FOREIGN KEY (`location_catalog_id`) REFERENCES `tripconnect`.`location_catalog` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Data contract for later application code:
-- * locations remains the area/destination table used by trips.location_id.
-- * location_catalog stores specific POIs and may contain repeated names when
--   address, area_location_id, or source_ref differs.
-- * activities.location_catalog_id is nullable so all existing activities remain valid.
-- * activities.location_name remains the user-visible snapshot and is not overwritten.
-- * location_source='user' is not verified. Only an explicit review/import process
--   may set location_verified_at and a trusted source value.
-- * Blank source references must be stored as NULL, not an empty string, so the
--   source-reference uniqueness rule does not merge unrelated manual locations.
