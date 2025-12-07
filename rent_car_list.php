<?php
require 'koneksi.php';

// Ambil parameter dari URL
$driver          = isset($_GET['driver']) && $_GET['driver'] === '0' ? 0 : 1;
$pickup_location = trim($_GET['pickup_location'] ?? '');
$start_raw       = $_GET['start_datetime'] ?? '';
$end_raw         = $_GET['end_datetime'] ?? '';
$duration_hours  = (int)($_GET['duration_hours'] ?? 12);

// Normalisasi datetime (input dari datetime-local biasanya "YYYY-MM-DDTHH:MM")
$start_datetime = $start_raw ?: null;
$end_datetime   = $end_raw   ?: null;

$driver_label = $driver ? 'With Driver' : 'Without Driver';

// ---- Hitung rentang waktu pencarian (search_start & search_end) ----
$search_start = null;
$search_end   = null;

if ($start_datetime) {
    $tsStart = strtotime($start_datetime);
    if ($tsStart !== false) {
        $search_start = date('Y-m-d H:i:s', $tsStart);
    }
}

if ($end_datetime) {
    // Kalau user kirim start & end (tanpa driver)
    $tsEnd = strtotime($end_datetime);
    if ($tsEnd !== false) {
        $search_end = date('Y-m-d H:i:s', $tsEnd);
    }
} elseif ($search_start && $duration_hours > 0) {
    // Kalau hanya ada start + durasi jam (with driver)
    $tsEnd = strtotime($search_start . " + {$duration_hours} hours");
    $search_end = date('Y-m-d H:i:s', $tsEnd);
}

// ---- Ambil daftar mobil + cek ketersediaan ----
$cars = [];

$sqlCars  = "SELECT * FROM cars ORDER BY nama_mobil ASC";
$resCars = mysqli_query($conn, $sqlCars);

if ($resCars) {
    while ($row = mysqli_fetch_assoc($resCars)) {
        $row['available'] = true; // default: dianggap tersedia

        // Kalau pencarian punya rentang waktu lengkap, cek ke rent_orders
        if ($search_start && $search_end) {
            $car_id = (int)$row['car_id'];

            $sqlCheck = "
                SELECT 1 
                FROM rent_orders
                WHERE car_id = ?
                  AND rent_status IN ('pending','menunggu_driver','dikonfirmasi','on_trip')
                  AND start_datetime < ?
                  AND end_datetime   > ?
                LIMIT 1
            ";

            if ($stmtCheck = mysqli_prepare($conn, $sqlCheck)) {
                mysqli_stmt_bind_param($stmtCheck, "iss", $car_id, $search_end, $search_start);
                mysqli_stmt_execute($stmtCheck);
                mysqli_stmt_store_result($stmtCheck);

                $hasConflict = mysqli_stmt_num_rows($stmtCheck) > 0;
                mysqli_stmt_close($stmtCheck);

                // Kalau ada booking yang bentrok, berarti mobil tidak tersedia
                if ($hasConflict) {
                    $row['available'] = false;
                }
            }
        }

        $cars[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Mobil - Laskar Trip</title>
    <link rel="stylesheet" href="rent_car.css">
</head>
<body>

<!-- NAVBAR MIRIP BERANDA -->
<header class="lt-navbar">
    <div class="lt-nav-inner">
        <a href="index.php" class="lt-brand">
            <div class="lt-logo-circle">LT</div>
            <div class="lt-brand-text">
                <span class="lt-brand-name">Laskar Trip</span>
                <span class="lt-brand-sub">Hotel &amp; Sewa Mobil</span>
            </div>
        </a>
        <div class="lt-nav-actions">
            <a href="index.php" class="lt-btn-nav-primary">
                Kembali ke Beranda
            </a>
        </div>
    </div>
</header>

<div class="rent-page">
    <header class="rent-header">
        <span class="rent-chip">Rent Car</span>
        <h1>Daftar Mobil Laskar Trip</h1>
        <p>Pilih mobil dan durasi sewa, nanti detail jadwal &amp; lokasi jemput kamu atur di langkah berikutnya.</p>
    </header>

    <div class="rent-summary-box">
    <div class="rent-summary-row">
        <span class="label">Tipe layanan</span>
        <span class="value"><?= htmlspecialchars($driver_label); ?></span>
    </div>

    <div class="rent-summary-actions">
    <a href="index.php#rentcar" class="btn-kecil">Ubah pencarian</a>
    </div>
    
    <div class="rent-car-list">
    <!-- card mobil kamu di sini -->
    </div>

    <?php if ($pickup_location): ?>
    <div class="rent-summary-row">
        <span class="label">Lokasi jemput</span>
        <span class="value"><?= htmlspecialchars($pickup_location); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($start_datetime): ?>
    <div class="rent-summary-row">
        <span class="label">Mulai</span>
        <span class="value">
            <?= date('d M Y H:i', strtotime($start_datetime)); ?>
        </span>
    </div>
    <?php endif; ?>

    <?php if ($end_datetime): ?>
    <div class="rent-summary-row">
        <span class="label">Selesai</span>
        <span class="value">
            <?= date('d M Y H:i', strtotime($end_datetime)); ?>
        </span>
    </div>
    <?php elseif ($duration_hours && $start_datetime): ?>
    <div class="rent-summary-row">
        <span class="label">Perkiraan durasi</span>
        <span class="value"><?= $duration_hours; ?> jam</span>
    </div>
    <?php endif; ?>
</div>

    <?php if (!empty($cars)): ?>
    <div class="car-list">
        <?php foreach ($cars as $row): ?>
            <?php $available = $row['available'] ?? true; ?>

            <article class="car-card">
                <div class="car-main">
                    <div class="car-title-row">
                        <h2 class="car-name">
                            <?= htmlspecialchars($row['nama_mobil']); ?>
                        </h2>
                        <span class="car-brand-badge">
                            <?= htmlspecialchars($row['brand']); ?>
                        </span>
                    </div>

                    <div class="car-tags">
                        <span class="tag"><?= htmlspecialchars($row['tipe']); ?></span>
                        <span class="tag"><?= htmlspecialchars($row['transmisi']); ?></span>
                        <span class="tag"><?= (int)$row['jumlah_kursi']; ?> kursi</span>
                    </div>

                    <p class="car-note">
                        Cocok untuk perjalanan keluarga, bisnis, maupun trip keliling kota bersama Laskar Trip.
                    </p>
                </div>

                <div class="car-side">
                    <div class="price-block">
                        <div class="price-row">
                            <span class="price-label">Mulai dari</span>
                            <span class="price-value">
                                Rp <?= number_format($row['harga_12_jam'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <div class="price-detail">
                            <span>12 Jam • Rp <?= number_format($row['harga_12_jam'], 0, ',', '.'); ?></span>
                            <span>24 Jam • Rp <?= number_format($row['harga_24_jam'], 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="car-footer">
                        <!-- Badge ketersediaan -->
                        <?php if ($available): ?>
                            <span class="availability-chip available">Tersedia di waktu ini</span>
                        <?php else: ?>
                            <span class="availability-chip not-available">Tidak tersedia di waktu ini</span>
                        <?php endif; ?>

                        <!-- Tombol -->
                        <?php if ($available): ?>
                            <a href="rent_car_booking.php?car_id=<?= (int)$row['car_id']; ?>&driver=<?= (int)$driver; ?>"
                               class="btn-primary">
                                Sewa Mobil Ini
                            </a>
                        <?php else: ?>
                            <span class="btn-primary btn-disabled">
                                Tidak bisa dipesan
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="empty-state">Saat ini belum ada mobil yang tersedia.</p>
<?php endif; ?>
</div>
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
