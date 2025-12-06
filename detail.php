<?php
session_start();
require 'koneksi.php';

// ===== WAJIB LOGIN UNTUK LIHAT DETAIL HOTEL =====
if (!isset($_SESSION['user_id'])) {
    // simpan URL sekarang supaya nanti bisa balik ke sini setelah login
    $currentUrl = $_SERVER['REQUEST_URI']; // contoh: /laskar-trip/detail.php?id=2
    $redirect   = 'login.php?redirect=' . urlencode($currentUrl);
    header("Location: $redirect");
    exit;
}

// ambil id hotel dari URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Query data hotel
$query = mysqli_query($conn, "SELECT * FROM hotels WHERE id = '$id'");
$data  = mysqli_fetch_array($query);

// Redirect jika data tidak ada
if (!$data) {
    header("Location: index.php");
    exit;
}

// Format harga
$harga_format = number_format($data['harga_per_malam'], 0, ',', '.');

// ====== REVIEW TAMU DARI DATABASE ======
// Nilai awal dari tabel hotels (kalau tidak ada review detail)
$avg_rating    = isset($data['rating']) ? (float)$data['rating'] : 0;
$total_reviews = isset($data['jumlah_review']) ? (int)$data['jumlah_review'] : 0;

// Ambil ringkasan review (jumlah & rata-rata) dari tabel hotel_reviews
$summarySql = "SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating),0) AS avg_rating
               FROM hotel_reviews
               WHERE hotel_id = ?";
if ($stmtSummary = mysqli_prepare($conn, $summarySql)) {
    mysqli_stmt_bind_param($stmtSummary, "i", $id);
    mysqli_stmt_execute($stmtSummary);
    $summaryResult = mysqli_stmt_get_result($stmtSummary);
    if ($rowSummary = mysqli_fetch_assoc($summaryResult)) {
        if ($rowSummary['total_reviews'] > 0) {
            $total_reviews = (int)$rowSummary['total_reviews'];
            $avg_rating    = round((float)$rowSummary['avg_rating'], 1);
        }
    }
    mysqli_stmt_close($stmtSummary);
}

// Ambil daftar review terbaru (maksimal 3)
$reviews = [];
$reviewSql = "SELECT nama_tamu, rating, komentar, created_at
              FROM hotel_reviews
              WHERE hotel_id = ?
              ORDER BY created_at DESC
              LIMIT 3";
if ($stmtReview = mysqli_prepare($conn, $reviewSql)) {
    mysqli_stmt_bind_param($stmtReview, "i", $id);
    mysqli_stmt_execute($stmtReview);
    $reviewResult = mysqli_stmt_get_result($stmtReview);
    while ($rowReview = mysqli_fetch_assoc($reviewResult)) {
        $reviews[] = $rowReview;
    }
    mysqli_stmt_close($stmtReview);
}

// ====== CEK APAKAH USER BOLEH MEMBERI REVIEW ======
$can_review      = false;
$review_info_msg = '';
$logged_name     = '';

if (isset($_SESSION['user_id'])) {
    $current_user_id = (int)$_SESSION['user_id'];

    // coba ambil nama dari session kalau ada
    if (isset($_SESSION['nama_lengkap'])) {
        $logged_name = $_SESSION['nama_lengkap'];
    } elseif (isset($_SESSION['nama'])) {
        $logged_name = $_SESSION['nama'];
    } elseif (isset($_SESSION['username'])) {
        $logged_name = $_SESSION['username'];
    }

    // cek apakah user punya booking di hotel ini
    $sqlCheckBooking = "
        SELECT COUNT(*) AS jml 
        FROM bookings 
        WHERE user_id = ? 
          AND hotel_id = ? 
          AND status IN ('pending','paid','success','completed')
    ";
    if ($stmtCB = mysqli_prepare($conn, $sqlCheckBooking)) {
        mysqli_stmt_bind_param($stmtCB, "ii", $current_user_id, $id);
        mysqli_stmt_execute($stmtCB);
        $resCB = mysqli_stmt_get_result($stmtCB);
        if ($rowCB = mysqli_fetch_assoc($resCB)) {
            if ((int)$rowCB['jml'] > 0) {
                $can_review = true;
            } else {
                $review_info_msg = 'Kamu perlu memiliki booking di hotel ini sebelum menulis ulasan.';
            }
        }
        mysqli_stmt_close($stmtCB);
    }
} else {
    $review_info_msg = 'Silakan login terlebih dahulu untuk menulis ulasan.';
}

// pesan setelah kirim review
$review_alert = '';
if (isset($_GET['review'])) {
    switch ($_GET['review']) {
        case 'ok':
            $review_alert = 'Terima kasih! Ulasanmu berhasil dikirim.';
            break;
        case 'invalid':
            $review_alert = 'Rating dan ulasan wajib diisi dengan benar.';
            break;
        case 'nobooking':
            $review_alert = 'Kamu belum memiliki booking di hotel ini, sehingga belum dapat menulis ulasan.';
            break;
        case 'error':
            $review_alert = 'Terjadi kesalahan saat menyimpan ulasan. Coba lagi nanti.';
            break;
    }
}

// Simulasi gambar gallery (menggunakan gradient/placeholder karena belum ada fitur upload gambar banyak)
// Di proyek asli, ini biasanya diambil dari tabel 'hotel_images'
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $data['nama_hotel'] ?> — Laskar Trip</title>
  
  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css">

  <style>
    :root {
      --primary: #22c55e; /* Hijau seperti tombol 'Pilih Kamar' di contoh */
      --primary-dark: #16a34a;
      --text-main: #111827;
      --text-muted: #6b7280;
      --bg: #ffffff;
      --border: #e5e7eb;
      --radius: 12px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: "Inter", sans-serif;
      color: var(--text-main);
      background: var(--bg);
      line-height: 1.5;
      padding-bottom: 80px;
    }

    .container {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* NAVBAR */
    .navbar {
      height: 70px;
      display: flex;
      align-items: center;
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 50;
    }
    
    .brand { font-weight: 700; font-size: 20px; color: #2563eb; text-decoration: none; }
    .brand span { color: var(--primary); }

    /* GALLERY GRID */
    .gallery-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      grid-template-rows: 200px 200px;
      gap: 8px;
      margin-top: 24px;
      border-radius: 16px;
      overflow: hidden;
    }

    .gallery-item {
      background-color: #e5e7eb;
      background-size: cover;
      background-position: center;
      position: relative;
    }

    /* Item pertama (besar kiri) */
    .gallery-item:nth-child(1) {
      grid-column: 1 / 2;
      grid-row: 1 / 3;
      background-image: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1000&q=80');
    }
    .gallery-item:nth-child(2) { background-image: url('https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=500&q=80'); }
    .gallery-item:nth-child(3) { background-image: url('https://images.unsplash.com/photo-1584132967334-10e028bd69f7?auto=format&fit=crop&w=500&q=80'); }
    .gallery-item:nth-child(4) { background-image: url('https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=500&q=80'); }
    .gallery-item:nth-child(5) { 
      background-image: url('https://images.unsplash.com/photo-1596436889106-be35e843f974?auto=format&fit=crop&w=500&q=80'); 
      position: relative;
    }
    
    /* Overlay 'Lihat Semua Foto' */
    .gallery-more {
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.4);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      cursor: pointer;
    }

    /* HEADER INFO */
    .hotel-header {
      margin-top: 24px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 24px;
      border-bottom: 1px solid #f3f4f6;
    }

    .hotel-title h1 {
      font-size: 26px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .hotel-location {
      color: var(--text-muted);
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .hotel-price {
      text-align: right;
    }

    .price-label {
      font-size: 12px;
      color: var(--text-muted);
    }

    .price-amount {
      font-size: 28px;
      font-weight: 700;
      color: #dc2626; /* Merah harga */
      margin: 4px 0 12px;
    }

    .btn-select {
      background: var(--primary);
      color: #fff;
      border: none;
      padding: 10px 24px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      transition: 0.2s;
    }
    .btn-select:hover { background: var(--primary-dark); }

    /* HIGHLIGHT CARDS (Serunya Menginap) */
    .section-title {
      font-size: 18px;
      font-weight: 600;
      margin: 32px 0 16px;
    }

    .highlights-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }

    .highlight-card {
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .highlight-icon {
      width: 40px;
      height: 40px;
      background: #f0fdf4;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }

    .highlight-text h3 { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
    .highlight-text p { font-size: 12px; color: var(--text-muted); line-height: 1.4; }

    /* REVIEWS */
    .review-summary {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }
    
    .rating-big { font-size: 32px; font-weight: 700; }
    .rating-label { font-size: 14px; color: var(--text-muted); }

    .review-cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }

    .review-card {
      background: #f9fafb;
      padding: 16px;
      border-radius: 12px;
      border: 1px solid #f3f4f6;
    }

    .reviewer-name { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
    .review-date { font-size: 11px; color: var(--text-muted); margin-bottom: 8px; display: block;}
    .review-text { font-size: 13px; color: #374151; font-style: italic; }

    /* FACILITIES */
    .facility-list {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .facility-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: #f9fafb;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
    }

    /* LOCATION LIST */
    .location-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    
    .location-item {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      padding-bottom: 8px;
      border-bottom: 1px dashed var(--border);
    }
    .loc-name { display: flex; align-items: center; gap: 8px; }
    .loc-dist { color: var(--text-muted); }

    /* RESPONSIVE */
    @media (max-width: 768px) {
      .gallery-grid { grid-template-columns: 1fr; grid-template-rows: 250px; }
      .gallery-item:not(:first-child) { display: none; } /* Hide small images on mobile */
      
      .hotel-header { flex-direction: column; gap: 16px; }
      .hotel-price { text-align: left; width: 100%; border-top: 1px solid #f3f4f6; padding-top: 16px; }
      
      .highlights-grid, .review-cards { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar detail-navbar">
  <div class="detail-topbar detail-header">
    <a href="index.php" class="brand">Laskar<span>Trip</span></a>
    <a href="index.php" class="detail-back-link">Kembali ke Home</a>
  </div>
</nav>

<main class="container">
  
  <!-- GALLERY -->
  <div class="gallery-grid">
    <div class="gallery-item"></div>
    <div class="gallery-item"></div>
    <div class="gallery-item"></div>
    <div class="gallery-item"></div>
    <div class="gallery-item">
      <div class="gallery-more">+ Lihat Semua Foto</div>
    </div>
  </div>

  <!-- TITLE & PRICE -->
  <div class="hotel-header">
    <div class="hotel-title">
      <h1><?= $data['nama_hotel'] ?></h1>
      <div class="hotel-location">
        <span>📍</span> <?= $data['lokasi'] ?>, Indonesia • Dekat Pusat Kota
      </div>
    </div>
    <div class="hotel-price">
      <div class="price-label">Harga Per Malam Mulai Dari</div>
      <div class="price-amount">Rp<?= $harga_format ?></div>
      <div class="hotel-price">

  <!-- TOMBOL BARU -->
  <a
    href="pembayaran.php?id=<?= $data['id']; ?>" 
    class="btn-select"
    data-hotel="<?= htmlspecialchars($data['nama_hotel']); ?>"
    onclick="handleBookingClick(event)"
  >
    Pilih Kamar
  </a>
</div>
      <div style="font-size:11px; color:#9ca3af; margin-top:4px;">Belum termasuk pajak & biaya lainnya.</div>
    </div>
  </div>

  <!-- HIGHLIGHTS (STATIC UI) -->
  <h2 class="section-title">Serunya Menginap Di Sini</h2>
  <div class="highlights-grid">
    <div class="highlight-card">
      <div class="highlight-icon">🍽️</div>
      <div class="highlight-text">
        <h3>Pilihan Makan Beragam</h3>
        <p>Restoran hotel & spot kuliner hits bisa dijangkau jalan kaki.</p>
      </div>
    </div>
    <div class="highlight-card">
      <div class="highlight-icon">🛍️</div>
      <div class="highlight-text">
        <h3>Dekat Pusat Belanja</h3>
        <p>Cuma beberapa menit ke Mall Besar & Pusat Perkantoran.</p>
      </div>
    </div>
    <div class="highlight-card">
      <div class="highlight-icon">🚆</div>
      <div class="highlight-text">
        <h3>Akses Transportasi</h3>
        <p>Jalan kaki ke Stasiun MRT & Halte Transjakarta terdekat.</p>
      </div>
    </div>
    <div class="highlight-card">
      <div class="highlight-icon">💻</div>
      <div class="highlight-text">
        <h3>Nyaman Untuk Kerja</h3>
        <p>Kamar dilengkapi meja kerja, kursi nyaman, dan Wi-Fi cepat.</p>
      </div>
    </div>
  </div>

  <!-- DESCRIPTION -->
  <h2 class="section-title">Tentang Akomodasi</h2>
  <p style="font-size:14px; color:#4b5563; line-height:1.6; max-width:800px;">
    <?= $data['deskripsi'] ?>
  </p>

      <!-- REVIEW TAMU -->
  <h2 class="section-title">Review Tamu</h2>

  <!-- pesan setelah kirim -->
  <?php if ($review_alert): ?>
    <div style="font-size:13px; color:#16a34a; margin-bottom:8px;">
      <?= htmlspecialchars($review_alert); ?>
    </div>
  <?php endif; ?>

  <div class="review-summary">
    <span class="rating-big">
      <?= $avg_rating > 0 ? number_format($avg_rating, 1, ',', '.') : '–' ?>
    </span>
    <span class="rating-label">
      <?php if ($total_reviews > 0): ?>
        Berdasarkan <?= $total_reviews ?> Ulasan Tamu.
      <?php else: ?>
        Belum ada ulasan. Jadilah tamu pertama yang memberikan review!
      <?php endif; ?>
    </span>
  </div>

  <?php if (!empty($reviews)): ?>
    <div class="review-cards">
      <?php foreach ($reviews as $review): ?>
        <div class="review-card">
          <div class="reviewer-name">
            <?= htmlspecialchars($review['nama_tamu']) ?>
          </div>
          <span class="review-date">
            <?php
              $ts = strtotime($review['created_at']);
              echo $ts ? date('j M Y', $ts) : '';
            ?>
          </span>
          <p class="review-text">
            "<?= nl2br(htmlspecialchars($review['komentar'])) ?>"
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p style="font-size:13px; color:#6b7280; margin-top:8px;">
      Belum ada review untuk akomodasi ini.
    </p>
  <?php endif; ?>

  <!-- FORM TULIS ULASAN -->
  <div style="margin-top:18px;">
    <h3 style="font-size:15px; font-weight:600; margin-bottom:6px;">Tulis Ulasan</h3>

    <?php if ($can_review): ?>
      <form action="kirim_review.php" method="POST" style="margin-top:8px;">
        <input type="hidden" name="hotel_id" value="<?= $id; ?>">
        <!-- supaya bisa redirect balik ke hotel yang sama -->
        <input type="hidden" name="redirect" value="detail.php?id=<?= $id; ?>">

        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:8px;">
          <div style="flex:1 1 150px; min-width:140px;">
            <label for="nama_tamu" style="font-size:12px; font-weight:500;">Nama di Ulasan</label>
            <input
              type="text"
              id="nama_tamu"
              name="nama_tamu"
              value="<?= htmlspecialchars($logged_name); ?>"
              placeholder="Nama yang ingin ditampilkan"
              style="width:100%; padding:8px 10px; border-radius:10px; border:1px solid #e5e7eb; font-size:13px;"
            >
          </div>

          <div style="width:140px;">
            <label for="rating" style="font-size:12px; font-weight:500;">Rating</label>
            <select
              id="rating"
              name="rating"
              required
              style="width:100%; padding:8px 10px; border-radius:10px; border:1px solid #e5e7eb; font-size:13px;"
            >
              <option value="">Pilih...</option>
              <option value="5">5 - Sangat Puas</option>
              <option value="4">4 - Puas</option>
              <option value="3">3 - Cukup</option>
              <option value="2">2 - Kurang</option>
              <option value="1">1 - Sangat Mengecewakan</option>
            </select>
          </div>
        </div>

        <div style="margin-bottom:8px;">
          <label for="komentar" style="font-size:12px; font-weight:500;">Ulasan</label>
          <textarea
            id="komentar"
            name="komentar"
            rows="3"
            required
            placeholder="Ceritakan pengalamanmu menginap di akomodasi ini..."
            style="width:100%; padding:8px 10px; border-radius:10px; border:1px solid #e5e7eb; font-size:13px; resize:vertical;"
          ></textarea>
        </div>

        <button
          type="submit"
          style="margin-top:4px; padding:8px 16px; border-radius:999px; border:none; background:#22c55e; color:white; font-size:13px; font-weight:600; cursor:pointer;">
          Kirim Ulasan
        </button>
      </form>
    <?php else: ?>
      <p style="font-size:13px; color:#6b7280; margin-top:4px;">
        <?= htmlspecialchars($review_info_msg); ?>
      </p>
    <?php endif; ?>
  </div>

  <!-- FACILITIES -->
  <h2 class="section-title">Fasilitas Populer</h2>
  <div class="facility-list">
    <div class="facility-item">🏊‍♂️ Kolam Renang</div>
    <div class="facility-item">📶 Wi-Fi Cepat</div>
    <div class="facility-item">🅿️ Parkir 24 Jam</div>
    <div class="facility-item">🏋️ Pusat Kebugaran</div>
    <div class="facility-item">🍽️ Restoran</div>
    <div class="facility-item">❄️ AC</div>
    <div class="facility-item">🚕 Antar Jemput Bandara</div>
  </div>

  <!-- LOCATION -->
  <h2 class="section-title">Di Sekitar Hotel</h2>
  <div class="location-list" style="max-width: 600px;">
    <div class="location-item">
      <div class="loc-name">📍 Stasiun MRT Terdekat</div>
      <div class="loc-dist">300 m</div>
    </div>
    <div class="location-item">
      <div class="loc-name">📍 Central Business District</div>
      <div class="loc-dist">2.2 km</div>
    </div>
    <div class="location-item">
      <div class="loc-name">📍 Stadion Gelora Bung Karno</div>
      <div class="loc-dist">2.7 km</div>
    </div>
    <div class="location-item">
      <div class="loc-name">📍 Pusat Perbelanjaan</div>
      <div class="loc-dist">1.8 km</div>
    </div>
  </div>
</main>
<script>
  function handleBookingClick(e) {
    e.preventDefault();

    const link      = e.currentTarget;
    const hotelName = link.dataset.hotel || '';

    // --- 1. URL WhatsApp (GANTI nomor dengan nomor kamu) ---
    const noWa  = '+6281340975967'; // contoh, ganti ke nomor WA LaskarTrip
    const pesan = `Halo, saya ingin pesan kamar di ${hotelName} via LaskarTrip. Apakah masih tersedia?`;

    const waUrl = `https://wa.me/${noWa}?text=${encodeURIComponent(pesan)}`;

    // Buka WhatsApp di tab baru
    window.open(waUrl, '_blank');

    // --- 2. Arahkan ke halaman pembayaran (link href tombol) ---
    const targetUrl = link.getAttribute('href');
    window.location.href = targetUrl;
  }

  // Script tema yang sudah ada
  (function () {
    var savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
      document.body.classList.add('dark-mode');
    }
  })();
</script>
</body>
</html>
