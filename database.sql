-- Ürünler tablosu
CREATE TABLE products
(
    id INT
    AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR
    (255) NOT NULL,
    price DECIMAL
    (10,2),
    description TEXT,
    seller VARCHAR
    (100),
    stock_status VARCHAR
    (50),
    rating DECIMAL
    (3,2),
    created_at DATETIME,
    updated_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- Ürün resimleri tablosu
    CREATE TABLE product_images
    (
        id INT
        AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    image_url VARCHAR
        (255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY
        (product_id) REFERENCES products
        (id) ON
        DELETE CASCADE
) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4;

        -- Ürün özellikleri tablosu
        CREATE TABLE product_specifications
        (
            id INT
            AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    spec_key VARCHAR
            (100),
    spec_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY
            (product_id) REFERENCES products
            (id) ON
            DELETE CASCADE
) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4;

            -- İndeksler
            CREATE INDEX idx_product_title ON products(title);
            CREATE INDEX idx_product_price ON products(price);
            CREATE INDEX idx_product_seller ON products(seller);
            CREATE INDEX idx_spec_key ON product_specifications(spec_key); 