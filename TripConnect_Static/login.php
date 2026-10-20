<?php
// Khởi động session để lưu trạng thái đăng nhập
session_start();
// Nhúng file kết nối cơ sở dữ liệu
require_once 'config/database.php';

$login_error = '';
$register_success = '';
$register_error = '';

// Xử lý ĐĂNG NHẬP (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['loginUsername'] ?? '');
    $password = trim($_POST['loginPassword'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            // Truy vấn tìm user theo username hoặc email
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Kiểm tra mật khẩu (hỗ trợ cả mật khẩu mã hóa password_hash hoặc mật khẩu thường nếu dùng dữ liệu cũ)
            if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                header("Location: index.php");
                exit();
            } else {
                $login_error = "Email/username hoặc mật khẩu không đúng!";
            }
        } catch (PDOException $e) {
            $login_error = "Lỗi hệ thống cơ sở dữ liệu!";
        }
    } else {
        $login_error = "Vui lòng nhập đầy đủ thông tin đăng nhập!";
    }
}

// Xử lý ĐĂNG KÝ (POST từ modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name     = trim($_POST['registerName'] ?? '');
    $email    = trim($_POST['registerEmail'] ?? '');
    $password = $_POST['registerPassword'] ?? '';
    $confirm  = $_POST['registerConfirmPassword'] ?? '';

    if (!empty($name) && !empty($email) && !empty($password)) {
        if ($password === $confirm) {
            try {
                // Kiểm tra email đã tồn tại chưa
                $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->rowCount() > 0) {
                    $register_error = "Email này đã được đăng ký!";
                } else {
                    // Tạo username ngẫu nhiên từ email trước dấu @
                    $username = explode('@', $email)[0] . rand(10, 99);
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $stmtReg = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'member', 'active')");
                    $stmtReg->execute([$username, $email, $hashedPassword, $name]);

                    $register_success = "Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay.";
                }
            } catch (PDOException $e) {
                $register_error = "Lỗi đăng ký: " . $e->getMessage();
            }
        } else {
            $register_error = "Mật khẩu xác nhận không trùng khớp!";
        }
    } else {
        $register_error = "Vui lòng điền đầy đủ thông tin đăng ký!";
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Đăng nhập TripConnect để quản lý lịch trình và kết nối với cộng đồng du lịch.">
<title>Đăng nhập | TripConnect</title>
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

<main class="container py-5">
 <div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">

   <div class="text-center mb-4">
    <div class="icon-circle mx-auto mb-3"><i class="bi bi-compass"></i></div>
    <h2 class="fw-bold">Chào mừng trở lại</h2>
    <p class="text-muted">Đăng nhập để quản lý lịch trình và kết nối cộng đồng TripConnect.</p>
   </div>

   <div class="card p-4 shadow-sm">
    <!-- Form đăng nhập kết nối database -->
    <form id="loginForm" method="POST" action="login.php" novalidate>
     <input type="hidden" name="action" value="login">
     <div class="mb-3">
      <label class="form-label fw-semibold" for="loginUsername">Email hoặc username</label>
      <div class="input-group">
       <span class="input-group-text"><i class="bi bi-person"></i></span>
       <input type="text" id="loginUsername" name="loginUsername" class="form-control" required placeholder="user@gmail.com">
      </div>
     </div>

     <div class="mb-3">
      <label class="form-label fw-semibold" for="loginPassword">Mật khẩu</label>
      <div class="input-group">
       <span class="input-group-text"><i class="bi bi-lock"></i></span>
       <input type="password" id="loginPassword" name="loginPassword" class="form-control" required placeholder="">
       <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Hiện/ẩn mật khẩu"><i class="bi bi-eye"></i></button>
      </div>
     </div>

     <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="form-check">
       <input class="form-check-input" type="checkbox" id="rememberMe">
       <label class="form-check-label small" for="rememberMe">Ghi nhớ đăng nhập</label>
      </div>
      <a href="#" class="small text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Quên mật khẩu?</a>
     </div>

     <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập</button>

     <!-- Hiển thị lỗi đăng nhập từ PHP -->
     <?php if (!empty($login_error)): ?>
      <div class="alert alert-danger mt-3 mb-0" role="alert">
       <i class="bi bi-exclamation-circle me-1"></i> <?php echo $login_error; ?>
      </div>
     <?php endif; ?>
    </form>

    <hr class="my-4">

    <p class="text-center mb-2">Chưa có tài khoản?
     <a href="#" class="fw-semibold text-decoration-none" data-bs-toggle="modal" data-bs-target="#registerModal">Đăng ký ngay</a>
    </p>

    <div class="text-center mt-3">
     <span class="small text-muted">Bạn là quản trị viên?</span>
     <a href="admin/admin-login.php">Đăng nhập Admin</a>
    </div>
   </div>

  </div>
 </div>
</main>

<!-- REGISTER MODAL -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content border-0 shadow-lg rounded-4">
   <div class="modal-header">
    <div><h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Tạo tài khoản</h5><small class="text-muted">Tham gia cộng đồng TripConnect</small></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
   </div>
   <div class="modal-body p-4">
    <form id="registerForm" method="POST" action="login.php" novalidate>
     <input type="hidden" name="action" value="register">
     <div class="mb-3"><label class="form-label">Họ và tên</label><input type="text" id="registerName" name="registerName" class="form-control" placeholder="Nguyễn Văn A" required></div>
     <div class="mb-3"><label class="form-label">Email</label><input type="email" id="registerEmail" name="registerEmail" class="form-control" placeholder="user@gmail.com" required></div>
     <div class="mb-3"><label class="form-label">Mật khẩu</label><input type="password" id="registerPassword" name="registerPassword" class="form-control" placeholder="" minlength="6" required><div class="form-text">Mật khẩu tối thiểu 6 ký tự.</div></div>
     <div class="mb-3"><label class="form-label">Xác nhận mật khẩu</label><input type="password" id="registerConfirmPassword" name="registerConfirmPassword" class="form-control" placeholder="Nhập lại mật khẩu" required></div>
     <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-person-plus me-1"></i> Đăng ký</button>
     
     <?php if (!empty($register_success)): ?>
      <div class="alert alert-success mt-3 mb-0" role="alert"><i class="bi bi-check-circle me-1"></i> <?php echo $register_success; ?></div>
     <?php endif; ?>
     <?php if (!empty($register_error)): ?>
      <div class="alert alert-danger mt-3 mb-0" role="alert"><i class="bi bi-exclamation-circle me-1"></i> <?php echo $register_error; ?></div>
     <?php endif; ?>
    </form>
   </div>
  </div>
 </div>
</div>

<!-- FORGOT PASSWORD MODAL -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content border-0 shadow-lg rounded-4">
   <div class="modal-header">
    <div><h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i>Quên mật khẩu</h5><small class="text-muted">Khôi phục tài khoản TripConnect</small></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
   </div>
   <div class="modal-body p-4">
    <p class="text-muted small">Nhập email đã đăng ký. Hệ thống sẽ gửi hướng dẫn đặt lại mật khẩu.</p>
    <form id="forgotPasswordForm" novalidate>
     <div class="mb-3"><label class="form-label">Email</label><input type="email" id="forgotEmail" class="form-control" placeholder="user@gmail.com" required></div>
     <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-send me-1"></i> Gửi yêu cầu</button>
    </form>
   </div>
  </div>
 </div>
</div>

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
<script>
document.addEventListener("DOMContentLoaded", function () {
  // Xử lý hiện/ẩn mật khẩu
  var togglePassword = document.getElementById("togglePassword");
  var passwordInput = document.getElementById("loginPassword");

  if (togglePassword && passwordInput) {
    togglePassword.addEventListener("click", function () {
      var isHidden = passwordInput.type === "password";
      passwordInput.type = isHidden ? "text" : "password";
      this.innerHTML = isHidden ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  }
});
</script>
</body>
</html>