<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php
    require_once 'config.php';
    require_once 'WooCommerceAPI.php';
    require_once 'SupabaseDB.php';
    require_once 'HepsiBurada.php';
    
    $woocommerce = new WooCommerceAPI();
    $supabase = new SupabaseDB();
    $hepsiburada = new HepsiBuradaAPI();
    
    $message = '';
    $messageType = '';
    $searchResults = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            try {
                switch ($_POST['action']) {
                    case 'test_woo':
                        $result = $woocommerce->getProducts();
                        $message = 'WooCommerce bağlantısı başarılı! Ürün sayısı: ' . count($result);
                        $messageType = 'success';
                        break;
                    case 'test_supabase':
                        $result = $supabase->testConnection();
                        $message = 'Supabase bağlantısı başarılı!';
                        $messageType = 'success';
                        break;
                    case 'sync_products':
                        $products = $woocommerce->getProducts();
                        foreach ($products as $product) {
                            $supabase->insertProduct($product);
                        }
                        $message = 'Ürünler başarıyla senkronize edildi!';
                        $messageType = 'success';
                        break;
                    case 'hepsiburada_search':
                        if (empty($_POST['search_term'])) {
                            throw new Exception('Arama terimi gerekli!');
                        }
                        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
                        $results = $hepsiburada->search($_POST['search_term'], $page);
                        if ($results['success']) {
                            $searchResults = $results['products'];
                            $message = count($searchResults) . ' ürün bulundu.';
                            $messageType = 'success';
                        } else {
                            throw new Exception('Arama başarısız: ' . $results['error']);
                        }
                        break;
                    case 'hepsiburada_import':
                        if (!isset($_POST['product_data'])) {
                            throw new Exception('Ürün verisi gerekli!');
                        }
                        $productData = json_decode($_POST['product_data'], true);
                        
                        // Supabase'e kaydet
                        $supabaseResult = $supabase->insertProduct([
                            'name' => $productData['title'],
                            'price' => $productData['price'],
                            'description' => $productData['title'],
                            'image_url' => $productData['image'],
                            'source' => 'hepsiburada',
                            'source_id' => $productData['id'],
                            'source_url' => $productData['url']
                        ]);
                        
                        // WordPress'e ekle
                        $wpResult = $woocommerce->addProduct([
                            'name' => $productData['title'],
                            'type' => 'simple',
                            'regular_price' => (string)$productData['price'],
                            'description' => $productData['title'],
                            'short_description' => $productData['title'],
                            'images' => [
                                ['src' => $productData['image']]
                            ]
                        ]);
                        
                        $message = 'Ürün başarıyla içe aktarıldı!';
                        $messageType = 'success';
                        break;
                }
            } catch (Exception $e) {
                $message = 'Hata: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
    ?>

    <div class="container py-5">
        <h1 class="text-center mb-5">API Test Paneli</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- WooCommerce Test -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fab fa-wordpress me-2"></i>WooCommerce Test</h5>
                        <p class="card-text">WooCommerce API bağlantısını test edin.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="test_woo">
                            <button type="submit" class="btn btn-primary">Test Et</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Supabase Test -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-database me-2"></i>Supabase Test</h5>
                        <p class="card-text">Supabase veritabanı bağlantısını test edin.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="test_supabase">
                            <button type="submit" class="btn btn-primary">Test Et</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Senkronizasyon -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-sync me-2"></i>Ürün Senkronizasyonu</h5>
                        <p class="card-text">WooCommerce ürünlerini Supabase'e aktarın.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="sync_products">
                            <button type="submit" class="btn btn-success">Senkronize Et</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- HepsiBurada Arama -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-search me-2"></i>HepsiBurada Ürün Arama</h5>
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="action" value="hepsiburada_search">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <input type="text" name="search_term" class="form-control" placeholder="Ürün adı girin..." required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="page" class="form-control" value="1" min="1">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Ara</button>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($searchResults)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Resim</th>
                                        <th>Başlık</th>
                                        <th>Fiyat</th>
                                        <th>Marka</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($searchResults as $product): ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($product['image']); ?>" alt="" style="max-width: 50px;"></td>
                                        <td><?php echo htmlspecialchars($product['title']); ?></td>
                                        <td><?php echo htmlspecialchars($product['price']); ?></td>
                                        <td><?php echo htmlspecialchars($product['brand']); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="hepsiburada_import">
                                                <input type="hidden" name="product_data" value='<?php echo htmlspecialchars(json_encode($product)); ?>'>
                                                <button type="submit" class="btn btn-sm btn-success">İçe Aktar</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 