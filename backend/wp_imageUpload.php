<?php
require_once __DIR__ . '/config.php';

// Maksimum çalışma süresini artır
set_time_limit(900); // 15 dakika
ini_set('max_execution_time', 900);

class WPImageUploader {
    private $wp_api_url;
    private $wp_auth;
    
    public function __construct() {
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
        
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Resim indirilemedi. HTTP Kodu: " . $http_code);
        }
        
        return $data;
    }
    
    public function uploadImage($image_url) {
        try {
            // Geçici dosya oluştur
            $temp_file = tempnam(sys_get_temp_dir(), 'wp_');
            $filename = basename(urldecode(parse_url($image_url, PHP_URL_PATH)));
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            
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
            
            return [
                'status' => 'success',
                'url' => $media_data['source_url'],
                'message' => 'Resim başarıyla yüklendi'
            ];
            
        } catch (Exception $e) {
            // Hata durumunda geçici dosyayı silmeyi dene
            if (isset($temp_file)) {
                @unlink($temp_file);
            }
            return [
                'status' => 'error',                
                'message' => $e->getMessage()
            ];
        }
    }
    
} 