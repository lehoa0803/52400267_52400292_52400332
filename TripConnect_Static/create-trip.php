<?php
session_start();
require_once 'config/database.php';
require_once 'config/trip_social.php';
require_once 'config/trip_timeline.php';
require_once 'config/trip_location_catalog.php';
require_once 'config/trip_metadata.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success_message = '';
trip_require_active_account($conn);
$error_message = '';
$error_message_key = '';
$csrf_token = $_SESSION['trip_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['trip_csrf_token'] = $csrf_token;
$form = [
    'title' => '',
    'description' => '',
    'location_id' => 0,
    'start_location' => '',
    'departure_at' => '',
    'return_at' => '',
    'days_count' => 3,
    'participant_count' => '',
    'budget' => '',
];
$selectedTagIds = [];
$tagsLoadError = false;
try {
    $availableTags = trip_metadata_load_tags($conn);
} catch (PDOException $e) {
    $availableTags = [];
    $tagsLoadError = true;
    error_log('[TripConnect create-trip] tags unavailable: ' . $e->getMessage());
}

// Lấy toàn bộ danh sách địa điểm từ cơ sở dữ liệu để đổ ra menu chọn
try {
    $stmtLocations = $conn->query("SELECT id, name FROM locations ORDER BY name ASC");
    $locationsList = $stmtLocations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $locationsList = [];
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
        $metadata = trip_metadata_normalize($_POST, $availableTags);
        $form['description'] = $metadata['description'];
        $selectedTagIds = $metadata['tag_ids'];
        $validationErrors = array_merge($validationErrors, $metadata['errors']);
        if ($tagsLoadError) $validationErrors[] = 'Không thể tải danh sách chủ đề. Vui lòng thử lại sau.';

        if (!in_array($status, ['draft', 'published'], true)) {
            $validationErrors[] = 'Trạng thái lịch trình không hợp lệ.';
        }
        if ($form['title'] === '' || mb_strlen($form['title']) > 150) {
            $validationErrors[] = 'Vui lòng nhập tên chuyến đi (tối đa 150 ký tự).';
        }
        if ($form['location_id'] <= 0) {
            $validationErrors[] = 'Vui lòng chọn địa điểm chính của chuyến đi.';
        }
        if ($form['participant_count'] < 1 || $form['participant_count'] > 65535) {
            $validationErrors[] = 'Số người tham gia phải từ 1 đến 65535.';
        }
        if (!is_numeric($form['budget']) || !is_finite((float) $form['budget']) || (float) $form['budget'] < 0 || (float) $form['budget'] > 9999999999.99) {
            $validationErrors[] = 'Ngân sách phải từ 0 đến 9.999.999.999,99 đ.';
        }
        if (($form['departure_at'] !== '' && !$departure) || ($form['return_at'] !== '' && !$return)) {
            $validationErrors[] = 'Ngày giờ khởi hành hoặc trở về không hợp lệ.';
        }
        if (($departure && !$return) || (!$departure && $return)) {
            $validationErrors[] = 'Cần nhập đủ ngày giờ khởi hành và trở về.';
        }
        if ($departure && $return && $return <= $departure) {
            $validationErrors[] = 'Ngày giờ trở về phải sau ngày giờ khởi hành.';
        }
        if ($departure && $return && $return > $departure) {
            $form['days_count'] = $departure->setTime(0, 0)->diff($return->setTime(0, 0))->days + 1;
        }
        if ($form['days_count'] < 1 || $form['days_count'] > 15) {
            $validationErrors[] = 'Số ngày phải nằm trong khoảng từ 1 đến 15.';
        }
        if ($status === 'published' && (!$departure || !$return)) {
            $validationErrors[] = 'Lịch trình phải có đủ thời gian chuyến đi trước khi xuất bản.';
        }

        $activitiesJson = $_POST['activities_json'] ?? null;
        $validationErrors = array_merge($validationErrors, trip_timeline_removed_day_errors([], $form['days_count'], $_POST));
        $activitiesDecoded = is_string($activitiesJson) ? json_decode($activitiesJson, true) : null;
        if (!is_string($activitiesJson) || json_last_error() !== JSON_ERROR_NONE || !is_array($activitiesDecoded)) {
            $validationErrors[] = 'Dữ liệu timeline không hợp lệ hoặc chưa được tạo từ biểu mẫu.';
            $activitiesDecoded = [];
        }
        $catalogRecords = trip_location_catalog_load($conn, $activitiesDecoded);
        $submittedCatalogIds = trip_location_catalog_ids($activitiesDecoded);
        $unknownCatalogIds = array_diff($submittedCatalogIds, array_keys($catalogRecords));
        if ($unknownCatalogIds) {
            $validationErrors[] = 'Một hoặc nhiều địa điểm trong catalog không còn tồn tại.';
        }
        $activitiesDecoded = trip_location_catalog_apply($activitiesDecoded, $catalogRecords);
        $timeline = trip_timeline_normalize_payload($activitiesDecoded, $form['days_count'], $departure, $return, $status, [], $catalogRecords);
        $validationErrors = array_merge($validationErrors, $timeline['errors']);
        $warning_message = $status === 'draft' && !empty($timeline['warnings']) ? implode(' ', $timeline['warnings']) : '';

        $user_id = (int) $_SESSION['user_id'];
        $uploadedCoverPath = null;
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

        // If the user did not upload or explicitly choose an image, make one
        // conservative, data-driven Wikimedia attempt. Ambiguous/no-result
        // searches are deliberately left as the neutral placeholder.
        if (!$hasCoverUpload && $selectedCover === null && empty($validationErrors) && trip_cover_metadata_supported($conn)) {
            try {
                $autoCover = trip_auto_cover_for_trip($conn, $form['location_id'], $timeline['data'], $catalogRecords, $coverImageLimit);
                if ($autoCover['selected'] !== null) {
                    $selectedCover = $autoCover['selected'];
                }
            } catch (Throwable $exception) {
                error_log('[TripConnect create-trip] automatic cover resolution skipped: ' . $exception->getMessage());
            }
        }

    if (empty($validationErrors)) {
        try {
            $conn->beginTransaction();

            $stmtLocName = $conn->prepare("SELECT name FROM locations WHERE id = ?");
            $stmtLocName->execute([$form['location_id']]);
            $locData = $stmtLocName->fetch(PDO::FETCH_ASSOC);
            if (!$locData) {
                throw new RuntimeException('Địa điểm không tồn tại.');
            }
            $location_name = $locData['name'];

            $cover_image = $selectedCover['image'] ?? null;
            $coverMeta = $selectedCover;
            if (isset($_FILES['coverInput']) && $_FILES['coverInput']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['coverInput'];
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
                if ($file['error'] !== UPLOAD_ERR_OK || !in_array($ext, $allowedExt, true) || @getimagesize($file['tmp_name']) === false) {
                    $validationErrors[] = 'Ảnh bìa không hợp lệ. Chỉ chấp nhận jpg, png, webp hoặc gif.';
                } else {
                    $uploadFileDir = __DIR__ . '/assets/uploads/';
                    if (!is_dir($uploadFileDir) && !mkdir($uploadFileDir, 0755, true)) {
                        $validationErrors[] = 'Không thể tạo thư mục lưu ảnh bìa.';
                    } elseif (!is_writable($uploadFileDir)) {
                        $validationErrors[] = 'Thư mục lưu ảnh bìa không có quyền ghi.';
                    } else {
                        $newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $uploadFileDir . $newFileName)) {
                            $cover_image = 'assets/uploads/' . $newFileName;
                            $coverMeta = [
                                'source' => 'user_upload', 'source_url' => null, 'attribution' => null,
                                'license' => null, 'license_url' => null,
                            ];
                            $uploadedCoverPath = $uploadFileDir . $newFileName;
                        } else {
                            $validationErrors[] = 'Không thể lưu ảnh bìa lên máy chủ.';
                        }
                    }
                }
            }
            if (!empty($validationErrors)) {
                throw new RuntimeException('Ảnh bìa không hợp lệ hoặc không thể lưu.');
            }
            $tripColumns = "user_id, location_id, start_location, title, description, departure_at, return_at, days_count, participant_count, budget_estimate, cover_image, status";
            $tripValues = [
                $user_id, $form['location_id'], $form['start_location'], $form['title'],
                $form['description'],
                trip_timeline_mysql_datetime($departure), trip_timeline_mysql_datetime($return),
                $form['days_count'], $form['participant_count'], $form['budget'], $cover_image, $status
            ];
            if ($coverMeta !== null && trip_cover_metadata_supported($conn)) {
                $tripColumns .= ', cover_source, cover_source_url, cover_attribution, cover_license, cover_license_url, cover_selected_at';
                array_push($tripValues, $coverMeta['source'], $coverMeta['source_url'], $coverMeta['attribution'], $coverMeta['license'], $coverMeta['license_url'], date('Y-m-d H:i:s'));
            }
            $tripColumns .= ', view_count, created_at';
            $placeholders = implode(', ', array_fill(0, count($tripValues), '?'));
            $stmtTrip = $conn->prepare("INSERT INTO trips ({$tripColumns}) VALUES ({$placeholders}, 0, NOW())");
            $stmtTrip->execute($tripValues);
            $trip_id = $conn->lastInsertId();

            $stmtDay = $conn->prepare("INSERT INTO trip_days (trip_id, day_number, day_date, title, note) VALUES (?, ?, ?, ?, ?)");
            $stmtAct = $conn->prepare("INSERT INTO activities (trip_day_id, location_catalog_id, start_at, end_at, sort_order, title, location_name, description, notes, menu_suggestions, nearby_amenities, cost, cost_scope, category, latitude, longitude, image, location_source, location_source_ref, location_verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            for ($dayNumber = 1; $dayNumber <= $form['days_count']; $dayNumber++) {
                // Tiêu đề ngày là tùy chọn; không tự gán nội dung mẫu vào dữ liệu mới.
                $dayTitle = trim((string) ($_POST['day_title_' . $dayNumber] ?? '')) ?: null;
                $dayNote = trim((string) ($_POST['day_desc_' . $dayNumber] ?? ''));
                $stmtDay->execute([$trip_id, $dayNumber, trip_timeline_day_date($departure, $dayNumber), $dayTitle, $dayNote]);
                $tripDayId = $conn->lastInsertId();

                foreach ($timeline['data']['day_' . $dayNumber] ?? [] as $activity) {
                    $stmtAct->execute([
                        $tripDayId, $activity['location_catalog_id'], $activity['start_at'], $activity['end_at'], $activity['sort_order'],
                        $activity['title'], $activity['location_name'], $activity['description'], $activity['notes'],
                        $activity['menu_suggestions'], $activity['nearby_amenities'], $activity['cost'], $activity['cost_scope'],
                        $activity['category'], $activity['latitude'], $activity['longitude'], $activity['image'],
                        $activity['location_source'], $activity['location_source_ref'], $activity['location_verified_at']
                    ]);
                }
            }

            trip_metadata_save_tags($conn, (int) $trip_id, $selectedTagIds);
            $conn->commit();
            $success_message = 'saved';
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            if ($uploadedCoverPath && is_file($uploadedCoverPath)) {
                @unlink($uploadedCoverPath);
            }
            error_log('[TripConnect create-trip] save failed: ' . $e->getMessage());
            $error_message = !empty($validationErrors)
                ? implode(' ', array_unique($validationErrors))
                : 'Lỗi khi lưu lịch trình. Vui lòng thử lại.';
        }
    } else {
        $error_message = implode(' ', array_unique($validationErrors));
    }
    }
}

$initialActivitiesForForm = is_array($activitiesDecoded ?? null) ? $activitiesDecoded : [];
$initialDescriptionsForForm = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedDays = max(1, min(15, (int) ($form['days_count'] ?? 1)));
    for ($dayNumber = 1; $dayNumber <= $postedDays; $dayNumber++) {
        $initialDescriptionsForForm['day_' . $dayNumber] = (string) ($_POST['day_desc_' . $dayNumber] ?? '');
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title data-i18n-ui>Tạo lịch trình | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css?v=4" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-xl sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng" data-i18n-attrs><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php" data-i18n="nav.home">Trang chủ</a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
    <li class="nav-item"><a class="nav-link" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
    <li class="nav-item"><a class="nav-link" href="profile.php" data-i18n="nav.profile">Hồ sơ</a></li><?php trip_social_nav(); ?>
   </ul>
   <div class="d-flex gap-2 align-items-center">
    <?php if (isset($_SESSION['user_id'])): ?>
        <span class="text-secondary small d-none d-md-inline"><span data-i18n-ui>Xin chào, </span><strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
        <form method="post" action="logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill"><span data-i18n-ui>Đăng xuất</span></button></form>
    <?php else: ?>
        <a class="btn btn-outline-secondary btn-sm px-3 rounded-pill" href="login.php" data-i18n="nav.login">Đăng nhập</a>
    <?php endif; ?>
    <div class="dropdown">
     <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span></button>
     <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
      <li><a class="dropdown-item py-2" href="#" data-lang-btn="vi"><span data-i18n-ui>🇻🇳 Tiếng Việt</span></a></li>
      <li><a class="dropdown-item py-2" href="#" data-lang-btn="en"><span data-i18n-ui>🇬🇧 English</span></a></li>
     </ul>
    </div>
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> <span data-i18n="nav.create_trip">Tạo lịch trình</span></a>
   </div>
  </div>
 </div>
</nav>

<main class="container py-4">
 <div class="page-header">
     <div class="small-label" data-i18n="create.page_label">Trip Builder</div>
     <h1 class="section-title" data-i18n="create.title">Tạo lịch trình mới</h1>
     <p class="text-muted" data-i18n="create.description">Nhập thông tin chuyến đi và thêm timeline hoạt động theo từng ngày.</p>
 </div>

 <?php if (!empty($success_message)): ?>
     <div class="alert alert-success" role="alert"><i class="bi bi-check-circle"></i> <span data-i18n="create.save_success">Đã lưu lịch trình và các hoạt động thành công!</span> <a href="explore.php" data-i18n="create.view_list">Xem danh sách</a></div>
 <?php endif; ?>

 <?php if (!empty($error_message)): ?>
     <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle"></i> <?php if ($error_message_key): ?><span data-i18n="<?php echo htmlspecialchars($error_message_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $error_message_key === 'create.validation_error' ? 'Vui lòng nhập tên chuyến đi và chọn địa điểm hợp lệ!' : 'Lỗi khi lưu lịch trình. Vui lòng thử lại.'; ?></span><?php else: ?><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></div>
 <?php endif; ?>
 <?php if (!empty($warning_message)): ?><div class="alert alert-warning" role="alert"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($warning_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

 <form method="POST" action="create-trip.php" enctype="multipart/form-data" id="tripForm">
 <!-- Input ẩn để chứa dữ liệu JSON các hoạt động -->
 <input type="hidden" name="activities_json" id="activitiesJson">
 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
 <input type="hidden" name="cover_selected_url" id="coverSelectedUrl" value="<?php echo htmlspecialchars($selectedCover['image'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <input type="hidden" name="cover_selected_source" id="coverSelectedSource" value="<?php echo htmlspecialchars($selectedCover['source'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <input type="hidden" name="cover_selected_source_url" id="coverSelectedSourceUrl" value="<?php echo htmlspecialchars($selectedCover['source_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <input type="hidden" name="cover_selected_attribution" id="coverSelectedAttribution" value="<?php echo htmlspecialchars($selectedCover['attribution'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <input type="hidden" name="cover_selected_license" id="coverSelectedLicense" value="<?php echo htmlspecialchars($selectedCover['license'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <input type="hidden" name="cover_selected_license_url" id="coverSelectedLicenseUrl" value="<?php echo htmlspecialchars($selectedCover['license_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
 <!-- Container chứa input ẩn mô tả từng ngày để truyền về PHP -->
 <div id="dayDescInputsContainer"></div>

 <div class="row g-4">
  <div class="col-lg-8">
   <!-- 1. Thông tin tổng quan -->
   <div class="card p-4 mb-4">
       <h5 data-i18n="create.overview">1. Thông tin tổng quan</h5>
       <div class="row g-3 mt-1">
        <div class="col-md-8">
            <label class="form-label" for="tripName" data-i18n="create.trip_name">Tên chuyến đi</label>
            <input id="tripName" name="tripName" required maxlength="150" class="form-control" value="<?php echo htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="VD: Hà Nội 4 ngày 3 đêm" data-i18n="create.trip_name_placeholder">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="startLocation"><span data-i18n-ui>Điểm xuất phát</span></label>
            <input id="startLocation" name="startLocation" maxlength="150" class="form-control" value="<?php echo htmlspecialchars($form['start_location'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="VD: TP.HCM" data-i18n-attrs>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="departureAt"><span data-i18n-ui>Khởi hành</span></label>
            <input id="departureAt" name="departureAt" type="datetime-local" class="form-control" value="<?php echo htmlspecialchars($form['departure_at'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="returnAt"><span data-i18n-ui>Trở về</span></label>
            <input id="returnAt" name="returnAt" type="datetime-local" class="form-control" value="<?php echo htmlspecialchars($form['return_at'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripLocation" data-i18n="create.location">Địa điểm</label>
            <select id="tripLocation" name="tripLocation" class="form-select" required>
                <option value="" data-i18n="create.choose_location">-- Chọn địa điểm --</option>
                <?php foreach ($locationsList as $loc): ?>
                    <option value="<?php echo (int) $loc['id']; ?>" <?php echo (int) $form['location_id'] === (int) $loc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripDays" data-i18n="create.days">Số ngày</label>
            <input id="tripDays" name="tripDays" type="number" min="1" max="15" class="form-control" value="<?php echo (int) $form['days_count']; ?>" required>
            <div id="tripDateStatus" class="form-text" role="status" aria-live="polite"><span data-i18n-ui>Số ngày được tự tính khi có đủ ngày khởi hành và trở về.</span></div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="participantCount"><span data-i18n-ui>Số người tham gia</span></label>
            <input id="participantCount" name="participantCount" type="number" min="1" max="65535" class="form-control" value="<?php echo $form['participant_count'] !== '' ? (int) $form['participant_count'] : ''; ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripBudget" data-i18n="create.budget">Ngân sách dự kiến</label>
            <input id="tripBudget" name="tripBudget" type="number" min="0" step="0.01" class="form-control" value="<?php echo htmlspecialchars((string) $form['budget'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="VD: 5000000" data-i18n="create.budget_placeholder">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripStatus" data-i18n="create.status">Trạng thái</label>
            <select id="tripStatus" name="tripStatus" class="form-select">
                <option value="draft" <?php echo (($status ?? 'draft') === 'draft') ? 'selected' : ''; ?> data-i18n="create.draft">Draft</option>
                <option value="published" <?php echo (($status ?? 'draft') === 'published') ? 'selected' : ''; ?> data-i18n="create.published">Published</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="tripDescription"><span data-i18n-ui>Mô tả chuyến đi</span></label>
            <textarea id="tripDescription" name="tripDescription" class="form-control" rows="3" placeholder="Giới thiệu chuyến đi và trải nghiệm bạn muốn chia sẻ" data-i18n-attrs><?php echo htmlspecialchars($form['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-12">
            <div class="trip-tag-panel" role="group" aria-labelledby="tripTagsLabel">
                <div id="tripTagsLabel" class="trip-tag-heading"><span data-i18n-ui>Chủ đề / tags</span></div>
                <input type="hidden" name="trip_tags_present" value="1">
                <div class="trip-tag-picker">
                <?php foreach ($availableTags as $tag): if ($tag['status'] !== 'active') continue; ?>
                    <span class="trip-tag-item"><input type="checkbox" class="interest-option visually-hidden" name="tripTags[]" id="tripTag<?php echo (int) $tag['id']; ?>" value="<?php echo (int) $tag['id']; ?>" <?php echo in_array((int) $tag['id'], $selectedTagIds, true) ? 'checked' : ''; ?>><label class="interest-choice badge rounded-pill" for="tripTag<?php echo (int) $tag['id']; ?>"><?php echo htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8'); ?></label></span>
                <?php endforeach; ?>
                </div>
                <?php if (!$availableTags || $tagsLoadError): ?><div class="form-text"><span data-i18n-ui><?php echo $tagsLoadError ? 'Không thể tải danh sách chủ đề. Vui lòng tải lại trang.' : 'Chưa có chủ đề để chọn.'; ?></span></div><?php endif; ?>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="coverInput" data-i18n="create.cover">Ảnh bìa</label>
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
        <h5 class="mb-0" data-i18n="create.activities">2. Hoạt động trong ngày</h5>
        <div class="d-flex align-items-center gap-2">
         <button type="button" id="dayMinus" class="btn btn-sm btn-light" aria-label="Ngày trước" data-i18n-attrs><i class="bi bi-dash"></i></button>
     <span class="badge bg-success px-3 py-2 fs-6" id="dayBadge"><span data-i18n-ui>Ngày 1</span></span>
         <button type="button" id="dayPlus" class="btn btn-sm btn-light" aria-label="Ngày tiếp theo" data-i18n-attrs><i class="bi bi-plus"></i></button>
        </div>
    </div>

    <!-- Mô tả cho ngày hiện tại -->
    <div class="mt-3">
        <label class="form-label fw-semibold" id="dayDescLabel"><span data-i18n-ui>Mô tả tổng quan Day 1</span></label>
        <input type="text" id="currentDayDescInput" class="form-control" placeholder="VD: Ngày đầu tiên check-in các điểm nổi bật..." data-i18n="create.day_description_placeholder">
    </div>

    <div class="row g-3 mt-2">
     <div class="col-md-3"><label class="form-label" for="activityStartTime"><span data-i18n-ui>Bắt đầu</span></label><input id="activityStartTime" type="time" class="form-control" step="60"></div>
     <div class="col-md-3"><label class="form-label" for="activityEndTime"><span data-i18n-ui>Kết thúc</span></label><input id="activityEndTime" type="time" class="form-control" step="60"></div>
     <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input id="activityOvernight" type="checkbox" class="form-check-input"><label class="form-check-label" for="activityOvernight"><span data-i18n-ui>Kết thúc vào ngày hôm sau</span></label><div id="overnightHint" class="form-text"><span data-i18n-ui>Sang ngày kế tiếp</span></div></div></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Tên hoạt động</span></label><input id="activityName" maxlength="150" class="form-control" placeholder="VD: Ăn sáng" data-i18n-attrs></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Địa điểm</span></label><input id="activityPlace" maxlength="150" class="form-control" placeholder="VD: Phố cổ" data-i18n-attrs><input type="hidden" id="activityLocationCatalogId"><div class="d-flex gap-1 mt-1"><button type="button" id="btnSearchCatalog" class="btn btn-sm btn-outline-secondary"><span data-i18n-ui>Tìm trong TripConnect</span></button><button type="button" id="btnSearchExternal" class="btn btn-sm btn-outline-secondary"><span data-i18n-ui>Tìm địa điểm bên ngoài</span></button></div><div id="locationSearchStatus" class="form-text"></div><div id="activityLocationDetails" class="form-text" aria-live="polite"></div><div id="locationSearchResults" class="list-group mt-2 small"></div></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Loại hoạt động</span></label><select id="activityCategory" class="form-select">
         <option value="sightseeing" data-i18n-ui>Tham quan</option><option value="food" data-i18n-ui>Ăn uống</option><option value="transport" data-i18n-ui>Di chuyển</option><option value="lodging" data-i18n-ui>Lưu trú</option><option value="rest" data-i18n-ui>Nghỉ ngơi</option><option value="free_time" data-i18n-ui>Thời gian tự do</option><option value="other" data-i18n-ui>Khác</option>
     </select></div>
     <div class="col-md-3"><label class="form-label" for="activityCost"><span data-i18n-ui>Chi phí dự kiến</span></label><input id="activityCost" type="number" min="0" step="0.01" class="form-control" placeholder="50000" data-i18n-attrs><div class="form-text"><span data-i18n-ui>Hoạt động miễn phí: nhập 0.</span></div></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Cách tính</span></label><select id="activityCostScope" class="form-select"><option value="fixed" data-i18n-ui>Cả nhóm / cố định</option><option value="" disabled data-i18n-ui>Chưa xác định</option><option value="per_person" data-i18n-ui>Theo người</option></select></div>
     <div class="col-md-3"><label class="form-label"><span data-i18n-ui>Mô tả</span></label><input id="activityDescription" maxlength="10000" class="form-control"></div>
     <div class="col-md-4"><label class="form-label"><span data-i18n-ui>Ghi chú</span></label><input id="activityNotes" maxlength="10000" class="form-control"></div>
     <div class="col-md-4"><label class="form-label"><span data-i18n-ui>Menu / món gợi ý</span></label><input id="activityMenu" maxlength="10000" class="form-control"></div>
     <div class="col-md-4"><label class="form-label"><span data-i18n-ui>Tiện ích gần đó</span></label><input id="activityAmenities" maxlength="10000" class="form-control"></div>
     <div class="col-12 d-flex align-items-end"><button type="button" id="btnAddActivity" class="btn btn-success px-4"><i class="bi bi-plus-lg"></i><span data-i18n-ui> Thêm hoạt động</span></button></div>
    </div>
    <hr class="my-4">
    <div id="activityList" class="timeline">
        <p class="text-muted fst-italic" data-i18n="create.no_activities">Chưa có hoạt động nào được thêm cho ngày này.</p>
    </div>
    <div class="border-top mt-3 pt-3 text-end fw-semibold"><span data-i18n-ui>Tổng ngày: </span><span id="currentDayTotal"><span data-i18n-ui>0 đ</span></span></div>
   </div>
  </div>

  <div class="col-lg-4">
   <div class="sticky-side">
    <div class="card p-4">
        <h6 data-i18n="create.preview_cover">Ảnh bìa xem trước</h6>
        <div class="empty-preview mt-2" id="previewPlaceholder"><i class="bi bi-image fs-1"></i><p class="mb-0 mt-2" data-i18n="create.no_image">Chưa chọn ảnh</p></div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" id="saveTripBtn" name="save_action" value="draft" class="btn btn-outline-primary flex-fill"><i class="bi bi-save"></i><span data-i18n-ui> Lưu bản nháp</span></button>
            <button type="submit" name="save_action" value="published" class="btn btn-primary flex-fill"><i class="bi bi-globe2"></i><span data-i18n-ui> Xuất bản lịch trình</span></button>
        </div>
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
        <h6 data-i18n="create.checklist">Checklist</h6>
        <div class="form-check mb-2"><input class="form-check-input" id="chkInfo" type="checkbox" checked disabled><label class="form-check-label" for="chkInfo" data-i18n="create.trip_info">Thông tin chuyến đi</label></div>
        <div class="form-check mb-2"><input class="form-check-input" id="chkTimeline" type="checkbox" disabled><label class="form-check-label" for="chkTimeline" data-i18n="create.timeline">Timeline hoạt động</label></div>
        <div class="form-check"><input class="form-check-input" id="chkBudget" type="checkbox" disabled><label class="form-check-label" for="chkBudget" data-i18n="create.budget_check">Ngân sách</label></div>
    </div>
   </div>
  </div>
 </div>
 </form>
</main>

<footer class="footer">
 <div class="container">
  <a class="footer-brand d-inline-flex align-items-center mb-4" href="index.php">
   <span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span>
  </a>
  <div class="row g-4">
   <div class="col-lg-4 col-md-6">
    <h6 class="footer-title" data-i18n="footer.company_title">Công ty TripConnect</h6>
    <ul class="footer-contact list-unstyled mb-0">
     <li><i class="bi bi-geo-alt-fill"></i><span data-i18n="footer.address">281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</span></li>
     <li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com"><span data-i18n-ui>info@tripconnect.com</span></a> - <a href="mailto:info.asia@tripconnect.com"><span data-i18n-ui>info.asia@tripconnect.com</span></a> <span data-i18n="footer.english_support">(English Support)</span></span></li>
     <li><i class="bi bi-globe2"></i><span data-i18n="footer.follow_us">Theo dõi chúng tôi trên:</span></li>
    </ul>
    <div class="footer-social">
     <span aria-label="Instagram" data-i18n-attrs><i class="bi bi-instagram"></i></span>
     <span aria-label="Facebook" data-i18n-attrs><i class="bi bi-facebook"></i></span>
     <span aria-label="WhatsApp" data-i18n-attrs><i class="bi bi-whatsapp"></i></span>
     <span aria-label="TikTok" data-i18n-attrs><i class="bi bi-tiktok"></i></span>
     <span aria-label="Messenger" data-i18n-attrs><i class="bi bi-messenger"></i></span>
    </div>
    <a class="btn-hotline" href="tel:1800646888"><i class="bi bi-telephone-fill"></i> 1800 646 888</a>
    <div class="footer-note" data-i18n="footer.hotline_note">Tổng đài miễn phí 24/7</div>
   </div>
   <div class="col-lg-2 col-md-6 offset-lg-1">
    <h6 class="footer-title" data-i18n="footer.explore_title">Khám phá</h6>
    <a class="footer-link" href="explore.php" data-i18n="footer.explore_1">Lịch trình nổi bật</a>
    <a class="footer-link" href="feed.php" data-i18n="footer.explore_2">Bảng tin</a>
    <a class="footer-link" href="create-trip.php" data-i18n="nav.create_trip">Tạo lịch trình</a>
   </div>
   <div class="col-lg-2 col-md-6">
    <h6 class="footer-title" data-i18n="footer.info_title">Thông tin</h6>
     <a class="footer-link" href="about-us.php" data-i18n="footer.about">Về chúng tôi</a>
    <a class="footer-link" href="#" data-i18n="footer.news">Tin tức</a>
    <a class="footer-link" href="#" data-i18n="footer.sitemap">Sitemap</a>
    <a class="footer-link" href="#" data-i18n="footer.help">Trợ giúp</a>
   </div>
   <div class="col-lg-3 col-md-6">
    <h6 class="footer-title" data-i18n="footer.terms_title">Điều kiện - Điều khoản</h6>
    <a class="footer-link" href="#" data-i18n="footer.privacy">Chính sách riêng tư</a>
    <a class="footer-link" href="#" data-i18n="footer.terms">Thỏa thuận sử dụng</a>
    <a class="footer-link" href="#" data-i18n="footer.data_policy">Chính sách bảo vệ dữ liệu cá nhân</a>
   </div>
  </div>
  <hr class="footer-hr my-4"><small><span data-i18n-ui>© 2026 TripConnect</span></small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=2"></script>
<script src="assets/i18n.js?v=11"></script>
<script>
window.tripTimelineInitialActivities = <?php echo json_encode($initialActivitiesForForm, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
window.tripTimelineInitialDescriptions = <?php echo json_encode($initialDescriptionsForForm, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script>
if (false) {
let currentDay = 1;
let maxDays = 3;
let tripActivities = {}; // Lưu trữ hoạt động theo từng ngày: { day_1: [...], day_2: [...] }
let dayDescriptions = {}; // Lưu trữ mô tả từng ngày: { day_1: "...", day_2: "..." }

const tripDaysInput = document.getElementById('tripDays');
const dayBadge = document.getElementById('dayBadge');
const dayDescLabel = document.getElementById('dayDescLabel');
const currentDayDescInput = document.getElementById('currentDayDescInput');

// Cập nhật số ngày tối đa khi người dùng thay đổi ô "Số ngày"
tripDaysInput.addEventListener('input', function() {
    maxDays = parseInt(this.value) || 1;
    if (currentDay > maxDays) {
        currentDay = maxDays;
        updateDayView();
    }
    updateChecklist();
});

// Chuyển ngày qua lại bằng nút cộng trừ
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
    const lang = localStorage.getItem('tripconnect_lang') || 'vi';
    const dayPrefix = translations[lang]['create.day_prefix'];
    const descriptionPrefix = translations[lang]['create.day_description_prefix'];
    dayBadge.innerText = dayPrefix + ' ' + currentDay;
    dayDescLabel.innerText = descriptionPrefix + ' ' + currentDay;
    currentDayDescInput.value = dayDescriptions['day_' + currentDay] || '';
    renderActivities();
}

// Lưu mô tả khi gõ vào ô input
currentDayDescInput.addEventListener('input', function() {
    dayDescriptions['day_' + currentDay] = this.value;
});

// Bấm thêm hoạt động
document.getElementById('btnAddActivity').addEventListener('click', function() {
    let time = document.getElementById('activityTime').value;
    let title = document.getElementById('activityName').value;
    let location = document.getElementById('activityPlace').value;
    let cost = document.getElementById('activityCost').value;
    let category = document.getElementById('activityCategory').value;

    if (!title) {
        const lang = localStorage.getItem('tripconnect_lang') || 'vi';
        alert(translations[lang]['create.activity_required']);
        return;
    }

    if (!tripActivities['day_' + currentDay]) {
        tripActivities['day_' + currentDay] = [];
    }

    tripActivities['day_' + currentDay].push({ time, title, location, cost, category });

    // Reset form nhỏ
    document.getElementById('activityTime').value = '';
    document.getElementById('activityName').value = '';
    document.getElementById('activityPlace').value = '';
    document.getElementById('activityCost').value = '';

    renderActivities();
    updateChecklist();
});

function renderActivities() {
    let listEl = document.getElementById('activityList');
    let activities = tripActivities['day_' + currentDay] || [];

    if (activities.length === 0) {
        const lang = localStorage.getItem('tripconnect_lang') || 'vi';
        listEl.innerHTML = '<p class="text-muted fst-italic">' + translations[lang]['create.no_activities'] + '</p>';
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

document.addEventListener('tripconnect:languagechange', function() {
    updateDayView();
});

function removeActivity(index) {
    tripActivities['day_' + currentDay].splice(index, 1);
    renderActivities();
    updateChecklist();
}

function updateChecklist() {
    let hasTimeline = Object.keys(tripActivities).some(k => tripActivities[k].length > 0);
    let hasBudget = document.getElementById('tripBudget').value > 0 || hasTimeline;

    document.getElementById('chkTimeline').checked = hasTimeline;
    document.getElementById('chkBudget').checked = hasBudget;
}

// Trước khi submit form, gom toàn bộ dữ liệu JSON hoạt động và input mô tả ngày đưa vào form ẩn
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
<script src="assets/pages/trip-timeline-form.js?v=8"></script>
</body>
</html>
