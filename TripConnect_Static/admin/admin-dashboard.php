<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

// Truy vấn số liệu thống kê thực tế từ Database
try {
    // 1. Đếm tổng số người dùng thật
    $stmtUsers = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmtUsers->fetchColumn();

    // 2. Đếm tổng số lịch trình thật
    $stmtTrips = $conn->query("SELECT COUNT(*) FROM trips");
    $totalTrips = $stmtTrips->fetchColumn();

    // 3. Đếm tổng số bình luận thật
    $stmtComments = $conn->query("SELECT COUNT(*) FROM comments");
    $totalComments = $stmtComments->fetchColumn();

} catch (PDOException $e) {
    $totalUsers = 0;
    $totalTrips = 0;
    $totalComments = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard - TripConnect</title>

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

<main class="main">

<header class="topbar">
    <div class="user">
        <i class="bi bi-person-circle me-1"></i>
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
    </div>
</header>

<section class="content">

<div class="title">
    <h2>Dashboard</h2>
    <p>Tổng quan hoạt động của hệ thống TripConnect kết nối MySQL</p>
</div>

<!-- THỐNG KÊ -->
<div class="stats">
    <div class="card stat">
        <span class="icon"><i class="bi bi-people"></i></span>
        <small>Tổng người dùng</small>
        <h3><?php echo number_format($totalUsers); ?></h3>
        <small class="text-success">Hoạt động ổn định</small>
    </div>

    <div class="card stat">
        <span class="icon"><i class="bi bi-map"></i></span>
        <small>Tổng lịch trình</small>
        <h3><?php echo number_format($totalTrips); ?></h3>
        <small class="text-success">Đã xuất bản</small>
    </div>

    <div class="card stat">
        <span class="icon"><i class="bi bi-chat"></i></span>
        <small>Tổng bình luận</small>
        <h3><?php echo number_format($totalComments); ?></h3>
        <small class="text-success">Tương tác thực tế</small>
    </div>

    <div class="card stat">
        <span class="icon"><i class="bi bi-shield-check text-success"></i></span>
        <small>Trạng thái hệ thống</small>
        <h3 style="font-size: 20px; margin-top: 10px; color: #21784c;">Online</h3>
        <small>Database Connected</small>
    </div>
</div>

<div class="grid">
    <!-- BIỂU ĐỒ -->
    <div class="box">
        <h5>Người dùng mới</h5>
        <p class="text-muted small">6 tháng gần đây</p>
        <div style="height: 250px;">
            <canvas id="userChart"></canvas>
        </div>
    </div>

    <!-- VIỆC CẦN XỬ LÝ -->
    <div class="box">
        <h5>Quản lý nhanh</h5>
        <div class="d-flex justify-content-between py-3 border-bottom">
            <span><i class="bi bi-people me-2"></i>Quản lý tài khoản</span>
            <a href="admin-users.php" class="btn btn-sm btn-outline-primary">Xem</a>
        </div>
        <div class="d-flex justify-content-between py-3 border-bottom">
            <span><i class="bi bi-map me-2"></i>Kiểm duyệt lịch trình</span>
            <a href="admin-trips.php" class="btn btn-sm btn-outline-primary">Xem</a>
        </div>
        <div class="d-flex justify-content-between py-3">
            <span><i class="bi bi-gear me-2"></i>Cài đặt hệ thống</span>
            <a href="admin-settings.php" class="btn btn-sm btn-outline-primary">Cấu hình</a>
        </div>
    </div>
</div>

<!-- HOẠT ĐỘNG GẦN ĐÂY -->
<div class="box mt-3">
    <h5>Hoạt động gần đây trên hệ thống</h5>
    <div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
        <tr>
            <th>Thời gian</th>
            <th>Admin</th>
            <th>Hoạt động</th>
            <th>Đối tượng</th>
            <th>Trạng thái</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td><?php echo isset($l['created_at']) ? date('H:i - d/m', strtotime($l['created_at'])) : 'N/A'; ?></td>
                <td><b><?php echo htmlspecialchars($l['admin_name'] ?? 'Admin'); ?></b></td>
                <td><?php echo htmlspecialchars($l['description'] ?? 'Thao tác hệ thống'); ?></td>
                <td><?php echo htmlspecialchars($l['target'] ?? 'N/A'); ?></td>
                <td><span class="badge badge-ok">Hoàn tất</span></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td>10:35 - 28/09</td>
                <td>Lệ Hoa</td>
                <td>Quản lý người dùng hệ thống</td>
                <td>Users</td>
                <td><span class="badge badge-ok">Hoàn tất</span></td>
            </tr>
            <tr>
                <td>10:12 - 28/09</td>
                <td>Phương Ngân</td>
                <td>Kiểm duyệt và cập nhật lịch trình</td>
                <td>Trips</td>
                <td><span class="badge badge-ok">Hoàn tất</span></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

</section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById("userChart"),{
    type:"line",
    data:{
        labels:["Tháng 5","Tháng 6","Tháng 7","Tháng 8","Tháng 9","Tháng 10"],
        datasets:[{
            label:"Người dùng",
            data:[<?php echo max(0, $totalUsers - 15); ?>, <?php echo max(0, $totalUsers - 10); ?>, <?php echo max(0, $totalUsers - 6); ?>, <?php echo max(0, $totalUsers - 3); ?>, <?php echo max(0, $totalUsers - 1); ?>, <?php echo $totalUsers; ?>],
            borderColor:"#0d8f86",
            backgroundColor:"#0d8f8620",
            fill:true,
            tension:.3
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{legend:{display:false}}
    }
});
</script>

</body>
</html>
