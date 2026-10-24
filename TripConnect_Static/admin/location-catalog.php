<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/database.php';
require_once '../config/admin_auth.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: admin-login.php');
    exit();
}

$csrfToken = $_SESSION['admin_location_catalog_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['admin_location_catalog_csrf'] = $csrfToken;
$successMessage = '';
$errorMessage = '';

function catalogText(string $key, int $maxLength = 500): string
{
    $value=$_POST[$key]??'';if(!is_string($value)||mb_strlen(trim($value))>$maxLength)throw new InvalidArgumentException('Dữ liệu không hợp lệ: '.$key);return trim($value);
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
    $value=catalogText($key,50);
    if ($value === '') return null;
    if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
        throw new InvalidArgumentException('Tọa độ không hợp lệ.');
    }
    return number_format((float) $value, 8, '.', '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf_token']??null)||!hash_equals($_SESSION['admin_location_catalog_csrf'] ?? '', $_POST['csrf_token'])) {
            throw new RuntimeException('Phiên biểu mẫu không hợp lệ.');
        }
        $action=catalogText('action',30);$conn->beginTransaction();
        if($action==='area_save'||$action==='area_delete'){
            $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if($id===false)throw new InvalidArgumentException('ID khu vực không hợp lệ.');
            if($id){$q=$conn->prepare('SELECT id FROM locations WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Không tìm thấy khu vực.');}
            if($action==='area_save'){
                $name=catalogText('name',100);$region=catalogText('region',100);if($name==='')throw new InvalidArgumentException('Vui lòng nhập tên.');
                if($id)$conn->prepare('UPDATE locations SET name=?,region=? WHERE id=?')->execute([$name,$region?:null,$id]);else $conn->prepare('INSERT INTO locations(name,region) VALUES(?,?)')->execute([$name,$region?:null]);
            }else{
                if(!$id)throw new InvalidArgumentException('ID khu vực không hợp lệ.');$q=$conn->prepare('SELECT (SELECT COUNT(*) FROM trips WHERE location_id=?)+(SELECT COUNT(*) FROM location_catalog WHERE area_location_id=?)');$q->execute([$id,$id]);if($q->fetchColumn())throw new InvalidArgumentException('Khu vực đang được sử dụng, không thể xóa.');$conn->prepare('DELETE FROM locations WHERE id=?')->execute([$id]);
            }
            $successMessage='Đã lưu khu vực.';
        }elseif($action==='delete_location'){
            $id=admin_target();$q=$conn->prepare('SELECT id FROM location_catalog WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Không tìm thấy địa điểm.');
            $q=$conn->prepare('SELECT COUNT(*) FROM activities WHERE location_catalog_id=?');$q->execute([$id]);if($q->fetchColumn())throw new InvalidArgumentException('Địa điểm đang được sử dụng; hãy ẩn thay vì xóa.');$conn->prepare('DELETE FROM location_catalog WHERE id=?')->execute([$id]);$successMessage='Đã xóa địa điểm chưa được sử dụng.';
        }elseif ($action === 'toggle_status') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $status = (string) ($_POST['status'] ?? '');
            if (!$id || !in_array($status, ['active', 'hidden'], true)) throw new InvalidArgumentException('Trạng thái không hợp lệ.');
            $q=$conn->prepare('SELECT id FROM location_catalog WHERE id=? FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new InvalidArgumentException('Không tìm thấy địa điểm.');
            $stmt = $conn->prepare('UPDATE location_catalog SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
            $successMessage = 'Đã cập nhật trạng thái địa điểm.';
        } elseif ($action === 'save_location') {
            $rawId=catalogText('id',20);$id=$rawId===''||$rawId==='0'?null:filter_var($rawId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new InvalidArgumentException('ID không hợp lệ.');
            $existing=null;if($id){$q=$conn->prepare('SELECT * FROM location_catalog WHERE id=? FOR UPDATE');$q->execute([$id]);$existing=$q->fetch(PDO::FETCH_ASSOC);if(!$existing)throw new InvalidArgumentException('Không tìm thấy địa điểm.');}
            $areaId = filter_input(INPUT_POST, 'area_location_id', FILTER_VALIDATE_INT) ?: null;
            $name = catalogText('name', 150);
            $source = catalogText('location_source', 30) ?: 'user';
            $status = catalogText('status', 20) ?: 'active';
            if ($name === '' || !in_array($source, ['user', 'osm', 'other'], true) || !in_array($status, ['active', 'hidden'], true)) {
                throw new InvalidArgumentException('Tên, nguồn hoặc trạng thái không hợp lệ.');
            }
            $sourceRef = catalogText('location_source_ref', 255) ?: null;
            if ($source === 'user') $sourceRef = null;
            $lat=catalogCoordinate('latitude',-90,90);$lng=catalogCoordinate('longitude',-180,180);if(($lat===null)!==($lng===null))throw new InvalidArgumentException('Nhập đủ hai tọa độ hoặc để trống cả hai.');
            $verified=$existing['location_verified_at']??null;if($existing&&($lat!=$existing['latitude']||$lng!=$existing['longitude']||$source!==$existing['location_source']||$sourceRef!==$existing['location_source_ref']))$verified=null;
            $params = [
                $areaId, $name, catalogText('address', 255) ?: null, catalogText('description', 5000) ?: null,
                catalogText('category', 50) ?: null, $lat,$lng,
                catalogUrl('image_url'), catalogUrl('image_source_url'), catalogText('image_attribution', 500) ?: null,
                catalogText('image_license', 100) ?: null, $source, $sourceRef, catalogUrl('source_url'), $verified, $status
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
        $conn->commit();
    } catch (PDOException $e) {
        if($conn->inTransaction())$conn->rollBack();
        $errorMessage = $e->getCode() === '23000' ? 'Mã nguồn địa điểm đã tồn tại hoặc khu vực không hợp lệ.' : 'Không thể lưu địa điểm vào cơ sở dữ liệu.';
    } catch (Throwable $e) {
        if($conn->inTransaction())$conn->rollBack();
        $errorMessage = $e->getMessage();
    }
}

$editLocation = null;
if (is_string($_GET['edit']??null) && ctype_digit($_GET['edit'])) {
    $stmt = $conn->prepare('SELECT * FROM location_catalog WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editLocation = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$areas = $conn->query('SELECT id, name, region FROM locations ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$locations = $conn->query('SELECT lc.*, l.name AS area_name, l.region AS area_region, (SELECT COUNT(*) FROM activities a WHERE a.location_catalog_id=lc.id) AS activity_count FROM location_catalog lc LEFT JOIN locations l ON l.id=lc.area_location_id ORDER BY lc.updated_at DESC, lc.id DESC')->fetchAll(PDO::FETCH_ASSOC);
function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title data-i18n-ui>Admin - Địa điểm cụ thể</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"><link rel="stylesheet" href="../assets/style.css">
<style>body{margin:0;background:#f5f8f7;color:#173232;font-family:var(--font-body)}.sidebar{position:fixed;width:220px;height:100vh;background:#073b3a;color:#fff;padding:20px 12px}.brand{font-size:17px;font-weight:600;margin-bottom:25px}.menu{display:block;color:#d5e5e3;text-decoration:none;padding:10px 12px;border-radius:7px;margin:4px 0;font-size:14px}.menu:hover,.menu.active{background:#0d7771;color:#fff}.main{margin-left:220px}.topbar{height:62px;background:#fff;border-bottom:1px solid #ddd;display:flex;justify-content:flex-end;align-items:center;padding:0 25px}.content{padding:25px}.card{border:0;box-shadow:0 2px 8px #00000008}table{font-size:13px}.badge-active{background:#d1e7dd;color:#0f5132}.badge-hidden{background:#f8d7da;color:#842029}@media(max-width:900px){.sidebar{position:static;width:100%;height:auto}.main{margin-left:0}}input,select,textarea{font:inherit;max-width:100%}details form{min-width:200px}</style></head><body>
<aside class="sidebar"><div class="brand"><i class="bi bi-compass"></i> TripConnect Admin</div>
<a class="menu" href="admin-dashboard.php"><i class="bi bi-speedometer2 me-2"></i><span data-i18n-ui>Dashboard</span></a>
<a class="menu" href="admin-reports.php"><i class="bi bi-bar-chart me-2"></i><span data-i18n-ui>Báo cáo</span></a>
<a class="menu" href="admin-users.php"><i class="bi bi-people me-2"></i><span data-i18n-ui>Người dùng</span></a>
<a class="menu" href="admin-trips.php"><i class="bi bi-map me-2"></i><span data-i18n-ui>Lịch trình</span></a>
<a class="menu" href="admin-comments.php"><i class="bi bi-chat-left-text me-2"></i><span data-i18n-ui>Bình luận</span></a>
<a class="menu" href="admin-tags.php"><i class="bi bi-tags me-2"></i><span data-i18n-ui>Tag </span>&amp;<span data-i18n-ui> sở thích</span></a>
<a class="menu" href="location-catalog.php"><span data-i18n-ui>Địa điểm</span></a>
<a class="menu" href="admin-logs.php"><i class="bi bi-journal-text me-2"></i><span data-i18n-ui>Nhật ký</span></a>
<a class="menu" href="admin-settings.php"><i class="bi bi-gear me-2"></i><span data-i18n-ui>Cài đặt</span></a>
<form method="post" action="../logout.php" class="d-inline"><input type="hidden" name="logout_csrf" value="<?= htmlspecialchars(trip_csrf_token('logout'), ENT_QUOTES, 'UTF-8') ?>"><button type="submit" class="menu mt-4 border-0 bg-transparent w-100 text-start"><i class="bi bi-box-arrow-right me-2"></i><span data-i18n-ui>Đăng xuất</span></button></form></aside>
<main class="main"><header class="topbar"><span><i class="bi bi-person-circle me-1"></i><?= h($_SESSION['full_name'] ?? 'Admin') ?></span></header><div class="content">
<?php if ($successMessage): ?><div class="alert alert-success"><?= h($successMessage) ?></div><?php endif; ?><?php if ($errorMessage): ?><div class="alert alert-danger"><?= h($errorMessage) ?></div><?php endif; ?>
<div class="mb-4"><h4 class="mb-1"><span data-i18n-ui>Địa điểm cụ thể</span></h4><p class="text-muted mb-0"><span data-i18n-ui>Quản lý điểm tham quan, ăn uống, lưu trú và tiện ích có nguồn dữ liệu.</span></p></div><div class="alert alert-info small"><span data-i18n-ui>Khu vực chính trong </span><code>locations</code><span data-i18n-ui> vẫn được giữ nguyên. Dữ liệu tại đây là địa điểm cụ thể trong </span><code>location_catalog</code><span data-i18n-ui>; không tự đánh dấu xác minh.</span></div>
<details class="card p-3 my-3"><summary><span data-i18n-ui>Quản lý khu vực / điểm đến</span></summary>
<form method="post" class="d-flex flex-wrap gap-2 mt-3"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="area_save"><input type="hidden" name="id" value="0"><input name="name" aria-label="Area name" maxlength="100" placeholder="Tên khu vực" required data-i18n-attrs><input name="region" aria-label="Region" maxlength="100" placeholder="Tỉnh / vùng" data-i18n-attrs><button class="btn btn-primary"><span data-i18n-ui>Thêm khu vực</span></button></form>
<?php foreach($areas as $area): ?><form method="post" class="d-flex flex-wrap gap-2 mt-3"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int)$area['id'] ?>"><input name="name" aria-label="Area name" maxlength="100" required value="<?= h($area['name']) ?>" data-i18n-attrs><input name="region" aria-label="Region" maxlength="100" value="<?= h($area['region']) ?>" data-i18n-attrs><button name="action" value="area_save" class="btn btn-outline-primary"><span data-i18n-ui>Lưu</span></button><button name="action" value="area_delete" class="btn btn-outline-danger"><span data-i18n-ui>Xóa nếu chưa dùng</span></button></form><?php endforeach; ?></details>
<div class="row g-4"><div class="col-xl-5"><div class="card p-4"><h5><span data-i18n-ui><?= $editLocation ? 'Chỉnh sửa địa điểm' : 'Thêm địa điểm cụ thể' ?></span></h5><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="save_location"><input type="hidden" name="id" value="<?= (int) ($editLocation['id'] ?? 0) ?>">
<div class="col-12"><label class="form-label"><span data-i18n-ui>Tên địa điểm</span></label><input name="name" class="form-control" maxlength="150" required value="<?= h($editLocation['name'] ?? '') ?>"></div><div class="col-12"><label class="form-label"><span data-i18n-ui>Khu vực chính</span></label><select name="area_location_id" class="form-select"><option value="" data-i18n-ui>Chưa liên kết khu vực</option><?php foreach($areas as $area): ?><option value="<?= (int)$area['id'] ?>" <?= ((int)($editLocation['area_location_id']??0)===(int)$area['id'])?'selected':'' ?>><?= h($area['name'].($area['region']?' - '.$area['region']:'')) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label"><span data-i18n-ui>Địa chỉ</span></label><input name="address" class="form-control" maxlength="255" value="<?= h($editLocation['address'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label"><span data-i18n-ui>Loại</span></label><input name="category" class="form-control" maxlength="50" placeholder="tham quan, ăn uống..." value="<?= h($editLocation['category'] ?? '') ?>" data-i18n-attrs></div><div class="col-md-6"><label class="form-label"><span data-i18n-ui>Trạng thái</span></label><select name="status" class="form-select"><option value="active" <?= (($editLocation['status']??'active')==='active')?'selected':'' ?> data-i18n-ui>Đang hoạt động</option><option value="hidden" <?= (($editLocation['status']??'')==='hidden')?'selected':'' ?> data-i18n-ui>Ẩn</option></select></div>
<div class="col-md-6"><label class="form-label"><span data-i18n-ui>Latitude</span></label><input name="latitude" type="number" step="0.00000001" min="-90" max="90" class="form-control" value="<?= h($editLocation['latitude'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label"><span data-i18n-ui>Longitude</span></label><input name="longitude" type="number" step="0.00000001" min="-180" max="180" class="form-control" value="<?= h($editLocation['longitude'] ?? '') ?>"></div><div class="col-12"><label class="form-label"><span data-i18n-ui>Mô tả</span></label><textarea name="description" class="form-control" rows="3" maxlength="5000"><?= h($editLocation['description'] ?? '') ?></textarea></div>
<div class="col-md-6"><label class="form-label"><span data-i18n-ui>Nguồn dữ liệu</span></label><select name="location_source" class="form-select"><option value="user" <?= (($editLocation['location_source']??'user')==='user')?'selected':'' ?> data-i18n-ui>Người dùng nhập</option><option value="osm" <?= (($editLocation['location_source']??'')==='osm')?'selected':'' ?> data-i18n-ui>OpenStreetMap/Nominatim</option><option value="other" <?= (($editLocation['location_source']??'')==='other')?'selected':'' ?> data-i18n-ui>Nguồn khác</option></select></div><div class="col-md-6"><label class="form-label"><span data-i18n-ui>Mã nguồn</span></label><input name="location_source_ref" class="form-control" maxlength="255" value="<?= h($editLocation['location_source_ref'] ?? '') ?>"></div><div class="col-12"><label class="form-label"><span data-i18n-ui>URL nguồn dữ liệu</span></label><input name="source_url" type="url" class="form-control" value="<?= h($editLocation['source_url'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label"><span data-i18n-ui>URL hình ảnh</span></label><input name="image_url" type="url" class="form-control" value="<?= h($editLocation['image_url'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label"><span data-i18n-ui>URL nguồn ảnh</span></label><input name="image_source_url" type="url" class="form-control" value="<?= h($editLocation['image_source_url'] ?? '') ?>"></div><div class="col-md-7"><label class="form-label"><span data-i18n-ui>Ghi công ảnh</span></label><input name="image_attribution" class="form-control" value="<?= h($editLocation['image_attribution'] ?? '') ?>"></div><div class="col-md-5"><label class="form-label"><span data-i18n-ui>Giấy phép ảnh</span></label><input name="image_license" class="form-control" value="<?= h($editLocation['image_license'] ?? '') ?>"></div><div class="col-12 d-flex gap-2"><button class="btn btn-success"><span data-i18n-ui>Lưu</span></button><?php if($editLocation): ?><a class="btn btn-outline-secondary" href="location-catalog.php"><span data-i18n-ui>Hủy</span></a><?php endif; ?></div></form></div></div>
<div class="col-xl-7"><div class="card p-3"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th><span data-i18n-ui>Địa điểm</span></th><th><span data-i18n-ui>Khu vực</span></th><th><span data-i18n-ui>Nguồn</span></th><th><span data-i18n-ui>Tọa độ</span></th><th><span data-i18n-ui>Trạng thái</span></th><th></th></tr></thead><tbody><?php if(!$locations): ?><tr><td colspan="6" class="text-center text-muted py-4"><span data-i18n-ui>Chưa có địa điểm cụ thể.</span></td></tr><?php endif; ?><?php foreach($locations as $location): ?><tr><td><strong><?= h($location['name']) ?></strong><br><small class="text-muted"><?= h($location['category']?:'Chưa phân loại') ?></small></td><td><?= h($location['area_name']?:'Chưa liên kết') ?><br><small class="text-muted"><?= h($location['address']?:'Chưa có địa chỉ') ?></small></td><td><?= h($location['location_source']) ?><br><small><?= (int)$location['activity_count'] ?><span data-i18n-ui> hoạt động</span></small></td><td><?= $location['latitude']!==null&&$location['longitude']!==null?h($location['latitude'].', '.$location['longitude']):'Chưa có' ?></td><td><?= $location['status']==='hidden'?'<span class="badge badge-hidden" data-i18n-ui>Đã ẩn</span>':'<span class="badge badge-active" data-i18n-ui>Đang hoạt động</span>' ?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="location-catalog.php?edit=<?= (int)$location['id'] ?>"><i class="bi bi-pencil"></i></a><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$location['id'] ?>"><input type="hidden" name="status" value="<?= $location['status']==='hidden'?'active':'hidden' ?>"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye<?= $location['status']==='hidden'?'':'-slash' ?>"></i></button></form><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>"><input type="hidden" name="action" value="delete_location"><input type="hidden" name="id" value="<?= (int)$location['id'] ?>"><button class="btn btn-sm btn-outline-danger"><span data-i18n-ui>Xóa</span></button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div></main><script src="../assets/i18n.js?v=10"></script>
</body></html>
