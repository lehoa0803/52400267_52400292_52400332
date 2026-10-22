-- TripConnect - read-only preflight for 006_trip_moderation.sql
-- No schema or data changes are performed here.

SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE,
       IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME = 'trips'
  AND COLUMN_NAME IN ('status', 'moderation_status');

SELECT status, COUNT(*) AS total
FROM tripconnect.trips
GROUP BY status
ORDER BY status;

SELECT COUNT(*) AS existing_moderation_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME = 'trips'
  AND COLUMN_NAME = 'moderation_status';

-- Expected before migration: moderation_status is absent and status remains
-- enum('draft','published','deleted'). Review before importing 006.
