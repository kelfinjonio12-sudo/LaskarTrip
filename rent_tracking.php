<?php
// rent_tracking.php
session_start();
require 'koneksi.php';

// cek login
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$rent_id = isset($_GET['rent_id']) ? (int) $_GET['rent_id'] : 0;

if ($rent_id <= 0) {
    die('ID sewa tidak valid.');
}

// ambil data sewa mobil
$sql = "
    SELECT r.*, c.nama_mobil, c.brand, c.tipe
    FROM rent_orders r
    JOIN cars c ON r.car_id = c.car_id
    WHERE r.rent_id = ? AND r.user_id = ?
";

$rent = null;

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $rent_id, $user_id);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $rent = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$rent) {
    die('Data sewa mobil tidak ditemukan.');
}

// data tujuan
$destLat         = isset($rent['destination_lat']) ? (float)$rent['destination_lat'] : 0;
$destLng         = isset($rent['destination_lng']) ? (float)$rent['destination_lng'] : 0;
$destinationName = $rent['lokasi_tujuan'] ?? 'Lokasi Tujuan';

// status styling
$rentStatus = strtolower($rent['rent_status'] ?? 'pending');
$statusText = $rent['rent_status'] ?? 'pending';
$statusClass = 'status-chip status-pending';

if (in_array($rentStatus, ['dikonfirmasi', 'selesai'])) {
    $statusClass = 'status-chip status-success';
} elseif (in_array($rentStatus, ['cancelled', 'dibatalkan'])) {
    $statusClass = 'status-chip status-danger';
} elseif (in_array($rentStatus, ['menunggu_driver'])) {
    $statusClass = 'status-chip status-warning';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tracking Sewa Mobil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- kalau kamu punya font / CSS global, boleh tambahkan di sini -->
    <!-- <link rel="stylesheet" href="assets/css/style.css"> -->

    <style>
        :root {
            --primary: #1e88e5;
            --primary-soft: rgba(30, 136, 229, 0.12);
            --accent: #00bfa6;
            --bg: #f3f6fb;
            --card-bg: #ffffff;
            --text-main: #1f2933;
            --text-muted: #6b7280;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #10b981;
            --radius-xl: 18px;
            --shadow-soft: 0 16px 35px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top left, #e0f2fe, #eef2ff 45%, #f9fafb);
            color: var(--text-main);
        }

        .tracking-shell {
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 32px 16px;
        }

        .tracking-wrapper {
            width: 100%;
            max-width: 1120px;
            background: linear-gradient(135deg, rgba(255,255,255,0.97), rgba(239,246,255,0.98));
            border-radius: 30px;
            box-shadow: var(--shadow-soft);
            padding: 24px 24px 28px;
        }

        @media (min-width: 992px) {
            .tracking-wrapper {
                padding: 28px 32px 32px;
            }
        }

        .tracking-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px 16px;
            margin-bottom: 20px;
        }

        .back-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.6);
            background: rgba(255,255,255,0.8);
            color: var(--text-muted);
            font-size: 13px;
            text-decoration: none;
        }

        .back-chip span {
            font-size: 16px;
            line-height: 1;
        }

        .tracking-title-group {
            flex: 1;
            min-width: 200px;
        }

        .tracking-title {
            font-size: 22px;
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .tracking-subtitle {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--text-muted);
        }

        .status-chip {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-chip::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
        }

        .status-pending {
            background: rgba(59,130,246,0.08);
            color: #2563eb;
        }
        .status-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }
        .status-warning {
            background: rgba(245,158,11,0.1);
            color: var(--warning);
        }
        .status-danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }

        .tracking-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.4fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .tracking-layout {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 18px 18px 20px;
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .card-title {
            font-size: 16px;
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title span.icon {
            width: 26px;
            height: 26px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .card-body {
            font-size: 14px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(226, 232, 240, 0.9);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-muted);
            font-size: 13px;
        }

        .info-value {
            font-weight: 600;
            text-align: right;
        }

        .info-value.muted {
            font-weight: 500;
            color: var(--text-muted);
        }

        #map {
            width: 100%;
            height: 360px;
            border-radius: 14px;
            overflow: hidden;
        }

        .warning {
            margin-top: 10px;
            padding: 9px 11px;
            border-radius: 10px;
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            border: 1px solid #fde68a;
        }

        .footer-note {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            font-size: 12px;
            color: var(--text-muted);
        }

        .powered-tag {
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(15,23,42,0.04);
        }

        .mini-chip {
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(59,130,246,0.06);
            color: #1d4ed8;
        }

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

<div class="tracking-shell">
    <div class="tracking-wrapper">

        <div class="tracking-header">
            <a href="profile.php?tab=rent" class="back-chip">
                <span>←</span> Kembali ke Sewa Mobil
            </a>

            <div class="tracking-title-group">
                <h1 class="tracking-title">Tracking Sewa Mobil</h1>
                <p class="tracking-subtitle">
                    Lihat detail perjalanan dan posisi tujuan sewa mobil kamu.
                </p>
            </div>

            <span class="<?= $statusClass; ?>">
                <?= htmlspecialchars($statusText); ?>
            </span>
        </div>

        <div class="tracking-layout">
            <!-- CARD INFO SEWA -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="icon">🚗</span>
                        Detail Sewa
                    </h2>
                    <span class="mini-chip">ID #<?= htmlspecialchars($rent['rent_id']); ?></span>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Nama Mobil</span>
                        <span class="info-value"><?= htmlspecialchars($rent['nama_mobil']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Brand</span>
                        <span class="info-value"><?= htmlspecialchars($rent['brand']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tipe</span>
                        <span class="info-value"><?= htmlspecialchars($rent['tipe']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Lokasi Jemput</span>
                        <span class="info-value"><?= htmlspecialchars($rent['lokasi_jemput']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Lokasi Tujuan</span>
                        <span class="info-value"><?= htmlspecialchars($destinationName); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Tanggal & Jam</span>
                        <span class="info-value muted">
                            <?= htmlspecialchars($rent['start_datetime'] ?? '-'); ?> s/d
                            <?= htmlspecialchars($rent['end_datetime'] ?? '-'); ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Harga</span>
                        <span class="info-value">
                            Rp <?= number_format((int)$rent['total_harga'], 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- CARD MAP -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="icon">📍</span>
                        Lokasi Tujuan di Map
                    </h2>
                </div>
                <div class="card-body">
                    <div id="map"></div>

                    <?php if ($destLat == 0 || $destLng == 0): ?>
                        <div class="warning">
                            Koordinat tujuan belum diatur. Pastikan <strong>destination_lat</strong>
                            dan <strong>destination_lng</strong> sudah terisi di tabel <strong>rent_orders</strong>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="footer-note">
            <span class="powered-tag">Laskar Trip • Sewa Mobil</span>
            <span>Data lokasi hanya digunakan untuk keperluan pemesanan perjalanan.</span>
        </div>
    </div>
</div>

<script>
    function initMap() {
        const destLat = <?= $destLat ?>;
        const destLng = <?= $destLng ?>;

        if (!destLat || !destLng) {
            console.warn('Koordinat tujuan belum di-set.');
            return;
        }

        const destination = { lat: destLat, lng: destLng };

        const map = new google.maps.Map(document.getElementById("map"), {
            zoom: 14,
            center: destination,
        });

        const marker = new google.maps.Marker({
            position: destination,
            map: map,
            title: "<?= addslashes($destinationName); ?>"
        });

        const infoWindow = new google.maps.InfoWindow({
            content: "<strong><?= addslashes($destinationName); ?></strong><br>Tujuan sewa mobil"
        });

        marker.addListener("click", () => {
            infoWindow.open(map, marker);
        });
    }
</script>

<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyALH5ozRsQK9Um6RlOgQFj1_gfi8u-ppGc&callback=initMap">
</script>

<script>
// Sinkron tema dengan yang disimpan di beranda
(function () {
    var theme = null;

    // daftar kemungkinan key yang dipakai
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
