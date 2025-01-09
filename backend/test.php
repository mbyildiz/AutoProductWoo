<?php
require_once 'HepsiBurada.php';

$api = new HepsiBuradaAPI();
$results = $api->search('laptop', 1);

if ($results['success']) {
    foreach ($results['products'] as $product) {
        echo "----------------------------------------\n";
        echo "ID: " . $product['id'] . "\n";
        echo "URL: " . $product['url'] . "\n";
        echo "Ürün Adı: " . $product['title'] . "\n";
        echo "Marka: " . $product['brand'] . "\n";
        echo "Fiyat: " . $product['price'] . " TL\n";
        echo "Ana Kategori: " . $product['categories']['main_category'] . "\n";
        echo "Alt Kategori: " . $product['categories']['sub_category'] . "\n";
        echo "Açıklama: " . substr($product['description'], 0, 200) . "...\n";
        echo "Ana Resim: " . $product['image'] . "\n";
        
        if (!empty($product['images'])) {
            echo "Ek Resimler:\n";
            foreach (array_slice($product['images'], 0, 3) as $img) {
                echo "- " . $img . "\n";
            }
            
            if (count($product['images']) > 3) {
                echo "(+" . (count($product['images']) - 3) . " resim daha)\n";
            }
        }
        
        if (!empty($product['description_table'])) {
            echo "Özellikler:\n";
            foreach ($product['description_table'] as $key => $value) {
                echo "- $key: $value\n";
            }
        }
        
        echo "----------------------------------------\n\n";
    }
} else {
    echo "Hata: " . $results['error'] . "\n";
} 