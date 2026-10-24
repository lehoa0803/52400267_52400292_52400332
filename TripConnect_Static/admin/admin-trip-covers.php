<?php
session_start();
require_once '../config/database.php';
require_once '../config/admin_auth.php';
require_once '../config/trip_cover_maintenance.php';

$isRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if ($isRequest) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Chỉ Admin được bảo trì ảnh bìa.']);
    } else header('Location: admin-login.php');
    exit;
}
$admin = $conn->prepare("SELECT role, status FROM users WHERE id = ?");
$admin->execute([(int) $_SESSION['user_id']]);
$account = $admin->fetch(PDO::FETCH_ASSOC);
if (!$account || $account['role'] !== 'admin' || $account['status'] !== 'active') {
    http_response_code(403);
    if ($isRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Tài khoản quản trị không còn quyền thao tác.']);
        exit;
    }
    exit('Tài khoản quản trị không còn quyền thao tác.');
}
$csrf = trip_csrf_token('cover_maintenance');
$now = time();
foreach ($_SESSION['cover_maintenance_previews'] ?? [] as $id => $preview) {
    if (($preview['expires'] ?? 0) < $now) unset($_SESSION['cover_maintenance_previews'][$id]);
}
if ($isRequest) {
    header('Content-Type: application/json; charset=utf-8');
    $reply = static function (bool $ok, string $message, array $extra = []): never {
        echo json_encode(['ok' => $ok, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    };
    if (!trip_verify_csrf('cover_maintenance', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        http_response_code(403);
        $reply(false, 'CSRF không hợp lệ. Vui lòng tải lại trang.');
    }
    try {
        if (!trip_cover_metadata_supported($conn)) throw new RuntimeException('Database chưa đủ metadata ảnh bìa; chưa thể bảo trì.');
        if (($_POST['action'] ?? '') === 'preview') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) throw new RuntimeException('Mã lịch trình không hợp lệ.');
            unset($_SESSION['cover_maintenance_previews'][$id]);
            $query = $conn->prepare("SELECT t.*, l.name AS location_name FROM trips t LEFT JOIN locations l ON l.id = t.location_id WHERE t.id = ? AND t.status <> 'deleted'");
            $query->execute([$id]);
            $trip = $query->fetch(PDO::FETCH_ASSOC);
            if (!$trip) throw new RuntimeException('Lịch trình không tồn tại hoặc đã xóa.');
            $assessment = trip_cover_assess($trip, true);
            if (!$assessment['eligible']) $reply(true, $assessment['reason'], ['status' => 'protected']);
            set_time_limit(90);
            $proposal = trip_cover_propose($conn, $trip);
            if ($proposal['status'] !== 'ready' || !is_array($proposal['candidate'] ?? null)) {
                $reply(true, $proposal['note'], $proposal);
            }
            $token = bin2hex(random_bytes(24));
            $_SESSION['cover_maintenance_previews'][$id] = $proposal + [
                'token' => $token, 'snapshot' => trip_cover_snapshot($trip), 'eligible' => true,
                'area' => (string) $trip['location_name'], 'expires' => time() + 1800,
            ];
            $reply(true, $assessment['reason'] . ' ' . $proposal['note'], $proposal + ['token' => $token]);
        }
        if (($_POST['action'] ?? '') === 'apply') {
            $requests = $_POST['confirmations'] ?? null;
            if (!is_array($requests)) throw new RuntimeException('Thiếu danh sách đề xuất đã xác nhận.');
            $count = trip_cover_apply_previews($conn, $requests, $_SESSION['cover_maintenance_previews'] ?? [], time());
            foreach (array_keys($requests) as $id) unset($_SESSION['cover_maintenance_previews'][$id]);
            $reply(true, "Đã cập nhật ảnh bìa cho $count lịch trình. Các nội dung khác được giữ nguyên.");
        }
        throw new RuntimeException('Thao tác không hợp lệ.');
    } catch (Throwable $e) {
        error_log('[TripConnect cover maintenance] ' . $e->getMessage());
        http_response_code(400);
        $message = $e instanceof PDOException || !($e instanceof RuntimeException || $e instanceof InvalidArgumentException)
            ? 'Không thể xử lý ảnh; hãy tải lại đề xuất và thử lại.' : $e->getMessage();
        $reply(false, $message);
    }
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = (int) $conn->query("SELECT COUNT(*) FROM trips WHERE status <> 'deleted'")->fetchColumn();
$pages = max(1, (int) ceil($total / 10));
$page = min($page, $pages);
$offset = ($page - 1) * 10;
$trips = $conn->query("SELECT t.*, l.name AS location_name FROM trips t LEFT JOIN locations l ON l.id = t.location_id WHERE t.status <> 'deleted' ORDER BY t.id LIMIT 10 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title data-i18n-ui>Bảo trì ảnh bìa | TripConnect Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/style.css" rel="stylesheet">
<style>.cover-audit-image{width:180px;height:110px;object-fit:cover;border-radius:12px;background:#eef2f1}.cover-audit-table td{min-width:180px;vertical-align:top}.cover-audit-table td:first-child{min-width:220px}.cover-audit-table input{width:1.1rem;height:1.1rem}.cover-audit-table label{cursor:pointer}</style>
</head><body><main class="container py-4">
<a href="admin-trips.php" class="btn btn-outline-secondary mb-3"><span data-i18n-ui>← Quản lý lịch trình</span></a>
<h1 class="h3"><span data-i18n-ui>Bảo trì ảnh bìa lịch trình</span></h1>
<p><span data-i18n-ui>Hệ thống chọn ảnh theo điểm đến, tên lịch trình và địa điểm Timeline. Admin xem trước rồi xác nhận một hoặc nhiều lịch trình; không cần đăng nhập tài khoản tác giả.</span></p>
<div class="alert alert-info"><span data-i18n-ui>Tìm ảnh chỉ tạo đề xuất, chưa ghi MySQL. Giữ nguyên ảnh upload/ảnh do người dùng chọn khi chưa có bằng chứng hỏng. Đề xuất hết hạn sau 30 phút; lịch trình thay đổi phải duyệt lại. Nếu API lỗi hoặc chưa có ảnh phù hợp, hãy tìm lại; hệ thống không ghi placeholder vào database.</span></div>
<div class="d-flex gap-2 flex-wrap mb-3">
<button type="button" class="btn btn-primary" id="previewCovers"><span data-i18n-ui>Kiểm tra </span>&amp;<span data-i18n-ui> tự tìm ảnh cho mục đã chọn</span></button>
<button type="button" class="btn btn-success" id="applyCovers" disabled><span data-i18n-ui>Xác nhận cập nhật các đề xuất đã chọn</span></button>
</div>
<div id="coverMaintenanceStatus" class="mb-3" role="status" aria-live="polite"></div>
<form id="coverMaintenanceForm" data-csrf="<?php echo $escape($csrf); ?>">
<div class="table-responsive"><table class="table cover-audit-table bg-white"><thead><tr><th><span data-i18n-ui>Lịch trình </span><button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllCovers"><span data-i18n-ui>Chọn tất cả</span></button></th><th><span data-i18n-ui>Ảnh hiện tại</span></th><th><span data-i18n-ui>Kiểm tra</span></th><th><span data-i18n-ui>Ảnh hệ thống đề xuất</span></th></tr></thead><tbody>
<?php foreach ($trips as $trip): $assessment = trip_cover_assess($trip); $image = trip_safe_image_url($trip['cover_image']); ?>
<tr data-trip-id="<?php echo (int) $trip['id']; ?>"><td><label><input type="checkbox" name="trip_ids[]" value="<?php echo (int) $trip['id']; ?>" <?php echo $assessment['eligible'] ? 'checked' : ''; ?>> <strong>#<?php echo (int) $trip['id']; ?> <?php echo $escape($trip['title']); ?></strong></label><div class="small text-muted"><?php echo $escape($trip['location_name']); ?> · <?php echo $escape($trip['status']); ?> / <?php echo $escape($trip['moderation_status']); ?></div></td>
<td><img data-trip-cover class="cover-audit-image" src="<?php echo $escape($image && !str_starts_with($image, 'assets/') ? $image : ($image ? '../' . $image : '../assets/images/trip-placeholder.svg')); ?>" alt="" data-i18n-attrs><div class="small text-muted"><?php echo $escape($trip['cover_source'] ?: 'Không có metadata nguồn'); ?></div></td>
<td class="cover-audit-note small"><?php echo $escape($assessment['reason']); ?></td><td class="cover-audit-proposal small"><span data-i18n-ui>Chưa tìm ảnh.</span></td></tr>
<?php endforeach; ?>
<?php if (!$trips): ?><tr><td colspan="4"><span data-i18n-ui>Chưa có lịch trình.</span></td></tr><?php endif; ?>
</tbody></table></div></form>
<nav class="d-flex gap-2 align-items-center mt-3" aria-label="Phân trang" data-i18n-attrs><?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="?page=<?php echo $page - 1; ?>"><span data-i18n-ui>Trang trước</span></a><?php endif; ?><span><span data-i18n-ui>Trang </span><?php echo $page; ?> / <?php echo $pages; ?> · <?php echo $total; ?><span data-i18n-ui> lịch trình</span></span><?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="?page=<?php echo $page + 1; ?>"><span data-i18n-ui>Trang sau</span></a><?php endif; ?></nav>
</main><script src="../assets/app.js?v=4"></script><script src="../assets/pages/admin-trip-covers.js?v=2"></script><script src="../assets/i18n.js?v=10"></script>
</body></html>
