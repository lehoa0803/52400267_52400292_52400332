<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$trip_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($trip_id > 0) {
    try {
        // Kiểm tra xem chuyến đi này có đúng là của user đang đăng nhập không trước khi xóa
        $stmtCheck = $conn->prepare("SELECT id FROM trips WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$trip_id, $user_id]);
        
        if ($stmtCheck->fetch()) {
            // Xóa các hoạt động thuộc các ngày của chuyến đi trước (để tránh lỗi khóa ngoại)
            $conn->exec("DELETE FROM activities WHERE trip_day_id IN (SELECT id FROM trip_days WHERE trip_id = $trip_id)");
            // Xóa các ngày của chuyến đi
            $conn->exec("DELETE FROM trip_days WHERE trip_id = $trip_id");
            // Xóa chuyến đi chính
            $stmtDel = $conn->prepare("DELETE FROM trips WHERE id = ?");
            $stmtDel->execute([$trip_id]);
        }
    } catch (PDOException $e) {
        // Xử lý lỗi nếu cần
    }
}

header("Location: profile.php");
exit();