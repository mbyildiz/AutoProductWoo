<?php
require_once 'config.php';
require_once 'WooCommerceAPI.php';

// Test sınıfını oluştur
class TestWooCommerceAPI {
    private $woocommerce;
    private $wp_api_url;
    private $wp_auth;
    
    public function __construct() {
        $this->woocommerce = new WooCommerceAPI();
        // WordPress API URL'sini düzelt
        $this->wp_api_url = str_replace('/wp-json/wc/v3', '/wp-json', WP_API_URL);
        // WordPress kimlik bilgilerini hazırla
        $this->wp_auth = base64_encode(WP_ADMIN_USER . ':' . WP_APP_PASSWORD);
    }
    
    private function downloadImage($url) {
        // URL'deki boşlukları %20 ile değiştir
        $url = str_replace(' ', '%20', $url);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_REFERER, 'https://www.hepsiburada.com/');
        
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Resim indirilemedi. HTTP Kodu: " . $http_code);
        }
        
        return $data;
    }
    
    private function uploadImageToWordPress($image_url) {
        // Geçici dosya oluştur
        $temp_file = tempnam(sys_get_temp_dir(), 'wp_');
        $filename = basename(urldecode(parse_url($image_url, PHP_URL_PATH)));
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        try {
            // Resmi indir ve geçici dosyaya kaydet
            $image_data = $this->downloadImage($image_url);
            file_put_contents($temp_file, $image_data);
            
            // WordPress'e yükle
            $upload_endpoint = rtrim($this->wp_api_url, '/') . '/wp/v2/media';
            
            $curl_file = new CURLFile($temp_file, 'image/jpeg', $filename);
            
            $ch = curl_init($upload_endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $curl_file]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . $this->wp_auth
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Geçici dosyayı sil
            @unlink($temp_file);
            
            if ($http_code !== 201) {
                throw new Exception("Resim WordPress'e yüklenemedi. HTTP Kodu: " . $http_code . " Yanıt: " . $response);
            }
            
            $media_data = json_decode($response, true);
            if (!isset($media_data['source_url'])) {
                throw new Exception("WordPress medya yanıtı geçersiz");
            }
            
            return $media_data['source_url'];
            
        } catch (Exception $e) {
            // Hata durumunda geçici dosyayı silmeyi dene
            @unlink($temp_file);
            throw $e;
        }
    }
    
    public function testImageUpload() {
        $test_images = [
            'https://images.hepsiburada.net/assets/Hirdavat/ProductDesc/GSB%2016%20RE_HRBOSCHGSB16RE.jpg',
            'https://images.hepsiburada.net/assets/Hirdavat/ProductDesc/GSB%2016%20RE_HRBOSCHGSB16RE.jpg'
        ];
        
        $results = [];
        foreach ($test_images as $image_url) {
            try {
                // Önce resmi WordPress'e yükle
                $wordpress_image_url = $this->uploadImageToWordPress($image_url);
                
                // WordPress'teki resim URL'ini kullanarak img tag'i oluştur
                $image_html = sprintf(
                    '<img src="%s" alt="Ürün Görseli" class="aligncenter" />',
                    $wordpress_image_url
                );
                
                // Test ürünü oluştur
                $product_data = [
                    'name' => 'Test Ürün (WordPress Medya) ' . uniqid(),
                    'type' => 'simple',
                    'regular_price' => '99.99',
                    'description' => $image_html,
                    'short_description' => 'Kısa açıklama',
                    'categories' => [
                        [
                            'id' => 1
                        ]
                    ]
                ];
                
                $result = $this->woocommerce->createProduct($product_data);
                $results[] = [
                    'status' => 'success',
                    'original_url' => $image_url,
                    'wordpress_url' => $wordpress_image_url,
                    'product_id' => $result['id'] ?? null,
                    'message' => 'Ürün ve resim başarıyla yüklendi'
                ];
                
            } catch (Exception $e) {
                $results[] = [
                    'status' => 'error',
                    'image_url' => $image_url,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}

// Testi çalıştır
$tester = new TestWooCommerceAPI();
$results = $tester->testImageUpload();

// Sonuçları göster
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); 