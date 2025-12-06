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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action          = $_POST['action'] ?? 'create';
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

// Data untuk form edit (jika ada ?edit=id)
$editHotel = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    foreach ($hotels as $h) {
        if ((int) $h['id'] === $edit_id) {
            $editHotel = $h;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Laskar Trip</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 (Untuk Popup Profesional) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --bg-body: #f3f4f6;
            --bg-sidebar: #ffffff;
            --bg-card: #ffffff;
            --text-main: #333333;
            --text-muted: #888888;
            --primary: #2563eb; /* Biru utama */
            --accent-blue: #3b82f6;
            --accent-green: #22c55e;
            --accent-yellow: #f59e0b;
            --accent-orange: #f97316;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        
        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: var(--bg-sidebar);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 20px;
            z-index: 100;
        }

        .brand {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand span { color: #1e293b; }

        .menu-list li { margin-bottom: 8px; }
        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: 0.3s;
            cursor: pointer;
        }
        .menu-link:hover, .menu-link.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        .menu-link i { width: 20px; text-align: center; }

        .menu-title {
            font-size: 11px;
            text-transform: uppercase;
            color: #aaa;
            margin: 20px 0 10px 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 20px 30px;
        }

        /* TOP HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header-title h1 { font-size: 24px; font-weight: 700; color: #1e293b; }
        .header-tools { display: flex; align-items: center; gap: 20px; }
        
        .search-box {
            background: white;
            border-radius: 50px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
            width: 300px;
        }
        .search-box input { border: none; outline: none; width: 100%; margin-left: 10px; font-size: 13px; }
        .search-box i { color: #aaa; }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-img {
            width: 40px;
            height: 40px;
            background: #cbd5e1;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: #fff;
        }
        .user-info { font-size: 13px; text-align: right; }
        .user-info b { display: block; color: #1e293b; }
        .user-info span { color: var(--text-muted); font-size: 11px; }

        /* DASHBOARD CARDS */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }

        /* Card Colors Gradients matching image */
        .card-blue { background: linear-gradient(135deg, #60a5fa, #2563eb); }
        .card-green { background: linear-gradient(135deg, #4ade80, #22c55e); }
        .card-yellow { background: linear-gradient(135deg, #fbbf24, #d97706); }
        .card-orange { background: linear-gradient(135deg, #fb923c, #ea580c); }

        .stat-info h3 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-info p { font-size: 13px; font-weight: 500; opacity: 0.9; }
        .stat-icon { font-size: 32px; opacity: 0.4; }

        /* CHART SECTION (Simulated) */
        .chart-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .chart-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .chart-title h3 { font-size: 18px; color: #1e293b; }
        .chart-title span { font-size: 12px; color: #888; }
        
        /* Simulated Chart Line using CSS Gradient/Mask or SVG */
        .chart-placeholder {
            width: 100%;
            height: 250px;
            background: linear-gradient(to bottom, rgba(37,99,235,0.05), transparent);
            position: relative;
            border-bottom: 1px solid #eee;
            border-left: 1px solid #eee;
        }
        /* Simple SVG Line Chart representation */
        .svg-chart { width: 100%; height: 100%; }
        .svg-path { fill: none; stroke: #3b82f6; stroke-width: 3; stroke-linecap: round; filter: drop-shadow(0 4px 6px rgba(59,130,246,0.3)); }
        .svg-area { fill: rgba(59,130,246,0.1); stroke: none; }

        /* DATA TABLE SECTION */
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn-add {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-add:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(37,99,235,0.3); }

        table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        th { text-align: left; color: #888; font-size: 12px; font-weight: 500; padding: 0 15px 10px; }
        td { background: #f8fafc; padding: 15px; font-size: 13px; color: #333; border-top: 1px solid transparent; border-bottom: 1px solid transparent; }
        
        tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; text-align: center; }
        tr:hover td { background: #f1f5f9; }

        .hotel-name { font-weight: 600; color: #1e293b; display: block; }
        .hotel-loc { font-size: 11px; color: #888; }
        .rating-badge { background: #fef3c7; color: #d97706; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .price-tag { font-weight: 600; color: var(--primary); }

        .action-btn {
            border: none;
            background: white;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            cursor: pointer;
            color: #888;
            transition: 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .action-btn.edit:hover { color: #f59e0b; background: #fffbeb; }
        .action-btn.delete:hover { color: #ef4444; background: #fef2f2; }

        /* EDITOR MODAL (Overlay) */
        .editor-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .editor-modal {
            background: white;
            width: 500px;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 18px; font-weight: 700; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #888; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #555; }
        .form-control { width: 100%; padding: 10px 15px; border-radius: 10px; border: 1px solid #ddd; background: #f9fafb; font-size: 13px; }
        .form-control:focus { outline: none; border-color: var(--primary); background: white; }
        
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-cancel { background: #f1f5f9; color: #64748b; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }

        @media (max-width: 900px) {
            .sidebar { width: 70px; padding: 20px 10px; }
            .brand span, .menu-link span, .menu-title { display: none; }
            .menu-link { justify-content: center; padding: 12px; }
            .main-content { margin-left: 70px; }
            .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR NAVIGATION -->
    <nav class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-hotel"></i>
            <span>LaskarTrip</span>
        </div>

        <div class="menu-title">Main Menu</div>
        <ul class="menu-list">
            <li>
                <a href="admin.php" class="menu-link active">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="#" class="menu-link">
                    <i class="fa-regular fa-user"></i>
                    <span>Guest (N/A)</span>
                </a>
            </li>
            <li>
                <a href="#" class="menu-link">
                    <i class="fa-solid fa-door-open"></i>
                    <span>Room (N/A)</span>
                </a>
            </li>
            <li>
                <a href="#" class="menu-link">
                    <i class="fa-solid fa-star-half-stroke"></i>
                    <span>Reviews (N/A)</span>
                </a>
            </li>
        </ul>

        <div class="menu-title">Other</div>
        <ul class="menu-list">
            <li>
                <a href="index.php" class="menu-link">
                    <i class="fa-solid fa-globe"></i>
                    <span>Lihat Website</span>
                </a>
            </li>
            <li>
                <!-- LOGOUT DENGAN KONFIRMASI (MENGIRIM PARAMETER ROLE=ADMIN) -->
                <a onclick="confirmLogout()" class="menu-link" style="color: #ef4444;">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        
        <!-- HEADER -->
        <header class="header">
            <div class="header-title">
                <h1>Dashboard</h1>
                <p style="font-size:13px; color:#888;">Selamat datang kembali, Admin!</p>
            </div>
            <div class="header-tools">
                <form class="search-box" method="GET">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Cari data hotel..." value="<?= htmlspecialchars($keyword) ?>">
                </form>
                <div class="user-profile">
                    <div class="user-info">
                        <b><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></b>
                        <span>Super Admin</span>
                    </div>
                    <div class="user-img">A</div>
                </div>
            </div>
        </header>

        <!-- STATS CARDS -->
        <div class="dashboard-grid">
            <!-- Card 1: Total Hotel -->
            <div class="stat-card card-blue">
                <div class="stat-info">
                    <h3><?= $stats['total_hotel']; ?></h3>
                    <p>Total Hotel</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
            </div>

            <!-- Card 2: Rating Rata-rata -->
            <div class="stat-card card-green">
                <div class="stat-info">
                    <h3><?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '0'; ?></h3>
                    <p>Avg Rating</p>
                </div>
                <div class="stat-icon"><i class="fa-regular fa-calendar-check"></i></div>
            </div>

            <!-- Card 3: Kota Top (Orange) -->
            <div class="stat-card card-yellow">
                <div class="stat-info">
                    <h3 style="font-size:18px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px;">
                        <?= explode(' ', $stats['top_kota'])[0]; ?>
                    </h3>
                    <p>Kota Populer</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-map-location-dot"></i></div>
            </div>

            <!-- Card 4: Avg Harga (Red/Orange) -->
            <div class="stat-card card-orange">
                <div class="stat-info">
                    <h3 style="font-size:18px;"><?= $stats['avg_harga'] ? 'Rp' . number_format($stats['avg_harga']/1000, 0) . 'k' : '0'; ?></h3>
                    <p>Avg Harga</p>
                </div>
                <div class="stat-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            </div>
        </div>

        <!-- CHART SECTION (Visual Placeholder to match Image) -->
        <div class="chart-section">
            <div class="chart-header">
                <div class="chart-title">
                    <h3>Reservation Statistic</h3>
                    <span>Data statistik performa hotel (Visualisasi)</span>
                </div>
                <select class="form-control" style="width: auto; padding: 5px 10px;">
                    <option>Yearly</option>
                    <option>Monthly</option>
                </select>
            </div>
            <div class="chart-placeholder">
                <!-- Simple SVG Curve to simulate chart -->
                <svg class="svg-chart" viewBox="0 0 500 150" preserveAspectRatio="none">
                    <path class="svg-area" d="M0,150 L0,100 Q50,50 100,80 T200,60 T300,100 T400,40 T500,80 L500,150 Z" />
                    <path class="svg-path" d="M0,100 Q50,50 100,80 T200,60 T300,100 T400,40 T500,80" />
                </svg>
            </div>
        </div>

        <!-- DATA LIST (TABLE) -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <h3>Data Hotel</h3>
                    <p style="font-size:12px; color:#888;">Kelola listing penginapan Laskar Trip</p>
                </div>
                <button class="btn-add" onclick="toggleEditor()">
                    <i class="fa-solid fa-plus"></i> Tambah Hotel
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Hotel & Lokasi</th>
                        <th>Harga / Malam</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($hotels) === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px;">Tidak ada data hotel.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($hotels as $hotel): ?>
                        <tr>
                            <td style="text-align:center;"><?= $no++; ?></td>
                            <td>
                                <span class="hotel-name"><?= htmlspecialchars($hotel['nama_hotel']); ?></span>
                                <span class="hotel-loc"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($hotel['lokasi']); ?></span>
                            </td>
                            <td>
                                <span class="price-tag"><?= format_rupiah($hotel['harga_per_malam']); ?></span>
                            </td>
                            <td>
                                <span class="rating-badge">⭐ <?= number_format($hotel['rating'], 1); ?></span>
                            </td>
                            <td><?= (int) $hotel['jumlah_review']; ?> Ulasan</td>
                            <td>
                                <a href="admin.php?edit=<?= (int) $hotel['id']; ?>" class="action-btn edit" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="admin.php?hapus=<?= (int) $hotel['id']; ?>" class="action-btn delete" title="Hapus" onclick="return confirm('Hapus hotel ini?');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- EDITOR MODAL (TAMBAH / EDIT) -->
    <div id="editorOverlay" class="editor-overlay" style="<?= $editHotel ? 'display:flex;' : '' ?>">
        <div class="editor-modal">
            <form method="post" action="admin.php">
                <div class="modal-header">
                    <h3><?= $editHotel ? 'Edit Hotel' : 'Tambah Hotel Baru'; ?></h3>
                    <button type="button" class="close-btn" onclick="toggleEditor()">×</button>
                </div>
                
                <input type="hidden" name="action" value="<?= $editHotel ? 'update' : 'create'; ?>">
                <?php if ($editHotel): ?>
                    <input type="hidden" name="id" value="<?= (int)$editHotel['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Nama Hotel</label>
                    <input type="text" name="nama_hotel" class="form-control" 
                           value="<?= $editHotel ? htmlspecialchars($editHotel['nama_hotel']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Lokasi (Kota)</label>
                    <input type="text" name="lokasi" class="form-control"
                           value="<?= $editHotel ? htmlspecialchars($editHotel['lokasi']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Harga per Malam (Rp)</label>
                    <input type="text" name="harga_per_malam" class="form-control"
                           value="<?= $editHotel ? number_format($editHotel['harga_per_malam'], 0, ',', '.') : ''; ?>" required>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Rating (0-5)</label>
                        <input type="number" step="0.1" max="5" name="rating" class="form-control"
                               value="<?= $editHotel ? htmlspecialchars($editHotel['rating']) : '4.5'; ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Jml Review</label>
                        <input type="number" name="jumlah_review" class="form-control"
                               value="<?= $editHotel ? htmlspecialchars($editHotel['jumlah_review']) : '0'; ?>">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="toggleEditor()">Batal</button>
                    <button type="submit" class="btn-save">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Toggle
        function toggleEditor() {
            const overlay = document.getElementById('editorOverlay');
            if (overlay.style.display === 'flex') {
                overlay.style.display = 'none';
                // Bersihkan URL jika menutup modal edit agar tidak reopen saat refresh
                const url = new URL(window.location.href);
                if (url.searchParams.has('edit')) {
                    url.searchParams.delete('edit');
                    window.history.pushState({}, '', url);
                }
            } else {
                overlay.style.display = 'flex';
            }
        }

        // Konfirmasi Logout dengan SweetAlert (Mengarahkan ke parameter role=admin)
        function confirmLogout() {
            Swal.fire({
                title: 'Yakin ingin keluar?',
                text: "Sesi Admin akan diakhiri.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php?role=admin';
                }
            })
        }
    </script>
</body>
</html>