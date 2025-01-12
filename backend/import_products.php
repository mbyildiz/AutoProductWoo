<?php
require_once 'config.php';
require_once 'HepsiBurada.php';
require_once 'WooCommerceAPI.php';

// Hata raporlamayı aktif et
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Maksimum çalışma süresini artır
set_time_limit(900); // 16 dakika
ini_set('max_execution_time', 900);

try {
    // İşlem başlangıç zamanı
    $total_start_time = microtime(true);
    
    // WooCommerce API'sini başlat
    $wc_api = new WooCommerceAPI();
    
    // POST verilerini al
    $json_data = file_get_contents('php://input');
    $post_data = json_decode($json_data, true);
    
    if (empty($post_data) || !isset($post_data['products']) || !is_array($post_data['products'])) {
        throw new Exception("Geçersiz veri formatı");
    }
    
    $products = $post_data['products'];
    
    // WordPress ürün ekleme başlangıç zamanı
    $wordpress_start_time = microtime(true);
    
    $results = [
        'success' => true,
        'total_products' => count($products),
        'imported_products' => 0,
        'failed_products' => 0,
        'duplicate_products' => 0,
        'products' => []
    ];
    
    // Her ürün için
    foreach ($products as $product) {
        try {
            // WooCommerce formatına dönüştür
            $wc_product_data = $wc_api->formatProductData($product);
            
            // WooCommerce'e ekle
            $response = $wc_api->createProduct($wc_product_data);
            
            if ($response) {
                $results['imported_products']++;
                $results['products'][] = [
                    'hb_id' => $product['id'],
                    'wc_id' => $response['id'],
                    'status' => 'success',
                    'message' => 'Başarıyla içe aktarıldı'
                ];
                
                if (DEBUG_MODE) {
                    error_log("Ürün başarıyla eklendi (ID: {$product['id']}, WC ID: {$response['id']})");
                }
            } else {
                throw new Exception("WooCommerce API yanıt vermedi");
            }
            
        } catch (Exception $e) {
            // Eğer hata tekrar eden üründen kaynaklanıyorsa
            if (strpos($e->getMessage(), "Bu isimde bir ürün zaten mevcut") !== false) {
                $results['duplicate_products']++;
                $results['products'][] = [
                    'hb_id' => $product['id'],
                    'status' => 'duplicate',
                    'message' => $e->getMessage()
                ];
            } else {
                $results['failed_products']++;
                $results['products'][] = [
                    'hb_id' => $product['id'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
            
            if (DEBUG_MODE) {
                error_log("Ürün içe aktarma hatası (ID: {$product['id']}): " . $e->getMessage());
                error_log("Hata detayı: " . $e->getTraceAsString());
            }
        }
        
        // Rate limiting - her istek arasında kısa bir bekleme
        usleep(200000); // 0.2 saniye bekle (0.5 yerine)
    }
    
    // WordPress ürün ekleme bitiş zamanı
    $wordpress_end_time = microtime(true);
    $wordpress_duration = $wordpress_end_time - $wordpress_start_time;
    
    // Toplam süre hesaplama
    $total_end_time = microtime(true);
    $total_duration = $total_end_time - $total_start_time;
    
    // İşlem sürelerini sonuçlara ekle
    $results['process_times'] = [
        'wordpress_duration' => sprintf("%.2f dakika", ($wordpress_duration / 60)),
        'total_duration' => sprintf("%.2f dakika", ($total_duration / 60))
    ];
    
    // Tekrar eden ürünleri ekle
    $results['duplicate_product_list'] = $wc_api->getDuplicateProducts();
    
    // Örnek ürünleri ekle (en fazla 5 adet)
    $results['sample_products'] = array_slice($results['products'], 0, 5);
    
    // Tüm ürünleri kaldır (örnek ürünler hariç)
    unset($results['products']);
    
    // JSON olarak yanıt ver
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    
    $error_response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    if (DEBUG_MODE) {
        $error_response['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    echo json_encode($error_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} 