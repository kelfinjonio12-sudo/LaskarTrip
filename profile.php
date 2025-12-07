<?php
session_start();
require 'koneksi.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];

// HANDLE UPDATE PROFIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');

    if ($nama_lengkap && $email) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $nama_lengkap, $email, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header('Location: profile.php?updated=1');
        exit;
    }
}

// Ambil data user
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo "User tidak ditemukan.";
    exit;
}

$join_date = date('d M Y', strtotime($user['created_at'] ?? date('Y-m-d')));
$initial   = strtoupper(substr($user['nama_lengkap'] ?: $user['username'], 0, 1));
$updated   = isset($_GET['updated']);


// AMBIL DATA BOOKING HOTEL (JIKA TABEL ADA)
$bookings      = [];
$booking_error = null;

$bookingSql = "
    SELECT b.*, h.nama_hotel, h.lokasi
    FROM bookings b
    JOIN hotels h ON b.hotel_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
";

if ($stmtB = @mysqli_prepare($conn, $bookingSql)) {
    mysqli_stmt_bind_param($stmtB, "i", $user_id);
    mysqli_stmt_execute($stmtB);
    $resB = mysqli_stmt_get_result($stmtB);
    while ($row = mysqli_fetch_assoc($resB)) {
        $bookings[] = $row;
    }
    mysqli_stmt_close($stmtB);
} else {
    // Kalau belum ada tabel bookings / struktur berbeda, jangan fatal error
    $booking_error = 'Data booking belum tersedia atau tabel bookings belum dibuat.';
}

// AMBIL DATA BOOKING SEWA MOBIL (JIKA TABEL ADA)
$rent_bookings = [];
$rent_error    = null;

$rentSql = "
    SELECT r.*, c.nama_mobil, c.brand, c.tipe
    FROM rent_orders r
    JOIN cars c ON r.car_id = c.car_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
";

if ($stmtR = @mysqli_prepare($conn, $rentSql)) {
    mysqli_stmt_bind_param($stmtR, "i", $user_id);
    mysqli_stmt_execute($stmtR);
    $resR = mysqli_stmt_get_result($stmtR);
    while ($row = mysqli_fetch_assoc($resR)) {
        $rent_bookings[] = $row;
    }
    mysqli_stmt_close($stmtR);
} else {
    // Kalau belum ada tabel rent_orders / cars, jangan fatal error
    $rent_error = 'Data sewa mobil belum tersedia atau tabel rent_orders / cars belum dibuat.';
}

// Helper rupiah
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Saya - Laskar Trip</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* HEADER KECIL (NAVBAR) */
        .detail-navbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-header {
            max-width: 1120px;
            margin: 0 auto;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand {
            font-weight: 700;
            font-size: 18px;
            color: #111827;
        }
        .brand span {
            color: #2563eb;
        }
        .detail-back-link {
            font-size: 13px;
            color: #6b7280;
        }

        .profile-shell {
            width: 100%;
            min-height: calc(100vh - 64px);
            margin: 0;
            padding: 24px 40px 40px;
            box-sizing: border-box;
            background: #f9fafb;
            border-radius: 0;
            box-shadow: none;
            border-top: 1px solid #e5e7eb;
            max-width: 1120px;
            margin-inline: auto;
        }
        .profile-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .profile-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .alert-success {
            margin-bottom: 16px;
            padding: 10px 12px;
            border-radius: 999px;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            font-size: 13px;
            color: #166534;
        }

        /* TAB NAV ATAS */
        .profile-tabs {
            display: inline-flex;
            background: #e5e7eb;
            border-radius: 999px;
            padding: 4px;
            margin-bottom: 20px;
            border: 1px solid #d1d5db;
        }
        .tab-link {
            border: none;
            background: transparent;
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 14px;
            cursor: pointer;
            color: #4b5563;
        }
        .tab-link.active {
            background: #ffffff;
            color: #111827;
            box-shadow: 0 4px 12px rgba(15,23,42,0.12);
        }
        .tab-panel {
            display: none;
        }
        .tab-panel.active {
            display: block;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.2fr);
            gap: 20px;
        }
        .profile-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 16px 18px;
            border: 1px solid #e5e7eb;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .profile-avatar {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 20px;
        }
        .profile-name {
            font-weight: 700;
            font-size: 16px;
        }
        .profile-username {
            font-size: 12px;
            color: #6b7280;
        }
        .profile-info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .profile-info-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }

        .profile-badge {
            margin-top: 6px;
            margin-bottom: 10px;
            padding: 8px 10px;
            border-radius: 12px;
            background: #eff6ff;
            border: 1px dashed #bfdbfe;
            font-size: 13px;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: space-between;
        }

        .profile-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .btn-pill {
            border-radius: 999px;
            border: 1px solid transparent;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: #ffffff;
            border-color: transparent;
        }
        .btn-outline {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }

        .profile-edit-form {
            margin-top: 12px;
            display: none;
        }
        .form-row {
            margin-bottom: 10px;
        }
        .form-row label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .form-row input {
            width: 100%;
            padding: 7px 10px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            font-size: 13px;
            box-sizing: border-box;
        }
        .profile-edit-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .profile-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 16px 18px;
            border: 1px solid #e5e7eb;
            margin-bottom: 14px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .section-title {
            font-weight: 600;
            font-size: 14px;
        }
        .section-subtext {
            font-size: 12px;
            color: #6b7280;
        }

        .trip-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 13px;
        }
        .trip-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .trip-item:last-child {
            border-bottom: none;
        }
        .trip-city {
            font-weight: 600;
        }
        .trip-meta {
            font-size: 12px;
            color: #6b7280;
        }

        .pref-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 11px;
            margin-top: 10px;
        }
        .pref-tag {
            padding: 4px 8px;
            border-radius: 999px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
        }

        .security-list {
            font-size: 13px;
            padding-left: 16px;
            margin: 6px 0 0;
        }
        .security-list li {
            margin-bottom: 4px;
        }

        .profile-footer {
            margin-top: 8px;
            font-size: 12px;
            color: #9ca3af;
            text-align: right;
        }

        /* BOOKING LIST (dipakai hotel & sewa mobil) */
        .booking-list {
            margin-top: 8px;
            font-size: 13px;
        }
        .booking-card {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            padding: 12px 14px;
            margin-bottom: 10px;
            background: #ffffff;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        .booking-main {
            flex: 1;
        }
        .booking-hotel {
            font-weight: 600;
            margin-bottom: 2px;
        }
        .booking-location {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .booking-dates {
            font-size: 12px;
            color: #6b7280;
        }
        .booking-side {
            text-align: right;
            min-width: 140px;
        }
        .booking-price {
            font-weight: 700;
            font-size: 14px;
        }
        .booking-status {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            margin-top: 4px;
        }
        
        .booking-actions {
            margin-top: 8px;
        }
        .badge-paid-small {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #22c55e;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-completed {
            background: #dcfce7;
            color: #166534;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .booking-empty {
            padding: 18px 14px;
            border-radius: 16px;
            border: 1px dashed #d1d5db;
            font-size: 13px;
            color: #6b7280;
        }

        /* Sub-tab di dalam "Booking Saya" */
        .booking-subtabs {
            display: inline-flex;
            background: #f3f4f6;
            padding: 4px;
            border-radius: 999px;
            margin: 16px 0 12px;
            gap: 4px;
        }
        .booking-tab {
            border: none;
            background: transparent;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            cursor: pointer;
            color: #6b7280;
            font-weight: 500;
        }
        .booking-tab.active {
            background: #ffffff;
            color: #111827;
            box-shadow: 0 2px 8px rgba(15,23,42,0.12);
        }
        .booking-pane {
            display: none;
        }
        .booking-pane.active {
            display: block;
        }

        @media (max-width: 900px) {
            .profile-shell {
                padding: 16px 16px 28px;
            }
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .booking-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .booking-side {
                text-align: left;
            }
        }

        /* ===== DARK MODE ===== */
        body.dark-mode {
            background: #020617;
            color: #e5e7eb;
        }
        body.dark-mode .detail-navbar {
            background: #020617;
            border-color: #111827;
        }
        body.dark-mode .profile-shell {
            background: #020617;
            border-color: #111827;
        }
        body.dark-mode .profile-card,
        body.dark-mode .profile-section {
            background: #020617;
            border-color: #1f2937;
        }
        body.dark-mode .brand {
            color: #f9fafb;
        }
        body.dark-mode .detail-back-link {
            color: #9ca3af;
        }
        body.dark-mode .section-subtext,
        body.dark-mode .trip-meta,
        body.dark-mode .profile-subtitle,
        body.dark-mode .profile-info-label {
            color: #9ca3af;
        }
        body.dark-mode .pref-tag {
            background: #020617;
            border-color: #1f2937;
            color: #e5e7eb;
        }
        body.dark-mode .booking-status {
            border: 1px solid rgba(148,163,184,0.5);
        }
        body.dark-mode .profile-tabs {
            background: #020617;
            border-color: #1f2937;
        }
        body.dark-mode .tab-link {
            color: #9ca3af;
        }
        body.dark-mode .tab-link.active {
            background: #020617;
            color: #f9fafb;
            box-shadow: 0 6px 18px rgba(15,23,42,0.6);
        }

        body.dark-mode .booking-subtabs {
            background: #020617;
            border-color: #1f2937;
        }
        body.dark-mode .booking-tab {
            color: #9ca3af;
        }
        body.dark-mode .booking-tab.active {
            background: #020617;
            color: #f9fafb;
            box-shadow: 0 6px 18px rgba(15,23,42,0.6);
        }
        body.dark-mode .booking-card {
            background: #020617;
            border-color: #1f2937;
        }
        body.dark-mode .booking-location,
        body.dark-mode .booking-dates {
            color: #9ca3af;
        }
        body.dark-mode .booking-empty {
            border-color: #374151;
            color: #9ca3af;
        }
        body.dark-mode .alert-success {
            background: #022c22;
            border-color: #065f46;
            color: #bbf7d0;
        }
    </style>
</head>
<body>

<!-- HEADER / NAVBAR KECIL -->
<nav class="navbar detail-navbar">
    <div class="detail-topbar detail-header">
        <a href="index.php" class="brand">Laskar<span>Trip</span></a>
        <a href="index.php" class="detail-back-link">← Kembali ke Beranda</a>
    </div>
</nav>

<main class="profile-shell">
    <h1 class="profile-title">Dashboard Pengguna</h1>
    <p class="profile-subtitle">Lihat profil, kelola akun, dan pantau perjalananmu di Laskar Trip.</p>

    <?php if ($updated): ?>
        <div class="alert-success">
            ✅ Profil berhasil diperbarui.
        </div>
    <?php endif; ?>

    <!-- TAB NAV -->
    <div class="profile-tabs">
        <button class="tab-link active" data-tab="tab-profile">Profil</button>
        <button class="tab-link" data-tab="tab-bookings">Booking Saya</button>
    </div>

    <!-- PANEL 1: PROFIL -->
    <section id="tab-profile" class="tab-panel active">
        <div class="profile-grid">
            <!-- Kolom kiri -->
            <aside class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar"><?= htmlspecialchars($initial); ?></div>
                    <div>
                        <div class="profile-name"><?= htmlspecialchars($user['nama_lengkap'] ?: $user['username']); ?></div>
                        <div class="profile-username">@<?= htmlspecialchars($user['username']); ?></div>
                    </div>
                </div>

                <div class="profile-info-row">
                    <div>
                        <div class="profile-info-label">Email</div>
                        <div><?= htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div>
                        <div class="profile-info-label">Bergabung Sejak</div>
                        <div><?= htmlspecialchars($join_date); ?></div>
                    </div>
                </div>

                <div class="profile-badge">
                    ✈️ Traveller Starter
                    <span style="font-size:11px; color:#6b7280;">Siap eksplorasi destinasi pertama kamu.</span>
                </div>

                <div class="profile-actions">
                    <button type="button" class="btn-pill btn-primary" id="btnEditProfile">Edit Profil</button>
                    <button type="button" class="btn-pill btn-outline">Pengaturan Akun</button>
                </div>

                <!-- FORM EDIT PROFIL -->
                <form method="post" class="profile-edit-form" id="profileEditForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap"
                               value="<?= htmlspecialchars($user['nama_lengkap']); ?>" required>
                    </div>
                    <div class="form-row">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="profile-edit-actions">
                        <button type="button" class="btn-pill btn-outline" id="btnCancelEdit">Batal</button>
                        <button type="submit" class="btn-pill btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </aside>

            <!-- Kolom kanan -->
            <section>
                <div class="profile-section">
                    <div class="section-header">
                        <div>
                            <div class="section-title">Ringkasan Perjalanan</div>
                            <div class="section-subtext">Destinasi yang pernah kamu lihat / tandai.</div>
                        </div>
                        <div class="section-subtext">Data hanya contoh — nanti bisa dihubungkan ke riwayat booking.</div>
                    </div>
                    <ul class="trip-list">
                        <li class="trip-item">
                            <div>
                                <div class="trip-city">Jakarta City View</div>
                                <div class="trip-meta">Hotel • Jakarta</div>
                            </div>
                            <div class="trip-meta">Terakhir dilihat: 2 hari lalu</div>
                        </li>
                        <li class="trip-item">
                            <div>
                                <div class="trip-city">Malioboro Grand Hotel</div>
                                <div class="trip-meta">Hotel • Yogyakarta</div>
                            </div>
                            <div class="trip-meta">Terakhir dilihat: 5 hari lalu</div>
                        </li>
                        <li class="trip-item">
                            <div>
                                <div class="trip-city">Staycation Kuta Resort</div>
                                <div class="trip-meta">Resort • Bali</div>
                            </div>
                            <div class="trip-meta">Terakhir dilihat: 1 minggu lalu</div>
                        </li>
                    </ul>
                </div>

                <div class="profile-section">
                    <div class="section-header">
                        <div>
                            <div class="section-title">Preferensi Perjalanan</div>
                            <div class="section-subtext">Kami gunakan ini untuk rekomendasi yang lebih tepat.</div>
                        </div>
                    </div>
                    <div class="pref-tags">
                        <span class="pref-tag">Dekat pusat kota</span>
                        <span class="pref-tag">Wi-Fi Cepat</span>
                        <span class="pref-tag">Kolam Renang</span>
                        <span class="pref-tag">Sarapan Gratis</span>
                        <span class="pref-tag">Rating 4.5+</span>
                    </div>
                </div>

                <div class="profile-section">
                    <div class="section-header">
                        <div>
                            <div class="section-title">Keamanan Akun</div>
                            <div class="section-subtext">Kami menyimpan password kamu dalam bentuk terenkripsi.</div>
                        </div>
                    </div>
                    <ul class="security-list">
                        <li>✅ Password disimpan menggunakan <strong>password_hash()</strong> di server.</li>
                        <li>✅ Sesi login akan berakhir setelah beberapa waktu tidak aktif.</li>
                        <li>💡 Jangan bagikan detail akun ke orang lain.</li>
                    </ul>
                </div>

                <div class="profile-footer">
                    &copy; <?= date('Y'); ?> Laskar Trip · Dashboard Profil Pengguna
                </div>
            </section>
        </div>
    </section>

    <!-- PANEL 2: BOOKING SAYA -->
    <section id="tab-bookings" class="tab-panel">
        <div class="profile-section">
            <div class="section-header">
                <div>
                    <div class="section-title">Booking Saya</div>
                    <div class="section-subtext">Riwayat pemesanan hotel dan sewa mobil yang terhubung dengan akunmu.</div>
                </div>
            </div>

            <!-- Sub-tab Booking: Hotel / Sewa Mobil -->
            <div class="booking-subtabs">
                <button type="button" class="booking-tab active" data-target="#booking-hotel">Booking Hotel</button>
                <button type="button" class="booking-tab" data-target="#booking-car">Sewa Mobil</button>
            </div>

            <!-- PANE: BOOKING HOTEL -->
            <div id="booking-hotel" class="booking-pane active">
                <?php if ($booking_error): ?>
                    <div class="booking-empty">
                        <?= htmlspecialchars($booking_error); ?>
                    </div>

                <?php elseif (empty($bookings)): ?>
                    <div class="booking-empty">
                        Kamu belum memiliki booking hotel. Mulai cari hotel favoritmu di halaman utama dan lakukan pemesanan.
                    </div>

                <?php else: ?>
                    <div class="booking-list">
                        <?php foreach ($bookings as $b): ?>
                            <?php
                            $check_in  = isset($b['check_in'])  && $b['check_in']
                                ? date('d M Y', strtotime($b['check_in']))
                                : '-';
                            $check_out = isset($b['check_out']) && $b['check_out']
                                ? date('d M Y', strtotime($b['check_out']))
                                : '-';
                            $created   = isset($b['created_at']) && $b['created_at']
                                ? date('d M Y H:i', strtotime($b['created_at']))
                                : '-';

                            $status_raw   = strtolower($b['status'] ?? 'pending');
                            $status_label = strtoupper($status_raw);
                            $status_class = 'status-pending';

                            if (in_array($status_raw, ['success', 'paid', 'completed'])) {
                                $status_class = 'status-completed';
                            } elseif (in_array($status_raw, ['cancelled', 'batal'])) {
                                $status_class = 'status-cancelled';
                            }
                            ?>
                            <article class="booking-card">
                                <div class="booking-main">
                                    <div class="booking-hotel">
                                        <?= htmlspecialchars($b['nama_hotel']); ?>
                                    </div>
                                    <div class="booking-location">
                                        <?= htmlspecialchars($b['lokasi']); ?>
                                    </div>
                                    <div class="booking-dates">
                                        Check-in: <?= $check_in; ?> &nbsp; • &nbsp;
                                        Check-out: <?= $check_out; ?><br>
                                        Dibuat: <?= $created; ?>
                                    </div>
                                </div>
                                <div class="booking-side">
                                    <div class="booking-price">
                                        <?= format_rupiah($b['total_price']); ?>
                                    </div>
                                    <div class="booking-status <?= $status_class; ?>">
                                        <?= htmlspecialchars($status_label); ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PANE: BOOKING SEWA MOBIL -->
<div id="booking-car" class="booking-pane">
    <?php if ($rent_error): ?>
        <div class="booking-empty">
            <?= htmlspecialchars($rent_error); ?>
        </div>

    <?php elseif (empty($rent_bookings)): ?>
        <div class="booking-empty">
            Kamu belum memiliki booking sewa mobil. Mulai cari mobil di menu sewa mobil dan lakukan pemesanan.
        </div>

    <?php else: ?>
        <div class="booking-list">
            <?php foreach ($rent_bookings as $r): ?>
                <?php
                $start   = isset($r['start_datetime']) && $r['start_datetime']
                    ? date('d M Y H:i', strtotime($r['start_datetime']))
                    : '-';
                $end     = isset($r['end_datetime']) && $r['end_datetime']
                    ? date('d M Y H:i', strtotime($r['end_datetime']))
                    : '-';
                $created = isset($r['created_at']) && $r['created_at']
                    ? date('d M Y H:i', strtotime($r['created_at']))
                    : '-';

                $rent_status_raw   = strtolower($r['rent_status'] ?? 'pending');
                $rent_status_label = strtoupper($rent_status_raw);
                $rent_status_class = 'status-pending';
                if (in_array($rent_status_raw, ['success', 'paid', 'completed', 'finished', 'confirmed'])) {
                    $rent_status_class = 'status-completed';
                } elseif (in_array($rent_status_raw, ['cancelled', 'batal'])) {
                    $rent_status_class = 'status-cancelled';
                }

                $pay_raw   = strtolower($r['payment_status'] ?? 'unpaid');
                $pay_label = strtoupper($pay_raw);
                $pay_class = 'status-pending';
                if (in_array($pay_raw, ['paid', 'success'])) {
                    $pay_class = 'status-completed';
                } elseif (in_array($pay_raw, ['cancelled', 'failed', 'batal'])) {
                    $pay_class = 'status-cancelled';
                }
                ?>
                <article class="booking-card">
                    <div class="booking-main">
                        <div class="booking-hotel">
                            <?= htmlspecialchars($r['nama_mobil']); ?>
                        </div>
                        <div class="booking-location">
                            <?= htmlspecialchars($r['brand']); ?> • <?= htmlspecialchars($r['tipe']); ?> •
                            <?= $r['with_driver'] ? 'With Driver' : 'Without Driver'; ?>
                        </div>
                        <div class="booking-dates">
                            Mulai: <?= $start; ?> &nbsp; • &nbsp;
                            Selesai: <?= $end; ?><br>
                            Dibuat: <?= $created; ?>
                        </div>
                    </div>
                    <div class="booking-side">
                        <div class="booking-price">
                            <?= format_rupiah($r['total_harga']); ?>
                        </div>
                        <div class="booking-status <?= $rent_status_class; ?>">
                            <?= htmlspecialchars($rent_status_label); ?>
                        </div>
                        <div class="booking-status <?= $pay_class; ?>" style="margin-top:4px;">
                            <?= htmlspecialchars($pay_label); ?>
                        </div>

                        <?php
                            $rent_id        = (int)($r['rent_id'] ?? 0);
                            $rent_status    = strtolower($r['rent_status'] ?? 'pending');
                            $payment_status = strtolower($r['payment_status'] ?? 'unpaid');
                        ?>
                        <div class="booking-actions">
                            <?php if ($payment_status === 'unpaid' && !in_array($rent_status, ['cancelled','batal'], true)): ?>
                                <form action="midtrans_rent.php" method="post" style="display:inline;">
                                    <input type="hidden" name="rent_id" value="<?= (int)$rent_id; ?>">
                                    <button type="submit" class="btn-pill btn-primary">
                                        Bayar Sekarang
                                    </button>
                                </form>
                            <?php elseif (in_array($payment_status, ['paid','success'], true)): ?>
                                <span class="badge-paid-small">Sudah dibayar</span>
                            <?php endif; ?>
                            <a href="rent_tracking.php?rent_id=<?= $rent_id; ?>" class="btn-pill btn-outline">
                            Lihat Lokasi Tujuan
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
    </section>
</main>

<!-- Aktifkan tema dark + logika tab & sub-tab booking + edit form -->
<script>
(function () {
    var savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // Tab utama (Profil / Booking Saya)
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabPanels = document.querySelectorAll('.tab-panel');

    tabLinks.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab');

            tabLinks.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            document.getElementById(target).classList.add('active');
        });
    });

    // Sub-tab Booking: Hotel vs Sewa Mobil
    const bookingTabs  = document.querySelectorAll('.booking-tab');
    const bookingPanes = document.querySelectorAll('.booking-pane');

    bookingTabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-target');

            bookingTabs.forEach(b => b.classList.remove('active'));
            bookingPanes.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const pane = document.querySelector(target);
            if (pane) {
                pane.classList.add('active');
            }
        });
    });

    // Edit profil toggle
    const editBtn = document.getElementById('btnEditProfile');
    const cancelBtn = document.getElementById('btnCancelEdit');
    const form = document.getElementById('profileEditForm');

    if (editBtn && form) {
        editBtn.addEventListener('click', () => {
            form.style.display = form.style.display === 'block' ? 'none' : 'block';
        });
    }
    if (cancelBtn && form) {
        cancelBtn.addEventListener('click', () => {
            form.style.display = 'none';
        });
    }
})();
</script>
</body>
</html>
