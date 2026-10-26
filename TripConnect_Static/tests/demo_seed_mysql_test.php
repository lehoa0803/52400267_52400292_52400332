<?php
// Creates ONLY a new, uniquely named localhost test database. Never imports schema into tripconnect.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/seed_demo.php';
require_once __DIR__ . '/../config/trip_recommendation.php';

function mysql_demo_check(bool $ok, string $message): void
{
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function mysql_demo_digest(PDO $db, array $tables): string
{
    $hash=hash_init('sha256');
    foreach($tables as $table) {
        if(!preg_match('/^[a-z_]+$/D',$table)) throw new RuntimeException('Invalid table.');
        hash_update($hash,$table);
        foreach($db->query('SELECT * FROM `' . $table . '` ORDER BY 1,2')->fetchAll(PDO::FETCH_ASSOC) as $row) hash_update($hash,serialize($row));
    }
    return hash_final($hash);
}
function mysql_demo_schema(PDO $db): string
{
    $rows=[];
    foreach(['COLUMNS'=>'TABLE_NAME,ORDINAL_POSITION','KEY_COLUMN_USAGE'=>'TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION'] as $table=>$order) {
        $rows[$table]=$db->query('SELECT * FROM INFORMATION_SCHEMA.' . $table . ' WHERE TABLE_SCHEMA=DATABASE() ORDER BY ' . $order)->fetchAll(PDO::FETCH_ASSOC);
    }
    return hash('sha256',serialize($rows));
}

if (($argv[1] ?? '') !== '--run') { fwrite(STDERR,"Use --run to create an isolated localhost test database. No DROP is performed.\n"); exit(1); }
$checks=0;
try {
    $host=getenv('TRIPCONNECT_DB_HOST')?:'localhost';
    if(!in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Refuse remote host for automatic database creation.');
    $mainName=getenv('TRIPCONNECT_DB_NAME')?:'tripconnect';
    if(!preg_match('/^[a-zA-Z0-9_]+$/D',$mainName)) throw new RuntimeException('Invalid source database name.');
    $user=getenv('TRIPCONNECT_DB_USER')?:'root'; $pass=getenv('TRIPCONNECT_DB_PASSWORD')?:'';
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
    // The source handle is used exclusively for SELECT and INFORMATION_SCHEMA reads.
    $main=new PDO('mysql:host='.$host.';dbname='.$mainName.';charset=utf8mb4',$user,$pass,$options);
    $tables=$main->query('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\' ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
    $mainBefore=mysql_demo_digest($main,$tables); $mainSchema=mysql_demo_schema($main);
    $name='tripconnect_demo_test_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    if($name===$mainName || !preg_match('/^tripconnect_demo_test_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D',$name)) throw new RuntimeException('Unsafe test name.');
    $server=new PDO('mysql:host='.$host.';charset=utf8mb4',$user,$pass,$options);
    $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $db=new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',$user,$pass,$options);
    $db->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    // Only extract CREATE TABLE statements. Never execute CREATE DATABASE/USE from schema.sql.
    preg_match_all('/CREATE TABLE IF NOT EXISTS `[^`]+` \(.*?\) ENGINE=InnoDB[^;]*;/s',file_get_contents(__DIR__.'/../database/schema.sql'),$ddl);
    mysql_demo_check(count($ddl[0])===17,'Expected 17 fixture tables.');
    foreach($ddl[0] as $sql) $db->exec($sql);
    foreach($main->query('SELECT name,region FROM locations')->fetchAll(PDO::FETCH_ASSOC) as $row) $db->prepare('INSERT INTO locations(name,region) VALUES (?,?)')->execute(array_values($row));
    foreach($main->query('SELECT name,slug,type,status FROM tags')->fetchAll(PDO::FETCH_ASSOC) as $row) $db->prepare('INSERT INTO tags(name,slug,type,status) VALUES (?,?,?,?)')->execute(array_values($row));
    $compiled=demo_compile(require __DIR__.'/../database/demo_dataset.php');
    $schemaBefore=mysql_demo_schema($db); $fixtureBefore=mysql_demo_digest($db,$tables);
    $preview=demo_seed($db,$compiled);
    mysql_demo_check(mysql_demo_digest($db,$tables)===$fixtureBefore,'Dry run must be read-only.');
    // Exercise the actual CLI entry with its database-name confirmation and live image checks.
    ob_start(); $code=demo_cli(['--database='.$name,'--apply','--confirm-database='.$name]); $cliOutput=ob_get_clean();
    mysql_demo_check($code===0,'Seeder CLI apply failed.');
    $created=json_decode($cliOutput,true,512,JSON_THROW_ON_ERROR)['result'];
    mysql_demo_check($created===$preview && $created['users_created']===18 && $created['trips_created']===33 && $created['activities_created']===522,'Exact CLI creation counts.');
    foreach(['users'=>18,'trips'=>33,'trip_days'=>95,'activities'=>522,'trip_tags'=>69] as $table=>$expected) mysql_demo_check((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$expected,'Count '.$table);
    mysql_demo_check(mysql_demo_schema($db)===$schemaBefore,'Seeder must not alter/reimport existing table schema.');
    foreach(['likes','comments','bookmarks','follows','user_preferences'] as $table) mysql_demo_check((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===0,'No fake interactions or legacy preferences.');
    // All foreign key relations, including optional columns, checked against actual MariaDB constraints.
    foreach($db->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $fk) {
        $sql='SELECT COUNT(*) FROM `'.$fk['TABLE_NAME'].'` c LEFT JOIN `'.$fk['REFERENCED_TABLE_NAME'].'` p ON c.`'.$fk['COLUMN_NAME'].'`=p.`'.$fk['REFERENCED_COLUMN_NAME'].'` WHERE c.`'.$fk['COLUMN_NAME'].'` IS NOT NULL AND p.`'.$fk['REFERENCED_COLUMN_NAME'].'` IS NULL';
        mysql_demo_check((int)$db->query($sql)->fetchColumn()===0,'Foreign key integrity.');
    }
    foreach($compiled['trips'] as $trip) {
        $stored=demo_one($db,'SELECT * FROM trips WHERE description LIKE ?',[$trip['marker'].' %']);
        mysql_demo_check($stored!==null && $stored['cover_image']===$trip['cover_metadata']['image_url'] && $stored['cover_license']===$trip['cover_metadata']['license'],'Cover persisted with metadata.');
        $stmt=$db->prepare('SELECT a.* FROM activities a JOIN trip_days d ON d.id=a.trip_day_id WHERE d.trip_id=? ORDER BY d.day_number,a.sort_order');$stmt->execute([$stored['id']]);$activities=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $groups=[];foreach($activities as $a) $groups[$a['trip_day_id']][]=$a;
        $summary=trip_timeline_budget_summary($groups,(int)$stored['participant_count']);
        mysql_demo_check($summary['incomplete']===0 && $summary['cents']===$trip['summary']['cents'] && $summary['per_person']===$trip['summary']['per_person'],'Budget round-trip exact.');
        mysql_demo_check(array_sum(array_column($summary['categories'],'cents'))===$summary['cents'] && array_sum(array_column($summary['days'],'cents'))===$summary['cents'],'Day/category totals.');
    }
    $before=mysql_demo_digest($db,$tables);$again=demo_seed($db,$compiled,true);
    mysql_demo_check($again['users_created']===0 && $again['trips_created']===0 && $again['trips_existing']===33 && mysql_demo_digest($db,$tables)===$before,'Re-run adds nothing and changes nothing.');
    // New synthetic trip succeeds, then a later missing tag fails: inserted days/activities must roll back.
    $bad=$compiled;$a=$compiled['trips'][0];$a['key']='rollback-a';$a['marker']=demo_marker('trip','rollback-a');$a['description']=$a['marker'].' isolated rollback test';
    $b=$a;$b['key']='rollback-b';$b['marker']=demo_marker('trip','rollback-b');$b['description']=$b['marker'].' isolated rollback test';$b['tags']=['nonexistent-demo-rollback-tag'];$bad['trips']=[$a,$b];
    try { demo_seed($db,$bad,true); throw new LogicException('Expected rollback error.'); }
    catch(RuntimeException $e) { mysql_demo_check(!$db->inTransaction() && mysql_demo_digest($db,$tables)===$before,'Late error rolls back inserted trips/days/activities/tags.'); }
    // Real FK rejection, not a mocked SQLite constraint.
    try { $db->exec('INSERT INTO trip_tags(trip_id,tag_id) VALUES (2147483647,2147483647)'); throw new LogicException('Expected FK rejection.'); }
    catch(PDOException $e) { mysql_demo_check($e->getCode()==='23000','MariaDB enforces foreign keys.'); }
    $found=trip_recommendation_search($db,['q'=>$compiled['trips'][3]['title']]);
    mysql_demo_check($found['total']===1,'Real MySQL Explore query.');
    $author=demo_one($db,'SELECT * FROM users WHERE username=?',['tc.thuy.linh']);
    $found=trip_recommendation_search($db,['use_interests'=>1,'user_id'=>$author['id']]);
    mysql_demo_check($found['total']===33 && $found['filters']['interests']!==[],'Real MySQL recommendation.');
    foreach($db->query('SELECT password,email,bio FROM users')->fetchAll(PDO::FETCH_ASSOC) as $u) mysql_demo_check(password_get_info($u['password'])['algoName']!=='unknown' && str_ends_with($u['email'],'@tripconnect.test') && str_starts_with($u['bio'],'[TripConnect demo v1 author:'),'Demo password and provenance.');
    mysql_demo_check(mysql_demo_digest($main,$tables)===$mainBefore && mysql_demo_schema($main)===$mainSchema,'Main database data and schema unchanged.');
    echo json_encode(['status'=>'PASS','checks'=>$checks,'database'=>$name,'engine'=>$db->query('SELECT VERSION()')->fetchColumn(),'created'=>$created,'days'=>95,'trip_tags'=>69,'source_unchanged'=>true,'schema_unchanged'=>true,'retained_for_review'=>true],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
} catch(Throwable $e) {
    // Never dump PDO connection parameters or credentials.
    fwrite(STDERR,$e instanceof PDOException?"MySQL test failed (PDO); isolated database retained if created.\n":$e->getMessage()."\n");
    if(isset($name))fwrite(STDERR,'Test database: '.$name."\n");
    exit(1);
}
