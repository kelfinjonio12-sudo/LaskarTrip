<?php
session_start();

// Cek apakah user sudah login atau belum
if (!isset($_SESSION['status_login'])) {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';

$id = $_GET['id'];

// Query Hapus
$query = "DELETE FROM hotels WHERE id = $id";

if (mysqli_query($conn, $query)) {
    echo "<script>
            alert('Data berhasil dihapus!');
            document.location.href = 'admin.php';
          </script>";
} else {
    echo "<script>
            alert('Gagal menghapus data!');
            document.location.href = 'admin.php';
          </script>";
}
?>