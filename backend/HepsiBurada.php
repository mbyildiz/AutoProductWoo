<?php

class HepsiBuradaScraper {
    private $baseUrl = 'https://www.hepsiburada.com/ara';
    private $cookies = [];
    
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
    private function fetchUrl($url) {
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
     * HTML içeriğinden ürünleri parse eder
     * @param string $html
     * @return array
     */
    private function parseProducts($html) {
        $products = [];
        preg_match_all('/<a[^>]*class="moria-ProductCard-gyqBb[^"]*".*?<\/a>/s', $html, $matches);
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $productHtml) {
                if (preg_match('/href="([^"]+)".*?title="([^"]+)"/', $productHtml, $urlMatch)) {
                    $url = $urlMatch[1];
                    if (strpos($url, 'adservice.hepsiburada.com') !== false) {
                        continue;
                    }
                    
                    if (strpos($url, 'http') !== 0) {
                        $url = 'https://www.hepsiburada.com' . $url;
                    }
                    $title = $urlMatch[2];
                    
                    preg_match('/p-([^?\/]+)/', $url, $idMatch);
                    $id = $idMatch[1] ?? '';
                    
                    $image = '';
                    if (preg_match('/src="([^"]+productimages[^"]+\.jpg)"/', $productHtml, $imageMatch)) {
                        if (strpos($imageMatch[1], 'adservice.hepsiburada.com') === false) {
                            $image = $imageMatch[1];
                        }
                    }
                    
                    $brand = explode(' ', $title)[0] ?? '';
                    $price = $this->extractPrice($productHtml);
                    
                    if (!empty($title)) {
                        $products[] = [
                            'id' => $id,
                            'title' => trim($title),
                            'price' => $price,
                            'image' => $image,
                            'url' => $url,
                            'brand' => $brand,
                            'category' => ''
                        ];
                    }
                }
            }
        }
        
        return $products;
    }
}

class HepsiBuradaAPI {
    private $scraper;
    
    public function __construct() {
        $this->scraper = new HepsiBuradaScraper();
    }
    
    /**
     * Ürün arama endpoint'i
     * @param string $searchTerm
     * @param int $page
     * @return array
     */
    public function search($searchTerm, $page = 1) {
        try {
            $products = $this->scraper->getProducts($searchTerm, $page);
            
            $response = [
                'success' => true,
                'page' => $page,
                'search_term' => $searchTerm,
                'products' => array_map(function($product) {
                    $product['title'] = $product['title'];
                    return $product;
                }, $products),
                'total' => count($products)
            ];

            // JSON_UNESCAPED_SLASHES ve JSON_UNESCAPED_UNICODE flaglerini ekleyelim
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
}

// API Kullanım örneği:
/*
$api = new HepsiBuradaAPI();
$results = $api->search('bosch', 1);
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
*/ 