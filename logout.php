<?php
session_start();

// Ambil parameter role dari URL, defaultnya 'user'
$role = $_GET['role'] ?? 'user';
$redirectTarget = 'login.php';
$messageTitle = 'Berhasil Logout';

if ($role === 'admin') {
    // Hapus HANYA session milik admin
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    
    $redirectTarget = 'admin_login.php';
    $messageTitle = 'Admin Logout';
} else {
    // Hapus HANYA session milik user
    unset($_SESSION['user_id']);
    unset($_SESSION['nama_lengkap']);
    unset($_SESSION['username']);
    
    $redirectTarget = 'login.php';
    $messageTitle = 'Berhasil Logout';
}

// CATATAN PENTING:
// Kita TIDAK menggunakan session_destroy() disini.
// session_destroy() akan menghapus semua data sesi (termasuk login user di tab lain).
// Dengan hanya menggunakan unset(), kita memisahkan logout Admin dan User.

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Logout - Laskar Trip</title>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; }
    </style>
</head>
<body>
    <script>
        Swal.fire({
            title: '<?= $messageTitle ?>!',
            text: 'Anda telah keluar dari sesi ini.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
            allowOutsideClick: false
        }).then(() => {
            window.location.href = '<?= $redirectTarget; ?>';
        });
    </script>
</body>
</html>