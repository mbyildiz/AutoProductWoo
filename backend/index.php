<?php
session_start();
?>
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
    <div class="container py-5">
        <h1 class="text-center mb-5">API Test Paneli</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['message'];
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
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
                        <form action="api.php" method="POST">
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
                        <form action="api.php" method="POST">
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
                        <form action="api.php" method="POST">
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
                        <form action="api.php" method="POST" class="mb-4">
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

                        <?php if (isset($_SESSION['search_results'])): ?>
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
                                    <?php foreach ($_SESSION['search_results'] as $product): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="" style="max-width: 50px;">
                                            <?php if (!empty($product['images'])): ?>
                                                <div class="mt-2">
                                                    <?php foreach (array_slice($product['images'], 0, 3) as $img): ?>
                                                        <img src="<?php echo htmlspecialchars($img); ?>" alt="" style="max-width: 30px; margin-right: 2px;">
                                                    <?php endforeach; ?>
                                                    <?php if (count($product['images']) > 3): ?>
                                                        <small>(+<?php echo count($product['images']) - 3; ?> resim)</small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['title']); ?></strong>
                                            <?php if (!empty($product['description'])): ?>
                                                <div class="small text-muted mt-1">
                                                    <?php echo nl2br(htmlspecialchars(substr($product['description'], 0, 200))); ?>
                                                    <?php if (strlen($product['description']) > 200): ?>...<?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="small">
                                                <strong>ID:</strong> <?php echo htmlspecialchars($product['id']); ?>
                                                <br>
                                                <strong>URL:</strong> <a href="<?php echo htmlspecialchars($product['url']); ?>" target="_blank">Ürünü Gör</a>
                                                <br>
                                                <?php echo htmlspecialchars($product['description']); ?>
                                                <br>
                                                <?php echo !empty($product['images']) ? htmlspecialchars($product['images'][0]) : 'Description da resim bulunamadı'; ?>
                                                
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['price']); ?> TL</td>
                                        <td><?php echo htmlspecialchars($product['brand']); ?></td>
                                        <td>
                                            <form action="api.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="hepsiburada_import">
                                                <input type="hidden" name="product_data" value='<?php echo htmlspecialchars(json_encode($product)); ?>'>
                                                <button type="submit" class="btn btn-sm btn-success">İçe Aktar</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-info mt-1" onclick='showProductDetails(<?php echo json_encode($product); ?>)'>
                                                Detaylar
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php 
                        unset($_SESSION['search_results']);
                        endif; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ürün Detayları Modal -->
    <div class="modal fade" id="productDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ürün Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="productDetails"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function showProductDetails(product) {
        const modal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
        const detailsDiv = document.getElementById('productDetails');
        
        let html = `
            <div class="row">
                <div class="col-md-4">
                    <img src="${product.image}" class="img-fluid mb-3" alt="">
                    ${product.images ? `
                        <div class="row g-2">
                            ${product.images.map(img => `
                                <div class="col-4">
                                    <img src="${img}" class="img-fluid" alt="">
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
                <div class="col-md-8">
                    <h4>${product.title}</h4>
                    <p class="text-muted">${product.brand}</p>
                    <h5 class="text-primary">${product.price} TL</h5>
                    ${product.description ? `
                        <hr>
                        <h6>Ürün Açıklaması:</h6>
                        <p>${product.description}</p>
                    ` : ''}
                    <hr>
                    <p><strong>Ürün ID:</strong> ${product.id}</p>
                    <p><strong>Ürün URL:</strong> <a href="${product.url}" target="_blank">HepsiBurada'da Gör</a></p>
                </div>
            </div>
        `;
        
        detailsDiv.innerHTML = html;
        modal.show();
    }
    </script>
</body>
</html> 