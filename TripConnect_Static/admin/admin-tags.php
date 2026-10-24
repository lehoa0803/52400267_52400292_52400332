<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
require_once '../config/admin_auth.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit();
}
$success_msg = '';
$error_msg = '';
$adminTagsCsrf = trip_csrf_token('admin_tags');

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  admin_token('admin_tags');$action=admin_text('action')?:admin_text('tag_action');
  $conn->beginTransaction();
  if(in_array($action,['add','edit'],true)){
   $name=admin_text('tag_name',50);$type=admin_text('tag_type',50)?:'theme';$slug=admin_text('slug',50);
   if($name==='')throw new InvalidArgumentException('Vui lòng nhập tên.');
   if($slug===''){$base=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);$slug=substr(trim(preg_replace('/[^a-z0-9]+/','-',strtolower($base?:'tag')),'-'),0,40).'-'.bin2hex(random_bytes(3));}
   if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new InvalidArgumentException('Slug chỉ dùng a-z, 0-9 và dấu gạch nối.');
   if($action==='add')$conn->prepare("INSERT INTO tags(name,slug,type,status) VALUES(?,?,?,'active')")->execute([$name,$slug,$type]);
   else{$id=admin_target();$q=$conn->prepare('SELECT id FROM tags WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Không tìm thấy tag.');$conn->prepare('UPDATE tags SET name=?,slug=?,type=? WHERE id=?')->execute([$name,$slug,$type,$id]);}
  }elseif(in_array($action,['hide','show','delete'],true)){
   $id=admin_target();$q=$conn->prepare('SELECT id FROM tags WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Không tìm thấy tag.');
   if($action==='delete'){$q=$conn->prepare('SELECT COUNT(*) FROM trip_tags WHERE tag_id=?');$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Tag đang được sử dụng; hãy ẩn thay vì xóa.');$conn->prepare('DELETE FROM tags WHERE id=?')->execute([$id]);}
   else $conn->prepare('UPDATE tags SET status=? WHERE id=?')->execute([$action==='hide'?'hidden':'active',$id]);
  }else throw new InvalidArgumentException('Thao tác không hợp lệ.');
  $conn->commit();$success_msg='Đã lưu thay đổi.';
 }catch(Throwable $e){if($conn->inTransaction())$conn->rollBack();$error_msg=$e instanceof InvalidArgumentException?$e->getMessage():'Không thể lưu; tên hoặc slug có thể bị trùng.';}
}

// 3. Lấy danh sách tag và đếm số lịch trình thực tế từ cơ sở dữ liệu
try {
    $sql = "SELECT t.*,
                   COUNT(DISTINCT tt.trip_id) as usage_count
            FROM tags t
            LEFT JOIN trip_tags tt ON t.id = tt.tag_id
            GROUP BY t.id
            ORDER BY t.id DESC";
    $stmt = $conn->query($sql);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[TripConnect admin-tags] list failed: ' . $e->getMessage());
    $tags = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title data-i18n-ui>Quản lý Tag & sở thích - TripConnect Admin</title>

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
.user{font-size:16px; font-weight:500}
.content{padding:25px}
.title{margin-bottom:20px}
.title h2{font-size:24px;margin:0}
.title p{font-size:13px;color:#788;margin:5px 0}

.toolbar,.table-box{background:white;padding:18px;border-radius:9px}
.toolbar{margin-bottom:15px}

table{font-size:13px}
thead th{background:#f7faf9!important;color:#667;padding:12px!important}
tbody td{padding:12px!important;vertical-align:middle}

.badge-published, .badge-active {
    background: #def3e8 !important;
    color: #21784c !important;
}.badge-draft{background:#fff1cf!important;color:#946900!important}
.badge-hidden{background:#ffe1e1!important;color:#a72f2f!important}
.badge-pending{background:#fff1cf!important;color:#946900!important}
.badge-resolved{background:#def3e8!important;color:#21784c!important}

.btn-admin{background:#0d8f86;color:white;border:0}
.btn-admin:hover{background:#08766f;color:white}

@media(max-width:900px){
.sidebar{position:static;width:100%;height:auto}
.main{margin-left:0}
}
input,select,textarea{font:inherit;max-width:100%}details form{min-width:200px}</style>
</head>

<body>

<div class="admin-layout">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand" style="font-size: 17px; font-weight: 600; margin-bottom: 25px;">
        <i class="bi bi-compass"></i> TripConnect Admin
    </div>

    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : ''; ?>" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i><span data-i18n-ui>Dashboard</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-reports.php' ? 'active' : ''; ?>" href="admin-reports.php"><i class="bi bi-bar-chart me-2"></i><span data-i18n-ui>Báo cáo</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-users.php' ? 'active' : ''; ?>" href="admin-users.php"><i class="bi bi-people me-2"></i><span data-i18n-ui>Người dùng</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-trips.php' ? 'active' : ''; ?>" href="admin-trips.php"><i class="bi bi-map me-2"></i><span data-i18n-ui>Lịch trình</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-comments.php' ? 'active' : ''; ?>" href="admin-comments.php"><i class="bi bi-chat-left-text me-2"></i><span data-i18n-ui>Bình luận</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-tags.php' ? 'active' : ''; ?>" href="admin-tags.php"><i class="bi bi-tags me-2"></i><span data-i18n-ui>Tag </span>&<span data-i18n-ui> sở thích</span></a>
<a class="menu" href="location-catalog.php"><span data-i18n-ui>Địa điểm</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-logs.php' ? 'active' : ''; ?>" href="admin-logs.php"><i class="bi bi-journal-text me-2"></i><span data-i18n-ui>Nhật ký</span></a>
    <a class="menu <?php echo basename($_SERVER['PHP_SELF']) === 'admin-settings.php' ? 'active' : ''; ?>" href="admin-settings.php"><i class="bi bi-gear me-2"></i><span data-i18n-ui>Cài đặt</span></a>

    <form method="post" action="../logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="menu mt-4 border-0 bg-transparent w-100 text-start"><i class="bi bi-box-arrow-right me-2"></i><span data-i18n-ui>Đăng xuất</span></button></form>
</aside>

<!-- MAIN -->
<main class="main">

<header class="topbar">
    <div class="user">
        <i class="bi bi-person-circle me-1"></i>
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
    </div>
</header>

<div class="content">

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><span data-i18n-ui>Tag </span>&<span data-i18n-ui> sở thích</span></h4>
        <p class="text-muted mb-0"><span data-i18n-ui>Quản lý các tag và sở thích phục vụ tìm kiếm, phân loại và gợi ý</span></p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tagModal">
        <i class="bi bi-plus-lg me-1"></i><span data-i18n-ui>Thêm Tag
    </span></button>
</div>

<!-- FILTER -->
<div class="card p-3 mb-4">
<div class="row g-2">
    <div class="col-md-6">
        <input id="search" class="form-control" placeholder="Tìm theo tên tag..." data-i18n-attrs>
    </div>
    <div class="col-md-3">
        <select id="typeFilter" class="form-select">
            <option value="all" data-i18n-ui>Tất cả loại</option>
            <option value="destination" data-i18n-ui>Điểm đến</option>
            <option value="activity" data-i18n-ui>Hoạt động</option>
            <option value="style" data-i18n-ui>Phong cách</option>
            <option value="food" data-i18n-ui>Ẩm thực</option>
        </select>
    </div>
    <div class="col-md-3">
        <select id="statusFilter" class="form-select">
            <option value="all" data-i18n-ui>Tất cả trạng thái</option>
            <option value="active" data-i18n-ui>Đang hoạt động</option>
            <option value="hidden" data-i18n-ui>Đã ẩn</option>
        </select>
    </div>
</div>
</div>

<!-- TABLE -->
<div class="card p-3">
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead>
<tr>
    <th><span data-i18n-ui>Tên Tag</span></th>
    <th><span data-i18n-ui>Loại</span></th>
    <th><span data-i18n-ui>Số lịch trình</span></th>
    <th><span data-i18n-ui>Số người quan tâm</span></th>
    <th><span data-i18n-ui>Trạng thái</span></th>
    <th><span data-i18n-ui>Thao tác</span></th>
</tr>
</thead>
<tbody id="tagTable">
<?php if (!empty($tags)): ?>
    <?php foreach ($tags as $tag): ?>
    <tr data-type="<?php echo htmlspecialchars($tag['type'] ?? 'destination'); ?>" data-status="<?php echo htmlspecialchars($tag['status'] ?? 'active'); ?>">
        <td><b><?php echo htmlspecialchars($tag['name']); ?></b></td>
        <td>
            <?php
                $types = ['destination' => 'Điểm đến', 'activity' => 'Hoạt động', 'style' => 'Phong cách', 'food' => 'Ẩm thực'];
                echo $types[$tag['type'] ?? ''] ?? 'Khác';
            ?>
        </td>
        <td><?php echo intval($tag['usage_count'] ?? 0); ?></td>
        <td><?php echo intval($tag['followers_count'] ?? 0); ?></td>
        <td>
            <?php if (($tag['status'] ?? 'active') === 'hidden'): ?>
                <span class="badge badge-hidden" data-i18n-ui><span data-i18n-ui>Đã ẩn</span></span>
            <?php else: ?>
                <span class="badge badge-active" data-i18n-ui><span data-i18n-ui>Đang hoạt động</span></span>
            <?php endif; ?>
        </td>
        <td>
<details><summary><span data-i18n-ui>Chỉnh sửa</span></summary><form method="post" class="d-grid gap-2 my-2"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminTagsCsrf) ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$tag['id'] ?>"><input name="tag_name" aria-label="Tag name" maxlength="50" required value="<?= htmlspecialchars($tag['name'],ENT_QUOTES) ?>" data-i18n-attrs><input name="slug" aria-label="Slug" maxlength="50" required value="<?= htmlspecialchars($tag['slug'],ENT_QUOTES) ?>" data-i18n-attrs><input name="tag_type" aria-label="Type" maxlength="50" value="<?= htmlspecialchars($tag['type'],ENT_QUOTES) ?>" data-i18n-attrs><button class="btn btn-sm btn-primary"><span data-i18n-ui>Lưu</span></button></form></details>
<form method="post" class="d-inline" onsubmit="return confirm('Xóa tag chưa được sử dụng?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminTagsCsrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$tag['id'] ?>"><button class="btn btn-sm btn-outline-danger"><span data-i18n-ui>Xóa</span></button></form>
            <?php if (($tag['status'] ?? 'active') === 'hidden'): ?>
                <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTagsCsrf); ?>"><input type="hidden" name="tag_action" value="show"><input type="hidden" name="id" value="<?php echo (int) $tag['id']; ?>"><button class="btn btn-outline-success action-btn" title="Hiển thị" data-i18n-attrs><i class="bi bi-eye"></i></button></form>
            <?php else: ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn ẩn tag này?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTagsCsrf); ?>"><input type="hidden" name="tag_action" value="hide"><input type="hidden" name="id" value="<?php echo (int) $tag['id']; ?>"><button class="btn btn-outline-danger action-btn" title="Ẩn" data-i18n-attrs><i class="bi bi-eye-slash"></i></button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4"><span data-i18n-ui>Chưa có tag nào trong hệ thống.</span></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</main>
</div>

<!-- MODAL THÊM TAG -->
<div class="modal fade" id="tagModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form action="admin-tags.php" method="POST">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminTagsCsrf); ?>">
<input type="hidden" name="action" value="add">
<div class="modal-header">
    <h5 class="modal-title"><span data-i18n-ui>Thêm Tag mới</span></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label"><span data-i18n-ui>Tên Tag</span></label>
        <input type="text" name="tag_name" class="form-control" placeholder="Ví dụ: Biển, Camping..." required data-i18n-attrs>
    </div>
    <div class="mb-3">
        <label class="form-label"><span data-i18n-ui>Loại</span></label>
        <select name="tag_type" class="form-select">
            <option value="destination" data-i18n-ui>Điểm đến</option>
            <option value="activity" data-i18n-ui>Hoạt động</option>
            <option value="style" data-i18n-ui>Phong cách</option>
            <option value="food" data-i18n-ui>Ẩm thực</option>
        </select>
    </div>
    <div class="mb-3"><label class="form-label"><span data-i18n-ui>Slug (a-z, 0-9, -)</span></label><input name="slug" maxlength="50" class="form-control" placeholder="Để trống để tự tạo" data-i18n-attrs></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n-ui>Hủy</span></button>
    <button type="submit" class="btn btn-success"><span data-i18n-ui>Lưu Tag</span></button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// TÌM KIẾM + LỌC TRỰC TIẾP
const search = document.getElementById("search");
const typeFilter = document.getElementById("typeFilter");
const statusFilter = document.getElementById("statusFilter");
const rows = document.querySelectorAll("#tagTable tr");

function filterTags(){
    const q = search.value.toLowerCase();
    rows.forEach(row => {
        if (!row.dataset.status) return;
        const text = row.innerText.toLowerCase();
        const okSearch = text.includes(q);
        const okType = typeFilter.value === "all" || row.dataset.type === typeFilter.value;
        const okStatus = statusFilter.value === "all" || row.dataset.status === statusFilter.value;

        row.style.display = (okSearch && okType && okStatus) ? "" : "none";
    });
}

search.oninput = filterTags;
typeFilter.onchange = filterTags;
statusFilter.onchange = filterTags;
</script>

<script src="../assets/i18n.js?v=10"></script>
</body>
</html>
