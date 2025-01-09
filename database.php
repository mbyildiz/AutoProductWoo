<?php

class Database {
    private $conn;
    
    public function __construct($host, $dbname, $username, $password) {
        try {
            $this->conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Bağlantı hatası: " . $e->getMessage());
        }
    }
    
    public function saveProduct($product) {
        try {
            // Ana ürün bilgilerini kaydet
            $stmt = $this->conn->prepare("INSERT INTO products (title, price, description, seller, stock_status, rating, created_at) 
                                        VALUES (:title, :price, :description, :seller, :stock_status, :rating, NOW())");
            
            $stmt->execute([
                ':title' => $product['title'],
                ':price' => $product['price'],
                ':description' => $product['description'],
                ':seller' => $product['seller'],
                ':stock_status' => $product['stock_status'],
                ':rating' => $product['rating']
            ]);
            
            $productId = $this->conn->lastInsertId();
            
            // Resimleri kaydet
            $stmt = $this->conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (:product_id, :image_url)");
            foreach ($product['images'] as $image) {
                $stmt->execute([
                    ':product_id' => $productId,
                    ':image_url' => $image
                ]);
            }
            
            // Özellikleri kaydet
            $stmt = $this->conn->prepare("INSERT INTO product_specifications (product_id, spec_key, spec_value) 
                                        VALUES (:product_id, :spec_key, :spec_value)");
            foreach ($product['specifications'] as $key => $value) {
                $stmt->execute([
                    ':product_id' => $productId,
                    ':spec_key' => $key,
                    ':spec_value' => $value
                ]);
            }
            
            return $productId;
            
        } catch(PDOException $e) {
            die("Kayıt hatası: " . $e->getMessage());
        }
    }
    
    public function getProduct($productId) {
        try {
            // Ana ürün bilgilerini al
            $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                return null;
            }
            
            // Resimleri al
            $stmt = $this->conn->prepare("SELECT image_url FROM product_images WHERE product_id = :product_id");
            $stmt->execute([':product_id' => $productId]);
            $product['images'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Özellikleri al
            $stmt = $this->conn->prepare("SELECT spec_key, spec_value FROM product_specifications WHERE product_id = :product_id");
            $stmt->execute([':product_id' => $productId]);
            $specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $product['specifications'] = [];
            foreach ($specs as $spec) {
                $product['specifications'][$spec['spec_key']] = $spec['spec_value'];
            }
            
            return $product;
            
        } catch(PDOException $e) {
            die("Sorgulama hatası: " . $e->getMessage());
        }
    }
}

?> 