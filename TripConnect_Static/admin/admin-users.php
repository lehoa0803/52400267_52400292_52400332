<?php
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
require_once '../config/database.php';
require_once '../config/admin_auth.php';
// Kiểm tra quyền Admin (nếu chưa đăng nhập hoặc không phải admin thì đá về trang login)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

$adminUsersCsrf = trip_csrf_token('admin_users');

// Xử lý Thêm người dùng mới
$success_msg = '';
$error_msg = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  admin_token('admin_users');$action=admin_text('action')?:admin_text('account_action');
  $conn->beginTransaction();
  if(in_array($action,['add_user','edit_user'],true)){
   $name=admin_text('full_name',100);$email=admin_text('email',100);$role=admin_text('role',20)?:'member';
   if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['member','user','admin'],true))throw new InvalidArgumentException('Tên, email hoặc vai trò không hợp lệ.');
   if($action==='add_user'){
    $username=admin_text('username',50);$password=admin_text('password',255);
    if(!preg_match('/^[A-Za-z0-9_]{3,50}$/D',$username)||strlen($password)<8)throw new InvalidArgumentException('Username cần 3–50 chữ, số hoặc gạch dưới; mật khẩu ít nhất 8 ký tự.');
    $conn->prepare("INSERT INTO users(username,full_name,email,password,role,status) VALUES(?,?,?,?,?,'active')")->execute([$username,$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
   }else{
    $id=admin_target();$q=$conn->prepare('SELECT role FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$oldRole=$q->fetchColumn();if($oldRole===false)throw new InvalidArgumentException('Không tìm thấy tài khoản.');
    if($id===(int)$_SESSION['user_id']&&$role!==$oldRole)throw new InvalidArgumentException('Không thể tự thay đổi quyền Admin hiện tại.');
    $conn->prepare('UPDATE users SET full_name=?,email=?,role=? WHERE id=?')->execute([$name,$email,$role,$id]);
   }
  }elseif(in_array($action,['ban','unban'],true)){
   $id=admin_target();$q=$conn->prepare('SELECT role FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$role=$q->fetchColumn();
   if($role===false)throw new InvalidArgumentException('Không tìm thấy tài khoản.');
   if($id===(int)$_SESSION['user_id']||$role==='admin')throw new InvalidArgumentException('Không thể khóa tài khoản Admin.');
   $conn->prepare('UPDATE users SET status=? WHERE id=?')->execute([$action==='ban'?'banned':'active',$id]);
  }else throw new InvalidArgumentException('Thao tác không hợp lệ.');
  $conn->commit();$success_msg='Đã lưu thay đổi.';
 }catch(Throwable $e){if($conn->inTransaction())$conn->rollBack();$error_msg=$e instanceof InvalidArgumentException?$e->getMessage():'Không thể lưu tài khoản; kiểm tra username/email trùng.';}
}

// Lấy danh sách toàn bộ người dùng từ Database
try {
    $stmtUsers = $conn->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title data-i18n-ui>Quản lý người dùng - TripConnect Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css">

<style>
body{margin:0;background:#f5f8f7;color:#173232;font-family:var(--font-body)}
.sidebar{position:fixed;width:220px;height:100vh;background:#073b3a;color:white;padding:20px 12px; z-index: 100;}
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
.title p{font-size:13px;color:#788;margin:5px 0}

.toolbar{background:white;padding:15px;border-radius:9px;margin-bottom:15px}
.table-box{background:white;border-radius:9px;padding:18px}
table{font-size:13px}
thead th{background:#f7faf9!important;color:#667;padding:12px!important}
tbody td{padding:12px!important;vertical-align:middle}

.avatar{width:35px;height:35px;border-radius:50%;background:#dff1ee;color:#08766f;display:inline-flex;align-items:center;justify-content:center;font-weight:600;margin-right:8px}

.badge-active{background:#def3e8;color:#21784c}
.badge-banned{background:#ffe1e1;color:#a72f2f}

.btn-admin{background:#0d8f86;color:white;border:0}
.btn-admin:hover{background:#08766f;color:white}

@media(max-width:900px){
.sidebar{position:static;width:100%;height:auto}
.main{margin-left:0}
}
input,select,textarea{font:inherit;max-width:100%}details form{min-width:200px}</style>
</head>

<body>

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
<i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Quản trị viên'); ?>
</div>
</header>

<section class="content">

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

<div class="title d-flex justify-content-between align-items-center">
<div>
<h2><span data-i18n-ui>Quản lý người dùng</span></h2>
<p><span data-i18n-ui>Quản lý tài khoản và trạng thái người dùng trực tiếp từ cơ sở dữ liệu</span></p>
</div>
<button class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#addUser">
<i class="bi bi-plus-lg me-1"></i><span data-i18n-ui> Thêm người dùng
</span></button>
</div>

<!-- SEARCH + FILTER -->
<div class="toolbar">
<div class="row g-2">
<div class="col-md-6">
<div class="input-group">
<span class="input-group-text"><i class="bi bi-search"></i></span>
<input type="text" id="search" class="form-control" placeholder="Tìm theo tên hoặc email..." data-i18n-attrs>
</div>
</div>
<div class="col-md-3">
<select id="statusFilter" class="form-select">
<option value="" data-i18n-ui>Tất cả trạng thái</option>
<option value="active" data-i18n-ui>Đang hoạt động</option>
<option value="banned" data-i18n-ui>Đã khóa</option>
</select>
</div>
<div class="col-md-3">
<select id="roleFilter" class="form-select">
<option value="" data-i18n-ui>Tất cả vai trò</option>
<option value="member" data-i18n-ui>Member</option>
<option value="admin" data-i18n-ui>Admin</option>
</select>
</div>
</div>
</div>

<!-- USER TABLE -->
<div class="table-box">
<div class="d-flex justify-content-between mb-3">
<h5 class="mb-0"><span data-i18n-ui>Danh sách người dùng</span></h5>
<span class="text-muted small"><span data-i18n-ui>Tổng: </span><b id="total"><?php echo count($users); ?></b><span data-i18n-ui> người</span></span>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th><span data-i18n-ui>Người dùng</span></th>
<th><span data-i18n-ui>Email</span></th>
<th><span data-i18n-ui>Vai trò</span></th>
<th><span data-i18n-ui>Ngày đăng ký</span></th>
<th><span data-i18n-ui>Trạng thái</span></th>
<th class="text-center"><span data-i18n-ui>Thao tác</span></th>
</tr>
</thead>
<tbody id="userTable">
<?php if (!empty($users)): ?>
    <?php foreach ($users as $u): ?>
        <tr data-status="<?php echo htmlspecialchars($u['status'] ?? 'active'); ?>" data-role="<?php echo htmlspecialchars($u['role'] ?? 'member'); ?>">
        <td>
            <span class="avatar"><?php echo strtoupper(mb_substr($u['full_name'] ?? 'U', 0, 1)); ?></span>
            <?php echo htmlspecialchars($u['full_name']); ?>
        </td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><?php echo ucfirst(htmlspecialchars($u['role'])); ?></td>
        <td><?php echo isset($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : 'N/A'; ?></td>
        <td>
            <?php if (($u['status'] ?? 'active') === 'banned'): ?>
                <span class="badge badge-banned" data-i18n-ui><span data-i18n-ui>Đã khóa</span></span>
            <?php else: ?>
                <span class="badge badge-active" data-i18n-ui><span data-i18n-ui>Đang hoạt động</span></span>
            <?php endif; ?>
        </td>
        <td class="text-center">
<details><summary><span data-i18n-ui>Chỉnh sửa</span></summary><form method="post" class="d-grid gap-2 my-2"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminUsersCsrf) ?>"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input name="full_name" aria-label="Full name" maxlength="100" required value="<?= htmlspecialchars($u['full_name'],ENT_QUOTES) ?>" data-i18n-attrs><input name="email" aria-label="Email" type="email" maxlength="100" required value="<?= htmlspecialchars($u['email'],ENT_QUOTES) ?>" data-i18n-attrs><select name="role" aria-label="Role" data-i18n-attrs><?php foreach(['member','user','admin'] as $role): ?><option data-i18n-ui value="<?= $role ?>" <?= $u['role']===$role?'selected':'' ?>><?= $role ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-primary"><span data-i18n-ui>Lưu</span></button></form></details>
            <?php if (($u['role'] ?? '') === 'admin'): ?>
                <button class="btn btn-sm btn-outline-secondary" disabled title="Không thể khóa tài khoản Admin chính" data-i18n-attrs>
                    <i class="bi bi-shield-check"></i>
                </button>
            <?php else: ?>
                <?php if (($u['status'] ?? 'active') === 'banned'): ?>
                     <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminUsersCsrf); ?>"><input type="hidden" name="account_action" value="unban"><input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>"><button class="btn btn-sm btn-outline-success" title="Mở khóa tài khoản" data-i18n-attrs><i class="bi bi-unlock"></i></button></form>
                <?php else: ?>
                     <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn khóa tài khoản này?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminUsersCsrf); ?>"><input type="hidden" name="account_action" value="ban"><input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>"><button class="btn btn-sm btn-outline-danger" title="Khóa tài khoản" data-i18n-attrs><i class="bi bi-lock"></i></button></form>
                <?php endif; ?>
            <?php endif; ?>
        </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="6" class="text-center text-muted py-4"><span data-i18n-ui>Chưa có người dùng nào trong cơ sở dữ liệu.</span></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</section>
</main>

<!-- ADD USER MODAL -->
<div class="modal fade" id="addUser" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><span data-i18n-ui>Thêm người dùng mới</span></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form action="admin-users.php" method="POST">
<input type="hidden" name="action" value="add_user"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminUsersCsrf) ?>"><label class="form-label"><span data-i18n-ui>Username</span></label><input name="username" class="form-control" required pattern="[A-Za-z0-9_]{3,50}" maxlength="50">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminUsersCsrf); ?>">
<div class="modal-body">
<div class="mb-3">
<label class="form-label"><span data-i18n-ui>Họ và tên</span></label>
<input type="text" name="full_name" class="form-control" required>
</div>
<div class="modal-body px-0 py-0">
<div class="mb-3">
<label class="form-label"><span data-i18n-ui>Email</span></label>
<input type="email" name="email" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label"><span data-i18n-ui>Mật khẩu</span></label>
<input type="password" name="password" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label"><span data-i18n-ui>Vai trò</span></label>
<select name="role" class="form-select">
<option value="member" data-i18n-ui>Member</option>
<option value="admin" data-i18n-ui>Admin</option>
</select>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n-ui>Hủy</span></button>
<button type="submit" class="btn btn-admin"><span data-i18n-ui>Thêm người dùng</span></button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// TÌM KIẾM + LỌC TRỰC TIẾP TRÊN GIAO DIỆN
function filterUsers(){
    let search = document.getElementById("search").value.toLowerCase();
    let status = document.getElementById("statusFilter").value;
    let role = document.getElementById("roleFilter").value;
    let rows = document.querySelectorAll("#userTable tr");
    let count = 0;

    rows.forEach(row => {
        if (!row.dataset.status) return; // Bỏ qua dòng thông báo trống nếu có
        let text = row.innerText.toLowerCase();
        let okSearch = text.includes(search);
        let okStatus = !status || row.dataset.status === status;
        let okRole = !role || row.dataset.role === role;

        row.style.display = okSearch && okStatus && okRole ? "" : "none";
        if(okSearch && okStatus && okRole) count++;
    });

    document.getElementById("total").innerText = count;
}

document.getElementById("search").oninput = filterUsers;
document.getElementById("statusFilter").onchange = filterUsers;
document.getElementById("roleFilter").onchange = filterUsers;
</script>

<script src="../assets/i18n.js?v=10"></script>
</body>
</html>
