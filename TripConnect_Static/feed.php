<?php
// Khởi động session và kết nối database
session_start();
require_once 'config/database.php';

// Lọc theo tab (all, following, popular)
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'all';

try {
    $sql = "SELECT t.*, u.full_name as author_name, u.avatar as author_avatar, l.name as location_name 
            FROM trips t 
            LEFT JOIN users u ON t.user_id = u.id 
            LEFT JOIN locations l ON t.location_id = l.id 
            WHERE t.status = 'published'";

    if ($tab === 'popular') {
        $sql .= " ORDER BY t.view_count DESC";
    } else {
        $sql .= " ORDER BY t.created_at DESC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php">Khám phá</a></li>
    <li class="nav-item"><a class="nav-link active" href="feed.php">Bảng tin</a></li>
    <li class="nav-item"><a class="nav-link" href="profile.php">Hồ sơ</a></li>
   </ul>
    <div class="d-flex gap-2 align-items-center">
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Nếu đã đăng nhập: Hiển thị lời chào / tên tài khoản và nút Đăng xuất -->
        <span class="text-secondary small d-none d-md-inline">Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
        <a class="btn btn-outline-danger btn-sm px-3 rounded-pill" href="logout.php">Đăng xuất</a>
    <?php else: ?>
        <!-- Nếu chưa đăng nhập: Hiển thị nút Đăng nhập -->
        <a class="btn btn-outline-secondary btn-sm px-3 rounded-pill" href="login.php">Đăng nhập</a>
    <?php endif; ?>
    
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> Tạo lịch trình</a>
</div>
  </div>
 </div>
</nav>

<main class="container py-4">
 <!-- Phần Header Bảng tin chuẩn theo hình mẫu -->
 <div class="page-header mb-4">
    <div class="small-label fw-semibold text-muted mb-1">Social Network</div>
    <h1 class="section-title fw-bold mb-2" style="color: #0f3d3e;">Bảng tin</h1>
    <p class="text-muted">Theo dõi những lịch trình mới từ cộng đồng bạn quan tâm.</p>

    <!-- Các nút lọc tab -->
    <div class="d-flex gap-2 mt-3">
        <a href="feed.php?tab=all" class="btn <?php echo ($tab === 'all') ? 'btn-primary' : 'btn-light'; ?> px-4 rounded-pill fw-semibold">Tất cả</a>
        <a href="feed.php?tab=following" class="btn <?php echo ($tab === 'following') ? 'btn-primary' : 'btn-light'; ?> px-4 rounded-pill fw-semibold">Đang theo dõi</a>
        <a href="feed.php?tab=popular" class="btn <?php echo ($tab === 'popular') ? 'btn-primary' : 'btn-light'; ?> px-4 rounded-pill fw-semibold">Phổ biến</a>
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
                   <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($post['days_count']); ?> ngày</span>
               </div>

               <!-- Tiêu đề & Nội dung -->
               <h5 class="fw-bold mb-2">
                   <a href="trip-detail.php?id=<?php echo $post['id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($post['title']); ?></a>
               </h5>
               <p class="text-muted small mb-3"><?php echo htmlspecialchars(substr($post['description'] ?? '', 0, 150)); ?>...</p>

               <!-- Ảnh bìa chuyến đi -->
               <?php if (!empty($post['cover_image'])): ?>
                   <div class="mb-3">
<?php if (!empty($post['cover_image'])): ?>
    <div class="mb-3">
        <a href="trip-detail.php?id=<?php echo $post['id']; ?>">
            <img src="<?php echo htmlspecialchars($post['cover_image']); ?>" class="img-fluid rounded-3 w-100" style="max-height: 380px; object-fit: cover;" alt="Ảnh chuyến đi">
        </a>
    </div>
<?php endif; ?>
                   </div>
               <?php endif; ?>

               <!-- Thông tin chi phí & nút hành động -->
               <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                   <div>
                       <span class="text-secondary small me-3"><i class="bi bi-wallet2"></i> Dự kiến: <strong>~ <?php echo number_format($post['budget_estimate'], 0, ',', '.'); ?>đ</strong></span>
                       <span class="text-secondary small"><i class="bi bi-eye"></i> <?php echo intval($post['view_count']); ?> lượt xem</span>
                   </div>
                   <a href="trip-detail.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-outline-primary px-3 rounded-pill">Xem chi tiết</a>
               </div>
           </div>
       <?php endforeach; ?>
   <?php else: ?>
       <div class="card p-5 text-center text-muted border-0 shadow-sm rounded-4">
           <i class="bi bi-journal-x fs-1 mb-2"></i>
           <p class="mb-0">Chưa có bài viết hoặc lịch trình nào được chia sẻ trong bảng tin.</p>
       </div>
   <?php endif; ?>
  </div>
 </div>
</main>

<footer class="footer py-5 mt-5">
 <div class="container">
  <div class="row g-4">
   <div class="col-md-5">
       <h4 class="text-white">TripConnect</h4>
       <p>Chia sẻ trải nghiệm, khám phá lịch trình và tạo chuyến đi theo cách của bạn.</p>
   </div>
   <div class="col-md-3">
     <h6 class="text-white" data-i18n="footer.explore_title">Khám phá</h6>
     <a class="d-block mb-2" href="explore.php" data-i18n="footer.explore_1">Lịch trình nổi bật</a>
     <a class="d-block mb-2" href="feed.php" data-i18n="footer.explore_2">Bảng tin</a>
   </div>
   <div class="col-md-4">
       <h6 class="text-white">Thông tin</h6>
       <p class="mb-0">Đã tích hợp Backend PHP và kết nối cơ sở dữ liệu MySQL thành công.</p>
   </div>
  </div>
  <hr class="border-secondary my-4"><small>© 2026 TripConnect Demo</small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>