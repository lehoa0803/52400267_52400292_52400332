<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/trip_timeline.php';
header('Cache-Control: no-store');
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
try {
    $actor=null;
    if(!empty($_SESSION['user_id'])){$q=$conn->prepare("SELECT id,role FROM users WHERE id=? AND status='active'");$q->execute([(int)$_SESSION['user_id']]);$actor=$q->fetch(PDO::FETCH_ASSOC)?:null;}
    $q=$conn->prepare("SELECT t.*,l.name AS destination,u.full_name AS author,u.status AS author_status FROM trips t JOIN users u ON u.id=t.user_id LEFT JOIN locations l ON l.id=t.location_id WHERE t.id=? AND t.status<>'deleted'");$q->execute([$id]);$trip=$q->fetch(PDO::FETCH_ASSOC);
    $public=$trip && $trip['status']==='published' && $trip['moderation_status']==='visible' && $trip['author_status']==='active';
    $private=$trip && $actor && ((int)$actor['id']===(int)$trip['user_id']||$actor['role']==='admin');
    if(!$public&&!$private){http_response_code(404);exit('<meta charset="utf-8"><p data-i18n-ui>Không tìm thấy lịch trình hoặc bạn không có quyền xuất.</p><script src="assets/i18n.js?v=10"></script>');}
    if(($_GET['format']??'')!=='png'){header('Location: trip-detail.php?id='.$id.'&print=1');exit;}
    $q=$conn->prepare('SELECT * FROM trip_days WHERE trip_id=? ORDER BY day_number,id');$q->execute([$id]);$days=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$conn->prepare('SELECT a.* FROM activities a JOIN trip_days d ON d.id=a.trip_day_id WHERE d.trip_id=? ORDER BY d.day_number,COALESCE(a.sort_order,65535),a.start_at,a.start_time,a.id');$q->execute([$id]);$groups=[];foreach($days as $day)$groups[(int)$day['id']]=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $activity)$groups[(int)$activity['trip_day_id']][]=$activity;
    $summary=trip_timeline_budget_summary($groups,$trip['participant_count']?(int)$trip['participant_count']:null);
    $clean=static fn($text)=>preg_replace('/^\[TripConnect demo v1 trip:[a-z0-9.-]+\]\s*/u','',(string)$text);
    $money=static fn($value)=>trip_timeline_money($value).'đ';
    $ui=static fn($value)=>['ui'=>$value];$text=static fn($value)=>['text'=>(string)$value];$amount=static fn($value)=>['money'=>(float)$value];
    $sections=[['title'=>'Thông tin chuyến đi','lines'=>[
        'Điểm đến: '.($trip['destination']?:'Chưa xác định').' · Tác giả: '.$trip['author'],
        (int)$trip['days_count'].' ngày · '.($trip['participant_count']?(int)$trip['participant_count'].' người':'Chưa xác định số người'),
        'Khởi hành: '.($trip['departure_at']?:'Chưa đặt').' · Kết thúc: '.($trip['return_at']?:'Chưa đặt'),
        $clean($trip['description']),
        $public?'Lịch trình công khai':'Bản xuất riêng tư — không chia sẻ khi chưa được phép',
    ]]];
    $sections[0]['lineParts']=[
        [$ui('Điểm đến:'),$text(' '),$trip['destination']?$text($trip['destination']):$ui('Chưa xác định'),$text(' · '),$ui('Tác giả:'),$text(' '.$trip['author'])],
        [$text((int)$trip['days_count'].' '),$ui('ngày'),$text(' · '),$trip['participant_count']?$text((int)$trip['participant_count'].' '):$ui('Chưa xác định số người'),$trip['participant_count']?$ui('người'):$text('')],
        [$ui('Khởi hành:'),$text(' '.($trip['departure_at']?:'')),$trip['departure_at']?$text(''):$ui('Chưa đặt'),$text(' · '),$ui('Kết thúc:'),$text(' '.($trip['return_at']?:'')),$trip['return_at']?$text(''):$ui('Chưa đặt')],
        null,[$ui($public?'Lịch trình công khai':'Bản xuất riêng tư — không chia sẻ khi chưa được phép')],
    ];
    foreach($days as $day){
        $lines=[];$parts=[];if($day['note'])$lines[]=$clean($day['note']);
        foreach($groups[(int)$day['id']] as $a){
            $start=$a['start_at']?:($a['start_time']?:'Chưa đặt giờ');$end=$a['end_at']?:'Chưa đặt giờ';
            $cents=trip_timeline_cost_cents($a,$trip['participant_count']?(int)$trip['participant_count']:null);$cost=$cents===null?null:$cents/100;
            $lines[]=$a['title'].' | '.$start.' → '.$end;
            $parts[count($lines)-1]=[$text($a['title'].' | '),$start==='Chưa đặt giờ'?$ui($start):$text($start),$text(' → '),$end==='Chưa đặt giờ'?$ui($end):$text($end)];
            $lines[]='Địa điểm: '.($a['location_name']?:'Chưa xác định');
            $parts[count($lines)-1]=[$ui('Địa điểm:'),$text(' '),$a['location_name']?$text($a['location_name']):$ui('Chưa xác định')];
            if(!empty($a['description']))$lines[]=$a['description'];
            if(!empty($a['notes'])){$lines[]='Ghi chú: '.$a['notes'];$parts[count($lines)-1]=[$ui('Ghi chú:'),$text(' '.$a['notes'])];}
            $lines[]='Chi phí: '.($a['cost']===null?'Chưa xác định':$money($a['cost'])).' · '.($a['cost_scope']==='per_person'?'mỗi người':($a['cost_scope']==='fixed'?'cả nhóm':'chưa rõ phạm vi')).' · Tổng: '.($cost===null?'Chưa xác định':$money($cost));
            $parts[count($lines)-1]=[$ui('Chi phí:'),$text(' '),$a['cost']===null?$ui('Chưa xác định'):$amount($a['cost']),$text(' · '),$ui($a['cost_scope']==='per_person'?'mỗi người':($a['cost_scope']==='fixed'?'cả nhóm':'chưa rõ phạm vi')),$text(' · '),$ui('Tổng:'),$text(' '),$cost===null?$ui('Chưa xác định'):$amount($cost)];
        }
        if(!$groups[(int)$day['id']]){$lines[]='Chưa có hoạt động.';$parts[count($lines)-1]=[$ui('Chưa có hoạt động.')];}
        $dayBudget=$summary['days'][(int)$day['id']];$lines[]='Tổng ngày: '.(!$groups[(int)$day['id']]?'Chưa có chi phí':(($dayBudget['incomplete']?'≥ ':'').$money($dayBudget['cents']/100).($dayBudget['incomplete']?' (còn chi phí chưa xác định)':'')));
        $parts[count($lines)-1]=[$ui('Tổng ngày:'),$text(' '),!$groups[(int)$day['id']]?$ui('Chưa có chi phí'):$amount($dayBudget['cents']/100),$dayBudget['incomplete']?$ui(' (còn chi phí chưa xác định)'):$text('')];
        $sections[]=['title'=>'Ngày '.(int)$day['day_number'].' — '.($day['title']?:'Timeline').($day['day_date']?' · '.$day['day_date']:''),'titleParts'=>[$ui('Ngày'),$text(' '.(int)$day['day_number'].' — '.($day['title']?:'Timeline').($day['day_date']?' · '.$day['day_date']:''))],'lines'=>$lines,'lineParts'=>$parts];
    }
    $lines=['Ngân sách mục tiêu: '.$money($trip['budget_estimate']),'Tổng chi phí dự kiến: '.(!$summary['count']?'Chưa xác định':(($summary['incomplete']?'≥ ':'').$money($summary['cents']/100))), 'Trung bình mỗi người: '.($summary['per_person']===null?'Chưa xác định':$money($summary['per_person']))];
    if($summary['incomplete'])$lines[]='Có '.$summary['incomplete'].' khoản chưa đủ dữ liệu. Không xem tổng này là chi phí đầy đủ.';
    foreach($summary['categories'] as $category)$lines[]=$category['label'].': '.$money($category['cents']/100);
    $lines[]='Chi phí trong bản xuất là dự kiến theo hoạt động; không phải số chi thực tế.';
    $parts=[[$ui('Ngân sách mục tiêu:'),$text(' '),$amount($trip['budget_estimate'])],[$ui('Tổng chi phí dự kiến:'),$text(' '.($summary['incomplete']?'≥ ':'')),$summary['count']?$amount($summary['cents']/100):$ui('Chưa xác định')],[$ui('Trung bình mỗi người:'),$text(' '),$summary['per_person']===null?$ui('Chưa xác định'):$amount($summary['per_person'])]];
    if($summary['incomplete'])$parts[]=[$text($summary['incomplete'].' '),$ui('khoản chưa đủ dữ liệu. Không xem tổng này là chi phí đầy đủ.')];
    foreach($summary['categories'] as $category)$parts[]=[$ui($category['label']),$text(': '),$amount($category['cents']/100)];
    $parts[]=[$ui('Chi phí trong bản xuất là dự kiến theo hoạt động; không phải số chi thực tế.')];
    $sections[]=['title'=>'Budget toàn chuyến','lines'=>$lines,'lineParts'=>$parts];
    $document=['id'=>$id,'title'=>$trip['title'],'sections'=>$sections];
}catch(Throwable $e){error_log('[TripConnect export] failed');http_response_code(503);exit('<meta charset="utf-8"><p data-i18n-ui>Không thể xuất lịch trình. Vui lòng thử lại.</p><script src="assets/i18n.js?v=10"></script>');}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title data-i18n-ui>Xuất lịch trình | TripConnect</title>
<style>body{margin:0;background:#f1f6f5;color:#143c39;font:16px Arial,sans-serif}header{padding:20px;position:sticky;top:0;background:white;box-shadow:0 1px 8px #ccc;z-index:1}button,a{font:inherit}button{padding:10px 15px;border:0;border-radius:8px;background:#0f6e66;color:white;cursor:pointer}button:disabled{opacity:.6}#pages{max-width:900px;margin:24px auto;padding:0 12px}article{margin:24px 0}canvas{width:100%;height:auto;box-shadow:0 2px 10px #ccd;display:block;margin:10px 0}#status{margin:10px 0}p{line-height:1.5}.actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}</style></head><body>
<header><div class="actions"><a href="trip-detail.php?id=<?= $id ?>"><span data-i18n-ui>← Lịch trình</span></a><button id="downloadPdf" disabled hidden><span data-i18n-ui>Tải PDF</span></button><span><span data-i18n-ui>Ảnh PNG: tải từng trang ở bản xem trước</span></span></div><p id="status" role="status"><span data-i18n-ui>Đang dàn trang…</span></p></header>
<main id="pages" aria-label="Xem trước bản xuất" data-i18n-attrs></main>
<script type="application/json" id="exportData"><?= json_encode($document,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="assets/pages/trip-export.js?v=1"></script><script src="assets/i18n.js?v=10"></script>
</body></html>
