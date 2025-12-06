<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    $currentUrl = $_SERVER['REQUEST_URI'];   // contoh: /laskar-trip/hotels.php
    $redirect   = 'login.php?redirect=' . urlencode($currentUrl);
    header("Location: $redirect");
    exit;
}

// Ambil keyword pencarian
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$checkin = isset($_GET['checkin']) ? $_GET['checkin'] : '';
$checkout= isset($_GET['checkout']) ? $_GET['checkout'] : '';

// Logika Query
if ($keyword !== '') {
    // Cari berdasarkan nama hotel ATAU lokasi
    // Gunakan prepared statement jika memungkinkan, tapi untuk simpel pakai escape string dulu
    $safe_keyword = mysqli_real_escape_string($conn, $keyword);
    $query = "SELECT * FROM hotels WHERE nama_hotel LIKE '%$safe_keyword%' OR lokasi LIKE '%$safe_keyword%'";
    $subtitle = "Menampilkan hasil pencarian untuk: <strong>" . htmlspecialchars($keyword) . "</strong>";
} else {
    // Jika tidak ada keyword, tampilkan semua
    $query = "SELECT * FROM hotels";
    $subtitle = "Menampilkan semua hotel yang tersedia";
}

$result = mysqli_query($conn, $query);
$count  = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Hasil Pencarian Hotel - Laskar Trip</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Fonts & Style -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css">
  
  <style>
    /* Styling khusus halaman hasil pencarian */
    .search-header {
      background: #fff;
      border-bottom: 1px solid #e5e7eb;
      padding: 20px 0;
      position: sticky;
      top: 0; /* Sticky di bawah navbar utama jika ada, atau top 0 */
      z-index: 40;
    }
    
    .mini-search-form {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .mini-input {
      flex: 1;
      padding: 10px 14px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 14px;
      min-width: 200px;
    }
    
    .btn-mini-search {
      background: var(--primary);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
    }
    
    .results-wrapper {
      padding: 30px 0;
      min-height: 60vh;
    }
    
    .results-count {
      margin-bottom: 20px;
      color: var(--text-muted);
      font-size: 14px;
    }

    /* Modifikasi Card agar cocok layout list */
    .hotel-list-card {
      display: flex;
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 20px;
      transition: box-shadow 0.2s;
    }
    
    .hotel-list-card:hover {
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      border-color: var(--primary);
    }
    
    .card-img-side {
      width: 280px;
      background-size: cover;
      background-position: center;
      background-color: #eee;
      position: relative;
    }
    
    .card-content-side {
      flex: 1;
      padding: 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    
    .card-top h3 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 6px;
      color: var(--text-main);
    }
    
    .card-loc {
      font-size: 13px;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .card-features {
      margin-top: 12px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .feat-badge {
      font-size: 11px;
      background: #f3f4f6;
      padding: 4px 8px;
      border-radius: 6px;
      color: #4b5563;
    }
    
    .card-bottom {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px dashed #e5e7eb;
    }
    
    .card-price label {
      font-size: 11px;
      color: var(--text-muted);
      display: block;
    }
    
    .card-price span {
      font-size: 20px;
      font-weight: 700;
      color: #dc2626;
    }
    
    .btn-view {
      background: var(--primary);
      color: white;
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      transition: background 0.2s;
    }
    .btn-view:hover {
      background: #1d4ed8;
    }

    /* Responsive mobile */
    @media (max-width: 768px) {
      .hotel-list-card {
        flex-direction: column;
      }
      .card-img-side {
        width: 100%;
        height: 180px;
      }
    }
    
    /* Dark Mode overrides */
    body.dark-mode .search-header { background: #1e293b; border-color: #334155; }
    body.dark-mode .hotel-list-card { background: #1e293b; border-color: #334155; }
    body.dark-mode .card-top h3 { color: #f9fafb; }
    body.dark-mode .card-loc { color: #94a3b8; }
    body.dark-mode .feat-badge { background: #334155; color: #cbd5e1; }
    body.dark-mode .mini-input { background: #0f172a; border-color: #334155; color: white; }
  </style>
</head>
<body>

  <!-- HEADER / NAVBAR -->
  <header class="header-wrapper">
    <div class="container navbar">
      <div class="brand-area">
        <a href="index.php" style="display:flex; align-items:center; gap:12px;">
            <div class="logo-icon">LT</div>
            <div class="logo-text-group">
            <span class="logo-main" style="color:white;">Laskar Trip</span>
            <span class="logo-sub">SEARCH</span>
            </div>
        </a>
      </div>
      <div class="nav-right">
        <?php if (isset($_SESSION['username'])): ?>
            <a href="profile.php" class="user-badge"><?= htmlspecialchars($_SESSION['username']) ?></a>
        <?php else: ?>
            <a href="login.php" class="btn-auth-login">Masuk</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- SEARCH BAR STICKY -->
  <div class="search-header">
    <div class="container">
      <form action="hotels.php" method="GET" class="mini-search-form">
        <input type="text" name="keyword" class="mini-input" 
               placeholder="Mau nginep di mana?" 
               value="<?= htmlspecialchars($keyword) ?>">
        
        <input type="date" name="checkin" class="mini-input" value="<?= $checkin ?>">
        <input type="date" name="checkout" class="mini-input" value="<?= $checkout ?>">
        
        <button type="submit" class="btn-mini-search">Cari Ulang</button>
      </form>
    </div>
  </div>

  <!-- HASIL PENCARIAN -->
  <main class="container results-wrapper">
    <div class="results-count">
      <?= $subtitle ?> • Ditemukan <strong><?= $count ?></strong> akomodasi.
    </div>

    <?php if ($count > 0): ?>
      <div class="list-container">
        <?php while($row = mysqli_fetch_assoc($result)): 
            $harga = number_format($row['harga_per_malam'], 0, ',', '.');
            // Gambar random/gradient sebagai placeholder
            $img_url = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=500&auto=format&fit=crop';
            if (strpos(strtolower($row['nama_hotel']), 'yogyakarta') !== false) {
                $img_url = 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?q=80&w=500&auto=format&fit=crop';
            }
        ?>
        
        <div class="hotel-list-card">
          <!-- Gambar Kiri -->
          <div class="card-img-side" style="background-image: url('<?= $img_url ?>');">
             <!-- Label Promo -->
             <div style="position:absolute; top:10px; left:10px; background:#ef4444; color:white; font-size:10px; padding:4px 8px; border-radius:4px; font-weight:700;">PROMO</div>
          </div>
          
          <!-- Konten Kanan -->
          <div class="card-content-side">
            <div class="card-top">
              <h3><?= htmlspecialchars($row['nama_hotel']) ?></h3>
              <div class="card-loc">
                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($row['lokasi']) ?>
              </div>
              <div style="margin-top:6px; font-size:12px; color:#f59e0b;">
                <?= $row['rating'] ?>/5.0 ⭐ (<?= $row['jumlah_review'] ?> Ulasan)
              </div>
              
              <div class="card-features">
                <span class="feat-badge">☕ Sarapan</span>
                <span class="feat-badge">📶 WiFi</span>
                <span class="feat-badge">🏊 Kolam Renang</span>
              </div>
            </div>
            
            <div class="card-bottom">
              <div class="card-price">
                <label>Harga per kamar/malam</label>
                <span>Rp <?= $harga ?></span>
              </div>
              <a href="detail.php?id=<?= $row['id'] ?>" class="btn-view">
                Lihat Detail
              </a>
            </div>
          </div>
        </div>
        
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <!-- TAMPILAN JIKA KOSONG -->
      <div style="text-align:center; padding: 60px 0; color:#6b7280;">
        <h3 style="margin-bottom:10px;">Yah, hotel tidak ditemukan 😔</h3>
        <p>Coba gunakan kata kunci lain seperti "Bali", "Jakarta", atau nama hotel spesifik.</p>
        <a href="index.php" style="color:var(--primary); text-decoration:underline; font-size:14px; margin-top:10px; display:inline-block;">Kembali ke Beranda</a>
      </div>
    <?php endif; ?>
  </main>

  <script>
    // Dark mode check
    if (localStorage.getItem('theme') === 'dark') {
      document.body.classList.add('dark-mode');
    }
  </script>
</body>
</html>