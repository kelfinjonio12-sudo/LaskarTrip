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

    if ($nama_lengkap !== '' && $email !== '') {
        $stmt = mysqli_prepare($conn,
            "UPDATE users SET nama_lengkap = ?, email = ? WHERE username = ?"
        );
        mysqli_stmt_bind_param($stmt, "sss", $nama_lengkap, $email, $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Hindari resubmit form
        header('Location: profile.php?updated=1');
        exit;
    }
}

// Ambil data user
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama_lengkap, username, email, created_at 
     FROM users 
     WHERE username = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $user = [
        'id'           => 0,
        'nama_lengkap' => $username,
        'username'     => $username,
        'email'        => '-',
        'created_at'   => date('Y-m-d H:i:s'),
    ];
}

$user_id   = (int)$user['id'];
$join_date = date('d M Y', strtotime($user['created_at']));
$initial   = strtoupper(substr($user['nama_lengkap'] ?: $user['username'], 0, 1));
$updated   = isset($_GET['updated']);

// AMBIL DATA BOOKING (JIKA TABEL ADA)
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
    <link rel="stylesheet" href="style.css">
    <style>
        body {
        margin: 0;
        background: #f3f4f6;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        /* HEADER / NAVBAR KECIL */
        .detail-navbar {
            padding: 16px 40px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }
        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .detail-back-link {
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            text-decoration: none;
        }
        .detail-back-link:hover {
            color: #111827;
        }

        /* WRAPPER DASHBOARD PROFIL – FULLSCREEN */
        .profile-shell {
            width: 100%;
            min-height: calc(100vh - 64px); /* kira-kira tinggi header */
            margin: 0;
            padding: 24px 40px 40px;        /* ruang kiri-kanan seperti index */
            box-sizing: border-box;
            background: #f9fafb;
            border-radius: 0;
            box-shadow: none;
            border-top: 1px solid #e5e7eb;  /* biar nyatu sama header */
        }
        .profile-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .profile-subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
        }

        /* TABS */
        .profile-tabs {
            display: inline-flex;
            padding: 4px;
            border-radius: 999px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            margin-bottom: 18px;
        }
        .tab-link {
            border: none;
            background: transparent;
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
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
            grid-template-columns: 280px 1fr;
            gap: 20px;
        }

        /* KARTU KIRI: INFO USER */
        .profile-card {
            background: #f9fafb;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            padding: 18px 18px 20px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }
        .profile-avatar {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            background: linear-gradient(135deg, #2563eb, #22c55e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }
        .profile-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .profile-username {
            font-size: 13px;
            color: #6b7280;
        }
        .profile-info-list {
            margin-top: 12px;
            font-size: 13px;
            color: #4b5563;
        }
        .profile-info-list div {
            margin-bottom: 6px;
        }
        .profile-info-label {
            color: #9ca3af;
            font-size: 12px;
        }

        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eff6ff;
            font-size: 12px;
            color: #1d4ed8;
            margin-top: 8px;
        }

        .profile-actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
        }
        .btn-pill {
            border-radius: 999px;
            border: none;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #111827;
        }

        .profile-edit-form {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px dashed #e5e7eb;
            display: none; /* di-toggle dengan JS */
        }
        .profile-edit-form .form-row {
            margin-bottom: 10px;
        }
        .profile-edit-form label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 4px;
            color: #4b5563;
        }
        .profile-edit-form input {
            width: 100%;
            padding: 7px 10px;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            font-size: 13px;
        }
        .profile-edit-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 8px;
        }

        .alert-success {
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        /* KARTU KANAN: RINGKASAN & PREFERENSI */
        .profile-section {
            background: #f9fafb;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            padding: 18px 18px 10px;
            margin-bottom: 14px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 600;
        }
        .section-subtext {
            font-size: 12px;
            color: #9ca3af;
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
            color: #9ca3af;
        }

        .pref-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 12px;
        }
        .pref-tag {
            padding: 5px 10px;
            border-radius: 999px;
            background: #e5e7eb;
            color: #374151;
        }

        .security-list {
            font-size: 13px;
            color: #4b5563;
        }
        .security-list li {
            margin-bottom: 4px;
        }

        .profile-footer {
            margin-top: 16px;
            font-size: 12px;
            color: #9ca3af;
            text-align: right;
        }

        /* BOOKING LIST */
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
            border-bottom: 1px solid #111827;
        }
        body.dark-mode .detail-header .brand {
            color: #f9fafb;
        }
        body.dark-mode .detail-header .brand span {
            color: #60a5fa;
        }
        body.dark-mode .detail-back-link {
            color: #93c5fd;
        }
        body.dark-mode .detail-back-link:hover {
            color: #bfdbfe;
        }

        body.dark-mode .profile-shell {
            background: #020617;
            box-shadow: none;
            border: none;
            border-top: 1px solid #111827;
        }

        body.dark-mode .profile-card,
        body.dark-mode .profile-section {
            background: #020617;
            border-color: #1f2937;
        }
        body.dark-mode .profile-subtitle,
        body.dark-mode .profile-username,
        body.dark-mode .profile-info-label,
        body.dark-mode .section-subtext,
        body.dark-mode .trip-meta {
            color: #9ca3af;
        }
        body.dark-mode .pref-tag {
            background: #1f2937;
            color: #e5e7eb;
        }
        body.dark-mode .btn-outline {
            background: transparent;
            border-color: #374151;
            color: #e5e7eb;
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
            <!-- Kolom kiri: info user + form edit -->
            <aside class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar"><?= htmlspecialchars($initial); ?></div>
                    <div>
                        <div class="profile-name"><?= htmlspecialchars($user['nama_lengkap'] ?: $user['username']); ?></div>
                        <div class="profile-username">@<?= htmlspecialchars($user['username']); ?></div>
                    </div>
                </div>

                <div class="profile-info-list">
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

            <!-- Kolom kanan: ringkasan & preferensi & keamanan -->
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
                            <div class="section-subtext">Kami gunakan untuk memberi rekomendasi hotel yang lebih relevan.</div>
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
                    <div class="section-subtext">Riwayat pemesanan hotel yang terhubung dengan akunmu.</div>
                </div>
            </div>

            <?php if ($booking_error): ?>
                <div class="booking-empty">
                    <?= htmlspecialchars($booking_error); ?>
                </div>

            <?php elseif (empty($bookings)): ?>
                <div class="booking-empty">
                    Kamu belum memiliki booking. Mulai cari hotel favoritmu di halaman utama dan lakukan pemesanan.
                </div>

            <?php else: ?>
                <div class="booking-list">
                    <?php foreach ($bookings as $b): ?>
                        <?php
                        // Format tanggal dari kolom bookings: check_in, check_out, created_at
                        $check_in  = isset($b['check_in'])  && $b['check_in']
                            ? date('d M Y', strtotime($b['check_in']))
                            : '-';
                        $check_out = isset($b['check_out']) && $b['check_out']
                            ? date('d M Y', strtotime($b['check_out']))
                            : '-';
                        $created   = isset($b['created_at']) && $b['created_at']
                            ? date('d M Y H:i', strtotime($b['created_at']))
                            : '-';

                        // Mapping status ke badge CSS
                        $status_raw   = strtolower($b['status'] ?? 'pending');
                        $status_label = strtoupper($status_raw);
                        $status_class = 'status-pending';

                        if (in_array($status_raw, ['success', 'paid', 'completed'])) {
                            $status_class = 'status-completed';
                        } elseif (in_array($status_raw, ['cancelled', 'canceled', 'failed'])) {
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
</section>
</main>

<!-- Aktifkan tema dark kalau sebelumnya user pilih dark + logika tab & edit form -->
<script>
(function () {
    var savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // Tab logic
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
