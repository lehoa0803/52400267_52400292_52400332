-- TripConnect 004: optional metadata for explicitly selected cover images.
-- MariaDB 10.4/phpMyAdmin compatible. No data is deleted or overwritten.
-- Do not import until the preflight has been reviewed.

ALTER TABLE trips
    ADD COLUMN IF NOT EXISTS cover_source VARCHAR(30) NULL AFTER cover_image,
    ADD COLUMN IF NOT EXISTS cover_source_url VARCHAR(500) NULL AFTER cover_source,
    ADD COLUMN IF NOT EXISTS cover_attribution VARCHAR(500) NULL AFTER cover_source_url,
    ADD COLUMN IF NOT EXISTS cover_license VARCHAR(100) NULL AFTER cover_attribution,
    ADD COLUMN IF NOT EXISTS cover_license_url VARCHAR(500) NULL AFTER cover_license,
    ADD COLUMN IF NOT EXISTS cover_selected_at DATETIME NULL AFTER cover_license_url;
