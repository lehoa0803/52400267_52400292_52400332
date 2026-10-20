<?php
// Khởi động session
session_start();

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