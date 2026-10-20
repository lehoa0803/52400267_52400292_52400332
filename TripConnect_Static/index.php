<?php
// Nhúng file kết nối database đã tạo ở thư mục config
session_start();
require_once 'config/database.php';

// 1. Lấy tổng số liệu thống kê thực tế từ Database cho phần KPI Strip
try {
    // Tổng số lượng chuyến đi đã published
    $stmtTrips = $conn->query("SELECT COUNT(*) FROM trips WHERE status = 'published'");
    $totalTrips = $stmtTrips->fetchColumn() ?: 860; // Nếu chưa có dữ liệu thì lấy tạm số mặc định

    // Tổng số lượng người dùng
    $stmtUsers = $conn->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmtUsers->fetchColumn() ?: 2400;

    // 2. Truy vấn danh sách các Lịch trình nổi bật từ database ra trang chủ
    $stmtFeatured = $conn->query("SELECT t.*, u.full_name as author_name, l.name as location_name,
                                  (SELECT COUNT(*) FROM likes lk WHERE lk.trip_id = t.id) AS like_count
                                  FROM trips t 
                                  LEFT JOIN users u ON t.user_id = u.id 
                                  LEFT JOIN locations l ON t.location_id = l.id 
                                  WHERE t.status = 'published' 
                                  ORDER BY t.view_count DESC 
                                  LIMIT 3");
    $featuredTrips = $stmtFeatured->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $totalTrips = 860;
    $totalUsers = 2400;
    $featuredTrips = [];
}
?>

<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="TripConnect — khám phá lịch trình du lịch thực tế, tạo hành trình của riêng bạn và kết nối với cộng đồng đam mê xê dịch.">
<title>Trang chủ | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php">
    <span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span>
  </a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
<li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
<li class="nav-item"><a class="nav-link" href="explore.php">Khám phá</a></li>
<li class="nav-item"><a class="nav-link" href="feed.php">Bảng tin</a></li>
<li class="nav-item"><a class="nav-link" href="profile.php">Hồ sơ</a></li>
   </ul>
   
  <div class="d-flex align-items-center gap-2">
    <!-- Nút chuyển đổi ngôn ngữ -->
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
        <li><a class="dropdown-item py-2" href="#" data-lang-btn="vi">🇻🇳 Tiếng Việt</a></li>
        <li><a class="dropdown-item py-2" href="#" data-lang-btn="en">🇬🇧 English</a></li>
      </ul>
    </div>

    <!-- Kiểm tra trạng thái đăng nhập -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Nếu đã đăng nhập: Hiển thị tên và nút Đăng xuất -->
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

<section class="hero py-5">
 <div class="container py-lg-4">
  <div class="row align-items-center g-5">
   <div class="col-lg-7">
    <span class="badge bg-light text-dark rounded-pill px-3 py-2 mb-3" data-i18n="hero.badge">Cộng đồng du lịch & lập lịch trình</span>
    <h1 class="display-4 mb-0" data-i18n="hero.title">Đi đâu cũng được,<br>miễn là có lịch trình.</h1>
    <p class="lead opacity-75 mt-3" data-i18n="hero.desc">Khám phá lịch trình thực tế, lưu lại chuyến đi yêu thích và tự tạo hành trình của riêng bạn.</p>
    
    <form id="heroSearchForm" class="hero-search mt-4" role="search" action="explore.php" method="get">
     <div class="row g-2 align-items-center">
      <div class="col-lg-5">
        <div class="input-group">
          <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-white"></i></span>
          <input id="heroSearch" name="q" class="form-control" placeholder="Bạn muốn đi đâu?" data-i18n="hero.placeholder" aria-label="Tìm kiếm điểm đến">
        </div>
      </div>
      <div class="col-lg-3">
        <select id="heroLocation" name="location" class="form-select" aria-label="Chọn địa điểm">
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
      <div class="col-lg-2">
        <select id="heroBudget" name="budget" class="form-select" aria-label="Chọn ngân sách">
          <option value="" data-i18n="hero.budget">Ngân sách</option>
          <option value="low">&lt; 3 triệu</option><option value="mid">3–5 triệu</option><option value="high">&gt; 5 triệu</option>
        </select>
      </div>
      <div class="col-lg-2">
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
     <a href="trip-detail.php?id=<?php echo (int)$hero['id']; ?>"><img class="w-100 rounded-4" style="height:210px;object-fit:cover" src="<?php echo htmlspecialchars($hero['cover_image'] ?: 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&w=900&q=80'); ?>" alt="<?php echo htmlspecialchars($hero['title']); ?>"></a>
     <h5 class="mt-3 text-white"><?php echo htmlspecialchars($hero['title']); ?></h5>
     <p class="small opacity-75 mb-0 text-white"><?php echo (int)$hero['days_count']; ?> ngày · ~<?php echo number_format($hero['budget_estimate'] / 1000000, 1, ',', '.'); ?> triệu · <?php echo (int)$hero['like_count']; ?> lượt thích</p>
     <?php else: ?>
     <p class="text-white mb-0">Chưa có lịch trình nổi bật.</p>
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
        <div class="display-stat"><?php echo number_format($totalUsers); ?>+</div>      <div class="small text-muted" data-i18n="kpi.community_desc">thành viên du lịch</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="small-label" data-i18n="kpi.trips">Lịch trình</div>
      <div class="display-stat"><?php echo number_format($totalTrips); ?>+</div>
      <div class="small text-muted" data-i18n="kpi.trips_desc">hành trình đã chia sẻ</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="small-label" data-i18n="kpi.locations">Địa điểm</div>
      <div class="display-stat">120+</div>
      <div class="small text-muted" data-i18n="kpi.locations_desc">điểm đến nổi bật</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card">
      <div class="small-label" data-i18n="kpi.interactions">Tương tác</div>
      <div class="display-stat">18K+</div>
      <div class="small text-muted" data-i18n="kpi.interactions_desc">lượt thích & lưu</div>
    </div>
  </div>
 </div>
</section>

<section class="container py-5">
 <div class="d-flex justify-content-between align-items-end mb-4">
  <div>
    <div class="small-label" data-i18n="section.suggest">Gợi ý cho bạn</div>
    <h2 class="section-title mb-0" data-i18n="section.title">Lịch trình nổi bật</h2>
  </div>
  <a href="explore.php" class="btn btn-soft"><span data-i18n="section.view_all">Xem tất cả</span> <i class="bi bi-arrow-right"></i></a>
 </div>

 <div class="row g-4">
<?php foreach ($featuredTrips as $t): ?>
  <div class="col-md-6 col-xl-4">
   <div class="card trip-card">
    <img class="trip-img" src="<?php echo htmlspecialchars($t['cover_image'] ?: 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&w=900&q=80'); ?>" alt="<?php echo htmlspecialchars($t['title']); ?>">
    <div class="card-body">
     <div class="d-flex justify-content-between"><span class="badge badge-soft"><?php echo (int)$t['days_count']; ?> ngày</span><span class="small text-muted"><i class="bi bi-heart"></i> <?php echo (int)$t['like_count']; ?></span></div>
     <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($t['title']); ?></h5>
     <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($t['location_name'] ?? 'Việt Nam'); ?></p>
     <p class="small text-muted"><?php echo htmlspecialchars(mb_substr($t['description'] ?? '', 0, 90)); ?>...</p>
     <div class="d-flex justify-content-between align-items-center"><strong>~ <?php echo number_format($t['budget_estimate'], 0, ',', '.'); ?>đ</strong><a href="trip-detail.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-soft">Xem chi tiết</a></div>
    </div>
   </div>
  </div>
<?php endforeach; ?>
<?php if (empty($featuredTrips)): ?>
  <p class="text-muted">Chưa có lịch trình nào được xuất bản.</p>
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
      <a href="trip-detail.php?id=<?php echo (int)($featuredTrips[0]['id'] ?? 1); ?>" data-i18n="card.budget_link">Xem demo →</a>
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
  <div class="row g-4">
   <div class="col-md-5">
     <h4 class="text-white">TripConnect</h4>
     <p data-i18n="footer.desc">Chia sẻ trải nghiệm, khám phá lịch trình và tạo chuyến đi theo cách của bạn.</p>
   </div>
   <div class="col-md-3">
     <h6 class="text-white" data-i18n="footer.explore_title">Khám phá</h6>
     <a class="d-block mb-2" href="explore.php" data-i18n="footer.explore_1">Lịch trình nổi bật</a>
     <a class="d-block mb-2" href="feed.php" data-i18n="footer.explore_2">Bảng tin</a>
   </div>
   <div class="col-md-4">
  <h6 class="text-white">Thông tin</h6>
  <p class="mb-2">
    Demo FrontEnd — dữ liệu hiện đang là mock data, sẵn sàng nối PHP/MySQL ở bước Backend.
  </p>
</div>
  </div>
  <hr class="border-secondary my-4"><small>© 2026 TripConnect Demo</small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
<script src="assets/i18n.js"></script>
<script src="assets/pages/home.js"></script>
</body>
</html>