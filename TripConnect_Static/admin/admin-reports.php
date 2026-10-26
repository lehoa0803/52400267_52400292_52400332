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

// Truy vấn các số liệu thống kê thực tế từ Database
try {
    // 1. Tổng số người dùng
    $stmtUsers = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmtUsers->fetchColumn() ?: 0;

    // 2. Tổng số lịch trình
    $stmtTrips = $conn->query("SELECT COUNT(*) FROM trips");
    $totalTrips = $stmtTrips->fetchColumn() ?: 0;

    // 3. Tổng số bình luận (nếu có bảng comments)
    try {
        $stmtComments = $conn->query("SELECT COUNT(*) FROM comments");
        $totalComments = $stmtComments->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $totalComments = 0; // Dự phòng nếu chưa có bảng comments
    }

    // 4. Thống kê trạng thái lịch trình cho biểu đồ
    $stmtPublished = $conn->query("SELECT COUNT(*) FROM trips WHERE status = 'published' AND moderation_status = 'visible'");
    $countPublished = $stmtPublished->fetchColumn() ?: 0;

    $stmtDraft = $conn->query("SELECT COUNT(*) FROM trips WHERE status = 'draft'");
    $countDraft = $stmtDraft->fetchColumn() ?: 0;

    $stmtDeleted = $conn->query("SELECT COUNT(*) FROM trips WHERE status = 'deleted'");
    $countDeleted = $stmtDeleted->fetchColumn() ?: 0;

    $stmtHidden = $conn->query("SELECT COUNT(*) FROM trips WHERE moderation_status = 'hidden' AND status <> 'deleted'");
    $countHidden = $stmtHidden->fetchColumn() ?: 0;

    $stmtUserGrowth = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS total
                                    FROM users
                                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
                                    GROUP BY period
                                    ORDER BY period");
    $userGrowth = $stmtUserGrowth->fetchAll(PDO::FETCH_ASSOC);

    // 6. Danh sách lịch trình phổ biến dựa theo view_count
    $stmtPopular = $conn->query("SELECT t.*, l.name as location_name FROM trips t LEFT JOIN locations l ON t.location_id = l.id ORDER BY t.view_count DESC LIMIT 4");
    $popularTrips = $stmtPopular->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $totalUsers = 0;
    $totalTrips = 0;
    $totalComments = 0;
    $countPublished = 0;
    $countDraft = 0;
    $countDeleted = 0;
    $countHidden = 0;
    $userGrowth = [];
    $popularTrips = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title data-i18n-ui>Admin - Báo cáo & Thống kê</title>

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
.stat-icon{background:#e3f3f1;padding:10px;border-radius:8px;line-height:1}
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><span data-i18n-ui>Báo cáo </span>&<span data-i18n-ui> thống kê</span></h4>
        <p class="text-muted mb-0"><span data-i18n-ui>Tổng hợp tình hình hoạt động thực tế của TripConnect từ cơ sở dữ liệu</span></p>
    </div>
    <select id="period" class="form-select" style="width:180px">
        <option value="month" data-i18n-ui>6 tháng gần nhất</option>
        <option value="year" data-i18n-ui>Theo năm</option>
    </select>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="text-muted"><span data-i18n-ui>Người dùng</span></div>
                    <div class="stat-number"><span data-i18n-number><?php echo number_format($totalUsers); ?></span></div>
                    <small class="text-success"><i class="bi bi-arrow-up"></i><span data-i18n-ui> Hoạt động</span></small>
                </div>
                <div class="stat-icon text-primary"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="text-muted"><span data-i18n-ui>Lịch trình</span></div>
                    <div class="stat-number"><span data-i18n-number><?php echo number_format($totalTrips); ?></span></div>
                    <small class="text-success"><i class="bi bi-arrow-up"></i><span data-i18n-ui> Tổng số</span></small>
                </div>
                <div class="stat-icon text-success"><i class="bi bi-map"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="text-muted"><span data-i18n-ui>Bình luận</span></div>
                    <div class="stat-number"><span data-i18n-number><?php echo number_format($totalComments); ?></span></div>
                    <small class="text-success"><i class="bi bi-chat"></i><span data-i18n-ui> Tương tác</span></small>
                </div>
                <div class="stat-icon text-warning"><i class="bi bi-chat-left-text"></i></div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="text-muted"><span data-i18n-ui>Trạng thái hệ thống</span></div>
                    <div class="stat-number text-success" style="font-size: 20px; margin-top: 5px;"><i class="bi bi-check-circle-fill"></i><span data-i18n-ui> Ổn định</span></div>
                    <small class="text-muted"><span data-i18n-ui>MySQL Connected</span></small>
                </div>
                <div class="stat-icon text-danger"><i class="bi bi-shield-check text-success"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card p-4">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0"><span data-i18n-ui>Tăng trưởng người dùng</span></h5>
                <span class="text-muted"><span data-i18n-ui>6 tháng</span></span>
            </div>
            <div class="chart-box">
                <canvas id="userChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4">
            <h5 class="mb-3"><span data-i18n-ui>Trạng thái lịch trình</span></h5>
            <div class="chart-box">
                <canvas id="tripChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- CONTENT STATISTICS -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4">
            <h5 class="mb-3"><span data-i18n-ui>Thống kê nội dung hệ thống</span></h5>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th><span data-i18n-ui>Loại dữ liệu</span></th>
                            <th><span data-i18n-ui>Số lượng thực tế</span></th>
                            <th><span data-i18n-ui>Tỷ lệ</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span data-i18n-ui>Tổng Lịch trình</span></td>
                            <td><b><?php echo $totalTrips; ?></b></td>
                            <td>100%</td>
                        </tr>
                        <tr>
                            <td><span data-i18n-ui>Đã xuất bản (Published)</span></td>
                            <td><b><?php echo $countPublished; ?></b></td>
                            <td><?php echo $totalTrips > 0 ? round(($countPublished/$totalTrips)*100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span data-i18n-ui>Bản nháp (Draft)</span></td>
                            <td><b><?php echo $countDraft; ?></b></td>
                            <td><?php echo $totalTrips > 0 ? round(($countDraft/$totalTrips)*100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span data-i18n-ui>Đã xóa</span></td>
                            <td><b><?php echo $countDeleted; ?></b></td>
                            <td><?php echo $totalTrips > 0 ? round(($countDeleted/$totalTrips)*100, 1) : 0; ?>%</td>
                        </tr>
                        <tr>
                            <td><span data-i18n-ui>Ẩn kiểm duyệt</span></td>
                            <td><b><?php echo $countHidden; ?></b></td>
                            <td><?php echo $totalTrips > 0 ? round(($countHidden/$totalTrips)*100, 1) : 0; ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-4">
            <h5 class="mb-3"><span data-i18n-ui>Lịch trình phổ biến nhất</span></h5>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th><span data-i18n-ui>Lịch trình</span></th>
                            <th><span data-i18n-ui>Lượt xem</span></th>
                            <th><span data-i18n-ui>Địa điểm</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($popularTrips)): ?>
                            <?php foreach ($popularTrips as $pt): ?>
                                <tr>
                                    <td><a href="trip-detail.php?id=<?php echo $pt['id']; ?>" target="_blank" class="text-decoration-none text-dark fw-bold"><?php echo htmlspecialchars($pt['title']); ?></a></td>
                                    <td><span class="badge bg-primary-subtle text-primary" data-i18n-ui><span data-i18n-number><?php echo number_format($pt['view_count']); ?></span><span data-i18n-ui> views</span></span></td>
                                    <td><?php echo htmlspecialchars($pt['location_name'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted"><span data-i18n-ui>Chưa có dữ liệu lịch trình.</span></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Biểu đồ tăng trưởng người dùng
const userCtx = document.getElementById("userChart");
new Chart(userCtx, {
    type: "line",
            data: {
        labels: <?php echo json_encode(array_column($userGrowth, 'period'), JSON_UNESCAPED_UNICODE); ?>,
        datasets: [{
            label: "Người dùng",
            data: <?php echo json_encode(array_map('intval', array_column($userGrowth, 'total'))); ?>,
            borderColor: "#0d8f86",
            backgroundColor: "rgba(13, 143, 134, 0.1)",
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Biểu đồ trạng thái lịch trình lấy từ Database PHP
const tripCtx = document.getElementById("tripChart");
new Chart(tripCtx, {
    type: "doughnut",
    data: {
        labels: ["Đã xuất bản", "Bản nháp", "Đã ẩn kiểm duyệt", "Đã xóa"],
        datasets: [{
            data: [<?php echo $countPublished; ?>, <?php echo $countDraft; ?>, <?php echo $countHidden; ?>, <?php echo $countDeleted; ?>],
            backgroundColor: ["#21784c", "#946900", "#6c5ce7", "#a72f2f"]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: "bottom" } }
    }
});
</script>

<script src="../assets/i18n.js?v=10"></script>
</body>
</html>
