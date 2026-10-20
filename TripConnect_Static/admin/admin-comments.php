<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
// Kiểm tra quyền Admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Xử lý thay đổi trạng thái bình luận
if (isset($_GET['action']) && isset($_GET['id'])) {
    $comment_id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'resolve') {
        $stmt = $conn->prepare("UPDATE comments SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$comment_id]);
    } elseif ($action === 'hide') {
        // Lấy lý do ẩn được gửi từ form modal lên (mặc định là 'other' nếu không chọn)
        $reason = $_POST['reason'] ?? 'other';
        
        $stmt = $conn->prepare("UPDATE comments SET status = 'hidden', reason = ? WHERE id = ?");
        $stmt->execute([$reason, $comment_id]);
    }
    
    header("Location: admin-comments.php");
    exit();
}

// Lấy danh sách bình luận từ cơ sở dữ liệu
try {
    $sql = "SELECT c.*, u.full_name as author_name, t.title as trip_title 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            LEFT JOIN trips t ON c.trip_id = t.id 
            ORDER BY c.created_at DESC";
    $stmt = $conn->query($sql);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Dữ liệu mẫu dự phòng nếu bảng comments chưa đầy đủ cột
    $comments = [
        ['id' => 1, 'author_name' => 'Nguyễn An', 'trip_title' => 'Đà Lạt 3 ngày 2 đêm', 'content' => 'Chuyến đi này tuyệt quá, mọi người cùng tham khảo nhé!', 'created_at' => '2026-09-25 10:00:00', 'reason' => 'spam', 'status' => 'pending'],
        ['id' => 2, 'author_name' => 'Trần Minh', 'trip_title' => 'Đà Nẵng - Hội An 4 ngày', 'content' => 'Lịch trình rất chi tiết và dễ tham khảo.', 'created_at' => '2026-09-24 14:00:00', 'reason' => 'other', 'status' => 'resolved'],
        ['id' => 3, 'author_name' => 'Lê Linh', 'trip_title' => 'Sapa mùa lúa chín', 'content' => 'Nội dung bình luận không phù hợp với cộng đồng.', 'created_at' => '2026-09-23 09:00:00', 'reason' => 'offensive', 'status' => 'pending']
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Quản lý bình luận</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">

<style>
body{margin:0;background:#f5f8f7;color:#173232;font-family:var(--font-body)}
.sidebar{position:fixed;width:220px;height:100vh;background:#073b3a;color:white;padding:20px 12px;z-index:100;}
.brand{font-size:17px;font-weight:600;margin-bottom:25px}
.menu{display:block;color:#d5e5e3;text-decoration:none;padding:10px 12px;border-radius:7px;margin:4px 0;font-size:14px}
.menu i{margin-right:9px}
.menu:hover,.menu.active{background:#0d7771;color:white}
.main{margin-left:220px}
.topbar{height:62px;background:white;border-bottom:1px solid #ddd;display:flex;justify-content:flex-end;align-items:center;padding:0 25px}
.user{font-size:16px;font-weight:500}
.content{padding:25px}
.title{margin-bottom:20px}
.title h2{font-size:24px;margin:0}
.title p{font-size:13px;color:#788; margin:5px 0}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}
.card{border:0;box-shadow:0 2px 8px #00000008}
.stat{padding:18px}
.stat small{color:#788}
.stat h3{margin:7px 0;font-size:25px}
.icon{float:right;background:#e3f3f1;color:#08766f;padding:10px;border-radius:8px}
.grid{display:grid;grid-template-columns:1.5fr 1fr;gap:15px;margin-top:15px}
.box{background:white;padding:18px;border-radius:9px}
.box h5{font-size:15px}
table{font-size:13px}
.badge-ok{background:#def3e8;color:#21784c}
.badge-warn{background:#fff1cf;color:#946900}
.badge-login{background:#cff4fc;color:#055160}
.badge-create{background:#d1e7dd;color:#0f5132}
.badge-update{background:#fff3cd;color:#664d03}
.badge-delete{background:#f8d7da;color:#842029}
.badge-hide{background:#e2e3e5;color:#41464b}
.badge-pending { background: #fff1cf; color: #946900 !important; }
.badge-resolved { background: #def3e8; color: #21784c !important; }
.badge-hidden { background: #ffe1e1; color: #a72f2f !important; }
@media(max-width:900px){
.sidebar{position:static;width:100%;height:auto}
.main{margin-left:0}
.stats{grid-template-columns:repeat(2,1fr)}
.grid{grid-template-columns:1fr}
}
@media(max-width:600px){.stats{grid-template-columns:1fr}}
</style>
</head>

<body>
<div class="admin-layout">

<aside class="sidebar">
    <div class="brand" style="font-size: 17px; font-weight: 600; margin-bottom: 25px;">
        <i class="bi bi-compass"></i> TripConnect Admin
    </div>

    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : ''; ?>" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-reports.php' ? 'active' : ''; ?>" href="admin-reports.php"><i class="bi bi-bar-chart me-2"></i>Báo cáo</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-users.php' ? 'active' : ''; ?>" href="admin-users.php"><i class="bi bi-people me-2"></i>Người dùng</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-trips.php' ? 'active' : ''; ?>" href="admin-trips.php"><i class="bi bi-map me-2"></i>Lịch trình</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-comments.php' ? 'active' : ''; ?>" href="admin-comments.php"><i class="bi bi-chat-left-text me-2"></i>Bình luận</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-locations.php' ? 'active' : ''; ?>" href="admin-locations.php"><i class="bi bi-geo-alt me-2"></i>Địa điểm</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-tags.php' ? 'active' : ''; ?>" href="admin-tags.php"><i class="bi bi-tags me-2"></i>Tag & sở thích</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-logs.php' ? 'active' : ''; ?>" href="admin-logs.php"><i class="bi bi-journal-text me-2"></i>Nhật ký</a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-settings.php' ? 'active' : ''; ?>" href="admin-settings.php"><i class="bi bi-gear me-2"></i>Cài đặt</a>

<a class="menu mt-4" href="../login.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></aside>

<main class="main">

<header class="topbar">
    <div class="user">
        <i class="bi bi-person-circle me-1"></i>
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
    </div>
</header>

<div class="content">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Bình luận</h4>
        <p class="text-muted mb-0">Quản lý và xử lý các bình luận trên hệ thống</p>
    </div>
</div>

<div class="card p-3 mb-4">
    <div class="row g-2">
        <div class="col-md-6">
            <input id="search" class="form-control" placeholder="Tìm bình luận, người dùng hoặc lịch trình...">
        </div>

        <div class="col-md-3">
            <select id="statusFilter" class="form-select">
                <option value="all">Tất cả trạng thái</option>
                <option value="pending">Chờ xử lý</option>
                <option value="resolved">Đã xử lý</option>
                <option value="hidden">Đã ẩn</option>
            </select>
        </div>

        <div class="col-md-3">
            <select id="reasonFilter" class="form-select">
                <option value="all">Tất cả lý do</option>
                <option value="spam">Spam</option>
                <option value="offensive">Nội dung không phù hợp</option>
                <option value="other">Khác</option>
            </select>
        </div>
    </div>
</div>

<div class="card p-3">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
<tr>
    <th>Người dùng</th>
    <th>Lịch trình</th>
    <th>Bình luận</th>
    <th>Ngày</th>
    <th>Lý do</th>
    <th>Trạng thái</th>
    <th>Thao tác</th>
</tr>
</thead>

<tbody id="commentTable">
<?php if (!empty($comments)): ?>
    <?php foreach ($comments as $cm): ?>
    <?php 
        $st = $cm['status'] ?? 'pending';
        $rs = $cm['reason'] ?? 'other';
    ?>
    <tr data-status="<?php echo htmlspecialchars($st); ?>" data-reason="<?php echo htmlspecialchars($rs); ?>">
        <td><b><?php echo htmlspecialchars($cm['author_name'] ?? 'Thành viên'); ?></b></td>
        <td><?php echo htmlspecialchars($cm['trip_title'] ?? 'Lịch trình TripConnect'); ?></td>
        <td class="comment"><?php echo htmlspecialchars($cm['content']); ?></td>
        <td><?php echo isset($cm['created_at']) ? date('d/m/Y', strtotime($cm['created_at'])) : 'N/A'; ?></td>
        <td>
            <?php 
                // Chỉ hiện lý do khi trạng thái là 'hidden' (Đã ẩn)
                if ($st === 'hidden') {
                    $reasons = ['spam' => 'Spam', 'offensive' => 'Nội dung không phù hợp', 'other' => 'Khác'];
                    echo '<span class="text-muted">' . ($reasons[$rs] ?? 'Khác') . '</span>';
                } else {
                    echo '<span class="text-muted">-</span>';
                }
            ?>
        </td>
        <td>
            <?php if ($st === 'resolved'): ?>
                <span class="badge badge-resolved">Đã xử lý</span>
            <?php elseif ($st === 'hidden'): ?>
                <span class="badge badge-hidden">Đã ẩn</span>
            <?php else: ?>
                <span class="badge badge-pending">Chờ xử lý</span>
            <?php endif; ?>
        </td>
        <td>
            <button class="btn btn-outline-primary action-btn view-btn" title="Xem chi tiết" 
                    data-user="<?php echo htmlspecialchars($cm['author_name'] ?? 'Thành viên'); ?>"
                    data-trip="<?php echo htmlspecialchars($cm['trip_title'] ?? 'Lịch trình'); ?>"
                    data-date="<?php echo isset($cm['created_at']) ? date('d/m/Y H:i', strtotime($cm['created_at'])) : 'N/A'; ?>"
                    data-content="<?php echo htmlspecialchars($cm['content']); ?>">
                <i class="bi bi-eye"></i>
            </button>

            <?php if ($st !== 'resolved'): ?>
            <a href="admin-comments.php?action=resolve&id=<?php echo $cm['id']; ?>" class="btn btn-outline-success action-btn" title="Đánh dấu đã xử lý">
                <i class="bi bi-check"></i>
            </a>
            <?php endif; ?>

            <?php if ($st !== 'hidden'): ?>
            <button type="button" class="btn btn-outline-danger action-btn hide-prompt-btn" title="Ẩn bình luận" data-id="<?php echo $cm['id']; ?>">
                <i class="bi bi-eye-slash"></i>
            </button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có bình luận nào trong hệ thống.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</main>
</div>

<!-- Modal xem bình luận -->
<div class="modal fade" id="commentModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
    <h5 class="modal-title">Chi tiết bình luận</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
    <p><b>Người dùng:</b> <span id="mUser"></span></p>
    <p><b>Lịch trình:</b> <span id="mTrip"></span></p>
    <p><b>Ngày:</b> <span id="mDate"></span></p>
    <p><b>Nội dung:</b></p>
    <div class="p-3 bg-light rounded" id="mComment"></div>
</div>

<div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
</div>
</div>
</div>
</div>

<!-- Modal Chọn lý do ẩn bình luận -->
<div class="modal fade" id="hideReasonModal" tabindex="-1">
<div class="modal-dialog">
<form method="POST" id="hideForm" class="modal-content">
<div class="modal-header">
    <h5 class="modal-title">Chọn lý do ẩn bình luận</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <input type="hidden" name="comment_id" id="modalCommentId">
    <p class="text-muted small">Vui lòng chọn lý do trước khi ẩn bình luận này khỏi hệ thống:</p>
    <div class="mb-3">
        <select name="reason" class="form-select" required>
            <option value="spam">Spam (Quảng cáo, rác)</option>
            <option value="offensive">Nội dung không phù hợp (Thô tục, xúc phạm)</option>
            <option value="other">Lý do khác</option>
        </select>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-danger">Xác nhận ẩn</button>
</div>
</form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const search = document.getElementById("search");
const statusFilter = document.getElementById("statusFilter");
const reasonFilter = document.getElementById("reasonFilter");
const rows = document.querySelectorAll("#commentTable tr");

function filterComments(){
    const q = search.value.toLowerCase();
    rows.forEach(r => {
        if (!r.dataset.status) return;
        const text = r.innerText.toLowerCase();
        const okSearch = text.includes(q);
        const okStatus = statusFilter.value === "all" || r.dataset.status === statusFilter.value;
        const okReason = reasonFilter.value === "all" || r.dataset.reason === reasonFilter.value;
        r.style.display = (okSearch && okStatus && okReason) ? "" : "none";
    });
}

search.oninput = filterComments;
statusFilter.onchange = filterComments;
reasonFilter.onchange = filterComments;

// Xử lý nút xem chi tiết modal
document.querySelectorAll(".view-btn").forEach(btn => {
    btn.onclick = () => {
        document.getElementById("mUser").textContent = btn.getAttribute("data-user");
        document.getElementById("mTrip").textContent = btn.getAttribute("data-trip");
        document.getElementById("mDate").textContent = btn.getAttribute("data-date");
        document.getElementById("mComment").textContent = btn.getAttribute("data-content");
        new bootstrap.Modal(document.getElementById("commentModal")).show();
    };
});

// Xử lý bật Modal chọn lý do khi bấm nút ẩn (Đã được đưa đúng vào thẻ script)
document.querySelectorAll(".hide-prompt-btn").forEach(btn => {
    btn.onclick = () => {
        const commentId = btn.getAttribute("data-id");
        document.getElementById("hideForm").action = "admin-comments.php?action=hide&id=" + commentId;
        new bootstrap.Modal(document.getElementById("hideReasonModal")).show();
    };
});
</script>

</body>
</html>
