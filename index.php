<?php
session_start();
// 1. PANGGIL KONEKSI DATABASE
require 'koneksi.php';

// 2. LOGIKA QUERY DEFAULT (Untuk rekomendasi)
// Karena pencarian utama sekarang pindah ke hotels.php, 
// di sini kita cukup ambil data default (misal: 6 hotel acak/terbaru untuk rekomendasi)
$query = "SELECT * FROM hotels ORDER BY rand() LIMIT 6";
$result = mysqli_query($conn, $query);

// Cek jika query error
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Laskar Trip — Hotel & Rent Car</title>

  <!-- Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <!-- HEADER BARU (Dark Theme) -->
  <header class="header-wrapper">
    <div class="container navbar">
      
      <!-- Kiri: Logo + Subtitle -->
      <div class="brand-area">
        <div class="logo-icon">LT</div>
        <div class="logo-text-group">
          <span class="logo-main">Laskar Trip</span>
          <span class="logo-sub">HOTEL & SEWA MOBIL</span>
        </div>
      </div>

      <!-- Kanan: Menu + Tombol -->
      <div class="nav-right">
        <!-- Currency Pill -->
        <div class="pill-currency">ID | IDR</div>

        <!-- Menu Links -->
        <nav class="nav-menu">
          <a href="index.php">
            Hotel <span class="badge-deal">Best Deal</span>
          </a>
          <a href="#">Sewa Mobil</a>
        </nav>

      <!-- Dark Mode Toggle (Visual Only) -->
      <button class="btn-dark-toggle">
        🌙 Dark
      </button>

      <!-- Auth / User Area -->
      <?php if (isset($_SESSION['username'])): ?>
        <!-- Profil User -->
        <a href="profile.php" class="user-badge">
          <span class="user-avatar">
            <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
          </span>
          <span class="user-name">
            <?= htmlspecialchars($_SESSION['username']) ?>
          </span>
        </a>
        <a href="logout.php" class="btn-auth-login">Logout</a>
      <?php else: ?>
        <a href="login.php" class="btn-auth-login">Masuk</a>
        <a href="register.php" class="btn-auth-signup">Daftar</a>
      <?php endif; ?>
      </div>

    </div>
  </header>

  <!-- MAIN -->
  <main class="container">
    <!-- HERO -->
    <section class="hero">
      <div class="hero-bg"></div>
      <div class="hero-grid">
        <div>
          <h1 class="hero-title">
            Booking <span>Hotel</span> &amp; <span>Rent Car</span> Dalam Satu Halaman.
          </h1>
          <p class="hero-sub">
            Laskar Trip Bantu Kamu Cari Penginapan Dan Sewa Mobil Terbaik Di Indonesia
            Dengan Harga Transparan, Review Jujur, Dan Proses Booking Yang Super Cepat.
          </p>

      <!-- SEARCH CARD -->
      <div class="search-wrapper">
        <div class="search-card">
          <!-- MAIN TABS (HOTEL vs CAR) -->
          <div class="search-tabs">
            <button class="search-tab active" data-target="#tab-hotel">
              <span class="dot"></span> Hotel
            </button>
            <button class="search-tab" data-target="#tab-car">
              <span class="dot"></span> Rent Car
            </button>
          </div>

          <!-- TAB HOTEL CONTENT -->
          <div id="tab-hotel" class="tab-pane active">
            <!-- FORM PENCARIAN DIUBAH DI SINI: Action mengarah ke hotels.php -->
            <form action="hotels.php" method="GET">
                <div class="search-grid">
                <div class="field">
                    <span class="field-label">Kota / Tujuan</span>
                    <div class="field-input">
                    <span class="field-icon">📍</span>
                    <!-- Input diberi name="keyword" untuk PHP -->
                    <input type="text" name="keyword" placeholder="Contoh: Bali, Bandung, Yogyakarta" required />
                    </div>
                </div>
                <div class="field">
                    <span class="field-label">Check-In</span>
                    <div class="field-input">
                    <span class="field-icon">📅</span>
                    <input type="date" name="checkin" />
                    </div>
                </div>
                <div class="field">
                    <span class="field-label">Check-Out</span>
                    <div class="field-input">
                    <span class="field-icon">📅</span>
                    <input type="date" name="checkout" />
                    </div>
                </div>
                <div class="field">
                    <span class="field-label">Guests And Rooms</span>
                    <div class="field-input">
                    <span class="field-icon">📍</span>
                    <input type="text" placeholder="2 Adult, 0 Child, 1 Room" />
                    </div>
                </div>
                
                <div class="field search-button-wrapper">
                    <button type="submit" class="btn-search">
                        <span class="icon">🔍</span>
                        Cari Hotel
                    </button>
                </div>
                </div>
            </form>
            <div class="search-hint"></div>
          </div>

          <!-- TAB CAR CONTENT -->
          <div id="tab-car" class="tab-pane">
            
            <!-- SUB TAB: WITH / WITHOUT DRIVER -->
            <div class="driver-tabs">
                <button class="driver-tab active" data-target="#with-driver">With Driver</button>
                <button class="driver-tab" data-target="#without-driver">Without Driver</button>
            </div>

            <!-- ================= WITH DRIVER ================= -->
            <div id="with-driver" class="driver-pane active">
                <div class="search-grid">
                <div class="field">
                    <span class="field-label">Lokasi penjemputan</span>
                    <div class="field-input">
                    <span class="field-icon">📍</span>
                    <input type="text" placeholder="Bandara / Stasiun / Lokasi kamu" />
                    </div>
                </div>

                <div class="field">
                    <span class="field-label">Tanggal &amp; jam mulai</span>
                    <div class="field-input">
                    <span class="field-icon">⏱</span>
                    <input type="text" placeholder="dd / mm / yyyy   -- : --" />
                    </div>
                </div>

                <div class="field">
                    <span class="field-label">Durasi sewa</span>
                    <div class="field-input">
                    <span class="field-icon">📆</span>
                    <select>
                        <option>12 Jam</option>
                        <option>24 Jam</option>
                        <option>2 Hari</option>
                        <option>3 Hari</option>
                        <option>1 Minggu</option>
                    </select>
                    </div>
                </div>

                <div class="field search-button-wrapper">
                    <button class="btn-search">
                    <span class="icon">🚗</span>
                    Cari Mobil
                    </button>
                </div>
                </div>

                <div class="search-hint">
                <span>Sopir profesional Laskar Trip sudah termasuk.</span>
                <span>BBM &amp; tol bisa pilih saat checkout.</span>
                </div>
            </div>

            <!-- ================= WITHOUT DRIVER ================= -->
            <div id="without-driver" class="driver-pane">
                <div class="search-grid">
                <div class="field">
                    <span class="field-label">Lokasi pengambilan</span>
                    <div class="field-input">
                    <span class="field-icon">📍</span>
                    <input type="text" placeholder="Kota / Lokasi rental" />
                    </div>
                </div>

                <div class="field">
                    <span class="field-label">Tanggal &amp; jam mulai</span>
                    <div class="field-input">
                    <span class="field-icon">⏱</span>
                    <input type="text" placeholder="dd / mm / yyyy   -- : --" />
                    </div>
                </div>

                <div class="field">
                    <span class="field-label">Tanggal &amp; jam selesai</span>
                    <div class="field-input">
                    <span class="field-icon">⏱</span>
                    <input type="text" placeholder="dd / mm / yyyy   -- : --" />
                    </div>
                </div>

                <div class="field search-button-wrapper">
                    <button class="btn-search">
                    <span class="icon">🚗</span>
                    Cari Mobil
                    </button>
                </div>
                </div>

                <div class="search-hint">
                <span>Tanpa sopir (lepas kunci), wajib punya SIM &amp; KTP aktif.</span>
                <span>Deposit &amp; aturan lainnya akan dijelaskan saat checkout.</span>
                </div>
            </div>
          </div> 
        </div>
      </div>   
    </section>

    <!-- WHY LASKAR TRIP -->
    <section class="section">
      <div class="section-heading">
        <div>
          <h2 class="section-title">Kenapa pilih Laskar Trip?</h2>
          <p class="section-sub">Satu halaman, semua kebutuhan perjalanan kamu.</p>
        </div>
      </div>

      <div class="feature-grid">
        <div class="feature-card">
          <div class="feature-icon">🎯</div>
          <div class="feature-title">All-in-one Travel Partner</div>
          <div class="feature-desc">
            Hotel, Apartemen, Villa, Sampai Mobil — Semua Bisa Kamu Atur Di Satu Tempat
            Tanpa Perlu Pindah-Pindah Aplikasi.
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-icon">💸</div>
          <div class="feature-title">Harga Jujur &amp; Transparan</div>
          <div class="feature-desc">
            Tidak Ada Biaya Tersembunyi. Harga Yang Kamu Lihat Adalah Harga Yang Kamu Bayar,
            Sebelum Pajak Dan Fee Dijelaskan Dengan Jelas.
          </div>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🤝</div>
          <div class="feature-title">Dukungan 24/7</div>
          <div class="feature-desc">
            Chat CS Kami Kapan Saja Dalam Bahasa Indonesia. Tim Kami Siap Bantu Sebelum,
            Saat, Dan Setelah Perjalananmu.
          </div>
        </div>
      </div>
    </section>

    <!-- POPULAR DESTINATIONS (Menampilkan 6 Rekomendasi) -->
    <section class="section">
      <div class="section-heading">
        <div>
          <h2 class="section-title">Pilihan Favorit Laskar Trip</h2>
          <p class="section-sub">Rekomendasi Destinasi Yang Diambil Langsung Dari Database.</p>
        </div>
        <div class="section-link">
          <?php if (isset($_SESSION['user_id'])): ?>
          <a href="hotels.php" class="see-all-link">Lihat semua ›</a>
        <?php else: ?>
          <a href="login.php?redirect=hotels.php" class="see-all-link">Lihat semua ›</a>
        <?php endif; ?>
          <span>›</span>
        </div>
      </div>

      <div class="card-grid">
        <?php 
        // 3. LOOPING DATA
        if(mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $harga_format = number_format($row['harga_per_malam'], 0, ',', '.');
                
                // Gunakan gambar default/gradient
                $bg_style = "background-image: linear-gradient(135deg, #0f172a, #2563eb);";
                if(strpos($row['nama_hotel'], 'Yogyakarta') !== false) {
                    $bg_style = "background-image: linear-gradient(135deg, #1d4ed8, #22c55e);";
                }
        ?>
            <!-- BUNGKUS DENGAN LINK KE DETAIL.PHP -->
            <a href="detail.php?id=<?= $row['id'] ?>" style="display:block;">
                <article class="destination-card">
                <div class="destination-image" style="<?= $bg_style ?>">
                    <!-- Tampilkan Lokasi dari DB -->
                    <div class="destination-badge"><?= $row['lokasi'] ?> • Hotel</div>
                </div>
                <div class="destination-body">
                    <!-- Tampilkan Nama Hotel dari DB -->
                    <div class="destination-title"><?= $row['nama_hotel'] ?></div>
                    <div class="destination-meta">
                    <!-- Tampilkan Rating & Review dari DB -->
                    <span><?= $row['rating'] ?> ⭐ <?= $row['jumlah_review'] ?> review</span>
                    <!-- Tampilkan Harga dari DB -->
                    <span class="price">Mulai Rp<?= $harga_format ?> /malam</span>
                    </div>
                </div>
                </article>
            </a>
        <?php 
            } // Akhir While
        } else {
            // JIKA TIDAK ADA DATA
            ?>
            <div style="grid-column: span 3; padding: 40px; text-align: center; background: #fff; border-radius: 12px; border: 1px dashed #ccc;">
                <h3 style="margin-bottom: 8px;">Belum ada data hotel.</h3>
                <p style="color: #6b7280; font-size: 14px;">Silakan tambahkan data melalui halaman admin.</p>
            </div>
            <?php
        }
        ?>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="section">
      <div class="section-heading">
        <div>
          <h2 class="section-title">Cara kerja Laskar Trip</h2>
          <p class="section-sub">Booking dalam 3 langkah singkat.</p>
        </div>
      </div>

      <div class="steps-grid">
        <div class="step-card">
          <div class="step-number">1</div>
          <div class="step-title">Pilih Destinasi & Tanggal</div>
          <p>
            Masukkan Kota Tujuan, Tanggal Menginap Atau Sewa Mobil, Lalu Klik Tombol Cari.
            Filter Sesuai Kebutuhanmu.
          </p>
        </div>
        <div class="step-card">
          <div class="step-number">2</div>
          <div class="step-title">Bandingkan & Pilih Paket</div>
          <p>
            Lihat Review Tamu, Tipe Kamar, Jenis Mobil, Kebijakan Refund, Dan Fasilitas
            Sebelum Melakukan Pembayaran.
          </p>
        </div>
        <div class="step-card">
          <div class="step-number">3</div>
          <div class="step-title">Bayar & Terima Voucher</div>
          <p>
            Pilih Metode Pembayaran Favoritmu. E-Voucher Dan Detail Pesanan Langsung
            Terkirim Ke Email & Whatsapp Kamu.
          </p>
        </div>
      </div>
    </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="brand">
            <div class="brand-logo">L</div>
            <div class="brand-text">Laskar<span>Trip</span></div>
          </div>
          <p style="margin-top:8px;">
            Laskar Trip Adalah Platform Pemesanan Hotel &amp; Sewa Mobil Yang Fokus Pada
            Pengalaman Pengguna Yang Sederhana, Jujur, Dan Menyenangkan.
          </p>
        </div>
        <div>
          <div class="footer-title">Produk</div>
          <div class="footer-links">
            <a href= "hotels.php">Hotel</a>
            <a>Rent Car</a>
            <a>Paket Hotel + Mobil</a>
            <a href= "admin.php">Admin</a>
          </div>
        </div>
        <div>
          <div class="footer-title">Perusahaan</div>
          <div class="footer-links">
            <a>Tentang Laskar Trip</a>
            <a>Karier</a>
            <a>Kerja sama</a>
          </div>
        </div>
        <div>
          <div class="footer-title">Bantuan</div>
          <div class="footer-links">
            <a>Pusat bantuan</a>
            <a>Syarat &amp; ketentuan</a>
            <a>Kebijakan privasi</a>
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <!-- PHP DYNAMIC DATE: Tahun akan berubah otomatis -->
        <span>© <?= date('Y'); ?> Laskar Trip. All rights reserved.</span>
        <div class="socmed">
          <a href="https://www.instagram.com/homekreatif_?igsh=aDJubDA0eDdqaGJl" aria-label="Instagram">IG</a>
          <a href="https://www.tiktok.com/@homekreatif_?_r=1&_t=ZS-91k7srIiJVZ" aria-label="TikTok">TT</a>
          <a href="https://pin.it/307Q6CpW5" aria-label="Pinterest">PT</a>
        </div>
      </div>
    </div>
  </footer>
  <script>
    // FUNGSI UNTUK TAB GENERIC (Bisa dipakai untuk Tab Utama & Driver Tab)
    function setupTabs(tabSelector, paneSelector) {
        const tabs = document.querySelectorAll(tabSelector);
        
        tabs.forEach((tab) => {
            tab.addEventListener("click", () => {
                const targetSelector = tab.getAttribute("data-target");

                // Hapus class active dari tab yang ditekan di grup ini
                const parent = tab.parentElement;
                parent.querySelectorAll(tabSelector).forEach(t => t.classList.remove("active"));
                
                // Cari semua pane yang sejenis dan hide
                const targetPane = document.querySelector(targetSelector);
                if(targetPane) {
                    const paneParent = targetPane.parentElement;
                    const siblings = paneParent.children;
                    for (let sibling of siblings) {
                        if (sibling.classList.contains("tab-pane") || sibling.classList.contains("driver-pane")) {
                            sibling.classList.remove("active");
                        }
                    }
                    targetPane.classList.add("active");
                }
                
                // Aktifkan tab yang diklik
                tab.classList.add("active");
            });
        });
    }

    // Jalankan setup untuk Tab Utama (Hotel vs Car)
    setupTabs(".search-tab", ".tab-pane");

    // Jalankan setup untuk Tab Driver (With vs Without)
    setupTabs(".driver-tab", ".driver-pane");
  </script>
<script>
  // ambil tombol dark mode
  const darkToggle = document.querySelector('.btn-dark-toggle');

  // kalau tombolnya ada
  if (darkToggle) {
    // cek preferensi yang tersimpan
    const savedTheme = localStorage.getItem('theme');

    if (savedTheme === 'dark') {
      document.body.classList.add('dark-mode');
    }

    // fungsi untuk update teks tombol
    function updateDarkLabel() {
      if (document.body.classList.contains('dark-mode')) {
        darkToggle.textContent = '☀️ Light';
      } else {
        darkToggle.textContent = '🌙 Dark';
      }
    }

    // set label awal
    updateDarkLabel();

    // kalau tombol diklik
    darkToggle.addEventListener('click', () => {
      document.body.classList.toggle('dark-mode');
      const theme = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
      localStorage.setItem('theme', theme);
      updateDarkLabel();
    });
  }
</script>
</body>
</html>