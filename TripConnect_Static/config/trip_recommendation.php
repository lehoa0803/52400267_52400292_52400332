<?php

function trip_recommendation_category_aliases(): array
{
    return [
        'sightseeing' => ['sightseeing', 'tham quan', 'thamquan'],
        'food' => ['food', 'ăn uống', 'an uong'],
        'transport' => ['transport', 'di chuyển', 'di chuyen'],
        'lodging' => ['lodging', 'lưu trú', 'luu tru', 'nghỉ dưỡng', 'nghi duong'],
        'rest' => ['rest', 'nghỉ ngơi', 'nghi ngoi'],
        'free_time' => ['free_time', 'thời gian tự do', 'thoi gian tu do'],
        'entertainment' => ['entertainment', 'giải trí', 'giai tri'],
        'other' => ['other', 'khác', 'khac', 'trải nghiệm', 'trai nghiem', 'mua sắm', 'mua sam'],
    ];
}

function trip_recommendation_normalize_category(string $category): string
{
    $needle = mb_strtolower(trim($category), 'UTF-8');
    foreach (trip_recommendation_category_aliases() as $code => $aliases) {
        if (in_array($needle, $aliases, true)) {
            return $code;
        }
    }

    return 'other';
}

function trip_recommendation_get_decimal($value): ?float
{
    if ($value === null || is_array($value) || is_object($value) || trim((string) $value) === '') {
        return null;
    }

    $number = filter_var($value, FILTER_VALIDATE_FLOAT);
    return $number !== false && $number >= 0 ? (float) $number : null;
}

function trip_recommendation_parse_interests(?string $value): array
{
    if (!$value) {
        return [];
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return [];
    }

    $interests = [];
    foreach ($decoded as $interest) {
        if (is_string($interest) && trim($interest) !== '') {
            $interests[] = mb_strtolower(trim($interest), 'UTF-8');
        }
    }

    return array_values(array_unique($interests));
}

function trip_recommendation_search(PDO $conn, array $input): array
{
    $page = max(1, min(10000, (int) ($input['page'] ?? 1)));
    $pageSize = 9;
    $keyword = trim((string) ($input['q'] ?? ''));
    $keyword = mb_substr($keyword, 0, 150, 'UTF-8');
    $location = trim((string) ($input['location'] ?? ''));
    $location = mb_substr($location, 0, 150, 'UTF-8');
    $days = filter_var($input['days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
    $category = trim((string) ($input['category'] ?? ''));
    $category = array_key_exists($category, trip_recommendation_category_aliases()) ? $category : '';
    $budgetScope = ($input['budget_scope'] ?? 'group') === 'personal' ? 'personal' : 'group';
    $budgetMax = trip_recommendation_get_decimal($input['budget_max'] ?? null);
    $budgetMin = null;

    // Giữ tương thích với form tìm kiếm cũ từ index.php: budget=low|mid|high.
    $legacyBudget = trim((string) ($input['budget'] ?? ''));
    if ($budgetMax === null && in_array($legacyBudget, ['low', 'mid', 'high'], true)) {
        if ($legacyBudget === 'low') {
            $budgetMax = 3000000.0;
        } elseif ($legacyBudget === 'mid') {
            $budgetMin = 3000000.0;
            $budgetMax = 5000000.0;
        } else {
            $budgetMin = 5000000.0;
        }
    }

    $sort = trim((string) ($input['sort'] ?? 'relevance'));
    $sort = in_array($sort, ['relevance', 'latest', 'popular', 'cost'], true) ? $sort : 'relevance';
    $useInterests = !empty($input['use_interests']) && !empty($input['user_id']);
    $interests = [];
    if ($useInterests) {
        $stmtInterest = $conn->prepare('SELECT travel_interests FROM users WHERE id = ? AND status = \'active\'');
        $stmtInterest->execute([(int) $input['user_id']]);
        $interests = trip_recommendation_parse_interests($stmtInterest->fetchColumn() ?: null);
    }

    $where = ["t.status = 'published'", "t.moderation_status = 'visible'"];
    $params = [];

    if ($keyword !== '') {
        $like = '%' . $keyword . '%';
        $where[] = '(t.title LIKE ? OR t.description LIKE ? OR l.name LIKE ? OR EXISTS (
            SELECT 1
            FROM trip_days sd
            JOIN activities sa ON sa.trip_day_id = sd.id
            LEFT JOIN location_catalog slc ON slc.id = sa.location_catalog_id AND slc.status = \'active\'
            WHERE sd.trip_id = t.id
              AND (sa.title LIKE ? OR sa.location_name LIKE ? OR slc.name LIKE ? OR slc.address LIKE ?)
        ))';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }

    if ($location !== '') {
        $like = '%' . $location . '%';
        $where[] = '(l.name LIKE ? OR EXISTS (
            SELECT 1
            FROM trip_days ld
            JOIN activities la ON la.trip_day_id = ld.id
            LEFT JOIN location_catalog llc ON llc.id = la.location_catalog_id AND llc.status = \'active\'
            LEFT JOIN locations al ON al.id = llc.area_location_id
            WHERE ld.trip_id = t.id
              AND (la.location_name LIKE ? OR llc.name LIKE ? OR llc.address LIKE ? OR al.name LIKE ?)
        ))';
        array_push($params, $like, $like, $like, $like, $like);
    }

    if ($days !== false && $days !== null) {
        $where[] = 't.days_count = ?';
        $params[] = (int) $days;
    }

    $categoryAliases = $category !== '' ? trip_recommendation_category_aliases()[$category] : [];
    if ($categoryAliases) {
        $placeholders = implode(',', array_fill(0, count($categoryAliases), '?'));
        $where[] = "EXISTS (
            SELECT 1
            FROM trip_days cd
            JOIN activities ca ON ca.trip_day_id = cd.id
            WHERE cd.trip_id = t.id AND LOWER(ca.category) IN ($placeholders)
        )";
        foreach ($categoryAliases as $alias) {
            $params[] = mb_strtolower($alias, 'UTF-8');
        }
    }

    $having = [];
    if ($budgetMax !== null || $budgetMin !== null) {
        $having[] = 'COUNT(a.id) > 0';
        $having[] = 'SUM(CASE WHEN a.id IS NOT NULL AND (a.cost IS NULL OR a.cost_scope IS NULL OR a.cost_scope NOT IN (\'per_person\', \'fixed\') OR (a.cost_scope = \'per_person\' AND (t.participant_count IS NULL OR t.participant_count < 1))) THEN 1 ELSE 0 END) = 0';
        $totalExpression = "SUM(CASE WHEN a.cost_scope = 'per_person' THEN a.cost * t.participant_count WHEN a.cost_scope = 'fixed' THEN a.cost ELSE 0 END)";
        if ($budgetMin !== null) {
            $having[] = $budgetScope === 'personal'
                ? "($totalExpression / NULLIF(t.participant_count, 0)) >= ?"
                : "$totalExpression >= ?";
            $params[] = $budgetMin;
        }
        if ($budgetMax !== null) {
            if ($budgetScope === 'personal') {
                $having[] = "($totalExpression / NULLIF(t.participant_count, 0)) <= ?";
            } else {
                $having[] = "$totalExpression <= ?";
            }
            $params[] = $budgetMax;
        }
    }

    $sql = "SELECT
                t.id, t.user_id, t.title, t.description, t.departure_at, t.return_at,
                t.days_count, t.participant_count, t.budget_estimate, t.cover_image,
                t.view_count, t.created_at,
                u.full_name AS author_name, u.avatar AS author_avatar,
                l.name AS location_name,
                (SELECT lc_image.image_url FROM trip_days image_days JOIN activities image_activities ON image_activities.trip_day_id = image_days.id JOIN location_catalog lc_image ON lc_image.id = image_activities.location_catalog_id WHERE image_days.trip_id = t.id AND NULLIF(TRIM(lc_image.image_url), '') IS NOT NULL ORDER BY image_days.day_number, COALESCE(image_activities.sort_order, 65535), image_activities.start_at, image_activities.id LIMIT 1) AS catalog_image,
                (SELECT activity_images.image FROM trip_days activity_days JOIN activities activity_images ON activity_images.trip_day_id = activity_days.id WHERE activity_days.trip_id = t.id AND NULLIF(TRIM(activity_images.image), '') IS NOT NULL ORDER BY activity_days.day_number, COALESCE(activity_images.sort_order, 65535), activity_images.start_at, activity_images.id LIMIT 1) AS activity_image,
                (SELECT COUNT(*) FROM likes lk WHERE lk.trip_id = t.id) AS like_count,
                COUNT(DISTINCT a.id) AS activity_count,
                SUM(CASE WHEN a.id IS NOT NULL AND (a.cost IS NULL OR a.cost_scope IS NULL OR a.cost_scope NOT IN ('per_person', 'fixed') OR (a.cost_scope = 'per_person' AND (t.participant_count IS NULL OR t.participant_count < 1))) THEN 1 ELSE 0 END) AS incomplete_cost_count,
                SUM(CASE WHEN a.cost_scope = 'per_person' THEN a.cost * t.participant_count WHEN a.cost_scope = 'fixed' THEN a.cost ELSE 0 END) AS calculated_total,
                GROUP_CONCAT(DISTINCT NULLIF(a.title, '') SEPARATOR ' || ') AS activity_titles,
                GROUP_CONCAT(DISTINCT NULLIF(a.location_name, '') SEPARATOR ' || ') AS activity_locations,
                GROUP_CONCAT(DISTINCT NULLIF(a.category, '') SEPARATOR ' || ') AS activity_categories,
                GROUP_CONCAT(DISTINCT NULLIF(lc.name, '') SEPARATOR ' || ') AS catalog_names,
                GROUP_CONCAT(DISTINCT NULLIF(lc.address, '') SEPARATOR ' || ') AS catalog_addresses
            FROM trips t
            LEFT JOIN users u ON u.id = t.user_id
            LEFT JOIN locations l ON l.id = t.location_id
            LEFT JOIN trip_days d ON d.trip_id = t.id
            LEFT JOIN activities a ON a.trip_day_id = d.id
            LEFT JOIN location_catalog lc ON lc.id = a.location_catalog_id AND lc.status = 'active'
            WHERE " . implode(' AND ', $where) . "
            GROUP BY t.id, t.user_id, t.title, t.description, t.departure_at, t.return_at,
                     t.days_count, t.participant_count, t.budget_estimate, t.cover_image,
                     t.view_count, t.created_at, u.full_name, u.avatar, l.name";
    if ($having) {
        $sql .= ' HAVING ' . implode(' AND ', $having);
    }

    if ($sort === 'latest') {
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC';
    } elseif ($sort === 'popular') {
        $sql .= ' ORDER BY t.view_count DESC, t.created_at DESC, t.id DESC';
    } elseif ($sort === 'cost') {
        $sql .= ' ORDER BY incomplete_cost_count ASC, calculated_total ASC, t.created_at DESC';
    } else {
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasCriteria = $keyword !== '' || $location !== '' || ($days !== false && $days !== null) || $category !== '' || $budgetMax !== null || $budgetMin !== null || $useInterests;

    $maxViews = 0;
    foreach ($rows as $row) {
        $maxViews = max($maxViews, (int) $row['view_count']);
    }

    foreach ($rows as &$row) {
        $activityText = mb_strtolower(implode(' ', array_filter([
            (string) ($row['activity_titles'] ?? ''),
            (string) ($row['activity_locations'] ?? ''),
            (string) ($row['activity_categories'] ?? ''),
            (string) ($row['catalog_names'] ?? ''),
            (string) ($row['catalog_addresses'] ?? ''),
        ])), 'UTF-8');
        $tripText = mb_strtolower(implode(' ', [(string) $row['title'], (string) $row['description'], (string) $row['location_name']]), 'UTF-8');
        $row['keyword_location_match'] = $keyword !== '' && mb_strpos($activityText . ' ' . $tripText, mb_strtolower($keyword, 'UTF-8')) !== false;
        $row['location_match'] = $location !== '' && mb_strpos($activityText . ' ' . $tripText, mb_strtolower($location, 'UTF-8')) !== false;
        $row['category_match'] = $category !== '' && trip_recommendation_category_matches($category, (string) ($row['activity_categories'] ?? ''));
        $row['interests_match'] = false;
        foreach ($interests as $interest) {
            if (mb_strpos($activityText . ' ' . $tripText, $interest) !== false) {
                $row['interests_match'] = true;
                break;
            }
        }

        $row['cost_complete'] = (int) $row['activity_count'] > 0 && (int) $row['incomplete_cost_count'] === 0;
        $row['calculated_total'] = $row['cost_complete'] ? (float) $row['calculated_total'] : null;
        $row['calculated_average'] = $row['cost_complete'] && (int) $row['participant_count'] > 0
            ? $row['calculated_total'] / (int) $row['participant_count']
            : null;
        $row['recommendation_score'] = null;
        $row['recommendation_reasons'] = [];
        if ($hasCriteria) {
            $score = 0;
            if ($row['location_match'] || ($keyword !== '' && $row['keyword_location_match'])) {
                $score += 40;
                $row['recommendation_reasons'][] = 'Phù hợp địa điểm hoặc khu vực tìm kiếm';
            } elseif ($keyword !== '' && mb_strpos($tripText, mb_strtolower($keyword, 'UTF-8')) !== false) {
                $score += 20;
                $row['recommendation_reasons'][] = 'Khớp từ khóa lịch trình';
            }
            if ($days !== false && $days !== null && (int) $row['days_count'] === (int) $days) {
                $score += 20;
                $row['recommendation_reasons'][] = 'Đúng số ngày yêu cầu';
            }
            if (($budgetMax !== null || $budgetMin !== null) && $row['cost_complete']) {
                $score += 20;
                $row['recommendation_reasons'][] = $budgetScope === 'personal' ? 'Chi phí trung bình phù hợp ngân sách cá nhân' : 'Tổng chi phí phù hợp ngân sách cả nhóm';
            }
            if ($row['category_match']) {
                $score += 10;
                $row['recommendation_reasons'][] = 'Có loại hoạt động phù hợp';
            }
            if ($row['interests_match']) {
                $score += 5;
                $row['recommendation_reasons'][] = 'Phù hợp sở thích du lịch đã chọn';
            }
            if ($maxViews > 0) {
                $score += (int) round(5 * ((int) $row['view_count'] / $maxViews));
            }
            $row['recommendation_score'] = min(100, $score);
        }
    }
    unset($row);

    if ($sort === 'relevance' && $hasCriteria) {
        usort($rows, static function (array $left, array $right): int {
            return [$right['recommendation_score'], strtotime($right['created_at']), (int) $right['id']]
                <=> [$left['recommendation_score'], strtotime($left['created_at']), (int) $left['id']];
        });
    }

    $total = count($rows);
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = min($page, $pages);
    $rows = array_slice($rows, ($page - 1) * $pageSize, $pageSize);

    $stmtLocations = $conn->query('SELECT name FROM locations ORDER BY name ASC');
    $locations = $stmtLocations->fetchAll(PDO::FETCH_COLUMN);

    return [
        'trips' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'page_size' => $pageSize,
        'locations' => $locations,
        'filters' => compact('keyword', 'location', 'days', 'category', 'budgetScope', 'budgetMax', 'budgetMin', 'sort', 'useInterests', 'interests'),
    ];
}

function trip_recommendation_category_matches(string $category, string $categories): bool
{
    $values = array_filter(array_map('trim', explode(' || ', mb_strtolower($categories, 'UTF-8'))));
    foreach (trip_recommendation_category_aliases()[$category] ?? [] as $alias) {
        if (in_array(mb_strtolower($alias, 'UTF-8'), $values, true)) {
            return true;
        }
    }

    return false;
}
