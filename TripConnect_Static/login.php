<?php
// Khởi động session để lưu trạng thái đăng nhập
session_start();
// Nhúng file kết nối cơ sở dữ liệu
require_once 'config/database.php';
require_once 'config/email_verification.php';

$login_error = '';
$register_success = '';
$register_error = '';
$login_notice = '';
$needsVerification = false;
$resendIdentifier = '';
$authToken = trip_csrf_token('auth');
$authPostValid = $_SERVER['REQUEST_METHOD'] !== 'POST'
    || (is_string($_POST['csrf_token'] ?? null) && trip_verify_csrf('auth', $_POST['csrf_token']));
$authInput = static fn(string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
if (!$authPostValid) {
    http_response_code(403);
    $login_error = $register_error = 'Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang.';
}
$next=$_POST['next']??$_GET['next']??'index.php';
if(!is_string($next)||preg_match('/[\r\n\\\\]/',$next)||!preg_match('/^(?:index|explore|profile|trip-detail|feed)\.php(?:\?[^#]*)?(?:#.*)?$/D',$next))$next='index.php';

// Xử lý ĐĂNG NHẬP (POST)
if ($authPostValid && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim($authInput('loginUsername'));
    $password = $authInput('loginPassword');

    if (!empty($username) && !empty($password)) {
        try {
            // Truy vấn tìm user theo username hoặc email
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Tác giả demo chỉ dùng password_hash; không cho dùng chuỗi hash làm mật khẩu.
            $isDemoAuthor = $user && str_ends_with(strtolower((string) $user['email']), '@tripconnect.test')
                && str_starts_with((string) ($user['bio'] ?? ''), '[TripConnect demo v1 author:');
            // Chỉ tài khoản chưa có hash mới được dùng tương thích plaintext cũ.
            $legacyPassword = $user && !$isDemoAuthor && password_get_info($user['password'])['algoName'] === 'unknown';
            if ($user && $user['status']==='active' && (password_verify($password, $user['password']) || ($legacyPassword && hash_equals($user['password'], $password)))) {
                if (empty($user['email_verified_at'])) {
                    // Chỉ báo "chưa xác nhận" sau khi mật khẩu đã đúng, để không lộ tài khoản nào tồn tại.
                    $login_error = 'Tài khoản chưa được xác nhận email. Vui lòng bấm liên kết trong email chúng tôi đã gửi cho bạn.';
                    $needsVerification = true;
                    $resendIdentifier = $username;
                } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                header('Location: '.$next);
                exit();
                }
            } else {
                $login_error = "Email/username hoặc mật khẩu không đúng!";
            }
        } catch (PDOException $e) {
            $login_error = 'Không thể đăng nhập lúc này. Vui lòng thử lại sau.';
        }
    } else {
        $login_error = "Vui lòng nhập đầy đủ thông tin đăng nhập!";
    }
}

// Xử lý ĐĂNG KÝ (POST từ modal)
if ($authPostValid && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $name     = trim($authInput('registerName'));
    $username = trim($authInput('registerUsername'));
    $email    = trim($authInput('registerEmail'));
    $password = $authInput('registerPassword');
    $confirm  = $authInput('registerConfirmPassword');

    if (!empty($name) && !empty($username) && !empty($email) && !empty($password) && !empty($confirm)) {
        if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
            $register_error = 'Username phải dài 3–30 ký tự, chỉ gồm chữ không dấu, số, dấu chấm hoặc dấu gạch dưới.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Z0-9._%+-]+@gmail\.com$/i', $email)) {
            $register_error = "Vui lòng sử dụng email Gmail hợp lệ có đuôi @gmail.com!";
        } elseif (!preg_match('/^.{6,}$/us', $password) || strlen($password) > 72 || str_contains($password, "\0")) {
            $register_error = 'Mật khẩu cần ít nhất 6 ký tự và không quá 72 byte.';
        } elseif ((function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 100 || strlen($email) > 100) {
            $register_error = 'Tên hoặc email vượt quá độ dài cho phép.';
        } elseif ($password !== $confirm) {
            $register_error = 'Mật khẩu xác nhận không trùng khớp!';
        } else {
            try {
                // Kiểm tra username và email đã được sử dụng chưa.
                // Tài khoản đăng ký qua email nhưng KHÔNG xác nhận trong 24 giờ sẽ được giải phóng để người khác dùng lại.
                $stmtCheck = $conn->prepare("SELECT id, username, email, email_verified_at, email_verify_token_hash, (email_verify_expires < NOW()) AS expired FROM users WHERE username = ? OR email = ?");
                $stmtCheck->execute([$username, $email]);
                $taken = [];
                foreach ($stmtCheck->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $stale = empty($row['email_verified_at']) && !empty($row['email_verify_token_hash']) && (int) $row['expired'] === 1;
                    if ($stale) {
                        $conn->prepare('DELETE FROM users WHERE id = ? AND email_verified_at IS NULL')->execute([(int) $row['id']]);
                    } else {
                        $taken[] = $row;
                    }
                }
                $sameUsername = array_filter($taken, static fn($r) => strcasecmp((string) $r['username'], $username) === 0);
                $sameEmail = array_filter($taken, static fn($r) => strcasecmp((string) $r['email'], $email) === 0);
                if ($sameUsername) {
                    $register_error = 'Username này đã được sử dụng. Vui lòng chọn tên khác.';
                } elseif ($sameEmail) {
                    $register_error = 'Email này đã được đăng ký! Nếu chưa xác nhận, hãy đăng nhập để gửi lại email xác nhận.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // email_verified_at để NULL cho tới khi người dùng bấm liên kết trong email.
                    $stmtReg = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'member', 'active')");
                    $stmtReg->execute([$username, $email, $hashedPassword, $name]);
                    $newUserId = (int) $conn->lastInsertId();

                    if (trip_email_send_verification($conn, ['id' => $newUserId, 'email' => $email, 'full_name' => $name])) {
                        $register_success = "Chúng tôi đã gửi email xác nhận tới $email. Vui lòng bấm liên kết trong email (kiểm tra cả mục Spam) để kích hoạt tài khoản trước khi đăng nhập.";
                    } else {
                        $register_success = "Tài khoản đã được tạo nhưng chưa gửi được email xác nhận. Hãy vào trang đăng nhập và bấm “Gửi lại email xác nhận”.";
                    }
                }
            } catch (PDOException $e) {
                error_log('[TripConnect registration] request failed');
                $register_error = 'Không thể đăng ký lúc này. Vui lòng thử lại sau.';
            }
        }
    } else {
        $register_error = "Vui lòng điền đầy đủ thông tin đăng ký!";
    }
}

// Xử lý GỬI LẠI email xác nhận (luôn trả lời giống nhau để không lộ tài khoản nào tồn tại)
if ($authPostValid && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_verification') {
    $identifier = trim($authInput('loginUsername'));
    $login_notice = 'Nếu tài khoản tồn tại và chưa xác nhận, chúng tôi đã gửi lại email xác nhận. Vui lòng kiểm tra hộp thư (cả mục Spam).';
    if ($identifier !== '') {
        try {
            $stmtRe = $conn->prepare("SELECT id, email, full_name, status, email_verified_at, (email_verify_sent_at IS NULL OR email_verify_sent_at < DATE_SUB(NOW(), INTERVAL " . TRIP_EMAIL_RESEND_COOLDOWN_SECONDS . " SECOND)) AS can_resend FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmtRe->execute([$identifier, $identifier]);
            $reUser = $stmtRe->fetch(PDO::FETCH_ASSOC);
            if ($reUser && $reUser['status'] === 'active' && empty($reUser['email_verified_at']) && (int) $reUser['can_resend'] === 1) {
                trip_email_send_verification($conn, $reUser);
            }
        } catch (Throwable $e) {
            error_log('[TripConnect resend verification] ' . $e->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Đăng nhập TripConnect để quản lý lịch trình và kết nối với cộng đồng du lịch.">
<title data-i18n-ui>Đăng nhập | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-label="Mở menu điều hướng" data-i18n-attrs><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php" data-i18n="nav.home">Trang chủ</a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php" data-i18n="nav.explore">Khám phá</a></li>
    <li class="nav-item"><a class="nav-link" href="feed.php" data-i18n="nav.feed">Bảng tin</a></li>
    <li class="nav-item"><a class="nav-link" href="profile.php" data-i18n="nav.profile">Hồ sơ</a></li>
   </ul>
<div class="d-flex gap-2 align-items-center">
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Nếu đã đăng nhập: Hiển thị lời chào / tên tài khoản và nút Đăng xuất -->
        <span class="text-secondary small d-none d-md-inline"><span data-i18n-ui>Xin chào, </span><strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
        <form method="post" action="logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill"><span data-i18n-ui>Đăng xuất</span></button></form>
    <?php endif; ?>
    
    <div class="dropdown">
     <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-globe me-1"></i> <span data-i18n="nav.language">Ngôn ngữ</span></button>
     <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
      <li><a class="dropdown-item py-2" href="#" data-lang-btn="vi"><span data-i18n-ui>🇻🇳 Tiếng Việt</span></a></li>
      <li><a class="dropdown-item py-2" href="#" data-lang-btn="en"><span data-i18n-ui>🇬🇧 English</span></a></li>
     </ul>
    </div>
    <a class="btn btn-primary btn-sm px-3 rounded-pill" href="create-trip.php"><i class="bi bi-plus-lg"></i> <span data-i18n="nav.create_trip">Tạo lịch trình</span></a>
</div>
  </div>
 </div>
</nav>

<main class="container py-5">
 <div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">

   <div class="text-center mb-4">
    <div class="icon-circle mx-auto mb-3"><i class="bi bi-compass"></i></div>
    <h2 class="fw-bold" data-i18n="login.welcome">Chào mừng trở lại</h2>
    <p class="text-muted" data-i18n="login.description">Đăng nhập để quản lý lịch trình và kết nối cộng đồng TripConnect.</p>
   </div>

   <div class="card p-4 shadow-sm">
    <!-- Form đăng nhập kết nối database -->
    <form id="loginForm" method="POST" action="login.php" novalidate>
     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($authToken, ENT_QUOTES, 'UTF-8') ?>">
     <input type="hidden" name="action" value="login">
     <input type="hidden" name="next" value="<?= htmlspecialchars($next,ENT_QUOTES,'UTF-8') ?>">
     <div class="mb-3">
      <label class="form-label fw-semibold" for="loginUsername" data-i18n="login.email_username">Email hoặc username</label>
      <div class="input-group">
       <span class="input-group-text"><i class="bi bi-person"></i></span>
       <input type="text" id="loginUsername" name="loginUsername" class="form-control" required placeholder="user@gmail.com" data-i18n-attrs>
      </div>
     </div>

     <div class="mb-3">
      <label class="form-label fw-semibold" for="loginPassword" data-i18n="login.password">Mật khẩu</label>
      <div class="input-group">
       <span class="input-group-text"><i class="bi bi-lock"></i></span>
       <input type="password" id="loginPassword" name="loginPassword" class="form-control" required placeholder="" data-i18n-attrs>
       <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Hiện/ẩn mật khẩu" data-i18n-attrs><i class="bi bi-eye"></i></button>
      </div>
     </div>

     <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="form-check">
       <input class="form-check-input" type="checkbox" id="rememberMe">
       <label class="form-check-label small" for="rememberMe" data-i18n="login.remember">Ghi nhớ đăng nhập</label>
      </div>
      <a href="#" class="small text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" data-i18n="login.forgot">Quên mật khẩu?</a>
     </div>

     <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i> <span data-i18n="login.submit">Đăng nhập</span></button>

     <!-- Hiển thị lỗi đăng nhập từ PHP -->
     <?php if (!empty($login_error)): ?>
      <div class="alert alert-danger mt-3 mb-0" role="alert">
       <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($login_error, ENT_QUOTES, "UTF-8") ?>
      </div>
     <?php endif; ?>
     <?php if (!empty($login_notice)): ?>
      <div class="alert alert-info mt-3 mb-0" role="status"><i class="bi bi-envelope-check me-1"></i> <?= htmlspecialchars($login_notice, ENT_QUOTES, "UTF-8") ?></div>
     <?php endif; ?>
    </form>
    <?php if ($needsVerification): ?>
    <form method="POST" action="login.php" class="mt-2">
     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($authToken, ENT_QUOTES, 'UTF-8') ?>">
     <input type="hidden" name="action" value="resend_verification">
     <input type="hidden" name="loginUsername" value="<?= htmlspecialchars($resendIdentifier, ENT_QUOTES, 'UTF-8') ?>">
     <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill"><i class="bi bi-envelope me-1"></i> Gửi lại email xác nhận</button>
    </form>
    <?php endif; ?>

    <hr class="my-4">

    <p class="text-center mb-2"><span data-i18n="login.no_account">Chưa có tài khoản?</span>
     <a href="#" class="fw-semibold text-decoration-none" data-bs-toggle="modal" data-bs-target="#registerModal" data-i18n="login.register_now">Đăng ký ngay</a>
    </p>

    <div class="text-center mt-3">
     <span class="small text-muted" data-i18n="login.admin_question">Bạn là quản trị viên?</span>
     <a href="admin/admin-login.php" data-i18n="login.admin_link">Đăng nhập Admin</a>
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
    <div><h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i><span data-i18n="login.create_account">Tạo tài khoản</span></h5><small class="text-muted" data-i18n="login.join">Tham gia cộng đồng TripConnect</small></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng" data-i18n-attrs></button>
   </div>
   <div class="modal-body p-4">
    <form id="registerForm" method="POST" action="login.php" novalidate>
     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($authToken, ENT_QUOTES, 'UTF-8') ?>">
     <input type="hidden" name="action" value="register">
     <div class="mb-3">
       <label class="form-label" for="registerName" data-i18n="login.full_name">Họ và tên</label>
       <input type="text" id="registerName" name="registerName" class="form-control" placeholder="Nguyễn Văn A" maxlength="100" autocomplete="name" required data-i18n-attrs>
       <div class="invalid-feedback">Vui lòng nhập họ và tên.</div>
      </div>
     <div class="mb-3">
       <label class="form-label" for="registerUsername">Tên đăng nhập (username)</label>
       <input type="text" id="registerUsername" name="registerUsername" class="form-control" placeholder="vd: vy_2006" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.]{3,30}" autocomplete="username" required aria-describedby="registerUsernameHelp">
       <div class="form-text" id="registerUsernameHelp">3–30 ký tự; dùng chữ không dấu, số, dấu chấm hoặc dấu gạch dưới. Bạn có thể dùng username này để đăng nhập.</div>
       <div class="invalid-feedback">Username chỉ được gồm chữ không dấu, số, dấu chấm hoặc dấu gạch dưới (3–30 ký tự).</div>
      </div>
     <div class="mb-3">
       <label class="form-label" for="registerEmail" data-i18n="login.email">Email</label>
       <input type="email" id="registerEmail" name="registerEmail" class="form-control" placeholder="user@gmail.com" maxlength="100" pattern="^[A-Za-z0-9._%+-]+@gmail\.com$" title="Chỉ chấp nhận email có đuôi @gmail.com" autocomplete="email" required data-i18n-attrs>
       <div class="invalid-feedback" id="registerEmailFeedback">Vui lòng nhập email Gmail hợp lệ có đuôi @gmail.com.</div>
      </div>
     <div class="mb-3">
       <label class="form-label" for="registerPassword" data-i18n="login.password">Mật khẩu</label>
       <div class="input-group">
        <input type="password" id="registerPassword" name="registerPassword" class="form-control" minlength="6" maxlength="72" autocomplete="new-password" required data-i18n-attrs>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password="registerPassword" aria-label="Hiện/ẩn mật khẩu"><i class="bi bi-eye"></i></button>
       </div>
       <div class="form-text" id="passwordHelp" data-i18n="login.password_min">Mật khẩu tối thiểu 6 ký tự.</div>
       <div class="invalid-feedback" id="registerPasswordFeedback">Mật khẩu cần ít nhất 6 ký tự.</div>
      </div>
     <div class="mb-3">
       <label class="form-label" for="registerConfirmPassword" data-i18n="login.confirm_password">Xác nhận mật khẩu</label>
       <div class="input-group">
        <input type="password" id="registerConfirmPassword" name="registerConfirmPassword" class="form-control" placeholder="Nhập lại mật khẩu" autocomplete="new-password" required data-i18n-attrs>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password="registerConfirmPassword" aria-label="Hiện/ẩn mật khẩu xác nhận"><i class="bi bi-eye"></i></button>
       </div>
       <div class="invalid-feedback" id="confirmPasswordFeedback">Mật khẩu xác nhận không trùng khớp.</div>
       <div class="valid-feedback" id="confirmPasswordSuccess">Mật khẩu trùng khớp.</div>
      </div>
     <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-person-plus me-1"></i> <span data-i18n="login.register_submit">Đăng ký</span></button>
     
     <?php if (!empty($register_success)): ?>
      <div class="alert alert-success mt-3 mb-0" role="status" aria-live="polite">
        <div><i class="bi bi-check-circle-fill me-1"></i><strong>Đăng ký thành công!</strong></div>
        <div class="small mt-1"><?= htmlspecialchars($register_success, ENT_QUOTES, "UTF-8") ?></div>
        <button type="button" class="btn btn-success btn-sm mt-3" id="goToLogin" data-bs-dismiss="modal">Quay lại đăng nhập</button>
       </div>
     <?php endif; ?>
     <?php if (!empty($register_error)): ?>
      <div class="alert alert-danger mt-3 mb-0" role="alert" aria-live="assertive"><i class="bi bi-exclamation-circle-fill me-1"></i><?= htmlspecialchars($register_error, ENT_QUOTES, "UTF-8") ?></div>
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
    <div><h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i><span data-i18n="login.forgot_title">Quên mật khẩu</span></h5><small class="text-muted" data-i18n="login.forgot_desc">Khôi phục tài khoản TripConnect</small></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng" data-i18n-attrs></button>
   </div>
   <div class="modal-body p-4">
    <p class="text-muted small" data-i18n="login.forgot_instruction">Nhập email đã đăng ký. Hệ thống sẽ gửi hướng dẫn đặt lại mật khẩu.</p>
    <form id="forgotPasswordForm" novalidate>
     <div class="mb-3"><label class="form-label" data-i18n="login.email">Email</label><input type="email" id="forgotEmail" class="form-control" placeholder="user@gmail.com" required data-i18n-attrs></div>
     <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-send me-1"></i> <span data-i18n="login.send_request">Gửi yêu cầu</span></button>
    </form>
   </div>
  </div>
 </div>
</div>

<footer class="footer">
 <div class="container">
  <a class="footer-brand d-inline-flex align-items-center mb-4" href="index.php">
   <span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span>
  </a>
  <div class="row g-4">
   <div class="col-lg-4 col-md-6">
    <h6 class="footer-title" data-i18n="footer.company_title">Công ty TripConnect</h6>
    <ul class="footer-contact list-unstyled mb-0">
     <li><i class="bi bi-geo-alt-fill"></i><span data-i18n="footer.address">281 Nam Kỳ Khởi Nghĩa, Phường Xuân Hòa, TP.HCM</span></li>
     <li><i class="bi bi-envelope-fill"></i><span><a href="mailto:info@tripconnect.com"><span data-i18n-ui>info@tripconnect.com</span></a> - <a href="mailto:info.asia@tripconnect.com"><span data-i18n-ui>info.asia@tripconnect.com</span></a> <span data-i18n="footer.english_support">(English Support)</span></span></li>
     <li><i class="bi bi-globe2"></i><span data-i18n="footer.follow_us">Theo dõi chúng tôi trên:</span></li>
    </ul>
    <div class="footer-social">
     <span aria-label="Instagram" data-i18n-attrs><i class="bi bi-instagram"></i></span>
     <span aria-label="Facebook" data-i18n-attrs><i class="bi bi-facebook"></i></span>
     <span aria-label="WhatsApp" data-i18n-attrs><i class="bi bi-whatsapp"></i></span>
     <span aria-label="TikTok" data-i18n-attrs><i class="bi bi-tiktok"></i></span>
     <span aria-label="Messenger" data-i18n-attrs><i class="bi bi-messenger"></i></span>
    </div>
    <a class="btn-hotline" href="tel:1800646888"><i class="bi bi-telephone-fill"></i> 1800 646 888</a>
    <div class="footer-note" data-i18n="footer.hotline_note">Tổng đài miễn phí 24/7</div>
   </div>
   <div class="col-lg-2 col-md-6 offset-lg-1">
    <h6 class="footer-title" data-i18n="footer.explore_title">Khám phá</h6>
    <a class="footer-link" href="explore.php" data-i18n="footer.explore_1">Lịch trình nổi bật</a>
    <a class="footer-link" href="feed.php" data-i18n="footer.explore_2">Bảng tin</a>
    <a class="footer-link" href="create-trip.php" data-i18n="nav.create_trip">Tạo lịch trình</a>
   </div>
   <div class="col-lg-2 col-md-6">
    <h6 class="footer-title" data-i18n="footer.info_title">Thông tin</h6>
    <a class="footer-link" href="#" data-i18n="footer.about">Về chúng tôi</a>
    <a class="footer-link" href="#" data-i18n="footer.news">Tin tức</a>
    <a class="footer-link" href="#" data-i18n="footer.sitemap">Sitemap</a>
    <a class="footer-link" href="#" data-i18n="footer.help">Trợ giúp</a>
   </div>
   <div class="col-lg-3 col-md-6">
    <h6 class="footer-title" data-i18n="footer.terms_title">Điều kiện - Điều khoản</h6>
    <a class="footer-link" href="#" data-i18n="footer.privacy">Chính sách riêng tư</a>
    <a class="footer-link" href="#" data-i18n="footer.terms">Thỏa thuận sử dụng</a>
    <a class="footer-link" href="#" data-i18n="footer.data_policy">Chính sách bảo vệ dữ liệu cá nhân</a>
   </div>
  </div>
  <hr class="footer-hr my-4"><small><span data-i18n-ui>© 2026 TripConnect</span></small>
 </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/app.js?v=2"></script>
<script src="assets/i18n.js?v=12"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  // Dùng chung chức năng hiện/ẩn mật khẩu cho đăng nhập và đăng ký.
  var loginToggle = document.getElementById("togglePassword");
  if (loginToggle) loginToggle.setAttribute("data-toggle-password", "loginPassword");

  document.querySelectorAll("[data-toggle-password]").forEach(function (button) {
    button.addEventListener("click", function () {
      var input = document.getElementById(button.getAttribute("data-toggle-password"));
      if (!input) return;
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      button.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
      button.setAttribute("aria-label", show ? "Ẩn mật khẩu" : "Hiện mật khẩu");
    });
  });

  var registerForm = document.getElementById("registerForm");
  var password = document.getElementById("registerPassword");
  var confirmPassword = document.getElementById("registerConfirmPassword");
  var email = document.getElementById("registerEmail");

  function checkPasswordMatch() {
    if (!password || !confirmPassword) return true;
    var hasValue = confirmPassword.value.length > 0;
    var matches = hasValue && password.value === confirmPassword.value;
    confirmPassword.setCustomValidity(matches || !hasValue ? "" : "Mật khẩu xác nhận không trùng khớp.");
    confirmPassword.classList.toggle("is-invalid", hasValue && !matches);
    confirmPassword.classList.toggle("is-valid", matches);
    return matches;
  }

  if (password) password.addEventListener("input", checkPasswordMatch);
  if (confirmPassword) confirmPassword.addEventListener("input", checkPasswordMatch);

  if (email) {
    email.addEventListener("input", function () {
      var validGmail = /^[A-Za-z0-9._%+-]+@gmail\.com$/i.test(email.value.trim());
      email.setCustomValidity(email.value && !validGmail ? "Vui lòng sử dụng email có đuôi @gmail.com." : "");
    });
  }

  if (registerForm) {
    registerForm.addEventListener("submit", function (event) {
      checkPasswordMatch();
      if (!registerForm.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
        registerForm.classList.add("was-validated");
        var firstInvalid = registerForm.querySelector(":invalid");
        if (firstInvalid) firstInvalid.focus();
      }
    });
  }

  // PHP trả kết quả sau POST: tự mở lại modal để người dùng thấy thông báo.
  var hasRegisterResult = <?php echo (!empty($register_success) || !empty($register_error)) ? 'true' : 'false'; ?>;
  if (hasRegisterResult) {
    var modalElement = document.getElementById("registerModal");
    if (modalElement && window.bootstrap) {
      bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
  }
});
</script>
</body>
</html>