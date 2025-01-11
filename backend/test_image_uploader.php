<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/wp_imageUpload.php';

try {
    $uploader = new WPImageUploader();
    echo "WPImageUploader başarıyla oluşturuldu\n";
    
    // Test için bir resim URL'si
    $test_image = "https://example.com/test.jpg";
    $result = $uploader->uploadImage($test_image);
    
    echo "Sonuç:\n";
    print_r($result);
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
    echo "Hata dosya: " . $e->getFile() . "\n";
    echo "Hata satır: " . $e->getLine() . "\n";
} 