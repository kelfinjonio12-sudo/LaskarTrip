<?php
session_start();          // TAMBAHAN
require 'koneksi.php';

// ambil id user yang login
$user_id = $_SESSION['user_id'] ?? 0;
// Ambil ID hotel dari URL
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Ambil data hotel
$query = mysqli_query($conn, "SELECT * FROM hotels WHERE id = '$id'");
$hotel = mysqli_fetch_assoc($query);

if (!$hotel) {
    header("Location: index.php");
    exit;
}

$nama_hotel      = $hotel['nama_hotel'];
$lokasi          = $hotel['lokasi'] ?? 'Indonesia';
$harga_per_malam = (int) $hotel['harga_per_malam'];
$harga_format    = number_format($harga_per_malam, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pembayaran - <?= htmlspecialchars($nama_hotel); ?> — LaskarTrip</title>

  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />

  <style>
    :root {
      --primary: #22c55e;
      --primary-dark: #16a34a;
      --text-main: #111827;
      --text-muted: #6b7280;
      --bg: #f9fafb;
      --border: #e5e7eb;
      --radius: 12px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: "Inter", sans-serif;
      color: var(--text-main);
      background: var(--bg);
      line-height: 1.5;
    }

    .container {
      max-width: 1120px;
      margin: 0 auto;
      padding: 0 20px 40px;
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
      margin-bottom: 24px;
    }
    .brand {
      font-weight: 700;
      font-size: 20px;
      color: #2563eb;
      text-decoration: none;
    }
    .brand span { color: var(--primary); }

    .navbar-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
    }

    .back-link {
      font-size: 14px;
      color: var(--text-muted);
      text-decoration: none;
    }
    .back-link:hover { color: #111827; }

    /* LAYOUT */
    .layout {
      display: grid;
      grid-template-columns: 2fr 1.4fr;
      gap: 24px;
      margin-top: 16px;
    }

    @media (max-width: 900px) {
      .layout {
        grid-template-columns: 1fr;
      }
    }

    .card {
      background: #fff;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      padding: 20px 24px;
      box-shadow: 0 10px 30px rgba(15,23,42,0.03);
    }

    .card-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .hotel-summary-name {
      font-size: 16px;
      font-weight: 600;
    }

    .hotel-summary-location {
      font-size: 13px;
      color: var(--text-muted);
      margin-top: 4px;
    }

    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 12px;
    }

    .price-per-night {
      font-size: 13px;
      color: var(--text-muted);
    }

    .price-main {
      font-size: 20px;
      font-weight: 700;
      color: #dc2626;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    @media (max-width: 700px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 12px;
    }

    .form-group label {
      font-size: 13px;
      font-weight: 500;
    }

    .form-group input,
    .form-group select {
      border-radius: 10px;
      border: 1px solid var(--border);
      padding: 9px 10px;
      font-size: 14px;
      outline: none;
    }

    .form-group input:focus,
    .form-group select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 1px rgba(34,197,94,0.2);
    }

    .note {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 4px;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 12px;
      padding-top: 12px;
      border-top: 1px dashed var(--border);
    }

    .total-label {
      font-size: 14px;
      font-weight: 500;
    }

    .total-amount {
      font-size: 18px;
      font-weight: 700;
      color: #b91c1c;
    }

    .btn-pay {
      margin-top: 18px;
      width: 100%;
      border: none;
      border-radius: 999px;
      background: var(--primary);
      color: #fff;
      padding: 11px 16px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
    }

    .btn-pay:hover {
      background: var(--primary-dark);
    }

    /* ============= DARK MODE ============= */
    body.dark-mode {
      background: #020617;
      color: #e5e7eb;
    }

    body.dark-mode .navbar {
      background: #020617;
      border-color: #1f2937;
    }

    body.dark-mode .brand {
      color: #60a5fa;
    }
    body.dark-mode .brand span {
      color: #4ade80;
    }

    body.dark-mode .container {
      color: #e5e7eb;
    }

    body.dark-mode .card {
      background: #0f172a;
      border-color: #1f2937;
      box-shadow: 0 16px 40px rgba(15,23,42,0.6);
    }

    body.dark-mode .section-subtext,
    body.dark-mode .note,
    body.dark-mode .price-per-night {
      color: #9ca3af;
    }

    body.dark-mode input,
    body.dark-mode select {
      background: #020617;
      border-color: #334155;
      color: #e5e7eb;
    }

    body.dark-mode input::placeholder {
      color: #6b7280;
    }

    body.dark-mode .price-main,
    body.dark-mode .total-amount {
      color: #f97373;
    }

    body.dark-mode .total-row {
      border-top-color: #1f2937;
    }
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="container navbar-inner">
      <a href="index.php" class="brand">Laskar<span>Trip</span></a>
      <a href="detail.php?id=<?= $id; ?>" class="back-link">Kembali ke Detail</a>
    </div>
  </nav>

  <main class="container">
    <h1 style="font-size:22px; font-weight:700; margin-bottom:10px;">
      Pembayaran
    </h1>
    <p style="font-size:13px; color:var(--text-muted); margin-bottom:18px;">
      Silakan cek kembali data pemesanan sebelum melanjutkan ke pembayaran.
    </p>

    <div class="layout">
      <!-- KIRI: FORM DATA TAMU -->
      <div class="card">
        <div class="card-title">Data Pemesan</div>

        <!-- Form menuju midtrans.php -->
        <form action="midtrans.php" method="POST" id="formPembayaran">
        <!-- data hotel (hidden) -->
        <input type="hidden" name="hotel_id" value="<?= $id; ?>">
        <input type="hidden" name="hotel_name" value="<?= htmlspecialchars($nama_hotel); ?>">
        <input type="hidden" name="harga_per_malam" id="hargaPerMalam" value="<?= $harga_per_malam; ?>">
        <input type="hidden" name="total_harga" id="totalHargaInput" value="<?= $harga_per_malam; ?>">

        <!-- TAMBAHAN: id user -->
        <input type="hidden" name="user_id" value="<?= (int)$user_id; ?>">

          <div class="form-grid">
            <div class="form-group">
              <label for="nama">Nama Lengkap</label>
              <input type="text" id="nama" name="customer_name" required placeholder="Nama sesuai KTP / identitas">
            </div>

            <div class="form-group">
              <label for="whatsapp">Nomor WhatsApp</label>
              <input type="text" id="whatsapp" name="customer_phone" required placeholder="Contoh: 6281234567890">
              <div class="note">Pastikan sama dengan nomor yang digunakan untuk chat pemesanan.</div>
            </div>

            <div class="form-group">
              <label for="email">Email (opsional)</label>
              <input type="email" id="email" name="customer_email" placeholder="Untuk kirim bukti pembayaran">
            </div>

            <div class="form-group">
              <label for="malam">Jumlah Malam</label>
              <select id="malam" name="malam" required>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                  <option value="<?= $i; ?>"><?= $i; ?> malam</option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label for="checkin">Check-in</label>
              <input type="date" id="checkin" name="checkin_date" required>
            </div>

            <div class="form-group">
              <label for="checkout">Check-out</label>
              <input type="date" id="checkout" name="checkout_date" required>
            </div>
          </div>

          <div class="note">
            Pembayaran diproses melalui Midtrans. Detail kamar dan kebijakan pembatalan mengikuti kebijakan LaskarTrip.
          </div>

          <div class="total-row">
            <div>
              <div class="total-label">Total yang harus dibayar</div>
              <div class="note" id="totalDetailText">
                Rp<?= $harga_format ?> x 1 malam
              </div>
            </div>
            <div class="total-amount" id="totalHargaText">
              Rp<?= $harga_format ?>
            </div>
          </div>

          <button type="submit" class="btn-pay">
            Lanjut ke Pembayaran
          </button>
        </form>
      </div>

      <!-- KANAN: RINGKASAN HOTEL -->
      <div class="card">
        <div class="card-title">Ringkasan Pesanan</div>

        <div style="margin-bottom:14px;">
          <div class="hotel-summary-name">
            <?= htmlspecialchars($nama_hotel); ?>
          </div>
          <div class="hotel-summary-location">
            📍 <?= htmlspecialchars($lokasi); ?>
          </div>
        </div>

        <div class="price-row">
          <div class="price-per-night">Harga per malam</div>
          <div class="price-main">Rp<?= $harga_format; ?></div>
        </div>

        <p class="note" style="margin-top:10px;">
          Harga belum termasuk pajak & biaya layanan. Detail akhir akan muncul di halaman pembayaran Midtrans.
        </p>
      </div>
    </div>
  </main>

  <script>
    // Hitung total harga dinamis berdasarkan jumlah malam
    const selectMalam      = document.getElementById('malam');
    const hargaPerMalamEl  = document.getElementById('hargaPerMalam');
    const totalText        = document.getElementById('totalHargaText');
    const totalDetailText  = document.getElementById('totalDetailText');
    const totalInput       = document.getElementById('totalHargaInput');

    function formatRupiah(number) {
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
      }).format(number).replace('Rp', 'Rp');
    }

    function updateTotal() {
      const perMalam = parseInt(hargaPerMalamEl.value || '0', 10);
      const malam    = parseInt(selectMalam.value || '1', 10);

      const total = perMalam * malam;

      totalText.textContent       = formatRupiah(total);
      totalDetailText.textContent = formatRupiah(perMalam) + ' x ' + malam + ' malam';
      totalInput.value            = total;
    }

    selectMalam.addEventListener('change', updateTotal);
    updateTotal(); // inisialisasi awal
  </script>
<script>
  (function () {
    var savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
      document.body.classList.add('dark-mode');
    }
  })();
</script>
</body>
</html>
