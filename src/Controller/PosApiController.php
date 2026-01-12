<?php

namespace Drupal\pos\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\pos\Traits\PosApiSecurityTrait;

/**
 * POS API Controller.
 * 
 * Güvenlik:
 * - Permission kontrolü (Drupal)
 * - Session Token (CSRF)
 * - Rate Limiting (Brute force)
 */
class PosApiController extends ControllerBase {
  
  use PosApiSecurityTrait;

  /**
   * Hızlı Menü Ürünlerini Getir.
   */
  public function getQuickMenuItems(Request $request) {
    // GÜVENLİK KONTROLÜ
    $access_error = $this->checkApiAccess($request, FALSE);
    if ($access_error) {
      return $access_error;
    }
    
    try {
      $config = \Drupal::config('pos.settings');
      $product_ids = $config->get('top_products') ?? [];
      $count = $config->get('top_products_count') ?? 6;
      
      $quick_items = [];
      
      if (!empty($product_ids)) {
        $storage = \Drupal::entityTypeManager()->getStorage('market');
        $products = $storage->loadMultiple(array_slice($product_ids, 0, $count));
        
        foreach ($products as $product) {
          if (!$product->hasField('field_barcode')) continue;
          
          $barcode = $product->get('field_barcode')->value;
          if (empty($barcode)) continue;
          
          // Title
          $title = '';
          if ($product->hasField('title')) {
            $title = $product->get('title')->value;
          } elseif ($product->hasField('name')) {
            $title = $product->get('name')->value;
          } else {
            $title = 'Ürün #' . $product->id();
          }
          
          // Price
          $price = 0;
          if ($product->hasField('field_price')) {
            $price = (float) $product->get('field_price')->value;
          }
          
          // Stock
          $stock = 0;
          if ($product->hasField('field_stock')) {
            $stock = (int) $product->get('field_stock')->value;
          }
          
          // Detail (Brand + Category gibi)
          $detail = '';
          if ($product->hasField('field_brand') && !$product->get('field_brand')->isEmpty()) {
            $brand = $product->get('field_brand')->entity;
            if ($brand) {
              $detail = $brand->getName();
            }
          }
          
          // Image
          $image_url = NULL;
          if ($product->hasField('field_image') && !$product->get('field_image')->isEmpty()) {
            $image = $product->get('field_image')->entity;
            if ($image) {
              $image_url = file_create_url($image->getFileUri());
            }
          }
          
          $quick_items[] = [
            'id' => $product->id(),
            'name' => $title,
            'detail' => $detail,
            'brand' => $detail, // Marka adı
            'price' => $price,
            'stock' => $stock,
            'barcode' => $barcode,
            'image_url' => $image_url,
          ];
        }
      }
      
      \Drupal::logger('pos')->info('Hızlı menü yüklendi: @count ürün', [
        '@count' => count($quick_items),
      ]);
      
      return new JsonResponse([
        'success' => TRUE,
        'items' => $quick_items,
        'count' => count($quick_items),
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('getQuickMenuItems hatası: @msg', [
        '@msg' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Kategoriye Göre Ürünleri Getir.
   */
  public function getCategoryProducts($category_id, Request $request) {
    // GÜVENLİK KONTROLÜ
    $access_error = $this->checkApiAccess($request, FALSE);
    if ($access_error) {
      return $access_error;
    }
    
    try {
      $config = \Drupal::config('pos.settings');
      $vocab = $config->get('category_vocabulary');
      
      if (empty($vocab)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Kategori vocabulary ayarlanmamış',
        ], 400);
      }
      
      // Kategori bilgisini al
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $category = $term_storage->load($category_id);
      
      if (!$category) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Kategori bulunamadı',
        ], 404);
      }
      
      $category_info = [
        'id' => $category->id(),
        'name' => $category->getName(),
        'icon' => '📦', // Default icon
      ];
      
      // Icon field varsa al
      if ($category->hasField('field_icon')) {
        $icon = $category->get('field_icon')->value;
        if ($icon) {
          $category_info['icon'] = $icon;
        }
      }
      
      // Bu kategorideki ürünleri bul
      $query = \Drupal::entityQuery('market')
        ->condition('type', 'product')
        ->accessCheck(FALSE);
      
      // Kategori field'ını bul (field_category, field_categories, vb.)
      $possible_fields = ['field_category', 'field_categories', 'field_product_category'];
      $category_field = NULL;
      
      $field_definitions = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('market', 'product');
      
      foreach ($possible_fields as $field_name) {
        if (isset($field_definitions[$field_name])) {
          $category_field = $field_name;
          break;
        }
      }
      
      if ($category_field) {
        $query->condition($category_field, $category_id);
      }
      
      $ids = $query->execute();
      
      $products = [];
      
      if ($ids) {
        $storage = \Drupal::entityTypeManager()->getStorage('market');
        $entities = $storage->loadMultiple($ids);
        
        foreach ($entities as $entity) {
          if (!$entity->hasField('field_barcode')) continue;
          
          $barcode = $entity->get('field_barcode')->value;
          if (empty($barcode)) continue;
          
          // Title
          $title = '';
          if ($entity->hasField('title')) {
            $title = $entity->get('title')->value;
          } elseif ($entity->hasField('name')) {
            $title = $entity->get('name')->value;
          } else {
            $title = 'Ürün #' . $entity->id();
          }
          
          // Price
          $price = 0;
          if ($entity->hasField('field_price')) {
            $price = (float) $entity->get('field_price')->value;
          }
          
          // Stock
          $stock = 0;
          if ($entity->hasField('field_stock')) {
            $stock = (int) $entity->get('field_stock')->value;
          }
          
          // Detail
          $detail = '';
          if ($entity->hasField('field_brand') && !$entity->get('field_brand')->isEmpty()) {
            $brand = $entity->get('field_brand')->entity;
            if ($brand) {
              $detail = $brand->getName();
            }
          }
          
          $products[] = [
            'id' => $entity->id(),
            'name' => $title,
            'detail' => $detail,
            'brand' => $detail, // Marka adı
            'price' => $price,
            'stock' => $stock,
            'barcode' => $barcode,
            'available' => $stock > 0,
          ];
        }
      }
      
      return new JsonResponse([
        'success' => TRUE,
        'category' => $category_info,
        'products' => $products,
        'count' => count($products),
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('getCategoryProducts hatası: @msg', [
        '@msg' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Markaları getir.
   */
  public function getBrands(Request $request) {
    // GÜVENLİK KONTROLÜ
    $access_error = $this->checkApiAccess($request, FALSE);
    if ($access_error) {
      return $access_error;
    }
    
    try {
      $brands = [];
      
      // Önce hangi vocabulary'ler var bakalım
      $vocabularies = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_vocabulary')
        ->loadMultiple();
      
      \Drupal::logger('pos')->info('Mevcut vocabulary\'ler: @vocabs', [
        '@vocabs' => implode(', ', array_keys($vocabularies))
      ]);
      
      // Olası vocabulary adlarını dene
      $possible_vids = ['brand', 'brands', 'marka', 'markalar'];
      $vid = null;
      
      foreach ($possible_vids as $test_vid) {
        if (isset($vocabularies[$test_vid])) {
          $vid = $test_vid;
          break;
        }
      }
      
      if (!$vid) {
        \Drupal::logger('pos')->warning('Brand vocabulary bulunamadı. Mevcut: @vocabs', [
          '@vocabs' => implode(', ', array_keys($vocabularies))
        ]);
        
        return new JsonResponse([
          'success' => TRUE,
          'brands' => [],
          'count' => 0,
          'message' => 'Brand vocabulary bulunamadı',
          'available_vocabularies' => array_keys($vocabularies),
        ]);
      }
      
      // Taxonomy terms - brand vocabulary
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => $vid]);
      
      foreach ($terms as $term) {
        $brands[] = [
          'id' => $term->id(),
          'name' => $term->getName(),
        ];
      }
      
      // Alfabetik sırala
      usort($brands, function($a, $b) {
        return strcmp($a['name'], $b['name']);
      });
      
      \Drupal::logger('pos')->info('Markalar yüklendi. Vocabulary: @vid, Toplam: @count', [
        '@vid' => $vid,
        '@count' => count($brands),
      ]);
      
      return new JsonResponse([
        'success' => TRUE,
        'brands' => $brands,
        'count' => count($brands),
        'vocabulary' => $vid,
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('getBrands hatası: @msg', ['@msg' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Tüm ürünleri lightweight olarak getir.
   */
  public function getAllProducts(Request $request) {
    // GÜVENLİK KONTROLÜ
    $access_error = $this->checkApiAccess($request, FALSE);
    if ($access_error) {
      return $access_error;
    }
    
    $products = [];
    
    try {
      \Drupal::logger('pos')->info('getAllProducts başladı');
      
      // market entity type kullan
      $storage = \Drupal::entityTypeManager()->getStorage('market');
      
      // Query oluştur
      $query = $storage->getQuery()
        ->condition('type', 'product')
        ->accessCheck(FALSE);
      
      $ids = $query->execute();
      
      \Drupal::logger('pos')->info('Bulunan ID sayısı: @count', ['@count' => count($ids)]);
      
      if (empty($ids)) {
        return new JsonResponse([
          'success' => TRUE,
          'count' => 0,
          'products' => [],
          'message' => 'Hiç ürün bulunamadı'
        ]);
      }
      
      // Ürünleri yükle
      $entities = $storage->loadMultiple($ids);
      
      foreach ($entities as $entity) {
        try {
          // Barcode kontrolü
          if (!$entity->hasField('field_barcode')) {
            \Drupal::logger('pos')->warning('Entity @id: field_barcode yok', ['@id' => $entity->id()]);
            continue;
          }
          
          $barcode = $entity->get('field_barcode')->value;
          
          if (empty($barcode)) {
            \Drupal::logger('pos')->warning('Entity @id: barkod boş', ['@id' => $entity->id()]);
            continue;
          }
          
          // Title - birkaç olasılığı dene
          $title = '';
          if ($entity->hasField('title')) {
            $title = $entity->get('title')->value;
          } elseif ($entity->hasField('name')) {
            $title = $entity->get('name')->value;
          } elseif ($entity->hasField('label')) {
            $title = $entity->get('label')->value;
          } else {
            $title = 'Ürün #' . $entity->id();
          }
          
          // Price
          $price = 0;
          if ($entity->hasField('field_price')) {
            $price = (float) $entity->get('field_price')->value;
          }
          
          // Stock
          $stock = 0;
          if ($entity->hasField('field_stock')) {
            $stock = (int) $entity->get('field_stock')->value;
          }
          
          // Brand (Marka)
          $brand = '';
          if ($entity->hasField('field_brand') && !$entity->get('field_brand')->isEmpty()) {
            $brand_entity = $entity->get('field_brand')->entity;
            if ($brand_entity) {
              $brand = $brand_entity->getName();
            }
          }
          
          $products[$barcode] = [
            'id' => $entity->id(),
            'name' => $title,
            'brand' => $brand,
            'price' => $price,
            'stock' => $stock,
            'barcode' => $barcode,
          ];
          
        } catch (\Exception $e) {
          \Drupal::logger('pos')->error('Entity @id işlenirken hata: @msg', [
            '@id' => $entity->id(),
            '@msg' => $e->getMessage()
          ]);
        }
      }
      
      \Drupal::logger('pos')->info('Başarılı. Toplam ürün: @count', ['@count' => count($products)]);
      
      return new JsonResponse([
        'success' => TRUE,
        'count' => count($products),
        'products' => $products,
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('getAllProducts hatası: @message', [
        '@message' => $e->getMessage(),
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Ürünler yüklenirken hata oluştu: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Barkod ile ürün ara.
   */
  public function searchProduct($barcode, Request $request) {
    // GÜVENLİK KONTROLÜ
    $access_error = $this->checkApiAccess($request, FALSE);
    if ($access_error) {
      return $access_error;
    }
    
    try {
      $query = \Drupal::entityQuery('market')
        ->condition('type', 'product')
        ->condition('field_barcode', $barcode)
        ->range(0, 1)
        ->accessCheck(FALSE);
      
      $ids = $query->execute();
      
      if ($ids) {
        $storage = \Drupal::entityTypeManager()->getStorage('market');
        $entity = $storage->load(reset($ids));
        
        // Title
        $title = '';
        if ($entity->hasField('title')) {
          $title = $entity->get('title')->value;
        } elseif ($entity->hasField('name')) {
          $title = $entity->get('name')->value;
        } else {
          $title = 'Ürün #' . $entity->id();
        }
        
        // Brand
        $brand = '';
        if ($entity->hasField('field_brand') && !$entity->get('field_brand')->isEmpty()) {
          $brand_entity = $entity->get('field_brand')->entity;
          if ($brand_entity) {
            $brand = $brand_entity->getName();
          }
        }
        
        return new JsonResponse([
          'success' => TRUE,
          'found' => TRUE,
          'product' => [
            'id' => $entity->id(),
            'name' => $title,
            'brand' => $brand,
            'price' => (float) $entity->get('field_price')->value,
            'stock' => (int) $entity->get('field_stock')->value,
            'barcode' => $entity->get('field_barcode')->value,
          ],
        ]);
      }
      
      return new JsonResponse([
        'success' => TRUE,
        'found' => FALSE,
        'message' => 'Ürün bulunamadı',
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('searchProduct hatası: @msg', ['@msg' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Stok doğrulama.
   */
  public function verifyStock($barcode, Request $request) {
    // GÜVENLİK KONTROLÜ
    $access_error = $this->checkApiAccess($request, FALSE);
    if ($access_error) {
      return $access_error;
    }
    
    try {
      $query = \Drupal::entityQuery('market')
        ->condition('type', 'product')
        ->condition('field_barcode', $barcode)
        ->range(0, 1)
        ->accessCheck(FALSE);
      
      $ids = $query->execute();
      
      if ($ids) {
        $storage = \Drupal::entityTypeManager()->getStorage('market');
        $entity = $storage->load(reset($ids));
        $stock = (int) $entity->get('field_stock')->value;
        
        return new JsonResponse([
          'success' => TRUE,
          'stock' => $stock,
          'available' => $stock > 0,
        ]);
      }
      
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Ürün bulunamadı',
      ]);
      
    } catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Satış kaydet.
   */
  public function saveSale(Request $request) {
    // GÜVENLİK KONTROLÜ (Token gerekli - POST)
    $access_error = $this->checkApiAccess($request, TRUE);
    if ($access_error) {
      return $access_error;
    }
    
    try {
      $data = json_decode($request->getContent(), TRUE);
      
      if (!$data || !isset($data['items']) || empty($data['items'])) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Geçersiz veri',
        ], 400);
      }
      
      $items = $data['items'];
      $total = $data['total'];
      $payment_type = $data['payment_type'];
      
      // Storage'ları al
      $transaction_storage = \Drupal::entityTypeManager()->getStorage('market_sales_activities');
      $extras_storage = \Drupal::entityTypeManager()->getStorage('extras');
      $product_storage = \Drupal::entityTypeManager()->getStorage('market');
      
      // Transaction oluştur
      $transaction = $transaction_storage->create([
        'type' => 'transactions',
        'field_total' => $total,
        'field_payment_method' => $payment_type,
      ]);
      
      $transaction->save();
      $transaction_id = $transaction->id();
      
      \Drupal::logger('pos')->info('Transaction oluşturuldu: @id', ['@id' => $transaction_id]);
      
      $item_ids = [];
      
      // Her item için kayıt oluştur
      foreach ($items as $item) {
        $product_query = \Drupal::entityQuery('market')
          ->condition('type', 'product')
          ->condition('field_barcode', $item['barcode'])
          ->range(0, 1)
          ->accessCheck(FALSE);
        
        $product_ids = $product_query->execute();
        
        if ($product_ids) {
          $product_id = reset($product_ids);
          $product = $product_storage->load($product_id);
          
          // Sale item oluştur
          $sale_item = $extras_storage->create([
            'type' => 'market_sales_items',
            'field_quantity' => $item['quantity'],
            'field_products' => $product_id,
            'field_unit_price' => $item['price'],
            // field_subtotal hook'ta otomatik hesaplanacak
          ]);
          
          $sale_item->save();
          $item_ids[] = $sale_item->id();
          
          \Drupal::logger('pos')->info('Sale item oluşturuldu: @id', ['@id' => $sale_item->id()]);
          
          // Stok düş
          $current_stock = (int) $product->get('field_stock')->value;
          $new_stock = $current_stock - $item['quantity'];
          $product->set('field_stock', max(0, $new_stock));
          $product->save();
          
          \Drupal::logger('pos')->info('Stok güncellendi. Ürün: @id, Eski: @old, Yeni: @new', [
            '@id' => $product_id,
            '@old' => $current_stock,
            '@new' => $new_stock,
          ]);
        }
      }
      
      // Transaction'a item referanslarını ekle
      if (!empty($item_ids)) {
        $transaction->set('field_market_items', $item_ids);
        $transaction->save();
      }
      
      \Drupal::logger('pos')->info('Satış başarıyla kaydedildi: @id', ['@id' => $transaction_id]);
      
      return new JsonResponse([
        'success' => TRUE,
        'transaction_id' => $transaction_id,
        'message' => 'Satış başarıyla kaydedildi',
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('saveSale hatası: @msg, Trace: @trace', [
        '@msg' => $e->getMessage(),
        '@trace' => $e->getTraceAsString(),
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Satış kaydedilirken hata oluştu: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Manuel ürün ekle.
   */
  public function addManualProduct(Request $request) {
    // GÜVENLİK KONTROLÜ (Token gerekli - POST)
    $access_error = $this->checkApiAccess($request, TRUE);
    if ($access_error) {
      return $access_error;
    }
    
    try {
      $data = json_decode($request->getContent(), TRUE);
      
      if (!$data || !isset($data['name']) || !isset($data['price']) || !isset($data['barcode'])) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Eksik veri',
        ], 400);
      }
      
      // Barkod kontrolü
      $query = \Drupal::entityQuery('market')
        ->condition('type', 'product')
        ->condition('field_barcode', $data['barcode'])
        ->accessCheck(FALSE);
      
      $existing = $query->execute();
      
      if ($existing) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Bu barkod zaten kayıtlı',
        ], 400);
      }
      
      // Yeni ürün oluştur
      $storage = \Drupal::entityTypeManager()->getStorage('market');
      
      $product_data = [
        'type' => 'product',
        'title' => $data['name'],
        'field_barcode' => $data['barcode'],
        'field_price' => $data['price'],
        'field_stock' => isset($data['stock']) ? (int) $data['stock'] : 1,
      ];
      
      // Marka varsa ekle (brand_id olarak gönderilecek)
      if (!empty($data['brand_id'])) {
        $product_data['field_brand'] = ['target_id' => (int) $data['brand_id']];
      } elseif (!empty($data['brand'])) {
        // Geriye dönük uyumluluk için
        $product_data['field_brand'] = ['target_id' => (int) $data['brand']];
      }
      
      $product = $storage->create($product_data);
      $product->save();
      
      // Marka adını al
      $brand_name = '';
      if ($product->hasField('field_brand') && !$product->get('field_brand')->isEmpty()) {
        $brand_entity = $product->get('field_brand')->entity;
        if ($brand_entity) {
          $brand_name = $brand_entity->getName();
        }
      }
      
      \Drupal::logger('pos')->info('Manuel ürün eklendi: @id - @name (Stok: @stock, Marka: @brand)', [
        '@id' => $product->id(),
        '@name' => $data['name'],
        '@stock' => $product_data['field_stock'],
        '@brand' => $brand_name ?: 'Yok',
      ]);
      
      return new JsonResponse([
        'success' => TRUE,
        'product' => [
          'id' => $product->id(),
          'name' => $data['name'],
          'brand' => $brand_name,
          'price' => (float) $data['price'],
          'stock' => $product_data['field_stock'],
          'barcode' => $data['barcode'],
        ],
      ]);
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('addManualProduct hatası: @msg', ['@msg' => $e->getMessage()]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Ürün eklenirken hata oluştu: ' . $e->getMessage(),
      ], 500);
    }
  }

}
