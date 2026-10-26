<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/config/trip_social.php';
header('Content-Type: application/json; charset=utf-8');
function social_reply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');social_reply(405,['ok'=>false,'message'=>'Chỉ chấp nhận POST.']);}
if(empty($_SESSION['user_id']))social_reply(401,['ok'=>false,'message'=>'Vui lòng đăng nhập.','redirect'=>'login.php']);
if(!is_string($_POST['csrf_token']??null)||!trip_verify_csrf('social',$_POST['csrf_token']))social_reply(403,['ok'=>false,'message'=>'Phiên thao tác hết hạn. Vui lòng tải lại trang.']);
try{
    $action=$_POST['action']??'';if(!is_string($action))throw new InvalidArgumentException('Thao tác không hợp lệ.');
    social_reply(200,['ok'=>true]+trip_social_action($conn,(int)$_SESSION['user_id'],$action,$_POST));
}catch(InvalidArgumentException $e){social_reply(422,['ok'=>false,'message'=>$e->getMessage()]);}
catch(PDOException $e){error_log('[TripConnect social] database operation failed');social_reply(503,['ok'=>false,'message'=>'Không thể lưu thao tác. Vui lòng thử lại.']);}
catch(RuntimeException $e){$code=in_array($e->getCode(),[403,404],true)?$e->getCode():409;social_reply($code,['ok'=>false,'message'=>$e->getMessage()]);}
