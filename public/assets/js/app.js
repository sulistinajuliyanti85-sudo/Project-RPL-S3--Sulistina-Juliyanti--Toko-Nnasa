/**
 * nnasa_ca Customer Frontend JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
  // Initialize Bootstrap tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Global Toast Notification Helper
  window.showToast = function (message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '1100';
      document.body.appendChild(container);
    }

    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success text-white' : (type === 'danger' ? 'bg-danger text-white' : 'bg-dark text-white');
    const icon = type === 'success' ? 'bi-check-circle-fill' : (type === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');

    const toastHtml = `
      <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body d-flex align-items-center">
            <i class="bi ${icon} fs-5 me-2"></i>
            <span>${message}</span>
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    const bsToast = new bootstrap.Toast(toastEl, { delay: 3500 });
    bsToast.show();

    toastEl.addEventListener('hidden.bs.toast', function () {
      toastEl.remove();
    });
  };

  // Global Incoming WhatsApp / SMS OTP Notification Simulation Banner
  window.showIncomingOtpNotification = function (options = {}) {
    const channel = (options.channel || 'whatsapp').toLowerCase();
    const isWa = channel === 'whatsapp';
    const otp = options.otp || '';
    const phone = options.phone || options.destination || '';
    const autoFillSelector = options.autoFillSelector || null;
    const sender = options.sender || (isWa ? 'nnasa_ca Official (WhatsApp)' : 'SMS nnasa_ca');
    const rawMessage = options.message || (isWa 
      ? `Kode verifikasi nnasa_ca kamu adalah: ${otp}. Berlaku selama 5 menit. Jangan bagikan kode ini kepada siapapun demi keamanan akun kamu.`
      : `[nnasa_ca] Kode verifikasi SMS kamu: ${otp}. Berlaku 5 menit.`);

    let notifContainer = document.getElementById('otp-incoming-notif-container');
    if (!notifContainer) {
      notifContainer = document.createElement('div');
      notifContainer.id = 'otp-incoming-notif-container';
      notifContainer.className = 'otp-incoming-container';
      document.body.appendChild(notifContainer);
    }

    const notifId = 'otp-notif-' + Date.now();
    const brandColor = isWa ? '#25D366' : '#0d6efd';
    const brandDark = isWa ? '#128C7E' : '#0a58ca';
    const iconClass = isWa ? 'bi-whatsapp' : 'bi-chat-square-text-fill';

    // Play subtle incoming notification chime using Web Audio API
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (AudioContext) {
        const audioCtx = new AudioContext();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.12); // A5
        gain.gain.setValueAtTime(0.12, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.35);
      }
    } catch (e) {
      // Audio autoplay policy fallback
    }

    // Try subtle device vibration
    if (navigator.vibrate) {
      try { navigator.vibrate([100, 50, 100]); } catch (e) {}
    }

    const html = `
      <div id="${notifId}" class="card border-0 shadow-lg mb-3 otp-incoming-card"
           style="background:#ffffff; border-left: 5px solid ${brandColor} !important;">
        <div class="card-header bg-white border-0 pt-3 pb-2 px-3 d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:32px; height:32px; background:${isWa ? '#E7FCE8' : '#E7F1FF'}; color:${brandColor};">
              <i class="bi ${iconClass} fs-5"></i>
            </span>
            <div>
              <div class="fw-bold text-dark small mb-0 lh-1">${isWa ? 'WhatsApp' : 'Pesan SMS'}</div>
              <small class="text-muted" style="font-size: 11px;">Notifikasi Pesan Masuk &bull; Baru saja</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-sm p-1" aria-label="Close" onclick="document.getElementById('${notifId}').remove()"></button>
        </div>
        <div class="card-body pt-1 pb-3 px-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="small fw-semibold text-dark"><i class="bi bi-shield-check text-success me-1"></i> ${escapeHtml(sender)}</span>
            ${phone ? `<span class="badge bg-light text-muted border font-monospace" style="font-size: 10px;">${escapeHtml(phone)}</span>` : ''}
          </div>
          <div class="small text-secondary mb-3 p-2 bg-light rounded-3 border" style="font-size: 13px; line-height: 1.45;">
            ${escapeHtml(rawMessage)}
          </div>
          ${otp ? `
            <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-success-subtle border border-success-subtle mb-3">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-key-fill text-success fs-5"></i>
                <span class="small fw-bold text-success-emphasis">Kode OTP:</span>
              </div>
              <span class="font-monospace fs-4 fw-bold text-dark px-3 py-1 bg-white border border-success rounded-pill shadow-sm tracking-wider">${otp}</span>
            </div>
          ` : ''}
          <div class="d-flex gap-2">
            ${(otp && autoFillSelector) ? `
              <button type="button" class="btn btn-sm btn-success rounded-pill w-100 fw-bold py-2 btn-auto-fill shadow-sm" style="background-color:${brandColor}; border-color:${brandDark};">
                <i class="bi bi-lightning-charge-fill me-1"></i> Isi Otomatis
              </button>
            ` : ''}
            ${otp ? `
              <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill w-100 fw-semibold py-2 btn-copy-otp">
                <i class="bi bi-clipboard me-1"></i> Salin Kode
              </button>
            ` : ''}
          </div>
        </div>
        <div class="progress" style="height: 3px; background: rgba(0,0,0,0.05);">
          <div class="progress-bar" style="width: 100%; background: ${brandColor}; transition: width 25s linear;"></div>
        </div>
      </div>
    `;

    notifContainer.insertAdjacentHTML('beforeend', html);
    const cardEl = document.getElementById(notifId);

    // Auto-fill button click handler
    const autoFillBtn = cardEl.querySelector('.btn-auto-fill');
    if (autoFillBtn && autoFillSelector) {
      autoFillBtn.addEventListener('click', function () {
        const targetInput = document.querySelector(autoFillSelector);
        if (targetInput) {
          targetInput.value = otp;
          targetInput.dispatchEvent(new Event('input', { bubbles: true }));
          targetInput.dispatchEvent(new Event('change', { bubbles: true }));
          targetInput.focus();
          if (typeof window.showToast === 'function') {
            window.showToast('Kode OTP ' + otp + ' berhasil diisikan otomatis! ⚡', 'success');
          }
          autoFillBtn.innerHTML = '<i class="bi bi-check2-all me-1"></i> Terisi!';
          autoFillBtn.disabled = true;
        }
      });
    }

    // Copy OTP button click handler
    const copyBtn = cardEl.querySelector('.btn-copy-otp');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(otp);
        } else {
          const temp = document.createElement('textarea');
          temp.value = otp;
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          document.body.removeChild(temp);
        }
        this.innerHTML = '<i class="bi bi-check2 me-1"></i> Tersalin!';
        this.classList.remove('btn-outline-secondary');
        this.classList.add('btn-secondary');
        setTimeout(() => {
          if (cardEl && cardEl.parentNode) {
            this.innerHTML = '<i class="bi bi-clipboard me-1"></i> Salin Kode';
            this.classList.add('btn-outline-secondary');
            this.classList.remove('btn-secondary');
          }
        }, 2000);
      });
    }

    // Progress bar animation & auto dismiss
    const pBar = cardEl.querySelector('.progress-bar');
    setTimeout(() => {
      if (pBar) pBar.style.width = '0%';
    }, 100);

    setTimeout(() => {
      if (cardEl && cardEl.parentNode) {
        cardEl.remove();
      }
    }, 25000);
  };

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // Wishlist Toggle
  document.querySelectorAll('.btn-wishlist-toggle').forEach(function (button) {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      const productId = this.getAttribute('data-product-id');
      const icon = this.querySelector('i');

      fetch(window.BASE_URL + 'api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'unauthorized') {
          window.location.href = window.BASE_URL + 'login.php';
          return;
        }
        if (data.status === 'added') {
          this.classList.add('active');
          if (icon) {
            icon.classList.remove('bi-heart');
            icon.classList.add('bi-heart-fill');
          }
          showToast('Produk ditambahkan ke Favorit! 💕', 'success');
        } else if (data.status === 'removed') {
          this.classList.remove('active');
          if (icon) {
            icon.classList.remove('bi-heart-fill');
            icon.classList.add('bi-heart');
          }
          showToast('Produk dihapus dari Favorit.', 'info');
        }
        // Update wishlist counter badge
        if (typeof data.count !== 'undefined') {
          document.querySelectorAll('.wishlist-counter-badge').forEach(badge => {
            badge.textContent = data.count;
          });
        }
      })
      .catch(err => {
        console.error(err);
        showToast('Gagal mengubah favorit.', 'danger');
      });
    });
  });

  // Quantity Stepper on Product Detail & Cart
  document.querySelectorAll('.quantity-stepper').forEach(function (stepper) {
    const minusBtn = stepper.querySelector('.btn-minus');
    const plusBtn = stepper.querySelector('.btn-plus');
    const input = stepper.querySelector('.qty-input');

    if (minusBtn && plusBtn && input) {
      minusBtn.addEventListener('click', function () {
        let val = parseInt(input.value) || 1;
        const min = parseInt(input.getAttribute('min')) || 1;
        if (val > min) {
          input.value = val - 1;
          input.dispatchEvent(new Event('change'));
        }
      });

      plusBtn.addEventListener('click', function () {
        let val = parseInt(input.value) || 1;
        const max = parseInt(input.getAttribute('max')) || 999;
        if (val < max) {
          input.value = val + 1;
          input.dispatchEvent(new Event('change'));
        } else {
          showToast('Jumlah maksimal stok yang tersedia telah tercapai.', 'warning');
        }
      });
    }
  });

  // Add to Cart handler
  document.querySelectorAll('.btn-add-to-cart').forEach(function (button) {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      const productId = this.getAttribute('data-product-id');
      const qtyInput = document.querySelector(`input[name="quantity"][data-product-id="${productId}"]`) || document.querySelector('#detail-quantity-input');
      const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
      
      // Check selected variant
      let variantId = null;
      const activeVariant = document.querySelector('.variant-pill.active');
      if (activeVariant) {
        variantId = activeVariant.getAttribute('data-variant-id');
      }

      const originalHtml = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menambahkan...';

      fetch(window.BASE_URL + 'api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'add',
          product_id: productId,
          variant_id: variantId,
          quantity: quantity
        })
      })
      .then(res => res.json())
      .then(data => {
        this.disabled = false;
        this.innerHTML = originalHtml;

        if (data.status === 'unauthorized') {
          window.location.href = window.BASE_URL + 'login.php';
          return;
        }

        if (data.success) {
          showToast('Produk berhasil masuk ke keranjang! 🛍️', 'success');
          // Update cart badges
          document.querySelectorAll('.cart-counter-badge').forEach(badge => {
            badge.textContent = data.cart_count;
            badge.style.display = 'flex';
          });
        } else {
          showToast(data.message || 'Gagal menambahkan ke keranjang.', 'danger');
        }
      })
      .catch(err => {
        this.disabled = false;
        this.innerHTML = originalHtml;
        console.error(err);
        showToast('Terjadi kesalahan koneksi.', 'danger');
      });
    });
  });

  // Dark & Light Mode Toggle Controller
  const themeToggleBtn = document.getElementById('themeToggleBtn');
  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', function () {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

      document.documentElement.setAttribute('data-theme', newTheme);
      document.documentElement.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('nnasa_theme', newTheme);
    });
  }
});
