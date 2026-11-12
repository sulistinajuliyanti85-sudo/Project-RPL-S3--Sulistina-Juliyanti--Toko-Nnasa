/**
 * NNasa AI Assistant Frontend Controller
 * Store: nnasa_ca Accessories Marketplace
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const triggerBtn = document.getElementById('nnasa-ai-trigger');
    const chatWindow = document.getElementById('nnasa-ai-window');
    const closeBtn = document.getElementById('btn-close-ai');
    const resetBtn = document.getElementById('btn-reset-ai');
    const sendBtn = document.getElementById('btn-send-ai');
    const inputField = document.getElementById('nnasa-ai-input');
    const messagesContainer = document.getElementById('nnasa-ai-messages');
    const typingIndicator = document.getElementById('nnasa-ai-typing');
    const quickChipsContainer = document.getElementById('nnasa-ai-chips');

    if (!triggerBtn || !chatWindow || !inputField || !sendBtn || !messagesContainer) {
      return;
    }

    const STORAGE_KEY = 'nnasa_ai_history';
    const STATE_KEY = 'nnasa_ai_open';
    const baseUrl = window.BASE_URL || '/toko/';

    // Toggle Chat Window
    function toggleChat(open = null) {
      const isOpen = open !== null ? open : !chatWindow.classList.contains('active');
      if (isOpen) {
        chatWindow.classList.add('active');
        triggerBtn.classList.add('chat-open');
        sessionStorage.setItem(STATE_KEY, '1');
        setTimeout(() => inputField.focus(), 300);
        scrollToBottom();
      } else {
        chatWindow.classList.remove('active');
        triggerBtn.classList.remove('chat-open');
        sessionStorage.setItem(STATE_KEY, '0');
      }
    }

    triggerBtn.addEventListener('click', function (e) {
      e.preventDefault();
      toggleChat();
    });

    closeBtn.addEventListener('click', function () {
      toggleChat(false);
    });

    // Restore previous state if open
    if (sessionStorage.getItem(STATE_KEY) === '1') {
      chatWindow.classList.add('active');
      triggerBtn.classList.add('chat-open');
    }

    // Scroll helper
    function scrollToBottom() {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Get current time string (HH:MM)
    function getTimeString() {
      const now = new Date();
      return now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
    }

    // Markdown-lite to Safe HTML Formatter
    function formatMarkdown(text) {
      if (!text) return '';
      // Escape basic HTML to prevent XSS
      let safe = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

      // Bold: **text**
      safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      // Strikethrough: ~~text~~
      safe = safe.replace(/~~(.*?)~~/g, '<del class="text-muted">$1</del>');
      // Inline code: `code`
      safe = safe.replace(/`([^`]+)`/g, '<code class="ai-inline-code">$1</code>');

      // Markdown links: [Title](URL)
      safe = safe.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (match, title, url) {
        // Prevent javascript: URLs
        if (/^javascript:/i.test(url)) return title;
        return `<a href="${url}" class="ai-chat-link" target="_self">${title} <i class="bi bi-box-arrow-up-right small"></i></a>`;
      });

      // Line breaks
      safe = safe.replace(/\n\n/g, '<div class="my-2"></div>');
      safe = safe.replace(/\n/g, '<br>');

      return safe;
    }

    // Append a message bubble to chat
    function appendMessage(sender, text, products = [], suggestions = [], saveToStorage = true, extra = {}) {
      const isUser = sender === 'user';
      const timeStr = getTimeString();

      const bubble = document.createElement('div');
      bubble.className = `ai-message-row ${isUser ? 'user-row' : 'bot-row'}`;

      let contentHtml = '';

      if (isUser) {
        contentHtml = `
          <div class="ai-bubble user-bubble">
            <div class="ai-bubble-text">${formatMarkdown(text)}</div>
            <div class="ai-bubble-time">${timeStr}</div>
          </div>
        `;
      } else {
        // Render stats badges if present
        let statsHtml = '';
        if (extra.stats_card && extra.stats_card.length > 0) {
          statsHtml = '<div class="ai-stats-row my-2 d-flex flex-wrap gap-2">';
          extra.stats_card.forEach(s => {
            statsHtml += `
              <div class="ai-stat-badge p-2 rounded border bg-light d-flex align-items-center gap-2">
                <i class="bi ${s.icon} text-${s.color} fs-5"></i>
                <div>
                  <div class="x-small text-muted">${s.label}</div>
                  <div class="fw-bold small text-dark">${s.value}</div>
                </div>
              </div>
            `;
          });
          statsHtml += '</div>';
        }

        // Render data table if present
        let tableHtml = '';
        if (extra.table && extra.table.headers && extra.table.rows && extra.table.rows.length > 0) {
          tableHtml = '<div class="table-responsive my-2 rounded border shadow-sm"><table class="table table-sm table-striped ai-chat-table mb-0 small">';
          tableHtml += '<thead class="table-light"><tr>';
          extra.table.headers.forEach(h => {
            tableHtml += `<th class="p-1 px-2 text-nowrap">${h}</th>`;
          });
          tableHtml += '</tr></thead><tbody>';
          extra.table.rows.forEach(r => {
            tableHtml += '<tr>';
            Object.values(r).forEach(val => {
              tableHtml += `<td class="p-1 px-2 text-nowrap">${val}</td>`;
            });
            tableHtml += '</tr>';
          });
          tableHtml += '</tbody></table></div>';
        }

        // Render product preview cards if present
        let productsHtml = '';
        if (products && products.length > 0) {
          productsHtml = '<div class="ai-products-preview mt-2">';
          products.forEach(p => {
            const discountBadge = p.has_discount 
              ? `<span class="badge bg-danger rounded-pill me-1">Diskon ${p.discount_percent}%</span>` 
              : '';
            const stockBadge = p.is_in_stock
              ? (p.is_low_stock 
                  ? `<span class="badge bg-warning text-dark rounded-pill">Sisa ${p.stock} pcs!</span>` 
                  : `<span class="badge bg-success-subtle text-success rounded-pill">Stok: ${p.stock} pcs</span>`)
              : `<span class="badge bg-secondary rounded-pill">Habis</span>`;

            productsHtml += `
              <div class="ai-product-card d-flex align-items-center mb-2 p-2">
                <img src="${p.image_url}" alt="${p.name}" class="ai-product-thumb rounded me-2">
                <div class="flex-grow-1 min-w-0">
                  <div class="small fw-bold text-truncate text-dark mb-1">${p.name}</div>
                  <div class="d-flex align-items-center gap-1 mb-1">
                    <span class="fw-bold text-primary-pink small">${p.price_formatted}</span>
                    ${p.has_discount ? `<span class="text-muted text-decoration-line-through x-small">${p.original_price_formatted}</span>` : ''}
                  </div>
                  <div class="d-flex align-items-center gap-1">
                    ${discountBadge}
                    ${stockBadge}
                  </div>
                </div>
                <a href="${p.url}" class="btn btn-sm btn-rose-gold ms-2 px-2 py-1 text-nowrap">
                  <i class="bi bi-eye"></i> Lihat
                </a>
              </div>
            `;
          });
          productsHtml += '</div>';
        }

        contentHtml = `
          <div class="d-flex align-items-start gap-2">
            <div class="ai-avatar-mini">
              <i class="bi bi-stars"></i>
            </div>
            <div class="ai-bubble bot-bubble">
              <div class="ai-bubble-text">${formatMarkdown(text)}</div>
              ${statsHtml}
              ${tableHtml}
              ${productsHtml}
              <div class="ai-bubble-time">${timeStr} &bull; NNasa AI</div>
            </div>
          </div>
        `;
      }

      bubble.innerHTML = contentHtml;
      messagesContainer.appendChild(bubble);

      // Render suggestions if provided
      if (!isUser && suggestions && suggestions.length > 0) {
        renderQuickChips(suggestions);
      }

      scrollToBottom();

      if (saveToStorage) {
        saveConversationToStorage(sender, text, products, suggestions, extra);
      }
    }

    // Render clickable suggestion chips
    function renderQuickChips(chips) {
      if (!quickChipsContainer) return;
      quickChipsContainer.innerHTML = '';
      if (!chips || chips.length === 0) return;

      chips.forEach(chipText => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ai-chip-btn';
        btn.textContent = chipText;
        btn.addEventListener('click', function () {
          inputField.value = chipText;
          sendMessage();
        });
        quickChipsContainer.appendChild(btn);
      });
      scrollToBottom();
    }

    // Save history to sessionStorage
    function saveConversationToStorage(sender, text, products = [], suggestions = [], extra = {}) {
      try {
        let history = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        history.push({ sender, text, products, suggestions, extra, time: getTimeString() });
        // Keep max 20 messages in storage
        if (history.length > 20) {
          history = history.slice(-20);
        }
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history));
      } catch (e) {
        console.warn('Storage save error:', e);
      }
    }

    // Restore history from sessionStorage
    function restoreConversation() {
      try {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        if (stored) {
          const history = JSON.parse(stored);
          if (Array.isArray(history) && history.length > 0) {
            messagesContainer.innerHTML = '';
            history.forEach(item => {
              appendMessage(item.sender, item.text, item.products || [], item.suggestions || [], false, item.extra || {});
            });
            scrollToBottom();
            return;
          }
        }
      } catch (e) {
        console.warn('Storage restore error:', e);
      }

      // Default initial welcome message if no history
      renderDefaultWelcome();
    }

    function renderDefaultWelcome() {
      const welcomeText = "Hai! Selamat datang di **nnasa_ca** 💕\nAku **NNasa AI**, asisten virtual cerdas terintegrasi langsung dengan database sistem toko. Aku siap membantu kamu mengecek stok riil, ringkasan produk, harga diskon, status pesanan, hingga panduan fitur platform toko. Ada yang bisa aku bantu hari ini?";
      appendMessage('bot', welcomeText, [], [
        '📊 Ringkasan Produk',
        '📦 Total Stok Fisik',
        '✨ Produk Aktif',
        '⚠️ Stok Menipis',
        '💎 Aksesoris Murah',
        '💡 Bantuan Fitur'
      ], false);
    }

    // Send Message Handler
    function sendMessage() {
      const text = inputField.value.trim();
      if (!text) return;

      // Append user bubble
      appendMessage('user', text);
      inputField.value = '';
      inputField.disabled = true;
      sendBtn.disabled = true;

      // Show typing indicator
      if (typingIndicator) {
        typingIndicator.style.display = 'flex';
        scrollToBottom();
      }

      // Clear chips while loading
      if (quickChipsContainer) {
        quickChipsContainer.innerHTML = '';
      }

      fetch(baseUrl + 'api/ai-chat.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ message: text })
      })
      .then(res => res.json())
      .then(data => {
        if (typingIndicator) {
          typingIndicator.style.display = 'none';
        }
        inputField.disabled = false;
        sendBtn.disabled = false;
        inputField.focus();

        if (data.success) {
          appendMessage('bot', data.reply, data.products || [], data.suggestions || [], true, {
            table: data.table || null,
            stats_card: data.stats_card || null
          });
        } else {
          appendMessage('bot', data.reply || data.message || 'Maaf, terjadi kesalahan saat menghubungi server.', [], ['Coba Lagi', 'Hubungi WhatsApp']);
        }
      })
      .catch(err => {
        console.error('AI Chat Error:', err);
        if (typingIndicator) {
          typingIndicator.style.display = 'none';
        }
        inputField.disabled = false;
        sendBtn.disabled = false;
        appendMessage('bot', 'Maaf, koneksi ke asisten sedang terganggu. Silakan periksa jaringan kamu atau hubungi admin nnasa_ca.', [], ['Hubungi WhatsApp', 'Buka Katalog']);
      });
    }

    sendBtn.addEventListener('click', function (e) {
      e.preventDefault();
      sendMessage();
    });

    inputField.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Reset Chat
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (confirm('Mulai ulang percakapan dengan NNasa AI?')) {
          sessionStorage.removeItem(STORAGE_KEY);
          messagesContainer.innerHTML = '';
          fetch(baseUrl + 'api/ai-chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reset: true })
          }).finally(() => {
            renderDefaultWelcome();
          });
        }
      });
    }

    // Initial restoration
    restoreConversation();
  });
})();
