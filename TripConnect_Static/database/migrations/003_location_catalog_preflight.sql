-- TripConnect - Phase 3 location catalog preflight
-- Read-only diagnostics. This file does not change any schema or data.
-- Run while the tripconnect database is selected in phpMyAdmin.
-- It intentionally does not use information_schema as the active database.

SELECT DATABASE() AS selected_database;

SELECT TABLE_NAME, TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME IN ('locations', 'trips', 'trip_days', 'activities', 'location_catalog')
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME IN ('locations', 'trips', 'trip_days', 'activities', 'location_catalog')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME IN ('locations', 'trips', 'trip_days', 'activities', 'location_catalog')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME IN ('locations', 'trips', 'trip_days', 'activities', 'location_catalog')
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

SELECT
    (SELECT COUNT(*) FROM tripconnect.locations) AS area_rows,
    (SELECT COUNT(*) FROM tripconnect.trips) AS trip_rows,
    (SELECT COUNT(*) FROM tripconnect.trip_days) AS trip_day_rows,
    (SELECT COUNT(*) FROM tripconnect.activities) AS activity_rows;

SELECT
    SUM(latitude IS NOT NULL AND longitude IS NOT NULL) AS activities_with_coordinates,
    SUM(image IS NOT NULL AND image <> '') AS activities_with_images,
    SUM(location_source = 'verified') AS activities_marked_verified
FROM tripconnect.activities;

-- The current locations.name unique key is deliberately not changed by 003.
-- This result identifies the existing key and any duplicate names for review.
SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS indexed_columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME = 'locations'
GROUP BY INDEX_NAME, NON_UNIQUE;

SELECT name, COUNT(*) AS duplicate_count
FROM tripconnect.locations
GROUP BY name
HAVING COUNT(*) > 1;

-- Expected before import: location_catalog is absent and activities.location_catalog_id is absent.
SELECT COUNT(*) AS existing_location_catalog_tables
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME = 'location_catalog';

SELECT COUNT(*) AS existing_activity_location_catalog_columns
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME = 'activities'
  AND COLUMN_NAME = 'location_catalog_id';
