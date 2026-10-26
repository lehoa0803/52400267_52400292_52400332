<?php
declare(strict_types=1);

/**
 * Read catalog records server-side and overlay canonical location fields onto
 * submitted activities. Client-provided coordinates/source values are never
 * trusted when a catalog ID is present.
 */
function trip_location_catalog_ids(array $decoded): array
{
    $ids = [];
    foreach ($decoded as $dayActivities) {
        if (!is_array($dayActivities)) continue;
        foreach ($dayActivities as $activity) {
            if (!is_array($activity)) continue;
            $id = filter_var($activity['location_catalog_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($id && $id > 0) $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function trip_location_catalog_load(PDO $conn, array $decoded): array
{
    $ids = trip_location_catalog_ids($decoded);
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // Load hidden records too so existing activities keep their historical link when edited.
    // New selections are restricted below by status and by the search endpoint.
    $stmt = $conn->prepare("SELECT id, name, address, description, category, latitude, longitude, image_url, location_source, location_source_ref, location_verified_at, status FROM location_catalog WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $records = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) $records[(int) $record['id']] = $record;
    return $records;
}

function trip_location_catalog_apply(array $decoded, array $catalog): array
{
    foreach ($decoded as $dayKey => $dayActivities) {
        if (!is_array($dayActivities)) continue;
        foreach ($dayActivities as $index => $activity) {
            if (!is_array($activity)) continue;
            $id = filter_var($activity['location_catalog_id'] ?? 0, FILTER_VALIDATE_INT);
            if (!$id || !isset($catalog[$id])) continue;
            $record = $catalog[$id];
            $decoded[$dayKey][$index]['location_catalog_id'] = (int) $record['id'];
            $decoded[$dayKey][$index]['location'] = (string) $record['name'];
            $decoded[$dayKey][$index]['latitude'] = $record['latitude'];
            $decoded[$dayKey][$index]['longitude'] = $record['longitude'];
            $decoded[$dayKey][$index]['image'] = $record['image_url'] ?? '';
            $decoded[$dayKey][$index]['location_source'] = $record['location_source'];
            $decoded[$dayKey][$index]['location_source_ref'] = $record['location_source_ref'] ?? '';
            $decoded[$dayKey][$index]['location_verified_at'] = $record['location_verified_at'];
        }
    }
    return $decoded;
}
