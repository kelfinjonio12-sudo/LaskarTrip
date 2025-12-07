<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    // kalau belum login sebagai admin, alihkan
    header('Location: admin_login.php');
    exit;
}

require 'koneksi.php';

// Helper sederhana untuk formatting rupiah
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ----- HANDLE UPDATE BOOKING SEWA MOBIL -----
$rent_flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rent'])) {
    $rent_id        = (int)($_POST['rent_id'] ?? 0);
    $rent_status    = $_POST['rent_status'] ?? 'pending';
    $payment_status = $_POST['payment_status'] ?? 'unpaid';
    $driver_id_raw  = $_POST['driver_id'] ?? '';

    // batasi status ke nilai yang diizinkan
    $allowed_rent_status = ['pending','menunggu_driver','dikonfirmasi','on_trip','completed','cancelled'];
    $allowed_pay_status  = ['unpaid','paid','cancelled','refunded'];

    if (!in_array($rent_status, $allowed_rent_status, true)) {
        $rent_status = 'pending';
    }
    if (!in_array($payment_status, $allowed_pay_status, true)) {
        $payment_status = 'unpaid';
    }

    // driver boleh kosong (artinya belum di-assign)
    $driver_id = ($driver_id_raw === '' ? null : (int)$driver_id_raw);

    if ($rent_id > 0) {
        if ($driver_id === null) {
            $sqlUpdate = "UPDATE rent_orders
                          SET rent_status = ?, payment_status = ?, driver_id = NULL
                          WHERE rent_id = ?";
            $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
            mysqli_stmt_bind_param($stmtUpdate, "ssi", $rent_status, $payment_status, $rent_id);
        } else {
            $sqlUpdate = "UPDATE rent_orders
                          SET rent_status = ?, payment_status = ?, driver_id = ?
                          WHERE rent_id = ?";
            $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
            mysqli_stmt_bind_param($stmtUpdate, "ssii", $rent_status, $payment_status, $driver_id, $rent_id);
        }

        if ($stmtUpdate && mysqli_stmt_execute($stmtUpdate)) {
            $rent_flash = "Perubahan booking sewa mobil #{$rent_id} berhasil disimpan.";
        } else {
            $rent_flash = "Gagal menyimpan perubahan booking sewa mobil.";
        }

        if ($stmtUpdate) {
            mysqli_stmt_close($stmtUpdate);
        }
    }
}

// ----- HANDLE HAPUS DATA HOTEL -----
if (isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];

    if ($id_hapus > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM hotels WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id_hapus);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header('Location: admin.php');
    exit;
}

// ----- HANDLE TAMBAH / EDIT HOTEL -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create' || $_POST['action'] === 'update')) {
    $action          = $_POST['action'];
    $id_hotel        = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $nama_hotel      = trim($_POST['nama_hotel'] ?? '');
    $lokasi          = trim($_POST['lokasi'] ?? '');
    $harga_per_malam = (int) str_replace(['.', ','], '', $_POST['harga_per_malam'] ?? '0');
    $rating          = (float) ($_POST['rating'] ?? 0);
    $jumlah_review   = (int) ($_POST['jumlah_review'] ?? 0);

    if ($nama_hotel !== '' && $lokasi !== '' && $harga_per_malam > 0) {
        if ($action === 'update' && $id_hotel > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE hotels 
                 SET nama_hotel = ?, lokasi = ?, harga_per_malam = ?, rating = ?, jumlah_review = ?
                 WHERE id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssiddi',
                $nama_hotel,
                $lokasi,
                $harga_per_malam,
                $rating,
                $jumlah_review,
                $id_hotel
            );
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO hotels (nama_hotel, lokasi, harga_per_malam, rating, jumlah_review)
                 VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssidi',
                $nama_hotel,
                $lokasi,
                $harga_per_malam,
                $rating,
                $jumlah_review
            );
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header('Location: admin.php');
    exit;
}

// ----- AMBIL DATA UNTUK FILTER & STATISTIK -----

// Lokasi unik
$lokasi_options = [];
$resLok = mysqli_query($conn, "SELECT DISTINCT lokasi FROM hotels ORDER BY lokasi ASC");
while ($row = mysqli_fetch_assoc($resLok)) {
    $lokasi_options[] = $row['lokasi'];
}
mysqli_free_result($resLok);

// Statistik global
$stats = [
    'total_hotel' => 0,
    'avg_harga'   => 0,
    'avg_rating'  => 0,
    'top_kota'    => '-'
];

// Total & rata-rata
$resStat = mysqli_query($conn, "SELECT COUNT(*) AS total, AVG(harga_per_malam) AS avg_harga, AVG(rating) AS avg_rating FROM hotels");
if ($row = mysqli_fetch_assoc($resStat)) {
    $stats['total_hotel'] = (int) $row['total'];
    $stats['avg_harga']   = (float) $row['avg_harga'];
    $stats['avg_rating']  = (float) $row['avg_rating'];
}
mysqli_free_result($resStat);

// Kota terbanyak
$resCity = mysqli_query($conn, "SELECT lokasi, COUNT(*) AS jml FROM hotels GROUP BY lokasi ORDER BY jml DESC LIMIT 1");
if ($row = mysqli_fetch_assoc($resCity)) {
    $stats['top_kota'] = $row['lokasi'];
}
mysqli_free_result($resCity);

// ----- AMBIL DATA HOTEL UNTUK LIST -----
$keyword       = trim($_GET['q'] ?? '');
$filter_lokasi = trim($_GET['lokasi'] ?? '');
$sort          = $_GET['sort'] ?? 'baru';

$sql    = "SELECT * FROM hotels WHERE 1";
$params = [];
$types  = '';

if ($keyword !== '') {
    $sql .= " AND (nama_hotel LIKE ? OR lokasi LIKE ?)";
    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($filter_lokasi !== '') {
    $sql .= " AND lokasi = ?";
    $params[] = $filter_lokasi;
    $types   .= 's';
}

switch ($sort) {
    case 'harga_asc':
        $sql .= " ORDER BY harga_per_malam ASC";
        break;
    case 'harga_desc':
        $sql .= " ORDER BY harga_per_malam DESC";
        break;
    case 'rating_desc':
        $sql .= " ORDER BY rating DESC";
        break;
    default:
        $sql .= " ORDER BY id DESC";
        break;
}

$stmt = mysqli_prepare($conn, $sql);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$hotels = [];
while ($row = mysqli_fetch_assoc($result)) {
    $hotels[] = $row;
}
mysqli_stmt_close($stmt);

// ----- PAGINASI HOTEL -----
$hotel_per_page = 10;
$hotel_total    = count($hotels);
$hotel_pages    = max(1, ceil($hotel_total / $hotel_per_page));

// halaman aktif
$hotel_page = isset($_GET['hotel_page']) ? (int)$_GET['hotel_page'] : 1;
if ($hotel_page < 1) $hotel_page = 1;
if ($hotel_page > $hotel_pages) $hotel_page = $hotel_pages;

$hotel_offset = ($hotel_page - 1) * $hotel_per_page;
$hotels_page = array_slice($hotels, $hotel_offset, $hotel_per_page);

$hotel_query_params = $_GET;
unset($hotel_query_params['hotel_page']);
$hotel_query_base = http_build_query($hotel_query_params);
$hotel_query_base = $hotel_query_base ? $hotel_query_base . '&' : '';

// ----- DATA RENT CAR UNTUK PANEL ADMIN SEWA MOBIL -----

// Ambil daftar driver
$drivers = [];
$sqlDrivers = "SELECT driver_id, nama_driver FROM drivers ORDER BY nama_driver ASC";
if ($resDrivers = mysqli_query($conn, $sqlDrivers)) {
    while ($rowDrv = mysqli_fetch_assoc($resDrivers)) {
        $drivers[] = $rowDrv;
    }
}

// Ambil daftar booking sewa mobil
$rent_orders = [];
$sqlRent = "
    SELECT r.*,
           u.nama_lengkap AS user_nama, u.email AS user_email,
           c.nama_mobil, c.brand, c.tipe,
           d.nama_driver
    FROM rent_orders r
    JOIN users u   ON r.user_id = u.id
    JOIN cars  c   ON r.car_id  = c.car_id
    LEFT JOIN drivers d ON r.driver_id = d.driver_id
    ORDER BY r.created_at DESC
";
if ($resRent = mysqli_query($conn, $sqlRent)) {
    while ($rowRent = mysqli_fetch_assoc($resRent)) {
        $rent_orders[] = $rowRent;
    }
}

// Hitung total booking rent car untuk card statistik
$total_rent_orders = count($rent_orders);

// ----- PAGINASI SEWA MOBIL -----
$rent_per_page = 10;
$rent_total    = count($rent_orders);
$rent_pages    = max(1, ceil($rent_total / $rent_per_page));

$rent_page = isset($_GET['rent_page']) ? (int)$_GET['rent_page'] : 1;
if ($rent_page < 1) $rent_page = 1;
if ($rent_page > $rent_pages) $rent_page = $rent_pages;

$rent_offset      = ($rent_page - 1) * $rent_per_page;
$rent_orders_page = array_slice($rent_orders, $rent_offset, $rent_per_page);

// Data untuk form edit (sekarang di-handle lewat JS, tapi kita siapkan struktur PHP-nya)
// Kita tidak lagi pakai $_GET['edit'] untuk render form langsung, tapi kirim data ke modal via JS.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>LaskarTrip Admin Panel</title>
    <!-- Fonts: Poppins / Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #4361ee;
            --bg-body: #f8f9fa;
            --text-dark: #333;
            --text-muted: #8898aa;
            --white: #ffffff;
            
            --card-blue: #4cc9f0;
            --card-green: #2ecc71;
            --card-yellow: #f1c40f;
            --card-orange: #e67e22;
            
            --border-color: #e9ecef;
            --sidebar-width: 250px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            background-color: var(--bg-body);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--white);
            border-right: 1px solid var(--border-color);
            position: fixed;
            height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 40px;
        }
        
        .sidebar-brand i { font-size: 24px; }

        .nav-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .nav-item a:hover {
            color: var(--primary);
            background: #eef2ff;
        }

        .nav-item a.active {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 30px;
            overflow-x: hidden;
        }

        /* Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-title h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info {
            text-align: right;
        }
        .user-info .name { font-weight: 600; font-size: 14px; }
        .user-info .role { font-size: 12px; color: var(--text-muted); }
        
        .user-avatar {
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: #eee;
            overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            border-radius: 16px;
            padding: 24px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-card.blue { background: var(--card-blue); }
        .stat-card.green { background: var(--card-green); }
        .stat-card.yellow { background: var(--card-yellow); }
        .stat-card.orange { background: var(--card-orange); }

        .stat-info h3 { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
        .stat-info p { font-size: 13px; opacity: 0.9; }
        .stat-icon { font-size: 32px; opacity: 0.4; }

        /* Chart Section Placeholder */
        .chart-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .chart-header h3 { font-size: 16px; font-weight: 600; }

        .chart-placeholder {
            height: 300px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            border: 2px dashed #d1d5db;
            flex-direction: column;
            gap: 12px;
        }
        
        .chart-placeholder i { font-size: 40px; color: #cbd5e1; }

        /* Tables & Lists */
        .content-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #304ffe; }
        .btn-outline { border: 1px solid #d1d5db; background: white; color: var(--text-dark); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-warning { background: #f59e0b; color: white; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: var(--text-muted); font-size: 12px; font-weight: 600; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
        td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: var(--text-dark); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-success { background: #d4edda; color: #155724; }
        .bg-info { background: #d1ecf1; color: #0c5460; }
        .bg-danger { background: #f8d7da; color: #721c24; }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
        }

        /* Editor Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 999;
        }
        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 16px;
            width: 500px;
            max-width: 90%;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 500; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; }

        /* Pagination */
        .pagination { display: flex; gap: 5px; justify-content: flex-end; margin-top: 15px; }
        .page-link { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; text-decoration: none; font-size: 12px; color: var(--text-dark); }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* Responsive */
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); transition: 0.3s; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .header-title h1 { font-size: 20px; }
        }
        
        /* ===== PANEL BOOKING SEWA MOBIL ===== */
        .rent-header {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .rent-title {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }
        .rent-subtitle {
            margin: 2px 0 0;
            font-size: 13px;
            color: #6b7280;
        }
        .rent-flash {
            margin-bottom: 8px;
            border-radius: 999px;
            padding: 8px 10px;
            font-size: 12px;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .rent-empty {
            padding: 18px 14px;
            border-radius: 14px;
            border: 1px dashed #d1d5db;
            font-size: 13px;
            color: #6b7280;
            background: #f9fafb;
        }
        .table-scroll {
            overflow-x: auto;
        }
        .rent-col-user { width: 180px; }
        .rent-col-car { width: 180px; }
        .rent-col-schedule { width: 220px; }
        .rent-col-total { width: 110px; }
        .rent-col-status { width: 260px; }

        .rent-user-name { font-weight: 600; font-size: 12px; }
        .rent-user-email { font-size: 11px; color: #6b7280; }
        .rent-car-name { font-weight: 600; font-size: 12px; }
        .rent-car-meta { font-size: 11px; color: #6b7280; }
        .rent-schedule-text { font-size: 11px; color: #4b5563; }
        .rent-location-text { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .rent-price { font-weight: 700; font-size: 13px; color: #111827; }

        .rent-badges { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px; }
        .rent-badge { display: inline-flex; align-items: center; padding: 3px 7px; border-radius: 999px; font-size: 10px; border: 1px solid transparent; }
        
        .badge-rent-pending { background: #fef3c7; border-color: #facc15; color: #92400e; }
        .badge-rent-menunggu_driver { background: #e0f2fe; border-color: #38bdf8; color: #075985; }
        .badge-rent-dikonfirmasi { background: #dcfce7; border-color: #22c55e; color: #166534; }
        .badge-rent-on_trip { background: #e0f2fe; border-color: #3b82f6; color: #1d4ed8; }
        .badge-rent-completed { background: #ecfdf3; border-color: #22c55e; color: #166534; }
        .badge-rent-cancelled { background: #fee2e2; border-color: #f97373; color: #991b1b; }

        .badge-pay-unpaid { background: #fee2e2; border-color: #f97373; color: #991b1b; }
        .badge-pay-paid { background: #dcfce7; border-color: #22c55e; color: #166534; }
        .badge-pay-cancelled, .badge-pay-refunded { background: #fef3c7; border-color: #facc15; color: #92400e; }

        .rent-driver { font-size: 11px; color: #6b7280; margin-bottom: 4px; }

        .rent-update-form { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
        .rent-update-form select { border-radius: 999px; border: 1px solid #d1d5db; background: #ffffff; font-size: 11px; padding: 3px 8px; }
        .rent-update-form button { border-radius: 999px; border: none; background: linear-gradient(135deg, #2563eb, #4f46e5); color: #ffffff; font-size: 11px; padding: 4px 10px; cursor: pointer; }

        @media (max-width: 900px) {
            .rent-col-user, .rent-col-car, .rent-col-schedule, .rent-col-total, .rent-col-status { width: auto; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-layer-group"></i>
            <span>LaskarTrip Admin</span>
        </div>
        
        <ul class="nav-links">
            <li class="nav-item">
                <a href="#dashboard" class="active">
                    <i class="fa-solid fa-grid-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="#hotels-section">
                    <i class="fa-solid fa-hotel"></i> Kelola Hotel
                </a>
            </li>
            <li class="nav-item">
                <a href="#rent-section">
                    <i class="fa-solid fa-car"></i> Kelola Sewa Mobil
                </a>
            </li>
            <li class="nav-item" style="margin-top: auto;">
                <a href="index.php">
                    <i class="fa-solid fa-globe"></i> Website Utama
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php?role=admin" style="color: #ef4444;">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </li>
        </ul>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header" id="dashboard">
            <div class="header-title">
                <h1>Dashboard</h1>
                <p style="color: var(--text-muted); font-size: 13px;">Welcome back, Admin!</p>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <div class="name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
                    <div class="role">Super Admin</div>
                </div>
                <div class="user-avatar">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['admin_name'] ?? 'Admin') ?>&background=random" alt="Admin">
                </div>
            </div>
        </header>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-info">
                    <h3><?= $stats['total_hotel']; ?></h3>
                    <p>Total Hotel</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
            </div>
            
            <div class="stat-card green">
                <div class="stat-info">
                    <h3><?= $total_rent_orders; ?></h3>
                    <p>Total Sewa Mobil</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-car-side"></i></div>
            </div>
            
            <div class="stat-card yellow">
                <div class="stat-info">
                    <h3><?= number_format($stats['avg_rating'], 1); ?></h3>
                    <p>Rata-rata Rating</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
            </div>
            
            <div class="stat-card orange">
                <div class="stat-info">
                    <h3 style="font-size: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 150px;">
                        <?= htmlspecialchars(strtok($stats['top_kota'], '(')); ?>
                    </h3>
                    <p>Kota Terpopuler</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-map-location-dot"></i></div>
            </div>
        </div>

        <!-- Chart Section (Placeholder) -->
        <div class="chart-section">
            <div class="chart-header">
                <h3>Reservation Statistic</h3>
                <button class="btn btn-outline"><i class="fa-solid fa-download"></i> Report</button>
            </div>
            <div class="chart-placeholder">
                <i class="fa-solid fa-chart-area"></i>
                <p style="font-weight: 500;">Fitur untuk sementara belum tersedia</p>
                <p style="font-size: 12px;">Grafik statistik pemesanan akan ditampilkan di sini.</p>
            </div>
        </div>

        <!-- HOTEL MANAGEMENT SECTION -->
        <section id="hotels-section" class="content-section">
            <div class="section-header">
                <h3>Daftar Hotel</h3>
                <button onclick="openEditor()" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Tambah Hotel
                </button>
            </div>

            <!-- Filters -->
            <form method="get" class="filter-bar">
                <input type="text" name="q" placeholder="Cari Hotel..." class="filter-input" value="<?= htmlspecialchars($keyword); ?>">
                <select name="lokasi" class="filter-input">
                    <option value="">Semua Lokasi</option>
                    <?php foreach ($lokasi_options as $lok): ?>
                        <option value="<?= htmlspecialchars($lok); ?>" <?= $filter_lokasi === $lok ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($lok); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline">Filter</button>
            </form>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Hotel</th>
                            <th>Lokasi</th>
                            <th>Harga/Malam</th>
                            <th>Rating</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($hotels_page) === 0): ?>
                            <tr><td colspan="6" style="text-align:center;">Data tidak ditemukan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($hotels_page as $hotel): ?>
                            <tr>
                                <td>#<?= $hotel['id'] ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($hotel['nama_hotel']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= (int)$hotel['jumlah_review'] ?> Ulasan</div>
                                </td>
                                <td><?= htmlspecialchars($hotel['lokasi']) ?></td>
                                <td><?= format_rupiah($hotel['harga_per_malam']) ?></td>
                                <td><span style="color: #f59e0b;"><i class="fa-solid fa-star"></i> <?= number_format($hotel['rating'], 1) ?></span></td>
                                <td>
                                    <!-- Tombol Edit yang memanggil JS -->
                                    <button onclick='editHotel(<?= json_encode($hotel) ?>)' class="btn btn-warning" style="padding: 4px 8px; font-size: 11px;">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    
                                    <a href="admin.php?hapus=<?= $hotel['id'] ?>" onclick="return confirm('Hapus hotel ini?')" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Hotel -->
            <?php if ($hotel_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $hotel_pages; $i++): ?>
                    <a href="admin.php?<?= $hotel_query_base . 'hotel_page=' . $i; ?>" 
                       class="page-link <?= $i === $hotel_page ? 'active' : ''; ?>">
                        <?= $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- RENT CAR MANAGEMENT SECTION -->
        <section id="rent-section" class="content-section">
            <div class="section-header">
                <h3>Booking Sewa Mobil</h3>
            </div>

            <?php if (!empty($rent_flash)): ?>
                <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 13px;">
                    <?= htmlspecialchars($rent_flash); ?>
                </div>
            <?php endif; ?>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Mobil</th>
                            <th>Jadwal</th>
                            <th>Total</th>
                            <th>Status Sewa</th>
                            <th>Pembayaran</th>
                            <th>Driver</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rent_orders_page)): ?>
                            <tr><td colspan="8" style="text-align:center;">Belum ada pesanan sewa mobil.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rent_orders_page as $row): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($row['user_nama']) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= htmlspecialchars($row['user_email']) ?></div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nama_mobil']) ?><br>
                                    <small><?= htmlspecialchars($row['brand']) ?></small>
                                </td>
                                <td>
                                    <small>Mulai: <?= date('d/m/y H:i', strtotime($row['start_datetime'])) ?></small><br>
                                    <small>Selesai: <?= date('d/m/y H:i', strtotime($row['end_datetime'])) ?></small>
                                </td>
                                <td><?= format_rupiah($row['total_harga']) ?></td>
                                <td>
                                    <?php 
                                        $rs = $row['rent_status'];
                                        $badgeClass = ($rs == 'completed' || $rs == 'dikonfirmasi') ? 'bg-success' : (($rs == 'cancelled') ? 'bg-danger' : 'bg-pending');
                                    ?>
                                    <span class="status-badge <?= $badgeClass ?>"><?= strtoupper($rs) ?></span>
                                </td>
                                <td>
                                    <?php 
                                        $ps = $row['payment_status'];
                                        $payClass = ($ps == 'paid') ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <span class="status-badge <?= $payClass ?>"><?= strtoupper($ps) ?></span>
                                </td>
                                <td>
                                    <!-- Form Update Rent -->
                                    <form method="post" style="display:flex; gap:5px; flex-direction:column;">
                                        <input type="hidden" name="update_rent" value="1">
                                        <input type="hidden" name="rent_id" value="<?= $row['rent_id'] ?>">
                                        <input type="hidden" name="rent_status" value="<?= $row['rent_status'] ?>">
                                        <input type="hidden" name="payment_status" value="<?= $row['payment_status'] ?>">
                                        
                                        <select name="driver_id" style="font-size:11px; padding:2px; max-width: 120px;">
                                            <option value="">- Driver -</option>
                                            <?php foreach ($drivers as $drv): ?>
                                                <option value="<?= $drv['driver_id'] ?>" <?= ($row['driver_id'] == $drv['driver_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($drv['nama_driver']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                </td>
                                <td>
                                        <button type="submit" class="btn btn-primary" style="padding: 4px 8px; font-size: 11px;">
                                            <i class="fa-solid fa-save"></i>
                                        </button>
                                    </form>
                                    <!-- Status Update Form Dropdown -->
                                    <form method="post" style="margin-top: 5px;">
                                        <input type="hidden" name="update_rent" value="1">
                                        <input type="hidden" name="rent_id" value="<?= $row['rent_id'] ?>">
                                        <input type="hidden" name="driver_id" value="<?= $row['driver_id'] ?>">
                                        <input type="hidden" name="payment_status" value="<?= $row['payment_status'] ?>">
                                        
                                        <select name="rent_status" style="font-size:11px; padding:2px;" onchange="this.form.submit()">
                                            <option value="pending" <?= $row['rent_status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="dikonfirmasi" <?= $row['rent_status'] == 'dikonfirmasi' ? 'selected' : '' ?>>Confirm</option>
                                            <option value="completed" <?= $row['rent_status'] == 'completed' ? 'selected' : '' ?>>Selesai</option>
                                            <option value="cancelled" <?= $row['rent_status'] == 'cancelled' ? 'selected' : '' ?>>Batal</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Rent -->
            <?php if ($rent_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $rent_pages; $i++): ?>
                    <a href="admin.php?rent_page=<?= $i; ?>" class="page-link <?= $i === $rent_page ? 'active' : ''; ?>">
                        <?= $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </section>

    </main>

    <!-- Modal Editor (Tambah/Edit Hotel) -->
    <div id="editorOverlay" class="modal-overlay">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 id="modalTitle" style="font-size:18px; font-weight:700;">Tambah Hotel Baru</h3>
                <button onclick="closeEditor()" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
            </div>
            
            <form id="hotelForm" method="post" action="admin.php">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="hotelId" value="">

                <div class="form-group">
                    <label>Nama Hotel</label>
                    <input type="text" name="nama_hotel" id="namaHotel" required>
                </div>
                <div class="form-group">
                    <label>Lokasi</label>
                    <input type="text" name="lokasi" id="lokasiHotel" required>
                </div>
                <div class="form-group">
                    <label>Harga per Malam (Rp)</label>
                    <input type="text" name="harga_per_malam" id="hargaHotel" required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Rating</label>
                        <input type="number" step="0.1" min="0" max="5" name="rating" id="ratingHotel" value="4.5">
                    </div>
                    <div class="form-group">
                        <label>Jumlah Review</label>
                        <input type="number" name="jumlah_review" id="reviewHotel" value="0">
                    </div>
                </div>

                <div style="text-align:right; margin-top:20px;">
                    <button type="button" onclick="closeEditor()" class="btn btn-outline" style="margin-right:10px;">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditor() {
            // Reset form ke mode "Tambah"
            document.getElementById('modalTitle').innerText = "Tambah Hotel Baru";
            document.getElementById('formAction').value = "create";
            document.getElementById('hotelId').value = "";
            document.getElementById('namaHotel').value = "";
            document.getElementById('lokasiHotel').value = "";
            document.getElementById('hargaHotel').value = "";
            document.getElementById('ratingHotel').value = "4.5";
            document.getElementById('reviewHotel').value = "0";
            
            // Tampilkan modal tanpa scroll ke atas
            document.getElementById('editorOverlay').style.display = 'flex';
        }

        function editHotel(hotel) {
            // Isi form dengan data hotel (Mode Edit)
            document.getElementById('modalTitle').innerText = "Edit Hotel";
            document.getElementById('formAction').value = "update";
            document.getElementById('hotelId').value = hotel.id;
            document.getElementById('namaHotel').value = hotel.nama_hotel;
            document.getElementById('lokasiHotel').value = hotel.lokasi;
            document.getElementById('hargaHotel').value = hotel.harga_per_malam;
            document.getElementById('ratingHotel').value = hotel.rating;
            document.getElementById('reviewHotel').value = hotel.jumlah_review;

            // Tampilkan modal
            document.getElementById('editorOverlay').style.display = 'flex';
        }

        function closeEditor() {
            document.getElementById('editorOverlay').style.display = 'none';
        }
    </script>
</body>
</html>