-- TripConnect - separate moderation state from publication state
-- MariaDB 10.4 / phpMyAdmin compatible.
-- Run 006_trip_moderation_preflight.sql and take a verified backup first.
-- Existing publication status and rows are preserved.

ALTER TABLE `tripconnect`.`trips`
    ADD COLUMN `moderation_status` VARCHAR(20) NOT NULL DEFAULT 'visible' AFTER `status`,
    ADD KEY `idx_trips_moderation_status` (`moderation_status`);

-- Conditional rollback plan (only after confirming no hidden rows depend on it):
-- ALTER TABLE `tripconnect`.`trips`
--     DROP INDEX `idx_trips_moderation_status`,
--     DROP COLUMN `moderation_status`;
