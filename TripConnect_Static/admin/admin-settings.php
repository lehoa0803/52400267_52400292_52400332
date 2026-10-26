<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
require_once '../config/admin_auth.php';
// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$adminSettingsCsrf = trip_csrf_token('admin_settings');
$success_msg = '';
$error_msg = '';

// Lấy thông tin hiện tại của Admin từ database
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $adminInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $adminInfo = ['full_name' => $_SESSION['full_name'] ?? 'Admin', 'email' => 'admin@tripconnect.com'];
}

// Xử lý cập nhật thông tin Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_admin') {
    if (!trip_verify_csrf('admin_settings', (string) ($_POST['csrf_token'] ?? ''))) {
        $error_msg = 'Yêu cầu quản trị không hợp lệ.';
    } else {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!empty($full_name) && !empty($email)) {
        try {
            $stmtUp = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmtUp->execute([$full_name, $email, $admin_id]);
            $_SESSION['full_name'] = $full_name; // Cập nhật lại session
            $success_msg = "Cập nhật thông tin Admin thành công!";

            // Reload thông tin mới
            $stmt->execute([$admin_id]);
            $adminInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error_msg = "Lỗi: Email này có thể đã được sử dụng bởi tài khoản khác.";
        }
    } else {
        $error_msg = "Vui lòng không để trống tên hoặc email!";
    }
    }
}

// Xử lý Đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!trip_verify_csrf('admin_settings', (string) ($_POST['csrf_token'] ?? ''))) {
        $error_msg = 'Yêu cầu quản trị không hợp lệ.';
    } else {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!empty($old_pass) && !empty($new_pass) && !empty($confirm_pass)) {
        if ($new_pass === $confirm_pass) {
            // Kiểm tra mật khẩu cũ có đúng không
            if (password_verify($old_pass, $adminInfo['password']) || $old_pass === $adminInfo['password']) {
                $newHash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmtPass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtPass->execute([$newHash, $admin_id]);
                $success_msg = "Đổi mật khẩu thành công!";
            } else {
                $error_msg = "Mật khẩu hiện tại không chính xác!";
            }
        } else {
            $error_msg = "Mật khẩu mới và xác nhận mật khẩu không khớp!";
        }
    } else {
        $error_msg = "Vui lòng nhập đầy đủ các trường đổi mật khẩu!";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title data-i18n-ui>Admin - Cài đặt</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">

<style>
body{margin:0;background:#f5f8f7;color:#173232;font-family:var(--font-body)}
.sidebar{position:fixed;width:220px;height:100vh;background:#073b3a;color:white;padding:20px 12px; z-index: 100;}
.brand{font-size:17px;font-weight:600;margin-bottom:25px}
.menu{display:block;color:#d5e5e3;text-decoration:none;padding:10px 12px;border-radius:7px;margin:4px 0;font-size:14px}
.menu i{margin-right:9px}
.menu:hover,.menu.active{background:#0d7771;color:white}
.main{margin-left:220px}
.topbar{height:62px;background:white;border-bottom:1px solid #ddd;display:flex;justify-content:flex-end;align-items:center;padding:0 25px}
.user{font-size:16px;font-weight:500}
.content{padding:25px}
.title{margin-bottom:20px}
.title h2{font-size:24px;margin:0}
.title p{font-size:13px;color:#788;margin:5px 0}

.toolbar{background:white;padding:15px;border-radius:9px;margin-bottom:15px}
.table-box{background:white;border-radius:9px;padding:18px}
table{font-size:13px}
thead th{background:#f7faf9!important;color:#667;padding:12px!important}
tbody td{padding:12px!important;vertical-align:middle}

.avatar{width:35px;height:35px;border-radius:50%;background:#dff1ee;color:#08766f;display:inline-flex;align-items:center;justify-content:center;font-weight:600;margin-right:8px}

.badge-active{background:#def3e8;color:#21784c}
.badge-banned{background:#ffe1e1;color:#a72f2f}

.btn-admin{background:#0d8f86;color:white;border:0}
.btn-admin:hover{background:#08766f;color:white}

@media(max-width:900px){
.sidebar{position:static;width:100%;height:auto}
.main{margin-left:0}
}
</style>
</head>

<body>

<div class="admin-layout">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand" style="font-size: 17px; font-weight: 600; margin-bottom: 25px;">
        <i class="bi bi-compass"></i> TripConnect Admin
    </div>

    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : ''; ?>" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i><span data-i18n-ui>Dashboard</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-reports.php' ? 'active' : ''; ?>" href="admin-reports.php"><i class="bi bi-bar-chart me-2"></i><span data-i18n-ui>Báo cáo</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-users.php' ? 'active' : ''; ?>" href="admin-users.php"><i class="bi bi-people me-2"></i><span data-i18n-ui>Người dùng</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-trips.php' ? 'active' : ''; ?>" href="admin-trips.php"><i class="bi bi-map me-2"></i><span data-i18n-ui>Lịch trình</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-comments.php' ? 'active' : ''; ?>" href="admin-comments.php"><i class="bi bi-chat-left-text me-2"></i><span data-i18n-ui>Bình luận</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-tags.php' ? 'active' : ''; ?>" href="admin-tags.php"><i class="bi bi-tags me-2"></i><span data-i18n-ui>Tag </span>&<span data-i18n-ui> sở thích</span></a>
<a class="menu" href="location-catalog.php"><span data-i18n-ui>Địa điểm</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-logs.php' ? 'active' : ''; ?>" href="admin-logs.php"><i class="bi bi-journal-text me-2"></i><span data-i18n-ui>Nhật ký</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-settings.php' ? 'active' : ''; ?>" href="admin-settings.php"><i class="bi bi-gear me-2"></i><span data-i18n-ui>Cài đặt</span></a>

    <form method="post" action="../logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="menu mt-4 border-0 bg-transparent w-100 text-start"><i class="bi bi-box-arrow-right me-2"></i><span data-i18n-ui>Đăng xuất</span></button></form>
</aside>

<!-- MAIN -->
<main class="main">

<header class="topbar">
    <div class="user">
        <i class="bi bi-person-circle me-1"></i>
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
    </div>
</header>

<div class="content">

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="mb-4">
    <h4 class="mb-1"><span data-i18n-ui>Cài đặt hệ thống</span></h4>
    <p class="text-muted mb-0"><span data-i18n-ui>Quản lý thông tin và thiết lập cơ bản của TripConnect</span></p>
</div>

<!-- THÔNG TIN WEBSITE -->
<div class="card p-4 mb-4">
    <h5 class="card-title mb-4"><i class="bi bi-globe2 me-2"></i><span data-i18n-ui> Thông tin website</span></h5>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label"><span data-i18n-ui>Tên website</span></label>
            <input class="form-control" value="TripConnect" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label"><span data-i18n-ui>Email liên hệ hệ thống</span></label>
            <input type="email" class="form-control" value="contact@tripconnect.com" readonly>
        </div>
        <div class="col-12">
            <label class="form-label"><span data-i18n-ui>Mô tả website</span></label>
            <textarea class="form-control" rows="3" readonly>Nền tảng chia sẻ và gợi ý lịch trình du lịch tích hợp Polyglot Persistence và PHP MVC thuần.</textarea>
        </div>
    </div>
    <div class="text-muted small mt-2"><i class="bi bi-info-circle"></i><span data-i18n-ui> Thông tin cấu hình website tĩnh được quản lý trực tiếp qua mã nguồn hệ thống.</span></div>
</div>

<!-- TÀI KHOẢN ADMIN (Cập nhật thực tế vào bảng users) -->
<div class="card p-4 mb-4">
    <h5 class="card-title mb-4"><i class="bi bi-person-gear me-2"></i><span data-i18n-ui> Tài khoản Admin</span></h5>
    <form action="admin-settings.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminSettingsCsrf); ?>">
        <input type="hidden" name="action" value="update_admin">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><span data-i18n-ui>Tên Admin</span></label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($adminInfo['full_name']); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><span data-i18n-ui>Email</span></label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($adminInfo['email']); ?>" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success mt-3">
            <i class="bi bi-check-lg me-1"></i><span data-i18n-ui> Lưu thông tin Admin
        </span></button>
    </form>
</div>

<!-- ĐỔI MẬT KHẨU -->
<div class="card p-4 mb-4">
    <h5 class="card-title mb-4"><i class="bi bi-shield-lock me-2"></i><span data-i18n-ui> Đổi mật khẩu</span></h5>
    <form action="admin-settings.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminSettingsCsrf); ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><span data-i18n-ui>Mật khẩu hiện tại</span></label>
                <input type="password" name="old_password" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><span data-i18n-ui>Mật khẩu mới</span></label>
                <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>
            <div class="col-md-4">
                <label class="form-label"><span data-i18n-ui>Xác nhận mật khẩu</span></label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success mt-3">
            <i class="bi bi-key me-1"></i><span data-i18n-ui> Đổi mật khẩu
        </span></button>
    </form>
</div>

<!-- CÀI ĐẶT HỆ THỐNG -->
<div class="card p-4 mb-4">
    <h5 class="card-title mb-4"><i class="bi bi-sliders me-2"></i><span data-i18n-ui> Cài đặt hệ thống</span></h5>
    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
        <div>
            <b><span data-i18n-ui>Cho phép đăng ký tài khoản</span></b>
            <div class="text-muted small"><span data-i18n-ui>Người dùng mới có thể tạo tài khoản trên hệ thống</span></div>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" checked disabled>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
        <div>
            <b><span data-i18n-ui>Cho phép bình luận lịch trình</span></b>
            <div class="text-muted small"><span data-i18n-ui>Thành viên có thể tương tác bình luận vào các bài viết</span></div>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" checked disabled>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center py-3">
        <div>
            <b><span data-i18n-ui>Nhật ký hoạt động Admin</span></b>
            <div class="text-muted small"><span data-i18n-ui>Ghi log thao tác quản trị vào cơ sở dữ liệu</span></div>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" checked disabled>
        </div>
    </div>
</div>

<!-- KHU VỰC NGUY HIỂM -->
<div class="card p-4">
    <h5 class="card-title text-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i><span data-i18n-ui> Khu vực quản lý dữ liệu</span></h5>
    <p class="text-muted"><span data-i18n-ui>Không có thao tác khôi phục dữ liệu mẫu. Trạng thái cơ sở dữ liệu được kiểm tra bằng công cụ vận hành riêng.</span></p>
    <button type="button" class="btn btn-outline-secondary" disabled>
        <i class="bi bi-database-check me-1"></i><span data-i18n-ui> Kiểm tra trạng thái DB
    </span></button>
</div>

</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/i18n.js?v=10"></script>
</body>
</html>
