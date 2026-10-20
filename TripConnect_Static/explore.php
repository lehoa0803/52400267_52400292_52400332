<?php
// Nhúng file kết nối cơ sở dữ liệu
session_start();
require_once 'config/database.php';

// Lấy các tham số lọc từ form GET gửi lên
$keyword  = isset($_GET['q']) ? trim($_GET['q']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$days     = isset($_GET['days']) ? trim($_GET['days']) : '';
$budget   = isset($_GET['budget']) ? trim($_GET['budget']) : '';

try {
    // Câu lệnh SQL lấy danh sách trip đã published kèm theo tên location, user và số lượt thích thật từ bảng likes
    $sql = "SELECT t.*, u.full_name as author_name, l.name as location_name,
                  (SELECT COUNT(*) FROM likes lk WHERE lk.trip_id = t.id) AS like_count
            FROM trips t 
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN locations l ON t.location_id = l.id 
            WHERE t.status = 'published'";
    
    $params = [];

    // 1. Lọc theo từ khóa (tiêu đề hoặc mô tả)
    if (!empty($keyword)) {
        $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }

    // 2. Lọc theo địa điểm
    if (!empty($location)) {
        $sql .= " AND l.name = ?";
        $params[] = $location;
    }

    // 3. Lọc theo số ngày (short: 1-3 ngày, mid: 4-5 ngày, long: 6+ ngày)
    if (!empty($days)) {
        if ($days === 'short') {
            $sql .= " AND t.days_count BETWEEN 1 AND 3";
        } elseif ($days === 'mid') {
            $sql .= " AND t.days_count BETWEEN 4 AND 5";
        } elseif ($days === 'long') {
            $sql .= " AND t.days_count >= 6";
        }
    }

    // 4. Lọc theo ngân sách (low: < 3tr, mid: 3-5tr, high: > 5tr)
    if (!empty($budget)) {
        if ($budget === 'low') {
            $sql .= " AND t.budget_estimate < 3000000";
        } elseif ($budget === 'mid') {
            $sql .= " AND t.budget_estimate BETWEEN 3000000 AND 5000000";
        } elseif ($budget === 'high') {
            $sql .= " AND t.budget_estimate > 5000000";
        }
    }

    $sql .= " ORDER BY t.created_at DESC";

    // Thực thi câu lệnh truy vấn an toàn bằng PDO Prepared Statement
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $trips = [];
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Khám phá và lọc các lịch trình du lịch công khai trên TripConnect theo địa điểm, số ngày và ngân sách.">
<title>Khám phá | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
 <li class="nav-item"><a class="nav-link" href="index.php" data-i18n="nav.home">Trang chủ</a></li>
 <li class="nav-item"><a class="nav-link active" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
 <li class="nav-item"><a class="nav-link" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
 <li class="nav-item"><a class="nav-link" href="profile.php" data-i18n="nav.profile">Hồ sơ</a></li>
   </ul>
 <div class="d-flex gap-2 align-items-center">
    <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
            <li><a class="dropdown-item py-2" href="#" data-lang-btn="vi">🇻🇳 Tiếng Việt</a></li>
            <li><a class="dropdown-item py-2" href="#" data-lang-btn="en">🇬🇧 English</a></li>
        </ul>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Nếu đã đăng nhập: Hiển thị tên tài khoản và nút Đăng xuất -->
        <span class="text-secondary small d-none d-md-inline">Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
        <a class="btn btn-outline-danger btn-sm px-3 rounded-pill" href="logout.php">Đăng xuất</a>
    <?php else: ?>
        <!-- Nếu chưa đăng nhập: Hiển thị nút Đăng nhập -->
        <a class="btn btn-outline-secondary btn-sm px-3 rounded-pill" href="login.php" data-i18n="nav.login">Đăng nhập</a>
    <?php endif; ?>
    
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> <span data-i18n="nav.create_trip">Tạo lịch trình</span></a>
</div>
  </div>
 </div>
</nav>

<main class="container">
 <div class="page-header">
    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
        <div>
            <div class="small-label" data-i18n="explore.page_label">Trip Library</div>
            <h1 class="section-title mb-2" data-i18n="explore.title">Khám phá lịch trình</h1>
            <p class="text-muted mb-0" data-i18n="explore.description">Tìm kiếm và lọc các chuyến đi công khai theo nhu cầu của bạn.</p>
        </div>
        <span id="resultCount" class="badge badge-soft" data-count="<?php echo count($trips); ?>"><?php echo count($trips); ?> <span data-i18n="explore.result_suffix">lịch trình</span></span>
    </div>
 </div>

 <div class="filter-box mb-4">
  <form id="filterForm" method="GET" action="explore.php" class="row g-3">
   <div class="col-lg-4">
        <label class="form-label fw-semibold" for="tripSearch" data-i18n="explore.keyword">Từ khóa</label>
       <div class="input-group">
           <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input id="tripSearch" name="q" class="form-control" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Đà Lạt, biển, check-in..." data-i18n="explore.search_placeholder">
       </div>
   </div>
<div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterLocation" data-i18n="explore.location">Địa điểm</label>
       <select id="filterLocation" name="location" class="form-select">
            <option value="" data-i18n="explore.all">Tất cả</option>
           <?php 
           // Truy vấn lấy danh sách tất cả các tỉnh thành từ bảng locations
           try {
               $stmtAllLocs = $conn->query("SELECT name FROM locations ORDER BY name ASC");
               while ($locRow = $stmtAllLocs->fetch(PDO::FETCH_ASSOC)) {
                   $locName = $locRow['name'];
                   // Kiểm tra nếu trùng với giá trị đang lọc thì thêm thuộc tính selected
                   $isSelected = (isset($location) && $location === $locName) ? 'selected' : '';
                   echo '<option value="' . htmlspecialchars($locName) . '" ' . $isSelected . '>' . htmlspecialchars($locName) . '</option>';
               }
           } catch (Exception $e) {
               // Xử lý lỗi ngầm nếu cần
           }
           ?>
       </select>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterDays" data-i18n="explore.days">Số ngày</label>
       <select id="filterDays" name="days" class="form-select">
            <option value="" data-i18n="explore.all">Tất cả</option>
            <option value="short" <?php echo ($days === 'short') ? 'selected' : ''; ?> data-i18n="explore.days_short">1–3 ngày</option>
            <option value="mid" <?php echo ($days === 'mid') ? 'selected' : ''; ?> data-i18n="explore.days_mid">4–5 ngày</option>
            <option value="long" <?php echo ($days === 'long') ? 'selected' : ''; ?> data-i18n="explore.days_long">6+ ngày</option>
       </select>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterBudget" data-i18n="explore.budget">Ngân sách</label>
       <select id="filterBudget" name="budget" class="form-select">
            <option value="" data-i18n="explore.all">Tất cả</option>
            <option value="low" <?php echo ($budget === 'low') ? 'selected' : ''; ?> data-i18n="explore.budget_low">Dưới 3 triệu</option>
            <option value="mid" <?php echo ($budget === 'mid') ? 'selected' : ''; ?> data-i18n="explore.budget_mid">3–5 triệu</option>
            <option value="high" <?php echo ($budget === 'high') ? 'selected' : ''; ?> data-i18n="explore.budget_high">Trên 5 triệu</option>
       </select>
   </div>
   <div class="col-lg-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary w-100" data-i18n="explore.filter_button">Lọc kết quả</button>
   </div>
  </form>
 </div>

<!-- Danh sách chuyến đi render động từ Database -->
<div id="tripGrid" class="row g-4">
  <?php if (!empty($trips)): ?>
    <?php foreach ($trips as $trip): ?>
      <div class="col-md-6 col-xl-4 trip-item">
       <div class="card trip-card">
        <img class="trip-img" src="<?php echo htmlspecialchars($trip['cover_image'] ?? 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&w=900&q=80'); ?>" alt="<?php echo htmlspecialchars($trip['title']); ?>">
        <div class="card-body">
         <div class="d-flex justify-content-between">
              <span class="badge badge-soft"><?php echo htmlspecialchars($trip['days_count']); ?> <span data-i18n="explore.day_suffix">ngày</span></span>
<span class="small text-muted"><i class="bi bi-heart"></i> <?php echo intval($trip['like_count']); ?></span>         </div>
         <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($trip['title']); ?></h5>
         <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($trip['location_name'] ?? 'Việt Nam'); ?></p>
         <p class="small text-muted"><?php echo htmlspecialchars(substr($trip['description'] ?? '', 0, 90)); ?>...</p>
         <div class="d-flex justify-content-between align-items-center">
             <strong>~ <?php echo number_format($trip['budget_estimate'], 0, ',', '.'); ?>đ</strong>
              <a href="trip-detail.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-soft" data-i18n="explore.view_details">Xem chi tiết</a>
         </div>
        </div>
       </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <!-- Trạng thái trống khi không tìm thấy kết quả phù hợp -->
    <div class="col-12 text-center text-muted py-5">
     <i class="bi bi-emoji-frown fs-1"></i>
     <p class="mt-3 mb-0" data-i18n="explore.no_results">Không tìm thấy lịch trình phù hợp. Hãy thử từ khóa hoặc bộ lọc khác.</p>
    </div>
  <?php endif; ?>
</div>

 <div class="text-center mt-5 mb-5">
     <button class="btn btn-outline-secondary" disabled data-i18n="explore.all_loaded">Đã hiển thị toàn bộ lịch trình phù hợp</button>
 </div>
</main>

<footer class="footer py-5 mt-5">
 <div class="container">
  <a class="footer-brand d-inline-flex align-items-center mb-4" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <div class="row g-4">
   <div class="col-lg-4 col-md-6">
     <h6 class="footer-title" data-i18n="footer.company_title">Công ty TripConnect</h6>
     <ul class="footer-contact list-unstyled mb-0">
       <li><i class="bi bi-geo-alt-fill"></i><span data-i18n="footer.address">281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</span></li>
       <li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com">info@tripconnect.com</a> - <a href="mailto:info.asia@tripconnect.com">info.asia@tripconnect.com</a> <span data-i18n="footer.english_support">(English Support)</span></span></li>
       <li><i class="bi bi-globe2"></i><span data-i18n="footer.follow_us">Theo dõi chúng tôi trên:</span></li>
     </ul>
     <div class="footer-social"><span aria-label="Instagram"><i class="bi bi-instagram"></i></span><span aria-label="Facebook"><i class="bi bi-facebook"></i></span><span aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></span><span aria-label="TikTok"><i class="bi bi-tiktok"></i></span><span aria-label="Messenger"><i class="bi bi-messenger"></i></span></div>
     <a class="btn-hotline" href="tel:1800646888"><i class="bi bi-telephone-fill"></i> 1800 646 888</a>
     <div class="footer-note" data-i18n="footer.hotline_note">Tổng đài miễn phí 24/7</div>
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
<script src="assets/i18n.js?v=3"></script>
</body>
</html>
