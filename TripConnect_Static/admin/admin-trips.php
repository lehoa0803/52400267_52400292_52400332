<?php
// Bật hiển thị lỗi chi tiết để bắt bệnh
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

// Xử lý thay đổi trạng thái (Ẩn / Xuất bản / Xóa)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $trip_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'hide') {
        $stmt = $conn->prepare("UPDATE trips SET status = 'hidden' WHERE id = ?");
        $stmt->execute([$trip_id]);
    } elseif ($action === 'publish') {
        $stmt = $conn->prepare("UPDATE trips SET status = 'published' WHERE id = ?");
        $stmt->execute([$trip_id]);
    } elseif ($action === 'delete') {
        try {
            $stmtDelActs = $conn->prepare("DELETE FROM activities WHERE trip_day_id IN (SELECT id FROM trip_days WHERE trip_id = ?)");
            $stmtDelActs->execute([$trip_id]);

            $stmtDelDays = $conn->prepare("DELETE FROM trip_days WHERE trip_id = ?");
            $stmtDelDays->execute([$trip_id]);

            $stmtDelTrip = $conn->prepare("DELETE FROM trips WHERE id = ?");
            $stmtDelTrip->execute([$trip_id]);
        } catch (Exception $e) {
            // Bỏ qua lỗi khóa ngoại nếu chưa có bảng chi tiết
        }
    }

    header("Location: admin-trips.php");
    exit();
}

// Truy vấn danh sách toàn bộ lịch trình từ database kết hợp thông tin người dùng và địa điểm
try {
    $sql = "SELECT t.*, u.full_name as author_name, l.name as location_name 
            FROM trips t 
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN locations l ON t.location_id = l.id 
            ORDER BY t.id DESC";
    $stmt = $conn->query($sql);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Dữ liệu mẫu dự phòng nếu bảng trips chưa có trong database
    $trips = [
        ['id' => 1, 'title' => 'Đà Lạt 3 ngày 2 đêm', 'author_name' => 'Nguyễn An', 'location_name' => 'Lâm Đồng', 'created_at' => '2026-09-25 10:00:00', 'status' => 'published'],
        ['id' => 2, 'title' => 'Đà Nẵng - Hội An 4 ngày', 'author_name' => 'Trần Minh', 'location_name' => 'Quảng Nam', 'created_at' => '2026-09-24 14:30:00', 'status' => 'published'],
        ['id' => 3, 'title' => 'Sapa mùa lúa chín', 'author_name' => 'Lê Linh', 'location_name' => 'Lào Cai', 'created_at' => '2026-09-23 09:15:00', 'status' => 'draft']
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý lịch trình - TripConnect Admin</title>

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
.user{font-size:16px;font-weight:500}
.content{padding:25px}
.title{margin-bottom:20px}
.title h2{font-size:24px;margin:0}
.title p{font-size:13px;color:#788;margin:5px 0}

.toolbar,.table-box{background:white;padding:18px;border-radius:9px}
.toolbar{margin-bottom:15px}

table{font-size:13px}
thead th{background:#f7faf9!important;color:#667;padding:12px!important}
tbody td{padding:12px!important;vertical-align:middle}

.trip-img{
    width:55px;height:40px;object-fit:cover;
    border-radius:6px;margin-right:8px
}

.badge-published{background:#def3e8;color:#21784c}
.badge-draft{background:#fff1cf;color:#946900}
.badge-hidden{background:#ffe1e1;color:#a72f2f}

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
<div class="title">
<h2>Quản lý lịch trình</h2>
<p>Quản lý và kiểm duyệt các lịch trình thực tế trên TripConnect</p>
</div>

<!-- SEARCH + FILTER -->
<div class="toolbar">
<div class="row g-2">
<div class="col-md-6">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-search"></i></span>
<input type="text" id="search" class="form-control" placeholder="Tìm theo tên lịch trình hoặc người tạo...">
</div>
</div>
<div class="col-md-3">
<select id="statusFilter" class="form-select">
<option value="">Tất cả trạng thái</option>
<option value="published">Đã xuất bản</option>
<option value="draft">Bản nháp</option>
<option value="hidden">Đã ẩn</option>
</select>
</div>
<div class="col-md-3">
<select id="sort" class="form-select">
<option value="">Sắp xếp</option>
<option value="new">Mới nhất</option>
<option value="old">Cũ nhất</option>
</select>
</div>
</div>
</div>

<!-- TABLE -->
<div class="table-box">
<div class="d-flex justify-content-between mb-3">
<h5 class="mb-0">Danh sách lịch trình</h5>
<span class="text-muted small">Tổng: <b id="total"><?php echo count($trips); ?></b> lịch trình</span>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th>Lịch trình</th>
<th>Người tạo</th>
<th>Địa điểm</th>
<th>Ngày tạo</th>
<th>Trạng thái</th>
<th class="text-center">Thao tác</th>
</tr>
</thead>
<tbody id="tripTable">
<?php if (!empty($trips)): ?>
    <?php foreach ($trips as $t): ?>
        <tr data-status="<?php echo htmlspecialchars($t['status'] ?? 'published'); ?>">
        <td>
            <?php
                // Trang admin nằm trong thư mục admin/ nên đường dẫn ảnh tương đối phải thêm ../
                $coverImg = $t['cover_image'] ?? '';
                if ($coverImg !== '' && !preg_match('#^(https?:)?//#i', $coverImg)) {
                    $coverImg = '../' . ltrim($coverImg, '/');
                }
            ?>
            <?php if ($coverImg !== ''): ?>
                <img class="trip-img" src="<?php echo htmlspecialchars($coverImg); ?>" alt="Ảnh">
            <?php endif; ?>
            <strong><?php echo htmlspecialchars($t['title']); ?></strong>
        </td>
        <td><?php echo htmlspecialchars($t['author_name'] ?? 'Thành viên'); ?></td>
        <td><?php echo htmlspecialchars($t['location_name'] ?? 'Việt Nam'); ?></td>
        <td><?php echo isset($t['created_at']) ? date('d/m/Y', strtotime($t['created_at'])) : 'N/A'; ?></td>
        <td>
            <?php 
                $status = $t['status'] ?? 'published';
                if ($status === 'published') {
                    echo '<span class="badge badge-published">Đã xuất bản</span>';
                } elseif ($status === 'draft') {
                    echo '<span class="badge badge-draft">Bản nháp</span>';
                } else {
                    echo '<span class="badge badge-hidden">Đã ẩn</span>';
                }
            ?>
        </td>
        <td class="text-center">
           <a href="../trip-detail.php?id=<?php echo $t['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
    <i class="bi bi-eye"></i>
</a>

            <?php if ($status === 'hidden'): ?>
    <a href="admin-trips.php?action=publish&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-success" title="Hiển thị lại">
        <i class="bi bi-eye"></i>
    </a>
<?php else: ?>
    <a href="admin-trips.php?action=hide&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-warning" title="Ẩn bài viết" onclick="return confirm('Bạn có chắc muốn ẩn lịch trình này?');">
        <i class="bi bi-eye-slash"></i>
    </a>
<?php endif; ?>

<a href="admin-trips.php?action=delete&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-danger" title="Xóa lịch trình" onclick="return confirm('Bạn có chắc muốn xóa vĩnh viễn lịch trình này?');">
    <i class="bi bi-trash"></i>
</a>
        </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có lịch trình nào trong cơ sở dữ liệu.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tìm kiếm và lọc danh sách lịch trình
const search = document.getElementById("search");
const statusFilter = document.getElementById("statusFilter");
const rows = document.querySelectorAll("#tripTable tr");

function filterTrips() {
    const q = search.value.toLowerCase();
    rows.forEach(row => {
        if (!row.dataset.status) return;
        const text = row.innerText.toLowerCase();
        const okSearch = text.includes(q);
        const okStatus = statusFilter.value === "" || row.dataset.status === statusFilter.value;
        row.style.display = (okSearch && okStatus) ? "" : "none";
    });
}

search.oninput = filterTrips;
statusFilter.onchange = filterTrips;
</script>

</body>
</html>
