<?php
session_start();

// Cek apakah user sudah login atau belum
if (!isset($_SESSION['status_login'])) {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';

// Cek apakah tombol submit sudah ditekan
if (isset($_POST['submit'])) {
    // Ambil data dari form
    $nama     = htmlspecialchars($_POST['nama_hotel']);
    $lokasi   = htmlspecialchars($_POST['lokasi']);
    $harga    = htmlspecialchars($_POST['harga']);
    $rating   = htmlspecialchars($_POST['rating']);
    $deskripsi = htmlspecialchars($_POST['deskripsi']);
    
    // Data default
    $review   = 0; 
    $gambar   = 'default.jpg';

    // Query Insert ke Database
    $query = "INSERT INTO hotels (nama_hotel, lokasi, harga_per_malam, rating, jumlah_review, gambar, deskripsi)
              VALUES ('$nama', '$lokasi', '$harga', '$rating', '$review', '$gambar', '$deskripsi')";

    if (mysqli_query($conn, $query)) {
        echo "<script>
                alert('Data berhasil ditambahkan!');
                document.location.href = 'admin.php';
              </script>";
    } else {
        echo "<script>alert('Gagal menambahkan data!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Hotel Baru</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; padding: 40px; display: flex; justify-content: center; }
        .card { background: white; padding: 40px; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #1f2937; margin-bottom: 24px; text-align: center; }
        
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #374151; }
        input, textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit;}
        textarea { height: 100px; resize: vertical; }
        
        .btn-submit { width: 100%; background: #2563eb; color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn-submit:hover { background: #1d4ed8; }
        .link-back { display: block; text-align: center; margin-top: 16px; font-size: 13px; color: #6b7280; text-decoration: none; }
    </style>
</head>
<body>

<div class="card">
    <h2>Tambah Hotel Baru</h2>
    <form action="" method="post">
        <div class="form-group">
            <label>Nama Hotel</label>
            <input type="text" name="nama_hotel" required placeholder="Contoh: Grand Indonesia Hotel">
        </div>
        
        <div class="form-group">
            <label>Lokasi (Kota)</label>
            <input type="text" name="lokasi" required placeholder="Contoh: Jakarta Pusat">
        </div>

        <div class="form-group">
            <label>Harga per Malam (Rp)</label>
            <input type="number" name="harga" required placeholder="Contoh: 500000">
        </div>

        <div class="form-group">
            <label>Rating Awal (1.0 - 5.0)</label>
            <input type="number" step="0.1" max="5" name="rating" required placeholder="Contoh: 4.5">
        </div>

        <div class="form-group">
            <label>Deskripsi Singkat</label>
            <textarea name="deskripsi" required placeholder="Jelaskan fasilitas dan keunggulan hotel..."></textarea>
        </div>

        <button type="submit" name="submit" class="btn-submit">Simpan Hotel</button>
        <a href="admin.php" class="link-back">Batal</a>
    </form>
</div>

</body>
</html>