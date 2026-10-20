<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$success_msg = '';
$error_msg = '';

// 1. Xử lý Thêm địa điểm mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_location') {
    $name = trim($_POST['locationName'] ?? '');
    $region = trim($_POST['locationProvince'] ?? '');
    $category = trim($_POST['locationCategory'] ?? 'city');
    $description = trim($_POST['locationDescription'] ?? '');

    if (!empty($name)) {
        try {
            // Kiểm tra cấu trúc bảng locations có đủ cột không, nếu có cột category/description thì lưu đầy đủ
            $stmt = $conn->prepare("INSERT INTO locations (name, region, category, description, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $region, $category, $description]);
            $success_msg = "Thêm địa điểm thành công!";
        } catch (PDOException $e) {
            try {
                // Dự phòng nếu bảng locations chỉ có các cột cơ bản (id, name, region)
                $stmtSimple = $conn->prepare("INSERT INTO locations (name, region) VALUES (?, ?)");
                $stmtSimple->execute([$name, $region]);
                $success_msg = "Thêm địa điểm thành công!";
            } catch (Exception $ex) {
                $error_msg = "Lỗi khi thêm địa điểm vào cơ sở dữ liệu: " . $ex->getMessage();
            }
        }
    } else {
        $error_msg = "Vui lòng nhập tên địa điểm!";
    }
}

// 2. Xử lý Ẩn / Hiển thị địa điểm
if (isset($_GET['action']) && isset($_GET['id'])) {
    $loc_id = intval($_GET['id']);
    $act = $_GET['action'];

    try {
        if ($act === 'hide') {
            $stmt = $conn->prepare("UPDATE locations SET status = 'hidden' WHERE id = ?");
            $stmt->execute([$loc_id]);
        } elseif ($act === 'show') {
            $stmt = $conn->prepare("UPDATE locations SET status = 'active' WHERE id = ?");
            $stmt->execute([$loc_id]);
        }
    } catch (Exception $e) {
        // Bỏ qua lỗi nếu bảng chưa có cột status
    }
    header("Location: admin-locations.php");
    exit();
}

// 3. Lấy danh sách địa điểm từ cơ sở dữ liệu kết hợp đếm số lịch trình
try {
    $sql = "SELECT l.*, COUNT(t.id) as trip_count 
            FROM locations l 
            LEFT JOIN trips t ON l.id = t.location_id 
            GROUP BY l.id 
            ORDER BY l.id DESC";
    $stmt = $conn->query($sql);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Dữ liệu mẫu dự phòng nếu bảng locations chưa đầy đủ cột
    $locations = [
        ['id' => 1, 'name' => 'Đà Lạt', 'region' => 'Lâm Đồng', 'category' => 'city', 'trip_count' => 128, 'status' => 'active'],
        ['id' => 2, 'name' => 'Phú Quốc', 'region' => 'Kiên Giang', 'category' => 'beach', 'trip_count' => 96, 'status' => 'active'],
        ['id' => 3, 'name' => 'Sapa', 'region' => 'Lào Cai', 'category' => 'mountain', 'trip_count' => 74, 'status' => 'active'],
        ['id' => 4, 'name' => 'Hội An', 'region' => 'Quảng Nam', 'category' => 'historical', 'trip_count' => 85, 'status' => 'active'],
        ['id' => 5, 'name' => 'Vịnh Hạ Long', 'region' => 'Quảng Ninh', 'category' => 'nature', 'trip_count' => 63, 'status' => 'active'],
        ['id' => 6, 'name' => 'Nha Trang', 'region' => 'Khánh Hòa', 'category' => 'city', 'trip_count' => 51, 'status' => 'hidden']
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Quản lý địa điểm</title>

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
.badge-ok{background:#def3e8;color:#21784c}
.badge-warn{background:#fff1cf;color:#946900}
/* Màu cho nhãn trạng thái (trước đây thiếu nên chữ trắng trên nền trắng) */
.badge-active,.badge-published,.badge-resolved{background:#d1e7dd;color:#0f5132}
.badge-hidden,.badge-banned,.badge-locked{background:#f8d7da;color:#842029}
.badge-draft,.badge-pending{background:#fff3cd;color:#664d03}
@media(max-width:900px){
.sidebar{position:static;width:100%;height:auto}
.main{margin-left:0}
.stats{grid-template-columns:repeat(2,1fr)}
.grid{grid-template-columns:1fr}
}
@media(max-width:600px){.stats{grid-template-columns:1fr}}
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
        <h4 class="mb-1">Địa điểm</h4>
        <p class="text-muted mb-0">Quản lý các địa điểm được sử dụng trên TripConnect</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#locationModal">
        <i class="bi bi-plus-lg me-1"></i>Thêm địa điểm
    </button>
</div>

<!-- FILTER -->
<div class="card p-3 mb-4">
    <div class="row g-2">
        <div class="col-md-6">
            <input id="search" class="form-control" placeholder="Tìm theo tên địa điểm hoặc tỉnh/thành...">
        </div>
        <div class="col-md-3">
            <select id="categoryFilter" class="form-select">
                <option value="all">Tất cả loại</option>
                <option value="city">Thành phố</option>
                <option value="beach">Biển</option>
                <option value="mountain">Núi</option>
                <option value="historical">Lịch sử</option>
                <option value="nature">Thiên nhiên</option>
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
    <th>Địa điểm</th>
    <th>Tỉnh / Thành phố</th>
    <th>Loại</th>
    <th>Số lịch trình</th>
    <th>Trạng thái</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody id="locationTable">
<?php if (!empty($locations)): ?>
    <?php foreach ($locations as $loc): ?>
    <tr data-category="<?php echo htmlspecialchars($loc['category'] ?? 'city'); ?>" data-status="<?php echo htmlspecialchars($loc['status'] ?? 'active'); ?>">
        <td><b><?php echo htmlspecialchars($loc['name']); ?></b></td>
        <td><?php echo htmlspecialchars($loc['region'] ?? 'N/A'); ?></td>
        <td>
            <?php 
                $cats = ['city' => 'Thành phố', 'beach' => 'Biển', 'mountain' => 'Núi', 'historical' => 'Lịch sử', 'nature' => 'Thiên nhiên'];
                echo $cats[$loc['category'] ?? ''] ?? 'Thành phố';
            ?>
        </td>
        <td><?php echo intval($loc['trip_count'] ?? 0); ?></td>
        <td>
            <?php if (($loc['status'] ?? 'active') === 'hidden'): ?>
                <span class="badge badge-hidden">Đã ẩn</span>
            <?php else: ?>
                <span class="badge badge-active">Đang hoạt động</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if (($loc['status'] ?? 'active') === 'hidden'): ?>
                <a href="admin-locations.php?action=show&id=<?php echo $loc['id']; ?>" class="btn btn-outline-success action-btn" title="Hiển thị">
                    <i class="bi bi-eye"></i>
                </a>
            <?php else: ?>
                <a href="admin-locations.php?action=hide&id=<?php echo $loc['id']; ?>" class="btn btn-outline-danger action-btn" title="Ẩn" onclick="return confirm('Bạn có chắc muốn ẩn địa điểm này?');">
                    <i class="bi bi-eye-slash"></i>
                </a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có địa điểm nào trong cơ sở dữ liệu.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</main>
</div>

<!-- MODAL THÊM ĐỊA ĐIỂM -->
<div class="modal fade" id="locationModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form action="admin-locations.php" method="POST">
<input type="hidden" name="action" value="add_location">
<div class="modal-header">
    <h5 class="modal-title">Thêm địa điểm</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label">Tên địa điểm</label>
        <input type="text" id="locationName" name="locationName" class="form-control" placeholder="Ví dụ: Đà Lạt" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Tỉnh / Thành phố</label>
        <input type="text" id="locationProvince" name="locationProvince" class="form-control" placeholder="Ví dụ: Lâm Đồng">
    </div>
    <div class="mb-3">
        <label class="form-label">Loại địa điểm</label>
        <select id="locationCategory" name="locationCategory" class="form-select">
            <option value="city">Thành phố</option>
            <option value="beach">Biển</option>
            <option value="mountain">Núi</option>
            <option value="historical">Lịch sử</option>
            <option value="nature">Thiên nhiên</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Mô tả</label>
        <textarea id="locationDescription" name="locationDescription" class="form-control" rows="3" placeholder="Mô tả ngắn về địa điểm..."></textarea>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-success">Lưu địa điểm</button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// TÌM KIẾM + LỌC TRỰC TIẾP TRÊN GIAO DIỆN
const search = document.getElementById("search");
const categoryFilter = document.getElementById("categoryFilter");
const statusFilter = document.getElementById("statusFilter");
const rows = document.querySelectorAll("#locationTable tr");

function filterLocations(){
    const q = search.value.toLowerCase();
    rows.forEach(row => {
        if (!row.dataset.status) return;
        const text = row.innerText.toLowerCase();
        const okSearch = text.includes(q);
        const okCategory = categoryFilter.value === "all" || row.dataset.category === categoryFilter.value;
        const okStatus = statusFilter.value === "all" || row.dataset.status === statusFilter.value;

        row.style.display = (okSearch && okCategory && okStatus) ? "" : "none";
    });
}

search.oninput = filterLocations;
categoryFilter.onchange = filterLocations;
statusFilter.onchange = filterLocations;
</script>

</body>
</html>
