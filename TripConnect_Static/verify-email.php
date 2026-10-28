<?php
session_start();
require_once 'config/database.php';

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$status = 'invalid';

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $stmt = $conn->prepare('SELECT id, email_verified_at, (email_verify_expires < NOW()) AS expired FROM users WHERE email_verify_token_hash = ? LIMIT 1');
        $stmt->execute([hash('sha256', $token)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && empty($user['email_verified_at'])) {
            if ((int) $user['expired'] === 1) {
                $status = 'expired';
            } else {
                $upd = $conn->prepare('UPDATE users SET email_verified_at = NOW(), email_verify_token_hash = NULL, email_verify_expires = NULL WHERE id = ? AND email_verified_at IS NULL');
                $upd->execute([(int) $user['id']]);
                $status = 'ok';
            }
        }
    } catch (PDOException $e) {
        error_log('[TripConnect verify-email] ' . $e->getMessage());
        $status = 'error';
    }
}

$messages = [
    'ok'      => ['success', 'bi-check-circle-fill', 'Xác nhận email thành công!', 'Tài khoản của bạn đã được kích hoạt. Bạn có thể đăng nhập ngay bây giờ.'],
    'expired' => ['warning', 'bi-clock-history', 'Liên kết đã hết hạn', 'Hãy quay lại trang đăng nhập và bấm “Gửi lại email xác nhận” để nhận liên kết mới.'],
    'invalid' => ['danger', 'bi-x-circle-fill', 'Liên kết không hợp lệ', 'Liên kết sai hoặc đã được sử dụng. Nếu bạn đã xác nhận trước đó, chỉ cần đăng nhập.'],
    'error'   => ['danger', 'bi-exclamation-triangle-fill', 'Có lỗi xảy ra', 'Không thể xác nhận lúc này. Vui lòng thử lại sau.'],
];
[$tone, $icon, $title, $text] = $messages[$status];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Xác nhận email | TripConnect</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width:520px">
  <div class="card shadow-sm border-0 rounded-4 p-4 text-center">
    <div class="display-5 text-<?= $tone ?> mb-3"><i class="bi <?= $icon ?>"></i></div>
    <h1 class="h4 mb-2"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-muted"><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?></p>
    <a class="btn btn-primary rounded-pill px-4 mt-2" href="login.php">Đến trang đăng nhập</a>
  </div>
</main>
</body>
</html>
