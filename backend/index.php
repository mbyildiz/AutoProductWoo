<?php
session_start();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HepsiBurada Ürün Aktarma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        .product-card {
            height: 100%;
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 200px;
            object-fit: contain;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .import-progress {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 300px;
            display: none;
            z-index: 9998;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Bağlantı Test Kartları -->
        <div class="row mb-4">
            <!-- WordPress Test -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fab fa-wordpress text-primary me-2"></i>WordPress Bağlantısı
                        </h5>
                        <p class="card-text text-muted small">WooCommerce API bağlantısını test edin ve mevcut ürün sayısını görün.</p>
                        <div class="d-flex align-items-center mt-3">
                            <button id="testWordPress" class="btn btn-primary me-2">
                                <i class="fas fa-plug"></i> Bağlantıyı Test Et
                            </button>
                            <div id="wpStatus" class="small"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supabase Test -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-database text-success me-2"></i>Supabase Bağlantısı
                        </h5>
                        <p class="card-text text-muted small">Supabase veritabanı bağlantısını test edin ve tablo durumunu kontrol edin.</p>
                        <div class="d-flex align-items-center mt-3">
                            <button id="testSupabase" class="btn btn-success me-2">
                                <i class="fas fa-plug"></i> Bağlantıyı Test Et
                            </button>
                            <div id="supabaseStatus" class="small"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sistem Durumu -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-server text-info me-2"></i>Sistem Durumu
                        </h5>
                        <div class="mt-3 small">
                            <div class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>PHP Sürümü: <span class="text-muted"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>Bellek Limiti: <span class="text-muted"><?php echo ini_get('memory_limit'); ?></span>
                            </div>
                            <div>
                                <i class="fas fa-check-circle text-success me-2"></i>Max Execution Time: <span class="text-muted"><?php echo ini_get('max_execution_time'); ?>s</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Arama Formu -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <form id="searchForm" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" id="search" name="search" placeholder="Ürün ara..." required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control" id="page" name="page" value="1" min="1" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" class="form-control" id="limit" name="limit" value="40" min="1" max="100" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Ara</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toplu İşlem Butonları -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="btn-group">
                    <button id="selectAll" class="btn btn-outline-primary">Tümünü Seç</button>
                    <button id="deselectAll" class="btn btn-outline-secondary">Seçimi Kaldır</button>
                    <button id="importSelected" class="btn btn-success" disabled>
                        <i class="fas fa-file-import"></i> Seçilenleri WordPress'e Aktar
                    </button>
                </div>
            </div>
        </div>

        <!-- Ürün Listesi -->
        <div class="row" id="productList">
            <!-- Ürünler buraya JavaScript ile eklenecek -->
        </div>
    </div>

    <!-- Yükleniyor Göstergesi -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Yükleniyor...</span>
            </div>
            <h5 id="loadingText">Yükleniyor...</h5>
        </div>
    </div>

    <!-- İlerleme Bildirimi -->
    <div class="import-progress" id="importProgress">
        <div class="alert alert-info">
            <h6 class="alert-heading">Aktarım Durumu</h6>
            <div class="progress mb-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
            </div>
            <small class="text-muted">
                Aktarılan: <span id="importedCount">0</span> / <span id="totalCount">0</span>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global değişkenler
        let selectedProducts = new Set();
        let currentProducts = [];

        // DOM elementleri
        const searchForm = document.getElementById('searchForm');
        const productList = document.getElementById('productList');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const importProgress = document.getElementById('importProgress');
        const selectAllBtn = document.getElementById('selectAll');
        const deselectAllBtn = document.getElementById('deselectAll');
        const importSelectedBtn = document.getElementById('importSelected');
        const testWordPressBtn = document.getElementById('testWordPress');
        const testSupabaseBtn = document.getElementById('testSupabase');
        const wpStatus = document.getElementById('wpStatus');
        const supabaseStatus = document.getElementById('supabaseStatus');

        // WordPress bağlantı testi
        testWordPressBtn.addEventListener('click', async () => {
            try {
                testWordPressBtn.disabled = true;
                wpStatus.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Test ediliyor...</span>';
                
                const response = await fetch('test_connection.php?type=wordpress');
                const data = await response.json();
                
                if (data.success) {
                    wpStatus.innerHTML = `
                        <span class="text-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Bağlantı başarılı! (${data.product_count} ürün)
                        </span>`;
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                wpStatus.innerHTML = `
                    <span class="text-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${error.message}
                    </span>`;
            } finally {
                testWordPressBtn.disabled = false;
            }
        });

        // Supabase bağlantı testi
        testSupabaseBtn.addEventListener('click', async () => {
            try {
                testSupabaseBtn.disabled = true;
                supabaseStatus.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Test ediliyor...</span>';
                
                const response = await fetch('test_connection.php?type=supabase');
                const data = await response.json();
                
                if (data.success) {
                    supabaseStatus.innerHTML = `
                        <span class="text-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Bağlantı başarılı!
                        </span>`;
                } else {
                    throw new Error(data.error);
                }
            } catch (error) {
                supabaseStatus.innerHTML = `
                    <span class="text-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        ${error.message}
                    </span>`;
            } finally {
                testSupabaseBtn.disabled = false;
            }
        });

        // Ürün kartı HTML'i oluştur
        function createProductCard(product) {
            return `
                <div class="col-md-3 mb-4">
                    <div class="card product-card h-100">
                        <div class="form-check position-absolute m-2">
                            <input class="form-check-input product-checkbox" type="checkbox" 
                                   value="${product.id}" id="product_${product.id}">
                        </div>
                        <img src="${product.image_url}" class="card-img-top product-image p-2" alt="${product.title}">
                        <div class="card-body">
                            <h6 class="card-title">${product.title}</h6>
                            <p class="card-text small text-muted">
                                <strong>Marka:</strong> ${product.brand}<br>
                                <strong>Kategori:</strong> ${product.categories.main_category} > ${product.categories.sub_category}
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="${product.url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i> HepsiBurada'da Gör
                            </a>
                        </div>
                    </div>
                </div>`;
        }

        // Yükleniyor göstergesini göster/gizle
        function toggleLoading(show, text = 'Yükleniyor...') {
            loadingOverlay.style.display = show ? 'flex' : 'none';
            document.getElementById('loadingText').textContent = text;
        }

        // İlerleme çubuğunu güncelle
        function updateProgress(current, total) {
            const percentage = (current / total) * 100;
            document.querySelector('.progress-bar').style.width = percentage + '%';
            document.getElementById('importedCount').textContent = current;
            document.getElementById('totalCount').textContent = total;
        }

        // Ürünleri WordPress'e aktar
        async function importToWordPress(products) {
            try {
                importProgress.style.display = 'block';
                updateProgress(0, products.length);

                const searchParams = new URLSearchParams({
                    search: document.getElementById('search').value,
                    page: '1',
                    limit: products.length.toString()
                });

                const response = await fetch(`import_products.php?${searchParams.toString()}`);
                const result = await response.json();

                if (result.success) {
                    // Başarılı aktarım mesajını oluştur
                    let message = `
                        Aktarım Tamamlandı!
                        Toplam Ürün: ${result.total_products}
                        Başarılı: ${result.imported_products}
                        Başarısız: ${result.failed_products}
                        Tekrar Eden: ${result.duplicate_products}
                        
                        Toplam Süre: ${result.process_times.total_duration}
                        Crawler Süresi: ${result.process_times.crawler_duration}
                        WordPress Süresi: ${result.process_times.wordpress_duration}
                    `;

                    // Eğer tekrar eden ürünler varsa, detayları ekle
                    if (result.duplicate_products > 0 && result.duplicate_product_list) {
                        message += "\n\nTekrar Eden Ürünler:";
                        result.duplicate_product_list.forEach((item, index) => {
                            message += `\n${index + 1}. ${item.attempted_product.name}`;
                        });
                    }

                    // Önce loading ve progress göstergelerini kapat
                    importProgress.style.display = 'none';
                    toggleLoading(false);

                    // Sonra alert mesajını göster
                    setTimeout(() => {
                        alert(message);
                    }, 100);

                } else {
                    throw new Error(result.error || 'Aktarım sırasında bir hata oluştu');
                }
            } catch (error) {
                // Hata durumunda da aynı sıralamayı uygula
                importProgress.style.display = 'none';
                toggleLoading(false);
                
                setTimeout(() => {
                    alert('Hata: ' + error.message);
                }, 100);
            }
        }

        // Tümünü seç/kaldır butonları
        selectAllBtn.addEventListener('click', () => {
            // Önce Set'i temizle
            selectedProducts.clear();
            
            // Mevcut ürünlerin ID'lerini Set'e ekle
            currentProducts.forEach(product => {
                selectedProducts.add(product.id.toString());
            });
            
            // Checkbox'ları güncelle
            document.querySelectorAll('.product-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            
            console.log('Seçilen ürün sayısı:', selectedProducts.size);
            console.log('Seçilen ürünler:', Array.from(selectedProducts));
            console.log('Mevcut ürünler:', currentProducts.length);
            
            // Import butonunu güncelle
            importSelectedBtn.disabled = selectedProducts.size === 0;
        });

        deselectAllBtn.addEventListener('click', () => {
            // Tüm checkbox'ları temizle
            document.querySelectorAll('.product-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Set'i temizle
            selectedProducts.clear();
            
            console.log('Seçim kaldırıldı. Seçilen ürün sayısı:', selectedProducts.size);
            
            // Import butonunu devre dışı bırak
            importSelectedBtn.disabled = true;
        });

        // Form gönderildiğinde
        searchForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            toggleLoading(true);

            // Yeni arama başladığında seçimleri temizle
            selectedProducts.clear();
            importSelectedBtn.disabled = true;

            const formData = new FormData(this);
            const searchParams = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                searchParams.append(key, value);
            }

            try {
                const response = await fetch(`api.php?${searchParams.toString()}`);
                const data = await response.json();

                if (data.success && Array.isArray(data.products)) {
                    currentProducts = data.products;
                    console.log('Yüklenen ürün sayısı:', currentProducts.length);
                    
                    if (currentProducts.length === 0) {
                        productList.innerHTML = '<div class="col-12"><div class="alert alert-warning">Ürün bulunamadı</div></div>';
                    } else {
                        productList.innerHTML = currentProducts.map(createProductCard).join('');
                        
                        // Checkbox event listener'ları ekle
                        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                if (this.checked) {
                                    selectedProducts.add(this.value);
                                    console.log('Ürün seçildi:', this.value);
                                } else {
                                    selectedProducts.delete(this.value);
                                    console.log('Ürün seçimi kaldırıldı:', this.value);
                                }
                                console.log('Toplam seçili ürün:', selectedProducts.size);
                                importSelectedBtn.disabled = selectedProducts.size === 0;
                            });
                        });
                    }
                } else {
                    throw new Error(data.error || 'Ürünler alınamadı');
                }
            } catch (error) {
                console.error('API Hatası:', error);
                productList.innerHTML = `<div class="col-12"><div class="alert alert-danger">Ürünler yüklenirken bir hata oluştu: ${error.message}</div></div>`;
            } finally {
                toggleLoading(false);
            }
        });

        // WordPress'e aktar butonu
        importSelectedBtn.addEventListener('click', async () => {
            if (selectedProducts.size === 0) return;

            const selectedProductsArray = currentProducts.filter(p => selectedProducts.has(p.id.toString()));
            
            if (confirm(`${selectedProducts.size} ürün WordPress'e aktarılacak. Onaylıyor musunuz?`)) {
                toggleLoading(true, 'Ürünler WordPress\'e aktarılıyor...');
                await importToWordPress(selectedProductsArray);
            }
        });
    </script>
</body>
</html> 