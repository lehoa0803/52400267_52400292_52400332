<?php
session_start();
require_once 'config/database.php';
require_once 'config/trip_social.php';
require_once 'config/trip_timeline.php';
require_once 'config/trip_location_catalog.php';
require_once 'config/trip_metadata.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = '';
trip_require_active_account($conn);
$error_message = '';
$csrf_token = $_SESSION['trip_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['trip_csrf_token'] = $csrf_token;

$trip_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$warning_message = '';

if ($trip_id <= 0) {
    header('Location: profile.php');
    exit();
}

// Lấy danh sách địa điểm
try {
    $stmtLocations = $conn->query("SELECT id, name FROM locations ORDER BY name ASC");
    $locationsList = $stmtLocations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $locationsList = [];
}

try {
    $stmtTrip = $conn->prepare("SELECT * FROM trips WHERE id = ? AND user_id = ?");
    $stmtTrip->execute([$trip_id, $user_id]);
    $trip = $stmtTrip->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        header('Location: profile.php');
        exit();
    }

    // Lấy danh sách các ngày và hoạt động cũ
    $availableTags = trip_metadata_load_tags($conn);
    $stmtTags = $conn->prepare('SELECT tag_id FROM trip_tags WHERE trip_id = ?');
    $stmtTags->execute([$trip_id]);
    $existingTagIds = array_map('intval', $stmtTags->fetchAll(PDO::FETCH_COLUMN));
    $selectedTagIds = $existingTagIds;
    $stmtDays = $conn->prepare("SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number ASC");
    $stmtDays->execute([$trip_id]);
    $tripDays = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

    $form = [
        'title' => $trip['title'],
        'description' => (string) ($trip['description'] ?? ''),
        'location_id' => (int) $trip['location_id'],
        'start_location' => $trip['start_location'] ?? '',
        'departure_at' => trip_timeline_display_datetime($trip['departure_at'] ?? null),
        'return_at' => trip_timeline_display_datetime($trip['return_at'] ?? null),
        'days_count' => (int) $trip['days_count'],
        'participant_count' => $trip['participant_count'] ?? '',
        'budget' => $trip['budget_estimate'],
    ];
    $existingActivities = [];
    $trustedLocationMetadata = [];
    $existingDayNotes = [];
    $existingDayTitles = [];
    foreach ($tripDays as $day) {
        $dNum = $day['day_number'];
        $existingDayNotes['day_' . $dNum] = $day['note'] ?? '';
        // Lưu lại tiêu đề gốc của ngày để không bị lặp chữ "Ngày X: Ngày X"
        $existingDayTitles['day_' . $dNum] = $day['title'] ?? null;

        $stmtActs = $conn->prepare("SELECT * FROM activities WHERE trip_day_id = ? ORDER BY COALESCE(sort_order, 65535), start_at, start_time, id ASC");
        $stmtActs->execute([$day['id']]);
        $acts = $stmtActs->fetchAll(PDO::FETCH_ASSOC);
        
        $existingActivities['day_' . $dNum] = [];
        foreach ($acts as $act) {
            $trustedLocationMetadata[(int) $act['id']] = [
                'cost' => $act['cost'],
                'cost_scope' => $act['cost_scope'],
                'location_catalog_id' => $act['location_catalog_id'] ?? null,
                'location_name' => $act['location_name'],
                'latitude' => $act['latitude'],
                'longitude' => $act['longitude'],
                'image' => $act['image'] ?? '',
                'location_source' => $act['location_source'] ?? 'unknown',
                'location_source_ref' => $act['location_source_ref'] ?? '',
                'location_verified_at' => $act['location_verified_at'] ?? null,
            ];
            $existingActivities['day_' . $dNum][] = [
                'id' => (int) $act['id'],
                'location_catalog_id' => $act['location_catalog_id'] ? (int) $act['location_catalog_id'] : null,
                'start_at' => trip_timeline_display_datetime($act['start_at'] ?? null),
                'end_at' => trip_timeline_display_datetime($act['end_at'] ?? null),
                'title' => $act['title'],
                'location' => $act['location_name'],
                'cost' => $act['cost'],
                'cost_scope' => $act['cost_scope'],
                'category' => trip_timeline_category_code($act['category']),
                'description' => $act['description'] ?? '',
                'notes' => $act['notes'] ?? '',
                'menu_suggestions' => $act['menu_suggestions'] ?? '',
                'nearby_amenities' => $act['nearby_amenities'] ?? '',
                'location_source' => $act['location_source'] ?: 'unknown',
                'location_source_ref' => $act['location_source_ref'] ?? '',
                'latitude' => $act['latitude'],
                'longitude' => $act['longitude'],
                'image' => $act['image'] ?? '',
            ];
        }
    }
} catch (PDOException $e) {
    die("Lỗi cơ sở dữ liệu: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['trip_csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
        $error_message = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } else {
    $form['title'] = trim($_POST['tripName'] ?? '');
    $form['location_id'] = intval($_POST['tripLocation'] ?? 0);
    $form['start_location'] = trim($_POST['startLocation'] ?? '');
    $form['departure_at'] = trim($_POST['departureAt'] ?? '');
    $form['return_at'] = trim($_POST['returnAt'] ?? '');
    $form['days_count'] = intval($_POST['tripDays'] ?? 1);
    $form['participant_count'] = intval($_POST['participantCount'] ?? 0);
    $form['budget'] = is_scalar($_POST['tripBudget'] ?? '0') ? trim((string) ($_POST['tripBudget'] ?? '0')) : 'invalid';
    if ($form['budget'] === '') $form['budget'] = '0';
    $status = strtolower(trim($_POST['save_action'] ?? $_POST['tripStatus'] ?? 'draft'));
    $departure = trip_timeline_parse_datetime($form['departure_at']);
    $return = trip_timeline_parse_datetime($form['return_at']);
    $validationErrors = [];
    $metadata = trip_metadata_normalize($_POST, $availableTags, $existingTagIds, (string) ($trip['description'] ?? ''));
    $form['description'] = $metadata['description'];
    $selectedTagIds = $metadata['tag_ids'];
    $validationErrors = array_merge($validationErrors, $metadata['errors']);

    if (!in_array($status, ['draft', 'published'], true)) $validationErrors[] = 'Trạng thái lịch trình không hợp lệ.';
    if ($form['title'] === '' || mb_strlen($form['title']) > 150 || $form['location_id'] <= 0) $validationErrors[] = 'Vui lòng nhập tên chuyến đi và chọn địa điểm hợp lệ.';
    if ($form['participant_count'] < 1 || $form['participant_count'] > 65535) $validationErrors[] = 'Số người tham gia phải từ 1 đến 65535.';
    if (!is_numeric($form['budget']) || !is_finite((float) $form['budget']) || (float) $form['budget'] < 0 || (float) $form['budget'] > 9999999999.99) $validationErrors[] = 'Ngân sách phải từ 0 đến 9.999.999.999,99 đ.';
    if (($form['departure_at'] !== '' && !$departure) || ($form['return_at'] !== '' && !$return)) $validationErrors[] = 'Ngày giờ khởi hành hoặc trở về không hợp lệ.';
    if (($departure && !$return) || (!$departure && $return)) $validationErrors[] = 'Cần nhập đủ ngày giờ khởi hành và trở về.';
    if ($departure && $return && $return <= $departure) $validationErrors[] = 'Ngày giờ trở về phải sau ngày giờ khởi hành.';
    if ($departure && $return && $return > $departure) $form['days_count'] = $departure->setTime(0, 0)->diff($return->setTime(0, 0))->days + 1;
    if ($form['days_count'] < 1 || $form['days_count'] > 15) $validationErrors[] = 'Số ngày phải nằm trong khoảng từ 1 đến 15.';
    if ($status === 'published' && (!$departure || !$return)) $validationErrors[] = 'Lịch trình phải có đủ thời gian chuyến đi trước khi xuất bản.';

    $activitiesJson = $_POST['activities_json'] ?? null;
    $validationErrors = array_merge($validationErrors, trip_timeline_removed_day_errors($tripDays, $form['days_count'], $_POST));
    $activitiesDecoded = is_string($activitiesJson) ? json_decode($activitiesJson, true) : null;
    $hasSubmittedTimeline = is_string($activitiesJson) && json_last_error() === JSON_ERROR_NONE && is_array($activitiesDecoded);
    if (!is_string($activitiesJson) || json_last_error() !== JSON_ERROR_NONE || !is_array($activitiesDecoded)) {
        $validationErrors[] = 'Dữ liệu timeline không hợp lệ hoặc bị thiếu; không thể cập nhật để tránh xóa nhầm hoạt động cũ.';
        $activitiesDecoded = [];
    }
    $catalogRecords = trip_location_catalog_load($conn, $activitiesDecoded);
    $submittedCatalogIds = trip_location_catalog_ids($activitiesDecoded);
    $unknownCatalogIds = array_diff($submittedCatalogIds, array_keys($catalogRecords));
    if ($unknownCatalogIds) $validationErrors[] = 'Một hoặc nhiều địa điểm trong catalog không còn tồn tại.';
    $activitiesDecoded = trip_location_catalog_apply($activitiesDecoded, $catalogRecords);
    $timeline = trip_timeline_normalize_payload($activitiesDecoded, $form['days_count'], $departure, $return, $status, $trustedLocationMetadata, $catalogRecords);
    $validationErrors = array_merge($validationErrors, $timeline['errors']);
    $warning_message = $status === 'draft' && !empty($timeline['warnings']) ? implode(' ', $timeline['warnings']) : '';

    $deletedActivityJson = $_POST['deleted_activity_ids'] ?? '[]';
    $deletedActivityIds = is_string($deletedActivityJson) ? json_decode($deletedActivityJson, true) : null;
    if (!is_string($deletedActivityJson) || json_last_error() !== JSON_ERROR_NONE || !is_array($deletedActivityIds)) {
        $validationErrors[] = 'Danh sách hoạt động cần xóa không hợp lệ.';
        $deletedActivityIds = [];
    }
    $deletedActivityIds = array_values(array_unique(array_filter(array_map('intval', $deletedActivityIds), static fn($id) => $id > 0)));
    $submittedActivityIds = [];
    foreach ($timeline['data'] as $dayActivities) {
        foreach ($dayActivities as $activity) {
            if ($activity['id'] > 0) $submittedActivityIds[] = $activity['id'];
        }
    }
    if (array_intersect($deletedActivityIds, $submittedActivityIds)) {
        $validationErrors[] = 'Một hoạt động không thể vừa cập nhật vừa xóa.';
    }

    $selectedCover = null;
    $coverImageLimit = trip_cover_image_column_limit($conn);
    $hasCoverUpload = isset($_FILES['coverInput']) && ($_FILES['coverInput']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$hasCoverUpload) {
        try {
            $selectedCover = trip_cover_selection_from_post($coverImageLimit);
            if ($selectedCover !== null && !trip_cover_metadata_supported($conn)) {
                $validationErrors[] = 'Chưa thể lưu ảnh đã chọn. Vui lòng thử lại hoặc chọn ảnh khác.';
            }
        } catch (InvalidArgumentException $exception) {
            $validationErrors[] = $exception->getMessage();
        }
    }

    // Lấy lại ảnh cũ làm mặc định
    $cover_image = $trip['cover_image'] ?? '';
    $coverMeta = $selectedCover;
    // Khi người dùng chủ động chọn ảnh Wikimedia, URL ảnh phải đi cùng
    // metadata trong cùng lần UPDATE; không được chỉ lưu metadata rồi giữ
    // lại URL ảnh cũ.
    if ($selectedCover !== null) {
        $cover_image = $selectedCover['image'];
    }
    if (!$hasCoverUpload && $selectedCover === null && trip_safe_image_url($cover_image) === null) {
        $cover_image = null;
    }

    // Xử lý upload ảnh bìa mới (báo lỗi rõ ràng thay vì bỏ qua âm thầm)
    if (empty($error_message) && isset($_FILES['coverInput']) && $_FILES['coverInput']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['coverInput'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Ảnh vượt quá kích thước cho phép.',
                UPLOAD_ERR_FORM_SIZE  => 'Ảnh vượt quá giới hạn của form.',
                UPLOAD_ERR_PARTIAL    => 'Ảnh chỉ được tải lên một phần.',
                UPLOAD_ERR_NO_TMP_DIR => 'Không thể tải ảnh lên. Vui lòng thử lại.',
                UPLOAD_ERR_CANT_WRITE => 'Không thể tải ảnh lên. Vui lòng thử lại.',
            ];
            $error_message = $uploadErrors[$file['error']] ?? 'Lỗi upload không xác định.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (!in_array($ext, $allowedExt, true) || @getimagesize($file['tmp_name']) === false) {
                $error_message = 'File không phải ảnh hợp lệ (jpg, png, webp, gif).';
            } else {
                $uploadFileDir = __DIR__ . '/assets/uploads/'; // đường dẫn tuyệt đối trên đĩa
                if (!is_dir($uploadFileDir) && !mkdir($uploadFileDir, 0755, true)) {
                    $error_message = 'Không thể lưu ảnh. Vui lòng thử lại.';
                } elseif (!is_writable($uploadFileDir)) {
                    $error_message = 'Không thể lưu ảnh. Vui lòng thử lại.';
                } else {
                    $newFileName = md5(uniqid('', true)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadFileDir . $newFileName)) {
                        $cover_image = 'assets/uploads/' . $newFileName; // đường dẫn web lưu vào DB
                        $coverMeta = [
                            'source' => 'user_upload', 'source_url' => null, 'attribution' => null,
                            'license' => null, 'license_url' => null,
                        ];
                    } else {
                        $error_message = 'Không lưu được ảnh lên server.';
                    }
                }
            }
        }
    }

    if (empty($error_message) && empty($validationErrors)) {
        try {
            $conn->beginTransaction();

            $updateFields = 'location_id = ?, start_location = ?, title = ?, departure_at = ?, return_at = ?, days_count = ?, participant_count = ?, budget_estimate = ?, cover_image = ?, status = ?';
            $updateValues = [$form['location_id'], $form['start_location'], $form['title'], trip_timeline_mysql_datetime($departure), trip_timeline_mysql_datetime($return), $form['days_count'], $form['participant_count'], $form['budget'], $cover_image, $status];
            $updateFields .= ', description = ?';
            $updateValues[] = $form['description'];
            if ($coverMeta !== null && trip_cover_metadata_supported($conn)) {
                $updateFields .= ', cover_source = ?, cover_source_url = ?, cover_attribution = ?, cover_license = ?, cover_license_url = ?, cover_selected_at = ?';
                array_push($updateValues, $coverMeta['source'], $coverMeta['source_url'], $coverMeta['attribution'], $coverMeta['license'], $coverMeta['license_url'], date('Y-m-d H:i:s'));
            }
            $updateValues[] = $trip_id;
            $updateValues[] = $user_id;
            $stmtUpdate = $conn->prepare("UPDATE trips SET {$updateFields} WHERE id = ? AND user_id = ?");
            $stmtUpdate->execute($updateValues);

            $stmtExistingDays = $conn->prepare("SELECT id, day_number, note FROM trip_days WHERE trip_id = ? ORDER BY day_number FOR UPDATE");
            $stmtExistingDays->execute([$trip_id]);
            $existingDaysByNumber = [];
            $lockedDays = $stmtExistingDays->fetchAll(PDO::FETCH_ASSOC);
            $removedDayErrors = trip_timeline_removed_day_errors($lockedDays, $form['days_count'], $_POST);
            if ($removedDayErrors) throw new RuntimeException(implode(' ', $removedDayErrors));
            foreach ($lockedDays as $row) $existingDaysByNumber[(int) $row['day_number']] = (int) $row['id'];

            $stmtAllActivityIds = $conn->prepare("SELECT a.id FROM activities a JOIN trip_days d ON d.id = a.trip_day_id WHERE d.trip_id = ? FOR UPDATE");
            $stmtAllActivityIds->execute([$trip_id]);
            $existingIds = array_map('intval', $stmtAllActivityIds->fetchAll(PDO::FETCH_COLUMN));
            $unknownDeletedIds = array_diff($deletedActivityIds, $existingIds);
            $unknownSubmittedIds = array_diff($submittedActivityIds, $existingIds);
            if ($unknownDeletedIds || $unknownSubmittedIds) {
                throw new RuntimeException('Hoạt động không thuộc lịch trình này.');
            }
            if (array_diff($existingIds, $submittedActivityIds, $deletedActivityIds)) {
                throw new RuntimeException('Dữ liệu Timeline bị thiếu hoạt động cũ. Vui lòng tải lại trang trước khi lưu để tránh cập nhật sai ngày hoặc nhân đôi hoạt động.');
            }
            if ($deletedActivityIds) {
                $placeholders = implode(',', array_fill(0, count($deletedActivityIds), '?'));
                $stmtDeleteActivities = $conn->prepare("DELETE FROM activities WHERE id IN ($placeholders)");
                $stmtDeleteActivities->execute($deletedActivityIds);
            }

            if ($existingDaysByNumber && max(array_keys($existingDaysByNumber)) > $form['days_count']) {
                $stmtRemovedActivities = $conn->prepare("SELECT COUNT(*) FROM activities a JOIN trip_days d ON d.id = a.trip_day_id WHERE d.trip_id = ? AND d.day_number > ?");
                $stmtRemovedActivities->execute([$trip_id, $form['days_count']]);
                if ((int) $stmtRemovedActivities->fetchColumn() > 0) throw new RuntimeException('Không thể giảm số ngày khi các ngày bị bỏ vẫn còn hoạt động.');
                $stmtDeleteDays = $conn->prepare("DELETE FROM trip_days WHERE trip_id = ? AND day_number > ?");
                $stmtDeleteDays->execute([$trip_id, $form['days_count']]);
            }

            $stmtInsertDay = $conn->prepare("INSERT INTO trip_days (trip_id, day_number, day_date, title, note) VALUES (?, ?, ?, ?, ?)");
            $stmtUpdateDay = $conn->prepare("UPDATE trip_days SET day_date = ?, title = ?, note = ? WHERE id = ? AND trip_id = ?");
            $dayIds = [];
            for ($dayNumber = 1; $dayNumber <= $form['days_count']; $dayNumber++) {
                // Không thay đổi tiêu đề cũ nếu form không gửi trường này.
                // Tiêu đề mới để trống được lưu NULL, không dùng nội dung mẫu.
                $dayTitle = array_key_exists('day_title_' . $dayNumber, $_POST)
                    ? trim((string) $_POST['day_title_' . $dayNumber])
                    : ($existingDayTitles['day_' . $dayNumber] ?? null);
                $dayTitle = $dayTitle === '' ? null : $dayTitle;
                $dayNote = trim((string) ($_POST['day_desc_' . $dayNumber] ?? ($existingDayNotes['day_' . $dayNumber] ?? '')));
                if (isset($existingDaysByNumber[$dayNumber])) {
                    $dayIds[$dayNumber] = $existingDaysByNumber[$dayNumber];
                    $stmtUpdateDay->execute([trip_timeline_day_date($departure, $dayNumber), $dayTitle, $dayNote, $dayIds[$dayNumber], $trip_id]);
                } else {
                    $stmtInsertDay->execute([$trip_id, $dayNumber, trip_timeline_day_date($departure, $dayNumber), $dayTitle, $dayNote]);
                    $dayIds[$dayNumber] = (int) $conn->lastInsertId();
                }
            }

            $stmtUpdateActivity = $conn->prepare("UPDATE activities SET trip_day_id = ?, location_catalog_id = ?, start_at = ?, end_at = ?, sort_order = ?, title = ?, location_name = ?, description = ?, notes = ?, menu_suggestions = ?, nearby_amenities = ?, cost = ?, cost_scope = ?, category = ?, latitude = ?, longitude = ?, image = ?, location_source = ?, location_source_ref = ?, location_verified_at = ? WHERE id = ?");
            $stmtInsertActivity = $conn->prepare("INSERT INTO activities (trip_day_id, location_catalog_id, start_at, end_at, sort_order, title, location_name, description, notes, menu_suggestions, nearby_amenities, cost, cost_scope, category, latitude, longitude, image, location_source, location_source_ref, location_verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($timeline['data'] as $dayKey => $dayActivities) {
                $dayNumber = (int) str_replace('day_', '', $dayKey);
                foreach ($dayActivities as $activity) {
                    $values = [$dayIds[$dayNumber], $activity['location_catalog_id'], $activity['start_at'], $activity['end_at'], $activity['sort_order'], $activity['title'], $activity['location_name'], $activity['description'], $activity['notes'], $activity['menu_suggestions'], $activity['nearby_amenities'], $activity['cost'], $activity['cost_scope'], $activity['category'], $activity['latitude'], $activity['longitude'], $activity['image'], $activity['location_source'], $activity['location_source_ref']];
                    if ($activity['id'] > 0 && in_array($activity['id'], $existingIds, true)) { $stmtUpdateActivity->execute(array_merge($values, [$activity['location_verified_at'], $activity['id']])); }
                    else { $stmtInsertActivity->execute(array_merge($values, [$activity['location_verified_at']])); }
                }
            }

            trip_metadata_save_tags($conn, $trip_id, $selectedTagIds);
            $conn->commit();

            // Cập nhật xong thì chuyển thẳng sang trang chi tiết chuyến đi
            header("Location: trip-detail.php?id=" . $trip_id);
            exit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('[TripConnect edit-trip] update failed: ' . $e->getMessage());
            $error_message = $e instanceof RuntimeException && !($e instanceof PDOException)
                ? $e->getMessage() : 'Lỗi khi cập nhật lịch trình. Vui lòng thử lại.';
        }
    } elseif (empty($error_message)) {
        $error_message = implode(' ', array_unique($validationErrors));
    }
    }
}

// Lưu thất bại phải trả lại dữ liệu đang sửa, thay vì tải đè bằng bản cũ trong DB.
if ($error_message !== '' && !empty($hasSubmittedTimeline)) {
    $existingActivities = $activitiesDecoded;
    for ($dayNumber = 1; $dayNumber <= 15; $dayNumber++) {
        if (isset($_POST['day_desc_' . $dayNumber]) && is_string($_POST['day_desc_' . $dayNumber])) {
            $existingDayNotes['day_' . $dayNumber] = $_POST['day_desc_' . $dayNumber];
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title data-i18n-ui>Chỉnh sửa lịch trình | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css?v=4" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-xl sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php"><span data-i18n-ui>Trang chủ</span></a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php"><span data-i18n-ui>Khám phá</span></a></li>
    <li class="nav-item"><a class="nav-link" href="profile.php"><span data-i18n-ui>Hồ sơ</span></a></li><?php trip_social_nav(); ?>
   </ul>
   <div class="d-flex gap-2 align-items-center">
    <span class="text-secondary small"><span data-i18n-ui>Xin chào, </span><strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
    <form method="post" action="logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill"><span data-i18n-ui>Đăng xuất</span></button></form>
   </div>
  </div>
 </div>
</nav>

<main class="container py-4">
 <div class="page-header">
     <div class="small-label"><span data-i18n-ui>Trip Builder</span></div>
     <h1 class="section-title"><span data-i18n-ui>Chỉnh sửa lịch trình</span></h1>
     <p class="text-muted"><span data-i18n-ui>Cập nhật thông tin chi tiết và timeline cho chuyến đi của bạn.</span></p>
 </div>

 <?php if (!empty($success_message)): ?>
     <div class="alert alert-success" role="alert"><i class="bi bi-check-circle"></i> <?php echo $success_message; ?></div>
 <?php endif; ?>

 <?php if (!empty($error_message)): ?>
     <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
 <?php endif; ?>
 <?php if (!empty($warning_message)): ?><div class="alert alert-warning" role="alert"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($warning_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

<form method="POST" action="edit-trip.php?id=<?php echo $trip_id; ?>" enctype="multipart/form-data" id="tripForm"> <input type="hidden" name="activities_json" id="activitiesJson"><input type="hidden" name="deleted_activity_ids" id="deletedActivityIds" value="[]"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cover_selected_url" id="coverSelectedUrl" value="<?php echo htmlspecialchars($selectedCover['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cover_selected_source" id="coverSelectedSource" value="<?php echo htmlspecialchars($selectedCover['source'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cover_selected_source_url" id="coverSelectedSourceUrl" value="<?php echo htmlspecialchars($selectedCover['source_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cover_selected_attribution" id="coverSelectedAttribution" value="<?php echo htmlspecialchars($selectedCover['attribution'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cover_selected_license" id="coverSelectedLicense" value="<?php echo htmlspecialchars($selectedCover['license'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cover_selected_license_url" id="coverSelectedLicenseUrl" value="<?php echo htmlspecialchars($selectedCover['license_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <div id="dayDescInputsContainer"></div>

 <div class="row g-4">
  <div class="col-lg-8">
   <!-- 1. Thông tin tổng quan -->
   <div class="card p-4 mb-4">
       <h5><span data-i18n-ui>1. Thông tin tổng quan</span></h5>
       <div class="row g-3 mt-1">
        <div class="col-md-8">
            <label class="form-label" for="tripName"><span data-i18n-ui>Tên chuyến đi</span></label>
            <input id="tripName" name="tripName" required maxlength="150" class="form-control" value="<?php echo htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
         <div class="col-md-4"><label class="form-label" for="startLocation"><span data-i18n-ui>Điểm xuất phát</span></label><input id="startLocation" name="startLocation" maxlength="150" class="form-control" value="<?php echo htmlspecialchars($form['start_location'], ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label" for="departureAt"><span data-i18n-ui>Khởi hành</span></label><input id="departureAt" name="departureAt" type="datetime-local" class="form-control" value="<?php echo htmlspecialchars($form['departure_at'], ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4"><label class="form-label" for="returnAt"><span data-i18n-ui>Trở về</span></label><input id="returnAt" name="returnAt" type="datetime-local" class="form-control" value="<?php echo htmlspecialchars($form['return_at'], ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-4">
            <label class="form-label" for="tripLocation"><span data-i18n-ui>Địa điểm</span></label>
            <select id="tripLocation" name="tripLocation" class="form-select" required>
                <option value="" data-i18n-ui>-- Chọn địa điểm --</option>
                <?php foreach ($locationsList as$loc): ?>
                    <option value="<?php echo (int) $loc['id']; ?>" <?php echo ((int) $loc['id'] === (int) $form['location_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripDays"><span data-i18n-ui>Số ngày</span></label>
            <input id="tripDays" name="tripDays" type="number" min="1" max="15" class="form-control" value="<?php echo (int) $form['days_count']; ?>" required>
            <div id="tripDateStatus" class="form-text" role="status" aria-live="polite"><span data-i18n-ui>Số ngày được tự tính khi có đủ ngày khởi hành và trở về.</span></div>
        </div>
        <div class="col-md-4"><label class="form-label" for="participantCount"><span data-i18n-ui>Số người tham gia</span></label><input id="participantCount" name="participantCount" type="number" min="1" max="65535" class="form-control" value="<?php echo $form['participant_count'] !== '' ? (int) $form['participant_count'] : ''; ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripBudget"><span data-i18n-ui>Ngân sách dự kiến</span></label>
            <input id="tripBudget" name="tripBudget" type="number" min="0" step="0.01" class="form-control" value="<?php echo htmlspecialchars((string) $form['budget'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripStatus"><span data-i18n-ui>Trạng thái</span></label>
            <select id="tripStatus" name="tripStatus" class="form-select">
                <option value="draft" <?php echo (($status ?? $trip['status']) === 'draft') ? 'selected' : ''; ?> data-i18n-ui>Draft</option>
                <option value="published" <?php echo (($status ?? $trip['status']) === 'published') ? 'selected' : ''; ?> data-i18n-ui>Published</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="tripDescription"><span data-i18n-ui>Mô tả chuyến đi</span></label>
            <textarea id="tripDescription" name="tripDescription" class="form-control" rows="3"><?php echo htmlspecialchars($form['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-12">
            <div class="trip-tag-panel" role="group" aria-labelledby="tripTagsLabel">
                <div id="tripTagsLabel" class="trip-tag-heading"><span data-i18n-ui>Chủ đề / tags</span></div>
                <input type="hidden" name="trip_tags_present" value="1">
                <div class="trip-tag-picker">
                <?php foreach ($availableTags as $tag): if ($tag['status'] !== 'active' && !in_array((int) $tag['id'], $existingTagIds, true)) continue; ?>
                    <span class="trip-tag-item"><input type="checkbox" class="interest-option visually-hidden" name="tripTags[]" id="tripTag<?php echo (int) $tag['id']; ?>" value="<?php echo (int) $tag['id']; ?>" <?php echo in_array((int) $tag['id'], $selectedTagIds, true) ? 'checked' : ''; ?>><label class="interest-choice badge rounded-pill" for="tripTag<?php echo (int) $tag['id']; ?>"><?php echo htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8'); ?><span data-i18n-ui><?php echo $tag['status'] !== 'active' ? ' (đã ẩn; có thể bỏ chọn)' : ''; ?></span></label></span>
                <?php endforeach; ?>
                </div>
                <?php if (!$availableTags): ?><div class="form-text"><span data-i18n-ui>Chưa có chủ đề để chọn.</span></div><?php endif; ?>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="coverInput"><span data-i18n-ui>Ảnh bìa</span></label>
            <input id="coverInput" name="coverInput" type="file" accept="image/*" class="form-control">
            <div class="border rounded p-3 mt-2">
                <div class="small fw-semibold mb-2"><span data-i18n-ui>Gợi ý ảnh Wikimedia Commons</span></div>
                <div class="input-group input-group-sm">
                    <input id="coverImageQuery" type="text" class="form-control" placeholder="Tên địa điểm hoặc khu vực" data-i18n-attrs>
                    <button type="button" id="btnSearchCoverImage" class="btn btn-outline-secondary"><span data-i18n-ui>Tìm ảnh</span></button>
                </div>
                <div id="coverImageStatus" class="form-text"></div>
                <div id="coverImageResults" class="row g-2 mt-1"></div>
                <div class="form-text"><span data-i18n-ui>Chỉ hiển thị ảnh có thông tin tác giả và giấy phép phù hợp.</span></div>
            </div>
        </div>
       </div>
   </div>

   <!-- 2. Hoạt động trong ngày -->
   <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><span data-i18n-ui>2. Hoạt động trong ngày</span></h5>
        <div class="d-flex align-items-center gap-2">
         <button type="button" id="dayMinus" class="btn btn-sm btn-light"><i class="bi bi-dash"></i></button>
         <span class="badge bg-success px-3 py-2 fs-6" id="dayBadge"><span data-i18n-ui>Ngày 1</span></span>
         <button type="button" id="dayPlus" class="btn btn-sm btn-light"><i class="bi bi-plus"></i></button>
        </div>
    </div>

    <div class="mt-3">
        <label class="form-label fw-semibold" id="dayDescLabel"><span data-i18n-ui>Mô tả tổng quan Day 1</span></label>
        <input type="text" id="currentDayDescInput" class="form-control" placeholder="VD: Ngày đầu tiên check-in..." data-i18n-attrs>
    </div>

    <div class="row g-3 mt-2">
     <div class="col-md-3"><label class="form-label" for="activityStartTime"><span data-i18n-ui>Bắt đầu</span></label><input id="activityStartTime" type="time" class="form-control" step="60"></div>
     <div class="col-md-3"><label class="form-label" for="activityEndTime"><span data-i18n-ui>Kết thúc</span></label><input id="activityEndTime" type="time" class="form-control" step="60"></div>
     <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input id="activityOvernight" type="checkbox" class="form-check-input"><label class="form-check-label" for="activityOvernight"><span data-i18n-ui>Kết thúc vào ngày hôm sau</span></label><div id="overnightHint" class="form-text"><span data-i18n-ui>Sang ngày kế tiếp</span></div></div></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Tên hoạt động</span></label><input id="activityName" maxlength="150" class="form-control"></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Địa điểm</span></label><input id="activityPlace" maxlength="150" class="form-control"><input type="hidden" id="activityLocationCatalogId"><div class="d-flex gap-1 mt-1"><button type="button" id="btnSearchCatalog" class="btn btn-sm btn-outline-secondary"><span data-i18n-ui>Tìm trong TripConnect</span></button><button type="button" id="btnSearchExternal" class="btn btn-sm btn-outline-secondary"><span data-i18n-ui>Tìm địa điểm bên ngoài</span></button></div><div id="locationSearchStatus" class="form-text"></div><div id="activityLocationDetails" class="form-text" aria-live="polite"></div><div id="locationSearchResults" class="list-group mt-2 small"></div></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Loại hoạt động</span></label><select id="activityCategory" class="form-select"><option value="sightseeing" data-i18n-ui>Tham quan</option><option value="food" data-i18n-ui>Ăn uống</option><option value="transport" data-i18n-ui>Di chuyển</option><option value="lodging" data-i18n-ui>Lưu trú</option><option value="rest" data-i18n-ui>Nghỉ ngơi</option><option value="free_time" data-i18n-ui>Thời gian tự do</option><option value="other" data-i18n-ui>Khác</option></select></div>
     <div class="col-md-3"><label class="form-label" for="activityCost"><span data-i18n-ui>Chi phí dự kiến</span></label><input id="activityCost" type="number" min="0" step="0.01" class="form-control"><div class="form-text"><span data-i18n-ui>Hoạt động miễn phí: nhập 0.</span></div></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Cách tính</span></label><select id="activityCostScope" class="form-select"><option value="fixed" data-i18n-ui>Cả nhóm / cố định</option><option value="" disabled data-i18n-ui>Chưa xác định</option><option value="per_person" data-i18n-ui>Theo người</option></select></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Mô tả</span></label><input id="activityDescription" maxlength="10000" class="form-control"></div>
     <div class="col-md-4"><label class="form-label"><span data-i18n-ui>Ghi chú</span></label><input id="activityNotes" maxlength="10000" class="form-control"></div>
     <div class="col-md-4"><label class="form-label"><span data-i18n-ui>Menu / món gợi ý</span></label><input id="activityMenu" maxlength="10000" class="form-control"></div>
     <div class="col-md-4"><label class="form-label"><span data-i18n-ui>Tiện ích gần đó</span></label><input id="activityAmenities" maxlength="10000" class="form-control"></div>
     <div class="col-12 d-flex align-items-end"><button type="button" id="btnAddActivity" class="btn btn-success px-4"><i class="bi bi-plus-lg"></i><span data-i18n-ui> Thêm hoạt động</span></button></div>
    </div>
    <hr class="my-4">
    <div id="activityList" class="timeline"></div>
    <div class="border-top mt-3 pt-3 text-end fw-semibold"><span data-i18n-ui>Tổng ngày: </span><span id="currentDayTotal"><span data-i18n-ui>0 đ</span></span></div>
   </div>
  </div>

  <div class="col-lg-4">
   <div class="sticky-side">
    <div class="card p-4">
        <h6><span data-i18n-ui>Ảnh bìa xem trước</span></h6>
        <div class="empty-preview mt-2" id="previewPlaceholder">
            <?php if (!empty($trip['cover_image'])): ?>
                <img src="<?php echo htmlspecialchars($trip['cover_image']); ?>" class="w-100 rounded" style="max-height:160px; object-fit:cover;" alt="Ảnh bìa" data-i18n-attrs>
            <?php else: ?>
                <i class="bi bi-image fs-1"></i><p class="mb-0 mt-2"><span data-i18n-ui>Chưa chọn ảnh</span></p>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" name="save_action" value="draft" class="btn btn-outline-primary flex-fill"><i class="bi bi-save"></i><span data-i18n-ui> Lưu bản nháp</span></button>
            <button type="submit" name="save_action" value="published" class="btn btn-primary flex-fill"><i class="bi bi-globe2"></i><span data-i18n-ui> Xuất bản lịch trình</span></button>
        </div>
        <a href="profile.php" class="btn btn-outline-secondary w-100 mt-2"><span data-i18n-ui>Quay lại hồ sơ</span></a>
    </div>
    <div class="card p-4 mt-4" id="budgetSummary">
        <h6><span data-i18n-ui>Tóm tắt ngân sách</span></h6>
        <div class="d-flex justify-content-between small"><span><span data-i18n-ui>Ngân sách mục tiêu</span></span><strong id="targetBudgetValue"><span data-i18n-ui>0 đ</span></strong></div>
        <div class="d-flex justify-content-between small"><span><span data-i18n-ui>Tổng từ hoạt động</span></span><strong id="tripCalculatedTotal"><span data-i18n-ui>0 đ</span></strong></div>
        <div class="d-flex justify-content-between small"><span><span data-i18n-ui>Trung bình mỗi người</span></span><strong id="tripAveragePerPerson"><span data-i18n-ui>0 đ</span></strong></div>
        <div id="budgetComparison" class="small mt-2" role="status" aria-live="polite"></div>
        <div class="border-top mt-3 pt-3"><h6 class="small fw-semibold"><span data-i18n-ui>Theo danh mục</span></h6><div id="budgetByCategory"></div><h6 class="small fw-semibold mt-3"><span data-i18n-ui>Theo ngày</span></h6><div id="budgetByDay"></div></div>
        <div class="small text-muted mt-1"><span data-i18n-ui>Chi phí hoạt động là dự kiến cho cả nhóm; chưa phải khoản chi thực tế.</span></div>
    </div>
    <div class="card p-4 mt-4">
        <h6><span data-i18n-ui>Checklist</span></h6>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label"><span data-i18n-ui>Thông tin chuyến đi</span></label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label"><span data-i18n-ui>Timeline hoạt động</span></label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label"><span data-i18n-ui>Ngân sách</span></label></div>
    </div>
   </div>
  </div>
 </div>
 </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
if (false) {
let currentDay = 1;
let maxDays = parseInt(document.getElementById('tripDays').value) || 3;
let tripActivities = <?php echo json_encode($existingActivities); ?>;
let dayDescriptions = <?php echo json_encode($existingDayNotes); ?>;

const tripDaysInput = document.getElementById('tripDays');
const dayBadge = document.getElementById('dayBadge');
const dayDescLabel = document.getElementById('dayDescLabel');
const currentDayDescInput = document.getElementById('currentDayDescInput');

// Khởi tạo hiển thị
updateDayView();

tripDaysInput.addEventListener('input', function() {
    maxDays = parseInt(this.value) || 1;
    if (currentDay > maxDays) {
        currentDay = maxDays;
        updateDayView();
    }
});

document.getElementById('dayPlus').addEventListener('click', function() {
    if (currentDay < maxDays) {
        saveCurrentDayDesc();
        currentDay++;
        updateDayView();
    }
});

document.getElementById('dayMinus').addEventListener('click', function() {
    if (currentDay > 1) {
        saveCurrentDayDesc();
        currentDay--;
        updateDayView();
    }
});

function saveCurrentDayDesc() {
    dayDescriptions['day_' + currentDay] = currentDayDescInput.value;
}

function updateDayView() {
    dayBadge.innerText = 'Day ' + currentDay;
    dayDescLabel.innerText = 'Mô tả tổng quan Day ' + currentDay;
    currentDayDescInput.value = dayDescriptions['day_' + currentDay] || '';
    renderActivities();
}

currentDayDescInput.addEventListener('input', function() {
    dayDescriptions['day_' + currentDay] = this.value;
});

// Xử lý xem trước ảnh khi chọn file mới
document.getElementById('coverInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewPlaceholder').innerHTML = `<img src="${e.target.result}" class="w-100 rounded" style="max-height:160px; object-fit:cover;" alt="Ảnh xem trước">`;
        }
        reader.readAsDataURL(file);
    }
});

document.getElementById('btnAddActivity').addEventListener('click', function() {
    let time = document.getElementById('activityTime').value;
    let title = document.getElementById('activityName').value;
    let location = document.getElementById('activityPlace').value;
    let cost = document.getElementById('activityCost').value;
    let category = document.getElementById('activityCategory').value;

    if (!title) {
        alert('Vui lòng nhập tên hoạt động!');
        return;
    }

    if (!tripActivities['day_' + currentDay]) {
        tripActivities['day_' + currentDay] = [];
    }

    tripActivities['day_' + currentDay].push({ time, title, location, cost, category });

    document.getElementById('activityTime').value = '';
    document.getElementById('activityName').value = '';
    document.getElementById('activityPlace').value = '';
    document.getElementById('activityCost').value = '';

    renderActivities();
});

function renderActivities() {
    let listEl = document.getElementById('activityList');
    let activities = tripActivities['day_' + currentDay] || [];

    if (activities.length === 0) {
        listEl.innerHTML = '<p class="text-muted fst-italic">Chưa có hoạt động nào được thêm cho ngày này.</p>';
        return;
    }

    let html = '';
    activities.forEach((act, index) => {
        html += `<div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <div>
                <strong>${act.time || '--:--'}</strong> - <span class="fw-semibold">${act.title}</span> 
                <span class="text-muted">(${act.location || 'N/A'})</span>
                <br><span class="badge bg-secondary">${act.category}</span> <span class="text-success fw-bold">${Number(act.cost || 0).toLocaleString()}đ</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeActivity(${index})"><i class="bi bi-trash"></i></button>
        </div>`;
    });
    listEl.innerHTML = html;
}

function removeActivity(index) {
    tripActivities['day_' + currentDay].splice(index, 1);
    renderActivities();
}

document.getElementById('tripForm').addEventListener('submit', function(e) {
    saveCurrentDayDesc();
    document.getElementById('activitiesJson').value = JSON.stringify(tripActivities);

    let container = document.getElementById('dayDescInputsContainer');
    container.innerHTML = '';
    for (let d = 1; d <= maxDays; d++) {
        let desc = dayDescriptions['day_' + d] || '';
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'day_desc_' + d;
        input.value = desc;
        container.appendChild(input);
    }
});
}
</script>
<script>
window.tripTimelineInitialActivities = <?php echo json_encode($existingActivities, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
window.tripTimelineInitialDescriptions = <?php echo json_encode($existingDayNotes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
window.tripTimelineDeletedActivityIds = <?php echo json_encode($deletedActivityIds ?? []); ?>;
</script>
<script src="assets/pages/trip-timeline-form.js?v=8"></script>
<script src="assets/i18n.js?v=17"></script>
</body>
</html>
