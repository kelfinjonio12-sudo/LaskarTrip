<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// --- data dasar dari form ---
$result_json = $_POST['result_data'] ?? '{}';

$booking_id  = (int)($_POST['booking_id'] ?? 0); // hotel
$hotel_id    = (int)($_POST['hotel_id'] ?? 0);
$hotel_name  = $_POST['hotel_name'] ?? 'LaskarTrip';

$total_harga = (int)($_POST['total_harga'] ?? 0);

// sewa mobil
$rent_id     = (int)($_POST['rent_id'] ?? 0);

// opsional (kalau kamu kirim)
$jenis_transaksi = $_POST['jenis_transaksi'] ?? 'hotel';

// --- decode json dari Midtrans ---
$data = json_decode($result_json, true);
if (!is_array($data)) {
    $data = [];
}

// ambil info penting dari Midtrans
$transaction_status = $data['transaction_status'] ?? 'unknown';
$payment_type       = $data['payment_type'] ?? 'N/A';
$transaction_time   = $data['transaction_time'] ?? null;
$gross_amount       = isset($data['gross_amount']) ? (int)$data['gross_amount'] : $total_harga;
$order_id_snap      = $data['order_id'] ?? '';
$va_numbers         = $data['va_numbers'][0]['va_number'] ?? null;
$bank               = $data['va_numbers'][0]['bank'] ?? ($data['bank'] ?? null);

// mapping status Midtrans -> status aplikasi
$app_status = 'pending';
if (in_array($transaction_status, ['capture', 'settlement'], true)) {
    $app_status = 'success';
} elseif ($transaction_status === 'pending') {
    $app_status = 'pending';
} else {
    $app_status = 'failed';
}

// ================================
// 1. UPDATE DATABASE
// ================================
if ($rent_id > 0) {
    // === SEWA MOBIL ===
// SESUAIKAN nama kolom dengan struktur rent_orders kamu
$payment_status = $app_status === 'success' ? 'paid' : $app_status;

// kalau di rent_orders ADA kolom rent_status, pakai ini:
$rent_status = $app_status === 'success' ? 'confirmed' : 'pending';

$sql = "UPDATE rent_orders
        SET payment_status = ?, 
            rent_status    = ?, 
            total_harga    = ?
        WHERE rent_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param(
        $stmt,
        "ssii",
        $payment_status,
        $rent_status,
        $gross_amount,
        $rent_id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

    $headline_type = 'rent';
} else {
    // === HOTEL ===
    // Sesuaikan nama kolom dengan tabel bookings punyamu
    // diasumsikan ada kolom: status, payment_method, payment_reference, total_price
    $booking_status = $app_status === 'success' ? 'success' : $app_status;

    $sql = "UPDATE bookings
            SET status = ?, 
                payment_method = ?, 
                payment_reference = ?, 
                total_price = ?
            WHERE booking_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param(
            $stmt,
            "sssii",
            $booking_status,
            $payment_type,
            $order_id_snap,
            $gross_amount,
            $booking_id
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $headline_type = 'hotel';
}

// ================================
// 2. SIAPKAN TEKS UNTUK HALAMAN SUKSES
// ================================
if ($headline_type === 'rent') {
    $title = 'Status Pembayaran Sewa Mobil';
    if ($app_status === 'success') {
        $subtitle = 'Pembayaran sewa mobil kamu berhasil diproses. Silakan cek halaman "Booking Sewa Mobil" untuk melihat detail perjalanan.';
    } elseif ($app_status === 'pending') {
        $subtitle = 'Pembayaran sedang diproses / menunggu. Ikuti instruksi pembayaran dari Midtrans.';
    } else {
        $subtitle = 'Terjadi kendala pada pembayaran. Kamu bisa mencoba lagi atau menggunakan metode pembayaran lain.';
    }
    $label_nama = 'Nama Mobil';
} else {
    $title = 'Status Pembayaran Booking Hotel';
    if ($app_status === 'success') {
        $subtitle = 'Pembayaran booking hotel kamu berhasil diproses. Silakan cek halaman "Booking Saya" untuk melihat detail.';
    } elseif ($app_status === 'pending') {
        $subtitle = 'Pembayaran sedang diproses / menunggu. Ikuti instruksi pembayaran dari Midtrans.';
    } else {
        $subtitle = 'Terjadi kendala pada pembayaran. Kamu bisa mencoba lagi atau menggunakan metode pembayaran lain.';
    }
    $label_nama = 'Nama Hotel';
}

// warna badge
$badge_text = 'Menunggu Pembayaran';
$badge_class = 'badge-pending';
if ($app_status === 'success') {
    $badge_text  = 'Pembayaran Berhasil';
    $badge_class = 'badge-success';
} elseif ($app_status === 'failed') {
    $badge_text  = 'Pembayaran Gagal';
    $badge_class = 'badge-failed';
}

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Status Pembayaran - LaskarTrip</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px 16px;
      background:#f3f4f6;
      color:#111827;
    }
    .wrapper{max-width:480px;width:100%;}
    .card{
      background:#fff;
      border-radius:18px;
      border:1px solid #e5e7eb;
      padding:22px 22px 18px;
      box-shadow:0 24px 60px rgba(15,23,42,.12);
    }
    h1{font-size:20px;margin-bottom:4px;}
    .subtitle{font-size:13px;color:#6b7280;margin-bottom:14px;line-height:1.4;}
    .badge{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 10px;
      border-radius:999px;
      font-size:11px;
      font-weight:600;
      margin-bottom:10px;
    }
    .badge-success{background:#dcfce7;color:#16a34a;}
    .badge-pending{background:#fef9c3;color:#ca8a04;}
    .badge-failed{background:#fee2e2;color:#b91c1c;}
    .summary{
      border-radius:14px;
      border:1px solid #e5e7eb;
      padding:12px 14px;
      background:#f9fafb;
      font-size:13px;
      margin-bottom:14px;
    }
    .row{display:flex;justify-content:space-between;gap:8px;margin-bottom:4px;}
    .row span:first-child{color:#6b7280;}
    .row span:last-child{font-weight:500;text-align:right;}
    .price{font-size:15px;font-weight:600;color:#111827;}
    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:6px;
      margin-top:6px;
      padding:10px 16px;
      border-radius:999px;
      border:none;
      background:#0ea5e9;
      color:#fff;
      font-size:14px;
      font-weight:600;
      cursor:pointer;
      text-decoration:none;
      box-shadow:0 12px 32px rgba(14,165,233,.4);
      transition:background .15s,box-shadow .15s,transform .15s;
    }
    .btn:hover{
      background:#0284c7;
      transform:translateY(-1px);
      box-shadow:0 16px 40px rgba(14,165,233,.5);
    }
    .note{margin-top:8px;font-size:11px;color:#9ca3af;}
    /* dark mode */
    body.dark-mode{background:#020617;color:#e5e7eb;}
    body.dark-mode .card{
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
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="card">
      <div class="badge <?= $badge_class; ?>">
        <?= htmlspecialchars($badge_text); ?>
      </div>
      <h1><?= htmlspecialchars($title); ?></h1>
      <p class="subtitle"><?= htmlspecialchars($subtitle); ?></p>

      <div class="summary">
        <div class="row">
          <span><?= htmlspecialchars($label_nama); ?></span>
          <span><?= htmlspecialchars($hotel_name); ?></span>
        </div>
        <div class="row">
          <span>Metode</span>
          <span><?= htmlspecialchars($payment_type); ?></span>
        </div>
        <div class="row">
          <span>Total</span>
          <span class="price">Rp <?= number_format($gross_amount,0,',','.'); ?></span>
        </div>
        <?php if ($va_numbers): ?>
        <div class="row">
          <span>VA <?= htmlspecialchars(strtoupper($bank)); ?></span>
          <span><?= htmlspecialchars($va_numbers); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($transaction_time): ?>
        <div class="row">
          <span>Waktu Transaksi</span>
          <span><?= htmlspecialchars($transaction_time); ?></span>
        </div>
        <?php endif; ?>
      </div>

      <a href="profile.php" class="btn">Lihat Booking Saya</a>
      <p class="note">Kamu bisa mengecek status terbaru transaksi ini di halaman profil / booking.</p>
    </div>
  </div>

  <script>
    (function () {
      var savedTheme = localStorage.getItem('laskartrip-theme') || localStorage.getItem('theme');
      if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
      }
    })();
  </script>
</body>
</html>
