# HepsiBurada API

Bu PHP kütüphanesi, HepsiBurada'dan ürün bilgilerini çekmek için kullanılır.

## Özellikler

- Ürün arama
- Fiyat bilgisi çekme (normal ve sepet fiyatı)
- Ürün resmi, başlık ve marka bilgisi
- Sayfalama desteği

## Kurulum

1. Dosyaları projenize ekleyin
2. `HepsiBuradaAPI` sınıfını kullanmaya başlayın

## Kullanım

```php
$api = new HepsiBuradaAPI();
$results = $api->search('bosch', 1); // İlk sayfadaki Bosch ürünlerini getirir
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

## Dönen Veri Formatı

```json
{
    "success": true,
    "page": 1,
    "search_term": "bosch",
    "products": [
        {
            "id": "PRODUCT_ID",
            "title": "Ürün Başlığı",
            "price": "100.50",
            "image": "https://productimages.hepsiburada.net/...",
            "url": "https://www.hepsiburada.com/...",
            "brand": "Bosch",
            "category": ""
        }
    ],
    "total": 1
}
```

## Gereksinimler

- PHP 7.0 veya üzeri
- cURL extension
- JSON extension

## Lisans

MIT

