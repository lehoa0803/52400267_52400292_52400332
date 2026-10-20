<?php
// Khởi động session để kiểm tra tài khoản đang đăng nhập
session_start();
// Nhúng file kết nối cơ sở dữ liệu
require_once 'config/database.php';

// Kiểm tra xem người dùng đã đăng nhập chưa, nếu chưa thì chuyển hướng về trang đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
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
    $stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        session_unset();
        header('Location: login.php');
        exit();
    }

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['profile_csrf_token'], $submittedToken)) {
            $profile_errors[] = 'Phiên chỉnh sửa không hợp lệ. Vui lòng tải lại trang và thử lại.';
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $selectedInterests = $_POST['interests'] ?? [];
        if (!is_array($selectedInterests)) {
            $selectedInterests = [];
        }
        $selectedInterests = array_values(array_unique(array_map('trim', $selectedInterests)));
        if (array_diff($selectedInterests, $interestOptions)) {
            $profile_errors[] = 'Danh sách sở thích không hợp lệ.';
        }
        if (!$interestStorageAvailable) {
            $profile_errors[] = 'Chưa có nơi lưu sở thích trong database. Vui lòng chạy SQL được hướng dẫn trước khi lưu.';
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
    $stmtTrips = $conn->prepare("SELECT t.*, l.name as location_name 
                                 FROM trips t 
                                 LEFT JOIN locations l ON t.location_id = l.id 
                                 WHERE t.user_id = ? 
                                 ORDER BY t.created_at DESC");
    $stmtTrips->execute([$user_id]);
    $myTrips = $stmtTrips->fetchAll(PDO::FETCH_ASSOC);
    $totalTrips = count($myTrips);

    // 3. Lấy danh sách những người đang theo dõi user này (Followers từ bảng follows thực tế)
    $stmtFollowers = $conn->prepare("
        SELECT u.id, u.full_name, u.avatar 
        FROM follows f 
        JOIN users u ON f.follower_id = u.id 
        WHERE f.followed_id = ?
    ");
    $stmtFollowers->execute([$user_id]);
    $followersList = $stmtFollowers->fetchAll(PDO::FETCH_ASSOC);
    $followersCount = count($followersList);

    // 4. Lấy danh sách những người mà user này đang theo dõi (Following từ bảng follows thực tế)
    $stmtFollowing = $conn->prepare("
        SELECT u.id, u.full_name, u.avatar 
        FROM follows f 
        JOIN users u ON f.followed_id = u.id 
        WHERE f.follower_id = ?
    ");
    $stmtFollowing->execute([$user_id]);
    $followingList = $stmtFollowing->fetchAll(PDO::FETCH_ASSOC);
    $followingCount = count($followingList);

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
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Hồ sơ thành viên TripConnect: lịch trình đã xuất bản, sở thích du lịch và bộ sưu tập đã lưu.">
<title>Hồ sơ cá nhân | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php">Khám phá</a></li>
    <li class="nav-item"><a class="nav-link" href="feed.php">Bảng tin</a></li>
    <li class="nav-item"><a class="nav-link active" href="profile.php">Hồ sơ</a></li>
   </ul>
   <div class="d-flex gap-2 align-items-center">
    <span class="text-secondary small">Xin chào, <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong></span>
    <a class="btn btn-outline-danger btn-sm px-3 rounded-pill" href="logout.php">Đăng xuất</a>
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> Tạo lịch trình</a>
   </div>
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
   <img class="profile-cover w-100" src="<?php echo htmlspecialchars($currentUser['cover_image'] ?: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80'); ?>" alt="Ảnh bìa trang cá nhân">
   <div class="p-4">
    <div class="profile-avatar-wrap d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div class="d-flex align-items-end gap-3">
            <img class="avatar-lg border border-4 border-white rounded-circle" src="<?php echo htmlspecialchars($currentUser['avatar'] ?? 'https://i.pravatar.cc/160?img=32'); ?>" width="100" height="100" alt="Ảnh đại diện">
            <div>
                <h2 class="mb-0"><?php echo htmlspecialchars($currentUser['full_name']); ?></h2>
                <div class="text-muted">@<?php echo htmlspecialchars($currentUser['username']); ?> · Việt Nam</div>
            </div>
        </div>
        <div class="profile-actions d-flex flex-wrap gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="bi bi-person-gear me-1"></i> Chỉnh sửa trang cá nhân</button>
            <a href="create-trip.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Tạo lịch trình</a>
        </div>
    </div>
    <p class="mt-4 mb-2"><?php echo htmlspecialchars($currentUser['bio'] ?? 'Chia sẻ những chuyến đi tự túc, lịch trình thực tế và kinh nghiệm tiết kiệm.'); ?></p>
<div class="d-flex gap-4 small">
        <span><strong><?php echo $totalTrips; ?></strong> lịch trình</span>
        <span><strong><?php echo $followersCount; ?></strong> người theo dõi</span>
        <span><strong><?php echo $followingCount; ?></strong> đang theo dõi</span>
    </div>
   </div>
  </div>

  <div class="row g-4 mt-2">
   <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Lịch trình của bạn</h4>
        <a href="create-trip.php" class="btn btn-soft">+ Tạo mới</a>
    </div>
    
    <div class="row g-4">
     <?php if (!empty($myTrips)): ?>
         <?php foreach ($myTrips as $trip): ?>
             <div class="col-md-6">
              <div class="card trip-card">
               <img class="trip-img" src="<?php echo htmlspecialchars($trip['cover_image'] ?? 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&w=900&q=80'); ?>" alt="Ảnh chuyến đi">
               <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="badge badge-soft"><?php echo htmlspecialchars($trip['days_count']); ?> ngày</span>
                    <span class="small text-muted"><i class="bi bi-heart"></i> <?php echo intval($trip['view_count']); ?></span>
                </div>
                <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($trip['title']); ?></h5>
                <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($trip['location_name'] ?? 'Việt Nam'); ?></p>
                <div class="d-flex justify-content-between align-items-center mt-2">
    <strong>~ <?php echo number_format($trip['budget_estimate'], 0, ',', '.'); ?>đ</strong>
    <div class="d-flex gap-1">
        <a href="trip-detail.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-primary" title="Xem chi tiết"><i class="bi bi-eye"></i></a>
        <a href="edit-trip.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-success" title="Chỉnh sửa"><i class="bi bi-pencil"></i></a>
        <a href="delete-trip.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa lịch trình này không?');"><i class="bi bi-trash"></i></a>
    </div>
</div>
               </div>
              </div>
             </div>
         <?php endforeach; ?>
     <?php else: ?>
         <div class="col-12">
             <div class="card p-4 text-center text-muted">
                 <p class="mb-2">Bạn chưa tạo lịch trình nào.</p>
                 <a href="create-trip.php" class="btn btn-primary btn-sm mx-auto">Tạo lịch trình đầu tiên ngay</a>
             </div>
         </div>
     <?php endif; ?>

     <div class="col-md-6 d-flex">
      <a href="create-trip.php" class="card w-100 text-decoration-none d-flex align-items-center justify-content-center text-muted" style="min-height:220px;border-style:dashed">
       <div class="text-center"><i class="bi bi-plus-circle fs-1"></i><p class="mt-2 mb-0">Thêm lịch trình mới</p></div>
      </a>
     </div>
    </div>
   </div>

   <div class="col-lg-4">
    <div class="card p-4">
        <h5>Sở thích du lịch</h5>
        <?php if ($currentInterests): ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach ($currentInterests as $interest): ?>
                    <span class="badge badge-soft"><?php echo htmlspecialchars($interest); ?></span>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted small mt-3 mb-0">Bạn chưa chọn sở thích du lịch nào</p>
        <?php endif; ?>
    </div>
    <div class="card p-4 mt-4">
        <h5>Bộ sưu tập</h5>
        <p class="text-muted small">Lịch trình đã lưu để xem sau.</p>
        <a href="explore.php" class="text-decoration-none">Khám phá thêm lịch trình →</a>
    </div>
   </div>
  </div>
 </div>
</main>

<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-lg">
  <div class="modal-content border-0 shadow-lg rounded-4">
   <div class="modal-header">
    <div><h5 class="modal-title" id="editProfileModalLabel"><i class="bi bi-person-gear me-2"></i>Chỉnh sửa trang cá nhân</h5><p class="text-muted small mb-0">Cập nhật thông tin hiển thị của bạn trên TripConnect.</p></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
   </div>
   <form method="POST" enctype="multipart/form-data" id="profileEditForm">
    <input type="hidden" name="action" value="update_profile">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <div class="modal-body p-4">
     <div class="row g-4">
      <div class="col-md-4 text-center">
       <img id="avatarPreview" class="profile-edit-avatar avatar-lg rounded-circle mb-3" src="<?php echo htmlspecialchars($currentUser['avatar'] ?? 'https://i.pravatar.cc/160?img=32'); ?>" width="120" height="120" alt="Xem trước ảnh đại diện">
       <label for="avatarInput" class="form-label fw-semibold d-block">Ảnh đại diện</label>
       <input type="file" id="avatarInput" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
       <div class="form-text">JPEG, PNG hoặc WebP, tối đa 5 MB.</div>
      </div>
      <div class="col-md-8">
       <div class="mb-4">
        <label for="coverImageInput" class="form-label fw-semibold">Ảnh bìa</label>
        <img id="coverPreview" class="profile-edit-cover w-100 rounded-3 mb-2" src="<?php echo htmlspecialchars($currentUser['cover_image'] ?: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80'); ?>" alt="Xem trước ảnh bìa">
        <input type="file" id="coverImageInput" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp">
        <div class="form-text">JPEG, PNG hoặc WebP, tối đa 5 MB.</div>
       </div>
       <div class="mb-4">
        <label class="form-label fw-semibold d-block">Sở thích du lịch</label>
        <div class="interest-picker d-flex flex-wrap gap-2">
         <?php foreach ($interestOptions as $index => $interest): ?>
          <?php $interestId = 'interest_' . $index; ?>
          <input class="interest-option visually-hidden" type="checkbox" name="interests[]" id="<?php echo $interestId; ?>" value="<?php echo htmlspecialchars($interest); ?>" <?php echo in_array($interest, $currentInterests, true) ? 'checked' : ''; ?>>
          <label class="interest-choice badge" for="<?php echo $interestId; ?>"><?php echo htmlspecialchars($interest); ?></label>
         <?php endforeach; ?>
        </div>
        <div class="form-text">Chọn một hoặc nhiều sở thích phù hợp với bạn.</div>
       </div>
       <div class="mb-3"><label for="fullNameInput" class="form-label fw-semibold">Tên hiển thị</label><input type="text" id="fullNameInput" name="full_name" class="form-control" maxlength="100" required value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>"></div>
       <div class="mb-3"><label for="usernameInput" class="form-label fw-semibold">Username</label><div class="input-group"><span class="input-group-text">@</span><input type="text" id="usernameInput" name="username" class="form-control" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" required value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>"></div><div class="form-text">3–30 ký tự, chỉ gồm chữ, số và dấu gạch dưới.</div></div>
       <div class="mb-3"><label for="bioInput" class="form-label fw-semibold">Tiểu sử</label><textarea id="bioInput" name="bio" class="form-control" rows="5" maxlength="1000" placeholder="Giới thiệu ngắn về bạn..."><?php echo htmlspecialchars($currentUser['bio'] ?? ''); ?></textarea><div class="form-text text-end"><span id="bioCounter">0</span>/1000</div></div>
       </div>
     </div>
    </div>
    <div class="modal-footer px-4 pb-4"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu thay đổi</button></div>
   </form>
  </div>
 </div>
</div>

<footer class="footer py-5 mt-5">
 <div class="container">
  <div class="row g-4">
   <div class="col-md-5">
       <h4 class="text-white">TripConnect</h4>
       <p>Chia sẻ trải nghiệm, khám phá lịch trình và tạo chuyến đi theo cách của bạn.</p>
   </div>
   <div class="col-md-3">
       <h6 class="text-white">Khám phá</h6>
       <a class="d-block mb-2 text-decoration-none text-muted" href="explore.php">Lịch trình nổi bật</a>
       <a class="d-block mb-2 text-decoration-none text-muted" href="feed.php">Bảng tin</a>
   </div>
   <div class="col-md-4">
       <h6 class="text-white">Thông tin</h6>
       <p class="mb-0">Đã tích hợp Backend PHP và kết nối cơ sở dữ liệu MySQL thành công.</p>
   </div>
  </div>
  <hr class="border-secondary my-4"><small>© 2026 TripConnect Demo</small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
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
</body>
</html>
