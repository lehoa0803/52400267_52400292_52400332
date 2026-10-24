<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Chạy: C:\xampp\php\php.exe TripConnect_Static/tests/trip_timeline_test.php
// Chỉ kiểm thử hàm thuần; không kết nối hoặc ghi database.
require_once __DIR__ . '/../config/trip_timeline.php';

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

$departure = trip_timeline_parse_datetime('2026-10-24T06:00');
$return = trip_timeline_parse_datetime('2026-10-26T23:59');
$activity = [
    'title' => 'Tham quan', 'location' => 'Địa điểm A', 'category' => 'sightseeing',
    'cost' => '100000', 'cost_scope' => 'per_person',
    'start_time' => '08:00', 'end_time' => '09:00',
];
$normalize = static function (array $payload, string $status = 'published', array $trusted = [], array $catalog = []) use ($departure, $return): array {
    return trip_timeline_normalize_payload($payload, 2, $departure, $return, $status, $trusted, $catalog);
};
$result = $normalize(['day_1' => [$activity], 'day_2' => []]);
check(!$result['errors'], 'Hoạt động hợp lệ phải được chấp nhận.');
check($result['data']['day_1'][0]['start_at'] === '2026-10-24 08:00:00', 'Phải giữ đúng ngày và giờ địa phương.');
check($result['data']['day_1'][0]['cost'] === '100000.00' && $result['data']['day_1'][0]['cost_scope'] === 'per_person', 'Không nhân chi phí theo người khi lưu.');
check($result['data']['day_1'][0]['latitude'] === null, 'Không tự tạo tọa độ.');

$overnight = array_replace($activity, ['start_time' => '23:00', 'end_time' => '01:00', 'overnight' => true]);
$result = $normalize(['day_1' => [$overnight]]);
check(!$result['errors'] && $result['data']['day_1'][0]['end_at'] === '2026-10-25 01:00:00', 'Phải xử lý hoạt động qua đêm.');
check((bool) $normalize(['day_1' => [array_replace($overnight, ['overnight' => false])]])['errors'], 'Phải từ chối giờ kết thúc trước bắt đầu.');
check((bool) $normalize(['day_3' => [$activity]])['errors'], 'Không bỏ qua hoạt động ngoài số ngày.');
check(!$normalize(['day_3' => []])['errors'], 'Ngày trống ngoài phạm vi không được cản việc giảm số ngày.');
check((bool) $normalize(['day_1' => 'invalid'])['errors'], 'Phải từ chối cấu trúc ngày sai.');

foreach (['-1', '100000000', '1e999', 'abc'] as $cost) {
    check((bool) $normalize(['day_1' => [array_replace($activity, ['cost' => $cost])]])['errors'], 'Phải từ chối chi phí sai: ' . $cost);
}
foreach ([['latitude' => 91, 'longitude' => 106], ['latitude' => 10], ['latitude' => 10, 'longitude' => 181]] as $coordinates) {
    check((bool) $normalize(['day_1' => [array_replace($activity, $coordinates)]])['errors'], 'Phải từ chối tọa độ sai hoặc thiếu cặp.');
}

$trusted = [7 => [
    'location_catalog_id' => 3, 'location_name' => 'Địa điểm A',
    'latitude' => 10.5, 'longitude' => 106.5, 'image' => 'assets/a.jpg',
    'location_source' => 'verified', 'location_source_ref' => 'reference',
]];
$existing = array_replace($activity, ['id' => 7, 'latitude' => 10.5, 'longitude' => 106.5, 'image' => 'assets/a.jpg']);
$result = $normalize(['day_1' => [$existing]], 'published', $trusted);
check(!$result['errors'] && $result['data']['day_1'][0]['location_source'] === 'verified', 'Sửa thông tin khác phải giữ địa điểm đã xác minh.');
$result = $normalize(['day_1' => [array_replace($existing, ['location' => 'Địa điểm B'])]], 'published', $trusted);
$item = $result['data']['day_1'][0];
check(!$result['errors'] && $item['latitude'] === null && $item['longitude'] === null && $item['image'] === '' && $item['location_source'] === 'user', 'Đổi địa điểm nhập tay không được giữ tọa độ/ảnh nơi cũ.');
check((bool) $normalize(['day_1' => [$existing, $existing]], 'published', $trusted)['errors'], 'Phải từ chối ID hoạt động trùng.');

$catalog = [3 => [
    'id' => 3, 'name' => 'Địa điểm catalog', 'status' => 'hidden',
    'latitude' => 11, 'longitude' => 107, 'location_source' => 'verified',
]];
$result = $normalize(['day_1' => [array_replace($existing, ['location_catalog_id' => 3])]], 'published', $trusted, $catalog);
check(!$result['errors'] && $result['data']['day_1'][0]['latitude'] === 11.0, 'Giữ liên kết catalog cũ bị ẩn và ưu tiên tọa độ catalog.');
$catalog[4] = array_replace($catalog[3], ['id' => 4]);
check((bool) $normalize(['day_1' => [array_replace($existing, ['location_catalog_id' => 4])]], 'published', $trusted, $catalog)['errors'], 'Không cho ID cũ liên kết mới vào catalog bị ẩn.');

$incomplete = array_replace($activity, ['start_time' => '', 'end_time' => '']);
$result = $normalize(['day_1' => [$incomplete]], 'draft');
check(!$result['errors'] && (bool) $result['warnings'], 'Nháp chưa có thời gian vẫn được lưu với cảnh báo.');
check((bool) $normalize(['day_1' => [array_replace($incomplete, ['id' => 999])]])['errors'], 'ID tự khai không được bỏ qua kiểm tra thời gian xuất bản.');
check((bool) $normalize(['day_1' => [array_replace($activity, ['start_time' => '25:00'])]], 'draft')['errors'], 'Nháp vẫn phải từ chối giờ sai định dạng/phạm vi.');
check((bool) $normalize(['day_1' => [$activity, array_replace($activity, ['start_time' => '08:30', 'end_time' => '09:30'])]])['errors'], 'Xuất bản phải phát hiện chồng chéo thời gian.');

check((bool) trip_timeline_normalize_payload([], 1000000, null, null, 'draft')['errors'], 'Từ chối số ngày quá lớn trước khi tạo Timeline.');
check((bool) trip_timeline_normalize_payload([], 0, null, null, 'draft')['errors'], 'Từ chối số ngày bằng 0.');
$notedDays = [['day_number' => 1, 'note' => 'Giữ ngày đầu'], ['day_number' => 3, 'note' => 'Ghi chú ngày cuối']];
check((bool) trip_timeline_removed_day_errors($notedDays, 2, []), 'Không giảm ngày làm mất ghi chú cũ.');
check(!trip_timeline_removed_day_errors($notedDays, 2, ['day_desc_3' => '']), 'Cho phép chủ động xóa ghi chú rồi giảm ngày.');
check((bool) trip_timeline_removed_day_errors([], 2, ['day_desc_3' => 'Ghi chú mới']), 'Tạo mới không được bỏ qua ghi chú ngày ngoài phạm vi.');
check((bool) trip_timeline_removed_day_errors([], 2, ['day_desc_3' => ['invalid']]), 'Không chấp nhận ghi chú sai kiểu khi giảm ngày.');
check(!trip_timeline_removed_day_errors($notedDays, 3, []), 'Giữ đủ ngày không cản lưu ghi chú.');
check(!trip_timeline_removed_day_errors([], 2, ['day_desc_3' => '  ']), 'Ngày chỉ có khoảng trắng có thể bỏ.');
check((bool) trip_timeline_removed_day_errors([['day_number' => 5, 'note' => 'Ngày không liên tục']], 3, []), 'Bảo vệ ghi chú ngay cả khi các ngày có khoảng trống.');
$shift = trip_timeline_parse_datetime('2028-02-28T06:00');
check(trip_timeline_day_date($shift, 3) === '2028-03-01', 'Cộng ngày chính xác qua ngày nhuận.');
$result = trip_timeline_normalize_payload(['day_2' => [$overnight]], 3, $shift, trip_timeline_parse_datetime('2028-03-01T23:59'), 'published');
check(!$result['errors'] && $result['data']['day_2'][0]['start_at'] === '2028-02-29 23:00:00' && $result['data']['day_2'][0]['end_at'] === '2028-03-01 01:00:00', 'Đổi ngày khởi hành phải dựng lại ngày hoạt động và qua đêm, giữ nguyên giờ.');
$result = trip_timeline_normalize_payload(['day_1' => [$overnight]], 1, $departure, trip_timeline_parse_datetime('2026-10-24T23:59'), 'published');
check((bool) $result['errors'], 'Không xuất bản hoạt động qua đêm vượt giờ trở về.');

check(trip_timeline_cost_cents(['cost' => 100, 'cost_scope' => 'fixed'], 3) === 10000, 'Chi phí cố định không nhân số người.');
check(trip_timeline_cost_cents(['cost' => 100, 'cost_scope' => 'per_person'], 3) === 30000, 'Chi phí theo người chỉ nhân một lần.');
check(trip_timeline_cost_cents(['cost' => 100, 'cost_scope' => null], 3) === null, 'Không suy đoán phạm vi chi phí cũ.');
check(trip_timeline_cost_cents(['cost' => 100, 'cost_scope' => 'per_person'], null) === null, 'Thiếu số người không được coi chi phí theo người là tổng nhóm.');
foreach ([null, -1, 'NaN', '1e999'] as $badCost) check(trip_timeline_cost_cents(['cost' => $badCost, 'cost_scope' => 'fixed'], 3) === null, 'Chi phí thiếu/sai không được tính bằng 0.');
check(trip_timeline_cost_cents(['cost' => 0, 'cost_scope' => 'fixed'], null) === 0, 'Chi phí 0 hợp lệ khác với chưa xác định.');
$budgetDays = [1 => [['cost' => '100', 'cost_scope' => 'fixed', 'category' => 'food'], ['cost' => '50', 'cost_scope' => 'per_person', 'category' => 'Ăn uống']], 2 => [['cost' => '200', 'cost_scope' => 'fixed', 'category' => 'stay']], 3 => []];
$summary = trip_timeline_budget_summary($budgetDays, 3);
check($summary['cents'] === 45000 && $summary['per_person'] === 150.0 && $summary['incomplete'] === 0, 'Tổng nhóm và trung bình/người chính xác.');
check(array_sum(array_column($summary['days'], 'cents')) === $summary['cents'], 'Tổng theo ngày bằng tổng chuyến đi.');
check(array_sum(array_column($summary['categories'], 'cents')) === $summary['cents'] && count($summary['categories']) === 2, 'Danh mục tiếng Việt và mã cũ được gộp đúng, không tính trùng.');
check($summary['days'][3]['cents'] === 0 && $summary['categories']['lodging']['cents'] === 20000, 'Ngày trống và danh mục lưu trú cũ đúng.');
$budgetDays[2][] = ['cost' => '999', 'cost_scope' => null, 'category' => 'food'];
$summary = trip_timeline_budget_summary($budgetDays, 3);
check($summary['cents'] === 45000 && $summary['incomplete'] === 1 && $summary['per_person'] === null && $summary['days'][2]['incomplete'] === 1, 'Chi phí chưa rõ không làm sai tổng đã biết hoặc trung bình/người.');
$decimal = trip_timeline_budget_summary([1 => [['cost' => '0.10', 'cost_scope' => 'fixed'], ['cost' => '0.20', 'cost_scope' => 'fixed']]], 1);
check($decimal['cents'] === 30, 'Cộng chi phí theo đơn vị nhỏ nhất, tránh sai số so sánh ngân sách.');
$legacyScope = array_replace($existing, ['cost_scope' => null]);
$trustedScope = $trusted; $trustedScope[7]['cost_scope'] = null;
$result = $normalize(['day_1' => [$legacyScope]], 'draft', $trustedScope);
check(!$result['errors'] && $result['data']['day_1'][0]['cost_scope'] === null, 'Sửa chuyến đi không được tự biến phạm vi chi phí cũ thành fixed.');
unset($legacyScope['cost_scope']);
$result = $normalize(['day_1' => [$legacyScope]], 'draft', $trustedScope);
check(!$result['errors'] && $result['data']['day_1'][0]['cost_scope'] === null, 'Payload cũ thiếu trường phạm vi cũng phải giữ dữ liệu chưa xác định.');
check((bool) $normalize(['day_1' => [array_replace($activity, ['cost_scope' => null])]], 'draft')['errors'], 'Hoạt động mới phải có phạm vi hợp lệ.');
$stop = trip_timeline_map_stop(['location_name' => 'Old name', 'latitude' => 10, 'longitude' => null, 'catalog_name' => 'Catalog name', 'catalog_latitude' => 11, 'catalog_longitude' => 107]);
check($stop['coords'] === [11.0, 107.0] && $stop['name'] === 'Catalog name', 'Không ghép vĩ độ hoạt động với kinh độ catalog.');
check(trip_timeline_map_stop(['latitude' => 10, 'longitude' => null]) === null, 'Thiếu một tọa độ thì không vẽ marker.');
check(trip_timeline_map_stop(['latitude' => 91, 'longitude' => 106]) === null, 'Tọa độ ngoài phạm vi không tạo marker.');
$stop = trip_timeline_map_stop(['location_name' => 'Manual', 'latitude' => 10, 'longitude' => 106, 'catalog_name' => 'Catalog', 'catalog_latitude' => 11, 'catalog_longitude' => 107]);
check($stop['coords'] === [10.0, 106.0] && $stop['name'] === 'Manual', 'Ưu tiên cặp tọa độ hoạt động hợp lệ.');
check(trip_timeline_coordinate_pair(0, 0) === [0.0, 0.0], 'Không nhầm tọa độ 0 với dữ liệu trống.');

foreach ([null, '', '   '] as $blankCost) {
    check((bool) $normalize(['day_1'=>[array_replace($activity,['cost'=>$blankCost])]])['errors'], 'Chi phí chưa nhập không được tự trở thành miễn phí.');
}
$missingCost=$activity;unset($missingCost['cost']);
check((bool) $normalize(['day_1'=>[$missingCost]])['errors'], 'Thiếu trường tiền phải được phát hiện.');
check(!$normalize(['day_1'=>[array_replace($activity,['cost'=>'0'])]])['errors'], 'Nhập 0 rõ ràng là miễn phí hợp lệ.');
$trustedUnknown=$trusted;$trustedUnknown[7]['cost']=null;$trustedUnknown[7]['cost_scope']='fixed';
$result=$normalize(['day_1'=>[array_replace($existing,['cost'=>null,'cost_scope'=>'fixed'])]],'draft',$trustedUnknown);
check(!$result['errors'] && $result['data']['day_1'][0]['cost']===null, 'Sửa chuyến đi giữ khoản tiền cũ chưa xác định.');
check(trip_timeline_budget_summary($result['data'],2)['incomplete']===1, 'Tiền chưa xác định vẫn tạo cảnh báo Budget.');
$catalog[3]=array_replace($catalog[3],['status'=>'active','location_source'=>'osm','location_source_ref'=>'node:123','location_verified_at'=>null]);
$result=$normalize(['day_1'=>[array_replace($activity,['location_catalog_id'=>3,'latitude'=>80,'longitude'=>10])]],'published',[],$catalog);
check(!$result['errors'] && $result['data']['day_1'][0]['latitude']===11.0 && $result['data']['day_1'][0]['longitude']===107.0, 'Tọa độ catalog ghi đè tọa độ do client tự gửi.');
check($result['data']['day_1'][0]['location_source_ref']==='node:123' && $result['data']['day_1'][0]['location_source']==='osm' && $result['data']['day_1'][0]['location_verified_at']===null, 'Giữ mã OSM thật, không tự đánh dấu xác minh.');
check(trip_timeline_money(0.1)==='0,10' && trip_timeline_money(0.3)==='0,30', 'Không hiển thị chi phí nhỏ hơn một đồng thành 0.');
check(trip_timeline_money(150000)==='150.000' && trip_timeline_money(150000.25)==='150.000,25', 'Giữ cách hiển thị tiền nguyên và chính xác phần lẻ.');
check(trip_timeline_money(100/3)==='33,33', 'Trung bình mỗi người làm tròn nhất quán hai chữ số.');
check(trip_timeline_budget_summary([1=>[]],3)['per_person']===null, 'Chuyến trống chưa có trung bình chi phí.');
check(trip_timeline_budget_summary([1=>[['cost'=>0,'cost_scope'=>'fixed']]],3)['per_person']===0.0, 'Hoạt động miễn phí có trung bình 0 rõ ràng, khác chuyến trống.');
echo "PASS: $checks timeline/budget/map checks\n";
