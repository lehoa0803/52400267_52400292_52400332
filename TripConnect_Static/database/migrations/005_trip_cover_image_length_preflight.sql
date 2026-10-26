-- TripConnect - read-only preflight for 005_trip_cover_image_length.sql
-- MariaDB 10.4 / phpMyAdmin compatible. This file performs no schema changes.

SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE,
       IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME = 'trips'
  AND COLUMN_NAME = 'cover_image';

SELECT COUNT(*) AS cover_rows,
       MAX(CHAR_LENGTH(cover_image)) AS max_cover_length,
       SUM(CHAR_LENGTH(cover_image) >= 255) AS rows_at_or_over_255
FROM tripconnect.trips
WHERE cover_image IS NOT NULL;

-- Expected before migration: one VARCHAR(255) cover_image column.
-- Review the results and take a full database backup before importing 005.
