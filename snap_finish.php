<?php
session_start();
require 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$result_json   = $_POST['result_data'] ?? '{}';
$booking_id    = (int)($_POST['booking_id'] ?? 0);
$hotel_id      = (int)($_POST['hotel_id'] ?? 0);
$hotel_name    = $_POST['hotel_name'] ?? 'Hotel';
$total_harga   = (int)($_POST['total_harga'] ?? 0);

$data = json_decode($result_json, true);
if (!is_array($data)) {
    $data = [];
}

// data dari Midtrans
$transaction_status = $data['transaction_status'] ?? 'unknown';
$payment_type       = $data['payment_type'] ?? 'N/A';
$transaction_time   = $data['transaction_time'] ?? null;
$gross_amount       = isset($data['gross_amount']) ? (int)$data['gross_amount'] : $total_harga;
$order_id_snap      = $data['order_id'] ?? '';

// mapping ke status di tabel bookings
$app_status = 'pending';
if (in_array($transaction_status, ['capture', 'settlement'])) {
    $app_status = 'success';
} elseif ($transaction_status === 'pending') {
    $app_status = 'pending';
} else {
    $app_status = 'pending'; // atau 'failed' kalau enum-mu ada
}

// UPDATE bookings
if ($booking_id > 0) {
    $status = mysqli_real_escape_string($conn, $app_status);
    $ptype  = mysqli_real_escape_string($conn, $payment_type);
    $pref   = mysqli_real_escape_string($conn, $order_id_snap);

    $sql = "UPDATE bookings
            SET status = '$status',
                payment_method = '$ptype',
                payment_reference = '$pref'
            WHERE id = $booking_id";

    mysqli_query($conn, $sql); // kalau error, booking tetap ada dg status pending
}

// ==== teks tampilan (seperti versi sebelumnya) ====
$status_label = 'Status tidak diketahui';
$status_desc  = 'Kami belum dapat menentukan status transaksi Anda.';
$status_badge = '#6b7280';

if (in_array($transaction_status, ['capture','settlement'])) {
    $status_label = 'Pembayaran Berhasil';
    $status_desc  = 'Terima kasih, pembayaran Anda telah kami terima.';
    $status_badge = '#16a34a';
} elseif ($transaction_status === 'pending') {
    $status_label = 'Menunggu Pembayaran';
    $status_desc  = 'Transaksi sudah dibuat, tetapi pembayaran belum diterima.';
    $status_badge = '#d97706';
} elseif (in_array($transaction_status, ['deny','expire','cancel','failure'])) {
    $status_label = 'Pembayaran Gagal / Kadaluarsa';
    $status_desc  = 'Silakan lakukan pemesanan ulang jika masih ingin melanjutkan.';
    $status_badge = '#dc2626';
}

function formatRupiahInt($angka) {
    return 'Rp' . number_format((int)$angka, 0, ',', '.');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Status Pembayaran — LaskarTrip</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * { box-sizing:border-box;margin:0;padding:0; }
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:#f3f4f6;
      color:#111827;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px 16px;
    }
    .wrapper { max-width:480px;width:100%; }
    .card {
      background:#fff;
      border-radius:18px;
      border:1px solid #e5e7eb;
      padding:22px 22px 18px;
      box-shadow:0 16px 40px rgba(15,23,42,0.08);
    }
    .brand {
      display:flex;align-items:center;justify-content:center;
      margin-bottom:14px;font-weight:700;font-size:18px;color:#2563eb;
    }
    .brand span{color:#22c55e;}
    .status-icon{
      width:56px;height:56px;border-radius:999px;
      display:flex;align-items:center;justify-content:center;
      margin:0 auto 12px;background:#ecfdf5;color:#16a34a;font-size:28px;
    }
    .status-title{text-align:center;font-size:20px;font-weight:600;margin-bottom:4px;}
    .status-badge{
      display:inline-flex;align-items:center;justify-content:center;
      padding:4px 10px;border-radius:999px;font-size:11px;font-weight:500;
      color:#fff;margin:6px auto 0;
    }
    .status-desc{
      text-align:center;font-size:13px;color:#6b7280;
      margin-top:10px;margin-bottom:16px;
    }
    .status-body{
      background:#f9fafb;border-radius:14px;border:1px solid #e5e7eb;
      padding:10px 12px;font-size:13px;
    }
    .row{display:flex;justify-content:space-between;margin-bottom:6px;gap:10px;}
    .row span:first-child{color:#6b7280;}
    .row span:last-child{font-weight:500;text-align:right;}
    .status-footer{
      margin-top:18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;
    }
    .btn{
      display:inline-flex;align-items:center;justify-content:center;
      border-radius:999px;padding:8px 16px;font-size:13px;font-weight:500;
      text-decoration:none;cursor:pointer;border:1px solid transparent;
    }
    .btn-primary{background:#22c55e;color:#fff;border-color:#16a34a;}
    .btn-primary:hover{background:#16a34a;}
    .btn-secondary{background:#fff;color:#111827;border-color:#d1d5db;}
    .btn-secondary:hover{background:#f3f4f6;}
  
  /* ============= DARK MODE ============= */
    body.dark-mode{
      background:#020617;
      color:#e5e7eb;
    }

    body.dark-mode .card{
      background:#0f172a;
      border-color:#1f2937;
      box-shadow:0 16px 40px rgba(15,23,42,0.6);
    }

    body.dark-mode .brand{
      color:#60a5fa;
    }
    body.dark-mode .brand span{
      color:#4ade80;
    }

    body.dark-mode .status-icon{
      background:#022c22;
      color:#4ade80;
    }

    body.dark-mode .status-desc{
      color:#9ca3af;
    }

    body.dark-mode .status-body{
      background:#020617;
      border-color:#1f2937;
    }

    body.dark-mode .row span:first-child{
      color:#9ca3af;
    }

    body.dark-mode .btn-secondary{
      background:#020617;
      border-color:#334155;
      color:#e5e7eb;
    }
    body.dark-mode .btn-secondary:hover{
      background:#111827;
    }

    body.dark-mode .btn-primary{
      background:#22c55e;
    }
    body.dark-mode .btn-primary:hover{
      background:#16a34a;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="card">
      <div class="brand">Laskar<span>Trip</span></div>

      <div class="status-icon">
        <?php if (in_array($transaction_status, ['capture','settlement'])): ?>
          ✓
        <?php elseif ($transaction_status === 'pending'): ?>
          !
        <?php else: ?>
          ×
        <?php endif; ?>
      </div>

      <h1 class="status-title"><?= htmlspecialchars($status_label); ?></h1>
      <div class="status-badge" style="background: <?= htmlspecialchars($status_badge); ?>;">
        <?= htmlspecialchars(strtoupper($transaction_status)); ?>
      </div>
      <p class="status-desc"><?= htmlspecialchars($status_desc); ?></p>

      <div class="status-body">
        <div class="row"><span>Hotel</span><span><?= htmlspecialchars($hotel_name); ?></span></div>
        <div class="row"><span>Total</span><span><?= formatRupiahInt($gross_amount ?: $total_harga); ?></span></div>
        <?php if ($transaction_time): ?>
          <div class="row"><span>Waktu</span><span><?= htmlspecialchars($transaction_time); ?></span></div>
        <?php endif; ?>
      </div>

      <div class="status-footer">
        <?php if ($hotel_id): ?>
          <a href="detail.php?id=<?= (int)$hotel_id; ?>" class="btn btn-secondary">Kembali ke Detail Hotel</a>
        <?php endif; ?>
        <a href="profile.php" class="btn btn-primary">Lihat Booking Saya</a>
      </div>
    </div>
  </div>
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
