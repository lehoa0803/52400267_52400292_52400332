<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
require_once '../config/admin_auth.php';
// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

$databaseConnected=true;$tripStats=['public'=>0,'hidden'=>0,'draft'=>0];$months=[];$monthCounts=[];$recentTrips=[];
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

    $tripStats=$conn->query("SELECT SUM(status='published' AND moderation_status='visible') AS public,SUM(moderation_status='hidden' AND status<>'deleted') AS hidden,SUM(status='draft') AS draft FROM trips")->fetch(PDO::FETCH_ASSOC);
    $start=new DateTimeImmutable($conn->query("SELECT DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn());$start=$start->modify('-5 months');
    $q=$conn->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,COUNT(*) AS n FROM users WHERE created_at>=? GROUP BY DATE_FORMAT(created_at,'%Y-%m')");$q->execute([$start->format('Y-m-d')]);$byMonth=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    for($i=0;$i<6;$i++){$key=$start->modify('+'.$i.' months')->format('Y-m');$months[]=$key;$monthCounts[]=(int)($byMonth[$key]??0);}
    $recentTrips=$conn->query("SELECT t.title,t.created_at,t.status,u.full_name,l.name AS destination FROM trips t JOIN users u ON u.id=t.user_id LEFT JOIN locations l ON l.id=t.location_id ORDER BY t.created_at DESC,t.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $databaseConnected=false;
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
<title data-i18n-ui>Admin Dashboard - TripConnect</title>

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
input,select,textarea{font:inherit;max-width:100%}details form{min-width:200px}</style>
</head>

<body>

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

<main class="main">

<header class="topbar">
    <div class="user">
        <i class="bi bi-person-circle me-1"></i>
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
    </div>
</header>

<section class="content">

<div class="title">
    <h2><span data-i18n-ui>Dashboard</span></h2>
    <p><span data-i18n-ui>Tổng quan hoạt động của hệ thống TripConnect kết nối MySQL</span></p>
</div>

<!-- THỐNG KÊ -->
<div class="stats">
    <div class="card stat">
        <span class="icon"><i class="bi bi-people"></i></span>
        <small><span data-i18n-ui>Tổng người dùng</span></small>
        <h3><span data-i18n-number><?php echo number_format($totalUsers); ?></span></h3>
        <small><span data-i18n-ui>Tài khoản trong database</span></small>
    </div>

    <div class="card stat">
        <span class="icon"><i class="bi bi-map"></i></span>
        <small><span data-i18n-ui>Tổng lịch trình</span></small>
        <h3><span data-i18n-number><?php echo number_format($totalTrips); ?></span></h3>
        <small><span data-i18n-ui>Công khai: </span><?= (int)$tripStats['public'] ?><span data-i18n-ui> / Ẩn: </span><?= (int)$tripStats['hidden'] ?><span data-i18n-ui> / Nháp: </span><?= (int)$tripStats['draft'] ?></small>
    </div>

    <div class="card stat">
        <span class="icon"><i class="bi bi-chat"></i></span>
        <small><span data-i18n-ui>Tổng bình luận</span></small>
        <h3><span data-i18n-number><?php echo number_format($totalComments); ?></span></h3>
        <small class="text-success"><span data-i18n-ui>Tương tác thực tế</span></small>
    </div>

    <div class="card stat">
        <span class="icon"><i class="bi bi-shield-check text-success"></i></span>
        <small><span data-i18n-ui>Trạng thái hệ thống</span></small>
        <h3 style="font-size: 20px; margin-top: 10px; color: #21784c;"><span data-i18n-ui><?= $databaseConnected?'Online':'Unavailable' ?></span></h3>
        <small><span data-i18n-ui>Database Connected</span></small>
    </div>
</div>

<div class="grid">
    <!-- BIỂU ĐỒ -->
    <div class="box">
        <h5><span data-i18n-ui>Người dùng mới</span></h5>
        <p class="text-muted small"><span data-i18n-ui>6 tháng gần đây</span></p>
        <div style="height: 250px;">
            <canvas id="userChart"></canvas><p class="small"><?php foreach($months as $i=>$month): ?><?= htmlspecialchars($month) ?>: <?= $monthCounts[$i] ?> &nbsp;<?php endforeach; ?></p>
        </div>
    </div>

    <!-- VIỆC CẦN XỬ LÝ -->
    <div class="box">
        <h5><span data-i18n-ui>Quản lý nhanh</span></h5>
        <div class="d-flex justify-content-between py-3 border-bottom">
            <span><i class="bi bi-people me-2"></i><span data-i18n-ui>Quản lý tài khoản</span></span>
            <a href="admin-users.php" class="btn btn-sm btn-outline-primary"><span data-i18n-ui>Xem</span></a>
        </div>
        <div class="d-flex justify-content-between py-3 border-bottom">
            <span><i class="bi bi-map me-2"></i><span data-i18n-ui>Kiểm duyệt lịch trình</span></span>
            <a href="admin-trips.php" class="btn btn-sm btn-outline-primary"><span data-i18n-ui>Xem</span></a>
        </div>
        <div class="d-flex justify-content-between py-3">
            <span><i class="bi bi-gear me-2"></i><span data-i18n-ui>Cài đặt hệ thống</span></span>
            <a href="admin-settings.php" class="btn btn-sm btn-outline-primary"><span data-i18n-ui>Cấu hình</span></a>
        </div>
    </div>
</div>

<div class="box mt-3"><h5><span data-i18n-ui>Lịch trình mới nhất</span></h5><div class="table-responsive"><table class="table"><thead><tr><th><span data-i18n-ui>Thời gian</span></th><th><span data-i18n-ui>Tác giả</span></th><th><span data-i18n-ui>Lịch trình</span></th><th><span data-i18n-ui>Điểm đến</span></th><th><span data-i18n-ui>Trạng thái</span></th></tr></thead><tbody><?php foreach($recentTrips as $recent): ?><tr><?php foreach(['created_at','full_name','title','destination','status'] as $key): ?><td><?= htmlspecialchars($recent[$key]??'',ENT_QUOTES,'UTF-8') ?></td><?php endforeach; ?></tr><?php endforeach; ?><?php if(!$recentTrips): ?><tr><td colspan="5"><span data-i18n-ui>Chưa có dữ liệu.</span></td></tr><?php endif; ?></tbody></table></div></div>
</section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
if(window.Chart)new Chart(document.getElementById("userChart"),{
    type:"line",
    data:{
        labels:<?= json_encode($months) ?>,
        datasets:[{
            label:"Người dùng",
            data:<?= json_encode($monthCounts) ?>,
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

<script src="../assets/i18n.js?v=10"></script>
</body>
</html>
