<?php

// Hàm nghiệp vụ dùng bởi Admin và kiểm thử; nạp file không ghi dữ liệu.
function trip_cover_text(string $value): string
{
    $value = strtolower(trip_ascii_search_term(rawurldecode($value)));
    return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
}

function trip_cover_phrase(string $text, string $phrase): bool
{
    $phrase = trip_cover_text($phrase);
    $text = ' ' . trip_cover_text($text) . ' ';
    if (strlen($phrase) >= 3 && str_contains($text, ' ' . $phrase . ' ')) return true;
    // Commons thường viết liền tên phiên âm: Hà Nội -> Hanoi, Đà Lạt -> Dalat.
    $compact = str_replace(' ', '', $phrase);
    return strlen($compact) >= 5 && str_contains($text, ' ' . $compact . ' ');
}

function trip_cover_snapshot(array $trip): string
{
    unset($trip['location_name'], $trip['author_name']);
    ksort($trip);
    return hash('sha256', json_encode($trip, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function trip_cover_probe(string $url): string
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    // Chỉ HEAD các nhà cung cấp ảnh cố định; không theo redirect hoặc gọi URL nội bộ.
    if (parse_url($url, PHP_URL_SCHEME) !== 'https'
        || !in_array($host, ['upload.wikimedia.org', 'thumb.wikimedia.org', 'images.unsplash.com'], true)
        || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PORT) !== null) return 'unknown';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'TripConnect/1.0 (admin cover audit)']);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($response === false) return 'unknown';
    if (in_array($code, [400, 404, 410], true)) return 'broken';
    if ($code >= 200 && $code < 300 && str_starts_with(strtolower($type), 'image/')) return 'ok';
    // 403/429/5xx hoặc lỗi mạng không chứng minh ảnh của người dùng bị hỏng.
    return 'unknown';
}

function trip_cover_assess(array $trip, bool $probe = false): array
{
    $url = trim((string) ($trip['cover_image'] ?? ''));
    $source = (string) ($trip['cover_source'] ?? '');
    $result = static fn(bool $eligible, string $reason): array => ['eligible' => $eligible, 'reason' => $reason];
    if ($url === '' || $source === 'placeholder') return $result(true, 'Chưa có ảnh bìa phù hợp.');
    if (trip_safe_image_url($url) === null) return $result(true, 'Ảnh mẫu cũ/URL không hợp lệ; không có bằng chứng đúng địa danh.');
    if (str_starts_with(ltrim($url, '/'), 'assets/')) {
        $root = realpath(__DIR__ . '/..');
        $file = realpath($root . DIRECTORY_SEPARATOR . ltrim($url, '/'));
        if (!$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file) || @getimagesize($file) === false) {
            return $result(true, 'Ảnh nội bộ bị thiếu hoặc không đọc được.');
        }
        return $result(false, 'Giữ nguyên ảnh upload đang hợp lệ.');
    }
    if ($source === 'wikimedia_auto' && strlen($url) === 255
        && !preg_match('/\.(?:jpe?g|png|webp|gif|svg|tiff?)(?:$|\?)/i', basename((string) parse_url($url, PHP_URL_PATH)))) {
        return $result(true, 'URL ảnh tự động bị cắt tại giới hạn cũ 255 ký tự.');
    }
    $health = $probe ? trip_cover_probe($url) : 'unknown';
    if ($health === 'broken') return $result(true, 'Máy chủ ảnh trả về URL lỗi (400/404/410).');
    if ($source === 'wikimedia' || $source === 'user_upload') {
        return $result(false, $health === 'ok' ? 'Giữ nguyên ảnh do người dùng chủ động chọn.' : 'Ảnh do người dùng chọn; chưa đủ bằng chứng ảnh hỏng, giữ nguyên.');
    }
    if ($source === 'wikimedia_auto' && !trip_cover_phrase((string) ($trip['cover_source_url'] ?? ''), (string) ($trip['location_name'] ?? ''))) {
        return $result(true, 'Ảnh tự động thiếu bằng chứng điểm đến chính; cần Admin duyệt ảnh thay thế.');
    }
    return $result(false, $health === 'ok' ? 'Ảnh đang tải được; cần kiểm tra địa danh bằng mắt, không tự ghi đè.' : 'Chưa xác minh URL; không tự ghi đè ảnh chưa rõ nguồn gốc.');
}

function trip_cover_is_hcm(string $area): bool
{
    return in_array(trip_cover_text($area), ['ho chi minh', 'ho chi minh city', 'tp ho chi minh', 'thanh pho ho chi minh', 'saigon', 'sai gon', 'tphcm', 'tp hcm'], true);
}

function trip_cover_area_evidence(string $text, string $area): bool
{
    if (!trip_cover_is_hcm($area)) return trip_cover_phrase($text, $area);
    // “Ho Chi Minh” riêng lẻ còn chỉ nhân vật lịch sử; cần tên gọi của thành phố.
    foreach (['Ho Chi Minh City', 'Thanh pho Ho Chi Minh', 'Saigon', 'Sai Gon', 'HCMC', 'TPHCM'] as $alias) {
        if (trip_cover_phrase($text, $alias)) return true;
    }
    return false;
}

function trip_cover_candidate_score(array $candidate, array $trip, array $places): int
{
    foreach (['image_url' => 1000, 'source_url' => 500, 'attribution' => 500, 'license' => 100, 'license_url' => 500] as $key => $limit) {
        if (!is_string($candidate[$key] ?? null) || trim($candidate[$key]) === '' || strlen($candidate[$key]) > $limit) return 0;
    }
    if (parse_url($candidate['image_url'], PHP_URL_SCHEME) !== 'https'
        || !in_array(strtolower((string) parse_url($candidate['image_url'], PHP_URL_HOST)), ['upload.wikimedia.org', 'thumb.wikimedia.org'], true)
        || parse_url($candidate['source_url'], PHP_URL_HOST) !== 'commons.wikimedia.org'
        || parse_url($candidate['source_url'], PHP_URL_SCHEME) !== 'https'
        || !filter_var($candidate['license_url'], FILTER_VALIDATE_URL)
        || parse_url($candidate['license_url'], PHP_URL_SCHEME) !== 'https'
        || !in_array(strtolower((string) parse_url($candidate['license_url'], PHP_URL_HOST)), ['creativecommons.org', 'www.creativecommons.org', 'commons.wikimedia.org', 'gnu.org', 'www.gnu.org'], true)
        || !preg_match('/^(?:CC BY(?:-SA)?(?: |$)|CC0|Public Domain|PD)/i', $candidate['license'])) return 0;
    $title = trip_cover_text((string) ($candidate['title'] ?? ''));
    if (preg_match('/\b(map|logo|flag|diagram|portrait)\b|coat of arms/', $title)
        || preg_match('/\.(?:svg|pdf|djvu)$/i', (string) ($candidate['title'] ?? ''))) return 0;
    $evidence = ($candidate['title'] ?? '') . ' ' . $candidate['source_url'] . ' ' . ($candidate['description'] ?? '');
    // Luôn cần bằng chứng điểm đến chính, không chỉ trùng một từ ngắn như “Da”.
    if (!trip_cover_area_evidence($evidence, (string) ($trip['location_name'] ?? ''))) return 0;
    $score = 60;
    foreach ($places as $place) if (trip_cover_phrase($evidence, $place)) $score += 15;
    if (trip_cover_phrase($evidence, (string) ($trip['title'] ?? ''))) $score += 10;
    if (trip_cover_area_evidence((string) ($candidate['title'] ?? ''), (string) ($trip['location_name'] ?? ''))) $score += 20;
    return $score;
}

function trip_cover_propose(PDO $conn, array $trip, ?callable $search = null, ?callable $probe = null): array
{
    $query = $conn->prepare('SELECT a.location_name, lc.name AS catalog_name FROM activities a JOIN trip_days d ON d.id = a.trip_day_id LEFT JOIN location_catalog lc ON lc.id = a.location_catalog_id WHERE d.trip_id = ? ORDER BY d.day_number, a.sort_order, a.id');
    $query->execute([(int) $trip['id']]);
    $places = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string) ($row['catalog_name'] ?: $row['location_name']));
        if ($name !== '') $places[] = $name;
    }
    $places = array_values(array_unique($places));
    $area = trim((string) ($trip['location_name'] ?? ''));
    if ($area === '') return ['candidate' => null, 'status' => 'retry', 'note' => 'Thiếu điểm đến chính để xác minh ảnh. Cần kiểm tra và tìm lại; chưa thay đổi ảnh.'];
    $terms = array_values(array_unique(array_filter([$area, trip_ascii_search_term($area) . ' city', str_replace(' ', '', trip_cover_text($area)), ...array_slice($places, 0, 2), trim((string) $trip['title'])])));
    if (trip_cover_is_hcm($area)) {
        $terms = array_values(array_unique(['Ho Chi Minh City', 'Saigon', 'Sai Gon', ...array_slice($places, 0, 2)]));
        $search ??= static fn(string $term, string $context): array => trip_wikimedia_search_images($term, $context, false, true);
    } else {
        $search ??= 'trip_wikimedia_search_images';
    }
    $candidates = [];
    $failure = 0;
    foreach ($terms as $term) {
        try {
            $results = $search($term, $area);
            foreach ($results as $candidate) {
                $candidate['score'] = trip_cover_candidate_score($candidate, $trip, $places);
                if ($candidate['score'] > 0) $candidates[$candidate['source_url']] = $candidate;
            }
        } catch (Throwable $e) {
            $failure++;
            error_log('[TripConnect cover maintenance] search failed: ' . $e->getMessage());
        }
        // Có ảnh đúng địa danh: không tiếp tục gọi API cho từng địa điểm.
        if ($candidates) break;
    }
    if (!$candidates) return ['candidate' => null, 'status' => $failure ? 'error' : 'retry',
        'note' => $failure ? 'Tìm ảnh chưa hoàn tất do lỗi dịch vụ; hãy thử lại, chưa cập nhật ảnh.' : 'Chưa có kết quả đủ bằng chứng địa danh và giấy phép. Cần tìm lại; giữ nguyên dữ liệu hiện tại.'];
    $candidates = array_values($candidates);
    usort($candidates, static fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['title'], $b['title']));
    $candidate = $candidates[0];
    $probe ??= 'trip_cover_probe';
    foreach (array_slice($candidates, 0, 3) as $option) {
        if ($probe($option['image_url']) !== 'broken') {
            $candidate = $option;
            return ['candidate' => $candidate, 'status' => 'ready', 'note' => 'Khớp điểm đến chính; ưu tiên địa điểm Timeline, sau đó tên lịch trình.'];
        }
    }
    return ['candidate' => null, 'status' => 'retry', 'note' => 'Ảnh đề xuất không tải được. Cần tìm lại; chưa cập nhật ảnh.'];
}

function trip_cover_apply_previews(PDO $conn, array $requests, array $previews, int $now): int
{
    if (!$requests || count($requests) > 10) throw new InvalidArgumentException('Chọn từ 1 đến 10 lịch trình đã xem trước.');
    ksort($requests, SORT_NUMERIC);
    $conn->beginTransaction();
    try {
        $count = 0;
        foreach ($requests as $id => $token) {
            if (filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1) throw new RuntimeException('Mã lịch trình không hợp lệ.');
            $preview = $previews[$id] ?? null;
            if (!is_array($preview) || !is_string($token) || !hash_equals($preview['token'], $token)
                || empty($preview['eligible']) || $preview['expires'] < $now || $preview['status'] !== 'ready'
                || !is_array($preview['candidate'] ?? null)) {
                throw new RuntimeException('Đề xuất thiếu hoặc hết hạn. Vui lòng tìm ảnh/xem trước lại.');
            }
            $query = $conn->prepare('SELECT * FROM trips WHERE id = ? FOR UPDATE');
            $query->execute([(int) $id]);
            $trip = $query->fetch(PDO::FETCH_ASSOC);
            if (!$trip || $trip['status'] === 'deleted' || !hash_equals($preview['snapshot'], trip_cover_snapshot($trip))) {
                throw new RuntimeException('Lịch trình đã thay đổi sau khi xem trước. Chưa cập nhật; hãy tải lại đề xuất.');
            }
            $candidate = $preview['candidate'];
            if (trip_cover_candidate_score($candidate, ['location_name' => $preview['area'], 'title' => $trip['title']], []) === 0) {
                throw new RuntimeException('Metadata ảnh đề xuất không hợp lệ.');
            }
            $update = $conn->prepare('UPDATE trips SET cover_image = ?, cover_source = ?, cover_source_url = ?, cover_attribution = ?, cover_license = ?, cover_license_url = ?, cover_selected_at = ?, updated_at = updated_at WHERE id = ?');
            $update->execute([$candidate['image_url'], 'wikimedia_auto',
                $candidate['source_url'] ?? null, $candidate['attribution'] ?? null, $candidate['license'] ?? null,
                $candidate['license_url'] ?? null, (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s'), (int) $id]);
            $count++;
        }
        $conn->commit();
        return $count;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}
