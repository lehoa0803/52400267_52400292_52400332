<?php
// Khởi động session để kiểm tra tài khoản đang đăng nhập
session_start();
// Nhúng file kết nối cơ sở dữ liệu
require_once 'config/database.php';

// Kiểm tra xem người dùng đã đăng nhập chưa, nếu chưa thì chuyển hướng về trang đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Lấy thông tin chi tiết của user hiện tại từ bảng users
    $stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // 2. Lấy danh sách các chuyến đi do chính user này tạo từ bảng trips
    $stmtTrips = $conn->prepare("SELECT t.*, l.name as location_name 
                                 FROM trips t 
                                 LEFT JOIN locations l ON t.location_id = l.id 
                                 WHERE t.user_id = ? 
                                 ORDER BY t.created_at DESC");
    $stmtTrips->execute([$user_id]);
    $myTrips = $stmtTrips->fetchAll(PDO::FETCH_ASSOC);
    $totalTrips = count($myTrips);

    // 3. Lấy danh sách những người đang theo dõi user này (Followers từ bảng follows thực tế)
    $stmtFollowers = $conn->prepare("
        SELECT u.id, u.full_name, u.avatar 
        FROM follows f 
        JOIN users u ON f.follower_id = u.id 
        WHERE f.followed_id = ?
    ");
    $stmtFollowers->execute([$user_id]);
    $followersList = $stmtFollowers->fetchAll(PDO::FETCH_ASSOC);
    $followersCount = count($followersList);

    // 4. Lấy danh sách những người mà user này đang theo dõi (Following từ bảng follows thực tế)
    $stmtFollowing = $conn->prepare("
        SELECT u.id, u.full_name, u.avatar 
        FROM follows f 
        JOIN users u ON f.followed_id = u.id 
        WHERE f.follower_id = ?
    ");
    $stmtFollowing->execute([$user_id]);
    $followingList = $stmtFollowing->fetchAll(PDO::FETCH_ASSOC);
    $followingCount = count($followingList);

} catch (PDOException $e) {
    $currentUser = [
        'full_name' => $_SESSION['full_name'] ?? 'Thành viên',
        'username' => $_SESSION['username'] ?? 'user',
        'avatar' => 'https://i.pravatar.cc/160?img=32',
        'bio' => 'Chia sẻ những chuyến đi tự túc và lịch trình thực tế.'
    ];
    $myTrips = [];
    $totalTrips = 0;
    $followersList = [];
    $followingList = [];
    $followersCount = 0;
    $followingCount = 0;
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Hồ sơ thành viên TripConnect: lịch trình đã xuất bản, sở thích du lịch và bộ sưu tập đã lưu.">
<title>Hồ sơ cá nhân | TripConnect</title>
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
    <li class="nav-item"><a class="nav-link" href="feed.php">Bảng tin</a></li>
    <li class="nav-item"><a class="nav-link active" href="profile.php">Hồ sơ</a></li>
   </ul>
   <div class="d-flex gap-2 align-items-center">
    <span class="text-secondary small">Xin chào, <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong></span>
    <a class="btn btn-outline-danger btn-sm px-3 rounded-pill" href="logout.php">Đăng xuất</a>
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> Tạo lịch trình</a>
   </div>
  </div>
 </div>
</nav>

<main>
 <div class="container">
  <div class="card overflow-hidden mt-4">
   <img class="profile-cover w-100" src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80" alt="Ảnh bìa trang cá nhân">
   <div class="p-4">
    <div class="profile-avatar-wrap d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div class="d-flex align-items-end gap-3">
            <img class="avatar-lg border border-4 border-white rounded-circle" src="<?php echo htmlspecialchars($currentUser['avatar'] ?? 'https://i.pravatar.cc/160?img=32'); ?>" width="100" height="100" alt="Ảnh đại diện">
            <div>
                <h2 class="mb-0"><?php echo htmlspecialchars($currentUser['full_name']); ?></h2>
                <div class="text-muted">@<?php echo htmlspecialchars($currentUser['username']); ?> · Việt Nam</div>
            </div>
        </div>
        <a href="create-trip.php" class="btn btn-primary px-4"><i class="bi bi-pencil-square"></i> Chỉnh sửa / Tạo mới</a>
    </div>
    <p class="mt-4 mb-2"><?php echo htmlspecialchars($currentUser['bio'] ?? 'Chia sẻ những chuyến đi tự túc, lịch trình thực tế và kinh nghiệm tiết kiệm.'); ?></p>
<div class="d-flex gap-4 small">
        <span><strong><?php echo $totalTrips; ?></strong> lịch trình</span>
        <span><strong><?php echo $followersCount; ?></strong> người theo dõi</span>
        <span><strong><?php echo $followingCount; ?></strong> đang theo dõi</span>
    </div>
   </div>
  </div>

  <div class="row g-4 mt-2">
   <div class="col-lg-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Lịch trình của bạn</h4>
        <a href="create-trip.php" class="btn btn-soft">+ Tạo mới</a>
    </div>
    
    <div class="row g-4">
     <?php if (!empty($myTrips)): ?>
         <?php foreach ($myTrips as $trip): ?>
             <div class="col-md-6">
              <div class="card trip-card">
               <img class="trip-img" src="<?php echo htmlspecialchars($trip['cover_image'] ?? 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?auto=format&fit=crop&w=900&q=80'); ?>" alt="Ảnh chuyến đi">
               <div class="card-body">
                <div class="d-flex justify-content-between">
                    <span class="badge badge-soft"><?php echo htmlspecialchars($trip['days_count']); ?> ngày</span>
                    <span class="small text-muted"><i class="bi bi-heart"></i> <?php echo intval($trip['view_count']); ?></span>
                </div>
                <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($trip['title']); ?></h5>
                <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($trip['location_name'] ?? 'Việt Nam'); ?></p>
                <div class="d-flex justify-content-between align-items-center mt-2">
    <strong>~ <?php echo number_format($trip['budget_estimate'], 0, ',', '.'); ?>đ</strong>
    <div class="d-flex gap-1">
        <a href="trip-detail.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-primary" title="Xem chi tiết"><i class="bi bi-eye"></i></a>
        <a href="edit-trip.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-success" title="Chỉnh sửa"><i class="bi bi-pencil"></i></a>
        <a href="delete-trip.php?id=<?php echo $trip['id']; ?>" class="btn btn-sm btn-outline-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa lịch trình này không?');"><i class="bi bi-trash"></i></a>
    </div>
</div>
               </div>
              </div>
             </div>
         <?php endforeach; ?>
     <?php else: ?>
         <div class="col-12">
             <div class="card p-4 text-center text-muted">
                 <p class="mb-2">Bạn chưa tạo lịch trình nào.</p>
                 <a href="create-trip.php" class="btn btn-primary btn-sm mx-auto">Tạo lịch trình đầu tiên ngay</a>
             </div>
         </div>
     <?php endif; ?>

     <div class="col-md-6 d-flex">
      <a href="create-trip.php" class="card w-100 text-decoration-none d-flex align-items-center justify-content-center text-muted" style="min-height:220px;border-style:dashed">
       <div class="text-center"><i class="bi bi-plus-circle fs-1"></i><p class="mt-2 mb-0">Thêm lịch trình mới</p></div>
      </a>
     </div>
    </div>
   </div>

   <div class="col-lg-4">
    <div class="card p-4">
        <h5>Sở thích du lịch</h5>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <span class="badge badge-soft">Check-in</span>
            <span class="badge badge-soft">Biển</span>
            <span class="badge badge-soft">Ẩm thực</span>
            <span class="badge badge-soft">Tự túc</span>
            <span class="badge badge-soft">Tiết kiệm</span>
        </div>
    </div>
    <div class="card p-4 mt-4">
        <h5>Bộ sưu tập</h5>
        <p class="text-muted small">Lịch trình đã lưu để xem sau.</p>
        <a href="explore.php" class="text-decoration-none">Khám phá thêm lịch trình →</a>
    </div>
   </div>
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
       <h6 class="text-white">Khám phá</h6>
       <a class="d-block mb-2 text-decoration-none text-muted" href="explore.php">Lịch trình nổi bật</a>
       <a class="d-block mb-2 text-decoration-none text-muted" href="feed.php">Bảng tin</a>
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