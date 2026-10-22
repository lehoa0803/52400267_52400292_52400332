<?php
declare(strict_types=1);

session_start();
require_once 'config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Bạn cần đăng nhập để tìm địa điểm.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function locationJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function locationInputText(string $key, int $max = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search_wikimedia') {
    $queryRaw = trim((string) ($_GET['q'] ?? ''));
    $areaRaw = trim((string) ($_GET['area'] ?? ''));
    $query = function_exists('mb_substr') ? mb_substr($queryRaw, 0, 150) : substr($queryRaw, 0, 150);
    $area = function_exists('mb_substr') ? mb_substr($areaRaw, 0, 150) : substr($areaRaw, 0, 150);
    if ($query === '') {
        locationJson(['ok' => false, 'message' => 'Hãy nhập tên địa điểm hoặc khu vực cần tìm ảnh.'], 422);
    }
    try {
        $results = trip_wikimedia_search_images($query, $area);
        locationJson([
            'ok' => true,
            'source' => 'wikimedia',
            'results' => $results,
            'message' => $results ? '' : 'Chưa tìm được ảnh Wikimedia có đủ bằng chứng địa điểm và giấy phép phù hợp.',
        ]);
    } catch (Throwable $exception) {
        error_log('[TripConnect location-search] Wikimedia image search failed: ' . $exception->getMessage());
        locationJson(['ok' => false, 'message' => 'Không thể kết nối Wikimedia Commons lúc này. Vui lòng thử lại sau.'], 502);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_external') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['trip_csrf_token'] ?? '', $csrf)) {
        locationJson(['ok' => false, 'message' => 'CSRF không hợp lệ.'], 403);
    }

    $raw = (string) ($_POST['result'] ?? '');
    $result = json_decode($raw, true);
    if (!is_array($result)) locationJson(['ok' => false, 'message' => 'Kết quả địa điểm không hợp lệ.'], 422);

    $osmType = locationInputText('osm_type', 20);
    $osmId = locationInputText('osm_id', 50);
    $name = trim((string) ($result['name'] ?? $result['display_name'] ?? ''));
    $address = trim((string) ($result['display_name'] ?? ''));
    $lat = $result['lat'] ?? null;
    $lon = $result['lon'] ?? null;
    if ($osmType === '' || $osmId === '' || $name === '' || !is_numeric($lat) || !is_numeric($lon) || (float) $lat < -90 || (float) $lat > 90 || (float) $lon < -180 || (float) $lon > 180) {
        locationJson(['ok' => false, 'message' => 'Kết quả bên ngoài thiếu tên hoặc tọa độ hợp lệ.'], 422);
    }

    $sourceRef = $osmType . ':' . $osmId;
    $stmt = $conn->prepare('SELECT id, area_location_id, name, address, description, category, latitude, longitude, image_url, location_source, location_source_ref, source_url, location_verified_at, status FROM location_catalog WHERE location_source = ? AND location_source_ref = ? LIMIT 1');
    $stmt->execute(['osm', $sourceRef]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if (($existing['status'] ?? '') !== 'active') {
            locationJson(['ok' => false, 'message' => 'Địa điểm này đã bị ẩn và không thể chọn mới.'], 409);
        }
        locationJson(['ok' => true, 'location' => $existing, 'created' => false]);
    }

    $sourceUrl = 'https://www.openstreetmap.org/' . rawurlencode($osmType) . '/' . rawurlencode($osmId);
    $category = locationInputText('category', 50) ?: null;
    $stmt = $conn->prepare('INSERT INTO location_catalog (area_location_id, name, address, description, category, latitude, longitude, image_url, image_source_url, image_attribution, image_license, location_source, location_source_ref, source_url, location_verified_at, status, created_by) VALUES (NULL, ?, ?, NULL, ?, ?, ?, NULL, NULL, NULL, NULL, ?, ?, ?, NULL, \'active\', ?)');
    $stmt->execute([$name, $address !== '' ? $address : null, $category, number_format((float) $lat, 8, '.', ''), number_format((float) $lon, 8, '.', ''), 'osm', $sourceRef, $sourceUrl, (int) $_SESSION['user_id']]);
    $id = (int) $conn->lastInsertId();
    $stmt = $conn->prepare('SELECT id, area_location_id, name, address, description, category, latitude, longitude, image_url, location_source, location_source_ref, source_url, location_verified_at, status FROM location_catalog WHERE id = ?');
    $stmt->execute([$id]);
    locationJson(['ok' => true, 'location' => $stmt->fetch(PDO::FETCH_ASSOC), 'created' => true]);
}

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '' || (function_exists('mb_strlen') ? mb_strlen($query) : strlen($query)) < 2) {
    locationJson(['ok' => true, 'source' => 'mysql', 'results' => [], 'message' => 'Nhập ít nhất 2 ký tự để tìm.']);
}
$query = function_exists('mb_substr') ? mb_substr($query, 0, 100) : substr($query, 0, 100);
$like = '%' . $query . '%';
$stmt = $conn->prepare('SELECT lc.id, lc.name, lc.address, lc.description, lc.category, lc.latitude, lc.longitude, lc.image_url, lc.location_source, lc.location_source_ref, lc.source_url, lc.location_verified_at, l.name AS area_name, l.region AS area_region FROM location_catalog lc LEFT JOIN locations l ON l.id = lc.area_location_id WHERE lc.status = \'active\' AND (lc.name LIKE ? OR lc.address LIKE ? OR lc.category LIKE ? OR l.name LIKE ? OR l.region LIKE ?) ORDER BY lc.name LIMIT 20');
$stmt->execute([$like, $like, $like, $like, $like]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (($_GET['external'] ?? '0') !== '1') {
    locationJson(['ok' => true, 'source' => 'mysql', 'results' => $results, 'message' => $results ? '' : 'Chưa tìm thấy địa điểm phù hợp trong dữ liệu TripConnect.']);
}

$cacheKey = md5(mb_strtolower($query, 'UTF-8'));
if (isset($_SESSION['nominatim_cache'][$cacheKey]) && (int) $_SESSION['nominatim_cache'][$cacheKey]['expires'] > time()) {
    locationJson(['ok' => true, 'source' => 'nominatim', 'results' => $_SESSION['nominatim_cache'][$cacheKey]['results'], 'message' => '']);
}
$lastRequest = (float) ($_SESSION['nominatim_last_request_at'] ?? 0);
if (microtime(true) - $lastRequest < 1.1) {
    locationJson(['ok' => false, 'message' => 'Vui lòng chờ một giây trước khi tìm nguồn bên ngoài.'], 429);
}

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => $query,
    'format' => 'jsonv2',
    'addressdetails' => 1,
    'limit' => 8,
    'accept-language' => 'vi'
]);
$ch = function_exists('curl_init') ? curl_init($url) : false;
if ($ch === false) {
    error_log('[TripConnect location-search] cURL extension is unavailable.');
    locationJson(['ok' => false, 'message' => 'Máy chủ chưa bật cURL để tìm địa điểm bên ngoài.'], 503);
}
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_USERAGENT => 'TripConnect/1.0 (location search; contact administrator)',
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$body = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);
$_SESSION['nominatim_last_request_at'] = microtime(true);
if ($body === false || $curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
    error_log(sprintf('[TripConnect location-search] Nominatim request failed: errno=%d http=%d error=%s', $curlErrno, $httpCode, $curlError));
    if ($httpCode === 429) {
        $message = 'OpenStreetMap đang giới hạn truy vấn. Vui lòng chờ một chút rồi thử lại.';
    } elseif ($curlErrno === 6 || stripos($curlError, 'resolve host') !== false) {
        $message = 'Máy chủ chưa kết nối được OpenStreetMap (lỗi DNS hoặc mạng). Vui lòng kiểm tra kết nối XAMPP rồi thử lại.';
    } elseif ($curlError !== '') {
        $message = 'Không thể kết nối OpenStreetMap từ máy chủ. Vui lòng thử lại sau.';
    } else {
        $message = 'OpenStreetMap tạm thời không phản hồi. Vui lòng thử lại sau.';
    }
    locationJson(['ok' => false, 'message' => $message], 502);
}
$decoded = json_decode($body, true);
if (!is_array($decoded)) {
    error_log('[TripConnect location-search] Nominatim returned invalid JSON.');
    locationJson(['ok' => false, 'message' => 'OpenStreetMap trả về dữ liệu không hợp lệ. Vui lòng thử lại sau.'], 502);
}
$externalResults = [];
foreach ($decoded as $item) {
    if (!is_array($item) || !isset($item['lat'], $item['lon'], $item['display_name'], $item['osm_type'], $item['osm_id'])) continue;
    $externalResults[] = [
        'name' => trim((string) ($item['name'] ?? $item['display_name'])),
        'address' => trim((string) $item['display_name']),
        'latitude' => (string) $item['lat'],
        'longitude' => (string) $item['lon'],
        'location_source' => 'osm',
        'location_source_ref' => (string) $item['osm_type'] . ':' . (string) $item['osm_id'],
        'source_url' => 'https://www.openstreetmap.org/' . rawurlencode((string) $item['osm_type']) . '/' . rawurlencode((string) $item['osm_id']),
        'raw' => $item
    ];
}
$_SESSION['nominatim_cache'][$cacheKey] = ['expires' => time() + 600, 'results' => $externalResults];
locationJson(['ok' => true, 'source' => 'nominatim', 'results' => $externalResults, 'message' => $externalResults ? '' : 'OpenStreetMap không có kết quả phù hợp.']);
