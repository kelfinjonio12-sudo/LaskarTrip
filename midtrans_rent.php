<?php
// midtrans_rent.php — pembayaran sewa mobil (TERPISAH dari hotel)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

require 'koneksi.php';                          // mysqli $conn
require_once __DIR__ . '/vendor/autoload.php';  // Midtrans PHP SDK

// ===============
// 1. VALIDASI DASAR
// ===============
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$rent_id = (int) ($_POST['rent_id'] ?? 0);

if ($rent_id <= 0) {
    die('ID sewa tidak valid.');
}

// ===============
// 2. AMBIL DATA SEWA MOBIL
// ===============
// SESUAIKAN nama tabel & kolom dengan yang kamu punya
$sql = "
    SELECT r.*, c.nama_mobil, c.brand, c.tipe
    FROM rent_orders r
    JOIN cars c ON r.car_id = c.car_id
    WHERE r.rent_id = ? AND r.user_id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $rent_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rent   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$rent) {
    die('Data sewa tidak ditemukan atau bukan milik akun ini.');
}

// Mapping kolom – GANTI kalau nama kolom di DB beda
$car_id          = (int)($rent['car_id'] ?? 0);
$car_name        = $rent['nama_mobil'] ?? 'Sewa Mobil LaskarTrip';
$brand           = $rent['brand'] ?? '';
$tipe            = $rent['tipe'] ?? '';
$pickup_location = $rent['lokasi_jemput'] ?? $rent['pickup_location'] ?? '';
$destination     = $rent['lokasi_tujuan'] ?? $rent['destination_location'] ?? '';
$start_datetime  = $rent['start_datetime'] ?? '';
$end_datetime    = $rent['end_datetime'] ?? '';
$total_harga     = (int)($rent['total_harga'] ?? $rent['total_price'] ?? 0);

$checkin_date  = substr($start_datetime, 0, 10);
$checkout_date = substr($end_datetime,   0, 10);

// Data customer dari session
$customer_name  = $_SESSION['username'] ?? 'Pelanggan LaskarTrip';
$customer_email = $_SESSION['email']    ?? 'guest@example.com';
$customer_phone = $_SESSION['phone']    ?? '';

// ===============
// 3. KONFIGURASI MIDTRANS
// ===============
// 👉 COPY PERSIS dari midtrans.php (hotel)
\Midtrans\Config::$serverKey    = 'Mid-server-j4vm7a3h3uiMl4Ly0MDWUXX8';
\Midtrans\Config::$isProduction = false; // atau true kalau hotelmu sudah live
\Midtrans\Config::$isSanitized  = true;
\Midtrans\Config::$is3ds        = true;

// order_id unik khusus sewa mobil
$mt_order_id = 'RENT-' . $rent_id . '-' . time();

// Data transaksi ke Midtrans
$params = [
    'transaction_details' => [
        'order_id'     => $mt_order_id,
        'gross_amount' => $total_harga,
    ],
    'item_details' => [[
        'id'       => 'RENT-' . $car_id,
        'price'    => $total_harga,
        'quantity' => 1,
        'name'     => substr($car_name, 0, 40),
        'category' => 'Car Rental',
    ]],
    'customer_details' => [
        'first_name' => $customer_name,
        'email'      => $customer_email,
        'phone'      => $customer_phone,
    ],
];

// (opsional) simpan reference order midtrans ke tabel rent_orders
/*
$upd = mysqli_prepare($conn,
    "UPDATE rent_orders SET payment_reference = ? WHERE rent_id = ? AND user_id = ?"
);
if ($upd) {
    mysqli_stmt_bind_param($upd, "sii", $mt_order_id, $rent_id, $user_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
}
*/

// ===============
// 4. MINTA SNAP TOKEN
// ===============
try {
    $snapToken = \Midtrans\Snap::getSnapToken($params);
} catch (Exception $e) {
    die('Gagal membuat Snap token: ' . htmlspecialchars($e->getMessage()));
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Pembayaran Sewa Mobil — LaskarTrip</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px 16px;
      background:#f3f4f6;
      color:#111827;
    }
    .box{
      background:#fff;
      border-radius:18px;
      border:1px solid #e5e7eb;
      padding:24px 24px 18px;
      max-width:480px;
      width:100%;
      box-shadow:0 24px 60px rgba(15,23,42,.14);
    }
    h1{
      font-size:20px;
      margin-bottom:4px;
    }
    .subtitle{
      font-size:13px;
      color:#6b7280;
      margin-bottom:18px;
    }
    .chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      font-size:11px;
      padding:5px 10px;
      border-radius:999px;
      background:#eff6ff;
      color:#1d4ed8;
      margin-bottom:10px;
      font-weight:500;
    }
    .summary{
      border-radius:14px;
      border:1px solid #e5e7eb;
      padding:12px 14px;
      background:#f9fafb;
      margin-bottom:12px;
      font-size:13px;
    }
    .row{
      display:flex;
      justify-content:space-between;
      gap:8px;
      margin-bottom:4px;
    }
    .row span:first-child{
      color:#6b7280;
    }
    .row span:last-child{
      font-weight:500;
      text-align:right;
    }
    .price{
      font-size:15px;
      font-weight:600;
      color:#111827;
    }
    .btn{
      margin-top:14px;
      width:100%;
      border:none;
      border-radius:999px;
      padding:11px 16px;
      font-size:15px;
      font-weight:600;
      background:#22c55e;
      color:#fff;
      cursor:pointer;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:6px;
      box-shadow:0 12px 32px rgba(22,163,74,.45);
      transition:background .15s,box-shadow .15s,transform .15s;
    }
    .btn:hover{
      background:#16a34a;
      transform:translateY(-1px);
      box-shadow:0 18px 40px rgba(22,163,74,.5);
    }
    .btn:active{
      transform:translateY(0);
      box-shadow:0 8px 24px rgba(22,163,74,.4);
    }
    .note{
      margin-top:10px;
      font-size:11px;
      color:#9ca3af;
      text-align:center;
    }

    /* dark mode optional - ikut beranda */
    body.dark-mode{
      background:#020617;
      color:#e5e7eb;
    }
    body.dark-mode .box{
      background:#020617;
      border-color:#1f2937;
      box-shadow:0 24px 60px rgba(15,23,42,.9);
    }
    body.dark-mode .subtitle{color:#9ca3af;}
    body.dark-mode .summary{
      background:#020617;
      border-color:#1f2937;
    }
    body.dark-mode .row span:first-child{color:#9ca3af;}

    /* =======================
       DARK MODE (ikut beranda)
       ======================= */

    body.dark-mode {
        /* override variable utama */
        --bg: #020617;
        --card-bg: #020617;
        --text-main: #e5e7eb;
        --text-muted: #9ca3af;
        --shadow-soft: 0 24px 60px rgba(15, 23, 42, 0.9);

        background: radial-gradient(circle at top left, #020617, #020617 45%, #020617);
        color: var(--text-main);
    }

    body.dark-mode .tracking-wrapper {
        background: linear-gradient(135deg, #020617, #020617);
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(15, 23, 42, 0.85);
    }

    body.dark-mode .tracking-title {
        color: var(--text-main);
    }

    body.dark-mode .tracking-subtitle,
    body.dark-mode .info-label,
    body.dark-mode .footer-note {
        color: var(--text-muted);
    }

    body.dark-mode .card {
        background: var(--card-bg);
        border-color: rgba(31, 41, 55, 0.8);
    }

    body.dark-mode .back-chip {
        background: rgba(15, 23, 42, 0.9);
        border-color: rgba(75, 85, 99, 0.9);
        color: var(--text-muted);
    }

    body.dark-mode .warning {
        background: rgba(250, 204, 21, 0.12);
        color: #facc15;
        border-color: #facc15;
    }

    body.dark-mode .powered-tag {
        background: rgba(15,23,42,0.6);
    }
  </style>
</head>
<body>
  <div class="box">
    <div class="chip">
      🚗 Sewa Mobil LaskarTrip
    </div>
    <h1>Pembayaran Sewa Mobil</h1>
    <p class="subtitle">
      Sedang memproses pembayaran untuk sewa mobil
      <strong><?= htmlspecialchars($car_name); ?></strong>. Klik tombol di bawah untuk melanjutkan ke Midtrans.
    </p>

    <div class="summary">
      <div class="row">
        <span>Mobil</span>
        <span><?= htmlspecialchars($car_name); ?></span>
      </div>
      <div class="row">
        <span>Lokasi Jemput</span>
        <span><?= htmlspecialchars($pickup_location); ?></span>
      </div>
      <div class="row">
        <span>Lokasi Tujuan</span>
        <span><?= htmlspecialchars($destination); ?></span>
      </div>
      <div class="row">
        <span>Periode</span>
        <span><?= htmlspecialchars($start_datetime); ?> s/d <?= htmlspecialchars($end_datetime); ?></span>
      </div>
      <div class="row">
        <span>Total</span>
        <span class="price">Rp <?= number_format($total_harga, 0, ',', '.'); ?></span>
      </div>
    </div>

    <button id="payBtn" class="btn">Bayar Sekarang</button>

    <form id="finishForm" method="post" action="snap_finish.php">
    <input type="hidden" name="result_data" id="result_data">

    <!-- khusus hotel (tidak dipakai di sewa mobil, jadi 0) -->
    <input type="hidden" name="booking_id" value="0">
    <input type="hidden" name="hotel_id" value="0">

    <!-- hotel_name dipakai juga sebagai nama mobil di halaman sukses -->
    <input type="hidden" name="hotel_name" value="<?= htmlspecialchars($car_name); ?>">

    <!-- khusus sewa mobil -->
    <input type="hidden" name="rent_id" value="<?= (int)$rent_id; ?>">
    <input type="hidden" name="total_harga" value="<?= (int)$total_harga; ?>">

    <!-- opsional penanda jenis transaksi -->
    <input type="hidden" name="jenis_transaksi" value="sewa_mobil">
    </form>

    <p class="note">
      Setelah pembayaran berhasil, status sewa mobil akan diperbarui di halaman "Booking Sewa Mobil".
    </p>
  </div>

  <!-- SNAP JS: pakai client-key yang SAMA dengan midtrans.php hotel -->
  <script src="https://app.sandbox.midtrans.com/snap/snap.js"
          data-client-key="Mid-client-SzC2hJV6frpYkrZk"></script>

  <script>
    const snapToken  = <?= json_encode($snapToken) ?>;
    const payBtn     = document.getElementById('payBtn');
    const finishForm = document.getElementById('finishForm');
    const resultData = document.getElementById('result_data');

    payBtn.addEventListener('click', function () {
      window.snap.pay(snapToken, {
        onSuccess: function (result) {
          resultData.value = JSON.stringify(result);
          finishForm.submit();
        },
        onPending: function (result) {
          resultData.value = JSON.stringify(result);
          finishForm.submit();
        },
        onError: function (result) {
          resultData.value = JSON.stringify(result);
          finishForm.submit();
        },
        onClose: function () {
          alert('Popup pembayaran ditutup sebelum selesai.');
        }
      });
    });
  </script>

  <script>
    // sinkron mode gelap dari localStorage (optional)
    (function () {
      var theme = localStorage.getItem('laskartrip-theme');
      if (theme === 'dark') {
        document.body.classList.add('dark-mode');
      }
    })();
  </script>
<script>
// Sinkron tema dengan beranda
(function () {
    var theme = null;

    // coba baca beberapa kemungkinan key yang dipakai di beranda
    var keys = ['laskartrip-theme', 'lt-theme', 'theme', 'color-theme'];
    for (var i = 0; i < keys.length; i++) {
        try {
            var v = localStorage.getItem(keys[i]);
            if (v) {
                theme = v;
                break;
            }
        } catch (e) {}
    }

    if (!theme) return;

    var t = String(theme).toLowerCase();

    // kalau value-nya mengandung kata "dark" / bernilai 1/true → anggap mode gelap
    var isDark = t.includes('dark') || t === '1' || t === 'true';

    if (isDark) {
        document.body.classList.add('dark-mode');
    } else {
        document.body.classList.remove('dark-mode');
    }
})();
</script>
</body>
</html>
