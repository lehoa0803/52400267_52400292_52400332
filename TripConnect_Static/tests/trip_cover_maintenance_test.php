<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// PHP CLI; SQLite trong bộ nhớ, không kết nối/ghi MySQL và không gọi Wikimedia.
$source = file_get_contents(__DIR__ . '/../config/database.php');
$boundary = strpos($source, "\n\$host = getenv(");
if ($boundary === false) throw new RuntimeException('Không tìm được ranh giới cấu hình database; dừng kiểm thử để không kết nối MySQL.');
eval('?>' . substr($source, 0, $boundary));
require_once __DIR__ . '/../config/trip_cover_maintenance.php';

class CoverTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$checks = 0;
function cover_check(bool $ok, string $message): void
{
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($message);
}
$conn = new CoverTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$conn->exec('CREATE TABLE trips (id INTEGER PRIMARY KEY, user_id INTEGER, location_id INTEGER, title TEXT, description TEXT, status TEXT, moderation_status TEXT, cover_image TEXT, cover_source TEXT, cover_source_url TEXT, cover_attribution TEXT, cover_license TEXT, cover_license_url TEXT, cover_selected_at TEXT, updated_at TEXT)');
$conn->exec('CREATE TABLE trip_days (id INTEGER PRIMARY KEY, trip_id INTEGER, day_number INTEGER)');
$conn->exec('CREATE TABLE activities (id INTEGER PRIMARY KEY, trip_day_id INTEGER, location_catalog_id INTEGER, location_name TEXT, sort_order INTEGER)');
$conn->exec('CREATE TABLE location_catalog (id INTEGER PRIMARY KEY, name TEXT)');
$conn->exec('CREATE TABLE likes (id INTEGER PRIMARY KEY, trip_id INTEGER, user_id INTEGER)');
$insert = $conn->prepare("INSERT INTO trips (id,user_id,location_id,title,description,status,moderation_status,cover_image,updated_at) VALUES (?,42,1,'Đà Lạt cuối tuần','Keep content','published','hidden',?,'2026-10-24 00:00:00')");
$insert->execute([1, 'https://images.unsplash.com/photo-1528127269322-539801943592']);
$insert->execute([2, null]);
$conn->exec("INSERT INTO trip_days VALUES (1,1,1); INSERT INTO activities VALUES (1,1,NULL,'Hồ Xuân Hương',1); INSERT INTO likes VALUES (1,1,50)");
$trip = $conn->query('SELECT * FROM trips WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$trip['location_name'] = 'Đà Lạt';
cover_check(trip_cover_assess($trip)['eligible'], 'Ảnh mẫu phải được nhận diện.');
cover_check(trip_cover_assess(['cover_image' => null])['eligible'], 'Nhận diện ảnh thiếu.');
cover_check(trip_cover_assess(['cover_image' => 'assets/uploads/does-not-exist.jpg'])['eligible'], 'Nhận diện file upload bị mất.');
cover_check(!trip_cover_assess(['cover_image' => 'https://upload.wikimedia.org/valid.jpg', 'cover_source' => 'wikimedia'])['eligible'], 'Không ghi đè ảnh người dùng chọn khi chưa biết hỏng.');
cover_check(!trip_cover_assess(['cover_image' => 'https://example.com/photo.jpg'])['eligible'], 'Không tự thay ảnh chưa rõ nguồn gốc.');
cover_check(trip_cover_probe('http://127.0.0.1/private') === 'unknown' && trip_cover_probe('https://example.com/private') === 'unknown', 'Không probe URL tùy ý hoặc mạng nội bộ.');
cover_check(trip_cover_phrase('File:Dalat_park.jpg', 'Đà Lạt') && trip_cover_phrase('File:Hanoi_city.jpg', 'Hà Nội'), 'Nhận diện tên viết liền trên Commons.');
cover_check(!trip_cover_phrase('File:Da_Nang.jpg', 'Đà Lạt'), 'Không trùng nhầm Đà Nẵng và Đà Lạt.');
$candidate = ['title' => 'File:Da_Lat_park.jpg', 'image_url' => 'https://upload.wikimedia.org/test.jpg',
    'source_url' => 'https://commons.wikimedia.org/wiki/File:Da_Lat_park.jpg', 'attribution' => 'Photographer',
    'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/'];
cover_check(trip_cover_candidate_score($candidate, $trip, []) >= 80, 'Chấp nhận bằng chứng địa danh và giấy phép.');
cover_check(trip_cover_candidate_score($candidate, ['location_name' => 'Đà Nẵng'], []) === 0, 'Từ chối ảnh sai điểm đến.');
foreach (['license', 'source_url', 'attribution'] as $field) {
    $bad = $candidate; $bad[$field] = '';
    cover_check(trip_cover_candidate_score($bad, $trip, []) === 0, 'Từ chối thiếu metadata ' . $field);
}
$bad = $candidate; $bad['title'] = 'File:Da_Lat_map.png';
cover_check(trip_cover_candidate_score($bad, $trip, []) === 0, 'Không chọn bản đồ làm ảnh bìa.');
$bad = $candidate; $bad['image_url'] = 'https://evil.test/test.jpg';
cover_check(trip_cover_candidate_score($bad, $trip, []) === 0, 'Ảnh phải đến từ Wikimedia.');
$bad = $candidate; $bad['attribution'] = str_repeat('a', 501);
cover_check(trip_cover_candidate_score($bad, $trip, []) === 0, 'Không vượt độ dài metadata schema.');
$proposal = trip_cover_propose($conn, $trip, static fn() => [$candidate], static fn() => 'ok');
cover_check($proposal['status'] === 'ready' && $proposal['candidate']['image_url'] === $candidate['image_url'], 'Tự chọn ảnh đủ tin cậy, không yêu cầu chọn thủ công.');
$proposal = trip_cover_propose($conn, $trip, static fn() => [], static fn() => 'ok');
cover_check($proposal['status'] === 'retry' && $proposal['candidate'] === null, 'Chưa có ảnh phải tìm lại, không đề xuất ghi placeholder.');
$proposal = trip_cover_propose($conn, $trip, static fn() => [$candidate], static fn() => 'broken');
cover_check($proposal['status'] === 'retry', 'URL hỏng phải tìm lại, không ghi placeholder.');
$proposal = trip_cover_propose($conn, $trip, static function () { throw new RuntimeException('Mock offline'); });
cover_check($proposal['status'] === 'error', 'Lỗi dịch vụ không được xem là không có ảnh.');
cover_check(trip_cover_display_image(['cover_source' => 'placeholder', 'activity_image' => 'https://example.com/wrong.jpg']) === null, 'Placeholder không fallback lại ảnh Timeline sai.');

$now = time();
$previewFor = static function (int $id, ?array $image = null) use ($conn, $now): array {
    $trip = $conn->query('SELECT * FROM trips WHERE id=' . $id)->fetch(PDO::FETCH_ASSOC);
    return ['token' => 'token-' . $id, 'snapshot' => trip_cover_snapshot($trip), 'expires' => $now + 1800,
        'candidate' => $image, 'status' => $image ? 'ready' : 'placeholder', 'area' => 'Đà Lạt', 'eligible' => true];
};
$relatedBefore = [$conn->query('SELECT * FROM trip_days')->fetchAll(PDO::FETCH_ASSOC), $conn->query('SELECT * FROM activities')->fetchAll(PDO::FETCH_ASSOC), $conn->query('SELECT * FROM likes')->fetchAll(PDO::FETCH_ASSOC)];
$before = $conn->query('SELECT * FROM trips WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$previews = [1 => $previewFor(1, $candidate), 2 => $previewFor(2, $candidate)];
cover_check(trip_cover_apply_previews($conn, [1 => 'token-1', 2 => 'token-2'], $previews, $now) === 2, 'Xác nhận một lần cập nhật nhiều lịch trình.');
$after = $conn->query('SELECT * FROM trips WHERE id=1')->fetch(PDO::FETCH_ASSOC);
cover_check($after['cover_image'] === $candidate['image_url'] && $after['cover_source'] === 'wikimedia_auto' && $after['cover_license'] === 'CC BY-SA 4.0', 'Lưu URL và metadata cùng nhau.');
foreach (['user_id', 'title', 'description', 'status', 'moderation_status', 'updated_at'] as $field) cover_check($before[$field] === $after[$field], 'Giữ nguyên ' . $field);
cover_check($conn->query('SELECT cover_source FROM trips WHERE id=2')->fetchColumn() === 'wikimedia_auto', 'Chỉ lưu ảnh thật khi cập nhật hàng loạt.');
$relatedAfter = [$conn->query('SELECT * FROM trip_days')->fetchAll(PDO::FETCH_ASSOC), $conn->query('SELECT * FROM activities')->fetchAll(PDO::FETCH_ASSOC), $conn->query('SELECT * FROM likes')->fetchAll(PDO::FETCH_ASSOC)];
cover_check($relatedBefore === $relatedAfter, 'Timeline, ngày và lượt thích không thay đổi.');

$previews = [1 => $previewFor(1, $candidate), 2 => $previewFor(2, $candidate)];
$conn->exec("UPDATE trips SET description='Changed by author' WHERE id=2");
try {
    trip_cover_apply_previews($conn, [1 => 'token-1', 2 => 'token-2'], $previews, $now);
    throw new LogicException('Phải từ chối snapshot cũ.');
} catch (RuntimeException $expected) {
    cover_check($conn->query('SELECT cover_image FROM trips WHERE id=1')->fetchColumn() === $candidate['image_url'], 'Lỗi mục thứ hai rollback cả nhóm.');
}
foreach (['expired', 'token', 'unreviewed'] as $case) {
    $preview = $previewFor(1, $candidate);
    if ($case === 'expired') $preview['expires'] = $now - 1;
    if ($case === 'unreviewed') $preview['eligible'] = false;
    try {
        trip_cover_apply_previews($conn, [1 => $case === 'token' ? 'wrong' : 'token-1'], [1 => $preview], $now);
        throw new LogicException('Phải từ chối đề xuất không hợp lệ.');
    } catch (RuntimeException $expected) { cover_check(!$conn->inTransaction(), 'Từ chối an toàn: ' . $case); }
}
$legacy = $previewFor(1);
try {
    trip_cover_apply_previews($conn, [1 => 'token-1'], [1 => $legacy], $now);
    throw new LogicException('Không được dùng lại đề xuất placeholder cũ.');
} catch (RuntimeException $expected) {
    cover_check($conn->query('SELECT cover_source FROM trips WHERE id=1')->fetchColumn() === 'wikimedia_auto', 'Chặn ghi placeholder kể cả từ tab/session cũ.');
}
$hcm = $trip;
$empty = $previewFor(1);
$empty['status'] = 'ready';
$empty['candidate'] = [];
try {
    trip_cover_apply_previews($conn, [1 => 'token-1'], [1 => $empty], $now);
    throw new LogicException('Không được ghi ảnh rỗng dưới trạng thái ready.');
} catch (RuntimeException $expected) {
    cover_check(!$conn->inTransaction(), 'Chặn mọi đề xuất ảnh rỗng kể cả trạng thái ready.');
}
$hcm['location_name'] = 'Hồ Chí Minh';
$palace = array_replace($candidate, ['title' => 'File:Independence Palace or Reunification Palace (12110625233).jpg',
    'source_url' => 'https://commons.wikimedia.org/wiki/File:Independence_Palace_or_Reunification_Palace_(12110625233).jpg',
    'description' => 'Independence Palace (Dinh Độc Lập), a landmark in Ho Chi Minh City, Vietnam.']);
cover_check(trip_cover_candidate_score($palace, $hcm, ['Dinh Độc Lập']) === 75, 'Chấp nhận địa danh TP.HCM trong mô tả khi tên file tiếng Anh không có tên thành phố.');
$person = array_replace($palace, ['title' => 'File:Delegation UNICEF Hanoi sept.75.jpg', 'description' => 'UNICEF Delegation for Ho Chi Minh, 1975.']);
cover_check(trip_cover_candidate_score($person, $hcm, []) === 0, 'Không nhầm nhân vật Hồ Chí Minh với thành phố.');
$saigon = array_replace($palace, ['title' => 'File:Saigon skyline.jpg', 'description' => 'Saigon city skyline']);
cover_check(trip_cover_candidate_score($saigon, $hcm, []) === 80, 'Nhận diện Saigon là tên gọi TP.HCM.');
cover_check(trip_cover_candidate_score($palace, $trip, []) === 0, 'Không dùng ảnh TP.HCM cho Đà Lạt.');
$queries = [];
$proposal = trip_cover_propose($conn, $hcm, static function ($term) use (&$queries, $palace) { $queries[] = $term; return [$palace]; }, static fn() => 'ok');
cover_check($queries === ['Ho Chi Minh City'] && $proposal['status'] === 'ready', 'Riêng TP.HCM ưu tiên tên thành phố đầy đủ, không tìm nhân vật.');
$proposal = trip_cover_propose($conn, $hcm, static fn() => [], static fn() => 'ok');
cover_check($proposal['status'] === 'retry', 'TP.HCM không kết quả vẫn được thử lại.');
echo "PASS: $checks cover maintenance checks (SQLite memory, no live writes)\n";

// Tags and description: existing SQLite regression cases, merged unchanged.
// Chạy bằng PHP CLI; chỉ ghi vào SQLite :memory:, không truy cập MySQL thật.
require_once __DIR__ . '/../config/trip_metadata.php';

$checks = 0;
function metadata_check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

$conn = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$conn->exec('PRAGMA foreign_keys = ON');
$conn->exec('CREATE TABLE trips (id INTEGER PRIMARY KEY, description TEXT)');
$conn->exec('CREATE TABLE tags (id INTEGER PRIMARY KEY, name TEXT, type TEXT, status TEXT)');
$conn->exec('CREATE TABLE trip_tags (trip_id INTEGER REFERENCES trips(id), tag_id INTEGER REFERENCES tags(id), PRIMARY KEY (trip_id, tag_id))');
$conn->exec("INSERT INTO trips VALUES (1, 'original'), (2, 'other owner')");
$conn->exec("INSERT INTO tags VALUES (1, 'Biển', 'theme', 'active'), (2, 'Cũ', 'theme', 'hidden'), (3, 'Ẩm thực', 'theme', 'active'), (4, 'Ẩn khác', 'theme', 'hidden')");
$conn->exec('INSERT INTO trip_tags VALUES (1, 1), (1, 2), (2, 3)');
$tags = trip_metadata_load_tags($conn);
metadata_check(count($tags) === 4 && $tags[1]['name'] === 'Biển', 'Đọc đủ danh mục, giữ tiếng Việt.');

$normalized = trip_metadata_normalize(['tripDescription' => "  Mô tả\nTiếng Việt 🧭  ", 'trip_tags_present' => '1', 'tripTags' => ['1', '1', '3']], $tags);
metadata_check(!$normalized['errors'] && $normalized['description'] === "Mô tả\nTiếng Việt 🧭", 'Giữ mô tả tiếng Việt, xuống dòng và emoji.');
metadata_check($normalized['tag_ids'] === [1, 3], 'Không tạo tag trùng.');
$preserved = trip_metadata_normalize([], $tags, [1, 2], 'original');
metadata_check($preserved['description'] === 'original' && $preserved['tag_ids'] === [1, 2], 'Form thiếu trường không được xóa mô tả/tags cũ.');
metadata_check(trip_metadata_normalize(['tripDescription' => '', 'trip_tags_present' => '1'], $tags, [1, 2], 'original')['tag_ids'] === [], 'Bỏ chọn tất cả là yêu cầu xóa tags rõ ràng.');
metadata_check(!trip_metadata_normalize(['trip_tags_present' => '1', 'tripTags' => ['2']], $tags, [2])['errors'], 'Cho phép giữ tag cũ bị ẩn.');
foreach ([['2'], ['4'], ['999'], ['-1'], [['nested']], ['1 OR 1=1']] as $ids) {
    metadata_check((bool) trip_metadata_normalize(['trip_tags_present' => '1', 'tripTags' => $ids], $tags)['errors'], 'Từ chối tag ẩn/mất hoặc ID sai.');
}
metadata_check((bool) trip_metadata_normalize(['trip_tags_present' => '1', 'tripTags' => '1'], $tags)['errors'], 'Từ chối danh sách tags sai kiểu.');
metadata_check((bool) trip_metadata_normalize(['trip_tags_present' => ['1']], $tags)['errors'], 'Từ chối marker giả.');
metadata_check((bool) trip_metadata_normalize(['tripDescription' => ['invalid']], $tags)['errors'], 'Từ chối mô tả dạng mảng.');
metadata_check((bool) trip_metadata_normalize(['tripDescription' => str_repeat('ế', 21846)], $tags)['errors'], 'Kiểm tra giới hạn TEXT theo byte UTF-8.');
metadata_check(!trip_metadata_normalize(['tripDescription' => str_repeat('a', 65535)], $tags)['errors'], 'Chấp nhận đúng giới hạn TEXT.');

try {
    trip_metadata_save_tags($conn, 1, [1]);
    throw new RuntimeException('Thiếu transaction phải bị từ chối.');
} catch (LogicException $expected) {
    metadata_check(true, 'Bắt buộc transaction.');
}

$idsFor = static function (int $tripId) use ($conn): array {
    $query = $conn->prepare('SELECT tag_id FROM trip_tags WHERE trip_id = ? ORDER BY tag_id');
    $query->execute([$tripId]);
    return array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
};
$conn->beginTransaction();
trip_metadata_save_tags($conn, 1, [2, 3, 3]);
$conn->commit();
metadata_check($idsFor(1) === [2, 3], 'Thêm/bỏ tag, giữ tag ẩn cũ và không trùng liên kết.');
metadata_check($idsFor(2) === [3], 'Không ảnh hưởng tags chuyến đi khác.');

// Tag bị ẩn sau validation phải được kiểm tra lại trong transaction.
$conn->exec("UPDATE tags SET status = 'hidden' WHERE id = 1");
$conn->beginTransaction();
try {
    trip_metadata_save_tags($conn, 1, [1, 2]);
    throw new RuntimeException('Tag mới bị ẩn phải bị từ chối khi lưu.');
} catch (InvalidArgumentException $expected) {
    $conn->rollBack();
    metadata_check($idsFor(1) === [2, 3], 'Lỗi tag không làm mất liên kết cũ.');
}

$conn->exec("UPDATE tags SET status = 'active' WHERE id = 1");
$conn->exec("CREATE TRIGGER fail_tag_insert BEFORE INSERT ON trip_tags WHEN NEW.tag_id = 1 BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
$conn->beginTransaction();
try {
    $conn->exec("UPDATE trips SET description = 'new description' WHERE id = 1");
    trip_metadata_save_tags($conn, 1, [1]);
    throw new RuntimeException('Lỗi ghi giả lập phải được phát hiện.');
} catch (PDOException $expected) {
    $conn->rollBack();
    metadata_check($conn->query('SELECT description FROM trips WHERE id = 1')->fetchColumn() === 'original' && $idsFor(1) === [2, 3], 'Rollback phải khôi phục mô tả và toàn bộ tags.');
}
$conn->beginTransaction();
trip_metadata_save_tags($conn, 1, []);
$conn->commit();
metadata_check($idsFor(1) === [] && $idsFor(2) === [3], 'Bỏ hết tags chỉ tác động chuyến đi cần sửa.');

echo "PASS: $checks metadata checks (SQLite memory only)\n";
