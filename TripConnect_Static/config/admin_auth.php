<?php
// Verify current database permissions, not just the role cached at login.
if(empty($_SESSION['user_id'])){header('Location: admin-login.php');exit;}
$adminPermissionQuery=$conn->prepare("SELECT role,status FROM users WHERE id=?");$adminPermissionQuery->execute([(int)$_SESSION['user_id']]);$adminPermission=$adminPermissionQuery->fetch(PDO::FETCH_ASSOC);
if(!$adminPermission||$adminPermission['role']!=='admin'||$adminPermission['status']!=='active'){http_response_code(403);exit('<meta charset="utf-8"><p data-i18n-ui>Không có quyền truy cập.</p><script src="../assets/i18n.js?v=10"></script>');}
$_SESSION['role']='admin';
function admin_text(string $key,int $max=150):string {
    $value=$_POST[$key]??'';
    if(!is_string($value)||mb_strlen(trim($value))>$max)throw new InvalidArgumentException('Dữ liệu '.$key.' không hợp lệ.');
    return trim($value);
}
function admin_token(string $scope):void {
    if(!is_string($_POST['csrf_token']??null)||!trip_verify_csrf($scope,$_POST['csrf_token']))throw new InvalidArgumentException('Phiên biểu mẫu không hợp lệ. Vui lòng tải lại trang.');
}
function admin_target(string $key='id'):int {
    $id=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if(!$id)throw new InvalidArgumentException('ID không hợp lệ.');return $id;
}
