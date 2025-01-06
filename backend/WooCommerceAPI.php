<?php
require_once 'config.php';

class WooCommerceAPI {
    private $consumer_key;
    private $consumer_secret;
    private $wp_api_url;

    public function __construct() {
        $this->consumer_key = WP_CONSUMER_KEY;
        $this->consumer_secret = WP_CONSUMER_SECRET;
        $this->wp_api_url = WP_API_URL;
    }

    private function getAuthHeader() {
        // Medya yüklemesi için WordPress yetkilendirmesi
        if (defined('WP_ADMIN_USER') && defined('WP_APP_PASSWORD') && !empty(WP_APP_PASSWORD)) {
            return base64_encode(WP_ADMIN_USER . ':' . WP_APP_PASSWORD);
        }
        // WooCommerce API yetkilendirmesi
        return base64_encode($this->consumer_key . ':' . $this->consumer_secret);
    }

    public function uploadImage($imageUrl) {
        $curl = curl_init();
        
        // Önce resmi indir
        echo "Resim indiriliyor: " . $imageUrl . "\n";
        $imageData = file_get_contents($imageUrl);
        if ($imageData === false) {
            throw new Exception("Resim indirilemedi: " . $imageUrl);
        }
        echo "Resim başarıyla indirildi. Boyut: " . strlen($imageData) . " byte\n";
        
        // Geçici dosya oluştur
        $tempFile = tempnam(sys_get_temp_dir(), 'wp_');
        file_put_contents($tempFile, $imageData);
        echo "Resim geçici dosyaya kaydedildi: " . $tempFile . "\n";
        
        // Dosya türünü belirle
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tempFile);
        finfo_close($finfo);
        echo "Dosya türü: " . $mimeType . "\n";
        
        // CURLFile oluştur
        $fileName = basename($imageUrl);
        if (strpos($fileName, '?') !== false) {
            $fileName = substr($fileName, 0, strpos($fileName, '?'));
        }
        if (!preg_match('/\.(jpg|jpeg|png|gif)$/i', $fileName)) {
            $fileName .= '.jpg';
        }
        
        $cfile = new CURLFile($tempFile, $mimeType, $fileName);
        echo "Dosya adı: " . $fileName . "\n";
        
        $url = str_replace('/wc/v3', '/wp/v2/media', $this->wp_api_url);
        echo "Upload URL: " . $url . "\n";
        
        $postFields = ['file' => $cfile];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $this->getAuthHeader(),
                'Content-Type: multipart/form-data',
                'Accept: application/json'
            ],
        ]);

        echo "API isteği gönderiliyor...\n";
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        // HTTP durum kodunu al
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        echo "HTTP Durum Kodu: " . $httpCode . "\n";
        
        // CURL bilgilerini göster
        $info = curl_getinfo($curl);
        echo "CURL Bilgileri:\n";
        print_r($info);
        
        // Geçici dosyayı sil
        unlink($tempFile);
        echo "Geçici dosya silindi\n";
        
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        echo "API Yanıtı:\n" . $response . "\n";
        
        $result = json_decode($response, true);
        if (!isset($result['id'])) {
            throw new Exception("Resim yükleme hatası: " . $response);
        }

        return $result;
    }

    public function addProduct($productData) {
        // Eğer images varsa, önce resimleri yükle
        if (isset($productData['images']) && is_array($productData['images'])) {
            $uploadedImages = [];
            foreach ($productData['images'] as $image) {
                if (isset($image['src'])) {
                    try {
                        $uploadedImage = $this->uploadImage($image['src']);
                        $uploadedImages[] = [
                            'id' => $uploadedImage['id'],
                            'position' => $image['position'] ?? 0
                        ];
                    } catch (Exception $e) {
                        error_log("Resim yükleme hatası: " . $e->getMessage());
                        continue;
                    }
                }
            }
            if (!empty($uploadedImages)) {
                $productData['images'] = $uploadedImages;
            }
        }

        $curl = curl_init();
        
        $url = $this->wp_api_url . '/products';
        error_log("API URL: " . $url);
        error_log("Product Data: " . json_encode($productData));
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($productData),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $this->getAuthHeader(),
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        error_log("HTTP Response Code: " . $httpCode);
        error_log("Response: " . $response);
        
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        if ($httpCode >= 400) {
            throw new Exception("HTTP Error " . $httpCode . ": " . $response);
        }

        return json_decode($response, true);
    }

    public function getProducts($page = 1, $per_page = 10) {
        $curl = curl_init();
        
        $url = $this->wp_api_url . '/products?page=' . $page . '&per_page=' . $per_page;
        error_log("API URL: " . $url);
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $this->getAuthHeader(),
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);

        if ($err) {
            throw new Exception("cURL Error: " . $err);
        }

        return json_decode($response, true);
    }
} 