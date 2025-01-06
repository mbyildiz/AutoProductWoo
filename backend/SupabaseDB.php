<?php
set_time_limit(120);
require_once 'config.php';

class SupabaseDB {
    private $supabase_url;
    private $supabase_key;

    public function __construct() {
        $this->supabase_url = SUPABASE_URL;
        $this->supabase_key = SUPABASE_KEY;
    }

    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        error_log("API isteği başlatılıyor: " . $this->supabase_url . $endpoint);
        
        $curl = curl_init();

        $headers = [
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . $this->supabase_key,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ];

        $url = $this->supabase_url . $endpoint;
        error_log("Tam URL: " . $url);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ];

        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);

        error_log("CURL isteği gönderiliyor...");
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        if ($err) {
            error_log("CURL Hatası: " . $err);
            throw new Exception("cURL Error: " . $err);
        }
        
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        error_log("HTTP Yanıt Kodu: " . $httpCode);
        curl_close($curl);

        if ($httpCode >= 400) {
            error_log("HTTP Hatası. Yanıt: " . $response);
            throw new Exception("HTTP Error: " . $httpCode . " - Response: " . $response);
        }

        return json_decode($response, true);
    }

    public function insertProduct($productData) {
        return $this->makeRequest('/rest/v1/products', 'POST', $productData);
    }

    public function getProducts($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        return $this->makeRequest("/rest/v1/products?select=*&offset={$offset}&limit={$limit}");
    }

    public function getScraperConfigs() {
        return $this->makeRequest('/rest/v1/scraper_configs?select=*');
    }

    public function testConnection() {
        try {
            error_log("Supabase bağlantı testi başlatılıyor...");
            error_log("Supabase URL: " . $this->supabase_url);
            
            // Düzeltilmiş basit sorgu - sadece tek bir kayıt isteyelim
            $result = $this->makeRequest('/rest/v1/products?select=*&limit=1', 'GET');
            
            error_log("Supabase bağlantı testi başarılı!");
            return true;
        } catch (Exception $e) {
            error_log("Supabase bağlantı hatası: " . $e->getMessage());
            throw new Exception("Supabase bağlantı hatası: " . $e->getMessage());
        }
    }
} 