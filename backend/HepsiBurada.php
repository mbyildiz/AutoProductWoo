<?php

class HepsiBuradaScraper {
    protected $baseUrl = 'https://www.hepsiburada.com/ara';
    protected $cookies = [];
    
    public function __construct() {
        // Base constructor
    }
    
    protected function fetchUrl($url) {
        $ch = curl_init();
        
        // Temel ayarlar
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        // Gerçekçi bir tarayıcı gibi davranalım
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        // Cookie yönetimi
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
        
        // HTTP başlıkları
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        
        curl_close($ch);
        return $body;
    }
    
    protected function parseProducts($html) {
        $products = [];
        
        if (DEBUG_MODE) {
            error_log("\n========= ÜRÜN PARSE İŞLEMİ BAŞLIYOR =========");
        }
        
        preg_match_all('/<a[^>]*class="moria-ProductCard-gyqBb[^"]*".*?<\/a>/s', $html, $matches);
        
        if (!empty($matches[0])) {
            if (DEBUG_MODE) {
                error_log("Sayfada bulunan toplam ürün sayısı: " . count($matches[0]));
            }
            
            foreach ($matches[0] as $index => $productHtml) {
                if (preg_match('/href="([^"]+)".*?title="([^"]+)"/', $productHtml, $urlMatch)) {
                    $url = $urlMatch[1];
                    $title = $urlMatch[2];
                    
                    // Reklam URL'lerini atla
                    if (strpos($url, 'adservice.hepsiburada.com') !== false) {
                        if (DEBUG_MODE) {
                            error_log("Reklam ürünü atlandı: " . $url);
                        }
                        continue;
                    }
                    
                    // URL'yi düzelt
                    if (strpos($url, 'http') !== 0) {
                        $url = 'https://www.hepsiburada.com' . $url;
                    }
                    
                    // Ürün ID'sini URL'den çıkar
                    $product_id = '';
                    if (preg_match('/-p-([A-Za-z0-9]+)/', $url, $id_match)) {
                        $product_id = $id_match[1];
                    } elseif (preg_match('/-pm-([A-Za-z0-9]+)/', $url, $id_match)) {
                        $product_id = $id_match[1];
                    }
                    
                    if (empty($product_id)) {
                        $product_id = 'HB_' . substr(md5($url), 0, 10);
                    }
                    
                    // URL'yi ürün verilerine ekle
                    $products[] = [
                        'id' => $product_id,
                        'url' => $url, // Tam URL'yi sakla
                        'title' => $title
                    ];
                    
                    if (DEBUG_MODE) {
                        error_log("Ürün eklendi - ID: $product_id, URL: $url");
                    }
                }
            }
        } else {
            if (DEBUG_MODE) {
                error_log("Sayfada hiç ürün bulunamadı!");
            }
        }
        
        if (DEBUG_MODE) {
            error_log("Toplam işlenen ürün sayısı: " . count($products));
            error_log("========= ÜRÜN PARSE İŞLEMİ TAMAMLANDI =========\n");
        }
        
        return $products;
    }
    
    protected function sendToCrawlAPI($url) {
        try {
            if (DEBUG_MODE) {
                error_log("\n========= CRAWL API İSTEĞİ BAŞLIYOR =========");
                error_log("URL: " . $url);
            }
            
            $ch = curl_init('http://localhost:8008/crawl');
            
            $postData = json_encode(['url' => $url]);
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if ($data) {
                    return $data;
                }
            }
            
            error_log("API Error for URL $url: HTTP Code $httpCode, Response: $response");
            return null;
            
        } catch (Exception $e) {
            error_log("Exception while calling Crawl API: " . $e->getMessage());
            return null;
        }
    }
}

class HepsiBuradaAPI extends HepsiBuradaScraper {
    private $batchSize = 5; // Her batch'te işlenecek ürün sayısı
    private $sleepBetweenBatches = 2; // Batch'ler arası bekleme süresi (saniye)
    
    public function __construct() {
        // PHP zaman aşımı limitini artır
        set_time_limit(1800); // 30 dakika
        ini_set('max_execution_time', 1800);
        
        // Parent constructor'ı çağır
        parent::__construct();
    }
    
    /**
     * Ürün detaylarını getir
     * @param array $product
     * @return array|null
     */
    public function getProductDetails($product) {
        if (DEBUG_MODE) {
            error_log("\n========= ÜRÜN DETAY ÇEKME BAŞLIYOR =========");
            error_log("Ürün ID: " . $product['id']);
            error_log("URL: " . $product['url']);
        }
        
        $crawlResponse = $this->sendToCrawlAPI($product['url']);
        
        if ($crawlResponse) {
            $crawlResponse['id'] = $product['id'];
            
            if (DEBUG_MODE) {
                error_log("Ürün detayları başarıyla alındı");
                error_log("========= ÜRÜN DETAY ÇEKME TAMAMLANDI =========\n");
            }
            
            return $crawlResponse;
        }
        
        if (DEBUG_MODE) {
            error_log("Ürün detayları alınamadı!");
            error_log("========= ÜRÜN DETAY ÇEKME BAŞARISIZ =========\n");
        }
        
        return null;
    }
    
    public function search($searchTerm, $page = 1, $limit = 10) {
        try {
            if (DEBUG_MODE) {
                error_log("\n========= ARAMA İŞLEMİ BAŞLIYOR =========");
                error_log("Arama terimi: " . $searchTerm);
                error_log("İstenen sayfa: " . $page);
                error_log("İstenen limit: " . $limit);
            }
            
            $url = $this->baseUrl . '?q=' . urlencode($searchTerm);
            if ($page > 1) {
                $url .= '&sayfa=' . $page;
            }
            
            if (DEBUG_MODE) {
                error_log("Oluşturulan URL: " . $url);
            }
            
            $html = $this->fetchUrl($url);
            $pageProducts = $this->parseProducts($html);
            
            if (DEBUG_MODE) {
                error_log("Parse edilen toplam ürün sayısı: " . count($pageProducts));
            }
            
            $pageProducts = array_slice($pageProducts, 0, $limit);
            
            $allProducts = [];
            $processedCount = 0;
            $batchNumber = 1;
            
            foreach ($pageProducts as $product) {
                if ($processedCount > 0 && $processedCount % $this->batchSize === 0) {
                    if (DEBUG_MODE) {
                        error_log("Batch #" . $batchNumber . " tamamlandı. " . $processedCount . " ürün işlendi.");
                        error_log("Bekleniyor: " . $this->sleepBetweenBatches . " saniye");
                    }
                    sleep($this->sleepBetweenBatches);
                    $batchNumber++;
                }
                
                // URL'nin doğru olduğundan emin ol
                if (empty($product['url'])) {
                    $product['url'] = 'https://www.hepsiburada.com/p-' . $product['id'];
                }
                
                $productDetails = $this->getProductDetails($product);
                if ($productDetails) {
                    $allProducts[] = $productDetails;
                    $processedCount++;
                    
                    if (DEBUG_MODE) {
                        error_log("Ürün detayları alındı: " . $processedCount . "/" . $limit);
                        error_log("Ürün URL: " . $product['url']);
                    }
                }
                
                if ($processedCount >= $limit) {
                    break;
                }
            }
            
            if (DEBUG_MODE) {
                error_log("Toplam işlenen ürün sayısı: " . count($allProducts));
                error_log("========= ARAMA İŞLEMİ TAMAMLANDI =========\n");
            }
            
            return [
                'success' => true,
                'page' => $page,
                'search_term' => $searchTerm,
                'products' => $allProducts,
                'total' => count($allProducts),
                'processed_count' => $processedCount,
                'batch_count' => $batchNumber - 1
            ];
            
        } catch (Exception $e) {
            error_log("HepsiBurada API Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function setBatchSize($size) {
        $this->batchSize = max(1, intval($size));
    }
    
    public function setSleepBetweenBatches($seconds) {
        $this->sleepBetweenBatches = max(1, intval($seconds));
    }
}

// API Kullanım örneği:
/*
$api = new HepsiBuradaAPI();
$results = $api->search('bosch', 1);
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
*/ 
