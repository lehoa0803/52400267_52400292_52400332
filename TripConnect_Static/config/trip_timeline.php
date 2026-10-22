<?php

function trip_timeline_categories(): array
{
    return [
        'sightseeing' => 'Tham quan',
        'food' => 'Ăn uống',
        'transport' => 'Di chuyển',
        'lodging' => 'Lưu trú',
        'rest' => 'Nghỉ ngơi',
        'free_time' => 'Thời gian tự do',
        'other' => 'Khác',
    ];
}

function trip_timeline_category_label(?string $category): string
{
    $category = trim((string) $category);
    $labels = trip_timeline_categories();
    if (isset($labels[$category])) {
        return $labels[$category];
    }

    $legacy = [
        'Ăn uống' => 'Ăn uống',
        'Tham quan' => 'Tham quan',
        'Di chuyển' => 'Di chuyển',
        'Lưu trú' => 'Lưu trú',
        'Nghỉ ngơi' => 'Nghỉ ngơi',
        'Khác' => 'Khác',
    ];

    return $legacy[$category] ?? ($category !== '' ? $category : 'Khác');
}

function trip_timeline_category_code(?string $category): string
{
    $category = trim((string) $category);
    if (isset(trip_timeline_categories()[$category])) {
        return $category;
    }

    return [
        'Ăn uống' => 'food',
        'Tham quan' => 'sightseeing',
        'Di chuyển' => 'transport',
        'Lưu trú' => 'lodging',
        'Nghỉ ngơi' => 'rest',
        'Thời gian tự do' => 'free_time',
        'Khác' => 'other',
    ][$category] ?? 'other';
}

function trip_timeline_parse_datetime(?string $value): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timezone = new DateTimeZone('Asia/Ho_Chi_Minh');
    foreach (['!Y-m-d\\TH:i', '!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
        // MySQL DATETIME và datetime-local đều là giờ địa phương, không phải UTC.
        $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date;
        }
    }

    return null;
}

function trip_timeline_mysql_datetime(?DateTimeImmutable $date): ?string
{
    return $date ? $date->format('Y-m-d H:i:s') : null;
}

function trip_timeline_display_datetime(?string $value): string
{
    $date = trip_timeline_parse_datetime($value);
    return $date ? $date->format('Y-m-d\\TH:i') : '';
}

function trip_timeline_day_date(?DateTimeImmutable $departure, int $dayNumber): ?string
{
    if (!$departure || $dayNumber < 1) {
        return null;
    }

    return $departure->modify('+' . ($dayNumber - 1) . ' days')->format('Y-m-d');
}

function trip_timeline_normalize_payload($decoded, int $daysCount, ?DateTimeImmutable $departure, ?DateTimeImmutable $return, string $status, array $trustedLocationMetadata = [], array $catalogMetadata = []): array
{
    $errors = [];
    $warnings = [];
    $normalized = [];
    $allTimed = [];
    $validCategories = array_keys(trip_timeline_categories());

    if (!is_array($decoded)) {
        return ['data' => [], 'errors' => ['Dữ liệu timeline không hợp lệ.'], 'warnings' => []];
    }

    for ($dayNumber = 1; $dayNumber <= $daysCount; $dayNumber++) {
        $key = 'day_' . $dayNumber;
        $dayActivities = isset($decoded[$key]) && is_array($decoded[$key]) ? $decoded[$key] : [];
        $normalized[$key] = [];

        foreach ($dayActivities as $index => $activity) {
            if (!is_array($activity)) {
                $errors[] = "Hoạt động thứ " . ($index + 1) . " của ngày $dayNumber không hợp lệ.";
                continue;
            }

            $title = trim((string) ($activity['title'] ?? ''));
            $location = trim((string) ($activity['location'] ?? $activity['location_name'] ?? ''));
            $description = trim((string) ($activity['description'] ?? ''));
            $notes = trim((string) ($activity['notes'] ?? ''));
            $menu = trim((string) ($activity['menu_suggestions'] ?? ''));
            $amenities = trim((string) ($activity['nearby_amenities'] ?? ''));
            $category = trip_timeline_category_code($activity['category'] ?? 'other');
            $costScope = trim((string) ($activity['cost_scope'] ?? 'fixed'));
            $costRaw = $activity['cost'] ?? 0;

            if ($title === '' || mb_strlen($title) > 150) {
                $errors[] = "Tên hoạt động ngày $dayNumber không hợp lệ hoặc vượt quá 150 ký tự.";
            }
            if ($location === '' || mb_strlen($location) > 150) {
                $errors[] = "Địa điểm hoạt động ngày $dayNumber không hợp lệ hoặc vượt quá 150 ký tự.";
            }
            if (!in_array($category, $validCategories, true)) {
                $errors[] = "Loại hoạt động của ngày $dayNumber không hợp lệ.";
                $category = 'other';
            }
            if (!in_array($costScope, ['per_person', 'fixed'], true)) {
                $errors[] = "Phạm vi chi phí của ngày $dayNumber không hợp lệ.";
                $costScope = 'fixed';
            }
            if (!is_numeric($costRaw) || (float) $costRaw < 0) {
                $errors[] = "Chi phí hoạt động ngày $dayNumber không hợp lệ.";
                $costRaw = 0;
            }

            // Các form mới gửi giờ riêng theo từng nhóm ngày. Luôn dựng lại
            // DATETIME từ ngày của nhóm + giờ đó để không dùng lại start_at
            // cũ sau một lần submit/validation thất bại.
            $startValue = (string) ($activity['start_at'] ?? $activity['startAt'] ?? '');
            $endValue = (string) ($activity['end_at'] ?? $activity['endAt'] ?? '');
            $startTime = trim((string) ($activity['start_time'] ?? ''));
            $endTime = trim((string) ($activity['end_time'] ?? ''));
            $timelineDayDate = trip_timeline_day_date($departure, $dayNumber);
            if ($timelineDayDate && preg_match('/^\d{2}:\d{2}$/', $startTime) && preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                $startValue = $timelineDayDate . 'T' . $startTime;
                $endDate = !empty($activity['overnight']) && $activity['overnight'] === true
                    ? trip_timeline_day_date($departure, $dayNumber + 1)
                    : $timelineDayDate;
                $endValue = ($endDate ?: $timelineDayDate) . 'T' . $endTime;
            }
            $startAt = trip_timeline_parse_datetime($startValue);
            $endAt = trip_timeline_parse_datetime($endValue);

            if (($startAt && !$endAt) || (!$startAt && $endAt)) {
                $errors[] = "Hoạt động '$title' phải có đủ thời gian bắt đầu và kết thúc.";
            }
            if ($startAt && $endAt && $endAt <= $startAt) {
                $errors[] = "Hoạt động '$title' phải kết thúc sau thời gian bắt đầu.";
            }

            if (!$startAt || !$endAt) {
                $message = "Hoạt động '$title' chưa có đủ thời gian.";
                $isLegacyActivity = isset($activity['id']) && ctype_digit((string) $activity['id']) && (int) $activity['id'] > 0;
                if ($status === 'published' && !$isLegacyActivity) {
                    $errors[] = $message . ' Không thể xuất bản.';
                } else {
                    $warnings[] = $message;
                }
            }

            if ($departure && $startAt && $startAt < $departure) {
                $message = "Hoạt động '$title' bắt đầu trước thời gian khởi hành (khởi hành " . $departure->format('Y-m-d H:i:s') . ", bắt đầu " . $startAt->format('Y-m-d H:i:s') . ').';
                $status === 'published' ? $errors[] = $message : $warnings[] = $message;
            }
            if ($return && $endAt && $endAt > $return) {
                $message = "Hoạt động '$title' kết thúc sau thời gian trở về.";
                $status === 'published' ? $errors[] = $message : $warnings[] = $message;
            }

            if ($departure && $startAt) {
                // Xác định ngày theo phần ngày lịch, không theo số giờ chênh lệch.
                // Nhờ đó 05:30 ngày 22/10 vẫn là Ngày 1 dù khởi hành lúc 04:34.
                $departureDate = DateTimeImmutable::createFromFormat('!Y-m-d', $departure->format('Y-m-d'), new DateTimeZone('Asia/Ho_Chi_Minh'));
                $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $startAt->format('Y-m-d'), new DateTimeZone('Asia/Ho_Chi_Minh'));
                $expectedDay = $departureDate && $startDate ? ((int) $departureDate->diff($startDate)->format('%r%a') + 1) : 0;
                if ($expectedDay !== $dayNumber) {
                    $message = "Hoạt động '$title' đang nằm ở ngày $dayNumber nhưng ngày thực tế của thời gian bắt đầu là ngày $expectedDay.";
                    $status === 'published' ? $errors[] = $message : $warnings[] = $message;
                }
            }

            $activityId = isset($activity['id']) && ctype_digit((string) $activity['id']) ? (int) $activity['id'] : 0;
            $catalogId = filter_var($activity['location_catalog_id'] ?? 0, FILTER_VALIDATE_INT);
            $catalogId = ($catalogId && $catalogId > 0) ? (int) $catalogId : null;
            $catalogRecord = $catalogId && isset($catalogMetadata[$catalogId]) ? $catalogMetadata[$catalogId] : null;
            // Hidden catalog entries may remain attached to existing activities, but cannot be
            // introduced by a new activity payload.
             if ($catalogRecord && ($catalogRecord['status'] ?? 'active') !== 'active' && $activityId <= 0) {
                 $errors[] = "Địa điểm catalog đã bị ẩn và không thể dùng cho hoạt động mới.";
                 $catalogRecord = null;
                 $catalogId = null;
             }
            $trusted = $trustedLocationMetadata[$activityId] ?? null;
            $sameLatitude = (($trusted['latitude'] ?? null) === null && ($activity['latitude'] ?? null) === null)
                || (is_numeric($trusted['latitude'] ?? null) && is_numeric($activity['latitude'] ?? null) && abs((float) $trusted['latitude'] - (float) $activity['latitude']) < 0.00000001);
            $sameLongitude = (($trusted['longitude'] ?? null) === null && ($activity['longitude'] ?? null) === null)
                || (is_numeric($trusted['longitude'] ?? null) && is_numeric($activity['longitude'] ?? null) && abs((float) $trusted['longitude'] - (float) $activity['longitude']) < 0.00000001);
            $sameTrustedLocation = $trusted
                && (string) ($trusted['location_name'] ?? '') === $location
                && (string) ($trusted['image'] ?? '') === trim((string) ($activity['image'] ?? ''))
                && $sameLatitude
                && $sameLongitude;
            if ($catalogRecord) {
                $location = (string) $catalogRecord['name'];
            }
            $item = [
                'id' => $activityId,
                'location_catalog_id' => $catalogRecord ? (int) $catalogRecord['id'] : null,
                'start_at' => trip_timeline_mysql_datetime($startAt),
                'end_at' => trip_timeline_mysql_datetime($endAt),
                'title' => $title,
                'location_name' => $location,
                'description' => mb_substr($description, 0, 10000),
                'notes' => mb_substr($notes, 0, 10000),
                'menu_suggestions' => mb_substr($menu, 0, 10000),
                'nearby_amenities' => mb_substr($amenities, 0, 10000),
                'cost' => number_format((float) $costRaw, 2, '.', ''),
                'cost_scope' => $costScope,
                'category' => $category,
                'sort_order' => $index + 1,
                'location_source' => $catalogRecord ? (string) $catalogRecord['location_source'] : (($sameTrustedLocation && ($trusted['location_source'] ?? '') === 'verified') ? 'verified' : 'user'),
                'location_source_ref' => $catalogRecord ? (string) ($catalogRecord['location_source_ref'] ?? '') : ($sameTrustedLocation && ($trusted['location_source'] ?? '') === 'verified' ? (string) ($trusted['location_source_ref'] ?? '') : ''),
                'location_verified_at' => $catalogRecord ? ($catalogRecord['location_verified_at'] ?? null) : ($sameTrustedLocation && ($trusted['location_source'] ?? '') === 'verified' ? ($trusted['location_verified_at'] ?? null) : null),
                'latitude' => $catalogRecord && is_numeric($catalogRecord['latitude'] ?? null) ? (float) $catalogRecord['latitude'] : (is_numeric($activity['latitude'] ?? null) ? (float) $activity['latitude'] : null),
                'longitude' => $catalogRecord && is_numeric($catalogRecord['longitude'] ?? null) ? (float) $catalogRecord['longitude'] : (is_numeric($activity['longitude'] ?? null) ? (float) $activity['longitude'] : null),
                'image' => $catalogRecord ? trim((string) ($catalogRecord['image_url'] ?? '')) : trim((string) ($activity['image'] ?? '')),
            ];

            if ($item['location_source'] !== 'verified') {
                $item['location_source_ref'] = '';
            }

            $normalized[$key][] = $item;
            if ($startAt && $endAt) {
                $allTimed[] = [$startAt, $endAt, $title, $key];
            }
        }
    }

    usort($allTimed, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
    for ($i = 1, $count = count($allTimed); $i < $count; $i++) {
        if ($allTimed[$i][0] < $allTimed[$i - 1][1]) {
            $message = "Hai hoạt động bị chồng chéo: '{$allTimed[$i - 1][2]}' và '{$allTimed[$i][2]}'.";
            $status === 'published' ? $errors[] = $message : $warnings[] = $message;
        }
    }

    return ['data' => $normalized, 'errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
}
