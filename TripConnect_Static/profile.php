<?php
// Khởi động session để kiểm tra tài khoản đang đăng nhập
session_start();
// Nhúng file kết nối cơ sở dữ liệu
require_once 'config/database.php';
$bookmarkedTrips=[];
$savedCount=0;
$savedPage=max(1,min(10000,(int)($_GET['saved_page']??1)));
$socialToken=trip_csrf_token('social');

require_once 'config/trip_social.php';
$actorId=(int)($_SESSION['user_id']??0);
$requestedId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$requestedName=is_string($_GET['username']??null)?trim($_GET['username']):'';
if(!$actorId && !isset($_GET['id']) && $requestedName===''){header('Location: login.php?next=profile.php');exit;}
$user_id=isset($_GET['id'])?($requestedId?:0):$actorId;
if($requestedName!==''){$q=$conn->prepare("SELECT id FROM users WHERE username=? AND status='active'");$q->execute([$requestedName]);$user_id=(int)$q->fetchColumn();}
$isOwn=$actorId>0 && $actorId===$user_id;
if($_SERVER['REQUEST_METHOD']==='POST'&&!$isOwn){http_response_code(403);exit('<meta charset="utf-8"><p data-i18n-ui>Không có quyền truy cập.</p><script src="assets/i18n.js?v=11"></script>');}
$viewerFollowing=trip_social_following($conn,$actorId);
$profile_success = '';
$profile_errors = [];
$currentInterests = [];
$interestStorageAvailable = false;
$interestOptions = [
    'Check-in', 'Biển', 'Núi', 'Ẩm thực', 'Văn hóa', 'Khám phá', 'Nghỉ dưỡng',
    'Mạo hiểm', 'Du lịch một mình', 'Du lịch cùng gia đình', 'Tiết kiệm', 'Sang trọng', 'Tự túc'
];
$csrf_token = $_SESSION['profile_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['profile_csrf_token'] = $csrf_token;

function handleProfileImageUpload(string $fieldName, string $uploadDir): array
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }

    $file = $_FILES[$fieldName];
    $maxBytes = 5 * 1024 * 1024;
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Không thể tải ảnh lên. Vui lòng thử lại.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['path' => null, 'error' => 'Ảnh không được vượt quá 5 MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['path' => null, 'error' => 'File tải lên không hợp lệ.'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($imageInfo === false || !isset($allowedMimeTypes[$mimeType])) {
        return ['path' => null, 'error' => 'Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP hợp lệ.'];
    }
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['path' => null, 'error' => 'Không thể tạo thư mục lưu ảnh.'];
    }
    if (!is_writable($uploadDir)) {
        return ['path' => null, 'error' => 'Thư mục lưu ảnh không có quyền ghi.'];
    }

    try {
        $fileName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
    } catch (Throwable $e) {
        return ['path' => null, 'error' => 'Không thể tạo tên ảnh an toàn.'];
    }

    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['path' => null, 'error' => 'Không thể lưu ảnh lên server.'];
    }

    return ['path' => 'assets/uploads/' . $fileName, 'error' => null, 'absolute_path' => $targetPath];
}

function deleteManagedProfileImage(?string $relativePath, string $uploadDir): void
{
    if (!$relativePath || !preg_match('#^assets/uploads/[^/]+$#', str_replace('\\', '/', $relativePath))) {
        return;
    }

    $uploadDirRealPath = realpath($uploadDir);
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $fileRealPath = is_file($filePath) ? realpath($filePath) : false;
    if ($uploadDirRealPath && $fileRealPath && dirname($fileRealPath) === $uploadDirRealPath) {
        @unlink($fileRealPath);
    }
}

try {
    // 1. Lấy thông tin chi tiết của user hiện tại từ bảng users
    $stmtUser = $conn->prepare($isOwn?"SELECT * FROM users WHERE id = ? AND status='active'":"SELECT id,username,full_name,avatar,cover_image,bio,travel_interests FROM users WHERE id = ? AND status='active'");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {http_response_code(404);exit('<meta charset="utf-8"><p data-i18n-ui>Trang cá nhân không tồn tại hoặc không được phép hiển thị.</p><a href="explore.php" data-i18n-ui>Khám phá</a><script src="assets/i18n.js?v=11"></script>');}
    $stmtInterestColumn = $conn->prepare("SHOW COLUMNS FROM users LIKE ?");
    $stmtInterestColumn->execute(['travel_interests']);
    $interestStorageAvailable = (bool) $stmtInterestColumn->fetch(PDO::FETCH_ASSOC);
    $currentInterests = [];
    if ($interestStorageAvailable && !empty($currentUser['travel_interests'])) {
        $storedInterests = json_decode($currentUser['travel_interests'], true);
        if (is_array($storedInterests)) {
            $currentInterests = array_values(array_intersect($interestOptions, $storedInterests));
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_trip_status') {
        $submittedTripToken = (string) ($_POST['csrf_token'] ?? '');
        $requestedStatus = strtolower(trim((string) ($_POST['status'] ?? '')));
        $requestedTripId = (int) ($_POST['trip_id'] ?? 0);

        if (!hash_equals($_SESSION['profile_csrf_token'], $submittedTripToken)) {
            $profile_errors[] = 'Phiên cập nhật lịch trình không hợp lệ. Vui lòng tải lại trang.';
        } elseif (!in_array($requestedStatus, ['draft', 'published'], true) || $requestedTripId <= 0) {
            $profile_errors[] = 'Trạng thái lịch trình không hợp lệ.';
        } else {
            $stmtStatus = $conn->prepare('UPDATE trips SET status = ? WHERE id = ? AND user_id = ?');
            $stmtStatus->execute([$requestedStatus, $requestedTripId, $user_id]);
            if ($stmtStatus->rowCount() < 1) {
                $profile_errors[] = 'Lịch trình không tồn tại hoặc bạn không có quyền thay đổi.';
            } else {
                header('Location: profile.php');
                exit();
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || !hash_equals($_SESSION['profile_csrf_token'], $submittedToken)) {
            $profile_errors[] = 'Phiên chỉnh sửa không hợp lệ. Vui lòng tải lại trang và thử lại.';
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $selectedInterests = $_POST['interests'] ?? [];
        if (!is_array($selectedInterests)) {
            $selectedInterests = [];
        }
        if(array_filter($selectedInterests,static fn($value)=>!is_string($value))){$profile_errors[]='Danh sách sở thích không hợp lệ.';$selectedInterests=[];}
        $selectedInterests = array_values(array_unique(array_map('trim', $selectedInterests)));
        if (array_diff($selectedInterests, $interestOptions)) {
            $profile_errors[] = 'Danh sách sở thích không hợp lệ.';
        }
        if (!$interestStorageAvailable) {
            $profile_errors[] = 'Chưa thể lưu sở thích lúc này. Vui lòng thử lại sau.';
        }

        if ($fullName === '' || mb_strlen($fullName) > 100) {
            $profile_errors[] = 'Tên hiển thị không được để trống và tối đa 100 ký tự.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $profile_errors[] = 'Username phải dài 3–30 ký tự và chỉ gồm chữ, số hoặc dấu gạch dưới.';
        }
        if (mb_strlen($bio) > 1000) {
            $profile_errors[] = 'Tiểu sử không được vượt quá 1.000 ký tự.';
        }

        if (!$profile_errors) {
            $stmtUsername = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
            $stmtUsername->execute([$username, $user_id]);
            if ($stmtUsername->fetch(PDO::FETCH_ASSOC)) {
                $profile_errors[] = 'Username này đã được tài khoản khác sử dụng.';
            }
        }

        $newAvatarPath = null;
        $newAvatarAbsolutePath = null;
        $newCoverPath = null;
        $newCoverAbsolutePath = null;
        $uploadDir = __DIR__ . '/assets/uploads';
        if (!$profile_errors) {
            $uploadResult = handleProfileImageUpload('avatar', $uploadDir);
            if ($uploadResult['error']) {
                $profile_errors[] = $uploadResult['error'];
            } else {
                $newAvatarPath = $uploadResult['path'];
                $newAvatarAbsolutePath = $uploadResult['absolute_path'] ?? null;
            }
        }
        if (!$profile_errors) {
            $uploadResult = handleProfileImageUpload('cover_image', $uploadDir);
            if ($uploadResult['error']) {
                $profile_errors[] = $uploadResult['error'];
            } else {
                $newCoverPath = $uploadResult['path'];
                $newCoverAbsolutePath = $uploadResult['absolute_path'] ?? null;
            }
        }

        if (!$profile_errors) {
            try {
                $avatarPath = $newAvatarPath ?? ($currentUser['avatar'] ?? null);
                $coverPath = $newCoverPath ?? ($currentUser['cover_image'] ?? null);
                $storedInterests = $selectedInterests ? json_encode($selectedInterests, JSON_UNESCAPED_UNICODE) : null;
                $conn->beginTransaction();
                $stmtUpdate = $conn->prepare('UPDATE users SET full_name = ?, username = ?, bio = ?, avatar = ?, cover_image = ?, travel_interests = ?, updated_at = NOW() WHERE id = ?');
                $stmtUpdate->execute([$fullName, $username, $bio !== '' ? $bio : null, $avatarPath, $coverPath, $storedInterests, $user_id]);
                $conn->commit();

                if ($newAvatarPath && $newAvatarPath !== ($currentUser['avatar'] ?? null)) {
                    deleteManagedProfileImage($currentUser['avatar'] ?? null, $uploadDir);
                }
                if ($newCoverPath && $newCoverPath !== ($currentUser['cover_image'] ?? null)) {
                    deleteManagedProfileImage($currentUser['cover_image'] ?? null, $uploadDir);
                }

                $_SESSION['full_name'] = $fullName;
                $_SESSION['username'] = $username;
                $currentUser['full_name'] = $fullName;
                $currentUser['username'] = $username;
                $currentUser['bio'] = $bio;
                $currentUser['avatar'] = $avatarPath;
                $currentUser['cover_image'] = $coverPath;
                $currentUser['travel_interests'] = $storedInterests;
                $currentInterests = $selectedInterests;
                $profile_success = 'Thông tin trang cá nhân đã được cập nhật.';
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                if ($newAvatarAbsolutePath && is_file($newAvatarAbsolutePath)) {
                    @unlink($newAvatarAbsolutePath);
                }
                if ($newCoverAbsolutePath && is_file($newCoverAbsolutePath)) {
                    @unlink($newCoverAbsolutePath);
                }
                $profile_errors[] = 'Không thể lưu thông tin trang cá nhân. Dữ liệu cũ vẫn được giữ nguyên.';
            }
        } else {
            if ($newAvatarAbsolutePath && is_file($newAvatarAbsolutePath)) {
                @unlink($newAvatarAbsolutePath);
            }
            if ($newCoverAbsolutePath && is_file($newCoverAbsolutePath)) {
                @unlink($newCoverAbsolutePath);
            }
        }
    }

    // 2. Lấy danh sách các chuyến đi do chính user này tạo từ bảng trips
    $stmtTrips = $conn->prepare("SELECT t.*, l.name as location_name, (SELECT COUNT(*) FROM likes lk WHERE lk.trip_id=t.id) AS like_count,
                                        (SELECT lc.image_url FROM trip_days td JOIN activities a ON a.trip_day_id = td.id JOIN location_catalog lc ON lc.id = a.location_catalog_id WHERE td.trip_id = t.id AND NULLIF(TRIM(lc.image_url), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS catalog_image,
                                        (SELECT a.image FROM trip_days td JOIN activities a ON a.trip_day_id = td.id WHERE td.trip_id = t.id AND NULLIF(TRIM(a.image), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS activity_image
                                 FROM trips t
                                 LEFT JOIN locations l ON t.location_id = l.id
                                 WHERE t.user_id = ? AND (?=1 OR (t.status='published' AND t.moderation_status='visible'))
                                 ORDER BY t.created_at DESC");
    $stmtTrips->execute([$user_id,(int)$isOwn]);
    $myTrips = $stmtTrips->fetchAll(PDO::FETCH_ASSOC);
    foreach ($myTrips as &$myTrip) {
        $myTrip['cover_image'] = trip_cover_display_image($myTrip);
    }
    unset($myTrip);
    $totalTrips = count($myTrips);

    // 3. Lấy danh sách những người đang theo dõi user này (Followers từ bảng follows thực tế)
    $stmtFollowers = $conn->prepare("
        SELECT DISTINCT u.id, u.full_name, u.avatar, u.username
        FROM follows f
        JOIN users u ON f.follower_id = u.id
        WHERE f.followed_id = ? AND u.status='active'
    ");
    $stmtFollowers->execute([$user_id]);
    $followersList = $stmtFollowers->fetchAll(PDO::FETCH_ASSOC);
    $followersCount = count($followersList);

    // 4. Lấy danh sách những người mà user này đang theo dõi (Following từ bảng follows thực tế)
    $stmtFollowing = $conn->prepare("
        SELECT DISTINCT u.id, u.full_name, u.avatar, u.username
        FROM follows f
        JOIN users u ON f.followed_id = u.id
        WHERE f.follower_id = ? AND u.status='active'
    ");
    $stmtFollowing->execute([$user_id]);
    $followingList = $stmtFollowing->fetchAll(PDO::FETCH_ASSOC);
    $followingCount = count($followingList);
    if($isOwn){$stmtSaved=$conn->prepare("SELECT t.*,l.name AS location_name,u.full_name AS saved_author,u.username AS saved_username,u.avatar AS saved_avatar FROM trips t JOIN users u ON u.id=t.user_id AND u.status='active' LEFT JOIN locations l ON l.id=t.location_id WHERE t.status='published' AND t.moderation_status='visible' AND EXISTS(SELECT 1 FROM bookmarks b WHERE b.trip_id=t.id AND b.user_id=?) ORDER BY t.created_at DESC,t.id DESC LIMIT 21 OFFSET ".(($savedPage-1)*20));
    $stmtSaved->execute([$user_id]);$bookmarkedTrips=$stmtSaved->fetchAll(PDO::FETCH_ASSOC);$hasNextSaved=count($bookmarkedTrips)>20;$bookmarkedTrips=array_slice($bookmarkedTrips,0,20);
    $q=$conn->prepare("SELECT COUNT(DISTINCT b.trip_id) FROM bookmarks b JOIN trips t ON t.id=b.trip_id JOIN users u ON u.id=t.user_id WHERE b.user_id=? AND t.status='published' AND t.moderation_status='visible' AND u.status='active'");$q->execute([$user_id]);$savedCount=(int)$q->fetchColumn();}

} catch (PDOException $e) {
    $currentUser = [
        'full_name' => $_SESSION['full_name'] ?? 'Thành viên',
        'username' => $_SESSION['username'] ?? 'user',
        'avatar' => 'https://i.pravatar.cc/160?img=32',
        'cover_image' => null,
        'bio' => 'Chia sẻ những chuyến đi tự túc và lịch trình thực tế.'
    ];
    $myTrips = [];
    $totalTrips = 0;
    $followersList = [];
    $followingList = [];
    $followersCount = 0;
    $followingCount = 0;
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="social-csrf" content="<?php echo htmlspecialchars($socialToken,ENT_QUOTES,'UTF-8'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Hồ sơ thành viên TripConnect: lịch trình đã xuất bản, sở thích du lịch và bộ sưu tập đã lưu.">
<title data-i18n-ui>Hồ sơ cá nhân | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-xl sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng" data-i18n-attrs><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php"><span data-i18n-ui>Trang chủ</span></a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php"><span data-i18n-ui>Khám phá</span></a></li>
    <li class="nav-item"><a class="nav-link" href="feed.php"><span data-i18n-ui>Bảng tin</span></a></li>
    <li class="nav-item"><a class="nav-link active" href="profile.php"><span data-i18n-ui>Hồ sơ</span></a></li><?php trip_social_nav(); ?>
   </ul>
   <div class="d-flex gap-2 align-items-center">
    <?php if($actorId): ?><span class="text-secondary small"><span data-i18n-ui>Xin chào, </span><strong><?php echo htmlspecialchars($_SESSION['full_name']??''); ?></strong></span>
    <form method="post" action="logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill"><span data-i18n-ui>Đăng xuất</span></button></form>
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i><span data-i18n-ui> Tạo lịch trình</span></a>
   <?php else: ?><a href="login.php" class="btn btn-primary"><span data-i18n-ui>Đăng nhập</span></a><?php endif; ?></div>
  </div>
 </div>
</nav>

<main>
 <div class="container">
  <?php if ($profile_success): ?>
   <div class="alert alert-success mt-4" role="alert"><i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($profile_success); ?></div>
  <?php endif; ?>
  <?php if ($profile_errors): ?>
   <div class="alert alert-danger mt-4" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars(implode(' ', $profile_errors)); ?></div>
  <?php endif; ?>
  <div class="card overflow-hidden mt-4">
   <img class="profile-cover w-100" src="<?php echo htmlspecialchars($currentUser['cover_image'] ?: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221200%22 height=%22240%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23e9f0ee%22/%3E%3C/svg%3E'); ?>" alt="Ảnh bìa trang cá nhân" data-i18n-attrs>
   <div class="p-4">
    <div class="profile-avatar-wrap d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div class="d-flex flex-wrap align-items-end gap-3" style="min-width:0">
            <img class="avatar-lg border border-4 border-white rounded-circle" src="<?php echo htmlspecialchars($currentUser['avatar'] ?? 'https://i.pravatar.cc/160?img=32'); ?>" width="100" height="100" alt="Ảnh đại diện" data-i18n-attrs>
            <div>
                <h2 class="mb-0 text-break"><?php echo htmlspecialchars($currentUser['full_name']); ?></h2>
                <div class="text-muted">@<?php echo htmlspecialchars($currentUser['username']); ?><span data-i18n-ui> · Việt Nam</span></div>
            </div>
        </div>
        <div class="profile-actions d-flex flex-wrap gap-2 justify-content-end"><?php if($isOwn): ?>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="bi bi-person-gear me-1"></i><span data-i18n-ui> Chỉnh sửa trang cá nhân</span></button>
            <a href="create-trip.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><span data-i18n-ui> Tạo lịch trình</span></a>
        <?php else: trip_social_follow_button((int)$user_id,$viewerFollowing); endif; ?></div>
    </div>
    <p class="mt-4 mb-2"><?php echo htmlspecialchars($currentUser['bio'] ?? 'Chia sẻ những chuyến đi tự túc, lịch trình thực tế và kinh nghiệm tiết kiệm.'); ?></p>
<div class="d-flex flex-wrap gap-3 small">
        <span><strong><?php echo $totalTrips; ?></strong><span data-i18n-ui> lịch trình</span></span>
        <a href="#followers" class="text-decoration-none"><strong data-followers-count="<?= (int)$user_id ?>"><?php echo $followersCount; ?></strong><span data-i18n-ui> người theo dõi</span></a>
        <a href="#following" class="text-decoration-none"><strong data-following-count="<?= (int)$user_id ?>"><?php echo $followingCount; ?></strong><span data-i18n-ui> đang theo dõi</span></a>
    </div>
   </div>
  </div>

  <div class="row g-4 mt-2">
   <div class="col-lg-8">
    <div class="d-flex gap-2 justify-content-between align-items-center mb-3">
        <h4 class="mb-0 flex-grow-1" style="min-width:0"><span data-i18n-ui><?= $isOwn?'Lịch trình của bạn':'Lịch trình công khai' ?></span></h4>
        <?php if($isOwn): ?><a href="create-trip.php" class="btn btn-soft flex-shrink-0"><span data-i18n-ui>+ Tạo mới</span></a><?php endif; ?>
    </div>

    <div class="row g-4">
     <?php if (!empty($myTrips)): ?>
         <?php foreach ($myTrips as $trip): ?>
             <div class="col-md-6">
              <div class="card trip-card">
               <?php if (!empty($trip['cover_image'])): ?><img class="trip-img" src="<?php echo htmlspecialchars($trip['cover_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Ảnh chuyến đi" data-i18n-attrs><?php else: ?><div class="trip-img d-flex align-items-center justify-content-center bg-light text-secondary" role="img" aria-label="Chưa có ảnh phù hợp" data-i18n-attrs><i class="bi bi-image fs-1" aria-hidden="true"></i></div><?php endif; ?>
               <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="badge badge-soft"><?php echo htmlspecialchars($trip['days_count']); ?><span data-i18n-ui> ngày</span></span>
                    <span class="small text-muted"><i class="bi bi-heart"></i> <?php echo intval($trip['like_count']); ?></span>
                </div>
                 <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($trip['title']); ?></h5>
                 <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($trip['location_name'] ?? 'Việt Nam'); ?></p>
                 <div class="d-flex align-items-center gap-2 mb-2">
                     <span class="badge <?php echo $trip['status'] === 'published' ? 'bg-success' : 'bg-secondary'; ?>"><span data-i18n-ui><?php echo $trip['status'] === 'published' ? 'Đã xuất bản' : 'Bản nháp'; ?></span></span>
                     <?php if($isOwn): ?><form method="POST" action="profile.php" class="d-inline">
                         <input type="hidden" name="action" value="update_trip_status">
                         <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                         <input type="hidden" name="status" value="<?php echo $trip['status'] === 'published' ? 'draft' : 'published'; ?>">
                         <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                         <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none"><span data-i18n-ui><?php echo $trip['status'] === 'published' ? 'Chuyển về bản nháp' : 'Xuất bản'; ?></span></button>
                     </form><?php endif; ?>
                 </div>
                 <div class="d-flex justify-content-between align-items-center mt-2">
    <strong>~ <span data-i18n-number><?php echo number_format($trip['budget_estimate'], 0, ',', '.'); ?></span><span data-i18n-ui>đ</span></strong>
    <div class="d-flex gap-1">
        <a href="trip-detail.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-primary" title="Xem chi tiết" data-i18n-attrs><i class="bi bi-eye"></i></a>
        <?php if($isOwn): ?><a href="edit-trip.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-success" title="Chỉnh sửa" data-i18n-attrs><i class="bi bi-pencil"></i></a>
        <form method="post" action="delete-trip.php" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lịch trình này không?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>"><button class="btn btn-sm btn-outline-danger" title="Xóa" type="submit" data-i18n-attrs><i class="bi bi-trash"></i></button></form><?php endif; ?>
    </div>
</div>
               </div>
              </div>
             </div>
         <?php endforeach; ?>
     <?php else: ?>
         <div class="col-12">
             <div class="card p-4 text-center text-muted">
                 <p class="mb-2"><span data-i18n-ui><?= $isOwn?'Bạn chưa tạo lịch trình nào.':'Tác giả chưa có lịch trình công khai.' ?></span></p>
                 <?php if($isOwn): ?><a href="create-trip.php" class="btn btn-primary btn-sm mx-auto"><span data-i18n-ui>Tạo lịch trình đầu tiên ngay</span></a><?php endif; ?>
             </div>
         </div>
     <?php endif; ?>

     <?php if($isOwn): ?><div class="col-md-6 d-flex">
      <a href="create-trip.php" class="card w-100 text-decoration-none d-flex align-items-center justify-content-center text-muted" style="min-height:220px;border-style:dashed">
       <div class="text-center"><i class="bi bi-plus-circle fs-1"></i><p class="mt-2 mb-0"><span data-i18n-ui>Thêm lịch trình mới</span></p></div>
      </a>
     </div>
    <?php endif; ?></div>

   <?php if($isOwn): ?><section class="mt-4" id="savedTrips">
    <div class="card p-4"><h4><span data-i18n-ui>Lịch trình đã lưu (</span><span data-bookmark-total><?= $savedCount ?></span>)</h4>
    <p data-bookmark-empty class="text-muted" <?php echo $bookmarkedTrips?'hidden':''; ?>><span data-i18n-ui>Chưa có lịch trình công khai nào trong danh sách đã lưu</span><span data-i18n-ui><?php echo $savedPage>1?' ở trang này':''; ?></span>.</p>
    <?php foreach($bookmarkedTrips as $saved): $savedCover=trip_cover_display_image($saved); ?>
      <div data-bookmark-card class="d-flex flex-wrap flex-sm-nowrap align-items-center gap-3 border-bottom py-3">
       <?php if($savedCover): ?><img data-trip-cover src="<?php echo htmlspecialchars($savedCover,ENT_QUOTES,'UTF-8'); ?>" width="96" height="64" style="object-fit:cover" class="rounded flex-shrink-0" alt="Ảnh bìa" loading="lazy" data-i18n-attrs><?php endif; ?>
       <div class="flex-grow-1" style="min-width:0;overflow-wrap:anywhere"><a href="trip-detail.php?id=<?php echo (int)$saved['id']; ?>"><?php echo htmlspecialchars($saved['title']); ?></a><div class="small text-muted"><?= (int)$saved['days_count'] ?><span data-i18n-ui> ngày · </span><?= htmlspecialchars($saved['location_name']??'') ?> · <span data-i18n-number><?= number_format((float)$saved['budget_estimate'],0,',','.') ?></span><span data-i18n-ui>đ</span></div><?php trip_social_author(['id'=>$saved['user_id'],'full_name'=>$saved['saved_author'],'username'=>$saved['saved_username'],'avatar'=>$saved['saved_avatar']]); ?></div>
       <button type="button" class="btn btn-light flex-shrink-0" data-social-action="bookmark" data-target="<?php echo (int)$saved['id']; ?>" data-active="1" aria-pressed="true"><i class="bi bi-bookmark-fill"></i> <span data-social-label data-i18n-ui><span data-i18n-ui>Đã lưu</span></span></button>
      </div>
    <?php endforeach; ?>
    <nav class="d-flex justify-content-between mt-3" aria-label="Trang lịch trình đã lưu" data-i18n-attrs>
     <?php if($savedPage>1): ?><a href="profile.php?saved_page=<?php echo $savedPage-1; ?>#savedTrips"><span data-i18n-ui>Trang trước</span></a><?php endif; ?>
     <?php if(!empty($hasNextSaved)): ?><a class="ms-auto" href="profile.php?saved_page=<?php echo $savedPage+1; ?>#savedTrips"><span data-i18n-ui>Trang sau</span></a><?php endif; ?>
    </nav></div>
   </section><?php endif; ?>
   </div>
   <div class="col-lg-4">
    <div class="card p-4">
        <h5><span data-i18n-ui>Sở thích du lịch</span></h5>
        <?php if($isOwn): ?><button type="button" class="btn btn-sm btn-link p-0 text-start" data-bs-toggle="modal" data-bs-target="#editProfileModal"><span data-i18n-ui>Cập nhật sở thích</span></button><?php endif; ?>
        <?php if ($currentInterests): ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach ($currentInterests as $interest): ?>
                    <span data-i18n-ui class="badge badge-soft"><?php echo htmlspecialchars($interest); ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted small mt-3 mb-0"><span data-i18n-ui>Bạn chưa chọn sở thích du lịch nào</span></p>
        <?php endif; ?>
    </div>
    <div class="card p-4 mt-4">
        <h5><span data-i18n-ui>Bộ sưu tập</span></h5>
        <p class="text-muted small"><span data-i18n-ui>Lịch trình đã lưu để xem sau.</span></p>
        <?php if($isOwn): ?><a href="#savedTrips" class="text-decoration-none"><span data-i18n-ui>Xem lịch trình đã lưu</span></a><br><?php endif; ?><a href="explore.php" class="text-decoration-none"><span data-i18n-ui>Khám phá thêm lịch trình →</span></a>
    </div>
   </div>
  </div>
  <?php foreach(['followers'=>['Người theo dõi',$followersList],'following'=>['Đang theo dõi',$followingList]] as $listKey=>[$listTitle,$members]): ?>
  <details class="card p-3 my-3" id="<?= $listKey ?>"><summary class="fw-semibold"><?= $listTitle ?></summary>
   <p data-follow-list-empty class="text-muted mt-3 mb-0" <?= $members?'hidden':'' ?>><span data-i18n-ui>Chưa có thành viên trong danh sách.</span></p>
   <?php foreach($members as $member): ?><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-3" <?= $isOwn&&$listKey==='following'?'data-own-following-row':'' ?>><?php trip_social_author($member);trip_social_follow_button((int)$member['id'],$viewerFollowing); ?></div><?php endforeach; ?>
  </details><?php endforeach; ?>
  <div id="socialStatus" role="status" class="small text-danger"></div>
 </div>
</main>

<?php if($isOwn): ?><div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-lg">
  <div class="modal-content border-0 shadow-lg rounded-4">
   <div class="modal-header">
    <div><h5 class="modal-title" id="editProfileModalLabel"><i class="bi bi-person-gear me-2"></i><span data-i18n-ui>Chỉnh sửa trang cá nhân</span></h5><p class="text-muted small mb-0"><span data-i18n-ui>Cập nhật thông tin hiển thị của bạn trên TripConnect.</span></p></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng" data-i18n-attrs></button>
   </div>
   <form method="POST" enctype="multipart/form-data" id="profileEditForm">
    <input type="hidden" name="action" value="update_profile">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <div class="modal-body p-4">
     <div class="row g-4">
      <div class="col-md-4 text-center">
       <img id="avatarPreview" class="profile-edit-avatar avatar-lg rounded-circle mb-3" src="<?php echo htmlspecialchars($currentUser['avatar'] ?? 'https://i.pravatar.cc/160?img=32'); ?>" width="120" height="120" alt="Xem trước ảnh đại diện" data-i18n-attrs>
       <label for="avatarInput" class="form-label fw-semibold d-block"><span data-i18n-ui>Ảnh đại diện</span></label>
       <input type="file" id="avatarInput" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
       <div class="form-text"><span data-i18n-ui>JPEG, PNG hoặc WebP, tối đa 5 MB.</span></div>
      </div>
      <div class="col-md-8">
       <div class="mb-4">
        <label for="coverImageInput" class="form-label fw-semibold"><span data-i18n-ui>Ảnh bìa</span></label>
        <img id="coverPreview" class="profile-edit-cover w-100 rounded-3 mb-2" src="<?php echo htmlspecialchars($currentUser['cover_image'] ?: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%221200%22 height=%22240%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23e9f0ee%22/%3E%3C/svg%3E'); ?>" alt="Xem trước ảnh bìa" data-i18n-attrs>
        <input type="file" id="coverImageInput" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        <div class="form-text"><span data-i18n-ui>JPEG, PNG hoặc WebP, tối đa 5 MB.</span></div>
       </div>
       <div class="mb-4">
        <label class="form-label fw-semibold d-block"><span data-i18n-ui>Sở thích du lịch</span></label>
        <div class="interest-picker d-flex flex-wrap gap-2">
         <?php foreach ($interestOptions as $index => $interest): ?>
          <?php $interestId = 'interest_' . $index; ?>
          <input class="interest-option visually-hidden" type="checkbox" name="interests[]" id="<?php echo $interestId; ?>" value="<?php echo htmlspecialchars($interest); ?>" <?php echo in_array($interest, $currentInterests, true) ? 'checked' : ''; ?>>
          <label data-i18n-ui class="interest-choice badge" for="<?php echo $interestId; ?>"><?php echo htmlspecialchars($interest); ?></label>
         <?php endforeach; ?>
        </div>
        <div class="form-text"><span data-i18n-ui>Chọn một hoặc nhiều sở thích phù hợp với bạn.</span></div>
       </div>
       <div class="mb-3"><label for="fullNameInput" class="form-label fw-semibold"><span data-i18n-ui>Tên hiển thị</span></label><input type="text" id="fullNameInput" name="full_name" class="form-control" maxlength="100" required value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>"></div>
       <div class="mb-3"><label for="usernameInput" class="form-label fw-semibold"><span data-i18n-ui>Username</span></label><div class="input-group"><span class="input-group-text">@</span><input type="text" id="usernameInput" name="username" class="form-control" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" required value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>"></div><div class="form-text"><span data-i18n-ui>3–30 ký tự, chỉ gồm chữ, số và dấu gạch dưới.</span></div></div>
       <div class="mb-3"><label for="bioInput" class="form-label fw-semibold"><span data-i18n-ui>Tiểu sử</span></label><textarea id="bioInput" name="bio" class="form-control" rows="5" maxlength="1000" placeholder="Giới thiệu ngắn về bạn..." data-i18n-attrs><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea><div class="form-text text-end"><span id="bioCounter">0</span>/1000</div></div>
       </div>
     </div>
    </div>
    <div class="modal-footer px-4 pb-4"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><span data-i18n-ui>Hủy</span></button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i><span data-i18n-ui>Lưu thay đổi</span></button></div>
   </form>
  </div>
 </div>
</div>

<?php endif; ?><footer class="footer py-5 mt-5">
 <div class="container">
  <div class="row g-4">
   <div class="col-md-8">
       <h4 class="text-white">TripConnect</h4>
       <p><span data-i18n-ui>Chia sẻ trải nghiệm, khám phá lịch trình và tạo chuyến đi theo cách của bạn.</span></p>
   </div>
   <div class="col-md-4">
       <h6 class="text-white"><span data-i18n-ui>Khám phá</span></h6>
       <a class="d-block mb-2 text-decoration-none text-muted" href="explore.php"><span data-i18n-ui>Lịch trình nổi bật</span></a>
       <a class="d-block mb-2 text-decoration-none text-muted" href="feed.php"><span data-i18n-ui>Bảng tin</span></a>
   </div>
  </div>
  <hr class="border-secondary my-4"><small><span data-i18n-ui>© 2026 TripConnect</span></small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=6"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if(location.hash==='#editProfileModal' && document.getElementById('editProfileModal') && window.bootstrap)bootstrap.Modal.getOrCreateInstance(document.getElementById('editProfileModal')).show();
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const coverImageInput = document.getElementById('coverImageInput');
    const coverPreview = document.getElementById('coverPreview');
    const bioInput = document.getElementById('bioInput');
    const bioCounter = document.getElementById('bioCounter');
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (event) { avatarPreview.src = event.target.result; };
            reader.readAsDataURL(file);
        });
    }
    if (coverImageInput && coverPreview) {
        coverImageInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (event) { coverPreview.src = event.target.result; };
            reader.readAsDataURL(file);
        });
    }
    function updateBioCounter() { if (bioInput && bioCounter) bioCounter.textContent = bioInput.value.length; }
    if (bioInput) { bioInput.addEventListener('input', updateBioCounter); updateBioCounter(); }
});
</script>
<script src="assets/i18n.js?v=11"></script>
</body>
</html>
