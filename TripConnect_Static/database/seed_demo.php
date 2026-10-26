<?php
// CLI only. No connection or mutation when this file is included by tests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/trip_timeline.php';
require_once __DIR__ . '/../config/trip_metadata.php';
require_once __DIR__ . '/../config/trip_cover_maintenance.php';

function demo_marker(string $kind, string $key): string
{
    if (!preg_match('/^[a-z0-9.-]+$/D', $key)) throw new InvalidArgumentException('Invalid seed key.');
    return '[TripConnect demo v1 ' . $kind . ':' . $key . ']';
}

function demo_compile(array $data): array
{
    $users = []; $trips = []; $seen = [];
    foreach ($data['profiles'] as [$key, $name, $interests, $bio]) {
        if (isset($users[$key])) throw new RuntimeException('Duplicate profile key.');
        $users[$key] = ['username'=>'tc.' . $key, 'email'=>$key . '@tripconnect.test', 'full_name'=>$name,
            'bio'=>demo_marker('author', $key) . ' Hồ sơ mẫu do nhóm biên soạn; không phải người dùng thật. ' . $bio,
            'travel_interests'=>json_encode($interests, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
    }
    foreach ($data['trips'] as $index => $trip) {
        if (isset($seen[$trip['key']]) || !isset($users[$trip['author']])) throw new RuntimeException('Invalid trip identity.');
        $seen[$trip['key']] = true;
        $daysCount = count($trip['days']);
        if ($daysCount < 2 || $daysCount > 5 || $trip['people'] < 1 || $trip['budget'] <= 0 || !$trip['tags']) throw new RuntimeException('Invalid trip overview.');
        $departure = new DateTimeImmutable('2026-11-03 00:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
        $departure = $departure->modify('+' . ($index * 7) . ' days');
        $return = $departure->modify('+' . ($daysCount - 1) . ' days')->setTime(23, 59);
        $payload = []; $titles = [];
        foreach ($trip['days'] as $dayIndex => $day) {
            $titles[$dayIndex + 1] = array_shift($day);
            if (count($day) < 3 || count($day) > 6) throw new RuntimeException('Incomplete day.');
            foreach ($day as [$start, $end, $title, $place, $category, $cost, $scope, $description]) {
                $payload['day_' . ($dayIndex + 1)][] = ['title'=>$title, 'location'=>$place,
                    'start_time'=>$start, 'end_time'=>str_replace('+1', '', $end), 'overnight'=>str_ends_with($end, '+1'),
                    'cost'=>$cost, 'cost_scope'=>$scope, 'category'=>$category, 'description'=>$description,
                    'notes'=>'Dữ liệu demo. Chi phí ước tính, không phải giá niêm yết. Tọa độ chưa xác minh; để trống.',
                    'latitude'=>null, 'longitude'=>null];
            }
        }
        $normalized = trip_timeline_normalize_payload($payload, $daysCount, $departure, $return, 'published');
        if ($normalized['errors'] || $normalized['warnings']) throw new RuntimeException($trip['key'] . ': ' . implode(' ', [...$normalized['errors'], ...$normalized['warnings']]));
        $summary = trip_timeline_budget_summary($normalized['data'], $trip['people']);
        if ($summary['incomplete']) throw new RuntimeException('Incomplete budget.');
        $cover = $data['covers'][$trip['cover']] ?? [];
        foreach (['image_url'=>1000, 'source_url'=>500, 'attribution'=>500, 'license'=>100, 'license_url'=>500] as $field=>$limit) {
            if (!is_string($cover[$field] ?? null) || !$cover[$field] || strlen($cover[$field]) > $limit) throw new RuntimeException('Invalid cover metadata.');
        }
        if (parse_url($cover['image_url'], PHP_URL_SCHEME) !== 'https'
            || !in_array(parse_url($cover['image_url'], PHP_URL_HOST), ['thumb.wikimedia.org','upload.wikimedia.org'], true)
            || parse_url($cover['source_url'], PHP_URL_HOST) !== 'commons.wikimedia.org'
            || parse_url($cover['license_url'], PHP_URL_HOST) !== 'creativecommons.org') throw new RuntimeException('Invalid Wikimedia source.');
        $trip['marker'] = demo_marker('trip', $trip['key']);
        $trip['description'] = $trip['marker'] . ' LỊCH TRÌNH MẪU do nhóm biên soạn, không phải trải nghiệm người dùng thật. '
            . $trip['description'] . "\nChi phí là ước tính cho cả chuyến tại điểm đến; không gồm vé liên tỉnh, mua sắm tùy ý. "
            . 'Tọa độ chưa xác minh, không gán tọa độ trung tâm thay thế. Giờ/giá dịch vụ cần kiểm tra trước khi đi. Nguồn tham khảo điểm đến: ' . $data['sources'][$trip['cover']];
        $trip['timeline'] = $normalized['data']; $trip['day_titles'] = $titles; $trip['summary'] = $summary;
        $trip['departure_at'] = $departure->format('Y-m-d H:i:s'); $trip['return_at'] = $return->format('Y-m-d H:i:s');
        $trip['cover_metadata'] = $cover;
        $trips[] = $trip;
    }
    return ['users'=>$users, 'trips'=>$trips, 'planned'=>$data['planned']];
}

function demo_one(PDO $db, string $sql, array $params): ?array
{
    $stmt = $db->prepare($sql); $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Default read-only. No DDL, updates, deletes, new catalog entries, or community interactions.
function demo_seed(PDO $db, array $compiled, bool $apply = false): array
{
    if ($db->inTransaction()) throw new LogicException('Seeder must own its transaction.');
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql','sqlite'], true)) throw new RuntimeException('Unsupported database.');
    $result = ['users_created'=>0,'trips_created'=>0,'users_existing'=>0,'trips_existing'=>0,'activities_created'=>0];
    if ($apply) $db->beginTransaction();
    try {
        $userIds = [];
        foreach ($compiled['users'] as $key=>$user) {
            // A locked author row serializes concurrent re-runs, including already-seeded users.
            $rows = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?' . ($apply && $driver === 'mysql' ? ' FOR UPDATE' : ''));
            $rows->execute([$user['username'], $user['email']]); $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                if (count($rows) !== 1 || $rows[0]['username'] !== $user['username'] || $rows[0]['email'] !== $user['email']
                    || !str_starts_with((string) $rows[0]['bio'], demo_marker('author', $key) . ' ')
                    || $rows[0]['role'] !== 'member') throw new RuntimeException('Existing account collision: ' . $key);
                $userIds[$key] = (int) $rows[0]['id']; $result['users_existing']++;
            } else {
                if ($apply) {
                    // Unique random secret is immediately discarded. Authors have no distributed login credential.
                    $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username,email,password,full_name,bio,travel_interests,role,status) VALUES (?,?,?,?,?,?,'member','active')");
                    $stmt->execute([$user['username'],$user['email'],$hash,$user['full_name'],$user['bio'],$user['travel_interests']]);
                    $userIds[$key] = (int) $db->lastInsertId();
                }
                $result['users_created']++;
            }
        }
        foreach ($compiled['trips'] as $trip) {
            $location = demo_one($db, 'SELECT id FROM locations WHERE name = ?', [$trip['area']]);
            if (!$location) throw new RuntimeException('Missing destination catalog: ' . $trip['area']);
            $tagIds = [];
            foreach ($trip['tags'] as $slug) {
                $tag = demo_one($db, "SELECT id FROM tags WHERE slug = ? AND status = 'active'", [$slug]);
                if (!$tag) throw new RuntimeException('Missing active tag: ' . $slug);
                $tagIds[] = (int) $tag['id'];
            }
            $query = $db->prepare('SELECT id,user_id,description FROM trips WHERE description LIKE ? OR EXISTS (SELECT 1 FROM trip_days d WHERE d.trip_id=trips.id AND d.note LIKE ?)');
            $query->execute([$trip['marker'] . ' %', $trip['marker'] . ' %']); $existing = $query->fetchAll(PDO::FETCH_ASSOC);
            if ($existing) {
                if (count($existing) !== 1 || (int) $existing[0]['user_id'] !== ($userIds[$trip['author']] ?? -1)) throw new RuntimeException('Trip marker collision.');
                $result['trips_existing']++;
                continue; // Never overwrite user edits, hidden/deleted status, or old seed data.
            }
            $result['trips_created']++;
            $result['activities_created'] += $trip['summary']['count'];
            if (!$apply) continue;
            $cover = $trip['cover_metadata'];
            $stmt = $db->prepare("INSERT INTO trips (user_id,location_id,title,description,departure_at,return_at,days_count,participant_count,budget_estimate,cover_image,cover_source,cover_source_url,cover_attribution,cover_license,cover_license_url,cover_selected_at,status,moderation_status,view_count) VALUES (?,?,?,?,?,?,?,?,?,?,'wikimedia',?,?,?,?,?,'published','visible',0)");
            $stmt->execute([$userIds[$trip['author']],$location['id'],$trip['title'],$trip['description'],$trip['departure_at'],$trip['return_at'],count($trip['timeline']),$trip['people'],$trip['budget'],$cover['image_url'],$cover['source_url'],$cover['attribution'],$cover['license'],$cover['license_url'],date('Y-m-d H:i:s')]);
            $tripId = (int) $db->lastInsertId();
            trip_metadata_save_tags($db, $tripId, $tagIds);
            foreach ($trip['timeline'] as $key=>$activities) {
                $number = (int) substr($key, 4);
                $date = trip_timeline_day_date(trip_timeline_parse_datetime($trip['departure_at']), $number);
                $stmt = $db->prepare('INSERT INTO trip_days (trip_id,day_number,day_date,title,note) VALUES (?,?,?,?,?)');
                $stmt->execute([$tripId,$number,$date,$trip['day_titles'][$number],$trip['marker'] . ' Dữ liệu demo; giờ và chi phí dự kiến, chưa đặt dịch vụ.']);
                $dayId = (int) $db->lastInsertId();
                foreach ($activities as $activity) {
                    $fields = ['sort_order','start_at','end_at','title','location_name','description','notes','cost','cost_scope','category','latitude','longitude','location_source'];
                    $stmt = $db->prepare('INSERT INTO activities (trip_day_id,' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields)+1, '?')) . ')');
                    $stmt->execute([$dayId, ...array_map(static fn($field)=>$activity[$field], $fields)]);
                }
            }
        }
        if ($apply) $db->commit();
    } catch (Throwable $e) {
        if ($apply && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return $result;
}

function demo_report(array $compiled): array
{
    return ['demo'=>true,'target_trips'=>33,'ready_trips'=>count($compiled['trips']),'prepared_profiles'=>count($compiled['users']),
        'days_total'=>array_sum(array_map(static fn($t)=>count($t['timeline']),$compiled['trips'])),
        'activities_total'=>array_sum(array_map(static fn($t)=>$t['summary']['count'],$compiled['trips'])),
        'trips_by_area'=>array_count_values(array_column($compiled['trips'],'area')),
        'trips_by_author'=>array_count_values(array_column($compiled['trips'],'author')),
        'cover_count'=>count(array_unique(array_column(array_column($compiled['trips'],'cover_metadata'),'source_url'))),
        'trips_with_cover'=>count($compiled['trips']),
        'shared_cover_uses'=>count($compiled['trips'])-count(array_unique(array_column(array_column($compiled['trips'],'cover_metadata'),'source_url'))),
        'users'=>array_column(array_values($compiled['users']), 'full_name'),
        'trips'=>array_map(static fn($t)=>['key'=>$t['key'],'title'=>$t['title'],'area'=>$t['area'],'author'=>$t['author'],
            'days'=>count($t['timeline']),'activities'=>$t['summary']['count'],'people'=>$t['people'],
            'budget_vnd'=>$t['budget'],'total_vnd'=>$t['summary']['cents']/100,'per_person_vnd'=>$t['summary']['per_person'],
            'remaining_vnd'=>$t['budget']-$t['summary']['cents']/100,'missing_costs'=>$t['summary']['incomplete'],
            'categories'=>$t['summary']['categories'],'by_day'=>$t['summary']['days'],
            'coordinates'=>'Chưa xác minh, NULL; không có tọa độ thay thế.','cover'=>$t['cover_metadata']], $compiled['trips']),
        'planned_not_imported'=>$compiled['planned']];
}

function demo_cli(array $args): int
{
    try {
        $options = [];
        foreach ($args as $arg) {
            if (!preg_match('/^--(report|verify-images|apply|database|confirm-database)(?:=(.*))?$/D', $arg, $match)) throw new RuntimeException('Unknown CLI argument.');
            if (isset($options[$match[1]])) throw new RuntimeException('Duplicate CLI argument.');
            $options[$match[1]] = $match[2] ?? true;
        }
        foreach (['report','verify-images','apply'] as $flag) {
            if (isset($options[$flag]) && $options[$flag] !== true) throw new RuntimeException('Flag --' . $flag . ' takes no value.');
        }
        $compiled = demo_compile(require __DIR__ . '/demo_dataset.php');
        if (isset($options['report']) || !$options) {
            if (count($options) > 1) throw new RuntimeException('--report cannot be combined with database actions.');
            echo json_encode(demo_report($compiled), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
            return 0;
        }
        if (isset($options['verify-images'])) {
            if (count($options) !== 1) throw new RuntimeException('--verify-images is a separate read-only operation.');
            $failed = false;
            foreach ((require __DIR__ . '/demo_dataset.php')['covers'] as $key=>$cover) {
                $status = trip_cover_probe($cover['image_url']);
                echo $key, ': ', $status, ' ', $cover['source_url'], PHP_EOL;
                $failed = $failed || $status !== 'ok';
            }
            return $failed ? 1 : 0;
        }
        $name = $options['database'] ?? '';
        if (!is_string($name) || !preg_match('/^[a-zA-Z0-9_]+$/D', $name)) throw new RuntimeException('Explicit --database=NAME required.');
        $apply = isset($options['apply']);
        if ($apply && ($options['confirm-database'] ?? null) !== $name) throw new RuntimeException('Writing requires --apply --confirm-database=NAME after approval.');
        if ($apply) {
            foreach ((require __DIR__ . '/demo_dataset.php')['covers'] as $key=>$cover) {
                if (trip_cover_probe($cover['image_url']) !== 'ok') throw new RuntimeException('Cover not verified: ' . $key . '. Retry later; database untouched.');
            }
        }
        $db = new PDO('mysql:host=' . (getenv('TRIPCONNECT_DB_HOST') ?: 'localhost') . ';dbname=' . $name . ';charset=utf8mb4',
            getenv('TRIPCONNECT_DB_USER') ?: 'root', getenv('TRIPCONNECT_DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
        // Do not create schema. Require transactional storage before any mutation.
        foreach ($db->query('SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN) as $engine) {
            if (strtolower((string) $engine) !== 'innodb') throw new RuntimeException('All target tables must use InnoDB.');
        }
        echo json_encode(['mode'=>$apply?'APPLY':'READ_ONLY','result'=>demo_seed($db,$compiled,$apply)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
        return 0;
    } catch (Throwable $e) {
        // PDO errors can contain credentials or server details. Keep those off CLI output.
        fwrite(STDERR, $e instanceof PDOException ? "Database operation failed; transaction rolled back if active.\n" : $e->getMessage() . "\n");
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(demo_cli(array_slice($argv, 1)));
}
