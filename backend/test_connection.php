<?php
require_once 'config.php';
require_once 'WooCommerceAPI.php';
require_once 'SupabaseDB.php';

// Hata raporlamayı aktif et
error_reporting(E_ALL);
ini_set('display_errors', 1); // Hata mesajlarını göster
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');

try {
    $type = $_GET['type'] ?? '';
    
    if (empty($type)) {
        throw new Exception("Bağlantı tipi belirtilmedi");
    }
    
    switch ($type) {
        case 'wordpress':
            try {
                $wc = new WooCommerceAPI();
                error_log("WooCommerceAPI başlatıldı");
                
                $result = $wc->getProducts(1, 1);
                error_log("getProducts sonucu: " . print_r($result, true));
                
                if (!isset($result['products'])) {
                    error_log("Geçersiz yanıt: products dizisi bulunamadı");
                    throw new Exception("WordPress'ten geçersiz yanıt alındı");
                }
                
                if (!isset($result['total'])) {
                    error_log("Geçersiz yanıt: total değeri bulunamadı");
                    throw new Exception("WordPress'ten geçersiz yanıt alındı - total değeri yok");
                }
                
                $response = [
                    'success' => true,
                    'product_count' => $result['total'],
                    'message' => 'WordPress bağlantısı başarılı'
                ];
                
                error_log("Başarılı yanıt gönderiliyor: " . print_r($response, true));
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                
            } catch (Exception $e) {
                error_log("WordPress bağlantı hatası: " . $e->getMessage());
                error_log("Hata detayı: " . $e->getTraceAsString());
                throw new Exception("WordPress bağlantısı başarısız: " . $e->getMessage());
            }
            break;
            
        case 'supabase':
            try {
                $db = new SupabaseDB();
                $result = $db->testConnection();
                
                if (!$result) {
                    throw new Exception("Supabase bağlantısı başarısız");
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Supabase bağlantısı başarılı'
                ], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                error_log("Supabase bağlantı hatası: " . $e->getMessage());
                throw new Exception("Supabase bağlantısı başarısız: " . $e->getMessage());
            }
            break;
            
        default:
            throw new Exception("Geçersiz bağlantı tipi: " . $type);
    }
    
} catch (Exception $e) {
    error_log("Test bağlantısı hatası: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} 