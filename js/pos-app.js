/**
 * POS Application JavaScript
 * Modern Market POS System - Vanilla JS
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.posApp = {
    attach: function (context, settings) {
      
      // Sadece bir kez çalıştır
      if (context !== document) return;
      
      // POS wrapper kontrolü
      const posWrapper = context.querySelector('.pos-wrapper');
      if (!posWrapper || posWrapper.dataset.posInitialized) return;
      
      // İşaretleme (tekrar çalışmasın)
      posWrapper.dataset.posInitialized = 'true';
      
      // POS App nesnesi
      const POS = {
        
        // Ayarlar
        settings: drupalSettings.pos || {},
        
        // Cache
        cache: {
          products: {},
          loaded: false,
          loading: false
        },
        
        // Markalar
        brands: [],
        selectedBrandIndex: -1,
        
        // Sepet
        cart: [],
        
        // Toplam
        total: 0,
        
        // DOM Elemanları
        elements: {},
        
        /**
         * Başlangıç
         */
        init: function() {
          console.log('🚀 POS Sistemi başlatılıyor...');
          
          // DOM elemanlarını al
          this.elements = {
            barcodeInput: document.getElementById('barcodeInput'),
            productList: document.getElementById('productList'),
            totalAmount: document.getElementById('totalAmount'),
            totalItems: document.getElementById('totalItems'),
            cartCount: document.getElementById('cartCount'),
            errorAlert: document.getElementById('errorAlert'),
            successAlert: document.getElementById('successAlert'),
            manualAdd: document.getElementById('manualAdd'),
            posLoading: document.getElementById('posLoading'),
            manualName: document.getElementById('manualName'),
            manualPrice: document.getElementById('manualPrice'),
            manualBarcode: document.getElementById('manualBarcode'),
            manualBrand: document.getElementById('manualBrand'),
            manualBrandId: document.getElementById('manualBrandId'),
            brandAutocompleteList: document.getElementById('brandAutocompleteList'),
            manualStock: document.getElementById('manualStock'),
            manualAddBtn: document.getElementById('manualAddBtn'),
            btnCash: document.getElementById('btnCash'),
            btnCredit: document.getElementById('btnCredit'),
            btnTransfer: document.getElementById('btnTransfer'),
            btnClearCart: document.getElementById('btnClearCart')
          };
          
          // Ayarları kontrol et
          if (!this.settings.apiEndpoints) {
            console.error('❌ API endpoints bulunamadı!');
            return;
          }
          
          console.log('⚙️ API Endpoints:', this.settings.apiEndpoints);
          
          // Cache yükle
          this.loadProductCache();
          
          // Markaları yükle
          this.loadBrands();
          
          // Event listener'ları ekle
          this.bindEvents();
          
          // Barkod input'a focus
          if (this.elements.barcodeInput) {
            this.elements.barcodeInput.focus();
          }
          
          console.log('✅ POS Sistemi hazır');
        },
        
        /**
         * Event listener'lar
         */
        bindEvents: function() {
          const self = this;
          
          // Barkod input - Enter
          if (this.elements.barcodeInput) {
            this.elements.barcodeInput.addEventListener('keypress', function(e) {
              if (e.key === 'Enter' || e.keyCode === 13) {
                const barcode = this.value.trim();
                if (barcode) {
                  self.scanProduct(barcode);
                  this.value = '';
                }
              }
            });
          }
          
          // Manuel ürün ekleme
          if (this.elements.manualAddBtn) {
            this.elements.manualAddBtn.addEventListener('click', function() {
              self.addManualProduct();
            });
          }
          
          // Marka autocomplete
          const manualBrandInput = document.getElementById('manualBrand');
          const manualBrandIdInput = document.getElementById('manualBrandId');
          const brandAutocompleteList = document.getElementById('brandAutocompleteList');
          
          if (manualBrandInput && brandAutocompleteList) {
            let brandTimeout = null;
            
            manualBrandInput.addEventListener('input', function() {
              const searchTerm = this.value.trim();
              
              // Clear previous timeout
              if (brandTimeout) {
                clearTimeout(brandTimeout);
              }
              
              if (searchTerm.length < 2) {
                brandAutocompleteList.innerHTML = '';
                brandAutocompleteList.style.display = 'none';
                return;
              }
              
              // Debounce: 300ms bekle
              brandTimeout = setTimeout(function() {
                // Markaları ara
                fetch(self.settings.apiEndpoints.brands || '/api/pos/brands')
                  .then(response => response.json())
                  .then(data => {
                    if (data.success && data.brands) {
                      const filtered = data.brands.filter(function(brand) {
                        return brand.name.toLowerCase().includes(searchTerm.toLowerCase());
                      });
                      
                      if (filtered.length > 0) {
                        let html = '';
                        filtered.forEach(function(brand) {
                          // Aranan kelimeyi highlight et (GitHub style)
                          const regex = new RegExp('(' + searchTerm + ')', 'gi');
                          const highlightedName = brand.name.replace(regex, '<mark>$1</mark>');
                          
                          html += '<div class="brand-autocomplete-item" data-id="' + brand.id + '" data-name="' + brand.name + '">' +
                                  '<span class="brand-icon">🏷️</span>' + highlightedName +
                                  '</div>';
                        });
                        
                        brandAutocompleteList.innerHTML = html;
                        brandAutocompleteList.style.display = 'block';
                        
                        // Click event'leri ekle
                        const items = brandAutocompleteList.querySelectorAll('.brand-autocomplete-item');
                        items.forEach(function(item) {
                          item.addEventListener('click', function() {
                            const brandId = this.getAttribute('data-id');
                            const brandName = this.getAttribute('data-name');
                            
                            manualBrandInput.value = brandName;
                            manualBrandIdInput.value = brandId;
                            brandAutocompleteList.innerHTML = '';
                            brandAutocompleteList.style.display = 'none';
                          });
                        });
                      } else {
                        brandAutocompleteList.innerHTML = '<div class="brand-autocomplete-item no-results">Sonuç bulunamadı</div>';
                        brandAutocompleteList.style.display = 'block';
                      }
                    }
                  })
                  .catch(function(error) {
                    console.error('Marka arama hatası:', error);
                  });
              }, 300);
            });
            
            // Click outside to close
            document.addEventListener('click', function(e) {
              if (e.target !== manualBrandInput && !brandAutocompleteList.contains(e.target)) {
                brandAutocompleteList.style.display = 'none';
              }
            });
          }
          
          // Ödeme butonları
          if (this.elements.btnCash) {
            this.elements.btnCash.addEventListener('click', function() {
              self.processPayment('cash');
            });
          }
          
          if (this.elements.btnCredit) {
            this.elements.btnCredit.addEventListener('click', function() {
              self.processPayment('credit_card');
            });
          }
          
          if (this.elements.btnTransfer) {
            this.elements.btnTransfer.addEventListener('click', function() {
              self.processPayment('transfer');
            });
          }
          
          // Sepeti temizle
          if (this.elements.btnClearCart) {
            this.elements.btnClearCart.addEventListener('click', function() {
              if (self.cart.length > 0) {
                if (confirm('Sepeti temizlemek istediğinizden emin misiniz?')) {
                  self.clearCart();
                }
              }
            });
          }
          
          // Keyboard shortcuts
          document.addEventListener('keydown', function(e) {
            if (!document.querySelector('.pos-wrapper')) return;
            
            if (e.key === 'F1') {
              e.preventDefault();
              self.processPayment('cash');
            }
            else if (e.key === 'F2') {
              e.preventDefault();
              self.processPayment('credit_card');
            }
            else if (e.key === 'F3') {
              e.preventDefault();
              self.processPayment('transfer');
            }
            else if (e.key === 'Escape') {
              if (self.elements.manualAdd) {
                self.elements.manualAdd.style.display = 'none';
              }
              if (self.elements.barcodeInput) {
                self.elements.barcodeInput.focus();
              }
            }
          });
        },
        
        /**
         * Markaları yükle
         */
        loadBrands: function() {
          const self = this;
          
          console.log('🏷️ Markalar yükleniyor...');
          
          fetch(self.settings.apiEndpoints.brands)
            .then(response => response.json())
            .then(data => {
              if (data.success && data.brands) {
                self.brands = data.brands;
                console.log('✅ Markalar yüklendi:', data.brands.length);
              }
            })
            .catch(error => {
              console.error('❌ Markalar yüklenirken hata:', error);
            });
        },
        
        /**
         * Ürün cache'ini yükle
         */
        loadProductCache: function() {
          const self = this;
          
          if (self.cache.loading) return;
          
          self.cache.loading = true;
          
          console.log('📦 Ürünler yükleniyor...');
          
          fetch(self.settings.apiEndpoints.products)
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                self.cache.products = data.products;
                self.cache.loaded = true;
                console.log('✅ Cache yüklendi:', data.count, 'ürün');
                self.showAlert('success', `✅ ${data.count} ürün yüklendi ve hazır`);
              }
            })
            .catch(error => {
              console.error('❌ Fetch hatası:', error);
              self.showAlert('error', '❌ Ürünler yüklenirken hata oluştu');
            })
            .finally(() => {
              self.cache.loading = false;
            });
        },
        
        /**
         * Barkod tarama
         */
        scanProduct: function(barcode) {
          const self = this;
          
          console.log('🔍 Barkod taranıyor:', barcode);
          
          if (self.elements.barcodeInput) {
            self.elements.barcodeInput.disabled = true;
          }
          
          // Cache'den bul
          if (self.cache.loaded && self.cache.products[barcode]) {
            const product = self.cache.products[barcode];
            console.log('✅ Ürün cache\'de bulundu:', product);
            
            setTimeout(function() {
              self.addToCart(barcode, product);
              self.showAlert('success', '✅ Ürün sepete eklendi!');
              if (self.elements.manualAdd) {
                self.elements.manualAdd.style.display = 'none';
              }
              if (self.elements.barcodeInput) {
                self.elements.barcodeInput.disabled = false;
                self.elements.barcodeInput.focus();
              }
            }, 200);
            
            self.verifyStock(barcode);
          }
          else {
            fetch(self.settings.apiEndpoints.search + barcode)
              .then(response => response.json())
              .then(data => {
                console.log('📡 API Response:', data);
                
                if (data.success && data.found) {
                  console.log('🛒 Sepete ekleniyor - Product data:', data.product);
                  console.log('🏷️ Brand değeri:', data.product.brand);
                  
                  self.addToCart(barcode, data.product);
                  self.showAlert('success', '✅ Ürün sepete eklendi!');
                  
                  if (self.cache.loaded) {
                    self.cache.products[barcode] = data.product;
                  }
                } else {
                  self.showAlert('error', '❌ Ürün bulunamadı!');
                  
                  // Manuel ekleme formunu aç ve barkodu doldur
                  if (self.elements.manualAdd) {
                    self.elements.manualAdd.style.display = 'block';
                    
                    // Barkodu manuel ekleme formuna doldur
                    const manualBarcodeInput = document.getElementById('manualBarcode');
                    if (manualBarcodeInput) {
                      manualBarcodeInput.value = barcode;
                    }
                    
                    // Name input'a focus
                    const manualNameInput = document.getElementById('manualName');
                    if (manualNameInput) {
                      manualNameInput.focus();
                    }
                  }
                }
              })
              .finally(() => {
                if (self.elements.barcodeInput) {
                  self.elements.barcodeInput.disabled = false;
                  self.elements.barcodeInput.focus();
                }
              });
          }
        },
        
        /**
         * Sepete ekle
         */
        addToCart: function(barcode, product) {
          // DEBUG: Product objesini kontrol et
          console.log('➕ Sepete Ekleniyor:', product);
          console.log('🏷️ Brand bilgisi:', product.brand);
          
          const existingItem = this.cart.find(item => item.barcode === barcode);
          
          if (existingItem) {
            existingItem.quantity++;
          } else {
            this.cart.push({
              barcode: barcode,
              name: product.name,
              price: parseFloat(product.price),
              quantity: 1,
              productId: product.id,
              brand: product.brand || ''
            });
          }
          
          this.updateDisplay();
        },
        
        /**
         * Sepetten çıkar
         */
        removeFromCart: function(barcode) {
          this.cart = this.cart.filter(item => item.barcode !== barcode);
          this.updateDisplay();
        },
        
        /**
         * Miktar artır
         */
        increaseQuantity: function(barcode) {
          const item = this.cart.find(item => item.barcode === barcode);
          if (item) {
            item.quantity++;
            this.updateDisplay();
            this.showAlert('success', '✅ ' + item.name + ' miktarı artırıldı');
          }
        },
        
        /**
         * Miktar azalt
         */
        decreaseQuantity: function(barcode) {
          const item = this.cart.find(item => item.barcode === barcode);
          if (item) {
            if (item.quantity > 1) {
              item.quantity--;
              this.updateDisplay();
              this.showAlert('success', '✅ ' + item.name + ' miktarı azaltıldı');
            } else {
              this.removeFromCart(barcode);
              this.showAlert('success', '✅ ' + item.name + ' sepetten çıkarıldı');
            }
          }
        },
        
        /**
         * Ekranı güncelle
         */
        updateDisplay: function() {
          const self = this;
          
          if (!self.elements.productList) return;
          
          if (self.cart.length === 0) {
            self.elements.productList.innerHTML = `
              <div class="pos-empty-cart">
                <div class="empty-icon">🛒</div>
                <p>Sepet boş</p>
                <small>Ürün eklemek için barkod okutun</small>
              </div>
            `;
            self.total = 0;
            if (self.elements.cartCount) self.elements.cartCount.textContent = '0';
            if (self.elements.totalItems) self.elements.totalItems.textContent = '0';
          } else {
            let html = '';
            let totalItems = 0;
            
            // REVERSE: En yeni eklenen en üstte görünsün
            const reversedCart = self.cart.slice().reverse();
            
            reversedCart.forEach(function(item) {
              const itemTotal = item.price * item.quantity;
              totalItems += item.quantity;
              
              // DEBUG: Marka bilgisini console'a yazdır
              console.log('🏷️ Ürün:', item.name, '| Brand:', item.brand);
              
              // Marka adı varsa göster (boş string bile olsa gösterme)
              let brandDisplay = '';
              if (item.brand && item.brand.trim() !== '') {
                brandDisplay = '<div class="product-brand"><span class="brand-icon">🏷️</span>' + self.escapeHtml(item.brand) + '</div>';
              } else {
                console.warn('⚠️ Marka bilgisi yok:', item.name);
              }
              
              html += `
                <div class="pos-product-item">
                  <div class="product-info">
                    <div class="product-name">${self.escapeHtml(item.name)}</div>
                    ${brandDisplay}
                    <div class="product-price">${item.price.toFixed(2)} ₺</div>
                  </div>
                  <div class="product-quantity-controls">
                    <button class="quantity-btn quantity-btn-minus" data-barcode="${self.escapeHtml(item.barcode)}">−</button>
                    <div class="product-quantity-display">${item.quantity}</div>
                    <button class="quantity-btn quantity-btn-plus" data-barcode="${self.escapeHtml(item.barcode)}">+</button>
                  </div>
                  <div class="product-total">${itemTotal.toFixed(2)} ₺</div>
                  <button class="product-remove-btn" data-barcode="${self.escapeHtml(item.barcode)}">🗑️</button>
                </div>
              `;
            });
            
            self.elements.productList.innerHTML = html;
            
            // Miktar artırma butonları
            const plusButtons = self.elements.productList.querySelectorAll('.quantity-btn-plus');
            plusButtons.forEach(function(btn) {
              btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const barcode = this.getAttribute('data-barcode');
                self.increaseQuantity(barcode);
              });
            });
            
            // Miktar azaltma butonları
            const minusButtons = self.elements.productList.querySelectorAll('.quantity-btn-minus');
            minusButtons.forEach(function(btn) {
              btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const barcode = this.getAttribute('data-barcode');
                self.decreaseQuantity(barcode);
              });
            });
            
            const removeButtons = self.elements.productList.querySelectorAll('.product-remove-btn');
            removeButtons.forEach(btn => {
              btn.addEventListener('click', function() {
                const barcode = this.getAttribute('data-barcode');
                self.removeFromCart(barcode);
              });
            });
            
            self.total = self.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            if (self.elements.cartCount) self.elements.cartCount.textContent = self.cart.length;
            if (self.elements.totalItems) self.elements.totalItems.textContent = totalItems;
          }
          
          if (self.elements.totalAmount) {
            self.elements.totalAmount.textContent = self.total.toFixed(2);
          }
        },
        
        /**
         * Stok doğrula
         */
        verifyStock: function(barcode) {
          // Background verification
        },
        
        /**
         * Manuel ürün ekle
         */
        addManualProduct: function() {
          const self = this;
          const name = self.elements.manualName ? self.elements.manualName.value.trim() : '';
          const price = self.elements.manualPrice ? parseFloat(self.elements.manualPrice.value) : 0;
          const barcode = self.elements.manualBarcode ? self.elements.manualBarcode.value.trim() : '';
          const stock = self.elements.manualStock ? parseInt(self.elements.manualStock.value) || 0 : 0;
          const brandId = document.getElementById('manualBrandId') ? document.getElementById('manualBrandId').value : '';
          
          if (!name || !price || !barcode) {
            self.showAlert('error', '❌ Ad, fiyat ve barkod zorunludur!');
            return;
          }
          
          self.showLoading(true);
          
          const posToken = self.settings.posToken || document.querySelector('.pos-wrapper').dataset.posToken;
          
          const requestData = {
            name: name,
            price: price,
            barcode: barcode,
            stock: stock
          };
          
          // Marka ID varsa ekle
          if (brandId) {
            requestData.brand_id = brandId;
          }
          
          fetch(self.settings.apiEndpoints.manual, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-POS-Token': posToken
            },
            body: JSON.stringify(requestData)
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                self.addToCart(barcode, data.product);
                self.showAlert('success', '✅ Manuel ürün eklendi!');
                
                // Formu temizle
                if (self.elements.manualName) self.elements.manualName.value = '';
                if (self.elements.manualPrice) self.elements.manualPrice.value = '';
                if (self.elements.manualBarcode) self.elements.manualBarcode.value = '';
                if (self.elements.manualStock) self.elements.manualStock.value = '';
                if (document.getElementById('manualBrand')) document.getElementById('manualBrand').value = '';
                if (document.getElementById('manualBrandId')) document.getElementById('manualBrandId').value = '';
                
                // Manuel ekleme panelini kapat
                if (self.elements.manualAdd) {
                  self.elements.manualAdd.style.display = 'none';
                }
              } else {
                self.showAlert('error', '❌ ' + (data.message || 'Ürün eklenemedi'));
              }
            })
            .catch(error => {
              console.error('Manuel ekleme hatası:', error);
              self.showAlert('error', '❌ Bağlantı hatası');
            })
            .finally(() => {
              self.showLoading(false);
            });
        },
        
        /**
         * Ödeme işle
         */
        processPayment: function(paymentType) {
          const self = this;
          
          if (self.cart.length === 0) {
            self.showAlert('error', '❌ Sepet boş!');
            return;
          }
          
          const paymentNames = {
            'cash': 'NAKİT',
            'credit_card': 'KREDİ KARTI',
            'transfer': 'HAVALE'
          };
          
          if (!confirm(`${paymentNames[paymentType]} ile ${self.total.toFixed(2)} ₺ ödeme?`)) {
            return;
          }
          
          self.showLoading(true);
          
          const posToken = self.settings.posToken || document.querySelector('.pos-wrapper').dataset.posToken;
          
          fetch(self.settings.apiEndpoints.save, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-POS-Token': posToken
            },
            body: JSON.stringify({
              items: self.cart,
              total: self.total,
              payment_type: paymentType
            })
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                self.cart = [];
                self.updateDisplay();
                self.showAlert('success', `🎉 ${paymentNames[paymentType]} ödeme tamamlandı!`);
                self.loadProductCache();
              }
            })
            .finally(() => {
              self.showLoading(false);
            });
        },
        
        /**
         * Sepeti temizle
         */
        clearCart: function() {
          this.cart = [];
          this.updateDisplay();
          this.showAlert('success', '🗑️ Sepet temizlendi');
        },
        
        /**
         * Alert göster
         */
        showAlert: function(type, message) {
          const alert = type === 'error' ? this.elements.errorAlert : this.elements.successAlert;
          if (!alert) return;
          
          const messageEl = alert.querySelector('.alert-message');
          if (messageEl) {
            messageEl.textContent = message;
          }
          
          alert.style.display = 'flex';
          
          setTimeout(function() {
            alert.style.display = 'none';
          }, 4000);
        },
        
        /**
         * Loading göster/gizle
         */
        showLoading: function(show) {
          if (this.elements.posLoading) {
            this.elements.posLoading.style.display = show ? 'flex' : 'none';
          }
        },
        
        /**
         * HTML escape
         */
        escapeHtml: function(text) {
          if (!text) return '';
          const div = document.createElement('div');
          div.textContent = text;
          return div.innerHTML;
        }
      };
      
      // POS'u başlat
      POS.init();
      
      // Global'e export et
      window.Drupal.behaviors.posApp.POS = POS;
    }
  };

})(Drupal, drupalSettings);
