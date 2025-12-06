<?php
// midtrans.php khusus LaskarTrip (dipanggil dari pembayaran.php)

session_start();
date_default_timezone_set('Asia/Jakarta');

require 'koneksi.php';                     // koneksi database (mysqli $conn)
require_once __DIR__ . '/vendor/autoload.php';  // library Midtrans via Composer

// Pastikan datang dari pembayaran.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// =====================
// 1. AMBIL DATA BOOKING
// =====================
$user_id         = (int)($_POST['user_id']         ?? 0);
$hotel_id        = (int)($_POST['hotel_id']        ?? 0);
$hotel_name      = $_POST['hotel_name']           ?? 'Hotel';

$harga_per_malam = (int)($_POST['harga_per_malam'] ?? 0);
$malam           = (int)($_POST['malam']           ?? 1);
$total_harga     = (int)($_POST['total_harga']     ?? 0);

$customer_name   = trim($_POST['customer_name']    ?? 'Tamu LaskarTrip');
$customer_phone  = trim($_POST['customer_phone']   ?? '');
$customer_email  = trim($_POST['customer_email']   ?? 'guest@example.com');
$checkin_date    = $_POST['checkin_date']          ?? null;
$checkout_date   = $_POST['checkout_date']         ?? null;

// Validasi sederhana
if (
    $user_id <= 0 || $hotel_id <= 0 ||
    $total_harga <= 0 || !$checkin_date || !$checkout_date
) {
    die('Data booking tidak lengkap.');
}

// ===============================
// 2. SIMPAN BOOKING KE DATABASE
//    (TABEL: bookings)
// ===============================
// Struktur tabel bookings punyamu:
// id, user_id, hotel_id, check_in, check_out, guests, rooms,
// total_price, status, payment_method, payment_reference,
// created_at, updated_at

$uid     = $user_id;
$hid     = $hotel_id;
$guests  = 1;  // sementara 1 tamu (bisa dikembangkan nanti)
$rooms   = 1;  // sementara 1 kamar
$total   = $total_harga;

$checkin  = mysqli_real_escape_string($conn, $checkin_date);
$checkout = mysqli_real_escape_string($conn, $checkout_date);

$sql = "INSERT INTO bookings
        (user_id, hotel_id, check_in, check_out, guests, rooms,
         total_price, status, payment_method, payment_reference)
        VALUES
        ($uid, $hid, '$checkin', '$checkout', $guests, $rooms,
         $total, 'pending', '', '')";

if (!mysqli_query($conn, $sql)) {
    // Kalau error, tampilkan supaya gampang debug
    die('Error simpan booking: ' . mysqli_error($conn));
}

$booking_id = mysqli_insert_id($conn); // id booking yang baru dibuat

// ==================================
// 3. KONFIGURASI MIDTRANS (SANDBOX)
// ==================================
\Midtrans\Config::$serverKey    = 'Mid-server-j4vm7a3h3uiMl4Ly0MDWUXX8'; // ganti kalau beda
\Midtrans\Config::$isProduction = false; // sandbox dulu
\Midtrans\Config::$isSanitized  = true;
\Midtrans\Config::$is3ds        = true;

// Buat order_id unik untuk Midtrans (boleh beda dengan id di DB)
$mt_order_id = 'BOOK-' . $booking_id . '-' . time();

// Data yang dikirim ke Midtrans
$params = [
    'transaction_details' => [
        'order_id'     => $mt_order_id,
        'gross_amount' => $total, // integer rupiah
    ],
    'item_details' => [[
        'id'       => 'HOTEL-' . $hid,
        'price'    => $harga_per_malam,
        'quantity' => $malam,
        'name'     => substr($hotel_name, 0, 50),
        'category' => 'Hotel Booking',
    ]],
    'customer_details' => [
        'first_name'       => $customer_name,
        'email'            => $customer_email,
        'phone'            => $customer_phone,
        'billing_address'  => [
            'address' => "Check-in: {$checkin_date}, Check-out: {$checkout_date}",
        ],
        'shipping_address' => [
            'address' => "Check-in: {$checkin_date}, Check-out: {$checkout_date}",
        ],
    ],
];

// Dapatkan Snap Token
$snapToken = \Midtrans\Snap::getSnapToken($params);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Pembayaran — LaskarTrip</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      display:grid;
      place-items:center;
      min-height:100vh;
      background:#f3f4f6;
      color:#111827;
      padding:24px 16px;
    }
    .box{
      background:#fff;
      border:1px solid #e5e7eb;
      border-radius:16px;
      padding:22px 22px 18px;
      max-width:520px;
      width:100%;
      box-shadow:0 12px 30px rgba(15,23,42,0.08);
    }
    h1{
      font-size:20px;
      margin-bottom:6px;
      font-weight:600;
    }
    .muted{
      font-size:13px;
      color:#6b7280;
      margin-bottom:12px;
    }
    .summary{
      font-size:13px;
      background:#f9fafb;
      border-radius:14px;
      padding:10px 12px;
      border:1px solid #e5e7eb;
      margin-bottom:12px;
    }
    .row{
      display:flex;
      justify-content:space-between;
      margin-bottom:4px;
      gap:8px;
    }
    .row span:first-child{
      color:#6b7280;
    }
    .row span:last-child{
      text-align:right;
      font-weight:500;
    }
    .btn{
      border:none;
      border-radius:999px;
      padding:10px 18px;
      background:#22c55e;
      color:#fff;
      font-weight:600;
      font-size:14px;
      cursor:pointer;
      margin-top:8px;
      width:100%;
      transition:.2s;
    }
    .btn:hover{
      background:#16a34a;
    }

    /* ============= DARK MODE ============= */
    body.dark-mode {
      background:#020617;
      color:#e5e7eb;
    }

    body.dark-mode .box{
      background:#0f172a;
      border-color:#1f2937;
      box-shadow:0 16px 40px rgba(15,23,42,0.6);
    }

    body.dark-mode .muted{
      color:#9ca3af;
    }

    body.dark-mode .summary{
      background:#020617;
      border-color:#1f2937;
    }

    body.dark-mode .row span:first-child{
      color:#9ca3af;
    }

    body.dark-mode .btn{
      background:#22c55e;
    }
    body.dark-mode .btn:hover{
      background:#16a34a;
    }
  </style>
</head>
<body>
  <div class="box">
    <h1>Pembayaran Booking</h1>
    <p class="muted">
      Sedang memproses pembayaran untuk pemesanan kamar di
      <strong><?= htmlspecialchars($hotel_name); ?></strong>.
      Klik tombol di bawah untuk melanjutkan ke Midtrans.
    </p>

    <div class="summary">
      <div class="row">
        <span>Tamu</span>
        <span><?= htmlspecialchars($customer_name); ?></span>
      </div>
      <div class="row">
        <span>Tanggal</span>
        <span><?= htmlspecialchars($checkin_date); ?> s/d <?= htmlspecialchars($checkout_date); ?></span>
      </div>
      <div class="row">
        <span>Total</span>
        <span>Rp<?= number_format($total,0,',','.'); ?></span>
      </div>
    </div>

    <button id="payBtn" class="btn">Bayar Sekarang</button>

    <!-- form tujuan setelah transaksi (hasil Snap) -->
    <form id="f" method="post" action="snap_finish.php">
      <input type="hidden" name="result_data" id="result_data">
      <input type="hidden" name="booking_id" value="<?= (int)$booking_id; ?>">
      <input type="hidden" name="hotel_id" value="<?= (int)$hotel_id; ?>">
      <input type="hidden" name="hotel_name" value="<?= htmlspecialchars($hotel_name); ?>">
      <input type="hidden" name="total_harga" value="<?= (int)$total; ?>">
    </form>
  </div>

  <!-- CLIENT KEY SANDBOX -->
  <script src="https://app.sandbox.midtrans.com/snap/snap.js"
          data-client-key="Mid-client-SzC2hJV6frpYkrZk"></script>
  <script>
    const token = <?= json_encode($snapToken) ?>;
    const f  = document.getElementById('f');
    const rd = document.getElementById('result_data');
    const btn= document.getElementById('payBtn');

    function pay(){
      window.snap.pay(token, {
        onSuccess: function(result){
          rd.value = JSON.stringify(result);
          f.submit();
        },
        onPending: function(result){
          rd.value = JSON.stringify(result);
          f.submit();
        },
        onError: function(result){
          rd.value = JSON.stringify(result);
          f.submit();
        },
        onClose: function(){
          alert('Popup pembayaran ditutup sebelum selesai.');
        }
      });
    }

    btn.addEventListener('click', pay);
    // kalau mau auto-popup begitu halaman dibuka, aktifkan ini:
    // pay();
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
