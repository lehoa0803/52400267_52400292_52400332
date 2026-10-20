<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
// Kiểm tra quyền Admin (nếu chưa đăng nhập hoặc không phải admin thì đá về trang login)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    // Để tiện test nhanh, nếu bạn đã có trang login riêng, hãy chuyển hướng về login.php
    // header("Location: login.php");
    // exit();
}

// Xử lý Thêm người dùng mới
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Mã hóa mật khẩu an toàn
    $role = $_POST['role'] ?? 'member';

    if (!empty($full_name) && !empty($email) && !empty($_POST['password'])) {
        try {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$full_name, $email, $password, $role]);
            $success_msg = "Thêm người dùng thành công!";
        } catch (PDOException $e) {
            $error_msg = "Lỗi: Email này có thể đã tồn tại trong hệ thống.";
        }
    } else {
        $error_msg = "Vui lòng điền đầy đủ thông tin!";
    }
}

// Xử lý Khóa / Mở khóa tài khoản (nếu có yêu cầu gửi qua GET)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'ban') {
        $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
        $stmt->execute([$target_id]);
    } elseif ($action === 'unban') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$target_id]);
    }
    header("Location: admin-users.php");
    exit();
}

// Lấy danh sách toàn bộ người dùng từ Database
try {
    $stmtUsers = $conn->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý người dùng - TripConnect Admin</title>

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

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand" style="font-size: 17px; font-weight: 600; margin-bottom: 25px;">
        <i class="bi bi-compass"></i> TripConnect Admin
    </div>

    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : ''; ?>" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-reports.php' ? 'active' : ''; ?>" href="admin-reports.php"><i class="bi bi-bar-chart me-2"></i>Báo cáo</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-users.php' ? 'active' : ''; ?>" href="admin-users.php"><i class="bi bi-people me-2"></i>Người dùng</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-trips.php' ? 'active' : ''; ?>" href="admin-trips.php"><i class="bi bi-map me-2"></i>Lịch trình</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-comments.php' ? 'active' : ''; ?>" href="admin-comments.php"><i class="bi bi-chat-left-text me-2"></i>Bình luận</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-locations.php' ? 'active' : ''; ?>" href="admin-locations.php"><i class="bi bi-geo-alt me-2"></i>Địa điểm</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-tags.php' ? 'active' : ''; ?>" href="admin-tags.php"><i class="bi bi-tags me-2"></i>Tag & sở thích</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-logs.php' ? 'active' : ''; ?>" href="admin-logs.php"><i class="bi bi-journal-text me-2"></i>Nhật ký</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-settings.php' ? 'active' : ''; ?>" href="admin-settings.php"><i class="bi bi-gear me-2"></i>Cài đặt</a>

    <a class="menu mt-4" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a>
</aside>

<!-- MAIN -->
<main class="main">
<header class="topbar">
<div class="user">
<i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Quản trị viên'); ?>
</div>
</header>

<section class="content">

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

<div class="title d-flex justify-content-between align-items-center">
<div>
<h2>Quản lý người dùng</h2>
<p>Quản lý tài khoản và trạng thái người dùng trực tiếp từ cơ sở dữ liệu</p>
</div>
<button class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#addUser">
<i class="bi bi-plus-lg me-1"></i> Thêm người dùng
</button>
</div>

<!-- SEARCH + FILTER -->
<div class="toolbar">
<div class="row g-2">
<div class="col-md-6">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-search"></i></span>
<input type="text" id="search" class="form-control" placeholder="Tìm theo tên hoặc email...">
</div>
</div>
<div class="col-md-3">
<select id="statusFilter" class="form-select">
<option value="">Tất cả trạng thái</option>
<option value="active">Đang hoạt động</option>
<option value="banned">Đã khóa</option>
</select>
</div>
<div class="col-md-3">
<select id="roleFilter" class="form-select">
<option value="">Tất cả vai trò</option>
<option value="member">Member</option>
<option value="admin">Admin</option>
</select>
</div>
</div>
</div>

<!-- USER TABLE -->
<div class="table-box">
<div class="d-flex justify-content-between mb-3">
<h5 class="mb-0">Danh sách người dùng</h5>
<span class="text-muted small">Tổng: <b id="total"><?php echo count($users); ?></b> người</span>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th>Người dùng</th>
<th>Email</th>
<th>Vai trò</th>
<th>Ngày đăng ký</th>
<th>Trạng thái</th>
<th class="text-center">Thao tác</th>
</tr>
</thead>
<tbody id="userTable">
<?php if (!empty($users)): ?>
    <?php foreach ($users as $u): ?>
        <tr data-status="<?php echo htmlspecialchars($u['status'] ?? 'active'); ?>" data-role="<?php echo htmlspecialchars($u['role'] ?? 'member'); ?>">
        <td>
            <span class="avatar"><?php echo strtoupper(mb_substr($u['full_name'] ?? 'U', 0, 1)); ?></span>
            <?php echo htmlspecialchars($u['full_name']); ?>
        </td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><?php echo ucfirst(htmlspecialchars($u['role'])); ?></td>
        <td><?php echo isset($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : 'N/A'; ?></td>
        <td>
            <?php if (($u['status'] ?? 'active') === 'banned'): ?>
                <span class="badge badge-banned">Đã khóa</span>
            <?php else: ?>
                <span class="badge badge-active">Đang hoạt động</span>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <?php if (($u['role'] ?? '') === 'admin'): ?>
                <button class="btn btn-sm btn-outline-secondary" disabled title="Không thể khóa tài khoản Admin chính">
                    <i class="bi bi-shield-check"></i>
                </button>
            <?php else: ?>
                <?php if (($u['status'] ?? 'active') === 'banned'): ?>
                    <a href="admin-users.php?action=unban&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-success" title="Mở khóa tài khoản">
                        <i class="bi bi-unlock"></i>
                    </a>
                <?php else: ?>
                    <a href="admin-users.php?action=ban&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger" title="Khóa tài khoản" onclick="return confirm('Bạn có chắc chắn muốn khóa tài khoản này?');">
                        <i class="bi bi-lock"></i>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có người dùng nào trong cơ sở dữ liệu.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</section>
</main>

<!-- ADD USER MODAL -->
<div class="modal fade" id="addUser" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Thêm người dùng mới</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form action="admin-users.php" method="POST">
<input type="hidden" name="action" value="add_user">
<div class="modal-body">
<div class="mb-3">
<label class="form-label">Họ và tên</label>
<input type="text" name="full_name" class="form-control" required>
</div>
<div class="modal-body px-0 py-0">
<div class="mb-3">
<label class="form-label">Email</label>
<input type="email" name="email" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label">Mật khẩu</label>
<input type="password" name="password" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label">Vai trò</label>
<select name="role" class="form-select">
<option value="member">Member</option>
<option value="admin">Admin</option>
</select>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
<button type="submit" class="btn btn-admin">Thêm người dùng</button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// TÌM KIẾM + LỌC TRỰC TIẾP TRÊN GIAO DIỆN
function filterUsers(){
    let search = document.getElementById("search").value.toLowerCase();
    let status = document.getElementById("statusFilter").value;
    let role = document.getElementById("roleFilter").value;
    let rows = document.querySelectorAll("#userTable tr");
    let count = 0;

    rows.forEach(row => {
        if (!row.dataset.status) return; // Bỏ qua dòng thông báo trống nếu có
        let text = row.innerText.toLowerCase();
        let okSearch = text.includes(search);
        let okStatus = !status || row.dataset.status === status;
        let okRole = !role || row.dataset.role === role;

        row.style.display = okSearch && okStatus && okRole ? "" : "none";
        if(okSearch && okStatus && okRole) count++;
    });

    document.getElementById("total").innerText = count;
}

document.getElementById("search").oninput = filterUsers;
document.getElementById("statusFilter").onchange = filterUsers;
document.getElementById("roleFilter").onchange = filterUsers;
</script>

</body>
</html>
