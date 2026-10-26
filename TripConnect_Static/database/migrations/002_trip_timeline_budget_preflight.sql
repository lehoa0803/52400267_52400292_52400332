-- TripConnect - Phase 2 migration preflight (read-only)
--
-- This file only reads metadata. It does not use or modify information_schema
-- as a selected database and does not change any table.
-- Run it with phpMyAdmin's SQL tab while the tripconnect database is selected.

SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME IN ('trips', 'trip_days', 'activities');

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND (
      (TABLE_NAME = 'trips' AND COLUMN_NAME IN (
          'departure_at', 'return_at', 'start_location', 'participant_count'
      ))
      OR
      (TABLE_NAME = 'trip_days' AND COLUMN_NAME IN ('day_date'))
      OR
      (TABLE_NAME = 'activities' AND COLUMN_NAME IN (
          'start_at', 'end_at', 'sort_order', 'cost_scope',
          'notes', 'menu_suggestions', 'nearby_amenities',
          'location_source', 'location_source_ref', 'location_verified_at'
      ))
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'tripconnect'
  AND TABLE_NAME IN ('trips', 'trip_days', 'activities')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
