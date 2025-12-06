<?php
session_start();

// Ambil parameter role dari URL, defaultnya 'user'
$role = $_GET['role'] ?? 'user';
$redirectTarget = 'login.php';

if ($role === 'admin') {
    // Hapus spesifik session milik admin
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    
    $redirectTarget = 'admin_login.php';
    $messageTitle = 'Admin Logout';
} else {
    // Hapus spesifik session milik user
    unset($_SESSION['user_id']);
    unset($_SESSION['nama_lengkap']);
    unset($_SESSION['username']);
    
    // Default redirect user
    $redirectTarget = 'login.php';
    $messageTitle = 'Berhasil Logout';
}

// Opsional: Jika tidak ada sesi penting yang tersisa, baru destroy total
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    session_destroy();
}
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
            text: 'Anda telah keluar dari sistem dengan aman.',
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