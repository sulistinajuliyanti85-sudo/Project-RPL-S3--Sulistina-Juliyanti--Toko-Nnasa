<?php
/**
 * NNasa AI Assistant Chatbot Widget Component
 * Store: nnasa_ca Accessories Marketplace
 * Injected cleanly into all customer pages
 */
if (function_exists('get_setting') && get_setting('ai_assistant_enabled', '1') === '0') {
    return;
}
?>
<!-- NNasa AI Floating Trigger Button -->
<div id="nnasa-ai-trigger-container" class="nnasa-ai-trigger-wrapper">
  <button type="button" id="nnasa-ai-trigger" class="nnasa-ai-fab shadow-lg" aria-label="Tanya NNasa AI Assistant" title="Tanya NNasa AI Assistant ✨">
    <div class="fab-icon-box">
      <i class="bi bi-chat-heart-fill icon-open"></i>
      <i class="bi bi-x-lg icon-close"></i>
    </div>
    <span class="ai-status-pulse"></span>
    <span class="ai-fab-label d-none d-md-inline-block">Tanya NNasa AI</span>
  </button>
</div>

<!-- NNasa AI Chat Window -->
<div id="nnasa-ai-window" class="nnasa-ai-popup shadow-lg" role="dialog" aria-modal="true" aria-labelledby="ai-chat-title">
  <!-- Header -->
  <div class="ai-popup-header d-flex align-items-center justify-content-between p-3">
    <div class="d-flex align-items-center gap-2">
      <div class="ai-header-avatar">
        <i class="bi bi-stars"></i>
      </div>
      <div>
        <h6 id="ai-chat-title" class="mb-0 fw-bold brand-font text-dark">NNasa AI Assistant</h6>
        <div class="d-flex align-items-center gap-1">
          <span class="ai-online-indicator"></span>
          <span class="ai-header-status text-muted">Online &bull; Asisten Resmi Toko</span>
        </div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-1">
      <button type="button" id="btn-reset-ai" class="btn btn-sm btn-icon-glass" title="Mulai Ulang Percakapan" aria-label="Mulai Ulang Percakapan">
        <i class="bi bi-arrow-clockwise"></i>
      </button>
      <button type="button" id="btn-close-ai" class="btn btn-sm btn-icon-glass" title="Tutup Chat" aria-label="Tutup Chat">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>

  <!-- Messages Scroll Area -->
  <div id="nnasa-ai-messages" class="ai-popup-body p-3">
    <!-- Message rows dynamically populated by ai-chat.js -->
  </div>

  <!-- Typing Indicator (Hidden by default) -->
  <div id="nnasa-ai-typing" class="ai-typing-indicator px-3 py-2" style="display: none;">
    <div class="ai-avatar-mini me-2">
      <i class="bi bi-stars"></i>
    </div>
    <div class="typing-bubble">
      <span class="typing-dot"></span>
      <span class="typing-dot"></span>
      <span class="typing-dot"></span>
    </div>
    <span class="small text-muted ms-2 fst-italic">NNasa AI sedang mengetik...</span>
  </div>

  <!-- Quick Suggestion Chips -->
  <div id="nnasa-ai-chips" class="ai-chips-wrapper px-3 py-2">
    <!-- Chips dynamically populated -->
  </div>

  <!-- Footer / Input Form -->
  <div class="ai-popup-footer p-2 bg-white border-top">
    <form id="nnasa-ai-form" onsubmit="return false;" class="d-flex align-items-center gap-2">
      <input 
        type="text" 
        id="nnasa-ai-input" 
        class="form-control ai-chat-input" 
        placeholder="Tanya aksesoris, harga, stok, atau promo..." 
        autocomplete="off" 
        maxlength="500"
      >
      <button type="button" id="btn-send-ai" class="btn btn-rose-gold btn-send-ai rounded-circle" aria-label="Kirim Pesan">
        <i class="bi bi-send-fill"></i>
      </button>
    </form>
    <div class="text-center mt-1">
      <span class="ai-footer-disclaimer text-muted">
        <i class="bi bi-shield-check text-success me-1"></i>Terhubung data resmi &bull; 100% Faktual
      </span>
    </div>
  </div>
</div>
