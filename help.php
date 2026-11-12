<?php
/**
 * Help Center & FAQ (Pusat Bantuan)
 * Store: nnasa_ca Accessories Marketplace
 */

$pageTitle = 'Pusat Bantuan & FAQ';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-5">
  <div class="text-center mb-5" style="max-width: 650px; margin: 0 auto;">
    <span class="badge bg-light text-primary-pink border px-3 py-2 rounded-pill fw-bold small mb-2">
      PUSAT BANTUAN
    </span>
    <h1 class="h3 fw-bold text-dark brand-font">Pertanyaan yang Sering Diajukan (FAQ)</h1>
    <p class="text-muted small">Temukan jawaban cepat seputar tata cara belanja, pembayaran, pengiriman, dan garansi di nnasa_ca.</p>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="accordion accordion-flush card border-0 shadow-sm rounded-4 overflow-hidden p-2" id="faqAccordion" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        
        <!-- Q1 -->
        <div class="accordion-item border-bottom">
          <h2 class="accordion-header" id="headingOne">
            <button class="accordion-button fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true">
              <i class="bi bi-question-circle text-primary-pink me-2"></i> Bagaimana cara berbelanja di nnasa_ca?
            </button>
          </h2>
          <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
            <div class="accordion-body small text-secondary leading-relaxed">
              1. Pilih produk aksesoris yang kamu sukai dari Katalog atau Beranda.<br>
              2. Tentukan varian warna atau ukuran dan kuantitas, lalu klik <strong>Tambah ke Keranjang</strong> atau <strong>Beli Sekarang</strong>.<br>
              3. Buka halaman Keranjang Belanja dan klik <strong>Lanjut ke Checkout</strong>.<br>
              4. Pilih alamat pengiriman, kurir, dan metode pembayaran.<br>
              5. Masukkan voucher promo jika ada, lalu selesaikan pembayaran sesuai instruksi.
            </div>
          </div>
        </div>

        <!-- Q2 -->
        <div class="accordion-item border-bottom">
          <h2 class="accordion-header" id="headingTwo">
            <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
              <i class="bi bi-wallet2 text-primary-pink me-2"></i> Apa saja metode pembayaran yang didukung?
            </button>
          </h2>
          <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body small text-secondary leading-relaxed">
              Kami mendukung pembayaran otomatis melalui QRIS (dapat dipindai dari BCA Mobile, Mandiri Livin', GoPay, OVO, ShopeePay, DANA) serta Virtual Account BCA dan Mandiri. Pada mode pengembangan XAMPP, sistem dilengkapi dengan simulator instan untuk pengujian alur pesanan.
            </div>
          </div>
        </div>

        <!-- Q3 -->
        <div class="accordion-item border-bottom">
          <h2 class="accordion-header" id="headingThree">
            <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
              <i class="bi bi-truck text-primary-pink me-2"></i> Berapa lama pesanan saya sampai dan bagaimana melacaknya?
            </button>
          </h2>
          <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body small text-secondary leading-relaxed">
              Pesanan diproses dalam 1x24 jam kerja. Pengiriman melalui SiCepat, JNE, dan J&T Express memerlukan waktu estimasi 1-3 hari kerja tergantung wilayah tujuan. Kamu dapat memantau pergerakan pesanan secara real-time melalui menu <strong>Lacak Pesanan</strong> dengan memasukkan nomor pesanan.
            </div>
          </div>
        </div>

        <!-- Q4 -->
        <div class="accordion-item border-bottom">
          <h2 class="accordion-header" id="headingFour">
            <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
              <i class="bi bi-gem text-primary-pink me-2"></i> Apakah aksesoris nnasa_ca hypoallergenic dan tidak mudah luntur?
            </button>
          </h2>
          <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body small text-secondary leading-relaxed">
              Sebagian besar koleksi kalung, cincin, dan anting kami menggunakan lapisan 18K Rose Gold Plated dan jarum perak 925 anti alergi yang aman untuk kulit sensitif. Untuk menjaga kilau perhiasan, hindari kontak langsung dengan cairan kimia keras, parfum, atau air klorin kolam renang.
            </div>
          </div>
        </div>

        <!-- Q5 -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingFive">
            <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive">
              <i class="bi bi-arrow-repeat text-primary-pink me-2"></i> Bagaimana kebijakan garansi jika produk diterima rusak?
            </button>
          </h2>
          <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body small text-secondary leading-relaxed">
              Jika produk yang kamu terima rusak, cacat produksi, atau salah varian, kami memberikan garansi penggantian barang baru gratis. Cukup lampirkan video unboxing paket dan hubungi admin kami melalui WhatsApp maksimal 2x24 jam setelah paket diterima.
            </div>
          </div>
        </div>

      </div>

      <div class="text-center mt-4">
        <p class="text-muted small">Masih membutuhkan bantuan lainnya?</p>
        <a href="<?= BASE_URL ?>contact.php" class="btn btn-sm btn-nnasa-outline rounded-pill px-4">
          Hubungi Tim Kami Sekarang
        </a>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
