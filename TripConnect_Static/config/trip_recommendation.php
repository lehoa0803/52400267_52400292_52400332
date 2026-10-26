<?php

function trip_recommendation_text($value): string
{
    return is_string($value) ? mb_substr(trim($value), 0, 150, 'UTF-8') : '';
}

// Only explicit saved interests are preferences; interaction affinity is explained separately.
function trip_recommendation_context(PDO $db, int $user, bool $enabled): array
{
    $context=['interests'=>[], 'history'=>[], 'following'=>[]];
    if (!$enabled || $user < 1) return $context;
    $q=$db->prepare("SELECT travel_interests FROM users WHERE id=? AND status='active'");$q->execute([$user]);
    $saved=$q->fetchColumn();if($saved===false)return $context;
    $context['interests']=trip_recommendation_parse_interests($saved?:null);
    $q=$db->prepare("SELECT t.location_id,t.days_count,t.budget_estimate,t.participant_count,
       (SELECT GROUP_CONCAT(tt.tag_id) FROM trip_tags tt JOIN tags tg ON tg.id=tt.tag_id AND tg.status='active' WHERE tt.trip_id=t.id) AS tag_ids
       FROM trips t JOIN users u ON u.id=t.user_id AND u.status='active'
       WHERE t.status='published' AND t.moderation_status='visible' AND t.user_id<>?
       AND (EXISTS(SELECT 1 FROM likes l WHERE l.trip_id=t.id AND l.user_id=?) OR EXISTS(SELECT 1 FROM bookmarks b WHERE b.trip_id=t.id AND b.user_id=?))
       ORDER BY t.id DESC LIMIT 50");$q->execute([$user,$user,$user]);$context['history']=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$db->prepare("SELECT DISTINCT f.followed_id FROM follows f JOIN users u ON u.id=f.followed_id AND u.status='active' WHERE f.follower_id=?");$q->execute([$user]);$context['following']=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
    return $context;
}

function trip_recommendation_interest_matches(string $interest, string $text): bool
{
    $aliases=[
        'ẩm thực'=>['ẩm thực','food','ăn uống'], 'nghỉ dưỡng'=>['nghỉ dưỡng','resort','resorts'],
        'biển'=>['biển','beach','đảo'], 'núi'=>['núi','mountain','trekking'],
        'văn hóa'=>['văn hóa','culture','bảo tàng','di tích'], 'mạo hiểm'=>['mạo hiểm','adventure','trekking'],
        'check-in'=>['check-in','check in'], 'tiết kiệm'=>['tiết kiệm','budget'],
        'du lịch một mình'=>['du lịch một mình','solo'], 'du lịch cùng gia đình'=>['gia đình','family'],
        'tự túc'=>['tự túc','self-guided'], 'sang trọng'=>['sang trọng','luxury'],
    ];
    foreach($aliases[$interest]??[$interest] as $word) {
        if(preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word,'/').'(?![\p{L}\p{N}])/u',$text))return true;
    }
    return false;
}

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
    $page = max(1, min(10000, (int) (is_scalar($input['page']??null)?$input['page']:1)));
    $pageSize = 9;
    $keyword = trip_recommendation_text($input['q']??'');
    $keyword = mb_substr($keyword, 0, 150, 'UTF-8');
    $location = trip_recommendation_text($input['location']??'');
    $location = mb_substr($location, 0, 150, 'UTF-8');
    $days = filter_var($input['days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
    $category = trip_recommendation_text($input['category']??'');
    $category = array_key_exists($category, trip_recommendation_category_aliases()) ? $category : '';
    $budgetScope = ($input['budget_scope'] ?? 'group') === 'personal' ? 'personal' : 'group';
    $budgetMax = trip_recommendation_get_decimal($input['budget_max'] ?? null);
    $budgetMin = null;

    // Giữ tương thích với form tìm kiếm cũ từ index.php: budget=low|mid|high.
    $legacyBudget = trip_recommendation_text($input['budget']??'');
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

    $sort = trip_recommendation_text($input['sort']??'relevance');
    $sort = in_array($sort, ['relevance', 'latest', 'popular', 'cost'], true) ? $sort : 'relevance';
    $user=filter_var($input['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
    $useInterests=$user>0 && (!array_key_exists('use_interests',$input)||in_array($input['use_interests'],[1,'1',true],true));
    $context=trip_recommendation_context($conn,$user,$useInterests);
    $interests=$context['interests'];
    $tag=filter_var($input['tag']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
    $where = ["t.status = 'published'", "t.moderation_status = 'visible'", "u.status='active'"];
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
        ) OR EXISTS (SELECT 1 FROM trip_tags kt JOIN tags kg ON kg.id=kt.tag_id AND kg.status=\'active\' WHERE kt.trip_id=t.id AND kg.name LIKE ?))';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
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

    if($tag!==null){$where[]="EXISTS(SELECT 1 FROM trip_tags ft JOIN tags tg ON tg.id=ft.tag_id AND tg.status='active' WHERE ft.trip_id=t.id AND ft.tag_id=?)";$params[]=$tag;}

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
                t.id, t.user_id, t.location_id, t.title, t.description, t.departure_at, t.return_at,
                t.days_count, t.participant_count, t.budget_estimate, t.cover_image, t.cover_source,
                t.view_count, t.created_at,
                u.full_name AS author_name, u.avatar AS author_avatar, u.username AS author_username,
                l.name AS location_name,
                (SELECT GROUP_CONCAT(tg.name SEPARATOR ' || ') FROM trip_tags tt JOIN tags tg ON tg.id=tt.tag_id AND tg.status='active' WHERE tt.trip_id=t.id) AS tag_names,
                (SELECT GROUP_CONCAT(tt.tag_id) FROM trip_tags tt JOIN tags tg ON tg.id=tt.tag_id AND tg.status='active' WHERE tt.trip_id=t.id) AS tag_ids,
                (SELECT COUNT(DISTINCT bm.user_id) FROM bookmarks bm WHERE bm.trip_id=t.id) AS bookmark_count,
                (SELECT lc_image.image_url FROM trip_days image_days JOIN activities image_activities ON image_activities.trip_day_id = image_days.id JOIN location_catalog lc_image ON lc_image.id = image_activities.location_catalog_id WHERE image_days.trip_id = t.id AND NULLIF(TRIM(lc_image.image_url), '') IS NOT NULL ORDER BY image_days.day_number, COALESCE(image_activities.sort_order, 65535), image_activities.start_at, image_activities.id LIMIT 1) AS catalog_image,
                (SELECT activity_images.image FROM trip_days activity_days JOIN activities activity_images ON activity_images.trip_day_id = activity_days.id WHERE activity_days.trip_id = t.id AND NULLIF(TRIM(activity_images.image), '') IS NOT NULL ORDER BY activity_days.day_number, COALESCE(activity_images.sort_order, 65535), activity_images.start_at, activity_images.id LIMIT 1) AS activity_image,
                (SELECT COUNT(DISTINCT lk.user_id) FROM likes lk WHERE lk.trip_id = t.id) AS like_count,
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
            GROUP BY t.id, t.user_id, t.location_id, t.title, t.description, t.departure_at, t.return_at,
                     t.days_count, t.participant_count, t.budget_estimate, t.cover_image, t.cover_source,
                     t.view_count, t.created_at, u.full_name, u.avatar, u.username, l.name";
    if ($having) {
        $sql .= ' HAVING ' . implode(' AND ', $having);
    }

    if ($sort === 'latest') {
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC';
    } elseif ($sort === 'popular') {
        $sql .= ' ORDER BY t.view_count DESC, t.created_at DESC, t.id DESC';
    } elseif ($sort === 'cost') {
        $sql='SELECT * FROM ('.$sql.') ranked';
        $unknown=$budgetScope==='personal'?' OR participant_count IS NULL OR participant_count<1':'';
        $costExpression=$budgetScope==='personal'?'calculated_total / NULLIF(participant_count,0)':'calculated_total';
        $sql .= ' ORDER BY (activity_count=0 OR incomplete_cost_count>0'.$unknown.') ASC, '.$costExpression.' ASC, created_at DESC,id DESC';
    } else {
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $activityText = mb_strtolower(implode(' ', array_filter([
            (string) ($row['activity_titles'] ?? ''),
            (string) ($row['activity_locations'] ?? ''),
            (string) ($row['activity_categories'] ?? ''),
            (string) ($row['catalog_names'] ?? ''),
            (string) ($row['catalog_addresses'] ?? ''),
        ])), 'UTF-8');
        $tripText = mb_strtolower(implode(' ', [(string) $row['title'], (string) $row['description'], (string) $row['location_name'], (string) $row['tag_names']]), 'UTF-8');
        $row['keyword_location_match'] = $keyword !== '' && mb_strpos($activityText . ' ' . $tripText, mb_strtolower($keyword, 'UTF-8')) !== false;
        $row['location_match'] = $location !== '' && mb_strpos($activityText . ' ' . $tripText, mb_strtolower($location, 'UTF-8')) !== false;
        $row['category_match'] = $category !== '' && trip_recommendation_category_matches($category, (string) ($row['activity_categories'] ?? ''));
        $matchedInterests=[];
        foreach($interests as $interest)if(trip_recommendation_interest_matches($interest,$tripText.' '.$activityText))$matchedInterests[]=$interest;
        $row['interests_match']=(bool)$matchedInterests;
        $row['cost_complete'] = (int) $row['activity_count'] > 0 && (int) $row['incomplete_cost_count'] === 0;
        $row['calculated_total'] = $row['cost_complete'] ? (float) $row['calculated_total'] : null;
        $row['calculated_average'] = $row['cost_complete'] && (int) $row['participant_count'] > 0
            ? $row['calculated_total'] / (int) $row['participant_count']
            : null;
        $reasons=[];$parts=['preferences'=>0,'criteria'=>0,'history'=>0,'popularity'=>0];
        if($matchedInterests){$parts['preferences']=25+(int)round(10*count($matchedInterests)/count($interests));$reasons[]='Sở thích: '.implode(', ',$matchedInterests);}
        if($row['location_match']){$parts['criteria']+=10;$reasons[]='Khớp khu vực đã chọn';}
        if($row['keyword_location_match']){$parts['criteria']+=5;$reasons[]='Khớp từ khóa tìm kiếm';}
        if($days!==false && $days!==null){$parts['criteria']+=5;$reasons[]='Đúng số ngày yêu cầu';}
        if(($budgetMax!==null||$budgetMin!==null)&&$row['cost_complete']){$parts['criteria']+=5;$reasons[]=$budgetScope==='personal'?'Chi phí dự kiến phù hợp mỗi người':'Chi phí dự kiến phù hợp cả nhóm';}
        if($row['category_match']||$tag!==null){$parts['criteria']+=5;$reasons[]='Có tags hoặc loại hoạt động đã chọn';}
        $affinity=['tags'=>false,'location'=>false,'days'=>false,'budget'=>false];
        $tagIds=array_filter(explode(',',(string)$row['tag_ids']));
        foreach($context['history'] as $previous){
            if(array_intersect($tagIds,explode(',',(string)$previous['tag_ids'])))$affinity['tags']=true;
            if((int)$previous['location_id']===(int)$row['location_id'])$affinity['location']=true;
            if((int)$previous['days_count']===(int)$row['days_count'])$affinity['days']=true;
            if((int)$previous['participant_count']>0 && (int)$row['participant_count']>0){
                $oldBudget=(float)$previous['budget_estimate']/(int)$previous['participant_count'];
                $newBudget=(float)$row['budget_estimate']/(int)$row['participant_count'];
                if($oldBudget>0 && $newBudget>0 && abs($newBudget-$oldBudget)/$oldBudget<=0.25)$affinity['budget']=true;
            }
        }
        if($affinity['tags']){$parts['history']+=8;$reasons[]='Tags tương tự lịch trình bạn đã thích/lưu';}
        if($affinity['location']){$parts['history']+=7;$reasons[]='Cùng điểm đến với lịch trình bạn đã thích/lưu';}
        if($affinity['days']){$parts['history']+=3;$reasons[]='Cùng thời lượng với lịch trình bạn đã thích/lưu';}
        if($affinity['budget']){$parts['history']+=3;$reasons[]='Ngân sách mục tiêu/người gần lịch trình bạn đã thích/lưu';}
        if(isset($context['following'][$row['user_id']])){$parts['history']+=4;$reasons[]='Tác giả bạn đang theo dõi';}
        // Bounded popularity: it cannot outweigh a positive saved-interest match.
        $parts['popularity']=(int)round(min(4,log(1+(int)$row['like_count'],2))+min(4,log(1+(int)$row['bookmark_count'],2))+min(2,log(1+max(0,(int)$row['view_count']),10)));
        $row['personal_match']=$parts['preferences']+$parts['history']>0;
        $row['score_parts']=$parts;
        $row['recommendation_score']=array_sum($parts);
        $row['recommendation_reasons']=$reasons;
    }
    unset($row);
    $hasPersonalMatches=(bool)array_filter($rows,static fn($r)=>$r['personal_match']);
    if($sort==='relevance'){
        usort($rows,static fn($left,$right)=>[$right['recommendation_score'],strtotime($right['created_at']),(int)$right['id']] <=> [$left['recommendation_score'],strtotime($left['created_at']),(int)$left['id']]);
    }

    $total = count($rows);
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = min($page, $pages);
    $rows = array_slice($rows, ($page - 1) * $pageSize, $pageSize);

    $stmtLocations = $conn->query('SELECT name FROM locations ORDER BY name ASC');
    $locations = $stmtLocations->fetchAll(PDO::FETCH_COLUMN);

    $tags=$conn->query("SELECT id,name,type FROM tags WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    return [
        'personalized'=>$hasPersonalMatches, 'tags'=>$tags,
        'trips' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'page_size' => $pageSize,
        'locations' => $locations,
        'filters' => compact('keyword', 'location', 'days', 'category', 'budgetScope', 'budgetMax', 'budgetMin', 'sort', 'useInterests', 'interests', 'tag'),
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
