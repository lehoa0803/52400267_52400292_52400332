<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Khởi động session và kết nối cơ sở dữ liệu
session_start();
// Lưu ý: vì file này nằm trong thư mục admin/, đường dẫn nhúng file config sẽ lùi lại 1 cấp (../config/database.php)
require_once '../config/database.php';

$login_error = '';

// Xử lý khi Admin bấm nút đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['adminUsername'] ?? '');
    $password = trim($_POST['adminPassword'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            // Truy vấn kiểm tra user có tồn tại, có quyền admin hay không (role = 'admin')
            $stmt = $conn->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = 'admin'");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Kiểm tra mật khẩu (hỗ trợ cả mật khẩu hash chuẩn hoặc các mật khẩu admin mẫu như '080306', 'huzi$1702'...)
            if ($admin && (password_verify($password, $admin['password']) || $password === $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['full_name'] = $admin['full_name'];
                $_SESSION['role'] = $admin['role'];

                // Chuyển hướng sang trang Dashboard của Admin
                header("Location: admin-dashboard.php");
                exit();
            } else {
                $login_error = "Tên đăng nhập hoặc mật khẩu quản trị không đúng!";
            }
        } catch (PDOException $e) {
            $login_error = "Lỗi kết nối cơ sở dữ liệu hệ thống!";
        }
    } else {
        $login_error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!";
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Đăng nhập quản trị hệ thống TripConnect.">
<title>Admin Login | TripConnect</title>

<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link href="../assets/style.css" rel="stylesheet">

<style>
body{
  background:
    radial-gradient(circle at top left, rgba(15,110,102,.14), transparent 40%),
    var(--paper);
}

.admin-login-wrapper{
  min-height:75vh;
  display:flex;
  align-items:center;
  justify-content:center;
}

.admin-login-card{
  border:0;
  border-radius:var(--radius-lg);
  overflow:hidden;
  box-shadow:var(--shadow-pop);
}

.admin-header{
  padding:35px 30px;
  text-align:center;
  color:#fff;
  background:linear-gradient(135deg,var(--teal-700),var(--teal-900));
}

.admin-icon{
  width:70px;
  height:70px;
  margin:0 auto 18px;
  display:flex;
  align-items:center;
  justify-content:center;
  border-radius:20px;
  background:rgba(255,255,255,.15);
  border:1px solid rgba(255,255,255,.25);
  font-size:30px;
}

.admin-badge{
  display:inline-flex;
  align-items:center;
  padding:6px 13px;
  border-radius:50px;
  background:rgba(255,255,255,.15);
  font-size:13px;
  margin-top:10px;
}

.admin-form{
  padding:32px;
  background:#fff;
}

.admin-login-btn{
  padding:12px;
  border:0;
  border-radius:var(--radius-sm);
  font-weight:600;
  background:linear-gradient(135deg,var(--teal-700),var(--teal-900));
  color:#fff;
  transition:.2s;
}

.admin-login-btn:hover{
  transform:translateY(-1px);
  box-shadow:0 8px 20px rgba(15,110,102,.25);
  color:#fff;
}

.admin-note{
  background:var(--teal-100);
  border:1px solid #bfe4dd;
  border-radius:12px;
  padding:12px 14px;
  font-size:13px;
  color:var(--ink-soft);
}

.admin-footer{
  background:var(--ink);
  color:#94a3b8;
}

.admin-footer a{
  color:#cbd5e1;
  text-decoration:none;
}

.admin-footer a:hover{
  color:#fff;
}
</style>
</head>

<body>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container py-2">

    <a class="navbar-brand fs-4 d-flex align-items-center" href="../index.php">
      <span class="brand-mark">
        <i class="bi bi-compass"></i>
      </span>
      TripConnect
      <span class="brand-dot"></span>
    </a>

    <div class="ms-auto">
      <a href="../login.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
        <i class="bi bi-person me-1"></i> Đăng nhập người dùng
      </a>
    </div>

  </div>
</nav>

<main class="container admin-login-wrapper">

  <div class="row justify-content-center w-100">

    <div class="col-md-7 col-lg-5 col-xl-4">

      <div class="admin-login-card">

        <div class="admin-header">

          <div class="admin-icon">
            <i class="bi bi-shield-lock"></i>
          </div>

          <h3 class="fw-bold mb-2 text-white">
            TripConnect Admin
          </h3>

          <p class="mb-0 opacity-75">
            Đăng nhập quản trị hệ thống
          </p>

          <div class="admin-badge">
            <i class="bi bi-shield-check me-1"></i>
            Administrator
          </div>

        </div>

        <div class="admin-form">

          <form id="adminLoginForm" method="POST" action="admin-login.php" novalidate>

            <div class="mb-3">

              <label class="form-label fw-semibold" for="adminUsername">
                Tên đăng nhập / Email Admin
              </label>

              <div class="input-group">

                <span class="input-group-text">
                  <i class="bi bi-person-badge"></i>
                </span>

                <input
                  type="text"
                  id="adminUsername"
                  name="adminUsername"
                  class="form-control"
                  placeholder="admin hoặc email"
                  required
                >

              </div>

            </div>

            <div class="mb-3">

              <label class="form-label fw-semibold" for="adminPassword">
                Mật khẩu
              </label>

              <div class="input-group">

                <span class="input-group-text">
                  <i class="bi bi-lock"></i>
                </span>

                <input
                  type="password"
                  id="adminPassword"
                  name="adminPassword"
                  class="form-control"
                  placeholder="••••••••"
                  required
                >

                <button
                  class="btn btn-outline-secondary"
                  type="button"
                  id="toggleAdminPassword"
                  aria-label="Hiện/ẩn mật khẩu"
                >
                  <i class="bi bi-eye"></i>
                </button>

              </div>

            </div>

            <div class="form-check mb-4">

              <input
                class="form-check-input"
                type="checkbox"
                id="rememberAdmin"
              >

              <label class="form-check-label small" for="rememberAdmin">
                Ghi nhớ đăng nhập
              </label>

            </div>

            <button type="submit" class="admin-login-btn w-100">
              <i class="bi bi-box-arrow-in-right me-1"></i>
              Đăng nhập Admin
            </button>

            <!-- Hiển thị lỗi đăng nhập từ PHP -->
            <?php if (!empty($login_error)): ?>
              <div
                id="adminLoginErrorMsg"
                class="alert alert-danger mt-3 mb-0"
                role="alert"
              >
                <i class="bi bi-exclamation-circle me-1"></i>
                <?php echo $login_error; ?>
              </div>
            <?php endif; ?>

          </form>

          <div class="admin-note mt-4">
            <i class="bi bi-info-circle me-1"></i>
            Khu vực này dành riêng cho tài khoản quản trị hệ thống.
          </div>

          <div class="text-center mt-4">

            <a
              href="#"
              class="small text-decoration-none"
              data-bs-toggle="modal"
              data-bs-target="#adminForgotModal"
            >
              Quên mật khẩu Admin?
            </a>

          </div>

          <hr class="my-4">

          <div class="text-center">

            <a href="../login.php" class="small text-decoration-none">
              <i class="bi bi-arrow-left me-1"></i>
              Quay lại đăng nhập người dùng
            </a>

          </div>

        </div>

      </div>

      <p class="text-center text-muted small mt-3">
        <i class="bi bi-lock-fill me-1"></i>
        Khu vực quản trị được bảo vệ
      </p>

    </div>

  </div>

</main>

<!-- QUÊN MẬT KHẨU -->
<div
  class="modal fade"
  id="adminForgotModal"
  tabindex="-1"
  aria-hidden="true"
>

  <div class="modal-dialog modal-dialog-centered">

    <div class="modal-content border-0 shadow-lg rounded-4">

      <div class="modal-header">

        <div>
          <h5 class="modal-title fw-bold">
            <i class="bi bi-key me-2"></i>
            Khôi phục mật khẩu Admin
          </h5>

          <small class="text-muted">
            Nhập email quản trị viên
          </small>
        </div>

        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="modal"
          aria-label="Đóng"
        ></button>

      </div>

      <div class="modal-body p-4">

        <form id="adminForgotForm" novalidate>

          <div class="mb-3">

            <label class="form-label">
              Email Admin
            </label>

            <input
              type="email"
              id="adminForgotEmail"
              class="form-control"
              placeholder="admin@tripconnect.com"
              required
            >

          </div>

          <button type="submit" class="admin-login-btn w-100">
            <i class="bi bi-send me-1"></i>
            Gửi yêu cầu
          </button>

        </form>

      </div>

    </div>

  </div>

</div>

<footer class="admin-footer py-4">

  <div class="container text-center">

    <div class="mb-2">
      <strong class="text-white">TripConnect</strong>
      <span class="mx-2">•</span>
      Khu vực quản trị
    </div>

    <small>© 2026 TripConnect Demo</small>

  </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/app.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  // HIỆN / ẨN MẬT KHẨU
  var toggleAdminPassword = document.getElementById("toggleAdminPassword");
  var adminPassword = document.getElementById("adminPassword");

  if (toggleAdminPassword && adminPassword) {
    toggleAdminPassword.addEventListener("click", function () {
      var isHidden = adminPassword.type === "password";
      adminPassword.type = isHidden ? "text" : "password";
      this.innerHTML = isHidden
        ? '<i class="bi bi-eye-slash"></i>'
        : '<i class="bi bi-eye"></i>';
    });
  }

  // QUÊN MẬT KHẨU ADMIN
  var adminForgotForm = document.getElementById("adminForgotForm");
  if (adminForgotForm) {
    adminForgotForm.addEventListener("submit", function (event) {
      event.preventDefault();
      var email = document.getElementById("adminForgotEmail").value.trim();
      if (!email) return;
      alert("Đã gửi yêu cầu khôi phục mật khẩu đến " + email);
      this.reset();
      var modal = bootstrap.Modal.getInstance(document.getElementById("adminForgotModal"));
      if (modal) modal.hide();
    });
  }
});
</script>

</body>
</html>