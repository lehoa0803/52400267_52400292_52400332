<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';

$trip_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($trip_id <= 0) {
    header('Location: profile.php');
    exit();
}

// Lấy danh sách địa điểm
try {
    $stmtLocations = $conn->query("SELECT id, name FROM locations ORDER BY name ASC");
    $locationsList = $stmtLocations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $locationsList = [];
}

// Lấy thông tin chuyến đi cũ
try {
    $stmtTrip = $conn->prepare("SELECT * FROM trips WHERE id = ? AND user_id = ?");
    $stmtTrip->execute([$trip_id, $user_id]);
    $trip = $stmtTrip->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        header('Location: profile.php');
        exit();
    }

    // Lấy danh sách các ngày và hoạt động cũ
    $stmtDays = $conn->prepare("SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number ASC");
    $stmtDays->execute([$trip_id]);
    $tripDays = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

    $existingActivities = [];
    $existingDayNotes = [];
    $existingDayTitles = [];
    foreach ($tripDays as $day) {
        $dNum = $day['day_number'];
        $existingDayNotes['day_' . $dNum] = $day['note'] ?? '';
        // Lưu lại tiêu đề gốc của ngày để không bị lặp chữ "Ngày X: Ngày X"
        $existingDayTitles['day_' . $dNum] = $day['title'] ?? "Khám phá trải nghiệm";

        $stmtActs = $conn->prepare("SELECT * FROM activities WHERE trip_day_id = ? ORDER BY start_time ASC");
        $stmtActs->execute([$day['id']]);
        $acts = $stmtActs->fetchAll(PDO::FETCH_ASSOC);
        
        $existingActivities['day_' . $dNum] = [];
        foreach ($acts as $act) {
            $existingActivities['day_' . $dNum][] = [
                'time' => substr($act['start_time'], 0, 5),
                'title' => $act['title'],
                'location' => $act['location_name'],
                'cost' => $act['cost'],
                'category' => $act['category']
            ];
        }
    }
} catch (PDOException $e) {
    die("Lỗi cơ sở dữ liệu: " . $e->getMessage());
}

// Xử lý khi bấm nút Cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['tripName'] ?? '');
    $location_id = intval($_POST['tripLocation'] ?? 0);
    $days_count  = intval($_POST['tripDays'] ?? 1);
    $budget      = floatval($_POST['tripBudget'] ?? 0);
    $status      = strtolower(trim($_POST['tripStatus'] ?? 'draft')); 

    // Lấy lại ảnh cũ làm mặc định
    $cover_image = $trip['cover_image'] ?? '';

    // Xử lý upload ảnh bìa mới (báo lỗi rõ ràng thay vì bỏ qua âm thầm)
    if (isset($_FILES['coverInput']) && $_FILES['coverInput']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['coverInput'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Ảnh vượt quá giới hạn upload_max_filesize của server (' . ini_get('upload_max_filesize') . ').',
                UPLOAD_ERR_FORM_SIZE  => 'Ảnh vượt quá giới hạn của form.',
                UPLOAD_ERR_PARTIAL    => 'Ảnh chỉ được tải lên một phần.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server thiếu thư mục tạm.',
                UPLOAD_ERR_CANT_WRITE => 'Server không ghi được file.',
            ];
            $error_message = $uploadErrors[$file['error']] ?? 'Lỗi upload không xác định.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (!in_array($ext, $allowedExt, true) || @getimagesize($file['tmp_name']) === false) {
                $error_message = 'File không phải ảnh hợp lệ (jpg, png, webp, gif).';
            } else {
                $uploadFileDir = __DIR__ . '/assets/uploads/'; // đường dẫn tuyệt đối trên đĩa
                if (!is_dir($uploadFileDir) && !mkdir($uploadFileDir, 0755, true)) {
                    $error_message = 'Không tạo được thư mục: ' . $uploadFileDir;
                } elseif (!is_writable($uploadFileDir)) {
                    $error_message = 'Thư mục không có quyền ghi: ' . $uploadFileDir;
                } else {
                    $newFileName = md5(uniqid('', true)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadFileDir . $newFileName)) {
                        $cover_image = 'assets/uploads/' . $newFileName; // đường dẫn web lưu vào DB
                    } else {
                        $error_message = 'Không lưu được ảnh lên server.';
                    }
                }
            }
        }
    }

    if (empty($error_message) && !empty($title) && $location_id > 0) {
        try {
            $conn->beginTransaction();

            // 1. Cập nhật bảng trips kèm ảnh bìa mới
            $stmtUpdate = $conn->prepare("UPDATE trips SET location_id = ?, title = ?, days_count = ?, budget_estimate = ?, cover_image = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmtUpdate->execute([$location_id, $title, $days_count, $budget, $cover_image, $status, $trip_id, $user_id]);

            // 2. Xóa các hoạt động và ngày cũ
            $conn->exec("DELETE FROM activities WHERE trip_day_id IN (SELECT id FROM trip_days WHERE trip_id = $trip_id)");
            $conn->exec("DELETE FROM trip_days WHERE trip_id = $trip_id");

            // 3. Đọc dữ liệu JSON timeline mới
            $activities_json =$_POST['activities_json'] ?? '[]';
            $activities_data = json_decode($activities_json, true);

            // 4. Lưu lại các ngày và hoạt động mới (chuẩn hóa tiêu đề sạch sẽ)
            for ($d = 1; $d <=$days_count; $d++) {$dayTitle = $_POST['day_title_' . $d] ?? ($existingDayTitles['day_' . $d] ?? 'Khám phá trải nghiệm');
                // Loại bỏ mọi tiền tố "Ngày X:" (kể cả bị lặp) rồi lưu tiêu đề SẠCH, không kèm "Ngày X:"
                $dayTitle = trim(preg_replace('/^(?:Ngày\s*\d+\s*[:\-]?\s*)+/ui', '', $dayTitle));
                if ($dayTitle === '') { $dayTitle = 'Khám phá trải nghiệm'; }
                $finalDayTitle = $dayTitle;

                $dayNote  = $_POST['day_desc_' .$d] ?? "Lịch trình chi tiết cho ngày $d.";

                $stmtDay =$conn->prepare("INSERT INTO trip_days (trip_id, day_number, title, note) VALUES (?, ?, ?, ?)");
                $stmtDay->execute([$trip_id,$d, $finalDayTitle,$dayNote]);
                $trip_day_id =$conn->lastInsertId();

                if (isset($activities_data['day_' .$d]) && is_array($activities_data['day_' .$d])) {
                    foreach ($activities_data['day_' .$d] as $act) {$time = !empty($act['time']) ?$act['time'] . ':00' : '08:00:00';
                        $actTitle =$act['title'] ?? 'Hoạt động';
                        $actLoc =$act['location'] ?? 'Địa điểm';
                        $actCost = floatval($act['cost'] ?? 0);
                        $actCategory =$act['category'] ?? 'Khác';

                        $stmtAct =$conn->prepare("INSERT INTO activities (trip_day_id, start_time, title, location_name, description, cost, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmtAct->execute([$trip_day_id, $time,$actTitle, $actLoc,$actTitle, $actCost,$actCategory]);
                    }
                }
            }

            $conn->commit();

            // Cập nhật xong thì chuyển thẳng sang trang chi tiết chuyến đi
            header("Location: trip-detail.php?id=" . $trip_id);
            exit();
        } catch (Exception $e) {
            $conn->rollBack();$error_message = "Lỗi khi cập nhật: " . $e->getMessage();
        }
    } elseif (empty($error_message)) {
        $error_message = "Vui lòng nhập tên chuyến đi và chọn địa điểm hợp lệ!";
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chỉnh sửa lịch trình | TripConnect</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A7%AD%3C/text%3E%3C/svg%3E">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
 <div class="container py-2">
  <a class="navbar-brand fs-4 d-flex align-items-center" href="index.php"><span class="brand-mark"><i class="bi bi-compass"></i></span>TripConnect<span class="brand-dot"></span></a>
  <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="mainNav">
   <ul class="navbar-nav mx-auto gap-lg-2">
    <li class="nav-item"><a class="nav-link" href="index.php">Trang chủ</a></li>
    <li class="nav-item"><a class="nav-link" href="explore.php">Khám phá</a></li>
    <li class="nav-item"><a class="nav-link" href="profile.php">Hồ sơ</a></li>
   </ul>
   <div class="d-flex gap-2 align-items-center">
    <span class="text-secondary small">Xin chào, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Thành viên'); ?></strong></span>
    <a class="btn btn-outline-danger btn-sm px-3 rounded-pill" href="logout.php">Đăng xuất</a>
   </div>
  </div>
 </div>
</nav>

<main class="container py-4">
 <div class="page-header">
     <div class="small-label">Trip Builder</div>
     <h1 class="section-title">Chỉnh sửa lịch trình</h1>
     <p class="text-muted">Cập nhật thông tin chi tiết và timeline cho chuyến đi của bạn.</p>
 </div>

 <?php if (!empty($success_message)): ?>
     <div class="alert alert-success" role="alert"><i class="bi bi-check-circle"></i> <?php echo $success_message; ?></div>
 <?php endif; ?>

 <?php if (!empty($error_message)): ?>
     <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?></div>
 <?php endif; ?>

<form method="POST" action="edit-trip.php?id=<?php echo $trip_id; ?>" enctype="multipart/form-data" id="tripForm"> <input type="hidden" name="activities_json" id="activitiesJson">
 <div id="dayDescInputsContainer"></div>

 <div class="row g-4">
  <div class="col-lg-8">
   <!-- 1. Thông tin tổng quan -->
   <div class="card p-4 mb-4">
       <h5>1. Thông tin tổng quan</h5>
       <div class="row g-3 mt-1">
        <div class="col-md-8">
            <label class="form-label" for="tripName">Tên chuyến đi</label>
            <input id="tripName" name="tripName" required class="form-control" value="<?php echo htmlspecialchars($trip['title']); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripLocation">Địa điểm</label>
            <select id="tripLocation" name="tripLocation" class="form-select" required>
                <option value="">-- Chọn địa điểm --</option>
                <?php foreach ($locationsList as$loc): ?>
                    <option value="<?php echo $loc['id']; ?>" <?php echo ($loc['id'] ==$trip['location_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripDays">Số ngày</label>
            <input id="tripDays" name="tripDays" type="number" min="1" max="15" class="form-control" value="<?php echo intval($trip['days_count']); ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripBudget">Ngân sách dự kiến</label>
            <input id="tripBudget" name="tripBudget" type="number" min="0" class="form-control" value="<?php echo floatval($trip['budget_estimate']); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tripStatus">Trạng thái</label>
            <select id="tripStatus" name="tripStatus" class="form-select">
                <option value="draft" <?php echo ($trip['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                <option value="published" <?php echo ($trip['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="coverInput">Ảnh bìa</label>
            <input id="coverInput" name="coverInput" type="file" accept="image/*" class="form-control">
        </div>
       </div>
   </div>

   <!-- 2. Hoạt động trong ngày -->
   <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">2. Hoạt động trong ngày</h5>
        <div class="d-flex align-items-center gap-2">
         <button type="button" id="dayMinus" class="btn btn-sm btn-light"><i class="bi bi-dash"></i></button>
         <span class="badge bg-success px-3 py-2 fs-6" id="dayBadge">Day 1</span>
         <button type="button" id="dayPlus" class="btn btn-sm btn-light"><i class="bi bi-plus"></i></button>
        </div>
    </div>

    <div class="mt-3">
        <label class="form-label fw-semibold" id="dayDescLabel">Mô tả tổng quan Day 1</label>
        <input type="text" id="currentDayDescInput" class="form-control" placeholder="VD: Ngày đầu tiên check-in...">
    </div>

    <div class="row g-3 mt-2">
     <div class="col-md-3"><label class="form-label">Thời gian</label><input id="activityTime" type="time" class="form-control"></div>
     <div class="col-md-4"><label class="form-label">Tên hoạt động</label><input id="activityName" class="form-control" placeholder="VD: Ăn sáng"></div>
     <div class="col-md-3"><label class="form-label">Địa điểm</label><input id="activityPlace" class="form-control" placeholder="VD: Quán ăn"></div>
     <div class="col-md-2"><label class="form-label">Chi phí</label><input id="activityCost" type="number" min="0" class="form-control" placeholder="50000"></div>
     
     <div class="col-md-4">
         <label class="form-label">Phân loại chi phí</label>
         <select id="activityCategory" class="form-select">
             <option value="Ăn uống">Ăn uống</option>
             <option value="Tham quan">Tham quan</option>
             <option value="Khác">Khác / Di chuyển</option>
         </select>
     </div>
     <div class="col-md-8 d-flex align-items-end">
         <button type="button" id="btnAddActivity" class="btn btn-success px-4"><i class="bi bi-plus-lg"></i> Thêm hoạt động</button>
     </div>
    </div>
    <hr class="my-4">
    <div id="activityList" class="timeline"></div>
   </div>
  </div>

  <div class="col-lg-4">
   <div class="sticky-side">
    <div class="card p-4">
        <h6>Ảnh bìa xem trước</h6>
        <div class="empty-preview mt-2" id="previewPlaceholder">
            <?php if (!empty($trip['cover_image'])): ?>
                <img src="<?php echo htmlspecialchars($trip['cover_image']); ?>" class="w-100 rounded" style="max-height:160px; object-fit:cover;" alt="Ảnh bìa">
            <?php else: ?>
                <i class="bi bi-image fs-1"></i><p class="mb-0 mt-2">Chưa chọn ảnh</p>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-4"><i class="bi bi-save"></i> Cập nhật lịch trình</button>
        <a href="profile.php" class="btn btn-outline-secondary w-100 mt-2">Quay lại hồ sơ</a>
    </div>
    <div class="card p-4 mt-4">
        <h6>Checklist</h6>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Thông tin chuyến đi</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Timeline hoạt động</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Ngân sách</label></div>
    </div>
   </div>
  </div>
 </div>
 </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentDay = 1;
let maxDays = parseInt(document.getElementById('tripDays').value) || 3;
let tripActivities = <?php echo json_encode($existingActivities); ?>;
let dayDescriptions = <?php echo json_encode($existingDayNotes); ?>;

const tripDaysInput = document.getElementById('tripDays');
const dayBadge = document.getElementById('dayBadge');
const dayDescLabel = document.getElementById('dayDescLabel');
const currentDayDescInput = document.getElementById('currentDayDescInput');

// Khởi tạo hiển thị
updateDayView();

tripDaysInput.addEventListener('input', function() {
    maxDays = parseInt(this.value) || 1;
    if (currentDay > maxDays) {
        currentDay = maxDays;
        updateDayView();
    }
});

document.getElementById('dayPlus').addEventListener('click', function() {
    if (currentDay < maxDays) {
        saveCurrentDayDesc();
        currentDay++;
        updateDayView();
    }
});

document.getElementById('dayMinus').addEventListener('click', function() {
    if (currentDay > 1) {
        saveCurrentDayDesc();
        currentDay--;
        updateDayView();
    }
});

function saveCurrentDayDesc() {
    dayDescriptions['day_' + currentDay] = currentDayDescInput.value;
}

function updateDayView() {
    dayBadge.innerText = 'Day ' + currentDay;
    dayDescLabel.innerText = 'Mô tả tổng quan Day ' + currentDay;
    currentDayDescInput.value = dayDescriptions['day_' + currentDay] || '';
    renderActivities();
}

currentDayDescInput.addEventListener('input', function() {
    dayDescriptions['day_' + currentDay] = this.value;
});

// Xử lý xem trước ảnh khi chọn file mới
document.getElementById('coverInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewPlaceholder').innerHTML = `<img src="${e.target.result}" class="w-100 rounded" style="max-height:160px; object-fit:cover;" alt="Ảnh xem trước">`;
        }
        reader.readAsDataURL(file);
    }
});

document.getElementById('btnAddActivity').addEventListener('click', function() {
    let time = document.getElementById('activityTime').value;
    let title = document.getElementById('activityName').value;
    let location = document.getElementById('activityPlace').value;
    let cost = document.getElementById('activityCost').value;
    let category = document.getElementById('activityCategory').value;

    if (!title) {
        alert('Vui lòng nhập tên hoạt động!');
        return;
    }

    if (!tripActivities['day_' + currentDay]) {
        tripActivities['day_' + currentDay] = [];
    }

    tripActivities['day_' + currentDay].push({ time, title, location, cost, category });

    document.getElementById('activityTime').value = '';
    document.getElementById('activityName').value = '';
    document.getElementById('activityPlace').value = '';
    document.getElementById('activityCost').value = '';

    renderActivities();
});

function renderActivities() {
    let listEl = document.getElementById('activityList');
    let activities = tripActivities['day_' + currentDay] || [];

    if (activities.length === 0) {
        listEl.innerHTML = '<p class="text-muted fst-italic">Chưa có hoạt động nào được thêm cho ngày này.</p>';
        return;
    }

    let html = '';
    activities.forEach((act, index) => {
        html += `<div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <div>
                <strong>${act.time || '--:--'}</strong> - <span class="fw-semibold">${act.title}</span> 
                <span class="text-muted">(${act.location || 'N/A'})</span>
                <br><span class="badge bg-secondary">${act.category}</span> <span class="text-success fw-bold">${Number(act.cost || 0).toLocaleString()}đ</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeActivity(${index})"><i class="bi bi-trash"></i></button>
        </div>`;
    });
    listEl.innerHTML = html;
}

function removeActivity(index) {
    tripActivities['day_' + currentDay].splice(index, 1);
    renderActivities();
}

document.getElementById('tripForm').addEventListener('submit', function(e) {
    saveCurrentDayDesc();
    document.getElementById('activitiesJson').value = JSON.stringify(tripActivities);

    let container = document.getElementById('dayDescInputsContainer');
    container.innerHTML = '';
    for (let d = 1; d <= maxDays; d++) {
        let desc = dayDescriptions['day_' + d] || '';
        let input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'day_desc_' + d;
        input.value = desc;
        container.appendChild(input);
    }
});
</script>
</body>
</html>