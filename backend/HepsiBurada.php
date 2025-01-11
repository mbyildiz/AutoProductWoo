<?php

class HepsiBuradaScraper {
    protected $baseUrl = 'https://www.hepsiburada.com/ara';
    protected $cookies = [];
    
    public function __construct() {
        // Base constructor
    }
    
    /**
     * Belirtilen arama terimi için ürünleri getirir
     * @param string $searchTerm Arama terimi
     * @param int $page Sayfa numarası
     * @return array Ürün listesi
     */
    public function getProducts($searchTerm, $page = 1) {
        $url = $this->baseUrl . '?q=' . urlencode($searchTerm);
        if ($page > 1) {
            $url .= '&sayfa=' . $page;
        }
        
        // Önce ana sayfayı ziyaret edelim
        $this->fetchUrl('https://www.hepsiburada.com/');
        sleep(2); // Kısa bir bekleme
        
        $html = $this->fetchUrl($url);
        return $this->parseProducts($html);
    }
    
    /**
     * URL'den içeriği çeker
     * @param string $url
     * @return string
     */
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
            'Upgrade-Insecure-Requests: 1',
            'Referer: https://www.hepsiburada.com/'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Diğer önemli ayarlar
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        // Response headers'ı al ve cookie'leri kaydet
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
        foreach($matches[1] as $cookie) {
            $this->cookies[] = $cookie;
        }
        
        curl_close($ch);
        return $body;
    }
    
    /**
     * Ürün HTML'inden fiyatı çıkarır
     * @param string $html
     * @return string
     */
    private function extractPrice($html) {
        $pattern = '/<div[^>]*data-test-id="price-current-price"[^>]*>([^<]*)/s';
        
        if (preg_match($pattern, $html, $matches)) {
            $priceHtml = $matches[1];
            $price = preg_replace('/[^0-9,]/', '', $priceHtml);
            $price = str_replace(',', '.', $price);
            
            if (preg_match('/<div[^>]*data-test-id="campaign"[^>]*>Sepette ([^<]*)/s', $html, $basketMatches)) {
                $basketPrice = preg_replace('/[^0-9,]/', '', $basketMatches[1]);
                $basketPrice = str_replace(',', '.', $basketPrice);
                $price = $basketPrice;
            }
            
            if (!empty($price)) {
                return $price;
            }
        }
        
        return '';
    }
    
    /**
     * Ürün detay sayfasından detaylı bilgileri çeker
     * @param string $url
     * @return array
     */
    private function getProductDetailsOrImages($html) {
        sleep(1); // Rate limiting için kısa bekleme

        // Ürün açıklamasını çek
        $description = '';
        if (preg_match('/<div[^>]*data-test-id="ProductDescription"[^>]*>(.*?)<\/div>/s', $html, $descMatches)) {
            $description = strip_tags($descMatches[1]);
            $description = trim(preg_replace('/\s+/', ' ', $description));

            // Açıklama içindeki resimleri kontrol et
            preg_match_all('/<img[^>]*src="([^"]*)"[^>]*>/i', $descMatches[1], $descImageMatches);
            if (!empty($descImageMatches[1])) {
                foreach ($descImageMatches[1] as $imgUrl) {
                    if (strpos($imgUrl, 'https://images.hepsiburada.net/description-assets') === 0) {
                        $images[] = $imgUrl;
                    }
                }
            }
        } else {
            $description = 'Ürün açıklaması bulunamadı';
        }

        // Ürün resimlerini çek
        $images = [];
        if (preg_match_all('/"imageUrl":"([^"]*productimages[^"]*\.jpg)"/', $html, $imageMatches)) {
            $images = array_merge($images, array_unique($imageMatches[1]));
            $images = array_values(array_filter($images, function($url) {
                return strpos($url, 'adservice.hepsiburada.com') === false;
            }));
        }

        return [
            'description' => $description,
            'images' => $images
        ];
    }
    
    /**
     * HTML içeriğinden ürünleri parse eder
     * @param string $html
     * @return array
     */
    private function parseProducts($html) {
        $products = [];
        preg_match_all('/<a[^>]*class="moria-ProductCard-gyqBb[^"]*".*?<\/a>/s', $html, $matches);
        
        if (!empty($matches[0])) {
            $productCount = 0;
            foreach ($matches[0] as $productHtml) {
                if (preg_match('/href="([^"]+)".*?title="([^"]+)"/', $productHtml, $urlMatch)) {
                    $url = $urlMatch[1];
                    if (strpos($url, 'adservice.hepsiburada.com') !== false) {
                        continue;
                    }
                    
                    if (strpos($url, 'http') !== 0) {
                        $url = 'https://www.hepsiburada.com' . $url;
                    }
                    
                    // Crawl API'sine istek gönder
                    $crawlResponse = $this->sendToCrawlAPI($url);
                    
                    if ($crawlResponse) {
                        $products[] = $crawlResponse;
                        $productCount++;
                        
                        if ($productCount >= 10) { // Test için sadece 1 ürün
                            break;
                        }
                    }
                }
            }
        }
        
        return $products;
    }
    
    /**
     * Crawl API'sine istek gönderir
     * @param string $url
     * @return array|null
     */
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
                    // URL'den ID çıkarma işlemini geliştir
                    $id = '';
                    
                    // pm-XXXXX formatı için kontrol
                    if (preg_match('/-pm-([A-Za-z0-9]+)/', $url, $matches)) {
                        $id = $matches[1];
                    }
                    // p-XXXXX formatı için kontrol
                    elseif (preg_match('/p-([A-Za-z0-9]+)/', $url, $matches)) {
                        $id = $matches[1];
                    }
                    // URL'den ID çıkarılamadıysa, başlıktan benzersiz ID oluştur
                    if (empty($id) && !empty($data['title'])) {
                        $id = 'HB_' . substr(md5($data['title']), 0, 10);
                    }

                    if (DEBUG_MODE) {
                        error_log("Çıkarılan ID: " . $id);
                        error_log("Başlık: " . ($data['title'] ?? 'Başlık yok'));
                    }

                    // Fiyatı düzenle (sadece sayısal değer)
                    $price = preg_replace('/[^0-9]/', '', $data['price'] ?? '');

                    $result = [
                        'id' => $id,
                        'url' => $url,
                        'title' => $data['title'] ?? '',
                        'brand' => $data['brand'] ?? '',
                        'description' => $data['description'] ?? '',
                        'image_url' => $data['image_url'] ?? null,
                        'additional_images' => $data['additional_images'] ?? [],
                        'img_description' => $data['img_description'] ?? [],
                        'price' => $price,
                        'categories' => [
                            'main_category' => $data['categories']['main_category'] ?? '',
                            'sub_category' => $data['categories']['sub_category'] ?? ''
                        ],
                        'description_table' => $data['description_table'] ?? []
                    ];

                    if (DEBUG_MODE) {
                        error_log("İşlenmiş veri: " . print_r($result, true));
                        error_log("========= CRAWL API İSTEĞİ TAMAMLANDI =========\n");
                    }

                    return $result;
                }
            }
            
            error_log("API Error for URL $url: HTTP Code $httpCode, Response: $response");
            return null;
            
        } catch (Exception $e) {
            error_log("Exception while calling Crawl API: " . $e->getMessage());
            return null;
        }
    }

    public function scrapeProduct($url) {
        if (DEBUG_MODE) {
            error_log("Ürün URL'si: " . $url);
        }

        // Debug için spesifik URL kontrolü
        $debug_url = 'https://www.hepsiburada.com/tp-cd-18-50-te-cs-18-165-1-te-os-18-150-tc-js-18-akulu-ahsap-seti-pm-HBC00007CEB9Y';
        $is_debug_url = ($url === $debug_url);

        if ($is_debug_url && DEBUG_MODE) {
            error_log("Debug URL tespit edildi: " . $url);
        }

        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            
            $response = curl_exec($ch);
            
            if ($is_debug_url) {
                // Debug HTML'i kaydet
                $debug_file = 'debug-craw.html';
                file_put_contents($debug_file, $response);
                error_log("HTML içeriği kaydedildi: " . $debug_file);
            }

            if (curl_errno($ch)) {
                throw new Exception('Curl Error: ' . curl_error($ch));
            }

            $data = $this->parseProductHTML($response);

            if ($is_debug_url) {
                // Ayrıştırılan veriyi de kaydet
                $debug_data_file = 'debug-data.json';
                file_put_contents($debug_data_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                error_log("Ayrıştırılan veri kaydedildi: " . $debug_data_file);
            }

            curl_close($ch);
            return $data;

        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("Hata oluştu: " . $e->getMessage());
            }
            throw $e;
        }
    }

    private function parseProductHTML($html) {
        if (DEBUG_MODE) {
            error_log("HTML ayrıştırma başladı");
        }

        // HTML'i parse et
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $data = [];

        try {
            // Başlık
            $titleNode = $xpath->query('//h1[@class="product-name"]')->item(0);
            $data['title'] = $titleNode ? trim($titleNode->nodeValue) : '';
            
            if (DEBUG_MODE) {
                error_log("Başlık: " . $data['title']);
            }

            // Marka
            $brandNode = $xpath->query('//span[@class="brand-name"]')->item(0);
            $data['brand'] = $brandNode ? trim($brandNode->nodeValue) : '';
            
            if (DEBUG_MODE) {
                error_log("Marka: " . $data['brand']);
            }

            // Resimler
            $data['image_url'] = '';
            $data['additional_images'] = [];
            
            $imageNodes = $xpath->query('//picture/img[@src]');
            foreach ($imageNodes as $img) {
                $imgUrl = $img->getAttribute('src');
                if (empty($data['image_url'])) {
                    $data['image_url'] = $imgUrl;
                } else {
                    $data['additional_images'][] = $imgUrl;
                }
            }

            if (DEBUG_MODE) {
                error_log("Ana resim: " . $data['image_url']);
                error_log("Ek resim sayısı: " . count($data['additional_images']));
            }

            // Özellikler tablosu
            $data['description_table'] = [];
            $rows = $xpath->query('//table[@class="data-list tech-spec"]//tr');
            
            foreach ($rows as $row) {
                $key = trim($xpath->query('.//th', $row)->item(0)->nodeValue);
                $value = trim($xpath->query('.//td', $row)->item(0)->nodeValue);
                $data['description_table'][$key] = $value;
            }

            if (DEBUG_MODE) {
                error_log("Özellik sayısı: " . count($data['description_table']));
            }

            // Debug için tüm veriyi logla
            if (DEBUG_MODE) {
                error_log("Ayrıştırılan veri: " . print_r($data, true));
            }

            return $data;

        } catch (Exception $e) {
            if (DEBUG_MODE) {
                error_log("HTML ayrıştırma hatası: " . $e->getMessage());
            }
            throw $e;
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
     * Ürün arama endpoint'i
     * @param string $searchTerm
     * @param int $page
     * @param int $limit Toplam işlenecek ürün sayısı
     * @return array
     */
    public function search($searchTerm, $page = 1, $limit = 10) {
        try {
            $allProducts = [];
            $processedCount = 0;
            $batchNumber = 1;
            
            // Sayfa numarasına göre URL oluştur
            $url = $this->baseUrl . '?q=' . urlencode($searchTerm);
            if ($page > 1) {
                $url .= '&sayfa=' . $page;
            }
            
            if (DEBUG_MODE) {
                error_log("Arama URL'si: " . $url);
                error_log("Sayfa: " . $page);
                error_log("Limit: " . $limit);
            }
            
            // HTML'i sayfaya göre al
            $html = $this->fetchUrl($url);
            preg_match_all('/<a[^>]*class="moria-ProductCard-gyqBb[^"]*".*?<\/a>/s', $html, $matches);
            
            if (!empty($matches[0])) {
                foreach ($matches[0] as $productHtml) {
                    if ($processedCount >= $limit) {
                        break;
                    }

                    if (preg_match('/href="([^"]+)".*?title="([^"]+)"/', $productHtml, $urlMatch)) {
                        $url = $urlMatch[1];
                        if (strpos($url, 'adservice.hepsiburada.com') !== false) {
                            continue;
                        }
                        
                        if (strpos($url, 'http') !== 0) {
                            $url = 'https://www.hepsiburada.com' . $url;
                        }

                        // Batch kontrolü
                        if ($processedCount > 0 && $processedCount % $this->batchSize === 0) {
                            if (DEBUG_MODE) {
                                error_log("Batch #" . $batchNumber . " tamamlandı. " . $processedCount . " ürün işlendi.");
                            }
                            sleep($this->sleepBetweenBatches);
                            $batchNumber++;
                        }
                        
                        // Crawl API'sine istek gönder
                        $crawlResponse = $this->sendToCrawlAPI($url);
                        
                        if ($crawlResponse) {
                            $allProducts[] = $crawlResponse;
                            $processedCount++;
                            
                            if (DEBUG_MODE) {
                                error_log("Ürün işlendi: " . $processedCount . "/" . $limit);
                            }
                        }
                    }
                }
            }
            
            $response = [
                'success' => true,
                'page' => $page,
                'search_term' => $searchTerm,
                'products' => $allProducts,
                'total' => count($allProducts),
                'processed_count' => $processedCount,
                'batch_count' => $batchNumber - 1
            ];

            return json_decode(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS), true);

        } catch (Exception $e) {
            error_log("HepsiBurada API Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Batch boyutunu ayarla
     * @param int $size
     */
    public function setBatchSize($size) {
        $this->batchSize = max(1, intval($size));
    }

    /**
     * Batch'ler arası bekleme süresini ayarla
     * @param int $seconds
     */
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