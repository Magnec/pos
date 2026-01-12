<?php

namespace Drupal\pos\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * POS Sistem Ayarları Form.
 */
class PosSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['pos.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pos_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pos.settings');
    
    $form['quick_menu'] = [
      '#type' => 'details',
      '#title' => $this->t('🔥 Hızlı Menü Ayarları'),
      '#open' => TRUE,
    ];
    
    // En Çok Satılan Ürünler
    $form['quick_menu']['top_products'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('En Çok Satılan Ürünler'),
      '#description' => $this->t('Hızlı erişim için en çok satılan ürünleri seçin (maksimum 6 ürün)'),
      '#target_type' => 'market',
      '#selection_settings' => [
        'target_bundles' => ['product'],
      ],
      '#tags' => TRUE,
      '#default_value' => $this->loadTopProducts($config->get('top_products')),
      '#maxlength' => 1024,
    ];
    
    $form['quick_menu']['top_products_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Gösterilecek Ürün Sayısı'),
      '#default_value' => $config->get('top_products_count') ?? 6,
      '#min' => 3,
      '#max' => 12,
    ];
    
    // Kategori Ayarları
    $form['categories'] = [
      '#type' => 'details',
      '#title' => $this->t('📂 Kategori Ayarları'),
      '#open' => TRUE,
    ];
    
    // Taxonomy vocabulary seçimi
    $vocabularies = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_vocabulary')
      ->loadMultiple();
    
    $vocab_options = [];
    foreach ($vocabularies as $vid => $vocabulary) {
      $vocab_options[$vid] = $vocabulary->label();
    }
    
    $form['categories']['category_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Kategori Vocabulary'),
      '#description' => $this->t('Ürün kategorileri için kullanılacak taxonomy vocabulary'),
      '#options' => $vocab_options,
      '#default_value' => $config->get('category_vocabulary'),
      '#empty_option' => $this->t('- Seçin -'),
    ];
    
    $form['categories']['show_categories'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Kategorileri Göster'),
      '#description' => $this->t('POS ekranında kategori menüsünü göster'),
      '#default_value' => $config->get('show_categories') ?? TRUE,
    ];
    
    $form['categories']['categories_per_row'] = [
      '#type' => 'select',
      '#title' => $this->t('Satır Başına Kategori'),
      '#options' => [
        2 => '2',
        3 => '3',
        4 => '4',
        6 => '6',
      ],
      '#default_value' => $config->get('categories_per_row') ?? 3,
    ];
    
    // Görünüm Ayarları
    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('🎨 Görünüm Ayarları'),
      '#open' => FALSE,
    ];
    
    $form['display']['show_product_images'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ürün Resimlerini Göster'),
      '#default_value' => $config->get('show_product_images') ?? FALSE,
    ];
    
    $form['display']['theme_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Tema Rengi'),
      '#options' => [
        'blue' => $this->t('Mavi (Varsayılan)'),
        'green' => $this->t('Yeşil'),
        'purple' => $this->t('Mor'),
        'red' => $this->t('Kırmızı'),
        'orange' => $this->t('Turuncu'),
      ],
      '#default_value' => $config->get('theme_color') ?? 'blue',
    ];
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * Top products yükle.
   */
  private function loadTopProducts($product_ids) {
    if (empty($product_ids)) {
      return NULL;
    }
    
    $storage = \Drupal::entityTypeManager()->getStorage('market');
    return $storage->loadMultiple($product_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('pos.settings');
    
    // Top products
    $top_products = $form_state->getValue('top_products');
    $product_ids = [];
    
    if ($top_products) {
      foreach ($top_products as $product) {
        if (isset($product['target_id'])) {
          $product_ids[] = $product['target_id'];
        }
      }
    }
    
    $config
      ->set('top_products', $product_ids)
      ->set('top_products_count', $form_state->getValue('top_products_count'))
      ->set('category_vocabulary', $form_state->getValue('category_vocabulary'))
      ->set('show_categories', $form_state->getValue('show_categories'))
      ->set('categories_per_row', $form_state->getValue('categories_per_row'))
      ->set('show_product_images', $form_state->getValue('show_product_images'))
      ->set('theme_color', $form_state->getValue('theme_color'))
      ->save();
    
    parent::submitForm($form, $form_state);
  }

}
