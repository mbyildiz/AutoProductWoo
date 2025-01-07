<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// URL'den endpoint'i ayıkla
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('?', $request_uri);
$path = trim($path_parts[0], '/');

// Debug için path bilgisini yazdır
error_log("Request URI: " . $request_uri);
error_log("Path: " . $path);

// İstek tipine göre yönlendirme yap
if (empty($path) || $path === 'index.php') {
    require_once 'index.php';
} else {
    require_once 'api.php';
} 