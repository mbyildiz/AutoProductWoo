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
    <style>
        .specs-table {
            font-size: 0.85rem;
        }
        .specs-table td {
            padding: 0.25rem 0.5rem !important;
        }
        .specs-table td:first-child {
            white-space: nowrap;
            background-color: #f8f9fa;
            width: 40%;
        }
        .specs-wrapper {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .specs-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        .specs-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .specs-wrapper::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .specs-wrapper::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        /* Resim stilleri */
        .product-image {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 2px;
            background: white;
        }
        .additional-images {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }
        .additional-images img {
            border: 1px solid #dee2e6;
            border-radius: 3px;
            transition: transform 0.2s;
        }
        .additional-images img:hover {
            transform: scale(1.1);
        }
        .image-count-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
        }
        .product-images-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .image-section {
            background: white;
            padding: 8px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .main-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            background: white;
            padding: 4px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            display: block;
            margin: 0 auto;
        }
        .additional-images-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            max-width: 120px;
            margin: 0 auto;
        }
        .additional-image {
            width: 35px;
            height: 35px;
            object-fit: cover;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 2px;
            background: white;
            transition: transform 0.2s;
        }
        .additional-image:hover {
            transform: scale(1.5);
            z-index: 1;
        }
        .image-count {
            grid-column: span 3;
            text-align: center;
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 4px;
        }
    </style>
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
                                <div class="col-md-10">
                                    <input type="text" name="search_term" class="form-control" placeholder="Ürün adı girin..." required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Ara ve Kaydet</button>
                                </div>
                            </div>
                        </form>

                        <?php if (isset($_SESSION['search_results'])): ?>
                        <?php if (isset($_SESSION['search_results']['processed_count'])): ?>
                        <div class="alert alert-info mb-3">
                            <h6 class="mb-2">İşlem Özeti:</h6>
                            <div class="small">
                                <div>Toplam İşlenen Ürün: <?php echo $_SESSION['search_results']['processed_count']; ?></div>
                                <div>Batch Sayısı: <?php echo $_SESSION['search_results']['batch_count']; ?></div>
                                <div>Toplam Bulunan Ürün: <?php echo $_SESSION['search_results']['total']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Durum</th>
                                        <th>Başlık</th>
                                        <th>Marka</th>
                                        <th>Kategori</th>
                                        <th>Fiyat</th>
                                        <th>Özellikler</th>
                                        <th>Resim</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['search_results'] as $product): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($product['title'])): ?>
                                                <i class="fas fa-check text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['title'])): ?>
                                                <?php echo htmlspecialchars($product['title']); ?>
                                            <?php else: ?>
                                                <span class="text-danger">Başlık alınamadı</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['brand'])): ?>
                                                <?php echo htmlspecialchars($product['brand']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['categories']['main_category'])): ?>
                                                <?php echo htmlspecialchars($product['categories']['main_category']); ?>
                                                <?php if (!empty($product['categories']['sub_category'])): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($product['categories']['sub_category']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['price'])): ?>
                                                <?php echo htmlspecialchars($product['price']); ?> TL
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['description_table'])): ?>
                                                <div class="specs-wrapper">
                                                    <table class="table table-sm mb-0 specs-table">
                                                        <tbody>
                                                            <?php foreach ($product['description_table'] as $key => $value): ?>
                                                                <tr>
                                                                    <td class="fw-bold"><?php echo htmlspecialchars($key); ?></td>
                                                                    <td><?php echo htmlspecialchars($value); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($product['image_url'])): ?>
                                                <div class="product-images-container">
                                                    <!-- Ana Resim -->
                                                    <div class="image-section">
                                                        <div class="section-title">Ana Resim:</div>
                                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                             alt="" 
                                                             class="main-image"
                                                             title="Ana Ürün Görseli">
                                                    </div>
                                                    
                                                    <!-- Ek Resimler -->
                                                    <?php if (!empty($product['additional_images'])): ?>
                                                        <div class="image-section">
                                                            <div class="section-title">Ürün Resimleri:</div>
                                                            <div class="additional-images-container">
                                                                <?php 
                                                                $additionalImages = array_slice($product['additional_images'], 0, 3);
                                                                foreach ($additionalImages as $index => $img): 
                                                                ?>
                                                                    <img src="<?php echo htmlspecialchars($img); ?>" 
                                                                         alt="" 
                                                                         class="additional-image"
                                                                         title="Ürün Resmi <?php echo $index + 1; ?>">
                                                                <?php endforeach; ?>
                                                                
                                                                <?php if (count($product['additional_images']) > 3): ?>
                                                                    <div class="image-count">
                                                                        +<?php echo count($product['additional_images']) - 3; ?> ürün resmi
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Açıklama Resimleri -->
                                                    <?php if (!empty($product['img_description'])): ?>
                                                        <div class="image-section">
                                                            <div class="section-title">Açıklama Resimleri:</div>
                                                            <div class="additional-images-container">
                                                                <?php 
                                                                $descImages = array_slice($product['img_description'], 0, 3);
                                                                foreach ($descImages as $index => $img): 
                                                                ?>
                                                                    <img src="<?php echo htmlspecialchars($img); ?>" 
                                                                         alt="" 
                                                                         class="additional-image"
                                                                         title="Açıklama Resmi <?php echo $index + 1; ?>">
                                                                <?php endforeach; ?>
                                                                
                                                                <?php if (count($product['img_description']) > 3): ?>
                                                                    <div class="image-count">
                                                                        +<?php echo count($product['img_description']) - 3; ?> açıklama resmi
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center">
                                                    <i class="fas fa-image text-muted" style="font-size: 2rem;"></i>
                                                    <div class="small text-muted mt-1">Resim yok</div>
                                                </div>
                                            <?php endif; ?>
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