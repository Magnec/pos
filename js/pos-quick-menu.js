/**
 * POS Quick Menu & Categories JavaScript - v2.2
 * İyileştirilmiş + Debug
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.posQuickMenu = {
    attach: function (context, settings) {
      
      if (context !== document) return;
      
      const posWrapper = context.querySelector('.pos-wrapper');
      if (!posWrapper || posWrapper.dataset.quickMenuInitialized) return;
      
      posWrapper.dataset.quickMenuInitialized = 'true';
      
      const POS_QUICK = {
        
        settings: drupalSettings.pos || {},
        
        elements: {
          quickMenuGrid: document.getElementById('quickMenuGrid'),
          categoriesGrid: document.getElementById('categoriesGrid'),
          modalOverlay: document.getElementById('modalOverlay'),
          modalTitle: document.getElementById('modalCategoryName'),
          modalIcon: document.getElementById('modalIcon'),
          modalBody: document.getElementById('modalBody'),
          modalClose: document.getElementById('modalClose'),
          modalSearch: document.getElementById('modalSearch'),
        },
        
        currentCategory: null,
        modalProducts: [],
        
        init: function() {
          console.log('🔥 Hızlı Menü v2.2 başlatılıyor...');
          console.log('API Endpoints:', this.settings.apiEndpoints);
          
          this.loadQuickMenu();
          this.bindEvents();
        },
        
        bindEvents: function() {
          const self = this;
          
          // Kategori tıklamaları
          if (self.elements.categoriesGrid) {
            const categoryItems = self.elements.categoriesGrid.querySelectorAll('.category-item');
            categoryItems.forEach(item => {
              item.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-category-id');
                self.openCategory(categoryId);
              });
            });
          }
          
          // Modal kapat
          if (self.elements.modalClose) {
            self.elements.modalClose.addEventListener('click', function() {
              self.closeModal();
            });
          }
          
          // Modal overlay'e tıklayınca kapat
          if (self.elements.modalOverlay) {
            self.elements.modalOverlay.addEventListener('click', function(e) {
              if (e.target === this) {
                self.closeModal();
              }
            });
          }
          
          // Modal arama
          if (self.elements.modalSearch) {
            self.elements.modalSearch.addEventListener('input', function() {
              self.filterModalProducts(this.value);
            });
          }
          
          // ESC ile modal kapat
          document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.elements.modalOverlay.classList.contains('show')) {
              self.closeModal();
            }
          });
        },
        
        /**
         * Hızlı Menü Yükle
         */
        loadQuickMenu: function() {
          const self = this;
          
          if (!self.elements.quickMenuGrid) {
            console.error('❌ quickMenuGrid element bulunamadı!');
            return;
          }
          
          if (!self.settings.apiEndpoints || !self.settings.apiEndpoints.quickMenu) {
            console.error('❌ API endpoint bulunamadı!', self.settings);
            return;
          }
          
          const apiUrl = self.settings.apiEndpoints.quickMenu;
          console.log('📡 Hızlı Menü API çağrısı:', apiUrl);
          
          fetch(apiUrl)
            .then(response => {
              console.log('📥 API Response status:', response.status);
              return response.json();
            })
            .then(data => {
              console.log('📊 API Data:', data);
              
              if (data.success && data.items) {
                console.log('✅ Hızlı menü items:', data.items.length);
                
                if (data.items.length > 0) {
                  self.renderQuickMenu(data.items);
                } else {
                  console.warn('⚠️ Hızlı menü boş');
                  self.renderQuickMenuEmpty();
                }
              } else {
                console.error('❌ API başarısız:', data);
                self.renderQuickMenuEmpty();
              }
            })
            .catch(error => {
              console.error('❌ Hızlı menü yüklenirken hata:', error);
              self.renderQuickMenuError();
            });
        },
        
        /**
         * Hızlı Menü Render
         */
        renderQuickMenu: function(items) {
          const self = this;
          
          console.log('🎨 Hızlı menü render ediliyor:', items.length, 'ürün');
          
          let html = '';
          
          items.forEach(item => {
            html += `
              <div class="quick-menu-item" data-barcode="${self.escapeHtml(item.barcode)}">
                <div class="quick-item-name">${self.escapeHtml(item.name)}</div>
                ${item.detail ? `<div class="quick-item-detail">${self.escapeHtml(item.detail)}</div>` : ''}
                <div class="quick-item-price">${item.price.toFixed(2)} ₺</div>
              </div>
            `;
          });
          
          self.elements.quickMenuGrid.innerHTML = html;
          
          // Click event'leri ekle
          const quickItems = self.elements.quickMenuGrid.querySelectorAll('.quick-menu-item');
          quickItems.forEach(item => {
            item.addEventListener('click', function() {
              const barcode = this.getAttribute('data-barcode');
              console.log('🔍 Hızlı menüden ürün seçildi:', barcode);
              
              // POS nesnesinin scanProduct fonksiyonunu çağır
              if (window.Drupal && window.Drupal.behaviors.posApp && window.Drupal.behaviors.posApp.POS) {
                window.Drupal.behaviors.posApp.POS.scanProduct(barcode);
              } else {
                console.error('❌ POS nesnesi bulunamadı!');
              }
            });
          });
          
          console.log('✅ Hızlı menü render tamamlandı');
        },
        
        /**
         * Boş Hızlı Menü
         */
        renderQuickMenuEmpty: function() {
          this.elements.quickMenuGrid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: #94a3b8;">
              <div style="font-size: 48px; margin-bottom: 12px;">📭</div>
              <div style="font-weight: 600; margin-bottom: 4px;">Hızlı menü boş</div>
              <div style="font-size: 13px;">Admin panelden ürün ekleyin</div>
            </div>
          `;
        },
        
        /**
         * Hata Mesajı
         */
        renderQuickMenuError: function() {
          this.elements.quickMenuGrid.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: #dc2626;">
              <div style="font-size: 48px; margin-bottom: 12px;">❌</div>
              <div style="font-weight: 600;">Yükleme hatası</div>
            </div>
          `;
        },
        
        /**
         * Kategori Aç
         */
        openCategory: function(categoryId) {
          const self = this;
          
          console.log('📂 Kategori açılıyor:', categoryId);
          
          self.elements.modalBody.innerHTML = `
            <div style="text-align: center; padding: 60px 20px;">
              <div class="loading-spinner" style="margin: 0 auto 20px;"></div>
              <p style="color: #64748b;">Ürünler yükleniyor...</p>
            </div>
          `;
          
          self.elements.modalOverlay.classList.add('show');
          
          fetch(self.settings.apiEndpoints.categoryProducts + categoryId)
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                self.currentCategory = data.category;
                self.modalProducts = data.products;
                
                if (self.elements.modalIcon) {
                  self.elements.modalIcon.textContent = data.category.icon;
                }
                if (self.elements.modalTitle) {
                  self.elements.modalTitle.textContent = data.category.name;
                }
                
                self.renderModalProducts(data.products);
              } else {
                self.elements.modalBody.innerHTML = `
                  <div class="modal-empty">
                    <div class="modal-empty-icon">❌</div>
                    <p>Kategori yüklenirken hata oluştu</p>
                  </div>
                `;
              }
            })
            .catch(error => {
              console.error('❌ Kategori yüklenirken hata:', error);
              self.elements.modalBody.innerHTML = `
                <div class="modal-empty">
                  <div class="modal-empty-icon">❌</div>
                  <p>Bağlantı hatası</p>
                </div>
              `;
            });
        },
        
        /**
         * Modal Ürünleri Render
         */
        renderModalProducts: function(products) {
          const self = this;
          
          if (!products || products.length === 0) {
            self.elements.modalBody.innerHTML = `
              <div class="modal-empty">
                <div class="modal-empty-icon">📦</div>
                <p>Bu kategoride ürün bulunamadı</p>
              </div>
            `;
            return;
          }
          
          let html = '';
          
          products.forEach(product => {
            const isAvailable = product.stock > 0;
            const disabledClass = !isAvailable ? 'disabled' : '';
            const stockClass = !isAvailable ? 'out-of-stock' : '';
            const stockText = isAvailable ? `✓ Stokta: ${product.stock} adet` : '✗ Stok Yok';
            
            html += `
              <div class="modal-product ${disabledClass}" data-barcode="${self.escapeHtml(product.barcode)}" data-available="${isAvailable}">
                <div class="modal-product-icon">📦</div>
                <div class="modal-product-info">
                  <div class="modal-product-name">${self.escapeHtml(product.name)}</div>
                  ${product.detail ? `<div class="modal-product-detail">${self.escapeHtml(product.detail)}</div>` : ''}
                  <div class="modal-product-stock ${stockClass}">${stockText}</div>
                </div>
                <div class="modal-product-price">${product.price.toFixed(2)} ₺</div>
              </div>
            `;
          });
          
          self.elements.modalBody.innerHTML = html;
          
          const modalProducts = self.elements.modalBody.querySelectorAll('.modal-product');
          modalProducts.forEach(item => {
            item.addEventListener('click', function() {
              if (this.classList.contains('disabled')) {
                return;
              }
              
              const barcode = this.getAttribute('data-barcode');
              
              if (window.Drupal && window.Drupal.behaviors.posApp && window.Drupal.behaviors.posApp.POS) {
                window.Drupal.behaviors.posApp.POS.scanProduct(barcode);
              }
              
              self.closeModal();
            });
          });
        },
        
        /**
         * Modal Ürün Filtrele
         */
        filterModalProducts: function(searchTerm) {
          const self = this;
          
          if (!searchTerm || searchTerm.trim() === '') {
            self.renderModalProducts(self.modalProducts);
            return;
          }
          
          const term = searchTerm.toLowerCase();
          const filtered = self.modalProducts.filter(product => {
            return product.name.toLowerCase().includes(term) ||
                   (product.detail && product.detail.toLowerCase().includes(term));
          });
          
          self.renderModalProducts(filtered);
        },
        
        /**
         * Modal Kapat
         */
        closeModal: function() {
          const self = this;
          
          if (self.elements.modalOverlay) {
            self.elements.modalOverlay.classList.remove('show');
          }
          
          if (self.elements.modalSearch) {
            self.elements.modalSearch.value = '';
          }
          
          self.currentCategory = null;
          self.modalProducts = [];
        },
        
        /**
         * HTML Escape
         */
        escapeHtml: function(text) {
          if (!text) return '';
          const div = document.createElement('div');
          div.textContent = text;
          return div.innerHTML;
        }
      };
      
      // Quick Menu'yu başlat
      POS_QUICK.init();
      
      // Global'e export et
      if (!window.Drupal.behaviors.posApp) {
        window.Drupal.behaviors.posApp = {};
      }
      window.Drupal.behaviors.posApp.POS_QUICK = POS_QUICK;
    }
  };

})(Drupal, drupalSettings);
