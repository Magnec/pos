<?php

namespace Drupal\pos\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * POS sayfa controller'ı.
 */
class PosPageController extends ControllerBase {

  /**
   * POS ana sayfası.
   */
  public function page() {
    
    // Session token oluştur
    $session = \Drupal::request()->getSession();
    
    if (!$session->has('pos_token')) {
      $session->set('pos_token', bin2hex(random_bytes(32)));
    }
    $pos_token = $session->get('pos_token');
    
    \Drupal::logger('pos')->info('POS Token oluşturuldu: @token', ['@token' => $pos_token]);
    
    // Mevcut kullanıcı
    $current_user = \Drupal::currentUser();
    
    // Config
    $config = \Drupal::config('pos.settings');
    
    // Kategorileri getir
    $categories = $this->getCategories();
    
    return [
      '#theme' => 'pos_page',
      '#user_name' => $current_user->getDisplayName(),
      '#user_id' => $current_user->id(),
      '#pos_token' => $pos_token,
      '#categories' => $categories,
      '#attached' => [
        'library' => [
          'pos/pos_app',
        ],
        'drupalSettings' => [
          'pos' => [
            'apiEndpoints' => [
              'products' => '/api/pos/products',
              'brands' => '/api/pos/brands',
              'search' => '/api/pos/product/',
              'verify' => '/api/pos/verify/',
              'save' => '/api/pos/sale',
              'manual' => '/api/pos/product/manual',
              'quickMenu' => '/api/pos/quick-menu',
              'categoryProducts' => '/api/pos/category/',
            ],
            'posToken' => $pos_token,
            'userId' => $current_user->id(),
            'userName' => $current_user->getDisplayName(),
            'settings' => [
              'showCategories' => $config->get('show_categories') ?? TRUE,
              'categoriesPerRow' => $config->get('categories_per_row') ?? 3,
              'showProductImages' => $config->get('show_product_images') ?? FALSE,
              'themeColor' => $config->get('theme_color') ?? 'blue',
            ],
          ],
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Kategorileri getir.
   */
  private function getCategories() {
    $config = \Drupal::config('pos.settings');
    $vocab = $config->get('category_vocabulary');
    
    if (empty($vocab)) {
      return [];
    }
    
    $categories = [];
    
    try {
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $terms = $term_storage->loadByProperties(['vid' => $vocab]);
      
      foreach ($terms as $term) {
        $icon = '📦'; // Default
        
        // Icon field varsa al
        if ($term->hasField('field_icon')) {
          $term_icon = $term->get('field_icon')->value;
          if ($term_icon) {
            $icon = $term_icon;
          }
        }
        
        // Bu kategorideki ürün sayısını bul
        $query = \Drupal::entityQuery('market')
          ->condition('type', 'product')
          ->accessCheck(FALSE);
        
        // Kategori field'ını bul
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
        
        $product_count = 0;
        if ($category_field) {
          $query->condition($category_field, $term->id());
          $product_count = $query->count()->execute();
        }
        
        $categories[] = [
          'id' => $term->id(),
          'name' => $term->getName(),
          'icon' => $icon,
          'count' => $product_count,
        ];
      }
      
    } catch (\Exception $e) {
      \Drupal::logger('pos')->error('Kategoriler yüklenirken hata: @msg', [
        '@msg' => $e->getMessage(),
      ]);
    }
    
    return $categories;
  }

}
