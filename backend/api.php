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
    
    // GET parametrelerini al
    $search = $_GET['search'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 40);
    
    if (empty($search)) {
        throw new Exception("Arama terimi gerekli");
    }
    
    // HepsiBurada'dan ürünleri al
    $result = $hb_api->search($search, $page, $limit);
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? "Ürünler alınamadı");
    }
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'products' => $result['products'],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => $result['total'] ?? count($result['products']),
            'total_pages' => ceil(($result['total'] ?? count($result['products'])) / $limit)
        ],
        'search_term' => $search,
        'processed_count' => $result['processed_count'] ?? count($result['products']),
        'batch_count' => $result['batch_count'] ?? 0
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} 