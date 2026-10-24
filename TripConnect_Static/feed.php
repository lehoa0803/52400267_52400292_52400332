<?php
// Khởi động session và kết nối database
session_start();
require_once 'config/database.php';
require_once 'config/trip_social.php';
$socialToken=trip_csrf_token('social');$viewerFollowing=trip_social_following($conn,(int)($_SESSION['user_id']??0));

// Lọc theo tab (all, following, popular)
$tab = isset($_GET['tab']) && is_string($_GET['tab']) ? trim($_GET['tab']) : 'all';
$tab=in_array($tab,['all','following','popular'],true)?$tab:'all';
if($tab==='following' && empty($_SESSION['user_id'])){header('Location: login.php');exit;}
$feedPage=max(1,min(10000,(int)($_GET['page']??1)));

try {
    $sql = "SELECT t.*, u.username as author_username, u.full_name as author_name, u.avatar as author_avatar, l.name as location_name,
                   (SELECT lc.image_url FROM trip_days td JOIN activities a ON a.trip_day_id = td.id JOIN location_catalog lc ON lc.id = a.location_catalog_id WHERE td.trip_id = t.id AND NULLIF(TRIM(lc.image_url), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS catalog_image,
                   (SELECT a.image FROM trip_days td JOIN activities a ON a.trip_day_id = td.id WHERE td.trip_id = t.id AND NULLIF(TRIM(a.image), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS activity_image
            FROM trips t 
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN locations l ON t.location_id = l.id 
            WHERE t.status = 'published' AND t.moderation_status = 'visible' AND u.status='active'";
    $params=[];
    if($tab==='following'){
        $sql.=' AND EXISTS (SELECT 1 FROM follows f WHERE f.followed_id=t.user_id AND f.follower_id=?)';
        $params[]=(int)$_SESSION['user_id'];
    }

    if ($tab === 'popular') {
        $sql .= " ORDER BY t.view_count DESC,t.created_at DESC,t.id DESC";
    } else {
        $sql .= " ORDER BY t.created_at DESC,t.id DESC";
    }

    $sql.=' LIMIT 11 OFFSET '.(($feedPage-1)*10);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasNextFeed=count($posts)>10;$posts=array_slice($posts,0,10);
    foreach ($posts as &$post) {
        $post['cover_image'] = trip_cover_display_image($post);
    }
    unset($post);

} catch (PDOException $e) {
    $posts = [];
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="social-csrf" content="<?= htmlspecialchars($socialToken,ENT_QUOTES) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Bảng tin cộng đồng TripConnect: chia sẻ trải nghiệm và lịch trình du lịch mới nhất.">
<title data-i18n-ui>Bảng tin | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-xl sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng" data-i18n-attrs><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
     <li class="nav-item"><a class="nav-link" href="index.php" data-i18n="nav.home">Trang chủ</a></li>
     <li class="nav-item"><a class="nav-link" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
     <li class="nav-item"><a class="nav-link active" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
     <li class="nav-item"><a class="nav-link" href="profile.php" data-i18n="nav.profile">Hồ sơ</a></li><?php trip_social_nav(); ?>
   </ul>
     <div class="d-flex gap-2 align-items-center">
     <div class="dropdown">
         <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
             <i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span>
         </button>
         <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
             <li><a class="dropdown-item py-2" href="#" data-lang-btn="vi"><span data-i18n-ui>🇻🇳 Tiếng Việt</span></a></li>
             <li><a class="dropdown-item py-2" href="#" data-lang-btn="en"><span data-i18n-ui>🇬🇧 English</span></a></li>
         </ul>
     </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Nếu đã đăng nhập: Hiển thị lời chào / tên tài khoản và nút Đăng xuất -->
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
               <div class="feed-author-header d-flex align-items-start gap-3 mb-3">
                   <div class="feed-author d-flex align-items-start gap-2 flex-grow-1">
                       <a class="flex-shrink-0" aria-label="Hồ sơ" data-i18n-attrs href="profile.php?id=<?= (int)$post['user_id'] ?>"><img class="rounded-circle" src="<?= htmlspecialchars($post['author_avatar']?:'assets/images/trip-placeholder.svg',ENT_QUOTES,'UTF-8') ?>" width="32" height="32" style="object-fit:cover" alt=""></a>
                       <div class="feed-author-details">
                           <div class="feed-author-line d-flex flex-wrap align-items-center gap-2">
                               <a class="text-decoration-none text-break" href="profile.php?id=<?= (int)$post['user_id'] ?>"><?= htmlspecialchars($post['author_name'],ENT_QUOTES,'UTF-8') ?></a>
                               <?php trip_social_follow_button((int)$post['user_id'],$viewerFollowing); ?>
                           </div>
                           <a class="d-block small text-muted text-decoration-none text-break" href="profile.php?id=<?= (int)$post['user_id'] ?>">@<?= htmlspecialchars($post['author_username'],ENT_QUOTES,'UTF-8') ?></a>
                       </div>
                   </div>
                   <span class="badge bg-primary-subtle text-primary flex-shrink-0 ms-auto"><?php echo htmlspecialchars($post['days_count']); ?> <span data-i18n="feed.day_suffix">ngày</span></span>
               </div>

               <!-- Tiêu đề & Nội dung -->
               <h5 class="fw-bold mb-2">
                   <a href="trip-detail.php?id=<?php echo $post['id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($post['title']); ?></a>
               </h5>
               <p class="text-muted small mb-3"><?php echo htmlspecialchars(mb_substr($post['description'] ?? '', 0, 150)); ?>...</p>

                <!-- Ảnh bìa chuyến đi -->
                <div class="mb-3">
                    <a href="trip-detail.php?id=<?php echo $post['id']; ?>">
                        <?php if (!empty($post['cover_image'])): ?>
                            <img data-trip-cover src="<?php echo htmlspecialchars($post['cover_image']); ?>" class="img-fluid rounded-3 w-100" style="max-height: 380px; object-fit: cover;" alt="Ảnh chuyến đi" data-i18n-attrs>
                        <?php else: ?>
                            <div class="img-fluid rounded-3 w-100 d-flex align-items-center justify-content-center bg-light text-secondary" style="height: 220px" role="img" aria-label="Chưa có ảnh bìa phù hợp" data-i18n-attrs><i class="bi bi-image fs-1" aria-hidden="true"></i></div>
                        <?php endif; ?>
                    </a>
                </div>

               <!-- Thông tin chi phí & nút hành động -->
               <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                   <div>
                        <span class="text-secondary small me-3"><i class="bi bi-wallet2"></i> <span data-i18n="feed.estimated">Dự kiến:</span> <strong>~ <span data-i18n-number><?php echo number_format($post['budget_estimate'], 0, ',', '.'); ?></span><span data-i18n-ui>đ</span></strong></span>
                        <span class="text-secondary small"><i class="bi bi-eye"></i> <?php echo intval($post['view_count']); ?> <span data-i18n="feed.views">lượt xem</span></span>
                   </div>
                    <a href="trip-detail.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-primary px-3 rounded-pill" data-i18n="feed.view_details">Xem chi tiết</a>
               </div>
           </div>
       <?php endforeach; ?>
   <?php else: ?>
       <div class="card p-5 text-center text-muted border-0 shadow-sm rounded-4">
           <?php if ($tab==='following'): ?><p><span data-i18n-ui>Bạn chưa theo dõi ai hoặc chưa có lịch trình công khai từ người đang theo dõi. Hãy mở lịch trình và bấm Theo dõi tác giả.</span></p><?php endif; ?>
           <i class="bi bi-journal-x fs-1 mb-2"></i>
            <p class="mb-0" data-i18n="feed.no_posts">Chưa có bài viết hoặc lịch trình nào được chia sẻ trong bảng tin.</p>
       </div>
   <?php endif; ?>
   <nav class="d-flex justify-content-between" aria-label="Trang bảng tin" data-i18n-attrs>
    <?php if($feedPage>1): ?><a class="btn btn-light" href="feed.php?tab=<?php echo $tab; ?>&amp;page=<?php echo $feedPage-1; ?>"><span data-i18n-ui>Trang trước</span></a><?php endif; ?>
    <?php if(!empty($hasNextFeed)): ?><a class="btn btn-light ms-auto" href="feed.php?tab=<?php echo $tab; ?>&amp;page=<?php echo $feedPage+1; ?>"><span data-i18n-ui>Trang sau</span></a><?php endif; ?>
   </nav>
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
       <li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com"><span data-i18n-ui>info@tripconnect.com</span></a> - <a href="mailto:info.asia@tripconnect.com"><span data-i18n-ui>info.asia@tripconnect.com</span></a> <span data-i18n="footer.english_support">(English Support)</span></span></li>
       <li><i class="bi bi-globe2"></i><span data-i18n="footer.follow_us">Theo dõi chúng tôi trên:</span></li>
     </ul>
     <div class="footer-social"><span aria-label="Instagram" data-i18n-attrs><i class="bi bi-instagram"></i></span><span aria-label="Facebook" data-i18n-attrs><i class="bi bi-facebook"></i></span><span aria-label="WhatsApp" data-i18n-attrs><i class="bi bi-whatsapp"></i></span><span aria-label="TikTok" data-i18n-attrs><i class="bi bi-tiktok"></i></span><span aria-label="Messenger" data-i18n-attrs><i class="bi bi-messenger"></i></span></div>
     <a class="btn-hotline" href="tel:1800646888"><i class="bi bi-telephone-fill"></i> 1800 646 888</a>
     <div class="footer-note" data-i18n="footer.hotline_note">Tổng đài miễn phí 24/7</div>
   </div>
   <div class="col-lg-2 col-md-6 offset-lg-1"><h6 class="footer-title" data-i18n="footer.explore_title">Khám phá</h6><a class="footer-link" href="explore.php" data-i18n="footer.explore_1">Lịch trình nổi bật</a><a class="footer-link" href="feed.php" data-i18n="footer.explore_2">Bảng tin</a><a class="footer-link" href="create-trip.php" data-i18n="nav.create_trip">Tạo lịch trình</a></div>
   <div class="col-lg-2 col-md-6"><h6 class="footer-title" data-i18n="footer.info_title">Thông tin</h6><a class="footer-link" href="#" data-i18n="footer.about">Về chúng tôi</a><a class="footer-link" href="#" data-i18n="footer.news">Tin tức</a><a class="footer-link" href="#" data-i18n="footer.sitemap">Sitemap</a><a class="footer-link" href="#" data-i18n="footer.help">Trợ giúp</a></div>
   <div class="col-lg-3 col-md-6"><h6 class="footer-title" data-i18n="footer.terms_title">Điều kiện - Điều khoản</h6><a class="footer-link" href="#" data-i18n="footer.privacy">Chính sách riêng tư</a><a class="footer-link" href="#" data-i18n="footer.terms">Thỏa thuận sử dụng</a><a class="footer-link" href="#" data-i18n="footer.data_policy">Chính sách bảo vệ dữ liệu cá nhân</a></div>
  </div>
  <hr class="footer-hr my-4"><small><span data-i18n-ui>© 2026 TripConnect</span></small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=6"></script>
<script src="assets/i18n.js?v=10"></script>
</body>
</html>
