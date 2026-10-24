<?php
// Only SQLite memory. No application database config or network calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/seed_demo.php';
require_once __DIR__ . '/../config/trip_recommendation.php';
$checks = 0;
function seed_check(bool $value, string $message): void
{
    global $checks; $checks++;
    if (!$value) throw new RuntimeException($message);
}
function seed_fixture(): PDO
{
    $db = new class('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]) extends PDO {
        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            // Dialect adaptation ONLY in isolated tests; no production SQL changes.
            $query = preg_replace("/GROUP_CONCAT\(DISTINCT (NULLIF\([^)]*\)) SEPARATOR ' \|\| '\)/", "REPLACE(GROUP_CONCAT(DISTINCT $1), ',', ' || ')", $query);
            // MySQL coerces numeric comparison parameters; SQLite expression affinity differs.
            $query = str_replace([' <= ?', ' >= ?'], [' <= CAST(? AS REAL)', ' >= CAST(? AS REAL)'], $query);
            return parent::prepare($query, $options);
        }
    };
    $db->exec('PRAGMA foreign_keys=ON');
    // Derive columns, keys and foreign keys from the shipped MySQL schema.
    // SQLite cannot verify MySQL ENUM/DECIMAL semantics; report that limitation.
    preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)` \((.*?)\) ENGINE=/s', file_get_contents(__DIR__ . '/../database/schema.sql'), $tables, PREG_SET_ORDER);
    foreach ($tables as $table) {
        $lines = [];
        foreach (explode("\n", $table[2]) as $line) {
            $line = rtrim(trim($line), ',');
            if (!$line || preg_match('/^(?:KEY |PRIMARY KEY \(`id`\))/', $line)) continue;
            $line = preg_replace('/^UNIQUE KEY `[^`]+` /', 'UNIQUE ', $line);
            $line = preg_replace('/enum\([^)]*\)/i', 'TEXT', $line);
            $line = preg_replace('/\b(?:smallint|bigint|int)\(\d+\)(?: unsigned)?/i', 'INTEGER', $line);
            $line = preg_replace('/\bdecimal\(\d+,\d+\)/i', 'NUMERIC', $line);
            $line = str_ireplace(' ON UPDATE current_timestamp()', '', $line);
            $line = str_ireplace('current_timestamp()', 'CURRENT_TIMESTAMP', $line);
            $line = str_replace('NOT NULL AUTO_INCREMENT', 'PRIMARY KEY AUTOINCREMENT', $line);
            $lines[] = $line;
        }
        $db->exec('CREATE TABLE `' . $table[1] . '` (' . implode(',', $lines) . ')');
    }
    $data = require __DIR__ . '/../database/demo_dataset.php';
    foreach (array_unique(array_column($data['trips'], 'area')) as $area) $db->prepare('INSERT INTO locations (name) VALUES (?)')->execute([$area]);
    foreach (['am-thuc'=>'Ẩm thực','tu-tuc'=>'Tự túc','bien'=>'Biển','check-in'=>'Check-in','nui-rung'=>'Núi rừng','tiet-kiem'=>'Tiết kiệm'] as $slug=>$name) $db->prepare("INSERT INTO tags (name,slug,type,status) VALUES (?,?,'theme','active')")->execute([$name,$slug]);
    $db->exec("INSERT INTO users (username,email,password,full_name,bio,role,status) VALUES ('existing','existing@example.test','unrelated','Existing person','Keep this','member','active')");
    $db->exec("INSERT INTO trips (user_id,location_id,title,description,days_count,status) SELECT u.id,l.id,'Existing trip','Unclassified original',1,'draft' FROM users u CROSS JOIN locations l LIMIT 1");
    return $db;
}
function seed_digest(PDO $db): string
{
    $all = [];
    foreach (['users','trips','trip_days','activities','trip_tags','locations','tags','likes','comments','bookmarks','follows','preferences','user_preferences'] as $table) $all[$table] = $db->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256', serialize($all));
}
$raw = require __DIR__ . '/../database/demo_dataset.php';
$compiled = demo_compile($raw);
seed_check(count($compiled['users']) === 18, '18 profiles.');
seed_check(count($compiled['trips']) === 33 && count($compiled['planned']) === 0, 'All 33 trips compiled, no empty plans.');
$expectedTotals = [3860000,7360000,4130000,1860000,3990000,11160000];
$authors=[]; $timelineSignatures=[];
foreach ($compiled['trips'] as $i=>$trip) {
    $summary = $trip['summary'];
    $independent=0;
    foreach ($raw['trips'][$i]['days'] as $day) {
        array_shift($day);
        foreach($day as $a) $independent += (int)round($a[5]*100)*($a[6]==='per_person'?$trip['people']:1);
    }
    seed_check($summary['cents'] === $independent && $summary['incomplete'] === 0, 'Independent expected group total.');
    if($i<6) seed_check($summary['cents'] === $expectedTotals[$i]*100, 'Original six trips unchanged.');
    seed_check(array_sum(array_column($summary['days'],'cents')) === $summary['cents'], 'Daily sum.');
    seed_check(array_sum(array_column($summary['categories'],'cents')) === $summary['cents'], 'Category sum.');
    seed_check($summary['per_person'] === round($independent/100/$trip['people'],2), 'Per-person average.');
    seed_check($trip['budget']*100 > $summary['cents'], 'Budget has a genuine reserve, not forced equal to total.');
    $authors[$trip['author']]=($authors[$trip['author']]??0)+1;
    $timelineSignatures[]=hash('sha256',json_encode(array_map(static fn($day)=>array_column($day,'location_name'),$trip['timeline'])));
    foreach ($trip['timeline'] as $activities) foreach ($activities as $a) seed_check($a['latitude'] === null && $a['longitude'] === null && $a['location_source'] === 'user', 'No invented coordinate or verification claim.');
}
seed_check(count($authors)===18 && min($authors)===1 && max($authors)<=3, 'Every prepared author has 1 to 3 trips.');
seed_check(count(array_unique($timelineSignatures))===33, 'No duplicated destination sequence.');
seed_check(count($raw['covers'])===25, '25 destination-specific Commons covers.');
$report=demo_report($compiled);
seed_check($report['days_total']===95 && $report['activities_total']===522 && count($report['trips_by_area'])===24 && $report['cover_count']===25, 'Operational report includes exact totals and destination distribution.');
foreach($raw['covers'] as $key=>$cover) {
    seed_check(!empty($cover['attribution']) && !empty($cover['license_url']), 'Photo attribution present.');
    if(isset($cover['http_status'])) seed_check($cover['http_status']==='ok', 'Additional photo passed live HEAD verification.');
}
$db = seed_fixture(); $before = seed_digest($db);
$preview = demo_seed($db,$compiled);
seed_check(seed_digest($db) === $before, 'Default dry-run writes nothing.');
seed_check($preview['users_created'] === 18 && $preview['trips_created'] === 33 && $preview['activities_created'] === 522, 'Dry-run counts.');
$created = demo_seed($db,$compiled,true);
seed_check($created === $preview, 'Actual isolated counts match preview.');
seed_check((int)$db->query('SELECT COUNT(*) FROM trip_days')->fetchColumn() === 95, '95 seeded days.');
seed_check((int)$db->query('SELECT COUNT(*) FROM activities')->fetchColumn() === 522, '522 activities.');
seed_check($db->query('PRAGMA foreign_key_check')->fetchAll() === [], 'All foreign keys valid.');
$hashes = $db->query("SELECT password FROM users WHERE email LIKE '%@tripconnect.test'")->fetchAll(PDO::FETCH_COLUMN);
seed_check(count(array_unique($hashes)) === 18 && !in_array('unknown',array_column(array_map('password_get_info',$hashes),'algoName')), '18 distinct PHP password hashes.');
seed_check((int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() === 0, 'No admin account created.');
seed_check((int)$db->query('SELECT COUNT(*) FROM user_preferences')->fetchColumn() === 0, 'Do not populate unused legacy preferences.');
foreach ($compiled['users'] as $u) seed_check(trip_recommendation_parse_interests($u['travel_interests']) !== [], 'Interests readable by Recommendation.');
foreach (['likes','comments','bookmarks','follows'] as $table) seed_check((int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() === 0, 'No fake interactions.');
seed_check($db->query("SELECT bio FROM users WHERE username='existing'")->fetchColumn() === 'Keep this', 'Existing user unchanged.');
seed_check($db->query("SELECT description FROM trips WHERE title='Existing trip'")->fetchColumn() === 'Unclassified original', 'Existing trip unchanged.');
$after = seed_digest($db); $again = demo_seed($db,$compiled,true);
seed_check(seed_digest($db) === $after && $again['users_created'] === 0 && $again['trips_created'] === 0, 'Idempotent re-run, no overwrites.');
$db->exec("UPDATE trips SET moderation_status='hidden',title='User edited seeded trip' WHERE description LIKE '[TripConnect demo v1 trip:hanoi-phoco] %'");
$edited = seed_digest($db); demo_seed($db,$compiled,true);
seed_check(seed_digest($db) === $edited, 'Preserve edited/hidden seed content on re-run.');
$db->exec("UPDATE trips SET description='User replaced description, keep seeded Timeline marker' WHERE title='User edited seeded trip'");
$edited = seed_digest($db); demo_seed($db,$compiled,true);
seed_check(seed_digest($db) === $edited, 'Day marker preserves idempotence when description replaced.');
// Actual Recommendation/Explore query against independently seeded data.
$db->sqliteCreateFunction('CONCAT', static fn(...$args)=>implode('', $args));
try {
    $found = trip_recommendation_search($db,['q'=>$compiled['trips'][3]['title'],'sort'=>'latest']);
    seed_check($found['total'] === 1, 'Explore returns the published seeded trip.');
    $id = (int)$db->query("SELECT id FROM users WHERE username='tc.thuy.linh'")->fetchColumn();
    $found = trip_recommendation_search($db,['use_interests'=>1,'user_id'=>$id,'sort'=>'relevance']);
    seed_check($found['total'] === 32 && $found['filters']['interests'] !== [], 'Recommendation reads interests and excludes hidden/draft trips.');
    $found = trip_recommendation_search($db,['budget_max'=>2000000,'budget_scope'=>'personal','sort'=>'cost']);
    seed_check($found['total'] === 9, 'Per-person budget filter respects group size, excluding hidden trip.');
    foreach ($found['trips'] as $trip) seed_check($trip['cost_complete'] && $trip['calculated_average'] <= 2000000, 'Budget filter returns complete estimates.');
} catch (PDOException $e) {
    throw new RuntimeException('SQLite compatibility for real search query needs attention: ' . $e->getMessage());
}
// Force a late failure after user inserts: entire transaction must roll back.
$db = seed_fixture(); $db->exec("UPDATE tags SET status='hidden' WHERE slug='check-in'"); $before = seed_digest($db);
try { demo_seed($db,$compiled,true); throw new LogicException('Expected missing tag rejection.'); }
catch (RuntimeException $e) { seed_check(seed_digest($db) === $before && !$db->inTransaction(), 'Rollback users and earlier trips after late catalog error.'); }
$db = seed_fixture();
$db->prepare("INSERT INTO users (username,email,password,full_name,bio,role) VALUES (?,?,?,?,?,'member')")->execute(['tc.thuy.linh','different@example.test','hash','Real person','No marker']);
$before=seed_digest($db);
try { demo_seed($db,$compiled,true); throw new LogicException('Expected account collision.'); }
catch (RuntimeException $e) { seed_check(seed_digest($db) === $before, 'Collision refuses changes.'); }
$bad=$raw; $bad['trips'][0]['days'][0][1][6]=null;
try { demo_compile($bad); throw new LogicException('Expected unknown cost scope error.'); }
catch (RuntimeException $e) { seed_check(true,'No missing scope accepted.'); }
$bad=$raw; $bad['trips'][0]['days'][0][2][0]='08:00';
try { demo_compile($bad); throw new LogicException('Expected overlap error.'); }
catch (RuntimeException $e) { seed_check(true,'No overlapping activities accepted.'); }
// Upgrade the previous six-trip batch without changing its stored data.
$db=seed_fixture(); $firstBatch=$compiled; $firstBatch['trips']=array_slice($compiled['trips'],0,6);
demo_seed($db,$firstBatch,true);
$snapshotSix=static function(PDO $db): string {
    $rows=[];
    $rows['trips']=$db->query("SELECT * FROM trips WHERE description LIKE '[TripConnect demo v1 trip:%' ORDER BY id LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
    $ids=array_column($rows['trips'],'id');
    $list=implode(',',array_map('intval',$ids));
    $rows['days']=$db->query("SELECT * FROM trip_days WHERE trip_id IN ($list) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $rows['activities']=$db->query("SELECT a.* FROM activities a JOIN trip_days d ON d.id=a.trip_day_id WHERE d.trip_id IN ($list) ORDER BY a.id")->fetchAll(PDO::FETCH_ASSOC);
    $rows['tags']=$db->query("SELECT * FROM trip_tags WHERE trip_id IN ($list) ORDER BY trip_id,tag_id")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256',serialize($rows));
};
$sixBefore=$snapshotSix($db); $upgrade=demo_seed($db,$compiled,true);
seed_check($upgrade['users_created']===0 && $upgrade['trips_created']===27 && $upgrade['trips_existing']===6 && $upgrade['activities_created']===440, 'Upgrade adds only remaining 27 trips.');
seed_check($snapshotSix($db)===$sixBefore, 'All stored content, timeline, tags and photo metadata of first six unchanged.');
echo 'PASS ', $checks, " checks; SQLite memory only.\n";
