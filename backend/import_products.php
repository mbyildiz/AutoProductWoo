<?php
require_once 'config.php';
require_once 'HepsiBurada.php';
require_once 'WooCommerceAPI.php';

// Hata raporlamayı aktif et
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // İşlem başlangıç zamanı
    $total_start_time = microtime(true);
    
    // HepsiBurada API'sini başlat
    $hb_api = new HepsiBuradaAPI();
    
    // WooCommerce API'sini başlat
    $wc_api = new WooCommerceAPI();
    
    // Arama terimini ve sayfa numarasını al
    $search_term = $_GET['search'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 10);
    
    if (empty($search_term)) {
        throw new Exception("Arama terimi gerekli");
    }
    
    // HepsiBurada'dan ürünleri alma başlangıç zamanı
    $crawler_start_time = microtime(true);
    
    // HepsiBurada'dan ürünleri al
    $hb_products = $hb_api->search($search_term, $page, $limit);
    
    // HepsiBurada'dan ürünleri alma bitiş zamanı
    $crawler_end_time = microtime(true);
    $crawler_duration = $crawler_end_time - $crawler_start_time;
    
    if (!$hb_products['success']) {
        throw new Exception("HepsiBurada'dan ürünler alınamadı: " . ($hb_products['error'] ?? 'Bilinmeyen hata'));
    }
    
    // WordPress ürün ekleme başlangıç zamanı
    $wordpress_start_time = microtime(true);
    
    $results = [
        'success' => true,
        'total_products' => count($hb_products['products']),
        'imported_products' => 0,
        'failed_products' => 0,
        'products' => []
    ];
    
    // Her ürün için
    foreach ($hb_products['products'] as $hb_product) {
        try {
            // HepsiBurada verilerini WooCommerce formatına dönüştür
            $wc_product_data = $wc_api->formatProductData($hb_product);
            
            // WooCommerce'e ekle
            $response = $wc_api->createProduct($wc_product_data);
            
            if ($response) {
                $results['imported_products']++;
                $results['products'][] = [
                    'hb_id' => $hb_product['id'],
                    'wc_id' => $response['id'],
                    'status' => 'success',
                    'message' => 'Başarıyla içe aktarıldı'
                ];
                
                if (DEBUG_MODE) {
                    error_log("Ürün başarıyla eklendi (ID: {$hb_product['id']}, WC ID: {$response['id']})");
                }
            } else {
                throw new Exception("WooCommerce API yanıt vermedi");
            }
            
        } catch (Exception $e) {
            $results['failed_products']++;
            $results['products'][] = [
                'hb_id' => $hb_product['id'],
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            
            if (DEBUG_MODE) {
                error_log("Ürün içe aktarma hatası (ID: {$hb_product['id']}): " . $e->getMessage());
                error_log("Hata detayı: " . $e->getTraceAsString());
            }
        }
        
        // Rate limiting - her istek arasında kısa bir bekleme
        usleep(500000); // 0.5 saniye bekle
    }
    
    // WordPress ürün ekleme bitiş zamanı
    $wordpress_end_time = microtime(true);
    $wordpress_duration = $wordpress_end_time - $wordpress_start_time;
    
    // Toplam süre hesaplama
    $total_end_time = microtime(true);
    $total_duration = $total_end_time - $total_start_time;
    
    // İşlem sürelerini sonuçlara ekle
    $results['process_times'] = [
        'crawler_duration' => sprintf("%.2f dakika", ($crawler_duration / 60)),
        'wordpress_duration' => sprintf("%.2f dakika", ($wordpress_duration / 60)),
        'total_duration' => sprintf("%.2f dakika", ($total_duration / 60))
    ];
    
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