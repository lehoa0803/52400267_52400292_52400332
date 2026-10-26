<?php
// Khởi động session
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
$submitted = $_POST['logout_csrf'] ?? null;
$expected = $_SESSION['trip_csrf_logout'] ?? null;
if (!is_string($submitted) || !is_string($expected) || $expected === '' || !hash_equals($expected, $submitted)) {
    http_response_code(403);
    exit('<meta charset="utf-8"><p data-i18n-ui>Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang.</p><script src="assets/i18n.js?v=12"></script>');
}

// Xóa toàn bộ các biến trong session
$_SESSION = array();

// Hủy hoàn toàn phiên làm việc (session)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Chuyển hướng người dùng về trang đăng nhập chung (login.php)
// (Điều chỉnh lại đường dẫn ../login.php nếu file logout.php nằm trong thư mục admin/)
header("Location: login.php");
exit();
?>
