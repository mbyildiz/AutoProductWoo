<?php
session_start();
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
    $_SESSION['message'] = 'Hata: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header('Location: /');
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

    // Form işlemleri
    if ($method === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'test_woo':
                $result = $woocommerce->getProducts();
                $_SESSION['message'] = 'WooCommerce bağlantısı başarılı! Ürün sayısı: ' . count($result);
                $_SESSION['message_type'] = 'success';
                header('Location: /');
                exit;

            case 'test_supabase':
                $result = $supabase->testConnection();
                $_SESSION['message'] = 'Supabase bağlantısı başarılı!';
                $_SESSION['message_type'] = 'success';
                header('Location: /');
                exit;

            case 'sync_products':
                $products = $woocommerce->getProducts();
                foreach ($products as $product) {
                    $supabase->insertProduct($product);
                }
                $_SESSION['message'] = 'Ürünler başarıyla senkronize edildi!';
                $_SESSION['message_type'] = 'success';
                header('Location: /');
                exit;

            case 'hepsiburada_search':
                if (empty($_POST['search_term'])) {
                    $_SESSION['message'] = 'Arama terimi gerekli!';
                    $_SESSION['message_type'] = 'danger';
                    header('Location: /');
                    exit;
                }
                $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
                $results = $hepsiburada->search($_POST['search_term'], $page);
                if ($results['success']) {
                    $_SESSION['search_results'] = $results['products'];
                    $_SESSION['message'] = count($results['products']) . ' ürün bulundu.';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Arama başarısız: ' . $results['error'];
                    $_SESSION['message_type'] = 'danger';
                }
                header('Location: /');
                exit;

            case 'hepsiburada_import':
                if (!isset($_POST['product_data'])) {
                    $_SESSION['message'] = 'Ürün verisi gerekli!';
                    $_SESSION['message_type'] = 'danger';
                    header('Location: /');
                    exit;
                }
                $productData = json_decode($_POST['product_data'], true);
                
                // Supabase'e kaydet
                $supabaseResult = $supabase->insertProduct([
                    'name' => $productData['title'],
                    'price' => $productData['price'],
                    'description' => $productData['description'] ?? $productData['title'],
                    'image_url' => $productData['image'],
                    'source' => 'hepsiburada',
                    'source_id' => $productData['id'],
                    'source_url' => $productData['url'],
                    'images' => $productData['images'] ?? []
                ]);
                
                // WordPress'e ekle
                $wpResult = $woocommerce->addProduct([
                    'name' => $productData['title'],
                    'type' => 'simple',
                    'regular_price' => (string)$productData['price'],
                    'description' => $productData['description'] ?? $productData['title'],
                    'short_description' => substr($productData['description'] ?? $productData['title'], 0, 200),
                    'images' => array_merge(
                        [['src' => $productData['image']]],
                        array_map(function($img) {
                            return ['src' => $img];
                        }, $productData['images'] ?? [])
                    )
                ]);
                
                $_SESSION['message'] = 'Ürün başarıyla içe aktarıldı!';
                $_SESSION['message_type'] = 'success';
                header('Location: /');
                exit;
        }
    }

    // API istekleri
    if (strpos($path, 'api/') === 0) {
        $path = substr($path, 4); // "api/" kısmını kaldır
        
        // GET /products - Ürünleri listeleme
        if ($method === 'GET' && $path === 'products') {
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
        else if ($method === 'GET' && strpos($path, 'hepsiburada/search') === 0) {
            $search_term = isset($_GET['q']) ? $_GET['q'] : '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            
            if (empty($search_term)) {
                http_response_code(400);
                sendResponse(['error' => 'Arama terimi gerekli']);
            }
            
            $results = $hepsiburada->search($search_term, $page);
            sendResponse($results);
        }
        
        // POST /products - Ürün ekleme
        else if ($method === 'POST' && $path === 'products') {
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
    }

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    handleError($e);
} 