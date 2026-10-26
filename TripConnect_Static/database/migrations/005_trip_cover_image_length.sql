-- TripConnect - widen cover image URL storage
-- MariaDB 10.4 / phpMyAdmin compatible.
-- Run 005_trip_cover_image_preflight.sql and take a verified backup first.
-- This changes only the trips.cover_image column; existing values are preserved.

ALTER TABLE `tripconnect`.`trips`
    MODIFY COLUMN `cover_image` VARCHAR(1000) NULL;

-- Conditional rollback plan (do not run blindly):
-- First verify that MAX(CHAR_LENGTH(cover_image)) <= 255 and that no value
-- depends on the widened column, then run:
-- ALTER TABLE `tripconnect`.`trips`
--     MODIFY COLUMN `cover_image` VARCHAR(255) NULL;
