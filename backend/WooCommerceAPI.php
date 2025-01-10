<?php

class WooCommerceAPI {
    private $consumer_key;
    private $consumer_secret;
    private $wp_api_url;
    private $process_times = []; // İşlem sürelerini tutacak dizi
    
    public function __construct() {
        $this->consumer_key = WP_CONSUMER_KEY;
        $this->consumer_secret = WP_CONSUMER_SECRET;
        $this->wp_api_url = WP_API_URL;
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
            $category_name = trim($category_name);
            $category_name = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $category_name);
            $category_name = html_entity_decode($category_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $category_name = preg_replace('/\s+/', ' ', $category_name);
            $category_name = trim($category_name);

            if (DEBUG_MODE) {
                error_log("Normalize edilmiş kategori adı: " . $category_name);
            }

            // Tüm kategorileri tek seferde al
            $all_categories = [];
            $page = 1;
            $per_page = 100;

            do {
                $categories = $this->makeRequest('GET', '/products/categories', [
                    'per_page' => $per_page,
                    'page' => $page
                ]);
                
                if (!empty($categories)) {
                    $all_categories = array_merge($all_categories, $categories);
                }
                
                $page++;
            } while (!empty($categories) && count($categories) === $per_page);

            if (DEBUG_MODE) {
                error_log("Toplam " . count($all_categories) . " kategori yüklendi");
            }

            // Önce tam eşleşme ara
            foreach ($all_categories as $category) {
                $cat_name = trim($category['name']);
                $cat_name = str_replace(['&', '+', '/', '\\', '@', '#', '$', '%', '^', '*', '='], ' ', $cat_name);
                $cat_name = html_entity_decode($cat_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $cat_name = preg_replace('/\s+/', ' ', $cat_name);
                $cat_name = trim($cat_name);

                if (strtolower($cat_name) === strtolower($category_name) && 
                    (int)$category['parent'] === (int)$parent_id) {
                    if (DEBUG_MODE) {
                        error_log("Mevcut kategori bulundu. ID: " . $category['id']);
                        error_log("Kategori adı: " . $category['name']);
                        error_log("Üst kategori ID: " . $category['parent']);
                    }
                    
                    // Eğer resim URL'si varsa ve kategorinin resmi yoksa, resmi güncelle
                    if (!empty($image_url) && empty($category['image'])) {
                        try {
                            $this->makeRequest('PUT', "/products/categories/{$category['id']}", [
                                'image' => ['src' => $image_url]
                            ]);
                            if (DEBUG_MODE) {
                                error_log("Kategori resmi güncellendi: " . $image_url);
                            }
                        } catch (Exception $e) {
                            error_log("Kategori resmi güncellenirken hata: " . $e->getMessage());
                        }
                    }
                    
                    return $category['id'];
                }
            }

            // Kategori bulunamadıysa oluşturmayı dene
            try {
                $slug = $this->create_slug($category_name);
                
                $category_data = [
                    'name' => $category_name,
                    'slug' => $slug,
                    'parent' => $parent_id
                ];
                
                // Eğer resim URL'si varsa ekle
                if (!empty($image_url)) {
                    $category_data['image'] = ['src' => $image_url];
                }
                
                $new_category = $this->makeRequest('POST', '/products/categories', $category_data);
                
                if (DEBUG_MODE) {
                    error_log("Yeni kategori oluşturuldu:");
                    error_log("ID: " . $new_category['id']);
                    error_log("Ad: " . $new_category['name']);
                    error_log("Slug: " . $new_category['slug']);
                    error_log("Üst ID: " . $new_category['parent']);
                    if (!empty($image_url)) {
                        error_log("Resim URL: " . $image_url);
                    }
                }
                
                return $new_category['id'];
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    if (DEBUG_MODE) {
                        error_log("Kategori zaten var hatası alındı, slug ile aranıyor...");
                    }
                    
                    // Slug ile tekrar ara
                    foreach ($all_categories as $category) {
                        if ($category['slug'] === $slug && (int)$category['parent'] === (int)$parent_id) {
                            if (DEBUG_MODE) {
                                error_log("Kategori slug ile bulundu. ID: " . $category['id']);
                            }
                            
                            // Eğer resim URL'si varsa ve kategorinin resmi yoksa, resmi güncelle
                            if (!empty($image_url) && empty($category['image'])) {
                                try {
                                    $this->makeRequest('PUT', "/products/categories/{$category['id']}", [
                                        'image' => ['src' => $image_url]
                                    ]);
                                    if (DEBUG_MODE) {
                                        error_log("Kategori resmi güncellendi: " . $image_url);
                                    }
                                } catch (Exception $e) {
                                    error_log("Kategori resmi güncellenirken hata: " . $e->getMessage());
                                }
                            }
                            
                            return $category['id'];
                        }
                    }
                }
                
                throw new Exception("Kategori oluşturulamadı ve mevcut kategori bulunamadı: " . $e->getMessage());
            }
        } catch (Exception $e) {
            error_log("Kategori işlemi hatası: " . $e->getMessage());
            throw $e;
        }
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
     * @return array|null
     */
    private function makeRequest($method, $endpoint, $data = null) {
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
        
        if ($data !== null) {
            $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
            
            if (DEBUG_MODE) {
                error_log("Gönderilen JSON: " . $json_data);
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlInfo = curl_getinfo($ch);
        
        if (DEBUG_MODE) {
            error_log("HTTP Durum Kodu: " . $httpCode);
            error_log("CURL Bilgileri: " . print_r($curlInfo, true));
            error_log("API Yanıtı: " . $response);
        }
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("CURL Hatası: " . $error);
            curl_close($ch);
            throw new Exception("CURL Hatası: " . $error);
        }
        
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Hatası: " . json_last_error_msg());
            error_log("Ham Yanıt: " . $response);
            throw new Exception("API yanıtı JSON formatında değil");
        }
        
        if ($httpCode >= 400) {
            error_log("API Hata Yanıtı: " . print_r($decoded, true));
            throw new Exception("API Hatası: " . ($decoded['message'] ?? 'Bilinmeyen hata'));
        }
        
        if (DEBUG_MODE) {
            error_log("İşlem Başarılı - Yanıt: " . print_r($decoded, true));
            error_log("========= API İSTEĞİ TAMAMLANDI =========\n");
        }
        
        return $decoded;
    }
} 