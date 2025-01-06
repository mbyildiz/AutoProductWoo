<?php
require_once 'config.php';
require_once 'WooCommerceAPI.php';
require_once 'SupabaseDB.php';
require_once 'HepsiBurada.php';

// Debug için hata raporlamayı açalım
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Hata işleme
function handleError($e) {
    error_log("Hata oluştu: " . $e->getMessage());
    error_log("Hata yığını: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}

// JSON yanıt gönderme
function sendResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

try {
    // Debug bilgisi
    error_log("Script started");
    error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
    error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    
    $woocommerce = new WooCommerceAPI();
    $supabase = new SupabaseDB();
    $hepsiburada = new HepsiBuradaAPI();

    // İstek metodunu al
    $method = $_SERVER['REQUEST_METHOD'];
    
    // URL'den endpoint'i ayıkla
    $request_uri = $_SERVER['REQUEST_URI'];
    $path_parts = explode('?', $request_uri);
    $path = trim($path_parts[0], '/');
    
    // Debug için path bilgisini yazdır
    error_log("Request URI: " . $request_uri);
    error_log("Path: " . $path);

    // OPTIONS isteklerini yanıtla
    if ($method === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // POST /products - Ürün ekleme
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        error_log("POST Data: " . json_encode($data));
        
        // Supabase'e kaydet
        $supabaseResult = $supabase->insertProduct($data);
        
        // WordPress'e ekle
        $wpResult = $woocommerce->addProduct([
            'name' => $data['name'],
            'type' => 'simple',
            'regular_price' => (string)$data['price'],
            'description' => $data['description'],
            'short_description' => $data['description'],
            'images' => [
                ['src' => $data['image_url']]
            ],
            'stock_quantity' => $data['stock']
        ]);
        
        sendResponse([
            'supabase' => $supabaseResult,
            'wordpress' => $wpResult
        ]);
    }

    // GET /products - Ürünleri listeleme
    else if ($method === 'GET') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        
        $supabaseProducts = $supabase->getProducts($page, $per_page);
        $wpProducts = $woocommerce->getProducts($page, $per_page);
        
        sendResponse([
            'supabase_products' => $supabaseProducts,
            'wordpress_products' => $wpProducts
        ]);
    }

    // GET /hepsiburada/search - HepsiBurada'da ürün arama
    if ($method === 'GET' && strpos($path, 'hepsiburada/search') === 0) {
        $search_term = isset($_GET['q']) ? $_GET['q'] : '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        
        if (empty($search_term)) {
            http_response_code(400);
            sendResponse(['error' => 'Arama terimi gerekli']);
        }
        
        $results = $hepsiburada->search($search_term, $page);
        sendResponse($results);
    }
    
    // POST /hepsiburada/import - HepsiBurada'dan ürünü içe aktar
    else if ($method === 'POST' && strpos($path, 'hepsiburada/import') === 0) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['product'])) {
            http_response_code(400);
            sendResponse(['error' => 'Ürün verisi gerekli']);
        }
        
        $product = $data['product'];
        
        // Supabase'e kaydet
        $supabaseResult = $supabase->insertProduct([
            'name' => $product['title'],
            'price' => $product['price'],
            'description' => $product['title'],
            'image_url' => $product['image'],
            'source' => 'hepsiburada',
            'source_id' => $product['id'],
            'source_url' => $product['url']
        ]);
        
        // WordPress'e ekle
        $wpResult = $woocommerce->addProduct([
            'name' => $product['title'],
            'type' => 'simple',
            'regular_price' => (string)$product['price'],
            'description' => $product['title'],
            'short_description' => $product['title'],
            'images' => [
                ['src' => $product['image']]
            ]
        ]);
        
        sendResponse([
            'supabase' => $supabaseResult,
            'wordpress' => $wpResult
        ]);
    }

    // 404 - Endpoint bulunamadı
    else {
        error_log("404 Error - Method: " . $method . ", Path: " . $path);
        http_response_code(404);
        sendResponse([
            'error' => 'Endpoint bulunamadı',
            'path' => $path,
            'method' => $method,
            'request_uri' => $request_uri
        ]);
    }

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    handleError($e);
} 