<?php
session_start();
require_once 'config/database.php';

// Hiển thị số liệu thật của website; dùng giá trị mặc định khi database chưa có dữ liệu.
$totalTrips = 0;
$totalUsers = 0;
try {
    $totalTrips = (int) $conn->query("SELECT COUNT(*) FROM trips WHERE status = 'published'")->fetchColumn();
    $totalUsers = (int) $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (PDOException $e) {
    // Trang giới thiệu vẫn hoạt động khi database chưa sẵn sàng.
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Tìm hiểu về TripConnect - nền tảng khám phá, chia sẻ và tạo lịch trình du lịch.">
<title>Về chúng tôi | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
<style>
  .about-hero{background:linear-gradient(135deg, rgba(10,58,56,.97), rgba(15,110,102,.88)), url('assets/images/hero2.jpg') center/cover; color:#fff; border-radius:var(--radius-lg); padding:clamp(2.2rem, 6vw, 5rem); overflow:hidden;}
  .about-hero h1,.about-hero p{color:#fff;}
  .about-card{height:100%; border:1px solid var(--line); border-radius:var(--radius-md); background:#fff; padding:1.6rem; box-shadow:var(--shadow-soft);}
  .about-icon{width:48px;height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:14px;background:var(--teal-100);color:var(--teal-700);font-size:1.35rem;margin-bottom:1rem;}
  .about-stat{border-left:3px solid var(--coral); padding-left:1rem;}
</style>
<style>
body.about-page{background:#f7f7f7}
body.about-page > main:not(.about-reference-layout){display:none}
.about-reference-layout{padding-bottom:3rem}
.about-reference-heading{text-align:center;padding:2rem 0 2.2rem}
.about-reference-heading h1{font-size:clamp(1.8rem,3vw,2.45rem);font-weight:700;margin-bottom:.45rem}
.about-reference-heading p{font-size:1.05rem;color:var(--ink);margin:0}
.about-reference-card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 8px 28px rgba(10,40,38,.09)}
.about-reference-image{height:300px;min-height:300px;background:url('assets/images/about-city.png') center/cover}
.about-reference-blue{height:calc(100% - 300px);min-height:390px;background:#0755c9;color:#fff;padding:1.7rem 1.7rem 2rem}
.about-reference-blue h2{color:#fff;text-align:center;font-size:1.6rem;font-weight:700;margin:0 0 1.5rem}
.about-reference-blue h2{font-size:0}.about-reference-blue h2::after{content:'TRỤ SỞ CHÍNH';font-size:1.6rem}
.about-reference-blue h3{color:#fff;font-size:1.1rem;font-weight:700;margin:1.35rem 0 .25rem}
.about-reference-blue p{font-size:1rem;line-height:1.5;margin:0;color:#fff}.about-reference-blue a{color:#fff;text-decoration:none}
.about-reference-content{height:100%;min-height:690px;padding:2.2rem clamp(1.5rem,4vw,3.3rem)}
.about-reference-content h2{font-size:1.6rem;font-weight:700;color:var(--ink);margin-bottom:1rem}
.about-reference-content h3{font-size:1.12rem;font-weight:700;color:var(--ink);margin-top:1.7rem;margin-bottom:.55rem}
.about-reference-content p{color:var(--ink-soft);line-height:1.7;margin-bottom:1rem}
.about-reference-feature{display:flex;gap:.8rem;align-items:flex-start;margin-bottom:1rem}.about-reference-feature i{color:#0755c9;font-size:1.2rem;margin-top:.2rem}.about-reference-feature strong{display:block;color:var(--ink)}.about-reference-feature span{color:var(--ink-soft);font-size:.95rem}
.about-reference-stat{display:inline-block;border-left:3px solid #0755c9;padding-left:.8rem;margin:1rem 2rem 0 0}.about-reference-stat strong{display:block;color:#0755c9;font-size:1.45rem;line-height:1.1}.about-reference-stat span{font-size:.82rem;color:var(--ink-soft)}
@media(max-width:991.98px){.about-reference-image{height:300px;min-height:300px}.about-reference-blue,.about-reference-content{height:auto;min-height:auto}}
</style>
</head>
<body class="about-page">
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php">Khám phá</a></li>
    <li class="nav-item"><a class="nav-link" href="feed.php">Bảng tin</a></li>
    <li class="nav-item"><a class="nav-link" href="profile.php">Hồ sơ</a></li>
   </ul>
   <div class="d-flex gap-2 align-items-center">
    <a class="btn btn-outline-secondary btn-sm px-3 rounded-pill" href="login.php">Đăng nhập</a>
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> Tạo lịch trình</a>
   </div>
  </div>
 </div>
</nav>

<main class="about-reference-layout container">
 <header class="about-reference-heading"><h1>VỀ CHÚNG TÔI</h1><p>Cùng TripConnect khám phá và tạo nên những hành trình đáng nhớ</p></header>
 <section class="about-reference-card row g-0" aria-label="Thông tin về TripConnect">
  <div class="col-lg-5"><div class="about-reference-image" role="img" aria-label="Khám phá những hành trình cùng TripConnect"></div><div class="about-reference-blue"><h2>TRIPCONNECT</h2><h3><i class="bi bi-compass me-2"></i>Nền tảng du lịch cộng đồng</h3><p>Nơi kết nối những người yêu thích khám phá, chia sẻ trải nghiệm và cùng nhau lên kế hoạch cho chuyến đi.</p><h3><i class="bi bi-geo-alt-fill me-2"></i>Địa chỉ</h3><p>281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</p><h3><i class="bi bi-envelope-fill me-2"></i>Email</h3><p><a href="mailto:info@tripconnect.com">info@tripconnect.com</a><br><a href="mailto:info.asia@tripconnect.com">info.asia@tripconnect.com</a></p><h3><i class="bi bi-telephone-fill me-2"></i>Hỗ trợ</h3><p><a href="tel:1800646888">1800 646 888</a><br><small>Miễn phí 24/7</small></p></div></div>
  <div class="col-lg-7"><div class="about-reference-content"><h2>TripConnect là gì?</h2><p>TripConnect là không gian để bạn tìm cảm hứng du lịch từ cộng đồng, khám phá những lịch trình thú vị và tự xây dựng kế hoạch phù hợp với mình.</p><p>Chúng tôi tin rằng mỗi chuyến đi đều có một câu chuyện riêng. Vì vậy, TripConnect giúp việc tìm kiếm, lập kế hoạch và chia sẻ trải nghiệm trở nên đơn giản, trực quan và gần gũi hơn.</p><h3>TripConnect mang đến cho bạn</h3><div class="about-reference-feature"><i class="bi bi-map-fill"></i><div><strong>Khám phá lịch trình</strong><span>Tìm các điểm đến và lịch trình nổi bật do cộng đồng chia sẻ.</span></div></div><div class="about-reference-feature"><i class="bi bi-pencil-square"></i><div><strong>Tạo chuyến đi của riêng bạn</strong><span>Sắp xếp địa điểm, thời gian và ngân sách theo cách bạn muốn.</span></div></div><div class="about-reference-feature"><i class="bi bi-people-fill"></i><div><strong>Kết nối cùng cộng đồng</strong><span>Theo dõi, chia sẻ và lan tỏa cảm hứng với những người cùng đam mê.</span></div></div><h3>Sứ mệnh của chúng tôi</h3><p>Giúp mỗi người dễ dàng biến ý tưởng du lịch thành một hành trình thực tế, đồng thời tạo ra một cộng đồng cởi mở để mọi trải nghiệm đều có thể được chia sẻ.</p><div><div class="about-reference-stat"><strong><?php echo number_format($totalTrips); ?>+</strong><span>Lịch trình đã chia sẻ</span></div><div class="about-reference-stat"><strong><?php echo number_format($totalUsers); ?>+</strong><span>Thành viên kết nối</span></div></div></div></div>
 </section>
</main>

<main class="container py-4 py-lg-5">
 <section class="about-hero mb-5">
  <div class="row align-items-center g-4">
   <div class="col-lg-8">
    <div class="small text-uppercase fw-semibold mb-2 opacity-75">Thông tin TripConnect</div>
    <h1 class="display-5 fw-bold mb-3">Kết nối đam mê, tạo nên hành trình đáng nhớ</h1>
    <p class="lead mb-0">TripConnect là nơi bạn khám phá những lịch trình thú vị, chia sẻ trải nghiệm và cùng cộng đồng xây dựng chuyến đi theo cách riêng của mình.</p>
   </div>
   <div class="col-lg-4 text-lg-end"><i class="bi bi-compass display-1 opacity-75"></i></div>
  </div>
 </section>

 <section class="row g-4 mb-5">
  <div class="col-lg-7">
   <div class="about-card">
    <div class="about-icon"><i class="bi bi-people-fill"></i></div>
    <h2 class="h3 mb-3">TripConnect là gì?</h2>
    <p class="text-muted mb-0">TripConnect giúp mọi người tìm cảm hứng du lịch từ cộng đồng, theo dõi các lịch trình phù hợp và tự tạo kế hoạch cho chuyến đi của mình. Từ những điểm đến mới đến các kinh nghiệm thực tế, mọi nội dung đều được tập hợp để việc chuẩn bị chuyến đi trở nên dễ dàng hơn.</p>
   </div>
  </div>
  <div class="col-lg-5">
   <div class="about-card d-flex flex-column justify-content-center">
    <h2 class="h5 mb-4">TripConnect hôm nay</h2>
    <div class="row g-4">
     <div class="col-6"><div class="about-stat"><div class="h3 mb-0"><?php echo number_format($totalTrips); ?>+</div><small class="text-muted">Lịch trình đã chia sẻ</small></div></div>
     <div class="col-6"><div class="about-stat"><div class="h3 mb-0"><?php echo number_format($totalUsers); ?>+</div><small class="text-muted">Thành viên kết nối</small></div></div>
    </div>
   </div>
  </div>
 </section>

 <section class="mb-5">
  <div class="text-center mb-4"><div class="small-label fw-semibold text-muted">Vì sao chọn TripConnect?</div><h2 class="section-title fw-bold">Mọi thứ cho một chuyến đi trọn vẹn</h2></div>
  <div class="row g-4">
   <div class="col-md-4"><div class="about-card"><div class="about-icon"><i class="bi bi-map"></i></div><h3 class="h5">Khám phá điểm đến</h3><p class="text-muted mb-0">Tìm các lịch trình nổi bật và cảm hứng du lịch từ nhiều địa điểm khác nhau.</p></div></div>
   <div class="col-md-4"><div class="about-card"><div class="about-icon"><i class="bi bi-pencil-square"></i></div><h3 class="h5">Tự tạo lịch trình</h3><p class="text-muted mb-0">Sắp xếp điểm đến, thời gian và ngân sách để chủ động lên kế hoạch cho riêng bạn.</p></div></div>
   <div class="col-md-4"><div class="about-card"><div class="about-icon"><i class="bi bi-chat-heart"></i></div><h3 class="h5">Chia sẻ cùng cộng đồng</h3><p class="text-muted mb-0">Đăng trải nghiệm, theo dõi những người bạn quan tâm và cùng nhau lan tỏa cảm hứng.</p></div></div>
  </div>
 </section>

 <section class="about-card text-center mb-3">
  <h2 class="h3 mb-3">Bắt đầu hành trình của bạn</h2>
  <p class="text-muted mb-4">Khám phá lịch trình mới hoặc tạo một chuyến đi ngay hôm nay.</p>
  <a class="btn btn-primary rounded-pill px-4 me-2" href="explore.php"><i class="bi bi-compass me-1"></i> Khám phá ngay</a>
  <a class="btn btn-outline-primary rounded-pill px-4" href="create-trip.php"><i class="bi bi-plus-lg me-1"></i> Tạo lịch trình</a>
 </section>
</main>

<footer class="footer py-5 mt-5"><div class="container">
 <a class="footer-brand d-inline-flex align-items-center mb-4" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
 <div class="row g-4">
  <div class="col-lg-4 col-md-6"><h6 class="footer-title">Công ty TripConnect</h6><ul class="footer-contact list-unstyled mb-0"><li><i class="bi bi-geo-alt-fill"></i><span>281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</span></li><li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com">info@tripconnect.com</a></span></li><li><i class="bi bi-telephone-fill"></i><a href="tel:1800646888">1800 646 888</a></li></ul></div>
  <div class="col-lg-2 col-md-6 offset-lg-1"><h6 class="footer-title">Khám phá</h6><a class="footer-link" href="explore.php">Lịch trình nổi bật</a><a class="footer-link" href="feed.php">Bảng tin</a><a class="footer-link" href="create-trip.php">Tạo lịch trình</a></div>
  <div class="col-lg-2 col-md-6"><h6 class="footer-title">Thông tin</h6><a class="footer-link" href="about-us.php">Về chúng tôi</a><a class="footer-link" href="#">Tin tức</a><a class="footer-link" href="#">Sitemap</a><a class="footer-link" href="#">Trợ giúp</a></div>
  <div class="col-lg-3 col-md-6"><h6 class="footer-title">Điều kiện - Điều khoản</h6><a class="footer-link" href="#">Chính sách riêng tư</a><a class="footer-link" href="#">Thỏa thuận sử dụng</a><a class="footer-link" href="#">Chính sách bảo vệ dữ liệu cá nhân</a></div>
 </div><hr class="footer-hr my-4"><small>© 2026 TripConnect</small>
</div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=3"></script>
<script src="assets/i18n.js?v=17"></script>
<script src="assets/footer-sync.js?v=1"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.footer .footer-title').forEach(function (title) {
    if (title.textContent.trim() === 'Thông tin') {
      var column = title.parentElement;
      column.querySelectorAll('a').forEach(function (link) {
        if (link.textContent.trim() === 'Tin tức' || link.textContent.trim() === 'Sitemap') link.remove();
        if (link.textContent.trim() === 'Trợ giúp') link.href = 'help.php';
      });
    }
  });
});
</script>
<script>document.querySelectorAll('.footer a').forEach(function(link){if(link.textContent.trim()==='Chính sách riêng tư') link.href='privacy.php';});</script>
</body>
</html>
