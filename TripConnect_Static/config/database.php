<?php
$host = 'localhost';
$db_name = 'tripconnect'; 
$username = 'root';       
$password = '';           

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // BỎ DẤU // Ở ĐÂY ĐỂ IN RA MÀN HÌNH TEST
} catch(PDOException $exception) {
    echo "Lỗi kết nối cơ sở dữ liệu: " . $exception->getMessage();
    exit();
}
?>