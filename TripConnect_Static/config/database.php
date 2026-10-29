<?php
function trip_default_avatar(): string
{
    return 'assets/images/default-avatar.png';
}

function trip_avatar_url($value): string
{
    // Chỉ dùng avatar đã qua kiểm tra; giá trị rỗng hoặc đường dẫn cũ/sai
    // đều quay về ảnh mặc định để tránh hiển thị ảnh vỡ.
    $avatar = trip_safe_image_url($value);
    if ($avatar === null) {
        return trip_default_avatar();
    }

    // Ảnh cục bộ có thể đã bị xóa khỏi uploads nhưng đường dẫn vẫn còn trong DB.
    if (str_starts_with($avatar, 'assets/')) {
        $assetsRoot = realpath(__DIR__ . '/../assets');
        $avatarPath = realpath(__DIR__ . '/../' . $avatar);
        if ($assetsRoot === false || $avatarPath === false
            || !str_starts_with($avatarPath, $assetsRoot . DIRECTORY_SEPARATOR)
            || !is_file($avatarPath)) {
            return trip_default_avatar();
        }
    }

    return $avatar;
}

function trip_safe_image_url($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return null;
    }

    // These are the known legacy stock-image identifiers previously used as
    // generic trip fallbacks. Treat them as unavailable so a trip can fall
    // through to a linked catalog/activity image or the neutral placeholder.
    foreach (['1559592413-7cec4d0cae2b', '1528127269322-539801943592', '1518509562904-e7ef99cdcc86', '1583417319070-4a69db38a482'] as $legacyImageId) {
        if (str_contains($value, $legacyImageId)) {
            return null;
        }
    }

    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    if ($scheme !== '' && in_array($scheme, ['http', 'https'], true) && filter_var($value, FILTER_VALIDATE_URL)) {
        return $value;
    }

    if (preg_match('#^(?:/)?assets/[A-Za-z0-9._/-]+$#', $value)) {
        return ltrim($value, '/');
    }

    return null;
}

function trip_first_valid_image(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $safeUrl = trip_safe_image_url($candidate);
        if ($safeUrl !== null) {
            return $safeUrl;
        }
    }

    return null;
}

function trip_cover_display_image(array $trip): ?string
{
    // Admin đã xác nhận chưa có ảnh đủ tin cậy: không lấy lại ảnh Timeline sai.
    if (($trip['cover_source'] ?? '') === 'placeholder') return null;
    return trip_first_valid_image([$trip['cover_image'] ?? null, $trip['catalog_image'] ?? null, $trip['activity_image'] ?? null]);
}

function trip_cover_metadata_supported(PDO $conn): bool
{
    static $supported;
    if ($supported !== null) {
        return $supported;
    }

    try {
        $columns = $conn->query("SHOW COLUMNS FROM trips WHERE Field IN ('cover_source', 'cover_source_url', 'cover_attribution', 'cover_license', 'cover_license_url', 'cover_selected_at')")->fetchAll(PDO::FETCH_COLUMN);
        $supported = count(array_unique($columns)) === 6;
    } catch (Throwable $exception) {
        $supported = false;
    }
    return $supported;
}

function trip_cover_image_column_limit(PDO $conn): int
{
    try {
        $stmt = $conn->query("SELECT CHARACTER_MAXIMUM_LENGTH
                              FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE()
                                AND TABLE_NAME = 'trips'
                                AND COLUMN_NAME = 'cover_image'
                              LIMIT 1");
        $length = (int) $stmt->fetchColumn();
        return $length > 0 ? $length : 255;
    } catch (Throwable $exception) {
        error_log('[TripConnect cover] unable to read cover_image limit: ' . $exception->getMessage());
        return 255;
    }
}

function trip_cover_selection_from_post(int $maxLength = 255): ?array
{
    $imageUrl = trim((string) ($_POST['cover_selected_url'] ?? ''));
    if ($imageUrl === '') {
        return null;
    }

    $source = strtolower(trim((string) ($_POST['cover_selected_source'] ?? '')));
    $sourceUrl = trim((string) ($_POST['cover_selected_source_url'] ?? ''));
    $attribution = trim((string) ($_POST['cover_selected_attribution'] ?? ''));
    $license = trim((string) ($_POST['cover_selected_license'] ?? ''));
    $licenseUrl = trim((string) ($_POST['cover_selected_license_url'] ?? ''));
    $invalidReason = null;
    if ($source !== 'wikimedia') {
        $invalidReason = 'source';
    } elseif (trip_safe_image_url($imageUrl) === null) {
        $invalidReason = 'image_url';
    } elseif (strlen($imageUrl) > $maxLength) {
        $invalidReason = 'image_length';
    } elseif (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
        $invalidReason = 'source_url';
    } elseif ($attribution === '') {
        $invalidReason = 'attribution';
    } elseif ($license === '') {
        $invalidReason = 'license';
    } elseif (!filter_var($licenseUrl, FILTER_VALIDATE_URL)) {
        $invalidReason = 'license_url';
    }
    if ($invalidReason !== null) {
        // Keep diagnostics server-side and avoid logging the URL, token, or
        // personal data. This distinguishes a missing frontend value from a
        // rejected value without exposing sensitive request contents.
        error_log('[TripConnect cover] rejected selected cover: reason=' . $invalidReason
            . ' source=' . ($source !== '' ? $source : 'empty')
            . ' image_length=' . strlen($imageUrl));
        throw new InvalidArgumentException(strlen($imageUrl) > $maxLength
            ? 'Ảnh đã chọn chưa được hỗ trợ. Vui lòng chọn ảnh khác.'
            : 'Ảnh được chọn chưa có đủ nguồn, tác giả hoặc giấy phép hợp lệ.');
    }

    return [
        'image' => $imageUrl,
        'source' => 'wikimedia',
        'source_url' => $sourceUrl,
        'attribution' => $attribution,
        'license' => $license,
        'license_url' => $licenseUrl,
    ];
}

function trip_csrf_token(string $scope): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $key = 'trip_csrf_' . preg_replace('/[^a-z0-9_]/i', '_', $scope);
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$key];
}

function trip_verify_csrf(string $scope, string $submitted): bool
{
    $expected = trip_csrf_token($scope);
    return $submitted !== '' && hash_equals($expected, $submitted);
}

function trip_require_active_account(PDO $db): void
{
    $query = $db->prepare('SELECT status FROM users WHERE id = ?');
    $query->execute([(int) ($_SESSION['user_id'] ?? 0)]);
    if ($query->fetchColumn() !== 'active') {
        http_response_code(403);
        exit('<meta charset="utf-8"><p data-i18n-ui>Tài khoản không có quyền thao tác.</p><script src="assets/i18n.js?v=12"></script>');
    }
}

/**
 * Build safe, de-duplicated search terms from the trip's real location data.
 * This is intentionally data-driven: no destination names or trip IDs are
 * embedded in the application.
 */
function trip_cover_search_terms(PDO $conn, int $locationId, array $activities, array $catalogRecords = []): array
{
    $terms = [];
    $area = '';
    $stmt = $conn->prepare('SELECT name FROM locations WHERE id = ? LIMIT 1');
    $stmt->execute([$locationId]);
    $area = trim((string) ($stmt->fetchColumn() ?: ''));

    foreach ($activities as $dayActivities) {
        if (!is_array($dayActivities)) {
            continue;
        }
        foreach ($dayActivities as $activity) {
            if (!is_array($activity)) {
                continue;
            }
            $catalogId = (int) ($activity['location_catalog_id'] ?? 0);
            $catalogName = trim((string) ($catalogRecords[$catalogId]['name'] ?? ''));
            $activityName = trim((string) ($activity['location_name'] ?? $activity['location'] ?? ''));
            foreach ([$catalogName, $activityName] as $term) {
                if ($term === '' || (function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') : strlen($term)) < 3) {
                    continue;
                }
                $key = function_exists('mb_strtolower') ? mb_strtolower($term, 'UTF-8') : strtolower($term);
                if (!isset($terms[$key])) {
                    $terms[$key] = $term;
                }
            }
        }
    }

    if ($area !== '') {
        $key = function_exists('mb_strtolower') ? mb_strtolower($area, 'UTF-8') : strtolower($area);
        if (!isset($terms[$key])) {
            $terms[$key] = $area;
        }
    }

    return ['area' => $area, 'terms' => array_values($terms)];
}

/**
 * Resolve a cover only when exactly one eligible Wikimedia candidate remains.
 * A failed request or an ambiguous result never blocks saving the trip.
 */
function trip_auto_cover_for_trip(PDO $conn, int $locationId, array $activities, array $catalogRecords = [], int $maxImageLength = 255): array
{
    $search = trip_cover_search_terms($conn, $locationId, $activities, $catalogRecords);
    $candidates = [];
    $searched = [];
    foreach (array_slice($search['terms'], 0, 3) as $term) {
        $key = strtolower($term . '|' . $search['area']);
        if (isset($searched[$key])) {
            continue;
        }
        $searched[$key] = true;
        try {
            foreach (trip_wikimedia_search_images($term, $search['area']) as $candidate) {
                $candidateKey = (string) ($candidate['source_url'] ?? $candidate['image_url'] ?? '');
                if ($candidateKey !== '') {
                    $candidates[$candidateKey] = $candidate;
                }
            }
        } catch (Throwable $exception) {
            error_log('[TripConnect cover] Wikimedia auto-search failed: ' . $exception->getMessage());
        }

        // A unique match for a specific activity is sufficient. More searches
        // only increase ambiguity and API usage.
        if (count($candidates) === 1 && $term !== $search['area']) {
            break;
        }
        if (count($candidates) > 1) {
            break;
        }
    }

    $candidates = array_values($candidates);
    // Do not let an external image URL exceed the current trips.cover_image
    // column. A too-long candidate must never make the trip transaction fail.
    $candidates = array_values(array_filter($candidates, static function (array $candidate): bool {
        $imageUrl = trim((string) ($candidate['image_url'] ?? ''));
        return $imageUrl !== ''
            && strlen($imageUrl) <= $maxImageLength
            && trip_safe_image_url($imageUrl) !== null;
    }));
    if (count($candidates) !== 1) {
        return [
            'selected' => null,
            'candidates' => $candidates,
            'status' => $candidates ? 'needs_user_choice' : 'not_found',
        ];
    }

    $candidate = $candidates[0];
    return [
        'selected' => [
            'image' => $candidate['image_url'],
            'source' => 'wikimedia_auto',
            'source_url' => $candidate['source_url'],
            'attribution' => $candidate['attribution'],
            'license' => $candidate['license'],
            'license_url' => $candidate['license_url'],
        ],
        'candidates' => $candidates,
        'status' => 'auto_selected',
    ];
}

function trip_wikimedia_cache_dir(): string
{
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tripconnect-wikimedia-cache';
    if (!is_dir($directory)) {
        @mkdir($directory, 0700, true);
    }
    return $directory;
}

function trip_wikimedia_metadata_value(array $metadata, string $key): string
{
    $value = $metadata[$key]['value'] ?? '';
    return trim(is_string($value) ? strip_tags($value) : '');
}

function trip_ascii_search_term(string $value): string
{
    $map = [
        'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
        'À'=>'A','Á'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A','Ă'=>'A','Ằ'=>'A','Ắ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A','Â'=>'A','Ầ'=>'A','Ấ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A',
        'đ'=>'d','Đ'=>'D','è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
        'È'=>'E','É'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ẹ'=>'E','Ê'=>'E','Ề'=>'E','Ế'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E',
        'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','Ì'=>'I','Í'=>'I','Ỉ'=>'I','Ĩ'=>'I','Ị'=>'I',
        'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
        'Ò'=>'O','Ó'=>'O','Ỏ'=>'O','Õ'=>'O','Ọ'=>'O','Ô'=>'O','Ồ'=>'O','Ố'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O','Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O',
        'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
        'Ù'=>'U','Ú'=>'U','Ủ'=>'U','Ũ'=>'U','Ụ'=>'U','Ư'=>'U','Ừ'=>'U','Ứ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U',
        'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y','Ỳ'=>'Y','Ý'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y'
    ];
    return strtr($value, $map);
}

function trip_wikimedia_search_images(string $name, string $area = '', bool $forceOriginalQuery = false, bool $allowDescriptionEvidence = false): array
{
    $name = trim($name);
    $area = trim($area);
    if ($name === '') {
        return [];
    }

    // Chỉ luồng bảo trì TP.HCM dùng bộ lọc mô tả/cache mới; các tìm kiếm cũ giữ nguyên.
    $cacheKey = hash('sha256', ($allowDescriptionEvidence ? 'v6-place-description|' : 'v5|') . ($forceOriginalQuery ? 'original|' : 'ascii|') . strtolower($name . '|' . $area));
    $cacheFile = trip_wikimedia_cache_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 86400) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && (!$allowDescriptionEvidence || $cached !== [])) {
            return $cached;
        }
    }

    $cooldownFile = trip_wikimedia_cache_dir() . DIRECTORY_SEPARATOR . 'retry-after.txt';
    if (is_file($cooldownFile) && (int) @file_get_contents($cooldownFile) > time()) {
        throw new RuntimeException('Wikimedia đang giới hạn truy vấn; hãy thử lại sau ít phút.');
    }

    $searchName = $forceOriginalQuery ? $name : trip_ascii_search_term($name);
    $params = [
        'action' => 'query',
        'generator' => 'search',
        'gsrsearch' => '"' . $searchName . '"',
        'gsrnamespace' => 6,
        'gsrlimit' => 8,
        'prop' => 'imageinfo',
        'iiprop' => 'url|extmetadata',
        'iiurlwidth' => 900,
        'format' => 'json',
        'formatversion' => 2,
    ];
    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query($params);
    static $lastWikimediaRequestAt = 0.0;
    $elapsed = microtime(true) - $lastWikimediaRequestAt;
    if ($elapsed < 1.0) {
        usleep((int) ((1.0 - $elapsed) * 1000000));
    }
    $lastWikimediaRequestAt = microtime(true);
    $ch = function_exists('curl_init') ? curl_init($url) : false;
    if ($ch === false) {
        throw new RuntimeException('Máy chủ chưa bật cURL để tìm ảnh Wikimedia Commons.');
    }
    $retryAfter = 60;
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'TripConnect/1.0 (Wikimedia image search; contact administrator)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$retryAfter): int {
            if (preg_match('/^Retry-After:\s*(\d+)/i', $header, $matches)) $retryAfter = max(60, min(300, (int) $matches[1]));
            return strlen($header);
        },
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (in_array($status, [429, 503], true)) {
        @file_put_contents($cooldownFile, (string) (time() + $retryAfter), LOCK_EX);
    }
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("Wikimedia request failed: HTTP {$status} {$error}");
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Wikimedia trả về JSON không hợp lệ.');
    }

    $normalizedName = function_exists('mb_strtolower') ? mb_strtolower($searchName, 'UTF-8') : strtolower($searchName);
    $normalizedArea = function_exists('mb_strtolower') ? mb_strtolower($area, 'UTF-8') : strtolower($area);
    $nameTokens = array_values(array_filter(preg_split('/\s+/u', $normalizedName) ?: [], static fn(string $token): bool => function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') >= 2 : strlen($token) >= 2));
    $results = [];
    foreach (($decoded['query']['pages'] ?? []) as $page) {
        $info = $page['imageinfo'][0] ?? [];
        $metadata = $info['extmetadata'] ?? [];
        $title = trim((string) ($page['title'] ?? ''));
        $normalizedTitle = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        $overlap = count(array_filter($nameTokens, static fn(string $token): bool => str_contains($normalizedTitle, $token)));
        $nameEvidence = $normalizedName !== '' && (str_contains($normalizedTitle, $normalizedName) || ($nameTokens && $overlap >= min(2, count($nameTokens))));
        if ($allowDescriptionEvidence) {
            $descriptionText = trip_wikimedia_metadata_value($metadata, 'ImageDescription');
            $descriptionText = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(trip_ascii_search_term($descriptionText))));
            $queryText = trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(trip_ascii_search_term($searchName))));
            $nameEvidence = $nameEvidence || ($queryText !== '' && str_contains(' ' . $descriptionText . ' ', ' ' . $queryText . ' '));
        }
        $license = trip_wikimedia_metadata_value($metadata, 'LicenseShortName');
        $licenseUrl = trip_wikimedia_metadata_value($metadata, 'LicenseUrl');
        $artist = trip_wikimedia_metadata_value($metadata, 'Artist');
        $imageUrl = (string) ($info['thumburl'] ?? $info['url'] ?? '');
        $sourceUrl = (string) ($info['descriptionurl'] ?? '');
        $licenseAllowed = (bool) preg_match('/(CC BY|CC0|Public Domain|PD|CC BY-SA)/i', $license);
        if (!$nameEvidence || !$licenseAllowed || $licenseUrl === '' || $artist === '' || trip_safe_image_url($imageUrl) === null || !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            continue;
        }
        $areaEvidence = $normalizedArea !== '' && str_contains($normalizedTitle, $normalizedArea);
        $results[] = [
            'title' => $title,
            'image_url' => $imageUrl,
            'source_url' => $sourceUrl,
            'attribution' => $artist,
            'license' => $license,
            'license_url' => $licenseUrl,
            'match_note' => $areaEvidence ? 'Tên file có dấu hiệu khớp địa điểm và khu vực.' : 'Tên file khớp địa điểm; khu vực cần kiểm tra thêm.',
            'description' => trip_wikimedia_metadata_value($metadata, 'ImageDescription'),
        ];
    }
    usort($results, static fn(array $left, array $right): int => strcmp($left['title'], $right['title']));
    $results = array_slice($results, 0, 6);
    if (!$results && !$forceOriginalQuery && $searchName !== $name) {
        $results = trip_wikimedia_search_images($name, $area, true, $allowDescriptionEvidence);
    }
    if (!$allowDescriptionEvidence || $results !== []) {
        @file_put_contents($cacheFile, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $results;
}

$host = getenv('TRIPCONNECT_DB_HOST') ?: 'localhost';
$db_name = getenv('TRIPCONNECT_DB_NAME') ?: 'tripconnect';
$username = getenv('TRIPCONNECT_DB_USER') ?: 'root';
$password = getenv('TRIPCONNECT_DB_PASSWORD') ?: '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // BỎ DẤU // Ở ĐÂY ĐỂ IN RA MÀN HÌNH TEST
} catch(PDOException $exception) {
    error_log('[TripConnect database] connection failed: ' . $exception->getMessage());
    $databaseUiAssetPrefix=basename(dirname($_SERVER['SCRIPT_NAME']??''))==='admin'?'../':'';
    echo '<meta charset="utf-8"><p data-i18n-ui>Dịch vụ tạm thời không khả dụng. Vui lòng thử lại sau.</p><script src="'.$databaseUiAssetPrefix.'assets/i18n.js?v=11"></script>';
    exit();
}
?>
