<?php
session_start();
require_once 'config/database.php';
require_once 'config/trip_social.php';
require_once 'config/trip_recommendation.php';

$filtersInput = $_GET;
$filtersInput['user_id'] = $_SESSION['user_id'] ?? null;
$discovery = [
    'trips' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'page_size' => 9,
    'locations' => [],
    'filters' => [
        'keyword' => '', 'location' => '', 'days' => null, 'category' => '',
        'budgetScope' => 'group', 'budgetMax' => null, 'budgetMin' => null,
        'sort' => 'relevance', 'useInterests' => false, 'interests' => [],
    ],
];
$discoveryError = false;

try {
    $discovery = trip_recommendation_search($conn, $filtersInput);
} catch (PDOException $e) {
    error_log('[TripConnect explore] search failed: ' . $e->getMessage());
    $discoveryError = true;
}

$socialToken=trip_csrf_token('social');
$viewerFollowing=trip_social_following($conn,(int)($_SESSION['user_id']??0));
$savedIds=[];$q=$conn->prepare('SELECT DISTINCT trip_id FROM bookmarks WHERE user_id=?');$q->execute([(int)($_SESSION['user_id']??0)]);$savedIds=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
$trips = $discovery['trips'];
$totalResults = (int) $discovery['total'];
$currentPage = (int) $discovery['page'];
$totalPages = (int) $discovery['pages'];
$locations = $discovery['locations'];
$filters = $discovery['filters'];
$keyword = $filters['keyword'];
$location = $filters['location'];
$days = $filters['days'];
$category = $filters['category'];
$budgetScope = $filters['budgetScope'];
$budgetMax = $filters['budgetMax'];
$sort = $filters['sort'];
$useInterests = $filters['useInterests'];
$tag=$filters['tag']??null;
$isPersonalized=!empty($discovery['personalized'])&&$sort==='relevance';

function explore_query(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }
    return http_build_query($query);
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8"><meta name="social-csrf" content="<?= htmlspecialchars($socialToken,ENT_QUOTES) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Khám phá và lọc các lịch trình du lịch công khai trên TripConnect theo địa điểm, số ngày và ngân sách.">
<title data-i18n-ui>Khám phá | TripConnect</title>
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
 <li class="nav-item"><a class="nav-link active" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
 <li class="nav-item"><a class="nav-link" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
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
        <!-- Nếu đã đăng nhập: Hiển thị tên tài khoản và nút Đăng xuất -->
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

<main class="container">
 <div class="page-header">
    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
        <div>
            <div class="small-label" data-i18n="explore.page_label">Trip Library</div>
            <h1 class="section-title mb-2" data-i18n="explore.title">Khám phá lịch trình</h1>
            <p class="text-muted mb-0" data-i18n="explore.description">Tìm kiếm và lọc các chuyến đi công khai theo nhu cầu của bạn.</p>
        </div>
        <span id="resultCount" class="badge badge-soft" data-count="<?php echo $totalResults; ?>"><?php echo $totalResults; ?> <span data-i18n="explore.result_suffix">lịch trình</span></span>
    </div>
 </div>

 <div class="filter-box mb-4">
  <form id="filterForm" method="GET" action="explore.php" class="row g-3">
   <div class="col-lg-4">
        <label class="form-label fw-semibold" for="tripSearch" data-i18n="explore.keyword">Từ khóa</label>
       <div class="input-group">
           <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input id="tripSearch" name="q" class="form-control" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Đà Lạt, Bãi Dâu, check-in..." data-i18n="explore.search_placeholder">
       </div>
   </div>
<div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterLocation" data-i18n="explore.location">Khu vực</label>
        <input id="filterLocation" name="location" class="form-control" list="tripLocations" value="<?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Vũng Tàu, Đà Lạt..." data-i18n-attrs>
        <datalist id="tripLocations">
            <?php foreach ($locations as $locName): ?>
                <option value="<?php echo htmlspecialchars($locName, ENT_QUOTES, 'UTF-8'); ?>"></option>
            <?php endforeach; ?>
        </datalist>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterDays" data-i18n="explore.days">Số ngày</label>
       <input id="filterDays" name="days" type="number" min="1" max="365" class="form-control" value="<?php echo $days !== false && $days !== null ? (int) $days : ''; ?>" placeholder="Ví dụ: 3" data-i18n-attrs>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterBudgetMax"><span data-i18n-ui>Chi phí dự kiến tối đa</span></label>
        <input id="filterBudgetMax" name="budget_max" type="number" min="0" step="1000" class="form-control" value="<?php echo $budgetMax !== null ? htmlspecialchars((string) $budgetMax, ENT_QUOTES, 'UTF-8') : ''; ?>" placeholder="VNĐ" data-i18n-attrs>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterBudgetScope"><span data-i18n-ui>Phạm vi ngân sách</span></label>
       <select id="filterBudgetScope" name="budget_scope" class="form-select">
            <option value="group" <?php echo $budgetScope === 'group' ? 'selected' : ''; ?> data-i18n-ui>Cả nhóm</option>
            <option value="personal" <?php echo $budgetScope === 'personal' ? 'selected' : ''; ?> data-i18n-ui>Mỗi người</option>
       </select>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterCategory"><span data-i18n-ui>Loại hoạt động</span></label>
       <select id="filterCategory" name="category" class="form-select">
            <option value="" data-i18n-ui>Tất cả</option>
            <option value="sightseeing" <?php echo $category === 'sightseeing' ? 'selected' : ''; ?> data-i18n-ui>Tham quan</option>
            <option value="food" <?php echo $category === 'food' ? 'selected' : ''; ?> data-i18n-ui>Ăn uống</option>
            <option value="lodging" <?php echo $category === 'lodging' ? 'selected' : ''; ?> data-i18n-ui>Lưu trú</option>
            <option value="entertainment" <?php echo $category === 'entertainment' ? 'selected' : ''; ?> data-i18n-ui>Giải trí</option>
            <option value="rest" <?php echo $category === 'rest' ? 'selected' : ''; ?> data-i18n-ui>Nghỉ ngơi</option>
            <option value="transport" <?php echo $category === 'transport' ? 'selected' : ''; ?> data-i18n-ui>Di chuyển</option>
       </select>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterTag"><span data-i18n-ui>Tags / chủ đề</span></label>
        <select id="filterTag" name="tag" class="form-select"><option value="" data-i18n-ui>Tất cả</option>
         <?php foreach($discovery['tags']??[] as $option): ?><option value="<?= (int)$option['id'] ?>" <?= (int)$tag===(int)$option['id']?'selected':'' ?>><?= htmlspecialchars($option['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?>
        </select>
   </div>
   <div class="col-md-4 col-lg-2">
        <label class="form-label fw-semibold" for="filterSort"><span data-i18n-ui>Sắp xếp</span></label>
       <select id="filterSort" name="sort" class="form-select">
            <option value="relevance" <?php echo $sort === 'relevance' ? 'selected' : ''; ?> data-i18n-ui>Phù hợp nhất</option>
            <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?> data-i18n-ui>Mới nhất</option>
            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?> data-i18n-ui>Phổ biến</option>
            <option value="cost" <?php echo $sort === 'cost' ? 'selected' : ''; ?> data-i18n-ui>Chi phí thấp</option>
       </select>
   </div>
   <div class="col-lg-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary w-100" data-i18n="explore.filter_button">Lọc kết quả</button>
        <a href="explore.php" class="btn btn-outline-secondary" aria-label="Đặt lại bộ lọc" data-i18n-attrs><i class="bi bi-arrow-counterclockwise"></i></a>
   </div>
   <?php if (!empty($_SESSION['user_id'])): ?>
   <div class="col-12 form-check mt-2">
      <input type="hidden" name="use_interests" value="0">
      <input class="form-check-input" type="checkbox" id="useInterests" name="use_interests" value="1" <?php echo $useInterests ? 'checked' : ''; ?>>
      <label class="form-check-label" for="useInterests"><span data-i18n-ui>Cá nhân hóa bằng sở thích và tương tác của tôi</span></label>
   </div>
   <?php endif; ?>
  </form>
 </div>

<?php if ($discoveryError): ?>
 <div class="alert alert-danger"><span data-i18n-ui>Không thể tải dữ liệu lịch trình lúc này. Vui lòng thử lại sau.</span></div>
<?php endif; ?>

<!-- Danh sách chuyến đi render động từ Database -->
<section class="mb-4" aria-labelledby="discoveryHeading">
 <h2 id="discoveryHeading" class="h4"><span data-i18n-ui><?= $isPersonalized?'Gợi ý dành cho bạn':'Khám phá lịch trình công khai' ?></span></h2>
 <?php if($isPersonalized): ?><p class="text-muted small"><span data-i18n-ui>Xếp hạng từ sở thích đã lưu và các tương tác thật. Mở “Vì sao gợi ý?” trên từng lịch trình để xem lý do.</span></p>
 <?php else: ?><p class="text-muted small"><span data-i18n-ui>Danh sách theo bộ lọc và cách sắp xếp bạn chọn. Khi chưa có tín hiệu phù hợp, hệ thống ưu tiên mức quan tâm cộng đồng và lịch trình mới.</span></p><?php endif; ?>
 <?php if(!empty($_SESSION['user_id'])): ?><a href="profile.php#editProfileModal" class="btn btn-sm btn-outline-primary"><span data-i18n-ui>Cập nhật sở thích du lịch</span></a><?php else: ?><a href="login.php?next=explore.php" class="btn btn-sm btn-outline-primary"><span data-i18n-ui>Đăng nhập để nhận gợi ý cá nhân</span></a><?php endif; ?>
 <details class="small mt-3 text-muted"><summary><span data-i18n-ui>Cách xếp hạng</span></summary><span data-i18n-ui>Sở thích tối đa 35 điểm; tiêu chí tìm kiếm 30; tương tự lịch trình đã thích/lưu và tác giả đang theo dõi 25; mức quan tâm cộng đồng 10. Điểm là tín hiệu xếp hạng trên thang 100, không phải xác suất phù hợp. Bộ lọc luôn được áp dụng trước khi chấm điểm.</span></details>
</section>
<div id="tripGrid" class="row g-4">
  <?php if (!empty($trips)): ?>
    <?php foreach ($trips as $trip): ?>
      <?php $trip['cover_image'] = trip_cover_display_image($trip); ?>
      <div class="col-md-6 col-xl-4 trip-item">
       <div class="card trip-card">
        <?php if (!empty($trip['cover_image'])): ?>
          <img class="trip-img" src="<?php echo htmlspecialchars($trip['cover_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
          <div class="trip-img d-flex align-items-center justify-content-center bg-light text-secondary"><i class="bi bi-image fs-1" aria-hidden="true"></i><span class="visually-hidden"><span data-i18n-ui>Chưa có ảnh phù hợp</span></span></div>
        <?php endif; ?>
        <div class="card-body">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><?php trip_social_author(['id'=>$trip['user_id'],'full_name'=>$trip['author_name'],'avatar'=>$trip['author_avatar'],'username'=>$trip['author_username']]);trip_social_follow_button((int)$trip['user_id'],$viewerFollowing); ?></div>

         <div class="d-flex justify-content-between">
              <span class="badge badge-soft"><?php echo (int) $trip['days_count']; ?> <span data-i18n="explore.day_suffix">ngày</span></span>
              <?php if ($trip['recommendation_score'] !== null): ?><span class="badge bg-primary-subtle text-primary"><span data-i18n-ui>Điểm xếp hạng </span><?php echo (int) $trip['recommendation_score']; ?>/100</span><?php endif; ?>
              <span class="small text-muted"><i class="bi bi-heart"></i> <?php echo (int) $trip['like_count']; ?></span>
         </div>
         <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($trip['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
         <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($trip['location_name'] ?? 'Chưa xác định', ENT_QUOTES, 'UTF-8'); ?></p>
         <p class="small text-muted"><?php echo htmlspecialchars(mb_strimwidth((string) ($trip['description'] ?? ''), 0, 120, '...', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></p>
         <?php if ($trip['recommendation_reasons']): ?>
           <details class="small text-success mb-2"><summary><span data-i18n-ui>Vì sao gợi ý?</span></summary><ul class="mt-2"><?php foreach($trip['recommendation_reasons'] as $reason): ?><li data-i18n-ui><?= htmlspecialchars($reason,ENT_QUOTES,'UTF-8') ?></li><?php endforeach; ?></ul></details>
         <?php endif; ?>
         <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
<button type="button" class="btn btn-sm btn-light" data-social-action="bookmark" data-target="<?= (int)$trip['id'] ?>" data-active="<?= (int)isset($savedIds[$trip['id']]) ?>" aria-pressed="<?= isset($savedIds[$trip['id']])?'true':'false' ?>"><i class="bi bi-bookmark<?= isset($savedIds[$trip['id']])?'-fill':'' ?>"></i> <span data-social-label data-i18n-ui><span data-i18n-ui><?= isset($savedIds[$trip['id']])?'Đã lưu':'Lưu' ?></span></span></button>
             <div class="small">
               <div><span class="text-muted"><span data-i18n-ui>Mục tiêu:</span></span> <strong><span data-i18n-number><?php echo number_format((float) $trip['budget_estimate'], 0, ',', '.'); ?></span><span data-i18n-ui>đ</span></strong></div>
               <?php if ($trip['cost_complete']): ?>
                 <div><span class="text-muted"><span data-i18n-ui>Dự kiến:</span></span> <span data-i18n-number><?php echo number_format((float) $trip['calculated_total'], 0, ',', '.'); ?></span><span data-i18n-ui>đ</span></div>
                 <?php if ($trip['calculated_average'] !== null): ?><div><span class="text-muted"><span data-i18n-ui>Mỗi người:</span></span> <span data-i18n-number><?php echo number_format((float) $trip['calculated_average'], 0, ',', '.'); ?></span><span data-i18n-ui>đ</span></div><?php endif; ?>
               <?php else: ?>
                 <div class="text-warning"><span data-i18n-ui>Chi phí chưa đủ dữ liệu</span></div>
               <?php endif; ?>
             </div>
              <a href="trip-detail.php?id=<?php echo (int) $trip['id']; ?>" class="btn btn-sm btn-soft" data-i18n="explore.view_details">Xem chi tiết</a>
         </div>
        </div>
       </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <!-- Trạng thái trống khi không tìm thấy kết quả phù hợp -->
    <div id="exploreEmpty" class="col-12 text-center text-muted py-5 is-visible">
     <i class="bi bi-emoji-frown fs-1"></i>
     <p class="mt-3 mb-0" data-i18n="explore.no_results">Không tìm thấy lịch trình phù hợp. Hãy thử từ khóa hoặc bộ lọc khác.</p>
    </div>
  <?php endif; ?>
</div>

 <?php if ($totalPages > 1): ?>
 <nav class="mt-5 mb-5" aria-label="Phân trang lịch trình" data-i18n-attrs>
   <ul class="pagination justify-content-center">
     <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
       <li class="page-item <?php echo $pageNumber === $currentPage ? 'active' : ''; ?>">
         <a class="page-link" href="explore.php?<?php echo htmlspecialchars(explore_query(['page' => $pageNumber]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $pageNumber; ?></a>
       </li>
     <?php endfor; ?>
   </ul>
 </nav>
 <?php endif; ?>
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
   <div class="col-lg-2 col-md-6"><h6 class="footer-title" data-i18n="footer.info_title">Thông tin</h6><a class="footer-link" href="about-us.php" data-i18n="footer.about">Về chúng tôi</a><a class="footer-link" href="#" data-i18n="footer.news">Tin tức</a><a class="footer-link" href="#" data-i18n="footer.sitemap">Sitemap</a><a class="footer-link" href="#" data-i18n="footer.help">Trợ giúp</a></div>
   <div class="col-lg-3 col-md-6"><h6 class="footer-title" data-i18n="footer.terms_title">Điều kiện - Điều khoản</h6><a class="footer-link" href="#" data-i18n="footer.privacy">Chính sách riêng tư</a><a class="footer-link" href="#" data-i18n="footer.terms">Thỏa thuận sử dụng</a><a class="footer-link" href="#" data-i18n="footer.data_policy">Chính sách bảo vệ dữ liệu cá nhân</a></div>
  </div>
  <hr class="footer-hr my-4"><small><span data-i18n-ui>© 2026 TripConnect</span></small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=6"></script>
<script src="assets/i18n.js?v=10"></script>
<script src="assets/pages/explore.js?v=2"></script>
</body>
</html>
