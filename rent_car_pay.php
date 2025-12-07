<?php
session_start();
require 'koneksi.php';

// GANTI ini kalau nama/config Midtrans kamu beda
require 'midtrans.php'; 

// ====== CEK LOGIN USER (SAMA DENGAN profile.php) ======
if (!isset($_SESSION['username'])) {
    // user belum login, paksa ke halaman login
    header('Location: login.php');
    exit;
}

// ambil user_id kalau ada, kalau belum ada set 0 saja dulu
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

// ====== AMBIL rent_id DARI URL ======
if (!isset($_GET['rent_id'])) {
    header('Location: profile.php?tab=rent');
    exit;
}
$rent_id = (int) $_GET['rent_id'];

// ====== AMBIL DATA RENT + USER + MOBIL ======
$sql = "
    SELECT r.*, 
           u.nama_lengkap, u.email,
           c.nama_mobil, c.brand
    FROM rent_orders r
    JOIN users u ON r.user_id = u.id
    JOIN cars  c ON r.car_id  = c.car_id
    WHERE r.rent_id = ? AND r.user_id = ?
    LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $rent_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rent   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$rent) {
    // booking tidak ditemukan / bukan milik user
    header('Location: profile.php?tab=rent');
    exit;
}

// Kalau sudah dibayar, jangan buat transaksi lagi
if (strtolower($rent['payment_status']) === 'paid') {
    header('Location: profile.php?tab=rent&pay=already_paid');
    exit;
}

// ====== HITUNG TOTAL ======
$total = (int) $rent['total_harga'];   // pastikan kolom ini sudah ada di rent_orders

// order_id unik khusus sewa mobil
$order_id = 'RENT-' . $rent_id;

// ====== PARAMETER MIDTRANS ======
$params = [
    'transaction_details' => [
        'order_id'     => $order_id,
        'gross_amount' => $total,
    ],
    'customer_details' => [
        'first_name'   => $rent['nama_lengkap'],
        'email'        => $rent['email'],
    ],
    'item_details' => [
        [
            'id'       => 'rent-car-' . $rent['car_id'],
            'price'    => $total,
            'quantity' => 1,
            'name'     => 'Sewa Mobil ' . $rent['nama_mobil'],
        ]
    ],
];

// Dapatkan Snap Token
$snapToken = \Midtrans\Snap::getSnapToken($params);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pembayaran Sewa Mobil | Laskar Trip</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- SESUAIKAN: sandbox / production -->
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" 
            data-client-key="<?= htmlspecialchars(\Midtrans\Config::$clientKey); ?>"></script>

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0f172a;
            color: #e5e7eb;
            margin: 0;
        }
        .shell {
            max-width: 480px;
            margin: 40px auto;
            padding: 0 16px;
        }
        .card {
            background: #020617;
            border-radius: 18px;
            padding: 18px 20px;
            border: 1px solid #1f2937;
            box-shadow: 0 20px 40px rgba(15,23,42,.7);
        }
        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }
        .subtitle {
            font-size: 13px;
            color: #9ca3af;
            margin-bottom: 14px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .label {
            color: #9ca3af;
        }
        .value {
            font-weight: 600;
        }
        .total {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px dashed #1f2937;
        }
        .total .value {
            font-size: 16px;
            color: #f9fafb;
        }
        .btn-pay, .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 8px 16px;
            font-size: 14px;
            text-decoration: none;
            margin-top: 14px;
            cursor: pointer;
        }
        .btn-pay {
            background: linear-gradient(135deg,#2563eb,#4f46e5);
            color: #fff;
            border: none;
        }
        .btn-back {
            background: transparent;
            color: #9ca3af;
            border: 1px solid #4b5563;
            margin-left: 8px;
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="card">
        <h1>Pembayaran Sewa Mobil</h1>
        <p class="subtitle">Silakan lanjutkan pembayaran untuk menyelesaikan booking sewa mobilmu.</p>

        <div class="detail-row">
            <span class="label">Mobil</span>
            <span class="value"><?= htmlspecialchars($rent['nama_mobil']); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Nama</span>
            <span class="value"><?= htmlspecialchars($rent['nama_lengkap']); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Order ID</span>
            <span class="value"><?= htmlspecialchars($order_id); ?></span>
        </div>

        <div class="detail-row total">
            <span class="label">Total yang dibayar</span>
            <span class="value">Rp <?= number_format($total, 0, ',', '.'); ?></span>
        </div>

        <button id="pay-button" class="btn-pay">Bayar dengan Midtrans</button>
        <a href="profile.php?tab=rent" class="btn-back">Kembali ke Booking Saya</a>
    </div>
</div>

<script type="text/javascript">
document.getElementById('pay-button').addEventListener('click', function () {
    snap.pay('<?= $snapToken; ?>', {
        onSuccess: function(result){
            window.location.href = 'profile.php?tab=rent&pay=success';
        },
        onPending: function(result){
            window.location.href = 'profile.php?tab=rent&pay=pending';
        },
        onError: function(result){
            alert('Terjadi kesalahan saat pembayaran. Silakan coba lagi.');
        },
        onClose: function(){
            // user menutup popup tanpa menyelesaikan pembayaran
        }
    });
});
</script>
</body>
</html>
