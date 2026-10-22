-- READ-ONLY PREFLIGHT for 004_trip_cover_image_metadata.sql
-- MariaDB/phpMyAdmin: run this file first and review the result.
-- This file does not change schema or data.

SHOW COLUMNS FROM trips LIKE 'cover_source';
SHOW COLUMNS FROM trips LIKE 'cover_source_url';
SHOW COLUMNS FROM trips LIKE 'cover_attribution';
SHOW COLUMNS FROM trips LIKE 'cover_license';
SHOW COLUMNS FROM trips LIKE 'cover_license_url';
SHOW COLUMNS FROM trips LIKE 'cover_selected_at';

SELECT id, title, cover_image
FROM trips
WHERE cover_image IS NOT NULL AND TRIM(cover_image) <> ''
ORDER BY id;
