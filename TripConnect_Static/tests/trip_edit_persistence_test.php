<?php
// Real edit controller, isolated SQLite fixture only. Never loads a live DB connection.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$case=$argv[1]??'';
if($case===''){
    foreach(['valid','blank','csrf','catalog','manual','missing','rollback'] as $name){
        $p=proc_open([PHP_BINARY,__FILE__,$name],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($p)!==0 || !str_contains($out,'PASS ')){fwrite(STDERR,$out.$err);exit(1);}echo $out;
    }
    exit;
}
if(!in_array($case,['valid','blank','csrf','catalog','manual','missing','rollback'],true))exit(1);
$app=dirname(__DIR__);$source=file_get_contents($app.'/config/database.php');$boundary=strpos($source,"\n\$host = getenv(");
if($boundary===false)throw new RuntimeException('Cannot safely isolate helper definitions.');
eval('?>'.substr($source,0,$boundary));
$conn=new class('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]) extends PDO{
    public function prepare(string $query,array $options=[]):PDOStatement|false{return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);}
};
$conn->exec('PRAGMA foreign_keys=ON');
preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)` \((.*?)\) ENGINE=/s',file_get_contents($app.'/database/schema.sql'),$tables,PREG_SET_ORDER);
foreach($tables as $table){$lines=[];foreach(explode("\n",$table[2]) as $line){$line=rtrim(trim($line),',');if(!$line||preg_match('/^(?:KEY |PRIMARY KEY \(`id`\))/', $line))continue;$line=preg_replace('/^UNIQUE KEY `[^`]+` /','UNIQUE ',$line);$line=preg_replace('/enum\([^)]*\)/i','TEXT',$line);$line=preg_replace('/\b(?:smallint|bigint|int)\(\d+\)(?: unsigned)?/i','INTEGER',$line);$line=preg_replace('/\bdecimal\(\d+,\d+\)/i','NUMERIC',$line);$line=str_ireplace(' ON UPDATE current_timestamp()','',$line);$line=str_ireplace('current_timestamp()','CURRENT_TIMESTAMP',$line);$line=str_replace('NOT NULL AUTO_INCREMENT','PRIMARY KEY AUTOINCREMENT',$line);$lines[]=$line;}$conn->exec('CREATE TABLE `'.$table[1].'` ('.implode(',',$lines).')');}
$conn->exec("INSERT INTO users(id,username,email,password,full_name) VALUES(1,'fixture','fixture@example.test','discarded','Fixture')");
$conn->exec("INSERT INTO locations(id,name) VALUES(1,'Fixture area')");
$conn->exec("INSERT INTO trips(id,user_id,location_id,title,description,departure_at,return_at,days_count,participant_count,budget_estimate,cover_image,status) VALUES(1,1,1,'Original','Keep description','2026-10-24 00:00:00','2026-10-24 23:59:00',1,3,100,'assets/images/hero1.jpg','published')");
$conn->exec("INSERT INTO trip_days(id,trip_id,day_number,day_date,title,note) VALUES(1,1,1,'2026-10-24','Keep day','Keep note')");
$conn->exec("INSERT INTO location_catalog(id,name,latitude,longitude,location_source,location_source_ref,status) VALUES(9,'Fixture catalog',10.5,106.5,'osm','node:9','active')");
$conn->exec("INSERT INTO activities(id,trip_day_id,location_catalog_id,sort_order,start_at,end_at,title,location_name,cost,cost_scope,category,latitude,longitude,location_source,location_source_ref) VALUES(1,1,9,1,'2026-10-24 08:00:00','2026-10-24 09:00:00','Original activity','Fixture catalog',5,'fixed','sightseeing',10.5,106.5,'osm','node:9'),(2,1,NULL,2,'2026-10-24 10:00:00','2026-10-24 11:00:00','Unknown amount','Manual',NULL,'fixed','food',NULL,NULL,'user',NULL)");
$digest=static function()use($conn){$data=[];foreach(['trips','trip_days','activities','trip_tags'] as $t)$data[$t]=$conn->query('SELECT * FROM '.$t.' ORDER BY 1,2')->fetchAll(PDO::FETCH_ASSOC);return hash('sha256',serialize($data));};$before=$digest();
if($case==='rollback')$conn->exec("CREATE TRIGGER reject_second_update BEFORE UPDATE ON activities WHEN OLD.id=2 BEGIN SELECT RAISE(ABORT,'isolated injected failure'); END");
$payload=['day_1'=>[
 ['id'=>1,'location_catalog_id'=>9,'title'=>'Edited activity','location'=>'Fixture catalog','cost'=>'2.35','cost_scope'=>'per_person','category'=>'food','start_time'=>'08:00','end_time'=>'09:00','latitude'=>80,'longitude'=>5],
 ['id'=>2,'title'=>'Unknown amount','location'=>'Manual','cost'=>null,'cost_scope'=>'fixed','category'=>'food','start_time'=>'10:00','end_time'=>'11:00'],
]];
if($case==='blank')$payload['day_1'][0]['cost']='';
if($case==='manual')$payload['day_1'][0]=array_replace($payload['day_1'][0],['location_catalog_id'=>null,'location'=>'Changed manual place','latitude'=>10.5,'longitude'=>106.5]);
if($case==='missing')array_pop($payload['day_1']);
$_SESSION=['user_id'=>1,'trip_csrf_token'=>'fixture-token'];$_GET=['id'=>1];$_SERVER['REQUEST_METHOD']='POST';$_FILES=[];
$_POST=['csrf_token'=>$case==='csrf'?'wrong':'fixture-token','tripName'=>'Edited trip','tripLocation'=>'1','startLocation'=>'','departureAt'=>'2026-10-24T00:00','returnAt'=>'2026-10-24T23:59','tripDays'=>'1','participantCount'=>'3','tripBudget'=>'100.50','save_action'=>'published','activities_json'=>json_encode($payload),'deleted_activity_ids'=>'[]'];
ob_start();register_shutdown_function(static function()use($conn,$case,$digest,$before){
    while(ob_get_level())ob_end_clean();
    try{
        if(in_array($case,['blank','csrf','missing','rollback'],true)){
            if($conn->inTransaction()||$digest()!==$before)throw new RuntimeException('Failed request changed fixture data.');
        }else{
            $row=$conn->query('SELECT * FROM activities WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            if($row['title']!=='Edited activity'||(float)$row['cost']!==2.35||$row['cost_scope']!=='per_person')throw new RuntimeException('Activity update failed.');
            if($case==='manual'){
                if($row['latitude']!==null||$row['longitude']!==null||$row['location_catalog_id']!==null||$row['location_source_ref']!=='')throw new RuntimeException('Manual change retained stale coordinates.');
            }elseif((float)$row['latitude']!==10.5||(float)$row['longitude']!==106.5||$row['location_source_ref']!=='node:9'||$row['location_verified_at']!==null)throw new RuntimeException('Canonical catalog metadata lost or forged.');
            if($conn->query('SELECT cost FROM activities WHERE id=2')->fetchColumn()!==null)throw new RuntimeException('Unknown old cost converted to zero.');
            if($conn->query('SELECT note FROM trip_days WHERE id=1')->fetchColumn()!=='Keep note')throw new RuntimeException('Day note lost.');
            if($conn->query('SELECT description FROM trips WHERE id=1')->fetchColumn()!=='Keep description')throw new RuntimeException('Description lost.');
        }
        echo 'PASS edit persistence ',$case," (SQLite memory)\n";
    }catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
});
chdir($app);$controller=file_get_contents($app.'/edit-trip.php');$controller=str_replace(['session_start();',"require_once 'config/database.php';"],'',$controller);eval('?>'.$controller);
