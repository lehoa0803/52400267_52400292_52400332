<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit();
}

// Catalog được tạo từ luồng chọn địa điểm của người dùng; route quản trị cũ chỉ còn để tương thích.
header('Location: admin-dashboard.php?notice=location-management-automated');
exit();

$csrfToken = $_SESSION['admin_location_catalog_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['admin_location_catalog_csrf'] = $csrfToken;
$successMessage = '';
$errorMessage = '';

function catalogText(string $key, int $maxLength = 500): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function catalogUrl(string $key): ?string
{
    $value = catalogText($key, 500);
    if ($value === '') return null;
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    if (!filter_var($value, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('URL không hợp lệ.');
    }
    return $value;
}

function catalogCoordinate(string $key, float $min, float $max): ?string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') return null;
    if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
        throw new InvalidArgumentException('Tọa độ không hợp lệ.');
    }
    return number_format((float) $value, 8, '.', '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['admin_location_catalog_csrf'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Phiên biểu mẫu không hợp lệ.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'toggle_status') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            if (!$id || !in_array($status, ['active', 'hidden'], true)) throw new InvalidArgumentException('Trạng thái không hợp lệ.');
            $stmt = $conn->prepare('UPDATE location_catalog SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
            $successMessage = 'Đã cập nhật trạng thái địa điểm.';
        } elseif ($action === 'save_location') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
            $areaId = filter_input(INPUT_POST, 'area_location_id', FILTER_VALIDATE_INT) ?: null;
            $name = catalogText('name', 150);
            $source = catalogText('location_source', 30) ?: 'user';
            $status = catalogText('status', 20) ?: 'active';
            if ($name === '' || !in_array($source, ['user', 'osm', 'other'], true) || !in_array($status, ['active', 'hidden'], true)) {
                throw new InvalidArgumentException('Tên, nguồn hoặc trạng thái không hợp lệ.');
            }
            $sourceRef = catalogText('location_source_ref', 255) ?: null;
            if ($source === 'user') $sourceRef = null;
            $params = [
                $areaId, $name, catalogText('address', 255) ?: null, catalogText('description', 5000) ?: null,
                catalogText('category', 50) ?: null, catalogCoordinate('latitude', -90, 90), catalogCoordinate('longitude', -180, 180),
                catalogUrl('image_url'), catalogUrl('image_source_url'), catalogText('image_attribution', 500) ?: null,
                catalogText('image_license', 100) ?: null, $source, $sourceRef, catalogUrl('source_url'), null, $status
            ];
            if ($id) {
                $stmt = $conn->prepare('UPDATE location_catalog SET area_location_id=?, name=?, address=?, description=?, category=?, latitude=?, longitude=?, image_url=?, image_source_url=?, image_attribution=?, image_license=?, location_source=?, location_source_ref=?, source_url=?, location_verified_at=?, status=? WHERE id=?');
                $stmt->execute(array_merge($params, [$id]));
                $successMessage = 'Đã cập nhật địa điểm cụ thể.';
            } else {
                $stmt = $conn->prepare('INSERT INTO location_catalog (area_location_id,name,address,description,category,latitude,longitude,image_url,image_source_url,image_attribution,image_license,location_source,location_source_ref,source_url,location_verified_at,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute(array_merge($params, [(int) $_SESSION['user_id']]));
                $successMessage = 'Đã thêm địa điểm cụ thể.';
            }
        } else {
            throw new InvalidArgumentException('Thao tác không được hỗ trợ.');
        }
    } catch (PDOException $e) {
        $errorMessage = $e->getCode() === '23000' ? 'Mã nguồn địa điểm đã tồn tại hoặc khu vực không hợp lệ.' : 'Không thể lưu địa điểm vào cơ sở dữ liệu.';
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$editLocation = null;
if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
    $stmt = $conn->prepare('SELECT * FROM location_catalog WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editLocation = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$areas = $conn->query('SELECT id, name, region FROM locations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$locations = $conn->query('SELECT lc.*, l.name AS area_name, l.region AS area_region, (SELECT COUNT(*) FROM activities a WHERE a.location_catalog_id=lc.id) AS activity_count FROM location_catalog lc LEFT JOIN locations l ON l.id=lc.area_location_id ORDER BY lc.updated_at DESC, lc.id DESC')->fetchAll(PDO::FETCH_ASSOC);
function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin - Địa điểm cụ thể</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"><link rel="stylesheet" href="../assets/style.css">
<style>body{margin:0;background:#f5f8f7;color:#173232;font-family:var(--font-body)}.sidebar{position:fixed;width:220px;height:100vh;background:#073b3a;color:#fff;padding:20px 12px}.brand{font-size:17px;font-weight:600;margin-bottom:25px}.menu{display:block;color:#d5e5e3;text-decoration:none;padding:10px 12px;border-radius:7px;margin:4px 0;font-size:14px}.menu:hover,.menu.active{background:#0d7771;color:#fff}.main{margin-left:220px}.topbar{height:62px;background:#fff;border-bottom:1px solid #ddd;display:flex;justify-content:flex-end;align-items:center;padding:0 25px}.content{padding:25px}.card{border:0;box-shadow:0 2px 8px #00000008}table{font-size:13px}.badge-active{background:#d1e7dd;color:#0f5132}.badge-hidden{background:#f8d7da;color:#842029}@media(max-width:900px){.sidebar{position:static;width:100%;height:auto}.main{margin-left:0}}</style></head><body>
<aside class="sidebar"><div class="brand"><i class="bi bi-compass"></i> TripConnect Admin</div>
<a class="menu" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
<a class="menu" href="admin-reports.php"><i class="bi bi-bar-chart me-2"></i>Báo cáo</a>
<a class="menu" href="admin-users.php"><i class="bi bi-people me-2"></i>Người dùng</a>
<a class="menu" href="admin-trips.php"><i class="bi bi-map me-2"></i>Lịch trình</a>
<a class="menu" href="admin-comments.php"><i class="bi bi-chat-left-text me-2"></i>Bình luận</a>
<a class="menu" href="admin-tags.php"><i class="bi bi-tags me-2"></i>Tag &amp; sở thích</a>
<a class="menu" href="admin-logs.php"><i class="bi bi-journal-text me-2"></i>Nhật ký</a>
<a class="menu" href="admin-settings.php"><i class="bi bi-gear me-2"></i>Cài đặt</a>
<a class="menu mt-4" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></aside>
<main class="main"><header class="topbar"><span><i class="bi bi-person-circle me-1"></i><?= h($_SESSION['full_name'] ?? 'Admin') ?></span></header><div class="content">
<?php if ($successMessage): ?><div class="alert alert-success"><?= h($successMessage) ?></div><?php endif; ?><?php if ($errorMessage): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
<div class="mb-4"><h4 class="mb-1">Địa điểm cụ thể</h4><p class="text-muted mb-0">Quản lý điểm tham quan, ăn uống, lưu trú và tiện ích có nguồn dữ liệu.</p></div><div class="alert alert-info small">Khu vực chính trong <code>locations</code> vẫn được giữ nguyên. Dữ liệu tại đây là địa điểm cụ thể trong <code>location_catalog</code>; không tự đánh dấu xác minh.</div>
<div class="row g-4"><div class="col-xl-5"><div class="card p-4"><h5><?= $editLocation ? 'Chỉnh sửa địa điểm' : 'Thêm địa điểm cụ thể' ?></h5><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="save_location"><input type="hidden" name="id" value="<?= (int) ($editLocation['id'] ?? 0) ?>">
<div class="col-12"><label class="form-label">Tên địa điểm</label><input name="name" class="form-control" maxlength="150" required value="<?= h($editLocation['name'] ?? '') ?>"></div><div class="col-12"><label class="form-label">Khu vực chính</label><select name="area_location_id" class="form-select"><option value="">Chưa liên kết khu vực</option><?php foreach($areas as $area): ?><option value="<?= (int)$area['id'] ?>" <?= ((int)($editLocation['area_location_id']??0)===(int)$area['id'])?'selected':'' ?>><?= h($area['name'].($area['region']?' - '.$area['region']:'')) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Địa chỉ</label><input name="address" class="form-control" maxlength="255" value="<?= h($editLocation['address'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Loại</label><input name="category" class="form-control" maxlength="50" placeholder="tham quan, ăn uống..." value="<?= h($editLocation['category'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Trạng thái</label><select name="status" class="form-select"><option value="active" <?= (($editLocation['status']??'active')==='active')?'selected':'' ?>>Đang hoạt động</option><option value="hidden" <?= (($editLocation['status']??'')==='hidden')?'selected':'' ?>>Ẩn</option></select></div>
<div class="col-md-6"><label class="form-label">Latitude</label><input name="latitude" type="number" step="0.00000001" min="-90" max="90" class="form-control" value="<?= h($editLocation['latitude'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">Longitude</label><input name="longitude" type="number" step="0.00000001" min="-180" max="180" class="form-control" value="<?= h($editLocation['longitude'] ?? '') ?>"></div><div class="col-12"><label class="form-label">Mô tả</label><textarea name="description" class="form-control" rows="3" maxlength="5000"><?= h($editLocation['description'] ?? '') ?></textarea></div>
<div class="col-md-6"><label class="form-label">Nguồn dữ liệu</label><select name="location_source" class="form-select"><option value="user" <?= (($editLocation['location_source']??'user')==='user')?'selected':'' ?>>Người dùng nhập</option><option value="osm" <?= (($editLocation['location_source']??'')==='osm')?'selected':'' ?>>OpenStreetMap/Nominatim</option><option value="other" <?= (($editLocation['location_source']??'')==='other')?'selected':'' ?>>Nguồn khác</option></select></div><div class="col-md-6"><label class="form-label">Mã nguồn</label><input name="location_source_ref" class="form-control" maxlength="255" value="<?= h($editLocation['location_source_ref'] ?? '') ?>"></div><div class="col-12"><label class="form-label">URL nguồn dữ liệu</label><input name="source_url" type="url" class="form-control" value="<?= h($editLocation['source_url'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">URL hình ảnh</label><input name="image_url" type="url" class="form-control" value="<?= h($editLocation['image_url'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label">URL nguồn ảnh</label><input name="image_source_url" type="url" class="form-control" value="<?= h($editLocation['image_source_url'] ?? '') ?>"></div><div class="col-md-7"><label class="form-label">Ghi công ảnh</label><input name="image_attribution" class="form-control" value="<?= h($editLocation['image_attribution'] ?? '') ?>"></div><div class="col-md-5"><label class="form-label">Giấy phép ảnh</label><input name="image_license" class="form-control" value="<?= h($editLocation['image_license'] ?? '') ?>"></div><div class="col-12 d-flex gap-2"><button class="btn btn-success">Lưu</button><?php if($editLocation): ?><a class="btn btn-outline-secondary" href="admin-locations.php">Hủy</a><?php endif; ?></div></form></div></div>
<div class="col-xl-7"><div class="card p-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Địa điểm</th><th>Khu vực</th><th>Nguồn</th><th>Tọa độ</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php if(!$locations): ?><tr><td colspan="6" class="text-center text-muted py-4">Chưa có địa điểm cụ thể.</td></tr><?php endif; ?><?php foreach($locations as $location): ?><tr><td><strong><?= h($location['name']) ?></strong><br><small class="text-muted"><?= h($location['category']?:'Chưa phân loại') ?></small></td><td><?= h($location['area_name']?:'Chưa liên kết') ?><br><small class="text-muted"><?= h($location['address']?:'Chưa có địa chỉ') ?></small></td><td><?= h($location['location_source']) ?><br><small><?= (int)$location['activity_count'] ?> hoạt động</small></td><td><?= $location['latitude']!==null&&$location['longitude']!==null?h($location['latitude'].', '.$location['longitude']):'Chưa có' ?></td><td><?= $location['status']==='hidden'?'<span class="badge badge-hidden">Đã ẩn</span>':'<span class="badge badge-active">Đang hoạt động</span>' ?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="admin-locations.php?edit=<?= (int)$location['id'] ?>"><i class="bi bi-pencil"></i></a><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$location['id'] ?>"><input type="hidden" name="status" value="<?= $location['status']==='hidden'?'active':'hidden' ?>"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye<?= $location['status']==='hidden'?'':'-slash' ?>"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div></main></body></html>
