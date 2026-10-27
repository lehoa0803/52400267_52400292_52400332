<?php
// Nhúng file kết nối cơ sở dữ liệu
session_start();
require_once 'config/database.php';
require_once 'config/trip_timeline.php';
require_once 'config/trip_social.php';
$socialToken = trip_csrf_token('social');
$socialState = ['likes'=>0,'liked'=>false,'bookmarked'=>false,'following'=>false];
$comment_error = '';

// Lấy trip_id động từ URL (ví dụ: trip-detail.php?id=2). Không có id thì hiện "Không tìm thấy"
$trip_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$detail_csrf_token = $_SESSION['detail_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['detail_csrf_token'] = $detail_csrf_token;

// Giá trị mặc định
$trip_days = [];
$budgetData = [];
$dayTotals = [];
$tripTotal = 0.0;
$costPerPerson = null;
$stops = [];
$center = null;
$likeCount = 0;
$comments = [];
$tags = [];

// Gửi bình luận (chỉ khi đã đăng nhập)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $trip_id > 0) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    if (!hash_equals($_SESSION['detail_csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Yêu cầu không hợp lệ.');
    }
    try {
        trip_social_action($conn,(int)$_SESSION['user_id'],'comment',['target'=>$trip_id,'content'=>$_POST['content']??'','parent_id'=>$_POST['parent_id']??null]);
        header("Location: trip-detail.php?id=" . $trip_id . "#comments");exit;
    } catch (PDOException $e) { $comment_error = 'Không thể lưu bình luận. Vui lòng thử lại.'; }
    catch (RuntimeException|InvalidArgumentException $e) { $comment_error = $e->getMessage(); }
}

try {
    // 1. Truy vấn thông tin chi tiết chuyến đi, thông tin tác giả và địa điểm theo ID động
    $viewerId = 0;
    $viewerRole = '';
    if (!empty($_SESSION['user_id'])) {
        $viewerQuery = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND status = 'active'");
        $viewerQuery->execute([(int) $_SESSION['user_id']]);
        if ($viewer = $viewerQuery->fetch(PDO::FETCH_ASSOC)) {
            $viewerId = (int) $viewer['id'];
            $viewerRole = $viewer['role'];
        }
    }
    $stmtTrip = $conn->prepare("SELECT t.*, u.username as author_username, u.full_name as author_name, u.avatar as author_avatar, l.name as location_name,
                                       (SELECT lc.image_url FROM trip_days td JOIN activities a ON a.trip_day_id = td.id JOIN location_catalog lc ON lc.id = a.location_catalog_id WHERE td.trip_id = t.id AND NULLIF(TRIM(lc.image_url), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS catalog_image,
                                       (SELECT a.image FROM trip_days td JOIN activities a ON a.trip_day_id = td.id WHERE td.trip_id = t.id AND NULLIF(TRIM(a.image), '') IS NOT NULL ORDER BY td.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.id LIMIT 1) AS activity_image
                                FROM trips t 
                                LEFT JOIN users u ON t.user_id = u.id 
                                LEFT JOIN locations l ON t.location_id = l.id 
                                WHERE t.id = ?
                                  AND ((t.status = 'published' AND t.moderation_status = 'visible' AND u.status = 'active')
                                       OR (t.user_id = ? AND t.status <> 'deleted')
                                       OR ? = 'admin')");
    $stmtTrip->execute([$trip_id, $viewerId, $viewerRole]);
    $trip = $stmtTrip->fetch(PDO::FETCH_ASSOC);
    if ($trip) {
        $trip['cover_image'] = trip_cover_display_image($trip);
        $socialState = trip_social_state($conn,$trip_id,(int)($_SESSION['user_id']??0),(int)$trip['user_id']);
    }

    // 2. Nếu tìm thấy chuyến đi, lấy tiếp danh sách các ngày (trip_days) ĐÚNG THEO CHUYẾN ĐI ĐÓ
    if ($trip) {
        $stmtDays = $conn->prepare("SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number ASC");
        $stmtDays->execute([$trip_id]);
        $trip_days = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

        // Chi phí được tính trực tiếp từ activities; per_person nhân participant_count, fixed không nhân.
        $activityGroups = [];
        foreach ($trip_days as $day) $activityGroups[(int) $day['id']] = [];
        $stmtActivities = $conn->prepare("SELECT a.*, lc.name AS catalog_name, lc.latitude AS catalog_latitude, lc.longitude AS catalog_longitude
            FROM activities a JOIN trip_days d ON d.id = a.trip_day_id
            LEFT JOIN location_catalog lc ON lc.id = a.location_catalog_id AND lc.status = 'active'
            WHERE d.trip_id = ? ORDER BY d.day_number, COALESCE(a.sort_order, 65535), a.start_at, a.start_time, a.id");
        $stmtActivities->execute([$trip_id]);
        foreach ($stmtActivities->fetchAll(PDO::FETCH_ASSOC) as $activity) {
            $activityGroups[(int) $activity['trip_day_id']][] = $activity;
            $stop = trip_timeline_map_stop($activity);
            if ($stop) $stops[] = $stop;
        }
        $budgetSummary = trip_timeline_budget_summary($activityGroups, !empty($trip['participant_count']) ? (int) $trip['participant_count'] : null);
        $tripTotal = $budgetSummary['cents'] / 100;
        $costPerPerson = $budgetSummary['per_person'];
        $dayTotals = $budgetSummary['days'];
        $palette = ['#e85a45', '#eda247', '#0f6e66', '#128077', '#8a9aa0', '#9272b1', '#4f819e'];
        $categoryColors = [];
        foreach ($budgetSummary['categories'] as $code => $group) {
            $categoryColors[$code] = $palette[count($budgetData) % count($palette)];
            if ($group['cents'] > 0) $budgetData[] = ['label' => $group['label'], 'value' => $group['cents'] / 100, 'color' => $categoryColors[$code]];
        }

        $stmtL = $conn->prepare("SELECT COUNT(*) FROM likes WHERE trip_id = ?");
        $stmtL->execute([$trip_id]);
        $likeCount = (int)$stmtL->fetchColumn();

        $stmtC = $conn->prepare("SELECT c.id, c.user_id, c.parent_id, c.content, c.created_at, u.full_name, u.avatar, u.username
                                 FROM comments c JOIN users u ON u.id = c.user_id
                                 WHERE c.trip_id = ? AND COALESCE(c.status,'pending') <> 'hidden' AND u.status='active' ORDER BY c.created_at ASC,c.id ASC");
        $stmtC->execute([$trip_id]);
        $comments = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        $visibleRoots=array_fill_keys(array_map(static fn($c)=>(int)$c['id'],array_filter($comments,static fn($c)=>$c['parent_id']===null)),true);
        $comments=array_values(array_filter($comments,static fn($c)=>$c['parent_id']===null||isset($visibleRoots[(int)$c['parent_id']])));

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
<meta name="social-csrf" content="<?php echo htmlspecialchars($socialToken,ENT_QUOTES,'UTF-8'); ?>">
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
<nav class="navbar navbar-expand-xl sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng" data-i18n-attrs><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
     <li class="nav-item"><a class="nav-link" href="index.php" data-i18n="nav.home">Trang chủ</a></li>
     <li class="nav-item"><a class="nav-link" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
     <li class="nav-item"><a class="nav-link" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
     <li class="nav-item"><a class="nav-link" href="profile.php" data-i18n="nav.profile">Hồ sơ</a></li><?php trip_social_nav(); ?>
   </ul>
    <div class="d-flex gap-2 align-items-center">
     <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span></button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4"><li><a class="dropdown-item py-2" href="#" data-lang-btn="vi"><span data-i18n-ui>🇻🇳 Tiếng Việt</span></a></li><li><a class="dropdown-item py-2" href="#" data-lang-btn="en"><span data-i18n-ui>🇬🇧 English</span></a></li></ul>
     </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <span class="text-secondary small d-none d-md-inline"><span data-i18n-ui>Xin chào, </span><strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
        <form method="post" action="logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill"><span data-i18n-ui>Đăng xuất</span></button></form>
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
   <button type="button" data-social-action="like" data-target="<?php echo $trip_id; ?>" data-active="<?php echo (int)$socialState['liked']; ?>" aria-pressed="<?php echo $socialState['liked']?'true':'false'; ?>" class="btn btn-light me-2 <?php echo $socialState['liked']?'is-liked':''; ?>"><i class="bi <?php echo $socialState['liked']?'bi-heart-fill':'bi-heart'; ?>"></i> <span data-social-label data-i18n-ui><span data-i18n-ui><?php echo $socialState['liked']?'Đã thích':'Thích'; ?></span></span> (<span data-like-count><?php echo $socialState['likes']; ?></span>)</button>
   <button type="button" data-social-action="bookmark" data-target="<?php echo $trip_id; ?>" data-active="<?php echo (int)$socialState['bookmarked']; ?>" aria-pressed="<?php echo $socialState['bookmarked']?'true':'false'; ?>" class="btn btn-light"><i class="bi <?php echo $socialState['bookmarked']?'bi-bookmark-fill':'bi-bookmark'; ?>"></i> <span data-social-label data-i18n-ui><span data-i18n-ui><?php echo $socialState['bookmarked']?'Đã lưu':'Lưu'; ?></span></span></button>
   <div id="socialStatus" class="small text-danger mt-2" role="status" aria-live="polite"></div>
  </div>
 </div>

 <?php if ($trip): ?>
 <div class="row g-4">
  <div class="col-lg-8">
   <?php if (!empty($trip['cover_image'])): ?><img class="cover-lg img-fluid rounded w-100" src="<?php echo htmlspecialchars($trip['cover_image']); ?>" alt="<?php echo htmlspecialchars($trip['title']); ?>"><?php else: ?><div class="cover-lg img-fluid rounded w-100 d-flex align-items-center justify-content-center bg-light text-secondary" role="img" aria-label="Chưa có ảnh bìa phù hợp" data-i18n-attrs><i class="bi bi-image fs-1" aria-hidden="true"></i></div><?php endif; ?>
   
   <div class="card mt-4 p-4">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <span class="badge badge-soft"><?php echo htmlspecialchars($trip['days_count']); ?> <span data-i18n="detail.day_suffix">ngày</span></span>
            <h1 class="mt-3 mb-2"><?php echo htmlspecialchars($trip['title']); ?></h1>
            <p class="text-muted mb-0"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($trip['location_name'] ?? 'Việt Nam'); ?> · <i class="bi bi-eye ms-2"></i> <span data-i18n-number><?php echo number_format($trip['view_count']); ?></span> <span data-i18n="detail.views">lượt xem</span></p>
            <div class="small text-muted mt-2">
                <?php if (!empty($trip['start_location'])): ?><span class="me-3"><i class="bi bi-signpost-2"></i><span data-i18n-ui> Từ </span><?php echo htmlspecialchars($trip['start_location']); ?></span><?php endif; ?>
                <?php if (!empty($trip['departure_at'])): ?><span class="me-3"><i class="bi bi-calendar-event"></i> <span data-i18n-date><?php echo date('d/m/Y H:i', strtotime($trip['departure_at'])); ?></span></span><?php endif; ?>
                <?php if (!empty($trip['return_at'])): ?><span><i class="bi bi-calendar-check"></i> <span data-i18n-date><?php echo date('d/m/Y H:i', strtotime($trip['return_at'])); ?></span></span><?php endif; ?>
            </div>
            <?php if (!empty($trip['participant_count'])): ?><div class="small text-muted mt-1"><i class="bi bi-people"></i> <?php echo (int) $trip['participant_count']; ?><span data-i18n-ui> người tham gia</span></div><?php endif; ?>
        </div>
        <?php trip_social_author(['id'=>$trip['user_id'],'full_name'=>$trip['author_name'],'avatar'=>$trip['author_avatar'],'username'=>$trip['author_username']]); ?>
    </div>
    <hr>
    <p><?php echo nl2br(htmlspecialchars($trip['description'])); ?></p>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($tags as $tg): ?>
        <span class="badge bg-light text-dark">#<?php echo htmlspecialchars($tg); ?></span>
        <?php endforeach; ?>
    </div>
   </div>

   <?php if (!empty($stops)): ?><div class="card mt-4 p-4 trip-map-card">
     <h4 class="mb-3" data-i18n="detail.map_title">Bản đồ hành trình</h4>
    <div id="tripMap" role="img" aria-label="Bản đồ các điểm đến trong lịch trình" style="height: 350px; border-radius: 8px;" data-i18n-attrs></div>
   </div><?php else: ?><div class="card mt-4 p-4 trip-map-card"><h4 class="mb-3" data-i18n="detail.map_title">Bản đồ hành trình</h4><p class="text-muted mb-2"><i class="bi bi-info-circle me-1"></i><span data-i18n-ui>Chưa có tọa độ đã xác minh cho hoạt động hoặc địa điểm catalog trong lịch trình.</span></p><?php if (!empty($trip['location_name'])): ?><p class="small text-muted mb-2"><span data-i18n-ui>Khu vực chính: </span><?php echo htmlspecialchars($trip['location_name'], ENT_QUOTES, 'UTF-8'); ?><span data-i18n-ui>. Hãy chọn địa điểm từ catalog khi chỉnh sửa để bổ sung vị trí thật.</span></p><a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer" href="https://www.openstreetmap.org/search?query=<?php echo rawurlencode($trip['location_name']); ?>"><span data-i18n-ui>Mở tìm kiếm khu vực trên OpenStreetMap</span></a><?php endif; ?></div><?php endif; ?>

   <!-- Timeline hành trình theo từng ngày -->
   <div class="card mt-4 p-4">
     <h4 class="mb-4" data-i18n="detail.timeline_title">Timeline hành trình</h4>
    <div id="activityList" class="timeline">
     <?php if (!empty($trip_days)): ?>
         <?php foreach ($trip_days as $day): ?>
             <div class="mb-4">
                  <h5 class="text-primary fw-bold"><span data-i18n="detail.day_label">Ngày</span> <?php echo (int) $day['day_number']; ?><?php if (!empty($day['day_date'])): ?> - <span data-i18n-date><?php echo date('d/m/Y', strtotime($day['day_date'])); ?></span><?php endif; ?></h5>
                 <p class="small text-muted mb-3"><em><?php echo htmlspecialchars($day['note']); ?></em></p>
                 
                 <!-- Hoạt động đã được tải cùng dữ liệu Budget và bản đồ. -->
                 <?php
                 $activities = $activityGroups[(int) $day['id']] ?? [];
                 ?>

                 <?php if (!empty($activities)): ?>
                     <?php foreach ($activities as $act): ?>
                         <div class="timeline-item mb-3 ps-3 border-start position-relative">
                             <span class="timeline-dot position-absolute start-0 translate-middle-x bg-primary rounded-circle" style="width: 10px; height: 10px; top: 5px;"></span>
                             <div class="small-label fw-bold text-muted">
                                 <?php if (!empty($act['start_at']) || !empty($act['end_at'])): ?>
                                     <?php echo !empty($act['start_at']) ? date('H:i', strtotime($act['start_at'])) : '--'; ?> – <?php echo !empty($act['end_at']) ? date('H:i', strtotime($act['end_at'])) : '--'; ?><?php if (!empty($act['start_at']) && !empty($act['end_at']) && date('Y-m-d', strtotime($act['start_at'])) !== date('Y-m-d', strtotime($act['end_at']))): ?> <span class="text-muted"><span data-i18n-ui>(ngày hôm sau)</span></span><?php endif; ?>
                                 <?php elseif (!empty($act['start_time'])): ?><span data-i18n-date><?php echo date('H:i', strtotime($act['start_time'])); ?></span><?php else: ?><span data-i18n-ui>Chưa xác định thời gian</span><?php endif; ?>
                             </div>
                             <h6 class="mb-1"><?php echo htmlspecialchars($act['title']); ?></h6>
                             <?php $activityCost = trip_timeline_cost_cents($act, !empty($trip['participant_count']) ? (int) $trip['participant_count'] : null); ?>
                             <div class="text-muted small"><span data-i18n-ui class="badge bg-light text-dark"><?php echo htmlspecialchars(trip_timeline_category_label(trip_timeline_category_code($act['category']))); ?></span> <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($act['location_name']); ?> · <?php if($activityCost!==null): ?><span data-i18n-number><?= trip_timeline_money($activityCost/100) ?></span><span data-i18n-ui>đ (cả nhóm)</span><?php else: ?><span data-i18n-ui>Chưa xác định đủ chi phí/phạm vi</span><?php endif; ?></div>
                             <?php if (!empty($act['description'])): ?><div class="small mt-1"><?php echo nl2br(htmlspecialchars($act['description'])); ?></div><?php endif; ?>
                             <?php if (!empty($act['notes'])): ?><div class="small text-muted"><span data-i18n-ui>Ghi chú: </span><?php echo nl2br(htmlspecialchars($act['notes'])); ?></div><?php endif; ?>
                             <?php if (!empty($act['menu_suggestions'])): ?><div class="small text-muted"><span data-i18n-ui>Menu/món gợi ý: </span><?php echo nl2br(htmlspecialchars($act['menu_suggestions'])); ?></div><?php endif; ?>
                             <?php if (!empty($act['nearby_amenities'])): ?><div class="small text-muted"><span data-i18n-ui>Tiện ích gần đó: </span><?php echo nl2br(htmlspecialchars($act['nearby_amenities'])); ?></div><?php endif; ?>
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
    <?php if ($comment_error !== ''): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($comment_error); ?></div><?php endif; ?>
     <h4><span data-i18n="detail.comments">Bình luận</span> (<span id="commentCount"><?php echo count($comments); ?></span>)</h4>
    <?php if (isset($_SESSION['user_id'])): ?>
    <form id="commentForm" method="post" action="trip-detail.php?id=<?php echo (int)$trip_id; ?>" class="d-flex gap-2 my-3">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($detail_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input id="commentInput" name="content" class="form-control" placeholder="Chia sẻ cảm nhận của bạn..." data-i18n="detail.comment_placeholder" required maxlength="1000" aria-label="Viết bình luận">
        <button class="btn btn-primary" type="submit" data-i18n="detail.send">Gửi</button>
    </form>
    <?php else: ?>
     <p class="my-3 text-muted"><span data-i18n="detail.login_to_comment">Vui lòng</span> <a href="login.php" data-i18n="nav.login">đăng nhập</a> <span data-i18n="detail.to_comment">để bình luận.</span></p>
    <?php endif; ?>
    <div id="commentList">
        <?php $roots=[];$replies=[];foreach($comments as $c){if($c['parent_id']===null)$roots[]=$c;else $replies[(int)$c['parent_id']][]=$c;}
        foreach($roots as $c): ?>
        <div class="comment-thread"><?php trip_social_comment($c,$trip_id,$detail_csrf_token);foreach($replies[(int)$c['id']]??[] as $reply)trip_social_comment($reply,$trip_id,$detail_csrf_token,true); ?></div>
        <?php endforeach; ?>
         <?php if (empty($comments)): ?><p class="text-muted mb-0" data-i18n="detail.no_comments">Chưa có bình luận nào. Hãy là người đầu tiên!</p><?php endif; ?>
    </div>
   </div>
  </div>

  <div class="col-lg-4">
   <div class="card p-4 sticky-side">
    <div class="d-flex justify-content-between">
        <div>
            <div class="small-label" data-i18n="detail.budget">Ngân sách mục tiêu</div>
            <div class="display-6 fw-bold"><span data-i18n-number><?php echo trip_timeline_money((float) ($trip['budget_estimate'] ?? 0)); ?></span><span data-i18n-ui>đ</span></div>
            <div class="small text-muted mt-1"><span data-i18n-ui><?php echo $budgetSummary['incomplete'] ? 'Chi phí đã xác định: ' : 'Tổng từ hoạt động: '; ?></span><strong><span data-i18n-number><?php echo trip_timeline_money($tripTotal); ?></span><span data-i18n-ui>đ</span></strong></div>
            <?php if ($budgetSummary['incomplete']): ?><div class="small text-warning"><span data-i18n-ui>Còn </span><?php echo (int) $budgetSummary['incomplete']; ?><span data-i18n-ui> khoản chưa xác định chi phí/phạm vi hoặc thiếu số người. Tổng chuyến đi và trung bình/người chưa đầy đủ.</span></div><?php endif; ?>
            <?php if ($costPerPerson !== null): ?><div class="small text-muted"><span data-i18n-ui>Trung bình/người: </span><span data-i18n-number><?php echo trip_timeline_money($costPerPerson); ?></span><span data-i18n-ui>đ</span></div><?php endif; ?>
            <?php $budgetDifference = (float) ($trip['budget_estimate'] ?? 0) - $tripTotal; ?>
            <?php if (!$budgetSummary['count']): ?><div class="small text-muted mt-2" role="status"><span data-i18n-ui>Chưa có hoạt động để so sánh với ngân sách.</span></div><?php elseif (!$budgetSummary['incomplete'] || $budgetDifference < 0): ?><div class="small mt-2 <?php echo $budgetDifference < 0 ? 'text-danger' : 'text-success'; ?>" role="status"><?php echo $budgetDifference < 0 ? ($budgetSummary['incomplete'] ? 'Chi phí đã xác định vượt ngân sách ít nhất: ' : 'Vượt ngân sách dự kiến: ') : 'Ngân sách dự kiến còn lại: '; ?><span data-i18n-number><?php echo trip_timeline_money(abs($budgetDifference)); ?></span><span data-i18n-ui>đ</span></div><?php endif; ?>
            <div class="small text-muted mt-1"><span data-i18n-ui>Chi phí hoạt động là dự kiến cho cả nhóm; chưa phải khoản chi thực tế.</span></div>
        </div>
        <div class="icon-circle"><i class="bi bi-wallet2"></i></div>
    </div>
    <hr>
    <canvas id="budgetChart" height="220" role="img" aria-label="Biểu đồ phân bổ ngân sách theo hạng mục" data-i18n-attrs></canvas>
    <div id="budgetChartStatus" class="small text-muted" role="status"><?php echo !$budgetData ? 'Chưa có chi phí đã xác định lớn hơn 0 để vẽ biểu đồ.' : ($budgetSummary['incomplete'] ? 'Biểu đồ chỉ gồm các khoản đã xác định.' : ''); ?></div>
    <ul class="list-unstyled small mt-3 mb-0" id="budgetLegend">
    <?php foreach ($budgetSummary['categories'] as $code => $group): ?><li><span aria-hidden="true" style="display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;background:<?php echo $categoryColors[$code]; ?>"></span><span data-i18n-ui><?php echo htmlspecialchars($group['label']); ?></span>: <span data-i18n-ui><?php echo $group['incomplete'] ? '≥ ' : ''; ?></span><span data-i18n-number><?php echo trip_timeline_money($group['cents'] / 100); ?></span><span data-i18n-ui>đ</span><?php if ($group['incomplete']): ?> · <?php echo (int) $group['incomplete']; ?><span data-i18n-ui> khoản chưa xác định</span><?php endif; ?></li><?php endforeach; ?>
    </ul>
    <div class="border-top mt-3 pt-3">
      <h6><span data-i18n-ui>Tổng theo ngày</span></h6>
      <?php foreach ($trip_days as $day): $dayBudget = $dayTotals[(int) $day['id']] ?? ['cents' => 0, 'incomplete' => 0]; ?><div class="d-flex justify-content-between small"><span><span data-i18n-ui>Ngày </span><?php echo (int) $day['day_number']; ?></span><span><span data-i18n-ui><?php echo $dayBudget['incomplete'] ? '≥ ' : ''; ?></span><span data-i18n-number><?php echo trip_timeline_money($dayBudget['cents'] / 100); ?></span><span data-i18n-ui>đ</span><?php if ($dayBudget['incomplete']): ?> · <?php echo (int) $dayBudget['incomplete']; ?><span data-i18n-ui> khoản chưa xác định</span><?php endif; ?></span></div><?php endforeach; ?>
    </div>
    <div class="d-grid gap-2 mt-3">
      <button id="cloneBtn" type="button" data-social-action="clone" data-target="<?php echo $trip_id; ?>" class="btn btn-primary"><i class="bi bi-copy"></i> <span data-i18n="detail.clone">Clone lịch trình</span></button>
      <button id="exportBtn" type="button" class="btn btn-soft"><span data-i18n-ui>In / Lưu PDF</span></button>
      <a class="btn btn-outline-secondary" href="export-trip.php?id=<?= (int)$trip_id ?>&amp;format=png"><span data-i18n-ui>Xuất ảnh PNG</span></a>
    </div>
   </div>
   
   <div class="card p-4 mt-4 trip-author-card">
     <h6 data-i18n="detail.author">Tác giả</h6>
    <div class="d-flex align-items-center gap-3">
        <a href="profile.php?id=<?= (int)$trip['user_id'] ?>"><img class="avatar-lg rounded-circle" src="<?php echo htmlspecialchars($trip['author_avatar'] ?? 'https://i.pravatar.cc/160?img=32'); ?>" width="60" height="60" alt="Avatar" data-i18n-attrs></a>
        <div>
            <strong><a href="profile.php?id=<?= (int)$trip['user_id'] ?>"><?php echo htmlspecialchars($trip['author_name'] ?? 'TripConnect User'); ?></a></strong>
         <div class="small text-muted" data-i18n="detail.active_member">Thành viên tích cực</div>
        </div>
    </div>
      <?php if ((int)($_SESSION['user_id']??0)!==(int)$trip['user_id']): ?><button type="button" data-social-action="follow" data-target="<?php echo (int)$trip['user_id']; ?>" data-active="<?php echo (int)$socialState['following']; ?>" aria-pressed="<?php echo $socialState['following']?'true':'false'; ?>" class="btn btn-primary w-100 mt-3"><span data-social-label data-i18n-ui><span data-i18n-ui><?php echo $socialState['following']?'Đang theo dõi':'Theo dõi'; ?></span></span></button><?php endif; ?>
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
     <ul class="footer-contact list-unstyled mb-0"><li><i class="bi bi-geo-alt-fill"></i><span data-i18n="footer.address">281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</span></li><li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com"><span data-i18n-ui>info@tripconnect.com</span></a> - <a href="mailto:info.asia@tripconnect.com"><span data-i18n-ui>info.asia@tripconnect.com</span></a> <span data-i18n="footer.english_support">(English Support)</span></span></li><li><i class="bi bi-globe2"></i><span data-i18n="footer.follow_us">Theo dõi chúng tôi trên:</span></li></ul>
     <div class="footer-social"><span aria-label="Instagram" data-i18n-attrs><i class="bi bi-instagram"></i></span><span aria-label="Facebook" data-i18n-attrs><i class="bi bi-facebook"></i></span><span aria-label="WhatsApp" data-i18n-attrs><i class="bi bi-whatsapp"></i></span><span aria-label="TikTok" data-i18n-attrs><i class="bi bi-tiktok"></i></span><span aria-label="Messenger" data-i18n-attrs><i class="bi bi-messenger"></i></span></div>
     <a class="btn-hotline" href="tel:1800646888"><i class="bi bi-telephone-fill"></i> 1800 646 888</a><div class="footer-note" data-i18n="footer.hotline_note">Tổng đài miễn phí 24/7</div>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {

  /* ---------- Budget chart ---------- */
  var budgetData = <?php echo json_encode($budgetData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  var ctx = document.getElementById("budgetChart");
  if (ctx && window.Chart && budgetData.length) {
    new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: budgetData.map(function (d) { return d.label; }),
        datasets: [{ data: budgetData.map(function (d) { return d.value; }), backgroundColor: budgetData.map(function (d) { return d.color; }), borderWidth: 0 }]
      },
      options: { plugins: { legend: { display: false } }, cutout: "68%" }
    });
  }
  if (ctx && (!window.Chart || !budgetData.length)) {
      ctx.hidden = true;
      if (!window.Chart && budgetData.length) document.getElementById('budgetChartStatus').textContent = 'Không tải được biểu đồ; số liệu theo danh mục vẫn hiển thị bên dưới.';
  }

  /* ---------- Map ---------- */
  var mapEl = document.getElementById("tripMap");
  function renderTripMap(stops) {
    if (!Array.isArray(stops) || !stops.length) {
      mapEl.innerHTML = '<div class="p-3 text-muted">Chưa có tọa độ hợp lệ để hiển thị bản đồ.</div>';
      return;
    }
    var map = L.map(mapEl, { scrollWheelZoom: false, maxZoom: 19 }).setView(stops[0].coords, 12);
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map).on('tileerror', function () {
      if (!mapEl.querySelector('[data-map-tile-error]')) {
        mapEl.insertAdjacentHTML('beforeend', '<div data-map-tile-error class="small text-warning p-2 bg-white">Không tải được lớp bản đồ OpenStreetMap. Vui lòng kiểm tra DNS/kết nối mạng.</div>');
      }
    });
    stops.forEach(function (s) {
      var popup = document.createElement('span');
      popup.textContent = s.name;
      L.marker(s.coords).addTo(map).bindPopup(popup);
    });
    if (stops.length > 1) map.fitBounds(stops.map(function (s) { return s.coords; }), { padding: [30, 30] });
    // Đo lại container sau khi card đã layout để tránh viewport bị lệch hoặc nền xám.
    window.setTimeout(function () { map.invalidateSize({ pan: false }); }, 0);
    window.addEventListener('resize', function () { map.invalidateSize({ pan: false }); });
  }
  if (mapEl && window.L) {
    renderTripMap(<?php echo json_encode($stops, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
  } else if (mapEl) {
    mapEl.innerHTML = '<div class="p-3 text-muted">Không tải được thư viện bản đồ. Vui lòng thử lại.</div>';
  }

  /* ---------- Clone / export ---------- */
  var exportBtn=document.getElementById('exportBtn');
  async function printTrip(){
    if(document.fonts)await document.fonts.ready;
    var originalTitle=document.title;
    document.title=<?= json_encode(($trip['title']??'TripConnect').' - TripConnect',JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    window.addEventListener('afterprint',function(){document.title=originalTitle;},{once:true});
    window.print();
  }
  if(exportBtn)exportBtn.addEventListener('click',printTrip);
  if(exportBtn&&new URLSearchParams(location.search).get('print')==='1')printTrip();

});
</script>
</body>
</html>
