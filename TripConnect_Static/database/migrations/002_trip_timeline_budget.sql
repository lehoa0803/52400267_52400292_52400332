-- TripConnect - Phase 2 itinerary timeline and budget migration
--
-- IMPORTANT:
-- 1. Run 002_trip_timeline_budget_preflight.sql first.
-- 2. Back up the tripconnect database before executing this file.
-- 3. This file is intended to be imported while tripconnect is selected.
-- 4. It does not query or modify information_schema.
-- 5. Run this migration once only, after confirming the preflight output.
-- 6. It does not change trips.status or any existing row data.

-- ================================================================
-- SCHEMA CHANGES: tripconnect application database only
-- ================================================================

ALTER TABLE `tripconnect`.`trips`
    ADD COLUMN departure_at DATETIME NULL AFTER description,
    ADD COLUMN return_at DATETIME NULL AFTER departure_at,
    ADD COLUMN start_location VARCHAR(150) NULL AFTER location_id,
    ADD COLUMN participant_count SMALLINT UNSIGNED NULL AFTER days_count;

ALTER TABLE `tripconnect`.`trip_days`
    ADD COLUMN day_date DATE NULL AFTER day_number;

ALTER TABLE `tripconnect`.`activities`
    ADD COLUMN start_at DATETIME NULL AFTER start_time,
    ADD COLUMN end_at DATETIME NULL AFTER start_at,
    ADD COLUMN sort_order SMALLINT UNSIGNED NULL AFTER trip_day_id,
    ADD COLUMN cost_scope VARCHAR(20) NULL AFTER cost,
    ADD COLUMN notes TEXT NULL AFTER description,
    ADD COLUMN menu_suggestions TEXT NULL AFTER notes,
    ADD COLUMN nearby_amenities TEXT NULL AFTER menu_suggestions,
    ADD COLUMN location_source VARCHAR(20) NULL AFTER image,
    ADD COLUMN location_source_ref VARCHAR(255) NULL AFTER location_source,
    ADD COLUMN location_verified_at DATETIME NULL AFTER location_source_ref;

CREATE INDEX idx_trip_days_timeline
    ON `tripconnect`.`trip_days` (trip_id, day_number, day_date);

CREATE INDEX idx_activities_timeline
    ON `tripconnect`.`activities` (trip_day_id, sort_order, start_at, end_at);

-- ================================================================
-- APPLICATION CONTRACT (documentation; no SQL execution)
-- ================================================================
-- trips.location_id remains the canonical destination location.
-- trips.budget_estimate remains the user's target budget.
-- No calculated total is stored in this migration. The authoritative total
-- must always be calculated from activities before display/save, so it cannot
-- become stale when an activity or participant count changes.
--
-- activities.cost is the amount for the selected cost_scope:
--   per_person = cost * participant_count
--   fixed      = cost for the whole activity/group, not multiplied
-- NULL cost_scope preserves the meaning of legacy activities until edited.
-- New activities must use one of the two values above. The old 'group' value
-- is intentionally not introduced because it is mathematically identical to
-- 'fixed'; the UI can label fixed as "cả nhóm" when appropriate.
--
-- start_at/end_at are the canonical fields for new activities. They are
-- nullable so existing activities continue to work. start_time remains as a
-- legacy display fallback and must not receive an automatic 08:00 value.
-- An activity may cross midnight when end_at is later than start_at and may
-- span multiple dates. Its trip_day_id is the day containing start_at.
--
-- location_source values are application-controlled values such as:
--   user, verified, unknown
-- location_source_ref stores a permitted provider/reference when available.
-- location_verified_at is populated only after a trusted provider response is
-- received and validated by the server. User-entered values must never be
-- marked verified automatically.
