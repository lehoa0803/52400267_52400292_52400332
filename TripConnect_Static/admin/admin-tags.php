<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit();
}
$success_msg = '';
$error_msg = '';
$adminTagsCsrf = trip_csrf_token('admin_tags');

// 1. Xử lý Thêm hoặc Cập nhật Tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!trip_verify_csrf('admin_tags', (string) ($_POST['csrf_token'] ?? ''))) {
        $error_msg = 'Yêu cầu quản trị không hợp lệ.';
    } else {
    $action = $_POST['action'];
    $tag_name = trim($_POST['tag_name'] ?? '');
    $tag_type = trim($_POST['tag_type'] ?? 'destination');
    $description = trim($_POST['description'] ?? '');

    if ($action === 'add') {
        if (!empty($tag_name)) {
            try {
                // Giả định bảng tags của bạn có các cột: name, type, description, status (active/hidden)
                $stmt = $conn->prepare("INSERT INTO tags (name, type, description, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$tag_name, $tag_type, $description]);
                $success_msg = "Thêm tag mới thành công!";
            } catch (PDOException $e) {
                // Nếu bảng tags chưa có trong DB, đoạn này sẽ bắt lỗi hoặc bạn có thể tạo bảng trước
                $error_msg = "Lỗi khi thêm tag (Có thể bảng tags chưa tồn tại trong cơ sở dữ liệu).";
            }
        } else {
            $error_msg = "Vui lòng nhập tên tag!";
        }
    }
    }
}

// 2. Xử lý thay đổi trạng thái bằng POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tag_action'], $_POST['id'])) {
    $tag_id = (int) $_POST['id'];
    $act = (string) $_POST['tag_action'];
    if (!trip_verify_csrf('admin_tags', (string) ($_POST['csrf_token'] ?? ''))) {
        $error_msg = 'Yêu cầu quản trị không hợp lệ.';
    } else {

    if ($act === 'hide') {
        $stmt = $conn->prepare("UPDATE tags SET status = 'hidden' WHERE id = ?");
        $stmt->execute([$tag_id]);
    } elseif ($act === 'show') {
        $stmt = $conn->prepare("UPDATE tags SET status = 'active' WHERE id = ?");
        $stmt->execute([$tag_id]);
    }
    }
}

// 3. Lấy danh sách tag và đếm số lịch trình thực tế từ cơ sở dữ liệu
try {
    $sql = "SELECT t.*,
                   COUNT(DISTINCT tt.trip_id) as usage_count
            FROM tags t
            LEFT JOIN trip_tags tt ON t.id = tt.tag_id
            GROUP BY t.id
            ORDER BY t.id DESC";
    $stmt = $conn->query($sql);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[TripConnect admin-tags] list failed: ' . $e->getMessage());
    $tags = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý Tag & sở thích - TripConnect Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">

<style>
body{margin:0;background:#f5f8f7;color:#173232;font-family:var(--font-body)}
.sidebar{position:fixed;width:220px;height:100vh;background:#073b3a;color:white;padding:20px 12px;z-index:100;}
.brand{font-size:17px;font-weight:600;margin-bottom:25px}
.menu{display:block;color:#d5e5e3;text-decoration:none;padding:10px 12px;border-radius:7px;margin:4px 0;font-size:14px}
.menu i{margin-right:9px}
.menu:hover,.menu.active{background:#0d7771;color:white}
.main{margin-left:220px}
.topbar{height:62px;background:white;border-bottom:1px solid #ddd;display:flex;justify-content:flex-end;align-items:center;padding:0 25px}
.user{font-size:16px; font-weight:500}
.content{padding:25px}
.title{margin-bottom:20px}
.title h2{font-size:24px;margin:0}
.title p{font-size:13px;color:#788;margin:5px 0}

.toolbar,.table-box{background:white;padding:18px;border-radius:9px}
.toolbar{margin-bottom:15px}

table{font-size:13px}
thead th{background:#f7faf9!important;color:#667;padding:12px!important}
tbody td{padding:12px!important;vertical-align:middle}

.badge-published, .badge-active {
    background: #def3e8 !important;
    color: #21784c !important;
}.badge-draft{background:#fff1cf!important;color:#946900!important}
.badge-hidden{background:#ffe1e1!important;color:#a72f2f!important}
.badge-pending{background:#fff1cf!important;color:#946900!important}
.badge-resolved{background:#def3e8!important;color:#21784c!important}

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

    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : ''; ?>" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-reports.php' ? 'active' : ''; ?>" href="admin-reports.php"><i class="bi bi-bar-chart me-2"></i>Báo cáo</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-users.php' ? 'active' : ''; ?>" href="admin-users.php"><i class="bi bi-people me-2"></i>Người dùng</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-trips.php' ? 'active' : ''; ?>" href="admin-trips.php"><i class="bi bi-map me-2"></i>Lịch trình</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-comments.php' ? 'active' : ''; ?>" href="admin-comments.php"><i class="bi bi-chat-left-text me-2"></i>Bình luận</a>
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Tag & sở thích</h4>
        <p class="text-muted mb-0">Quản lý các tag và sở thích phục vụ tìm kiếm, phân loại và gợi ý</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tagModal">
        <i class="bi bi-plus-lg me-1"></i>Thêm Tag
    </button>
</div>

<!-- FILTER -->
<div class="card p-3 mb-4">
<div class="row g-2">
    <div class="col-md-6">
        <input id="search" class="form-control" placeholder="Tìm theo tên tag...">
    </div>
    <div class="col-md-3">
        <select id="typeFilter" class="form-select">
            <option value="all">Tất cả loại</option>
            <option value="destination">Điểm đến</option>
            <option value="activity">Hoạt động</option>
            <option value="style">Phong cách</option>
            <option value="food">Ẩm thực</option>
        </select>
    </div>
    <div class="col-md-3">
        <select id="statusFilter" class="form-select">
            <option value="all">Tất cả trạng thái</option>
            <option value="active">Đang hoạt động</option>
            <option value="hidden">Đã ẩn</option>
        </select>
    </div>
</div>
</div>

<!-- TABLE -->
<div class="card p-3">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
<tr>
    <th>Tên Tag</th>
    <th>Loại</th>
    <th>Số lịch trình</th>
    <th>Số người quan tâm</th>
    <th>Trạng thái</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody id="tagTable">
<?php if (!empty($tags)): ?>
    <?php foreach ($tags as $tag): ?>
    <tr data-type="<?php echo htmlspecialchars($tag['type'] ?? 'destination'); ?>" data-status="<?php echo htmlspecialchars($tag['status'] ?? 'active'); ?>">
        <td><b><?php echo htmlspecialchars($tag['name']); ?></b></td>
        <td>
            <?php
                $types = ['destination' => 'Điểm đến', 'activity' => 'Hoạt động', 'style' => 'Phong cách', 'food' => 'Ẩm thực'];
                echo $types[$tag['type'] ?? ''] ?? 'Khác';
            ?>
        </td>
        <td><?php echo intval($tag['usage_count'] ?? 0); ?></td>
        <td><?php echo intval($tag['followers_count'] ?? 0); ?></td>
        <td>
            <?php if (($tag['status'] ?? 'active') === 'hidden'): ?>
                <span class="badge badge-hidden">Đã ẩn</span>
            <?php else: ?>
                <span class="badge badge-active">Đang hoạt động</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if (($tag['status'] ?? 'active') === 'hidden'): ?>
                <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTagsCsrf); ?>"><input type="hidden" name="tag_action" value="show"><input type="hidden" name="id" value="<?php echo (int) $tag['id']; ?>"><button class="btn btn-outline-success action-btn" title="Hiển thị"><i class="bi bi-eye"></i></button></form>
            <?php else: ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn ẩn tag này?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTagsCsrf); ?>"><input type="hidden" name="tag_action" value="hide"><input type="hidden" name="id" value="<?php echo (int) $tag['id']; ?>"><button class="btn btn-outline-danger action-btn" title="Ẩn"><i class="bi bi-eye-slash"></i></button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có tag nào trong hệ thống.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</main>
</div>

<!-- MODAL THÊM TAG -->
<div class="modal fade" id="tagModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form action="admin-tags.php" method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTagsCsrf); ?>">
<input type="hidden" name="action" value="add">
<div class="modal-header">
    <h5 class="modal-title">Thêm Tag mới</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label">Tên Tag</label>
        <input type="text" name="tag_name" class="form-control" placeholder="Ví dụ: Biển, Camping..." required>
    </div>
    <div class="mb-3">
        <label class="form-label">Loại</label>
        <select name="tag_type" class="form-select">
            <option value="destination">Điểm đến</option>
            <option value="activity">Hoạt động</option>
            <option value="style">Phong cách</option>
            <option value="food">Ẩm thực</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Mô tả</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Mô tả ngắn về Tag..."></textarea>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-success">Lưu Tag</button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// TÌM KIẾM + LỌC TRỰC TIẾP
const search = document.getElementById("search");
const typeFilter = document.getElementById("typeFilter");
const statusFilter = document.getElementById("statusFilter");
const rows = document.querySelectorAll("#tagTable tr");

function filterTags(){
    const q = search.value.toLowerCase();
    rows.forEach(row => {
        if (!row.dataset.status) return;
        const text = row.innerText.toLowerCase();
        const okSearch = text.includes(q);
        const okType = typeFilter.value === "all" || row.dataset.type === typeFilter.value;
        const okStatus = statusFilter.value === "all" || row.dataset.status === statusFilter.value;

        row.style.display = (okSearch && okType && okStatus) ? "" : "none";
    });
}

search.oninput = filterTags;
typeFilter.onchange = filterTags;
statusFilter.onchange = filterTags;
</script>

</body>
</html>
