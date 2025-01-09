<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../scraper.php';

// Scraper'ı başlat
$scraper = new ProductScraper();

// Örnek ürün URL'si
$url = "https://www.hepsiburada.com/bosch-gsb-21-2-rct-profesyonel-1300-watt-elektrikli-darbeli-matkap-pm-hrboschgsb222rce";

try {
    // Ürün bilgilerini çek
    $product = $scraper->scrapeProduct($url);
    
    // Sonuçları göster
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $product,
        'message' => 'Ürün bilgileri başarıyla çekildi'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'Ürün bilgileri çekilirken bir hata oluştu'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?> 