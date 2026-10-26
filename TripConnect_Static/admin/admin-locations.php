<?php
session_start();
require_once '../config/database.php';
require_once '../config/admin_auth.php';
header('Location: location-catalog.php');
exit;
