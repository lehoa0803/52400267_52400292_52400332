<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

trip_require_active_account($conn);
$trip_id = filter_var($_POST['trip_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !is_string($_SESSION['profile_csrf_token'] ?? null)
    || $_SESSION['profile_csrf_token'] === ''
    || !is_string($_POST['csrf_token'] ?? null)
    || $_POST['csrf_token'] === ''
    || !hash_equals($_SESSION['profile_csrf_token'], $_POST['csrf_token'])) {
    http_response_code(405);
    exit('Yêu cầu không hợp lệ.');
}

if ($trip_id > 0) {
    try {
        $conn->beginTransaction();
        // Kiểm tra xem chuyến đi này có đúng là của user đang đăng nhập không trước khi xóa
        $stmtCheck = $conn->prepare("SELECT id FROM trips WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$trip_id, $user_id]);

        if ($stmtCheck->fetch()) {
            // Xóa các hoạt động thuộc các ngày của chuyến đi trước (để tránh lỗi khóa ngoại)
            $stmtDelActs = $conn->prepare("DELETE FROM activities WHERE trip_day_id IN (SELECT id FROM trip_days WHERE trip_id = ?)");
            $stmtDelActs->execute([$trip_id]);
            // Xóa các ngày của chuyến đi
            $conn->exec("DELETE FROM trip_days WHERE trip_id = $trip_id");
            // Xóa chuyến đi chính
            $stmtDel = $conn->prepare("DELETE FROM trips WHERE id = ?");
            $stmtDel->execute([$trip_id]);
        }
        $conn->commit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('[TripConnect delete-trip] delete failed: ' . $e->getMessage());
    }
}

header("Location: profile.php");
exit();
