<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// Truy vấn danh sách nhật ký hoạt động kết hợp với bảng users để lấy tên thật của Admin
try {
    $sql = "SELECT l.*, u.full_name as admin_name 
            FROM activity_logs l 
            LEFT JOIN users u ON l.admin_id = u.id 
            ORDER BY l.created_at DESC";
    $stmt = $conn->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Nhật ký hoạt động</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">

<style>
body{margin:0;background:#f5f8f7;color:#173232;font-family:Arial}
.sidebar{position:fixed;width:220px;height:100vh;background:#073b3a;color:white;padding:20px 12px;z-index:100;}
.brand{font-size:17px;font-weight:bold;margin-bottom:25px}
.menu{display:block;color:#d5e5e3;text-decoration:none;padding:10px 12px;border-radius:7px;margin:4px 0;font-size:14px}
.menu i{margin-right:9px}
.menu:hover,.menu.active{background:#0d7771;color:white}
.main{margin-left:220px}
.topbar{height:62px;background:white;border-bottom:1px solid #ddd;display:flex;justify-content:flex-end;align-items:center;padding:0 25px}
.user{font-size:16px;font-weight:bold}
.content{padding:25px}
.title{margin-bottom:20px}
.title h2{font-size:24px;margin:0}
.title p{font-size:13px;color:#788; margin:5px 0}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}
.card{border:0;box-shadow:0 2px 8px #00000008}
.stat{padding:18px}
.stat small{color:#788}
.stat h3{margin:7px 0;font-size:25px}
.icon{float:right;background:#e3f3f1;color:#08766f;padding:10px;border-radius:8px}
.grid{display:grid;grid-template-columns:1.5fr 1fr;gap:15px;margin-top:15px}
.box{background:white;padding:18px;border-radius:9px}
.box h5{font-size:15px}
table{font-size:13px}

/* Tùy chỉnh màu sắc Badge thao tác giống ảnh mẫu */
.badge-login{background:#cff4fc!important;color:#055160!important}
.badge-create{background:#d1e7dd!important;color:#0f5132!important}
.badge-update{background:#fff3cd!important;color:#664d03!important}
.badge-delete{background:#f8d7da!important;color:#842029!important}
.badge-hide{background:#e2e3e5!important;color:#41464b!important}

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
    <div class="brand" style="font-size: 17px; font-weight: bold; margin-bottom: 25px;">
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
        <i class="bi bi-person-circle me-1"></i>
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
    </div>
</header>

<div class="content">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Nhật ký hoạt động</h4>
        <p class="text-muted mb-0">Theo dõi các thao tác được thực hiện trên hệ thống</p>
    </div>
    <button class="btn btn-outline-secondary" onclick="clearFilter()">
        <i class="bi bi-arrow-clockwise me-1"></i>Làm mới
    </button>
</div>

<!-- FILTER -->
<div class="card p-3 mb-4">
<div class="row g-2">
    <div class="col-md-5">
        <input id="search" class="form-control" placeholder="Tìm Admin, thao tác hoặc đối tượng...">
    </div>
    <div class="col-md-3">
        <select id="adminFilter" class="form-select">
            <option value="all">Tất cả Admin</option>
            <option value="Lệ Hoa">Lệ Hoa</option>
            <option value="Phương Ngân">Phương Ngân</option>
            <option value="Hầu Vy">Hầu Vy</option>
        </select>
    </div>
    <div class="col-md-4">
        <select id="actionFilter" class="form-select">
            <option value="all">Tất cả thao tác</option>
            <option value="login">Đăng nhập</option>
            <option value="create">Thêm mới</option>
            <option value="update">Cập nhật</option>
            <option value="delete">Xóa</option>
            <option value="hide">Ẩn / hiện</option>
        </select>
    </div>
</div>
</div>

<!-- LOG TABLE -->
<div class="card p-3">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
<tr>
    <th>Thời gian</th>
    <th>Admin</th>
    <th>Thao tác</th>
    <th>Đối tượng</th>
    <th>Mô tả</th>
    <th>IP</th>
</tr>
</thead>
<tbody id="logTable">
<?php if (!empty($logs)): ?>
    <?php foreach ($logs as $log): ?>
    <?php 
        $type = $log['action'] ?? '';
        $target = $log['target_type'] ?? 'Hệ thống';
        $desc = $log['description'] ?? 'Thao tác hệ thống';
        $ip = $log['ip_address'] ?? '127.0.0.1';
        
        $badgeClass = 'badge-secondary';
        $label = $type;
        if ($type === 'login') { $badgeClass = 'badge-login'; $label = 'Đăng nhập'; }
        elseif ($type === 'create') { $badgeClass = 'badge-create'; $label = 'Thêm mới'; }
        elseif ($type === 'update') { $badgeClass = 'badge-update'; $label = 'Cập nhật'; }
        elseif ($type === 'delete') { $badgeClass = 'badge-delete'; $label = 'Xóa'; }
        elseif ($type === 'hide') { $badgeClass = 'badge-hide'; $label = 'Ẩn / hiện'; }
    ?>
    <tr data-admin="<?php echo htmlspecialchars($log['admin_name'] ?? ''); ?>" data-action="<?php echo htmlspecialchars($type); ?>">
        <td><?php echo isset($log['created_at']) ? date('d/m/Y H:i', strtotime($log['created_at'])) : 'N/A'; ?></td>
        <td><b><?php echo htmlspecialchars($log['admin_name'] ?? 'Quản trị viên'); ?></b></td>
        <td>
            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($label); ?></span>
        </td>
        <td><?php echo htmlspecialchars($target); ?></td>
        <td><?php echo htmlspecialchars($desc); ?></td>
        <td><?php echo htmlspecialchars($ip); ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có nhật ký hoạt động nào.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const search = document.getElementById("search");
const admin = document.getElementById("adminFilter");
const action = document.getElementById("actionFilter");
const rows = document.querySelectorAll("#logTable tr");

function filterLogs(){
    const q = search.value.toLowerCase();
    rows.forEach(row => {
        if (!row.dataset.admin) return;
        const text = row.innerText.toLowerCase();
        const okSearch = text.includes(q);
        const okAdmin = admin.value === "all" || row.dataset.admin === admin.value;
        const okAction = action.value === "all" || row.dataset.action === action.value;

        row.style.display = (okSearch && okAdmin && okAction) ? "" : "none";
    });
}

search.oninput = filterLogs;
admin.onchange = filterLogs;
action.onchange = filterLogs;

function clearFilter(){
    search.value = "";
    admin.value = "all";
    action.value = "all";
    filterLogs();
}
</script>

</body>
</html>