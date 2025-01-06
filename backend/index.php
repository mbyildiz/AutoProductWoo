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
    
    $woocommerce = new WooCommerceAPI();
    $supabase = new SupabaseDB();
    
    $message = '';
    $messageType = '';
    
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 