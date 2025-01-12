<?php
require_once 'config.php';
require_once 'HepsiBurada.php';

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST');

try {
    // HepsiBurada API'sini başlat
    $hb_api = new HepsiBuradaAPI();
    
    // GET parametrelerini al ve doğrula
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1)); // Minimum 1 olmalı
    $limit = max(1, min(100, intval($_GET['limit'] ?? 10))); // 1-100 arası olmalı
    
    if (empty($search)) {
        throw new Exception("Arama terimi gerekli");
    }
    
    if (DEBUG_MODE) {
        error_log("\n========= API İSTEĞİ BAŞLIYOR =========");
        error_log("Arama terimi: " . $search);
        error_log("İstenen sayfa: " . $page);
        error_log("İstenen limit: " . $limit);
    }
    
    // HepsiBurada'dan ürünleri al
    $result = $hb_api->search($search, $page, $limit);
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? "Ürünler alınamadı");
    }
    
    if (DEBUG_MODE) {
        error_log("Bulunan ürün sayısı: " . count($result['products']));
        error_log("İşlenen ürün sayısı: " . ($result['processed_count'] ?? 0));
        error_log("Batch sayısı: " . ($result['batch_count'] ?? 0));
        error_log("Sayfa: " . $result['page']);
    }
    
    // Başarılı yanıt
    $response = [
        'success' => true,
        'products' => $result['products'],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => count($result['products']),
            'total_pages' => ceil(count($result['products']) / $limit)
        ],
        'search_term' => $search,
        'processed_count' => $result['processed_count'] ?? count($result['products']),
        'batch_count' => $result['batch_count'] ?? 0
    ];
    
    if (DEBUG_MODE) {
        error_log("Yanıt hazırlandı: " . json_encode($response, JSON_UNESCAPED_UNICODE));
        error_log("========= API İSTEĞİ TAMAMLANDI =========\n");
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("API Hatası: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} 