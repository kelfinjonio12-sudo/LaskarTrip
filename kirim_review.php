<?php
session_start();
require 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$user_id  = $_SESSION['user_id'] ?? 0;
$hotel_id = isset($_POST['hotel_id']) ? (int)$_POST['hotel_id'] : 0;
$redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : 'index.php';

$rating   = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$komentar = trim($_POST['komentar'] ?? '');
$nama_tamu= trim($_POST['nama_tamu'] ?? '');

if ($hotel_id <= 0) {
    header('Location: index.php');
    exit;
}

// pastikan redirect relatif (keamanan sederhana)
if (strpos($redirect, 'http') === 0) {
    $redirect = 'index.php';
}

// jika belum login
if ($user_id <= 0) {
    header('Location: ' . $redirect . '&review=nologin');
    exit;
}

// validasi rating & komentar
if ($rating < 1 || $rating > 5 || $komentar === '') {
    header('Location: ' . $redirect . '&review=invalid');
    exit;
}

// cek apakah user punya booking di hotel ini
$sqlCheck = "
    SELECT COUNT(*) AS jml
    FROM bookings
    WHERE user_id = ?
      AND hotel_id = ?
      AND status IN ('pending','paid','success','completed')
";
if (!$stmtC = mysqli_prepare($conn, $sqlCheck)) {
    header('Location: ' . $redirect . '&review=error');
    exit;
}

mysqli_stmt_bind_param($stmtC, "ii", $user_id, $hotel_id);
mysqli_stmt_execute($stmtC);
$resC = mysqli_stmt_get_result($stmtC);
$rowC = mysqli_fetch_assoc($resC);
mysqli_stmt_close($stmtC);

if ((int)$rowC['jml'] <= 0) {
    header('Location: ' . $redirect . '&review=nobooking');
    exit;
}

// siapkan data untuk disimpan ke tabel hotel_reviews
if ($nama_tamu === '') {
    $nama_tamu = 'Tamu LaskarTrip';
}

$nama  = mysqli_real_escape_string($conn, $nama_tamu);
$komen = mysqli_real_escape_string($conn, $komentar);
$rate  = (int)$rating;

// opsional: jika ingin 1 review saja per user per hotel,
// bisa cek berdasarkan nama + hotel (karena tabel belum simpan user_id)
$sqlFind = "SELECT id FROM hotel_reviews WHERE hotel_id = ? AND nama_tamu = ? LIMIT 1";
if ($stmtF = mysqli_prepare($conn, $sqlFind)) {
    mysqli_stmt_bind_param($stmtF, "is", $hotel_id, $nama_tamu);
    mysqli_stmt_execute($stmtF);
    $resF = mysqli_stmt_get_result($stmtF);
    $existing = mysqli_fetch_assoc($resF);
    mysqli_stmt_close($stmtF);
} else {
    $existing = null;
}

if ($existing) {
    // update ulasan lama
    $rid = (int)$existing['id'];
    $sqlUpdate = "
        UPDATE hotel_reviews
        SET rating = $rate,
            komentar = '$komen',
            created_at = NOW()
        WHERE id = $rid
    ";
    $ok = mysqli_query($conn, $sqlUpdate);
} else {
    // insert ulasan baru
    $sqlInsert = "
        INSERT INTO hotel_reviews (hotel_id, nama_tamu, rating, komentar)
        VALUES ($hotel_id, '$nama', $rate, '$komen')
    ";
    $ok = mysqli_query($conn, $sqlInsert);
}

if (!$ok) {
    // kalau error SQL
    // echo mysqli_error($conn); exit; // bisa dipakai debug
    header('Location: ' . $redirect . '&review=error');
    exit;
}

// sukses
header('Location: ' . $redirect . '&review=ok');
exit;
