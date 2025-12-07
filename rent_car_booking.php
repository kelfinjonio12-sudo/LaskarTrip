<?php
session_start();
include 'koneksi.php'; 

// --- CEK LOGIN USER ---
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$userId) {
    header('Location: login.php?redirect=rent_car_list.php');
    exit;
}

// --- AMBIL car_id ---
if (!isset($_GET['car_id']) && !isset($_POST['car_id'])) {
    die('Mobil tidak ditemukan.');
}

$carId = isset($_GET['car_id']) ? (int)$_GET['car_id'] : (int)$_POST['car_id'];

// default with_driver dari URL (?driver=1/0)
$withDriverDefault = isset($_GET['driver']) ? (int)$_GET['driver'] : 1;
$withDriverDefault = $withDriverDefault === 0 ? 0 : 1; // pastikan 0 atau 1

// --- AMBIL DATA MOBIL ---
$stmt = $conn->prepare("SELECT * FROM cars WHERE car_id = ? AND status = 'aktif'");
$stmt->bind_param("i", $carId);
$stmt->execute();
$carResult = $stmt->get_result();
$car = $carResult->fetch_assoc();

if (!$car) {
    die('Mobil tidak tersedia atau tidak ditemukan.');
}

$error = '';
$success = false;

// --- HANDLE FORM SUBMIT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lokasi_jemput = trim($_POST['lokasi_jemput'] ?? '');
    $lokasi_tujuan = trim($_POST['lokasi_tujuan'] ?? '');
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
    $jam_mulai     = $_POST['jam_mulai'] ?? '';
    $durasi_paket  = $_POST['durasi_paket'] ?? '12';
    $with_driver   = isset($_POST['with_driver']) && $_POST['with_driver'] == '0' ? 0 : 1;
    $catatan_user  = trim($_POST['catatan_user'] ?? '');

    // >>> TAMBAHKAN INI <<<
    $destination_lat = $_POST['destination_lat'] !== '' ? (float)$_POST['destination_lat'] : 0;
    $destination_lng = $_POST['destination_lng'] !== '' ? (float)$_POST['destination_lng'] : 0;
    
    if ($lokasi_jemput === '' || $tanggal_mulai === '' || $jam_mulai === '') {
        $error = "Lokasi jemput, tanggal, dan jam mulai wajib diisi.";
    } else {
        // bentuk datetime
        $start_datetime = $tanggal_mulai . ' ' . $jam_mulai . ':00';

        // durasi_jam & harga
        if ($durasi_paket == '24') {
            $durasi_jam  = 24;
            $total_harga = (int)$car['harga_24_jam'];
        } else {
            $durasi_jam  = 12;
            $total_harga = (int)$car['harga_12_jam'];
        }

        // hitung end_datetime
        $start = new DateTime($start_datetime);
        $end   = clone $start;
        $end->modify("+{$durasi_jam} hours");
        $end_datetime = $end->format('Y-m-d H:i:s');

        // sementara driver_id NULL (nanti admin yang assign)
        $driver_id = null;

        $stmtInsert = $conn->prepare("
            INSERT INTO rent_orders
        (user_id, car_id, driver_id, with_driver, lokasi_jemput, lokasi_tujuan,
         destination_lat, destination_lng,
         start_datetime, end_datetime, durasi_jam, total_harga,
         payment_status, rent_status, catatan_user)
            VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'pending', ?)
        ");

        // urutan param: i,i,i,i,s,s,d,d,s,s,i,i,s  (13 data)
        $stmtInsert->bind_param(
            "iiiissddssiis",
            $userId,           // i
            $carId,            // i
            $driver_id,        // i (boleh 0 / null)
            $with_driver,      // i
            $lokasi_jemput,    // s
            $lokasi_tujuan,    // s
            $destination_lat,  // d
            $destination_lng,  // d
            $start_datetime,   // s
            $end_datetime,     // s
            $durasi_jam,       // i
            $total_harga,      // i
            $catatan_user      // s
        );

        if ($stmtInsert->execute()) {
            $success = true;
        } else {
            $error = "Gagal menyimpan booking: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Booking Sewa Mobil - Laskar Trip</title>
    <link rel="stylesheet" href="rent_car.css">
</head>
<body>

<!-- NAVBAR SAMA SEPERTI LIST -->
<header class="lt-navbar">
    <div class="lt-nav-inner">
        <a href="index.php" class="lt-brand">
            <div class="lt-logo-circle">LT</div>
            <div class="lt-brand-text">
                <span class="lt-brand-name">Laskar Trip</span>
                <span class="lt-brand-sub">Hotel &amp; Sewa Mobil</span>
            </div>
        </a>

        <nav class="lt-nav-links">
            <a href="index.php#hotel">Hotel</a>
            <a href="rent_car_list.php" class="active">Sewa Mobil</a>
        </nav>

        <div class="lt-nav-actions">
            <a href="index.php" class="lt-btn-nav-primary">
                Kembali ke Beranda
            </a>
        </div>
    </div>
</header>

<div class="booking-page">
    <div class="booking-header">
        <h1>Detail Booking Mobil</h1>
        <p>Lengkapi data penjemputan, jadwal, dan opsi driver untuk menyelesaikan pemesanan.</p>
    </div>

    <div class="booking-layout">
        <!-- Kartu info mobil -->
        <aside class="booking-summary">
            <h2><?= htmlspecialchars($car['nama_mobil']) ?></h2>
            <p class="summary-brand"><?= htmlspecialchars($car['brand']) ?> • <?= htmlspecialchars($car['tipe']) ?></p>
            <p class="summary-meta">
                Transmisi: <strong><?= htmlspecialchars($car['transmisi']) ?></strong><br>
                Kapasitas: <strong><?= (int)$car['jumlah_kursi'] ?> kursi</strong>
            </p>

            <div class="summary-price">
                <span class="label">Mulai dari</span>
                <span class="value">Rp <?= number_format($car['harga_12_jam'], 0, ',', '.') ?></span>
                <div class="detail">
                    12 Jam • Rp <?= number_format($car['harga_12_jam'], 0, ',', '.') ?><br>
                    24 Jam • Rp <?= number_format($car['harga_24_jam'], 0, ',', '.') ?>
                </div>
            </div>

            <p class="summary-note">
                Setelah booking dibuat, tim Laskar Trip akan mengonfirmasi ketersediaan mobil & driver.
            </p>
        </aside>
        <!-- Form booking -->
        <section class="booking-form-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    Booking berhasil dibuat! 🎉<br>
                    Silakan cek statusnya di halaman dashboard / booking kamu.
                </div>
            <?php else: ?>
                <form method="post" class="booking-form" id="bookingForm">
    <input type="hidden" name="car_id" value="<?= (int)$carId ?>">

    <!-- HIDDEN koordinat tujuan, TIDAK mengubah tampilan -->
    <input type="hidden" id="destination_lat" name="destination_lat"
           value="<?= htmlspecialchars($_POST['destination_lat'] ?? '') ?>">
    <input type="hidden" id="destination_lng" name="destination_lng"
           value="<?= htmlspecialchars($_POST['destination_lng'] ?? '') ?>">

    <!-- LOKASI JEMPUT -->
    <div class="form-row">
        <label class="form-label" for="lokasi_jemput">Lokasi jemput *</label>
        <div class="form-field">
            <input
                type="text"
                id="lokasi_jemput"
                name="lokasi_jemput"
                required
                placeholder="Bandara / Stasiun / Alamat penjemputan"
                value="<?= htmlspecialchars($_POST['lokasi_jemput'] ?? '') ?>"
            >
        </div>
    </div>

    <!-- LOKASI TUJUAN (OPSIONAL) -->
    <div class="form-row">
        <label class="form-label" for="lokasi_tujuan">Lokasi tujuan (opsional)</label>
        <div class="form-field">
            <input
                type="text"
                id="lokasi_tujuan"
                name="lokasi_tujuan"
                placeholder="Lokasi tujuan / area penggunaan mobil"
                value="<?= htmlspecialchars($_POST['lokasi_tujuan'] ?? '') ?>"
            >
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-row">
            <label class="form-label">Tanggal mulai *</label>
            <div class="form-field">
                <input type="date" name="tanggal_mulai" required>
            </div>
        </div>

        <div class="form-row">
            <label class="form-label">Jam mulai *</label>
            <div class="form-field">
                <input type="time" name="jam_mulai" required>
            </div>
        </div>
    </div>

    <div class="form-row">
        <label class="form-label">Durasi sewa *</label>
        <div class="form-field">
            <select name="durasi_paket">
                <option value="12">12 Jam (Rp <?= number_format($car['harga_12_jam'], 0, ',', '.') ?>)</option>
                <option value="24">24 Jam (Rp <?= number_format($car['harga_24_jam'], 0, ',', '.') ?>)</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <label class="form-label">Opsi driver *</label>
        <div class="form-field form-radio-group">
            <label>
                <input type="radio" name="with_driver" value="1" <?= $withDriverDefault ? 'checked' : '' ?>>
                With Driver
            </label>
            <label>
                <input type="radio" name="with_driver" value="0" <?= !$withDriverDefault ? 'checked' : '' ?>>
                Without Driver (lepas kunci)
            </label>
        </div>
    </div>

    <div class="form-row">
        <label class="form-label">Catatan tambahan (opsional)</label>
        <div class="form-field">
            <textarea
                name="catatan_user"
                rows="3"
                placeholder="Contoh: Tolong bantu siapkan kursi bayi, jemput di terminal kedatangan."></textarea>
        </div>
    </div>

    <div class="form-actions">
        <a href="rent_car_list.php" class="btn-secondary">Kembali</a>
        <button type="submit" class="btn-primary">
            Konfirmasi Booking
        </button>
    </div>
</form>
            <?php endif; ?>
        </section>
    </div>
</div>
<!-- FOOTER LASKAR TRIP -->
    <footer class="lt-footer">
        <div class="lt-footer-inner">
            <div class="lt-footer-main">
                <span class="lt-footer-brand">Laskar Trip</span>
                <span class="lt-footer-text">
                    Transparan, review jujur, dan proses booking yang super cepat.
                </span>
            </div>
            <div class="lt-footer-meta">
                <span>© <?= date('Y'); ?> Laskar Trip.</span>
                <span>Hotel &amp; Sewa Mobil di satu halaman.</span>
            </div>
        </div>
    </footer>
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
<!-- GANTI YOUR_API_KEY dengan API key yang sama seperti di rent_tracking.php -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyALH5ozRsQK9Um6RlOgQFj1_gfi8u-ppGc&libraries=places"></script>
<script>
(function () {
    window.addEventListener('load', function () {
        var inputTujuan = document.getElementById('lokasi_tujuan');
        if (!inputTujuan) return;

        var autocomplete = new google.maps.places.Autocomplete(inputTujuan, {
            componentRestrictions: { country: "id" },
            fields: ["geometry", "formatted_address", "name"]
        });

        autocomplete.addListener('place_changed', function () {
            var place = autocomplete.getPlace();
            if (!place || !place.geometry) return;

            document.getElementById('destination_lat').value =
                place.geometry.location.lat();
            document.getElementById('destination_lng').value =
                place.geometry.location.lng();
        });
    });
})();
</script>
</body>
</html>

