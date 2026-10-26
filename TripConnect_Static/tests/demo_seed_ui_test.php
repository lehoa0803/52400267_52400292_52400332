<?php
// HTTP integration test; only a localhost development server bound to a demo TEST database.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../database/seed_demo.php';
$name=$argv[1]??''; $base=$argv[2]??'';
if(!preg_match('/^tripconnect_demo_test_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}$/D',$name)
    || !preg_match('#^http://127\.0\.0\.1:([0-9]{4,5})/$#D',$base)) {fwrite(STDERR,"Explicit test database and http://127.0.0.1:PORT/ required.\n");exit(1);}
$checks=0; $probe=null; $db=null; $author=null;
function ui_check(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function ui_request($ch,string $url,?array $post=null):array{
    if(str_ends_with($url,'login.php')&&$post!==null&&!array_key_exists('csrf_token',$post)){$form=ui_request($ch,$url);if(!preg_match('/name="csrf_token" value="([^"]+)"/',$form['body'],$token))throw new RuntimeException('Auth CSRF missing.');$post['csrf_token']=$token[1];}
    curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIEFILE=>'']);
    if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);else curl_setopt($ch,CURLOPT_HTTPGET,true);
    $response=curl_exec($ch);if($response===false)throw new RuntimeException('Local HTTP request failed.');
    return ['status'=>(int)curl_getinfo($ch,CURLINFO_HTTP_CODE),'body'=>substr($response,(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE))];
}
try{
    $ch=curl_init();
    $probe=__DIR__.'/.demo_ui_probe_'.bin2hex(random_bytes(6)).'.php';
    file_put_contents($probe,'<?php require dirname(__DIR__)."/config/database.php"; header("Content-Type: application/json"); echo json_encode(["db"=>$conn->query("SELECT DATABASE()")->fetchColumn()]);');
    $response=ui_request($ch,$base.'tests/'.basename($probe));
    ui_check($response['status']===200 && (json_decode($response['body'],true)['db']??'')===$name,'Refuse HTTP server connected to another database.');
    unlink($probe);$probe=null;
    $host=getenv('TRIPCONNECT_DB_HOST')?:'localhost';if(!in_array($host,['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Local test database only.');
    $db=new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',getenv('TRIPCONNECT_DB_USER')?:'root',getenv('TRIPCONNECT_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $compiled=demo_compile(require __DIR__.'/../database/demo_dataset.php');
    foreach(['index.php','explore.php'] as $page){$r=ui_request($ch,$base.$page);ui_check($r['status']===200 && !str_contains($r['body'],'Fatal error'),'Public page renders.');}
    foreach($compiled['trips'] as $trip){
        $row=demo_one($db,'SELECT id FROM trips WHERE description LIKE ?',[$trip['marker'].' %']);
        $r=ui_request($ch,$base.'trip-detail.php?id='.$row['id']);$html=$r['body'];
        ui_check($r['status']===200 && str_contains($html,htmlspecialchars($trip['title'])),'Trip detail title renders.');
        ui_check(str_contains($html,htmlspecialchars($trip['cover_metadata']['image_url'])),'Correct cover URL in rendered detail.');
        ui_check(str_contains($html,number_format($trip['summary']['cents']/100,0,',','.')),'Correct group Budget in rendered HTML.');
        foreach($trip['timeline'] as $activities) foreach($activities as $a)ui_check(str_contains($html,htmlspecialchars($a['title'])),'Every Timeline activity rendered.');
    }
    $author=demo_one($db,'SELECT id,password FROM users WHERE username=?',['tc.thuy.linh']);
    // Use a temporary password ONLY in the isolated test DB, restore its original hash in finally.
    $secret=bin2hex(random_bytes(32));$hash=password_hash($secret,PASSWORD_DEFAULT);
    $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash,$author['id']]);
    $r=ui_request($ch,$base.'login.php',['action'=>'login','loginUsername'=>'tc.thuy.linh','loginPassword'=>$secret]);
    ui_check($r['status']===302,'Seeded author can log in when intentionally given a test-only password.');
    $r=ui_request($ch,$base.'profile.php');ui_check($r['status']===200 && str_contains($r['body'],$compiled['users']['thuy.linh']['full_name']),'Authenticated profile renders.');
    ui_check(str_contains($r['body'],htmlspecialchars($compiled['trips'][0]['cover_metadata']['image_url'])) && str_contains($r['body'],htmlspecialchars($compiled['trips'][0]['title'])),'Profile shows correct seeded trip and photo.');
    $bad=curl_init();$r=ui_request($bad,$base.'login.php',['action'=>'login','loginUsername'=>'tc.thuy.linh','loginPassword'=>$hash]);
    ui_check($r['status']===200,'Stored hash itself must not authenticate a demo author.');
    $r=ui_request($bad,$base.'profile.php');ui_check($r['status']===302,'Rejected login cannot open profile.');curl_close($bad);
    echo json_encode(['status'=>'PASS','checks'=>$checks,'database'=>$name,'detail_pages'=>33,'login_test'=>'temporary secret, original hash restored','visual_browser_check'=>'not included']),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e instanceof PDOException?"Test database operation failed.\n":$e->getMessage()."\n");$failed=true;}
finally{
    if($db && $author)$db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$author['password'],$author['id']]);
    if($probe && file_exists($probe))unlink($probe);
    if(isset($ch))curl_close($ch);
}
exit(!empty($failed)?1:0);
