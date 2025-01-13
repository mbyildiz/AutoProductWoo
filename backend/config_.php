<?php
define('SUPABASE_URL', 'https://test.supabase.co');
define('SUPABASE_KEY', 'asdasdasd');
define('WP_API_URL', 'https://test.domain.com/wp-json/wc/v3');
define('WP_CONSUMER_KEY', 'asdasd');
define('WP_CONSUMER_SECRET', 'asdasdasdasdasdasd');

// WordPress yönetici kullanıcı adı ve uygulama şifresi
define('WP_ADMIN_USER', 'admin'); // WordPress yönetici kullanıcı adınız
define('WP_APP_PASSWORD', '1111 2222 3333 4444 5555 666'); // Buraya oluşturduğunuz uygulama şifresini yazın

// Hata ayıklama modu
define('DEBUG_MODE', true);

// Hata loglama ayarları
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', 'debug.log');
ini_set('log_errors', 1);

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); 