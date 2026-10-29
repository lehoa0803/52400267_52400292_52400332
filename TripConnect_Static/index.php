<?php
// Nhúng file kết nối database đã tạo ở thư mục config
session_start();
require_once 'config/database.php';
require_once 'config/trip_social.php';
$socialToken=trip_csrf_token('social');$viewerFollowing=trip_social_following($conn,(int)($_SESSION['user_id']??0));

// 1. Lấy tổng số liệu thống kê thực tế từ Database cho phần KPI Strip
try {
    // Tổng số lượng chuyến đi đã published
    $stmtTrips = $conn->query("SELECT COUNT(*) FROM trips WHERE status = 'published' AND moderation_status = 'visible'");
    $totalTrips = (int) ($stmtTrips->fetchColumn() ?: 0);

    // Tổng số lượng người dùng
    $stmtUsers = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = (int) ($stmtUsers->fetchColumn() ?: 0);

    $stmtLocations = $conn->query("SELECT COUNT(*) FROM locations");
    $totalLocations = (int) ($stmtLocations->fetchColumn() ?: 0);

    $stmtInteractions = $conn->query("SELECT
        (SELECT COUNT(*) FROM likes) +
        (SELECT COUNT(*) FROM bookmarks) +
        (SELECT COUNT(*) FROM comments)");
    $totalInteractions = (int) ($stmtInteractions->fetchColumn() ?: 0);

    // 2. Truy vấn danh sách các Lịch trình nổi bật từ database ra trang chủ
    $stmtFeatured = $conn->query("SELECT t.*, u.username as author_username, u.avatar as author_avatar, u.full_name as author_name, l.name as location_name,
                                  (SELECT COUNT(*) FROM likes lk WHERE lk.trip_id = t.id) AS like_count,
                                  (SELECT lc.image_url FROM trip_days td JOIN activities a ON a.trip_day_id = td.id JOIN location_catalog lc ON lc.id = a.location_catalog_id WHERE td.trip_id = t.id AND NULLIF(TRIM(lc.image_url), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS catalog_image,
                                  (SELECT a.image FROM trip_days td JOIN activities a ON a.trip_day_id = td.id WHERE td.trip_id = t.id AND NULLIF(TRIM(a.image), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS activity_image
                                  FROM trips t
                                  LEFT JOIN users u ON t.user_id = u.id
                                  LEFT JOIN locations l ON t.location_id = l.id
                                  WHERE t.status = 'published' AND t.moderation_status = 'visible'
                                  ORDER BY t.view_count DESC
                                  LIMIT 3");
    $featuredTrips = $stmtFeatured->fetchAll(PDO::FETCH_ASSOC);
    foreach ($featuredTrips as &$featuredTrip) {
        $featuredTrip['cover_image'] = trip_cover_display_image($featuredTrip);
    }
    unset($featuredTrip);

} catch (PDOException $e) {
    error_log('[TripConnect index] database query failed: ' . $e->getMessage());
    $totalTrips = 0;
    $totalUsers = 0;
    $totalLocations = 0;
    $totalInteractions = 0;
    $featuredTrips = [];
}
?>

<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="social-csrf" content="<?= htmlspecialchars($socialToken,ENT_QUOTES) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="TripConnect — khám phá lịch trình du lịch thực tế, tạo hành trình của riêng bạn và kết nối với cộng đồng đam mê xê dịch.">
<title data-i18n-ui>Trang chủ | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-xl sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php">
    <span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span>
  </a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng" data-i18n-attrs>
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
<li class="nav-item"><a class="nav-link" href="index.php" data-i18n="nav.home">Trang chủ</a></li>
<li class="nav-item"><a class="nav-link" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
<li class="nav-item"><a class="nav-link" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
<li class="nav-item"><a class="nav-link" href="profile.php" data-i18n="nav.profile">Hồ sơ</a></li><?php trip_social_nav(); ?>
   </ul>

  <div class="d-flex align-items-center gap-2">
    <!-- Nút chuyển đổi ngôn ngữ -->
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
        <li><a class="dropdown-item py-2" href="#" data-lang-btn="vi"><span data-i18n-ui>🇻🇳 Tiếng Việt</span></a></li>
        <li><a class="dropdown-item py-2" href="#" data-lang-btn="en"><span data-i18n-ui>🇬🇧 English</span></a></li>
      </ul>
    </div>

    <!-- Kiểm tra trạng thái đăng nhập -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Nếu đã đăng nhập: Hiển thị tên và nút Đăng xuất -->
        <span class="text-secondary small d-none d-md-inline"><span data-i18n-ui>Xin chào, </span><strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
        <form method="post" action="logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill"><span data-i18n-ui>Đăng xuất</span></button></form>
    <?php else: ?>
        <!-- Nếu chưa đăng nhập: Hiển thị nút Đăng nhập -->
        <a class="btn btn-outline-secondary btn-sm px-3 rounded-pill" href="login.php" data-i18n="nav.login">Đăng nhập</a>
    <?php endif; ?>

    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> <span data-i18n="nav.create_trip">Tạo lịch trình</span></a>
   </div>
  </div>
 </div>
</nav>

<section class="hero py-5">
 <div class="hero-slider" aria-hidden="true">
  <div class="hero-slide"></div>
  <div class="hero-slide"></div>
  <div class="hero-slide"></div>
  <div class="hero-slide"></div>
 </div>
 <div class="container py-lg-4">
  <div class="row align-items-center g-5">
   <div class="col-lg-7">
    <span class="badge bg-light text-dark rounded-pill px-3 py-2 mb-3" data-i18n="hero.badge">Cộng đồng du lịch & lập lịch trình</span>
    <h1 class="display-4 mb-0" data-i18n="hero.title">Đi đâu cũng được,<br>miễn là có lịch trình.</h1>
    <p class="lead opacity-75 mt-3" data-i18n="hero.desc">Khám phá lịch trình thực tế, lưu lại chuyến đi yêu thích và tự tạo hành trình của riêng bạn.</p>

    <form id="heroSearchForm" class="hero-search mt-4" role="search" action="explore.php" method="get">
     <div class="row g-2 align-items-center">
      <div class="col-lg-4">
        <div class="input-group">
          <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-white"></i></span>
          <input id="heroSearch" name="q" class="form-control" placeholder="Bạn muốn đi đâu?" data-i18n="hero.placeholder" aria-label="Tìm kiếm điểm đến">
        </div>
      </div>
      <div class="col-lg">
        <select id="heroLocation" name="location" class="form-select" aria-label="Chọn địa điểm" data-i18n-attrs>
          <option value="" data-i18n="hero.location">Địa điểm</option>
          <?php
          // Đảm bảo file index.php đã gọi require_once 'config/database.php'; ở đầu trang
          if (isset($conn)) {
              $stmtHeroLoc = $conn->query("SELECT name FROM locations ORDER BY name ASC");
              while ($hLoc = $stmtHeroLoc->fetch(PDO::FETCH_ASSOC)) {
                  echo '<option value="' . htmlspecialchars($hLoc['name']) . '">' . htmlspecialchars($hLoc['name']) . '</option>';
              }
          }
          ?>
        </select>
      </div>
      <div class="col-lg">
        <select id="heroBudget" name="budget" class="form-select" aria-label="Chọn ngân sách" data-i18n-attrs>
          <option value="" data-i18n="hero.budget">Ngân sách</option>
          <option value="low" data-i18n-ui>&lt; 3 triệu</option><option value="mid" data-i18n-ui>3–5 triệu</option><option value="high" data-i18n-ui>&gt; 5 triệu</option>
        </select>
      </div>
      <div class="col-lg">
        <button type="submit" class="btn btn-primary w-100 py-2" data-i18n="hero.search_btn">Tìm kiếm</button>
      </div>
     </div>
    </form>
   </div>

   <div class="col-lg-5">
    <div class="hero-card p-4 text-white">
     <div class="d-flex justify-content-between align-items-center mb-4">
      <span class="fw-bold text-white" data-i18n="hero.featured_title">Trip nổi bật hôm nay</span>
      <span class="badge bg-light text-dark">#01</span>
     </div>
     <?php if (!empty($featuredTrips)): $hero = $featuredTrips[0]; ?>
     <a href="trip-detail.php?id=<?php echo (int)$hero['id']; ?>"><?php if (!empty($hero['cover_image'])): ?><img data-trip-cover class="w-100 rounded-4" style="height:210px;object-fit:cover" src="<?php echo htmlspecialchars($hero['cover_image']); ?>" alt="<?php echo htmlspecialchars($hero['title']); ?>"><?php else: ?><div class="w-100 rounded-4 d-flex align-items-center justify-content-center bg-light text-secondary" style="height:210px" role="img" aria-label="Chưa có ảnh bìa phù hợp" data-i18n-attrs><i class="bi bi-image fs-1" aria-hidden="true"></i></div><?php endif; ?></a>
     <h5 class="mt-3 text-white"><?php echo htmlspecialchars($hero['title']); ?></h5>
     <p class="small opacity-75 mb-0 text-white"><?php echo (int)$hero['days_count']; ?><span data-i18n-ui> ngày · ~</span><span data-i18n-number><?php echo number_format($hero['budget_estimate'] / 1000000, 1, ',', '.'); ?></span><span data-i18n-ui> triệu · </span><?php echo (int)$hero['like_count']; ?><span data-i18n-ui> lượt thích</span></p>
     <?php else: ?>
     <p class="text-white mb-0"><span data-i18n-ui>Chưa có lịch trình nổi bật.</span></p>
     <?php endif; ?>
    </div>
   </div>
  </div>
 </div>
</section>

<section class="container kpi-strip mt-4">
 <div class="row g-3">
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="small-label" data-i18n="kpi.community">Cộng đồng</div>
        <div class="display-stat"><span data-i18n-number><?php echo number_format($totalUsers); ?></span></div>      <div class="small text-muted" data-i18n="kpi.community_desc">thành viên du lịch</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="small-label" data-i18n="kpi.trips">Lịch trình</div>
      <div class="display-stat"><span data-i18n-number><?php echo number_format($totalTrips); ?></span></div>
      <div class="small text-muted" data-i18n="kpi.trips_desc">hành trình đã chia sẻ</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="small-label" data-i18n="kpi.locations">Địa điểm</div>
      <div class="display-stat"><span data-i18n-number><?php echo number_format($totalLocations); ?></span></div>
      <div class="small text-muted" data-i18n="kpi.locations_desc">điểm đến nổi bật</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="small-label" data-i18n="kpi.interactions">Tương tác</div>
      <div class="display-stat"><span data-i18n-number><?php echo number_format($totalInteractions); ?></span></div>
      <div class="small text-muted" data-i18n="kpi.interactions_desc">lượt thích & lưu</div>
    </div>
  </div>
 </div>
</section>

<section class="container py-5">
 <div class="d-flex justify-content-between align-items-end mb-4">
  <div>
    <div class="small-label"><span data-i18n-ui>Nổi bật trong cộng đồng</span></div>
    <h2 class="section-title mb-0" data-i18n="section.title">Lịch trình nổi bật</h2>
  </div>
  <a href="explore.php" class="btn btn-soft"><span data-i18n="section.view_all">Xem tất cả</span> <i class="bi bi-arrow-right"></i></a>
 </div>

 <div class="row g-4">
<?php foreach ($featuredTrips as $t): ?>
  <div class="col-md-6 col-xl-4">
   <div class="card trip-card">
    <?php if (!empty($t['cover_image'])): ?><img class="trip-img" src="<?php echo htmlspecialchars($t['cover_image']); ?>" alt="<?php echo htmlspecialchars($t['title']); ?>"><?php else: ?><div class="trip-img d-flex align-items-center justify-content-center bg-light text-secondary" role="img" aria-label="Chưa có ảnh bìa phù hợp" data-i18n-attrs><i class="bi bi-image fs-1" aria-hidden="true"></i></div><?php endif; ?>
    <div class="card-body"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><?php trip_social_author(['id'=>$t['user_id'],'full_name'=>$t['author_name'],'avatar'=>$t['author_avatar'],'username'=>$t['author_username']]);trip_social_follow_button((int)$t['user_id'],$viewerFollowing); ?></div>
     <div class="d-flex justify-content-between"><span class="badge badge-soft"><?php echo (int)$t['days_count']; ?><span data-i18n-ui> ngày</span></span><span class="small text-muted"><i class="bi bi-heart"></i> <?php echo (int)$t['like_count']; ?></span></div>
     <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($t['title']); ?></h5>
     <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($t['location_name'] ?? 'Việt Nam'); ?></p>
     <p class="small text-muted"><?php echo htmlspecialchars(mb_substr($t['description'] ?? '', 0, 90)); ?>...</p>
     <div class="d-flex justify-content-between align-items-center"><strong>~ <span data-i18n-number><?php echo number_format($t['budget_estimate'], 0, ',', '.'); ?></span><span data-i18n-ui>đ</span></strong><a href="trip-detail.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-soft"><span data-i18n-ui>Xem chi tiết</span></a></div>
    </div>
   </div>
  </div>
<?php endforeach; ?>
<?php if (empty($featuredTrips)): ?>
  <p class="text-muted"><span data-i18n-ui>Chưa có lịch trình nào được xuất bản.</span></p>
<?php endif; ?>
 </div>
</section>

<section class="container pb-5">
 <div class="row g-4">
  <div class="col-md-4">
    <div class="card stat-card h-100 p-4">
      <div class="icon-circle mb-3"><i class="bi bi-map"></i></div>
      <h5 data-i18n="card.create_title">Tạo lịch trình</h5>
      <p class="text-muted" data-i18n="card.create_desc">Xây dựng timeline theo từng ngày, địa điểm, thời gian và chi phí.</p>
      <a href="create-trip.php" data-i18n="card.create_link">Bắt đầu tạo →</a>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card h-100 p-4">
      <div class="icon-circle mb-3"><i class="bi bi-bar-chart"></i></div>
      <h5 data-i18n="card.budget_title">Theo dõi ngân sách</h5>
      <p class="text-muted" data-i18n="card.budget_desc">Tổng hợp chi phí ăn uống, di chuyển, lưu trú và trải nghiệm.</p>
      <?php if (!empty($featuredTrips)): ?><a href="trip-detail.php?id=<?php echo (int) $featuredTrips[0]['id']; ?>" data-i18n="card.budget_link">Xem lịch trình →</a><?php else: ?><a href="explore.php" data-i18n="card.budget_link">Khám phá lịch trình →</a><?php endif; ?>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card h-100 p-4">
      <div class="icon-circle mb-3"><i class="bi bi-people"></i></div>
      <h5 data-i18n="card.community_title">Kết nối cộng đồng</h5>
      <p class="text-muted" data-i18n="card.community_desc">Like, comment, bookmark, follow và cập nhật từ những người bạn theo dõi.</p>
      <a href="feed.php" data-i18n="card.community_link">Mở bảng tin →</a>
    </div>
  </div>
 </div>
</section>

<footer class="footer py-5 mt-5">
 <div class="container">
  <a class="footer-brand d-inline-flex align-items-center mb-4" href="index.php">
    <span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span>
  </a>
  <div class="row g-4">
   <div class="col-lg-4 col-md-6">
     <h6 class="footer-title" data-i18n="footer.company_title">Công ty TripConnect</h6>
     <ul class="footer-contact list-unstyled mb-0">
       <li><i class="bi bi-geo-alt-fill"></i><span data-i18n="footer.address">281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</span></li>
       <li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com"><span data-i18n-ui>info@tripconnect.com</span></a> - <a href="mailto:info.asia@tripconnect.com"><span data-i18n-ui>info.asia@tripconnect.com</span></a> <span data-i18n="footer.english_support">(English Support)</span></span></li>
       <li><i class="bi bi-globe2"></i><span data-i18n="footer.follow_us">Theo dõi chúng tôi trên:</span></li>
     </ul>
     <div class="footer-social">
       <span aria-label="Instagram" data-i18n-attrs><i class="bi bi-instagram"></i></span>
       <span aria-label="Facebook" data-i18n-attrs><i class="bi bi-facebook"></i></span>
       <span aria-label="WhatsApp" data-i18n-attrs><i class="bi bi-whatsapp"></i></span>
       <span aria-label="TikTok" data-i18n-attrs><i class="bi bi-tiktok"></i></span>
       <span aria-label="Messenger" data-i18n-attrs><i class="bi bi-messenger"></i></span>
     </div>
     <a class="btn-hotline" href="tel:1800646888"><i class="bi bi-telephone-fill"></i> 1800 646 888</a>
     <div class="footer-note" data-i18n="footer.hotline_note">Tổng đài miễn phí 24/7</div>
   </div>
   <div class="col-lg-2 col-md-6 offset-lg-1">
     <h6 class="footer-title" data-i18n="footer.explore_title">Khám phá</h6>
     <a class="footer-link" href="explore.php" data-i18n="footer.explore_1">Lịch trình nổi bật</a>
     <a class="footer-link" href="feed.php" data-i18n="footer.explore_2">Bảng tin</a>
     <a class="footer-link" href="create-trip.php" data-i18n="nav.create_trip">Tạo lịch trình</a>
   </div>
   <div class="col-lg-2 col-md-6">
     <h6 class="footer-title" data-i18n="footer.info_title">Thông tin</h6>
     <a class="footer-link" href="about-us.php" data-i18n="footer.about">Về chúng tôi</a>
     <a class="footer-link" href="#" data-i18n="footer.news">Tin tức</a>
     <a class="footer-link" href="#" data-i18n="footer.sitemap">Sitemap</a>
     <a class="footer-link" href="#" data-i18n="footer.help">Trợ giúp</a>
   </div>
   <div class="col-lg-3 col-md-6">
     <h6 class="footer-title" data-i18n="footer.terms_title">Điều kiện - Điều khoản</h6>
     <a class="footer-link" href="#" data-i18n="footer.privacy">Chính sách riêng tư</a>
     <a class="footer-link" href="#" data-i18n="footer.terms">Thỏa thuận sử dụng</a>
     <a class="footer-link" href="#" data-i18n="footer.data_policy">Chính sách bảo vệ dữ liệu cá nhân</a>
   </div>
  </div>
  <hr class="footer-hr my-4"><small><span data-i18n-ui>© 2026 TripConnect</span></small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=6"></script>
<script src="assets/i18n.js?v=17"></script>
<script src="assets/pages/home.js"></script>
</body>
</html>
