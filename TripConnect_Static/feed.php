<?php
// Khởi động session và kết nối database
session_start();
require_once 'config/database.php';

// Lọc theo tab (all, following, popular)
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'all';

try {
    $sql = "SELECT t.*, u.full_name as author_name, u.avatar as author_avatar, l.name as location_name,
                   (SELECT lc.image_url FROM trip_days td JOIN activities a ON a.trip_day_id = td.id JOIN location_catalog lc ON lc.id = a.location_catalog_id WHERE td.trip_id = t.id AND NULLIF(TRIM(lc.image_url), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS catalog_image,
                   (SELECT a.image FROM trip_days td JOIN activities a ON a.trip_day_id = td.id WHERE td.trip_id = t.id AND NULLIF(TRIM(a.image), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS activity_image
            FROM trips t 
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN locations l ON t.location_id = l.id 
            WHERE t.status = 'published' AND t.moderation_status = 'visible'";

    if ($tab === 'popular') {
        $sql .= " ORDER BY t.view_count DESC";
    } else {
        $sql .= " ORDER BY t.created_at DESC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($posts as &$post) {
        $post['cover_image'] = trip_first_valid_image([$post['cover_image'] ?? null, $post['catalog_image'] ?? null, $post['activity_image'] ?? null]);
    }
    unset($post);

} catch (PDOException $e) {
    $posts = [];
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Bảng tin cộng đồng TripConnect: chia sẻ trải nghiệm và lịch trình du lịch mới nhất.">
<title>Bảng tin | TripConnect</title>
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
     <li class="nav-item"><a class="nav-link" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
     <li class="nav-item"><a class="nav-link active" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
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
        <!-- Nếu đã đăng nhập: Hiển thị lời chào / tên tài khoản và nút Đăng xuất -->
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

<main class="container py-4">
 <!-- Phần Header Bảng tin chuẩn theo hình mẫu -->
 <div class="page-header mb-4">
     <div class="small-label fw-semibold text-muted mb-1" data-i18n="feed.page_label">Social Network</div>
     <h1 class="section-title fw-bold mb-2" style="color: #0f3d3e;" data-i18n="feed.title">Bảng tin</h1>
     <p class="text-muted" data-i18n="feed.description">Theo dõi những lịch trình mới từ cộng đồng bạn quan tâm.</p>

    <!-- Các nút lọc tab -->
    <div class="d-flex gap-2 mt-3">
         <a href="feed.php?tab=all" class="btn <?php echo ($tab === 'all') ? 'btn-primary' : 'btn-light'; ?> px-4 rounded-pill fw-semibold" data-i18n="feed.all">Tất cả</a>
         <a href="feed.php?tab=following" class="btn <?php echo ($tab === 'following') ? 'btn-primary' : 'btn-light'; ?> px-4 rounded-pill fw-semibold" data-i18n="feed.following">Đang theo dõi</a>
         <a href="feed.php?tab=popular" class="btn <?php echo ($tab === 'popular') ? 'btn-primary' : 'btn-light'; ?> px-4 rounded-pill fw-semibold" data-i18n="feed.popular">Phổ biến</a>
    </div>
 </div>

 <!-- Danh sách bài viết / bảng tin -->
 <div class="row justify-content-center">
  <div class="col-lg-8">
   <?php if (!empty($posts)): ?>
       <?php foreach ($posts as $post): ?>
           <div class="card p-4 mb-4 shadow-sm border-0 rounded-4">
               <!-- Thông tin tác giả -->
               <div class="d-flex align-items-center justify-content-between mb-3">
                   <div class="d-flex align-items-center gap-3">
                       <img class="avatar rounded-circle" src="<?php echo htmlspecialchars($post['author_avatar'] ?? 'https://i.pravatar.cc/80?img=32'); ?>" width="45" height="45" alt="Avatar">
                       <div>
                           <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($post['author_name'] ?? 'Thành viên TripConnect'); ?></h6>
                           <small class="text-muted"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($post['location_name'] ?? 'Việt Nam'); ?> · <?php echo date('d/m/Y', strtotime($post['created_at'])); ?></small>
                       </div>
                   </div>
                    <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($post['days_count']); ?> <span data-i18n="feed.day_suffix">ngày</span></span>
               </div>

               <!-- Tiêu đề & Nội dung -->
               <h5 class="fw-bold mb-2">
                   <a href="trip-detail.php?id=<?php echo $post['id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($post['title']); ?></a>
               </h5>
               <p class="text-muted small mb-3"><?php echo htmlspecialchars(substr($post['description'] ?? '', 0, 150)); ?>...</p>

                <!-- Ảnh bìa chuyến đi -->
                <div class="mb-3">
                    <a href="trip-detail.php?id=<?php echo $post['id']; ?>">
                        <?php if (!empty($post['cover_image'])): ?>
                            <img src="<?php echo htmlspecialchars($post['cover_image']); ?>" class="img-fluid rounded-3 w-100" style="max-height: 380px; object-fit: cover;" alt="Ảnh chuyến đi">
                        <?php else: ?>
                            <div class="img-fluid rounded-3 w-100 d-flex align-items-center justify-content-center bg-light text-secondary" style="height: 220px" role="img" aria-label="Chưa có ảnh bìa phù hợp"><i class="bi bi-image fs-1" aria-hidden="true"></i></div>
                        <?php endif; ?>
                    </a>
                </div>

               <!-- Thông tin chi phí & nút hành động -->
               <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                   <div>
                        <span class="text-secondary small me-3"><i class="bi bi-wallet2"></i> <span data-i18n="feed.estimated">Dự kiến:</span> <strong>~ <?php echo number_format($post['budget_estimate'], 0, ',', '.'); ?>đ</strong></span>
                        <span class="text-secondary small"><i class="bi bi-eye"></i> <?php echo intval($post['view_count']); ?> <span data-i18n="feed.views">lượt xem</span></span>
                   </div>
                    <a href="trip-detail.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-primary px-3 rounded-pill" data-i18n="feed.view_details">Xem chi tiết</a>
               </div>
           </div>
       <?php endforeach; ?>
   <?php else: ?>
       <div class="card p-5 text-center text-muted border-0 shadow-sm rounded-4">
           <i class="bi bi-journal-x fs-1 mb-2"></i>
            <p class="mb-0" data-i18n="feed.no_posts">Chưa có bài viết hoặc lịch trình nào được chia sẻ trong bảng tin.</p>
       </div>
   <?php endif; ?>
  </div>
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
<script src="assets/i18n.js?v=4"></script>
</body>
</html>
