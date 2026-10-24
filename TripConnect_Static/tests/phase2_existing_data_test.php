<?php
// SELECT-only audit of the already imported dataset. Does not load or run the Seeder.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/trip_timeline.php';
$checks=0;
function existing_check(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$counts=$conn->query('SELECT (SELECT COUNT(*) FROM users) users,(SELECT COUNT(*) FROM trips) trips')->fetch(PDO::FETCH_ASSOC);
existing_check((int)$counts['users']===229 && (int)$counts['trips']===42,'Unexpected project baseline; do not repair automatically.');
$q=$conn->prepare('SELECT * FROM trips WHERE description LIKE ? ORDER BY id');$q->execute(['[TripConnect demo v1 trip:%']);$trips=$q->fetchAll(PDO::FETCH_ASSOC);
existing_check(count($trips)===33,'Expected 33 imported trips.');$totalDays=0;$totalActivities=0;$tagCount=0;$pairs=0;
foreach($trips as $trip){
    existing_check($trip['status']==='published' && $trip['moderation_status']==='visible','Seed publication state.');
    foreach(['cover_image','cover_source_url','cover_attribution','cover_license','cover_license_url'] as $field)existing_check(!empty($trip[$field]),'Photo metadata missing.');
    $q=$conn->prepare('SELECT * FROM trip_days WHERE trip_id=? ORDER BY day_number');$q->execute([$trip['id']]);$days=$q->fetchAll(PDO::FETCH_ASSOC);$totalDays+=count($days);
    existing_check(count($days)===(int)$trip['days_count'],'Day count.');$payload=[];$independent=0;
    foreach($days as $i=>$day){
        existing_check((int)$day['day_number']===$i+1 && $day['day_date']===trip_timeline_day_date(trip_timeline_parse_datetime($trip['departure_at']),$i+1),'Day sequence/date.');
        $q=$conn->prepare('SELECT * FROM activities WHERE trip_day_id=? ORDER BY sort_order,id');$q->execute([$day['id']]);$activities=$q->fetchAll(PDO::FETCH_ASSOC);$payload['day_'.($i+1)]=$activities;$totalActivities+=count($activities);
        foreach($activities as $j=>$a){
            existing_check((int)$a['sort_order']===$j+1 && $a['location_name']!=='' && $a['title']!=='','Activity order/place/title.');
            existing_check(in_array($a['cost_scope'],['fixed','per_person'],true) && $a['cost']!==null,'Explicit cost/scope.');
            $independent+=(int)round((float)$a['cost']*100)*($a['cost_scope']==='per_person'?(int)$trip['participant_count']:1);
            if($a['latitude']!==null || $a['longitude']!==null){existing_check(trip_timeline_coordinate_pair($a['latitude'],$a['longitude'])!==null,'No partial/invalid coordinate.');$pairs++;}
        }
    }
    $normal=trip_timeline_normalize_payload($payload,(int)$trip['days_count'],trip_timeline_parse_datetime($trip['departure_at']),trip_timeline_parse_datetime($trip['return_at']),'published');
    existing_check(!$normal['errors'] && !$normal['warnings'],'Time/date/overnight overlap validation.');
    $sum=trip_timeline_budget_summary($payload,(int)$trip['participant_count']);
    existing_check($sum['cents']===$independent && !$sum['incomplete'],'Group cost.');
    existing_check($sum['per_person']===round($independent/100/(int)$trip['participant_count'],2),'Per-person cost.');
    existing_check(array_sum(array_column($sum['days'],'cents'))===$independent && array_sum(array_column($sum['categories'],'cents'))===$independent,'Day/category cost.');
    $q=$conn->prepare('SELECT COUNT(*) FROM trip_tags WHERE trip_id=?');$q->execute([$trip['id']]);$tags=(int)$q->fetchColumn();existing_check($tags>0,'Tags present.');$tagCount+=$tags;
}
existing_check($totalDays===95 && $totalActivities===522 && $tagCount===69,'Exact imported counts.');
echo json_encode(['status'=>'PASS','checks'=>$checks,'trips'=>33,'days'=>$totalDays,'activities'=>$totalActivities,'tags'=>$tagCount,'coordinate_pairs'=>$pairs,'main_users'=>(int)$counts['users'],'main_trips'=>(int)$counts['trips'],'mode'=>'SELECT only; no Seeder']),PHP_EOL;
