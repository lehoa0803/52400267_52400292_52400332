<?php
// Nhúng file kết nối cơ sở dữ liệu
session_start();
require_once 'config/database.php';

// Lấy trip_id động từ URL (ví dụ: trip-detail.php?id=2). Không có id thì hiện "Không tìm thấy"
$trip_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Giá trị mặc định
$trip_days = [];
$budgetData = [];
$stops = [];
$center = [16.047, 108.22];
$likeCount = 0;
$comments = [];
$tags = [];

// Gửi bình luận (chỉ khi đã đăng nhập)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $trip_id > 0) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    $content = trim($_POST['content'] ?? '');
    if ($content !== '' && mb_strlen($content) <= 1000) {
        $stmtIns = $conn->prepare("INSERT INTO comments (user_id, trip_id, content) VALUES (?, ?, ?)");
        $stmtIns->execute([$_SESSION['user_id'], $trip_id, $content]);
    }
    header("Location: trip-detail.php?id=" . $trip_id . "#comments");
    exit;
}

try {
    // 1. Truy vấn thông tin chi tiết chuyến đi, thông tin tác giả và địa điểm theo ID động
    $stmtTrip = $conn->prepare("SELECT t.*, u.full_name as author_name, u.avatar as author_avatar, l.name as location_name 
                                FROM trips t 
                                LEFT JOIN users u ON t.user_id = u.id 
                                LEFT JOIN locations l ON t.location_id = l.id 
                                WHERE t.id = ?");
    $stmtTrip->execute([$trip_id]);
    $trip = $stmtTrip->fetch(PDO::FETCH_ASSOC);

    // 2. Nếu tìm thấy chuyến đi, lấy tiếp danh sách các ngày (trip_days) ĐÚNG THEO CHUYẾN ĐI ĐÓ
    if ($trip) {
        $stmtDays = $conn->prepare("SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number ASC");
        $stmtDays->execute([$trip_id]);
        $trip_days = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

        // Ngân sách theo hạng mục (tính từ chi phí các hoạt động)
        $stmtB = $conn->prepare("SELECT a.category, SUM(a.cost) AS total
                                 FROM activities a JOIN trip_days d ON d.id = a.trip_day_id
                                 WHERE d.trip_id = ? GROUP BY a.category");
        $stmtB->execute([$trip_id]);
        // Nhãn tiếng Việt cho các mã danh mục tiếng Anh (nếu DB đã lưu sẵn tiếng Việt thì giữ nguyên)
        $catLabels = ['food' => 'Ăn uống', 'transport' => 'Di chuyển', 'stay' => 'Lưu trú', 'experience' => 'Trải nghiệm', 'other' => 'Khác'];
        // Màu cũ của giao diện tĩnh; nhận cả tên tiếng Anh lẫn tiếng Việt
        $palette = ['#e85a45', '#eda247', '#0f6e66', '#128077', '#8a9aa0'];
        $knownColors = [
            'food' => '#e85a45',       'ăn uống' => '#e85a45',
            'transport' => '#eda247',  'di chuyển' => '#eda247',
            'stay' => '#0f6e66',       'lưu trú' => '#0f6e66',
            'experience' => '#128077', 'trải nghiệm' => '#128077', 'tham quan' => '#128077',
        ];
        $usedColors = [];
        foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['total'] <= 0) continue;
            $key = mb_strtolower(trim($r['category'] ?? ''), 'UTF-8');
            $color = $knownColors[$key] ?? null;
            // Danh mục lạ hoặc trùng màu: lấy màu kế tiếp trong bảng màu chưa dùng
            if (!$color || in_array($color, $usedColors, true)) {
                $color = null;
                foreach ($palette as $p) { if (!in_array($p, $usedColors, true)) { $color = $p; break; } }
                if (!$color) $color = '#8a9aa0';
            }
            $usedColors[] = $color;
            $budgetData[] = [
                'label' => $catLabels[$key] ?? ($r['category'] ?: 'Khác'),
                'value' => (float)$r['total'],
                'color' => $color
            ];
        }

        // Các điểm có tọa độ để vẽ lên bản đồ
        $stmtM = $conn->prepare("SELECT a.location_name, a.latitude, a.longitude
                                 FROM activities a JOIN trip_days d ON d.id = a.trip_day_id
                                 WHERE d.trip_id = ? AND a.latitude IS NOT NULL AND a.longitude IS NOT NULL
                                 ORDER BY d.day_number, a.start_time");
        $stmtM->execute([$trip_id]);
        foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $stops[] = ['name' => $r['location_name'], 'coords' => [(float)$r['latitude'], (float)$r['longitude']]];
        }
        if (!empty($stops)) $center = $stops[0]['coords'];

        // Lượt thích, bình luận, tag
        $stmtL = $conn->prepare("SELECT COUNT(*) FROM likes WHERE trip_id = ?");
        $stmtL->execute([$trip_id]);
        $likeCount = (int)$stmtL->fetchColumn();

        $stmtC = $conn->prepare("SELECT c.content, c.created_at, u.full_name, u.avatar
                                 FROM comments c JOIN users u ON u.id = c.user_id
                                 WHERE c.trip_id = ? ORDER BY c.created_at ASC");
        $stmtC->execute([$trip_id]);
        $comments = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        $stmtT = $conn->prepare("SELECT tg.name FROM trip_tags tt JOIN tags tg ON tg.id = tt.tag_id WHERE tt.trip_id = ?");
        $stmtT->execute([$trip_id]);
        $tags = $stmtT->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $trip_days = [];
    }

} catch (PDOException $e) {
    $trip = null;
    $trip_days = [];
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Chi tiết lịch trình trên TripConnect: timeline hoạt động, ngân sách dự kiến và bình luận từ cộng đồng.">
<title><?php echo htmlspecialchars($trip['title'] ?? 'Chi tiết lịch trình'); ?> | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="trip-detail-page">
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
     <li class="nav-item"><a class="nav-link" href="index.php" data-i18n="nav.home">Trang chủ</a></li>
     <li class="nav-item"><a class="nav-link" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
     <li class="nav-item"><a class="nav-link" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
     <li class="nav-item"><a class="nav-link" href="profile.php" data-i18n="nav.profile">Hồ sơ</a></li>
   </ul>
    <div class="d-flex gap-2 align-items-center">
     <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span></button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4"><li><a class="dropdown-item py-2" href="#" data-lang-btn="vi">🇻🇳 Tiếng Việt</a></li><li><a class="dropdown-item py-2" href="#" data-lang-btn="en">🇬🇧 English</a></li></ul>
     </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <span class="text-secondary small d-none d-md-inline">Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
        <a class="btn btn-outline-danger btn-sm px-3 rounded-pill" href="logout.php">Đăng xuất</a>
    <?php else: ?>
     <a class="btn btn-outline-secondary btn-sm px-3 rounded-pill" href="login.php" data-i18n="nav.login">Đăng nhập</a>
    <?php endif; ?>
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> <span data-i18n="nav.create_trip">Tạo lịch trình</span></a>
   </div>
  </div>
 </div>
</nav>

<main class="container py-4">
 <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
   <a href="explore.php" class="text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> <span data-i18n="detail.back_explore">Quay lại khám phá</span></a>
  <div>
   <button data-like class="btn btn-light me-2"><i class="bi bi-heart"></i> <span data-like-label data-i18n="detail.like">Thích</span> (<span data-like-count><?php echo (int)$likeCount; ?></span>)</button>
   <button data-bookmark class="btn btn-light"><i class="bi bi-bookmark"></i> <span data-bookmark-label data-i18n="detail.save">Lưu</span></button>
  </div>
 </div>

 <?php if ($trip): ?>
 <div class="row g-4">
  <div class="col-lg-8">
   <img class="cover-lg img-fluid rounded w-100" src="<?php echo htmlspecialchars($trip['cover_image'] ?? 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&w=1400&q=85'); ?>" alt="<?php echo htmlspecialchars($trip['title']); ?>">
   
   <div class="card mt-4 p-4">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <span class="badge badge-soft"><?php echo htmlspecialchars($trip['days_count']); ?> <span data-i18n="detail.day_suffix">ngày</span></span>
            <h1 class="mt-3 mb-2"><?php echo htmlspecialchars($trip['title']); ?></h1>
            <p class="text-muted mb-0"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($trip['location_name'] ?? 'Việt Nam'); ?> · <i class="bi bi-eye ms-2"></i> <?php echo number_format($trip['view_count']); ?> <span data-i18n="detail.views">lượt xem</span></p>
        </div>
        <a href="profile.php?id=<?php echo (int)$trip['user_id']; ?>"><img class="avatar rounded-circle" src="<?php echo htmlspecialchars($trip['author_avatar'] ?? 'https://i.pravatar.cc/80?img=32'); ?>" width="50" height="50" alt="Ảnh đại diện"></a>
    </div>
    <hr>
    <p><?php echo nl2br(htmlspecialchars($trip['description'])); ?></p>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($tags as $tg): ?>
        <span class="badge bg-light text-dark">#<?php echo htmlspecialchars($tg); ?></span>
        <?php endforeach; ?>
    </div>
   </div>

   <div class="card mt-4 p-4 trip-map-card">
     <h4 class="mb-3" data-i18n="detail.map_title">Bản đồ hành trình</h4>
    <div id="tripMap" role="img" aria-label="Bản đồ các điểm đến trong lịch trình" style="height: 350px; border-radius: 8px;"></div>
   </div>

   <!-- Timeline hành trình theo từng ngày -->
   <div class="card mt-4 p-4">
     <h4 class="mb-4" data-i18n="detail.timeline_title">Timeline hành trình</h4>
    <div id="activityList" class="timeline">
     <?php if (!empty($trip_days)): ?>
         <?php foreach ($trip_days as $day): ?>
             <div class="mb-4">
                  <h5 class="text-primary fw-bold"><span data-i18n="detail.day_label">Ngày</span> <?php echo $day['day_number']; ?>: <?php echo htmlspecialchars($day['title']); ?></h5>
                 <p class="small text-muted mb-3"><em><?php echo htmlspecialchars($day['note']); ?></em></p>
                 
                 <!-- Truy vấn danh sách hoạt động trong ngày từ cơ sở dữ liệu ứng với trip_day_id -->
                 <?php
                 $stmtActs = $conn->prepare("SELECT * FROM activities WHERE trip_day_id = ? ORDER BY start_time ASC");
                 $stmtActs->execute([$day['id']]);
                 $activities = $stmtActs->fetchAll(PDO::FETCH_ASSOC);
                 ?>

                 <?php if (!empty($activities)): ?>
                     <?php foreach ($activities as $act): ?>
                         <div class="timeline-item mb-3 ps-3 border-start position-relative">
                             <span class="timeline-dot position-absolute start-0 translate-middle-x bg-primary rounded-circle" style="width: 10px; height: 10px; top: 5px;"></span>
                             <div class="small-label fw-bold text-muted"><?php echo date('H:i', strtotime($act['start_time'])); ?></div>
                             <h6 class="mb-1"><?php echo htmlspecialchars($act['title']); ?></h6>
                             <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($act['location_name']); ?> · <?php echo number_format($act['cost'], 0, ',', '.'); ?>đ</div>
                         </div>
                     <?php endforeach; ?>
                 <?php else: ?>
                      <div class="text-muted small ms-3" data-i18n="detail.no_activities">Chưa có hoạt động cụ thể cho ngày này.</div>
                 <?php endif; ?>
             </div>
         <?php endforeach; ?>
     <?php else: ?>
          <div class="text-muted" data-i18n="detail.no_timeline">Chưa có lịch trình chi tiết theo ngày cho chuyến đi này.</div>
     <?php endif; ?>
    </div>
   </div>

   <div class="card mt-4 p-4" id="comments">
     <h4><span data-i18n="detail.comments">Bình luận</span> (<span id="commentCount"><?php echo count($comments); ?></span>)</h4>
    <?php if (isset($_SESSION['user_id'])): ?>
    <form id="commentForm" method="post" action="trip-detail.php?id=<?php echo (int)$trip_id; ?>" class="d-flex gap-2 my-3">
        <input id="commentInput" name="content" class="form-control" placeholder="Chia sẻ cảm nhận của bạn..." data-i18n="detail.comment_placeholder" required maxlength="1000" aria-label="Viết bình luận">
        <button class="btn btn-primary" type="submit" data-i18n="detail.send">Gửi</button>
    </form>
    <?php else: ?>
     <p class="my-3 text-muted"><span data-i18n="detail.login_to_comment">Vui lòng</span> <a href="login.php" data-i18n="nav.login">đăng nhập</a> <span data-i18n="detail.to_comment">để bình luận.</span></p>
    <?php endif; ?>
    <div id="commentList">
        <?php foreach ($comments as $c): ?>
        <div class="comment mb-2">
            <div class="d-flex gap-2">
                <img class="avatar rounded-circle" src="<?php echo htmlspecialchars($c['avatar'] ?: 'https://i.pravatar.cc/80?img=5'); ?>" width="40" height="40" alt="Avatar">
                <div>
                    <strong><?php echo htmlspecialchars($c['full_name']); ?></strong>
                    <div class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></div>
                    <div class="mt-1"><?php echo nl2br(htmlspecialchars($c['content'])); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
         <?php if (empty($comments)): ?><p class="text-muted mb-0" data-i18n="detail.no_comments">Chưa có bình luận nào. Hãy là người đầu tiên!</p><?php endif; ?>
    </div>
   </div>
  </div>

  <div class="col-lg-4">
   <div class="card p-4 sticky-side">
    <div class="d-flex justify-content-between">
        <div>
            <div class="small-label" data-i18n="detail.budget">Ngân sách dự kiến</div>
            <div class="display-6 fw-bold"><?php echo number_format(($trip['budget_estimate'] ?? 4500000) / 1000000, 1, ',', '.'); ?>tr</div>
        </div>
        <div class="icon-circle"><i class="bi bi-wallet2"></i></div>
    </div>
    <hr>
    <canvas id="budgetChart" height="220" role="img" aria-label="Biểu đồ phân bổ ngân sách theo hạng mục"></canvas>
    <ul class="list-unstyled small mt-3 mb-0" id="budgetLegend"></ul>
    <div class="d-grid gap-2 mt-3">
      <button id="cloneBtn" class="btn btn-primary"><i class="bi bi-copy"></i> <span data-i18n="detail.clone">Clone lịch trình</span></button>
      <button id="exportBtn" class="btn btn-soft" type="button" title="Mở bản in để lưu thành PDF"><i class="bi bi-file-earmark-pdf"></i> <span data-i18n="detail.export">Xuất PDF</span></button>
    </div>
   </div>
   
   <div class="card p-4 mt-4">
     <h6 data-i18n="detail.author">Tác giả</h6>
    <div class="d-flex align-items-center gap-3">
        <img class="avatar-lg rounded-circle" src="<?php echo htmlspecialchars($trip['author_avatar'] ?? 'https://i.pravatar.cc/160?img=32'); ?>" width="60" height="60" alt="Avatar">
        <div>
            <strong><?php echo htmlspecialchars($trip['author_name'] ?? 'TripConnect User'); ?></strong>
         <div class="small text-muted" data-i18n="detail.active_member">Thành viên tích cực</div>
        </div>
    </div>
     <button data-follow data-following="false" class="btn btn-primary w-100 mt-3" data-i18n="detail.follow">Theo dõi</button>
   </div>
  </div>
 </div>
 <?php else: ?>
    <div class="text-center py-5">
         <h3 data-i18n="detail.not_found">Không tìm thấy chuyến đi!</h3>
         <p class="text-muted" data-i18n="detail.not_found_desc">Lịch trình bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</p>
         <a href="explore.php" class="btn btn-primary" data-i18n="detail.back_to_explore">Quay lại trang khám phá</a>
    </div>
 <?php endif; ?>
</main>

<footer class="footer py-5 mt-5">
 <div class="container">
  <a class="footer-brand d-inline-flex align-items-center mb-4" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <div class="row g-4">
   <div class="col-lg-4 col-md-6">
     <h6 class="footer-title" data-i18n="footer.company_title">Công ty TripConnect</h6>
     <ul class="footer-contact list-unstyled mb-0"><li><i class="bi bi-geo-alt-fill"></i><span data-i18n="footer.address">281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</span></li><li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com">info@tripconnect.com</a> - <a href="mailto:info.asia@tripconnect.com">info.asia@tripconnect.com</a> <span data-i18n="footer.english_support">(English Support)</span></span></li><li><i class="bi bi-globe2"></i><span data-i18n="footer.follow_us">Theo dõi chúng tôi trên:</span></li></ul>
     <div class="footer-social"><span aria-label="Instagram"><i class="bi bi-instagram"></i></span><span aria-label="Facebook"><i class="bi bi-facebook"></i></span><span aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></span><span aria-label="TikTok"><i class="bi bi-tiktok"></i></span><span aria-label="Messenger"><i class="bi bi-messenger"></i></span></div>
     <a class="btn-hotline" href="tel:1800646888"><i class="bi bi-telephone-fill"></i> 1800 646 888</a><div class="footer-note" data-i18n="footer.hotline_note">Tổng đài miễn phí 24/7</div>
   </div>
   <div class="col-lg-2 col-md-6 offset-lg-1"><h6 class="footer-title" data-i18n="footer.explore_title">Khám phá</h6><a class="footer-link" href="explore.php" data-i18n="footer.explore_1">Lịch trình nổi bật</a><a class="footer-link" href="feed.php" data-i18n="footer.explore_2">Bảng tin</a><a class="footer-link" href="create-trip.php" data-i18n="nav.create_trip">Tạo lịch trình</a></div>
   <div class="col-lg-2 col-md-6"><h6 class="footer-title" data-i18n="footer.info_title">Thông tin</h6><a class="footer-link" href="#" data-i18n="footer.about">Về chúng tôi</a><a class="footer-link" href="#" data-i18n="footer.news">Tin tức</a><a class="footer-link" href="#" data-i18n="footer.sitemap">Sitemap</a><a class="footer-link" href="#" data-i18n="footer.help">Trợ giúp</a></div>
   <div class="col-lg-3 col-md-6"><h6 class="footer-title" data-i18n="footer.terms_title">Điều kiện - Điều khoản</h6><a class="footer-link" href="#" data-i18n="footer.privacy">Chính sách riêng tư</a><a class="footer-link" href="#" data-i18n="footer.terms">Thỏa thuận sử dụng</a><a class="footer-link" href="#" data-i18n="footer.data_policy">Chính sách bảo vệ dữ liệu cá nhân</a></div>
  </div>
  <hr class="footer-hr my-4"><small>© 2026 TripConnect</small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=2"></script>
<script src="assets/i18n.js?v=5"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

  /* ---------- Budget chart ---------- */
  var budgetData = <?php echo json_encode($budgetData, JSON_UNESCAPED_UNICODE); ?>;
  var ctx = document.getElementById("budgetChart");
  if (ctx && window.Chart) {
    new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: budgetData.map(function (d) { return d.label; }),
        datasets: [{ data: budgetData.map(function (d) { return d.value; }), backgroundColor: budgetData.map(function (d) { return d.color; }), borderWidth: 0 }]
      },
      options: { plugins: { legend: { display: false } }, cutout: "68%" }
    });
  }
  var legend = document.getElementById("budgetLegend");
  if (legend) {
      budgetData.forEach(function (d) {
        var li = document.createElement("li");
        li.className = "d-flex justify-content-between align-items-center mb-1";
        li.innerHTML = '<span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + d.color + ';margin-right:8px"></span>' + d.label + '</span><span class="text-muted"></span>';
        li.querySelector(".text-muted").textContent = d.value.toLocaleString("vi-VN") + "đ";
        legend.appendChild(li);
      });
  }

  /* ---------- Map ---------- */
  var mapEl = document.getElementById("tripMap");
  if (mapEl && window.L) {
    var map = L.map(mapEl, { scrollWheelZoom: false }).setView(<?php echo json_encode($center); ?>, 12);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map);
    var stops = <?php echo json_encode($stops, JSON_UNESCAPED_UNICODE); ?>;
    stops.forEach(function (s) { L.marker(s.coords).addTo(map).bindPopup(s.name); });
    if (stops.length > 1) map.fitBounds(stops.map(function (s) { return s.coords; }), { padding: [30, 30] });
  }

  /* ---------- Clone / export ---------- */
  var cloneBtn = document.getElementById("cloneBtn");
  if (cloneBtn) {
      cloneBtn.addEventListener("click", function () {
        alert("Đã tạo bản nháp riêng từ lịch trình này!");
      });
  }
  var exportBtn = document.getElementById("exportBtn");
  if (exportBtn) {
      exportBtn.addEventListener("click", function () {
        // Dùng trình in gốc của trình duyệt: người dùng có thể chọn "Save as PDF".
        // Toàn bộ nội dung ở đây đã được PHP lấy đúng theo trip_id từ database.
        var originalTitle = document.title;
        var tripTitle = <?php echo json_encode($trip['title'] ?? 'Lịch trình TripConnect', JSON_UNESCAPED_UNICODE); ?>;
        document.title = tripTitle + " - TripConnect";
        window.print();
        window.setTimeout(function () { document.title = originalTitle; }, 1000);
      });
  }
});
</script>
</body>
</html>
