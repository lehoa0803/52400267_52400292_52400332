<?php
session_start();
require_once '../config/database.php';
require_once '../config/admin_auth.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

// All state-changing admin actions use POST and CSRF.
$adminTripsCsrf = trip_csrf_token('admin_trips');
$actionMessage = '';
$actionError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $trip_id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
    $action=is_string($_POST['action'])?$_POST['action']:'';
    $q=$conn->prepare('SELECT status FROM trips WHERE id=?');$q->execute([$trip_id]);$targetStatus=$q->fetchColumn();

    if (!$trip_id || !is_string($_POST['csrf_token']??null) || !trip_verify_csrf('admin_trips', $_POST['csrf_token']) || $targetStatus===false || !in_array($action,['hide','restore','publish','delete'],true) || ($targetStatus==='deleted'&&$action!=='delete')) {
        $actionError = 'Yêu cầu quản trị không hợp lệ.';
    } elseif ($action === 'hide' || $action === 'restore') {
        try {
            $moderationStatus = $action === 'hide' ? 'hidden' : 'visible';
            $stmtModeration = $conn->prepare("UPDATE trips SET moderation_status = ? WHERE id = ? AND status <> 'deleted'");
            $stmtModeration->execute([$moderationStatus, $trip_id]);
            $actionMessage = $action === 'hide' ? 'Đã ẩn lịch trình khỏi khu vực công khai.' : 'Đã khôi phục lịch trình trong khu vực công khai.';
        } catch (Throwable $e) {
            error_log('[TripConnect admin-trips] moderation action failed: ' . $e->getMessage());
            $actionError = 'Không thể cập nhật trạng thái kiểm duyệt.';
        }
    } elseif ($action === 'publish') {
        try {
            $stmt = $conn->prepare("UPDATE trips SET status = 'published' WHERE id = ?");
            $stmt->execute([$trip_id]);
            $actionMessage = 'Đã xuất bản lịch trình.';
        } catch (Throwable $e) {
            error_log('[TripConnect admin-trips] publish failed: ' . $e->getMessage());
            $actionError = 'Không thể xuất bản lịch trình.';
        }
    } elseif ($action === 'delete') {
        try {
            $conn->beginTransaction();
            $stmtDelActs = $conn->prepare("DELETE FROM activities WHERE trip_day_id IN (SELECT id FROM trip_days WHERE trip_id = ?)");
            $stmtDelActs->execute([$trip_id]);

            $stmtDelDays = $conn->prepare("DELETE FROM trip_days WHERE trip_id = ?");
            $stmtDelDays->execute([$trip_id]);

            $stmtDelTrip = $conn->prepare("DELETE FROM trips WHERE id = ?");
            $stmtDelTrip->execute([$trip_id]);
            $conn->commit();
            $actionMessage = 'Đã xóa lịch trình.';
        } catch (Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log('[TripConnect admin-trips] action failed: ' . $e->getMessage());
            $actionError = 'Không thể thực hiện thao tác với lịch trình.';
        }
    }
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
    error_log('[TripConnect admin-trips] list failed: ' . $e->getMessage());
    $trips = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title data-i18n-ui>Quản lý lịch trình - TripConnect Admin</title>

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
<i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Quản trị viên'); ?>
</div>
</header>

<section class="content">
<?php if ($actionMessage): ?><div class="alert alert-success"><?php echo htmlspecialchars($actionMessage); ?></div><?php endif; ?>
<?php if ($actionError): ?><div class="alert alert-warning"><?php echo htmlspecialchars($actionError); ?></div><?php endif; ?>
<div class="title">
<h2><span data-i18n-ui>Quản lý lịch trình</span></h2>
<a class="btn btn-admin mb-2" href="admin-trip-covers.php"><span data-i18n-ui>Bảo trì ảnh bìa hàng loạt</span></a>
<p><span data-i18n-ui>Quản lý và kiểm duyệt các lịch trình thực tế trên TripConnect</span></p>
</div>

<!-- SEARCH + FILTER -->
<div class="toolbar">
<div class="row g-2">
<div class="col-md-6">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-search"></i></span>
<input type="text" id="search" class="form-control" placeholder="Tìm theo tên lịch trình hoặc người tạo..." data-i18n-attrs>
</div>
</div>
<div class="col-md-3">
<select id="statusFilter" class="form-select">
<option value="" data-i18n-ui>Tất cả trạng thái</option>
<option value="published" data-i18n-ui>Đã xuất bản</option>
<option value="draft" data-i18n-ui>Bản nháp</option>
<option value="hidden" data-i18n-ui>Đã ẩn kiểm duyệt</option>
</select>
</div>
<div class="col-md-3">
<select id="sort" class="form-select">
<option value="" data-i18n-ui>Sắp xếp</option>
<option value="new" data-i18n-ui>Mới nhất</option>
<option value="old" data-i18n-ui>Cũ nhất</option>
</select>
</div>
</div>
</div>

<!-- TABLE -->
<div class="table-box">
<div class="d-flex justify-content-between mb-3">
<h5 class="mb-0"><span data-i18n-ui>Danh sách lịch trình</span></h5>
<span class="text-muted small"><span data-i18n-ui>Tổng: </span><b id="total"><?php echo count($trips); ?></b><span data-i18n-ui> lịch trình</span></span>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th><span data-i18n-ui>Lịch trình</span></th>
<th><span data-i18n-ui>Người tạo</span></th>
<th><span data-i18n-ui>Địa điểm</span></th>
<th><span data-i18n-ui>Ngày tạo</span></th>
<th><span data-i18n-ui>Trạng thái</span></th>
<th class="text-center"><span data-i18n-ui>Thao tác</span></th>
</tr>
</thead>
<tbody id="tripTable">
<?php if (!empty($trips)): ?>
    <?php foreach ($trips as $t): ?>
        <tr data-status="<?php echo htmlspecialchars($t['status'] ?? 'published'); ?>" data-moderation-status="<?php echo htmlspecialchars($t['moderation_status'] ?? 'visible'); ?>">
        <td>
            <?php
                // Trang admin nằm trong thư mục admin/ nên đường dẫn ảnh tương đối phải thêm ../
                $coverImg = $t['cover_image'] ?? '';
                if ($coverImg !== '' && !preg_match('#^(https?:)?//#i', $coverImg)) {
                    $coverImg = '../' . ltrim($coverImg, '/');
                }
            ?>
            <?php if ($coverImg !== ''): ?>
                <img class="trip-img" src="<?php echo htmlspecialchars($coverImg); ?>" alt="Ảnh" data-i18n-attrs>
            <?php endif; ?>
            <strong><?php echo htmlspecialchars($t['title']); ?></strong>
        </td>
        <td><?php echo htmlspecialchars($t['author_name'] ?? 'Thành viên'); ?></td>
        <td><?php echo htmlspecialchars($t['location_name'] ?? 'Việt Nam'); ?></td>
        <td><?php echo isset($t['created_at']) ? date('d/m/Y', strtotime($t['created_at'])) : 'N/A'; ?></td>
        <td>
            <?php
                $status = $t['status'] ?? 'published';
                $moderationStatus = $t['moderation_status'] ?? 'visible';
                if ($moderationStatus === 'hidden') {
                    echo '<span class="badge badge-hidden" data-i18n-ui>Đã ẩn kiểm duyệt</span>';
                } elseif ($status === 'published') {
                    echo '<span class="badge badge-published" data-i18n-ui>Đã xuất bản</span>';
                } elseif ($status === 'draft') {
                    echo '<span class="badge badge-draft" data-i18n-ui>Bản nháp</span>';
                } elseif ($status === 'deleted') {
                    echo '<span class="badge badge-hidden" data-i18n-ui>Đã xóa</span>';
                } else {
                    echo '<span class="badge badge-hidden" data-i18n-ui>Không xác định</span>';
                }
            ?>
        </td>
        <td class="text-center">
           <a href="../trip-detail.php?id=<?php echo $t['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Xem chi tiết" data-i18n-attrs>
    <i class="bi bi-eye"></i>
</a>

            <?php if ($status === 'draft'): ?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTripsCsrf); ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>"><button class="btn btn-sm btn-outline-success" title="Xuất bản" data-i18n-attrs><i class="bi bi-eye"></i></button></form><?php endif; ?>
            <?php if ($status !== 'deleted'): ?><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTripsCsrf); ?>"><input type="hidden" name="action" value="<?php echo $moderationStatus === 'hidden' ? 'restore' : 'hide'; ?>"><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>"><button class="btn btn-sm btn-outline-secondary" title="<?php echo $moderationStatus === 'hidden' ? 'Khôi phục công khai' : 'Ẩn kiểm duyệt'; ?>"><i class="bi bi-<?php echo $moderationStatus === 'hidden' ? 'eye' : 'eye-slash'; ?>"></i></button></form><?php endif; ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa vĩnh viễn lịch trình này?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTripsCsrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>"><button class="btn btn-sm btn-outline-danger" title="Xóa lịch trình" data-i18n-attrs><i class="bi bi-trash"></i></button></form>
        </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4"><span data-i18n-ui>Chưa có lịch trình nào trong cơ sở dữ liệu.</span></td></tr>
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
        const okStatus = statusFilter.value === ""
            || row.dataset.status === statusFilter.value
            || (statusFilter.value === "hidden" && row.dataset.moderationStatus === "hidden");
        row.style.display = (okSearch && okStatus) ? "" : "none";
    });
}

search.oninput = filterTrips;
statusFilter.onchange = filterTrips;
</script>

<script src="../assets/i18n.js?v=10"></script>
</body>
</html>
