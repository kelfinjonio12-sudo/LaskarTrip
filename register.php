<?php
session_start();
require 'koneksi.php';

$errors = [];

// Data input user (untuk mengisi kembali form jika ada error)
$old_nama     = '';
$old_username = '';
$old_email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';

    // Simpan input lama agar user tidak perlu ketik ulang semua
    $old_nama     = htmlspecialchars($nama_lengkap);
    $old_username = htmlspecialchars($username);
    $old_email    = htmlspecialchars($email);

    // --- VALIDASI ---
    if ($nama_lengkap === '' || $username === '' || $email === '' || $password === '' || $password2 === '') {
        $errors[] = 'Semua field wajib diisi.';
    }

    if (!preg_match('/^[a-zA-Z0-9_.-]{3,20}$/', $username)) {
        $errors[] = 'Username hanya boleh huruf, angka, titik, strip (3–20 karakter).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    if ($password !== $password2) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    // --- CEK DUPLIKAT ---
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'Username atau email sudah terdaftar. Silakan login.';
        }
        mysqli_stmt_close($stmt);
    }

    // --- PROSES SIMPAN ---
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($conn,
            "INSERT INTO users (nama_lengkap, username, email, password) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ssss", $nama_lengkap, $username, $email, $hash);

        if (mysqli_stmt_execute($stmt)) {
            // Auto login setelah sukses daftar
            $_SESSION['user_id']       = mysqli_insert_id($conn);
            $_SESSION['nama_lengkap']  = $nama_lengkap;
            $_SESSION['username']      = $username;

            header("Location: index.php");
            exit;
        } else {
            $errors[] = 'Gagal menyimpan ke database. Silakan coba lagi.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Daftar Akun - Laskar Trip</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --text-main: #0f172a;
      --text-muted: #64748b;
      --bg-input: #f1f5f9;
      --border-input: #e2e8f0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', sans-serif;
      height: 100vh;
      width: 100%;
      background: #fff;
      overflow: hidden; /* Mencegah scroll pada level body */
    }

    .login-container {
      display: grid;
      grid-template-columns: 1.2fr 1fr; 
      height: 100%;
    }

    /* === BAGIAN KIRI (GAMBAR) === */
    .left-section {
      position: relative;
      background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?q=80&w=2000&auto=format&fit=crop');
      background-size: cover;
      background-position: center;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 40px;
      color: white;
    }

    .left-section::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.1) 50%, rgba(0,0,0,0.6) 100%);
      z-index: 1;
    }

    .brand-top, .content-bottom {
      position: relative;
      z-index: 2;
    }

    .brand-top {
      font-size: 24px;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    .content-bottom h2 {
      font-size: 42px;
      font-weight: 700;
      margin-bottom: 12px;
      line-height: 1.1;
    }

    .content-bottom p {
      font-size: 16px;
      color: rgba(255, 255, 255, 0.9);
      max-width: 80%;
      margin-bottom: 24px;
      line-height: 1.5;
    }

    .footer-links {
      display: flex;
      gap: 20px;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.7);
    }
    .footer-links a { color: inherit; text-decoration: none; }
    .footer-links a:hover { text-decoration: underline; color: white; }

    /* === BAGIAN KANAN (FORM) === */
    .right-section {
      background: #ffffff;
      display: flex;
      flex-direction: column;
      align-items: center;
      /* FIX: Hapus justify-content: center; agar tidak memotong konten atas saat di-scroll */
      /* justify-content: center; */ 
      padding: 40px;
      overflow-y: auto; /* Mengizinkan scroll vertikal */
    }

    .form-wrapper {
      width: 100%;
      max-width: 420px;
      padding: 20px 0;
      /* FIX: Gunakan margin auto agar vertikal center jika muat, tapi aman jika overflow */
      margin: auto; 
    }

    .form-header {
      margin-bottom: 28px;
    }

    .form-header h1 {
      font-size: 28px;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .form-header p {
      color: var(--text-muted);
      font-size: 14px;
    }

    /* ERROR BOX */
    .error-box {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 12px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 20px;
    }
    .error-box ul { padding-left: 20px; margin: 0; }

    /* FORM FIELDS */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 6px;
    }

    .input-wrapper {
      position: relative;
    }

    .form-control {
      width: 100%;
      padding: 11px 14px;
      background: var(--bg-input);
      border: 1px solid transparent;
      border-radius: 10px;
      font-size: 14px;
      color: var(--text-main);
      transition: all 0.2s;
      font-family: inherit;
    }

    .form-control:focus {
      outline: none;
      background: #fff;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    /* Toggle Password Eye */
    .toggle-password {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      cursor: pointer;
      font-size: 14px;
    }

    /* CHECKBOX TERMS */
    .terms-check {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 24px;
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.4;
    }
    .terms-check input {
      margin-top: 3px;
      accent-color: var(--primary);
      width: 16px;
      height: 16px;
      cursor: pointer;
    }
    .terms-check a {
      color: var(--text-main);
      font-weight: 600;
      text-decoration: none;
    }
    .terms-check a:hover { text-decoration: underline; }

    /* BUTTONS */
    .btn-submit {
      width: 100%;
      padding: 14px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-submit:hover {
      background: var(--primary-dark);
    }

    .login-text {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: var(--text-muted);
    }
    .login-text a {
      color: var(--text-main);
      font-weight: 700;
      text-decoration: none;
    }
    .login-text a:hover { text-decoration: underline; }

    /* SOCIAL */
    .divider {
      display: flex;
      align-items: center;
      margin: 24px 0;
      color: var(--text-muted);
      font-size: 12px;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #e2e8f0;
    }
    .divider span { padding: 0 10px; }

    .social-buttons {
      display: flex;
      gap: 12px;
    }

    .btn-social {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
      color: var(--text-main);
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-social:hover { background: #f8fafc; border-color: #cbd5e1; }
    .btn-social.google { flex: 2; }

    /* RESPONSIVE */
    @media (max-width: 900px) {
      /* Ubah height 100vh ke auto agar bisa scroll penuh di mobile */
      body { overflow: auto; height: auto; }
      .login-container { grid-template-columns: 1fr; height: auto; min-height: 100vh; }
      .left-section { display: none; }
      .right-section { padding: 30px 20px; height: auto; overflow: visible; }
    }
  </style>
</head>
<body>

<div class="login-container">
  
  <!-- LEFT SIDE: IMAGE -->
  <div class="left-section">
    <div class="brand-top">
      Laskar Trip
    </div>
    
    <div class="content-bottom">
      <h2>Start your journey.</h2>
      <p>Buat akun baru untuk mulai menjelajahi destinasi impian, memesan hotel terbaik, dan sewa kendaraan dengan mudah.</p>
      
      <div class="footer-links">
        <span>© <?= date('Y') ?> Laskar Trip.</span>
        <a href="#">Privacy Policy</a>
        <a href="#">Terms</a>
      </div>
    </div>
  </div>

  <!-- RIGHT SIDE: REGISTER FORM -->
  <div class="right-section">
    <div class="form-wrapper">
      
      <div class="form-header">
        <h1>Create an account</h1>
        <p>Bergabunglah dengan komunitas traveler kami hari ini.</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form action="" method="POST">
        
        <!-- Nama Lengkap -->
        <div class="form-group">
          <label for="nama_lengkap">Nama Lengkap</label>
          <input 
            type="text" 
            id="nama_lengkap" 
            name="nama_lengkap" 
            class="form-control" 
            placeholder="Contoh: Budi Santoso"
            value="<?= $old_nama ?>"
            required
          >
        </div>

        <div class="form-row">
          <!-- Username -->
          <div class="form-group">
            <label for="username">Username</label>
            <input 
              type="text" 
              id="username" 
              name="username" 
              class="form-control" 
              placeholder="budisantoso"
              value="<?= $old_username ?>"
              required
            >
          </div>
          
          <!-- Email -->
          <div class="form-group">
            <label for="email">Email</label>
            <input 
              type="email" 
              id="email" 
              name="email" 
              class="form-control" 
              placeholder="budi@example.com"
              value="<?= $old_email ?>"
              required
            >
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-control" 
              placeholder="Minimal 6 karakter"
              required
            >
            <i class="fa-regular fa-eye-slash toggle-password" onclick="togglePass('password', this)"></i>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label for="password2">Konfirmasi Password</label>
          <div class="input-wrapper">
            <input 
              type="password" 
              id="password2" 
              name="password2" 
              class="form-control" 
              placeholder="Ulangi password"
              required
            >
            <i class="fa-regular fa-eye-slash toggle-password" onclick="togglePass('password2', this)"></i>
          </div>
        </div>

        <!-- Terms Checkbox -->
        <div class="terms-check">
          <input type="checkbox" id="terms" required>
          <label for="terms">
            Saya setuju dengan <a href="#">Syarat & Ketentuan</a> serta <a href="#">Kebijakan Privasi</a> Laskar Trip.
          </label>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-submit">
          Buat Akun <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
        </button>
      </form>

      <!-- Login Link -->
      <div class="login-text">
        Sudah punya akun? <a href="login.php">Masuk (Login)</a>
      </div>

      <!-- Social Register -->
      <div class="divider">
        <span>Or sign up with</span>
      </div>

      <div class="social-buttons">
        <button type="button" class="btn-social google">
          <i class="fa-brands fa-google"></i> Google
        </button>
        <button type="button" class="btn-social">
          <i class="fa-brands fa-facebook"></i>
        </button>
        <button type="button" class="btn-social">
          <i class="fa-brands fa-apple"></i>
        </button>
      </div>

    </div>
  </div>

</div>

<script>
  // Fungsi toggle password reusable
  function togglePass(inputId, icon) {
    const input = document.getElementById(inputId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
  }
</script>

</body>
</html>