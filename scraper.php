<?php

class ProductScraper {
    private $html;
    private $dom;
    
    public function __construct() {
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
    }
    
    public function scrapeProduct($url) {
        // cURL ile sayfayı çek
        $ch = curl_init();
        
        // Temel ayarlar
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // SSL ayarları
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Browser taklidi
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.8,en-US;q=0.5,en;q=0.3',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: max-age=0',
            'Referer: https://www.hepsiburada.com/'
        ];
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Otomatik sıkıştırma kabul et
        
        // Sayfayı çek
        $this->html = curl_exec($ch);
        
        // Debug için HTML'i kaydet
        $debug_file = 'backend/debug_' . md5(time()) . '.html';
        file_put_contents($debug_file, $this->html);
        
        // Hata kontrolü
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        if (empty($this->html)) {
            throw new Exception('Sayfa içeriği boş geldi. Site bot koruması aktif olabilir.');
        }
        
        curl_close($ch);
        
        // UTF-8 dönüşümü
        $this->html = mb_convert_encoding($this->html, 'HTML-ENTITIES', 'UTF-8');
        
        // DOM'u yükle
        @$this->dom->loadHTML($this->html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($this->dom);
        
        // Ürün bilgilerini çek
        $product = [
            'title' => $this->getTitle($xpath),
            'price' => $this->getPrice($xpath),
            'images' => $this->getImages($xpath),
            'specifications' => $this->getSpecifications($xpath),
            'description' => $this->getDescription($xpath),
            'seller' => $this->getSeller($xpath),
            'stock_status' => $this->getStockStatus($xpath),
            'rating' => $this->getRating($xpath),
            'debug_file' => $debug_file
        ];
        
        return $product;
    }
    
    private function getTitle($xpath) {
        $selectors = [
            "//h1[contains(@class, 'product-name')]",
            "//h1[contains(@class, 'title')]",
            "//h1[contains(@itemprop, 'name')]",
            "//h1"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->nodeValue);
            }
        }
        
        return '';
    }
    
    private function getPrice($xpath) {
        $selectors = [
            "//span[contains(@class, 'price')]",
            "//span[contains(@data-test-id, 'price')]",
            "//span[contains(@class, 'product-price')]",
            "//div[contains(@class, 'price')]//span[contains(@class, 'amount')]"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $priceText = trim($nodes->item(0)->nodeValue);
                return preg_replace('/[^0-9,]/', '', $priceText);
            }
        }
        
        return '';
    }
    
    private function getImages($xpath) {
        $images = [];
        $selectors = [
            "//img[contains(@class, 'product-image')]",
            "//img[contains(@class, 'carousel')]//img",
            "//div[contains(@class, 'image')]//img",
            "//div[contains(@class, 'gallery')]//img"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $img) {
                $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
                if (!empty($src) && !in_array($src, $images)) {
                    $images[] = $src;
                }
            }
            
            if (!empty($images)) {
                break;
            }
        }
        
        return array_unique($images);
    }
    
    private function getSpecifications($xpath) {
        $specs = [];
        $selectors = [
            "//div[contains(@class, 'spec')]//tr",
            "//div[contains(@class, 'detail')]//tr",
            "//table[contains(@class, 'data')]//tr"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $label = $xpath->query(".//th|.//td[1]", $node)->item(0);
                $value = $xpath->query(".//td[2]", $node)->item(0);
                
                if ($label && $value) {
                    $labelText = trim($label->nodeValue);
                    $valueText = trim($value->nodeValue);
                    if (!empty($labelText) && !empty($valueText)) {
                        $specs[$labelText] = $valueText;
                    }
                }
            }
            
            if (!empty($specs)) {
                break;
            }
        }
        
        return $specs;
    }
    
    private function getDescription($xpath) {
        $selectors = [
            "//div[contains(@class, 'description')]",
            "//div[contains(@class, 'detail')]",
            "//div[contains(@id, 'description')]",
            "//div[contains(@class, 'product-description')]"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->nodeValue);
            }
        }
        
        return '';
    }
    
    private function getSeller($xpath) {
        $selectors = [
            "//span[contains(@class, 'seller')]",
            "//a[contains(@class, 'seller')]",
            "//div[contains(@class, 'merchant')]//span",
            "//div[contains(@class, 'seller')]//a"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->nodeValue);
            }
        }
        
        return '';
    }
    
    private function getStockStatus($xpath) {
        $selectors = [
            "//div[contains(@class, 'stock')]",
            "//span[contains(@class, 'stock')]",
            "//div[contains(@class, 'availability')]"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                return trim($nodes->item(0)->nodeValue);
            }
        }
        
        return 'Stok durumu bilinmiyor';
    }
    
    private function getRating($xpath) {
        $selectors = [
            "//span[contains(@class, 'rating')]",
            "//div[contains(@class, 'rating')]//span",
            "//div[contains(@class, 'stars')]//span"
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $ratingText = trim($nodes->item(0)->nodeValue);
                return floatval(str_replace(',', '.', $ratingText));
            }
        }
        
        return 0.0;
    }
}

// Test kodunu kaldırdık
?> 