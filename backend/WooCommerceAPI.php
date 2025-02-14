<?php
// WooCommerceAPI.php dosyanızın en üstüne ekleyin
require_once __DIR__ . '/wp_imageUpload.php';

class WooCommerceAPI {
    private $consumer_key;
    private $consumer_secret;
    private $wp_api_url;
    private $process_times = []; // İşlem sürelerini tutacak dizi
    private $duplicate_products = [];
    private $wp_imageUploader; 
    
    public function __construct() {
        $this->consumer_key = WP_CONSUMER_KEY;
        $this->consumer_secret = WP_CONSUMER_SECRET;
        $this->wp_api_url = WP_API_URL;
        $this->wp_imageUploader = new WPImageUploader();
    }
    
    /**
     * İşlem süresini kaydet
     * @param string $key
     * @param float $time
     */
    public function addProcessTime($key, $time) {
        $this->process_times[$key] = $time;
    }
    
    /**
     * İşlem sürelerini getir
     * @return array
     */
    public function getProcessTimes() {
        return $this->process_times;
    }
    
    /**
     * Aynı isimde ürün var mı kontrol et
     * @param string $product_name
     * @return array|null Ürün varsa ürün bilgilerini, yoksa null döner
     */
    private function checkProductExists($product_name) {
        try {
            error_log("\n========= ÜRÜN KONTROL EDİLİYOR =========");
            error_log("Aranan ürün adı: " . $product_name);
            
            // Ürün adını normalize et
            $normalized_search_name = $this->normalizeProductName($product_name);
            error_log("Normalize edilmiş aranan ürün adı: " . $normalized_search_name);
            
            // Ürünleri ara
            $result = $this->makeRequest('GET', '/products?' . http_build_query([
                'search' => $product_name,
                'per_page' => 100
            ]), null, true);
            
            if (!empty($result['data'])) {
                error_log("Bulunan toplam ürün sayısı: " . count($result['data']));
                
                foreach ($result['data'] as $product) {
                    $normalized_product_name = $this->normalizeProductName($product['name']);
                    error_log("Karşılaştırılan ürün: " . $product['name']);
                    error_log("Normalize edilmiş ürün adı: " . $normalized_product_name);
                    
                    // Tam eşleşme kontrolü
                    if ($normalized_product_name === $normalized_search_name) {
                        error_log("Eşleşme bulundu!");
                        error_log("Ürün ID: " . $product['id']);
                        error_log("Ürün Adı: " . $product['name']);
                        return $product;
                    }
                }
                error_log("Hiçbir ürün eşleşmedi.");
            } else {
                error_log("Hiç ürün bulunamadı.");
            }
            
            error_log("========= ÜRÜN KONTROL TAMAMLANDI =========\n");
            return null;
            
        } catch (Exception $e) {
            error_log("Ürün kontrolü sırasında hata: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Ürün adını normalize et
     * @param string $name
     * @return string
     */
    private function normalizeProductName($name) {
        // Boşlukları temizle
        $name = trim($name);
        
        // HTML entityleri düzelt
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Özel karakterleri kaldır
        $name = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $name);
        
        // Türkçe karakterleri düzelt
        $tr = array('ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç');
        $eng = array('i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c');
        $name = str_replace($tr, $eng, $name);
        
        // Tüm karakterleri küçük harfe çevir
        $name = mb_strtolower($name, 'UTF-8');
        
        // Çoklu boşlukları tek boşluğa çevir
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Son bir kez trim
        return trim($name);
    }
    
    /**
     * Tekrar eden ürünleri getir
     * @return array
     */
    public function getDuplicateProducts() {
        return $this->duplicate_products;
    }

    /**
     * Tekrar eden ürün ekle
     * @param array $product_data Ürün bilgileri
     * @param array $existing_product Mevcut ürün bilgileri
     */
    private function addDuplicateProduct($product_data, $existing_product) {
        $this->duplicate_products[] = [
            'attempted_product' => $product_data,
            'existing_product' => $existing_product,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Ürün ekle
     * @param array $product_data
     * @return array|null
     */
    public function createProduct($product_data) {
        try {
            if (DEBUG_MODE) {
                error_log("\n========= ÜRÜN EKLEME BAŞLIYOR =========");
                error_log("Ürün Verisi: " . print_r($product_data, true));
            }
            
            // Önce aynı isimde ürün var mı kontrol et
            $existing_product = $this->checkProductExists($product_data['name']);
            
            if ($existing_product !== null) {
                if (DEBUG_MODE) {
                    error_log("Aynı isimde ürün zaten mevcut. Ürün ID: " . $existing_product['id']);
                    error_log("========= ÜRÜN EKLEME İPTAL EDİLDİ =========\n");
                }
                // Tekrar eden ürünü listeye ekle
                $this->addDuplicateProduct($product_data, $existing_product);
                throw new Exception("Bu isimde bir ürün zaten mevcut: " . $product_data['name']);
            }
            
            $result = $this->makeRequest('POST', '/products', $product_data);
            
            if (DEBUG_MODE) {
                error_log("Ürün Ekleme Sonucu: " . print_r($result, true));
                error_log("========= ÜRÜN EKLEME TAMAMLANDI =========\n");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Ürün eklenirken hata: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ürün güncelle
     * @param int $product_id
     * @param array $product_data
     * @return array|null
     */
    public function updateProduct($product_id, $product_data) {
        return $this->makeRequest('PUT', "/products/{$product_id}", $product_data);
    }
    
    /**
     * Ürün sil
     * @param int $product_id
     * @return array|null
     */
    public function deleteProduct($product_id) {
        return $this->makeRequest('DELETE', "/products/{$product_id}");
    }
    
    /**
     * Ürün getir
     * @param int $product_id
     * @return array|null
     */
    public function getProduct($product_id) {
        return $this->makeRequest('GET', "/products/{$product_id}");
    }
    
    /**
     * Kategori var mı kontrol et veya oluştur
     * @param string $category_name
     * @param int $parent_id
     * @param string $image_url
     * @return int Category ID
     */
    private function getOrCreateCategory($category_name, $parent_id = 0, $image_url = '') {
        try {
            if (DEBUG_MODE) {
                error_log("\n========= KATEGORİ İŞLEMİ BAŞLIYOR =========");
                error_log("Kategori adı: " . $category_name);
                error_log("Üst kategori ID: " . $parent_id);
                error_log("Resim URL: " . $image_url);
            }

            // Kategori adını normalize et
            $normalized_category_name = $this->normalizeString($category_name);

            if (DEBUG_MODE) {
                error_log("Normalize edilmiş kategori adı: " . $normalized_category_name);
            }

            // Önce mevcut kategoriyi doğrudan arama yap
            $search_response = $this->makeRequest('GET', '/products/categories', [
                'search' => $category_name,
                'parent' => $parent_id,
                'per_page' => 10
            ], true);

            if (!empty($search_response['data'])) {
                foreach ($search_response['data'] as $category) {
                    $normalized_found_name = $this->normalizeString($category['name']);
                    if ($normalized_found_name === $normalized_category_name && 
                        (int)$category['parent'] === (int)$parent_id) {
                        if (DEBUG_MODE) {
                            error_log("Doğrudan aramada kategori bulundu. ID: " . $category['id']);
                        }
                        return $category['id'];
                    }
                }
            }

            // Doğrudan bulunamadıysa tüm kategorileri kontrol et
            $all_categories = [];
            $page = 1;
            $per_page = 100;

            do {
                if (DEBUG_MODE) {
                    error_log("Sayfa " . $page . " yükleniyor...");
                }

                $response = $this->makeRequest('GET', '/products/categories', [
                    'per_page' => $per_page,
                    'page' => $page,
                    'orderby' => 'id',
                    'order' => 'asc'
                ], true);

                if (empty($response['data'])) {
                    break;
                }

                foreach ($response['data'] as $category) {
                    $normalized_cat_name = $this->normalizeString($category['name']);
                    $all_categories[$normalized_cat_name] = $category;
                }

                if (DEBUG_MODE) {
                    error_log("Sayfa " . $page . " yüklendi. Şu ana kadar toplam " . count($all_categories) . " kategori");
                }

                // Header'lardan toplam sayfa sayısını al
                $total_pages = isset($response['headers']) ? 
                    (int)$this->getHeaderValue($response['headers'], 'X-WP-TotalPages') : 
                    1;

                $page++;
            } while ($page <= $total_pages);

            if (DEBUG_MODE) {
                error_log("Toplam " . count($all_categories) . " kategori yüklendi");
            }

            // Normalize edilmiş isimle eşleşme ara
            if (isset($all_categories[$normalized_category_name])) {
                $existing_category = $all_categories[$normalized_category_name];
                
                if ((int)$existing_category['parent'] === (int)$parent_id) {
                    if (DEBUG_MODE) {
                        error_log("Mevcut kategori bulundu. ID: " . $existing_category['id']);
                    }
                    return $existing_category['id'];
                }
            }

            // Kategori bulunamadıysa oluştur
            try {
                $slug = $this->create_slug($category_name);
                
                $category_data = [
                    'name' => $category_name,
                    'slug' => $slug,
                    'parent' => $parent_id
                ];
                
                if (!empty($image_url)) {
                    $category_data['image'] = ['src' => $image_url];
                }
                
                $new_category = $this->makeRequest('POST', '/products/categories', $category_data);
                
                if (DEBUG_MODE) {
                    error_log("Yeni kategori oluşturuldu. ID: " . $new_category['id']);
                }
                
                return $new_category['id'];
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'term_exists') !== false) {
                    // Son bir kez daha arama yap
                    $final_search = $this->makeRequest('GET', '/products/categories', [
                        'search' => $category_name,
                        'parent' => $parent_id,
                        'per_page' => 1
                    ]);
                    
                    if (!empty($final_search[0])) {
                        if (DEBUG_MODE) {
                            error_log("Kategori son kontrolde bulundu. ID: " . $final_search[0]['id']);
                        }
                        return $final_search[0]['id'];
                    }
                }
                throw new Exception("Kategori işlemi başarısız: " . $e->getMessage());
            }
        } catch (Exception $e) {
            error_log("Kategori işlemi hatası: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * String normalize etme yardımcı fonksiyonu
     */
    private function normalizeString($str) {
        $str = trim($str);
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $str);
        
        // Türkçe karakterleri değiştir
        $tr = array('ş','Ş','ı','İ','ğ','Ğ','ü','Ü','ö','Ö','ç','Ç');
        $eng = array('s','s','i','i','g','g','u','u','o','o','c','c');
        $str = str_replace($tr, $eng, $str);
        
        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }
    
    /**
     * Header değerini alma yardımcı fonksiyonu
     */
    private function getHeaderValue($headers, $key) {
        if (preg_match("/$key: (\d+)/i", $headers, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
    
    /**
     * Slug formatında metin oluştur
     */
    private function create_slug($text) {
        // Türkçe karakterleri değiştir
        $tr = array('ş','Ş','ı','İ','ğ','Ğ','ü','Ü','ö','Ö','ç','Ç');
        $eng = array('s','s','i','i','g','g','u','u','o','o','c','c');
        $text = str_replace($tr, $eng, $text);
        
        // Küçük harfe çevir
        $text = mb_strtolower($text, 'UTF-8');
        
        // HTML etiketlerini kaldır
        $text = strip_tags($text);
        
        // & işaretini 've' ile değiştir
        $text = str_replace('&', 've', $text);
        
        // Alfanumerik olmayan karakterleri tire ile değiştir
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        
        // Birden fazla tireyi tek tireye indir
        $text = preg_replace('/-+/', '-', $text);
        
        // Baştaki ve sondaki tireleri kaldır
        $text = trim($text, '-');
        
        return $text;
    }
    
    /**
     * Marka özniteliğini kontrol et veya oluştur
     * @param string $brand_name
     * @return array Attribute data
     */
    private function getOrCreateBrandAttribute($brand_name) {
        try {
            if (DEBUG_MODE) {
                error_log("\n========= MARKA ÖZNİTELİĞİ İŞLEMİ BAŞLIYOR =========");
                error_log("Orijinal marka adı: " . $brand_name);
            }

            // Marka adını normalize et
            $brand_name = trim($brand_name);
            $brand_name = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $brand_name);
            $brand_name = html_entity_decode($brand_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $brand_name = preg_replace('/\s+/', ' ', $brand_name);
            $brand_name = trim($brand_name);

            if (DEBUG_MODE) {
                error_log("Normalize edilmiş marka adı: " . $brand_name);
            }

            // Önce 'Marka' özniteliğini ara
            $attributes = $this->makeRequest('GET', '/products/attributes', [
                'per_page' => 100
            ]);

            $brand_attribute_id = null;
            $brand_term_id = null;

            // 'Marka' özniteliğini bul
            foreach ($attributes as $attribute) {
                if (strtolower($attribute['name']) === 'marka') {
                    $brand_attribute_id = $attribute['id'];
                    if (DEBUG_MODE) {
                        error_log("Mevcut marka özniteliği bulundu. ID: " . $brand_attribute_id);
                    }
                    break;
                }
            }

            // 'Marka' özniteliği yoksa oluştur
            if (!$brand_attribute_id) {
                try {
                    $new_attribute = $this->makeRequest('POST', '/products/attributes', [
                        'name' => 'Marka',
                        'slug' => 'marka',
                        'type' => 'select',
                        'order_by' => 'menu_order',
                        'has_archives' => true
                    ]);
                    $brand_attribute_id = $new_attribute['id'];
                    if (DEBUG_MODE) {
                        error_log("Yeni marka özniteliği oluşturuldu. ID: " . $brand_attribute_id);
                    }
                } catch (Exception $e) {
                    error_log("Marka özniteliği oluşturma hatası: " . $e->getMessage());
                    throw $e;
                }
            }

            // Marka terimini ara
            try {
                $terms = $this->makeRequest('GET', "/products/attributes/{$brand_attribute_id}/terms", [
                    'per_page' => 100
                ]);

                // Tam eşleşme için kontrol
                foreach ($terms as $term) {
                    $term_name = trim($term['name']);
                    $term_name = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $term_name);
                    $term_name = html_entity_decode($term_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $term_name = preg_replace('/\s+/', ' ', $term_name);
                    $term_name = trim($term_name);

                    if (strtolower($term_name) === strtolower($brand_name)) {
                        $brand_term_id = $term['id'];
                        if (DEBUG_MODE) {
                            error_log("Mevcut marka terimi bulundu. ID: " . $brand_term_id);
                        }
                        break;
                    }
                }
            } catch (Exception $e) {
                error_log("Marka terimi arama hatası: " . $e->getMessage());
            }

            // Terim bulunamadıysa oluşturmayı dene
            if (!$brand_term_id) {
                try {
                    $new_term = $this->makeRequest('POST', "/products/attributes/{$brand_attribute_id}/terms", [
                        'name' => $brand_name
                    ]);
                    $brand_term_id = $new_term['id'];
                    if (DEBUG_MODE) {
                        error_log("Yeni marka terimi oluşturuldu. ID: " . $brand_term_id);
                    }
                } catch (Exception $e) {
                    // Eğer terim zaten varsa, tekrar aramayı dene
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        if (DEBUG_MODE) {
                            error_log("Terim zaten var hatası alındı, tekrar aranıyor...");
                        }
                        // Tüm terimleri al ve normalize ederek karşılaştır
                        $all_terms = $this->makeRequest('GET', "/products/attributes/{$brand_attribute_id}/terms", [
                            'per_page' => 100
                        ]);
                        foreach ($all_terms as $term) {
                            $term_name = trim($term['name']);
                            $term_name = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $term_name);
                            $term_name = html_entity_decode($term_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $term_name = preg_replace('/\s+/', ' ', $term_name);
                            $term_name = trim($term_name);

                            if (strtolower($term_name) === strtolower($brand_name)) {
                                $brand_term_id = $term['id'];
                                if (DEBUG_MODE) {
                                    error_log("Var olan marka terimi bulundu. ID: " . $brand_term_id);
                                }
                                break;
                            }
                        }
                    } else {
                        throw $e;
                    }
                }
            }

            if (!$brand_term_id) {
                throw new Exception("Marka terimi oluşturulamadı veya bulunamadı: " . $brand_name);
            }

            $result = [
                'id' => $brand_attribute_id,
                'term_id' => $brand_term_id,
                'name' => 'Marka',
                'value' => $brand_name
            ];

            if (DEBUG_MODE) {
                error_log("Marka işlemi tamamlandı: " . print_r($result, true));
                error_log("========= MARKA ÖZNİTELİĞİ İŞLEMİ TAMAMLANDI =========\n");
            }

            return $result;

        } catch (Exception $e) {
            error_log("Marka işlemi hatası: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * HepsiBurada'dan gelen veriyi WooCommerce formatına dönüştür
     * @param array $hb_product
     * @return array
     */
    public function formatProductData($hb_product) {
        if (DEBUG_MODE) {
            error_log("\n========= ÜRÜN VERİSİ BAŞLANGIÇ =========");
            error_log("Gelen ham veri: " . print_r($hb_product, true));
        }

        // Başlık kontrolü ve temizleme
        if (empty($hb_product['title'])) {
            throw new Exception("Ürün başlığı boş olamaz");
        }

        // Başlığı temizle
        $title = trim($hb_product['title']);
        // Özel karakterleri temizle
        $title = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $title);
        // HTML entityleri düzelt
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Çoklu boşlukları tek boşluğa çevir
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);

        if (DEBUG_MODE) {
            error_log("Orijinal başlık: " . $hb_product['title']);
            error_log("Temizlenmiş başlık: " . $title);
        }

        // Açıklama kontrolü
        $description = $hb_product['description'] ?? '';
        $short_description = !empty($description) ? mb_substr($description, 0, 200) . '...' : '';

        // Resim kontrolü ve düzenleme
        $images = [];
        if (!empty($hb_product['image_url'])) {
            // URL'yi temizle
            $image_url = str_replace(' ', '%20', $hb_product['image_url']);
            $images[] = ['src' => $image_url];
            if (DEBUG_MODE) {
                error_log("Ana resim eklendi: " . $image_url);
            }
        }

        if (!empty($hb_product['additional_images']) && is_array($hb_product['additional_images'])) {
            foreach ($hb_product['additional_images'] as $img_url) {
                if (!empty($img_url)) {
                    // URL'yi temizle
                    $img_url = str_replace(' ', '%20', $img_url);
                    $images[] = ['src' => $img_url];
                    if (DEBUG_MODE) {
                        error_log("Ek resim eklendi: " . $img_url);
                    }
                }
            }
        }
        if (!empty($hb_product['img_description']) && is_array($hb_product['img_description'])) {
            $description .= "\n\n"; // Açıklama ile resimler arasına boşluk ekle
            foreach ($hb_product['img_description'] as $img_url) {
                try {
                    if (!empty($img_url)) {
                        // URL'yi temizle
                        $img_url = str_replace(' ', '%20', $img_url);
                       
                        
                        // Uzantıyı kontrol et
                        $allowed_extensions = ['jpg', 'jpeg', 'png'];
                        $extension = strtolower(pathinfo($img_url, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowed_extensions)) {
                            if (DEBUG_MODE) {
                                error_log("Sadece jpg, jpeg ve png uzantılı resimlere izin verilmektedir: " . $img_url);
                            }
                        }
                        else{
                            
                            $result = $this->wp_imageUploader->uploadImage($img_url);                                                       
                           
                     if($result['status'] == 'success'){
                        $description .= sprintf('<img src="%s" alt="%s" class="product-description-image"  />\n', 
                                $result['url'], 
                                $title); 
                    }
                    else{
                        error_log("Resim yükleme hatası: " . $result['message']);
                    }

                        
                        if (DEBUG_MODE) {
                            error_log("Açıklama resmi eklendi json_encode(result['url'] " . json_encode($result['url'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        }
                        }
                        
                        
                    }
                } catch (Exception $e) {
                    error_log("Resim yükleme hatası: " . $e->getMessage());
                }   
            }
        }

        // Öznitelik kontrolü ve düzenleme
        $attributes = [];
        
        // Önce marka ekle (varsa)
        if (!empty($hb_product['brand'])) {
            try {
                $brand_attribute = $this->getOrCreateBrandAttribute($hb_product['brand']);
                $attributes[] = [
                    'id' => $brand_attribute['id'],
                    'name' => $brand_attribute['name'],
                    'visible' => true,
                    'variation' => false,
                    'options' => [$brand_attribute['value']]
                ];
                if (DEBUG_MODE) {
                    error_log("Marka eklendi: " . $hb_product['brand']);
                }
            } catch (Exception $e) {
                error_log("Marka eklenirken hata: " . $e->getMessage());
            }
        }

        // Diğer öznitelikleri ekle
        if (!empty($hb_product['description_table']) && is_array($hb_product['description_table'])) {
            foreach ($hb_product['description_table'] as $key => $value) {
                if (!empty($key) && !empty($value)) {
                    $key = trim($key);
                    $value = trim($value);
                    $attributes[] = [
                        'name' => $key,
                        'visible' => true,
                        'variation' => false,
                        'options' => [$value]
                    ];
                    if (DEBUG_MODE) {
                        error_log("Öznitelik eklendi: {$key} = {$value}");
                    }
                }
            }
        }

        // Kategori işleme
        try {
            $main_category = $hb_product['categories']['main_category'] ?? '';
            $sub_category = $hb_product['categories']['sub_category'] ?? '';

            if (empty($main_category) || empty($sub_category)) {
                throw new Exception("Kategori bilgileri eksik");
            }

            $main_category_id = $this->getOrCreateCategory($main_category, 0, $hb_product['image_url'] ?? '');
            $sub_category_id = $this->getOrCreateCategory($sub_category, $main_category_id, $hb_product['image_url'] ?? '');

            if (DEBUG_MODE) {
                error_log("Kategoriler oluşturuldu - Ana: {$main_category} ({$main_category_id}), Alt: {$sub_category} ({$sub_category_id})");
            }
        } catch (Exception $e) {
            error_log("Kategori oluşturulurken hata: " . $e->getMessage());
            throw $e;
        }

        // Ürün verisi oluştur
        $product_data = [
            'name' => $title,
            'type' => 'simple',
            'status' => 'publish',
            'catalog_visibility' => 'visible',
            'description' => $description,
            'short_description' => $short_description,
            'categories' => [
                ['id' => $main_category_id],
                ['id' => $sub_category_id]
            ],
            'images' => $images,
            'attributes' => $attributes,
            'meta_data' => [
                [
                    'key' => '_hepsiburada_url',
                    'value' => $hb_product['url'] ?? ''
                ]
            ]
        ];

        if (DEBUG_MODE) {
            error_log("\n========= WORDPRESS'E GÖNDERİLECEK VERİ =========");
            error_log(json_encode($product_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            error_log("================================================\n");
        }

        return $product_data;
    }
    
    /**
     * API isteği gönder
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param bool $include_headers Header bilgilerini dahil et
     * @return array|null
     */
    private function makeRequest($method, $endpoint, $data = null, $include_headers = false) {
        $url = $this->wp_api_url . $endpoint;
        
        if (DEBUG_MODE) {
            error_log("\n========= API İSTEĞİ BAŞLIYOR =========");
            error_log("URL: " . $url);
            error_log("Method: " . $method);
            if ($data !== null) {
                error_log("Gönderilen Veri: " . print_r($data, true));
            }
        }
        
        $ch = curl_init();
        
        // URL'de query string varsa, & ile ekle, yoksa ? ile başla
        if ($data !== null && $method === 'GET') {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        // Basic Auth
        curl_setopt($ch, CURLOPT_USERPWD, $this->consumer_key . ":" . $this->consumer_secret);
        
        if ($include_headers) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        
        if ($data !== null && $method !== 'GET') {
            $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
            
            if (DEBUG_MODE) {
                error_log("Gönderilen JSON: " . $json_data);
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (DEBUG_MODE) {
            error_log("HTTP Durum Kodu: " . $httpCode);
        }
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("CURL Hatası: " . $error);
        }
        
        if ($include_headers) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            curl_close($ch);
            
            $decoded = json_decode($body, true);
            
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("API yanıtı JSON formatında değil");
            }
            
            if ($httpCode >= 400) {
                error_log("API Hata Yanıtı: " . print_r($decoded, true));
                throw new Exception("API Hatası: " . ($decoded['message'] ?? 'Bilinmeyen hata'));
            }
            
            return [
                'data' => $decoded,
                'headers' => $headers
            ];
        }
        
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("API yanıtı JSON formatında değil");
        }
        
        if ($httpCode >= 400) {
            error_log("API Hata Yanıtı: " . print_r($decoded, true));
            throw new Exception("API Hatası: " . ($decoded['message'] ?? 'Bilinmeyen hata'));
        }
        
        return $decoded;
    }
    
    /**
     * Tüm ürünleri getir
     * @param int $page Sayfa numarası
     * @param int $per_page Sayfa başına ürün sayısı
     * @return array
     */
    public function getProducts($page = 1, $per_page = 100) {
        try {
            error_log("\n========= ÜRÜN LİSTESİ ALINIYOR =========");
            error_log("Sayfa: " . $page);
            error_log("Sayfa başına: " . $per_page);
            
            $params = [
                'page' => $page,
                'per_page' => $per_page,
                'status' => 'publish'
            ];
            
            error_log("API isteği yapılıyor: " . $this->wp_api_url . '/products?' . http_build_query($params));
            $result = $this->makeRequest('GET', '/products?' . http_build_query($params), null, true);
            error_log("API yanıtı: " . print_r($result, true));
            
            if (!isset($result['data'])) {
                error_log("API yanıtında 'data' alanı bulunamadı");
                throw new Exception("API yanıtı geçersiz format");
            }

            if (!isset($result['headers'])) {
                error_log("API yanıtında 'headers' alanı bulunamadı");
                throw new Exception("API yanıtı geçersiz format - headers yok");
            }

            // Header'lardan toplam ürün sayısını al
            $total = $this->getHeaderValue($result['headers'], 'X-WP-Total');
            error_log("Toplam ürün sayısı (header'dan): " . $total);

            if ($total === null) {
                error_log("Header'da toplam ürün sayısı bulunamadı");
                throw new Exception("Toplam ürün sayısı alınamadı");
            }
            
            $return_data = [
                'products' => $result['data'],
                'total' => (int)$total
            ];
            
            error_log("Dönüş verisi: " . print_r($return_data, true));
            error_log("========= ÜRÜN LİSTESİ TAMAMLANDI =========\n");
            
            return $return_data;
            
        } catch (Exception $e) {
            error_log("Ürün listesi alınırken hata: " . $e->getMessage());
            error_log("Hata detayı: " . $e->getTraceAsString());
            throw new Exception("Ürünler alınamadı: " . $e->getMessage());
        }
    }
} 