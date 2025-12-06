<?php
session_start();
require 'koneksi.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? ''); // Bisa username atau email
    $password = $_POST['password'] ?? '';

    if ($login_id === '' || $password === '') {
        $errors[] = 'Username/Email dan password wajib diisi.';
    }

    if (empty($errors)) {
        // Query cek user berdasarkan username ATAU email
        $stmt = mysqli_prepare($conn,
            "SELECT id, nama_lengkap, username, password FROM users
             WHERE username = ? OR email = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ss", $login_id, $login_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Verifikasi password
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['nama_lengkap']  = $user['nama_lengkap'];
            $_SESSION['username']      = $user['username'];

            // Redirect ke halaman utama
            header("Location: index.php");
            exit;
        } else {
            $errors[] = 'Username/Email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - Laskar Trip</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- FontAwesome untuk ikon -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary: #2563eb;       /* Biru utama */
      --primary-dark: #1d4ed8;  /* Biru gelap untuk hover */
      --text-main: #0f172a;     /* Warna teks utama */
      --text-muted: #64748b;    /* Warna teks pudar */
      --bg-input: #f1f5f9;      /* Background input field */
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', sans-serif;
      height: 100vh;
      width: 100%;
      background: #fff;
      overflow: hidden; /* Mencegah scroll di desktop */
    }

    .login-container {
      display: grid;
      grid-template-columns: 1.2fr 1fr; /* Kiri (Gambar) lebih lebar sedikit */
      height: 100%;
    }

    /* === BAGIAN KIRI (GAMBAR) === */
    .left-section {
      position: relative;
      /* URL Gambar Background - Pemandangan Alam/Travel */
      background-image: url('https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?q=80&w=2000&auto=format&fit=crop');
      background-size: cover;
      background-position: center;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 40px;
      color: white;
    }

    /* Overlay Hitam Transparan agar teks terbaca */
    .left-section::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.8) 100%);
      z-index: 1;
    }

    /* Konten di atas gambar (z-index lebih tinggi dari overlay) */
    .brand-top, .content-bottom {
      position: relative;
      z-index: 2;
    }

    .brand-top {
      font-size: 24px;
      font-weight: 700;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .content-bottom h2 {
      font-size: 42px;
      font-weight: 700;
      margin-bottom: 12px;
      line-height: 1.1;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .content-bottom p {
      font-size: 16px;
      color: rgba(255, 255, 255, 0.95);
      max-width: 85%;
      margin-bottom: 24px;
      line-height: 1.6;
      text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .footer-links {
      display: flex;
      gap: 20px;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.8);
    }
    .footer-links a { color: inherit; text-decoration: none; transition: 0.3s; }
    .footer-links a:hover { color: white; text-decoration: underline; }

    /* === BAGIAN KANAN (FORM) === */
    .right-section {
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
      overflow-y: auto;
    }

    .form-wrapper {
      width: 100%;
      max-width: 400px;
    }

    .form-header {
      margin-bottom: 32px;
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

    /* ERROR ALERT */
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

    /* FORM ELEMENTS */
    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .input-wrapper {
      position: relative;
    }

    .form-control {
      width: 100%;
      padding: 12px 16px;
      background: var(--bg-input);
      border: 1px solid transparent;
      border-radius: 12px;
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

    .toggle-password {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-muted);
      cursor: pointer;
      font-size: 14px;
    }

    /* ACTIONS: Remember & Forgot */
    .form-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      font-size: 13px;
    }

    .remember-me {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text-main);
      cursor: pointer;
    }
    .remember-me input {
      width: 16px;
      height: 16px;
      accent-color: var(--primary);
      cursor: pointer;
    }

    .forgot-link {
      color: var(--text-main);
      font-weight: 600;
      text-decoration: none;
    }
    .forgot-link:hover { text-decoration: underline; color: var(--primary); }

    /* SUBMIT BUTTON */
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
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
    }
    .btn-submit:hover {
      background: var(--primary-dark);
    }

    /* REGISTER LINK */
    .register-text {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: var(--text-muted);
    }
    .register-text a {
      color: var(--text-main);
      font-weight: 700;
      text-decoration: none;
    }
    .register-text a:hover { text-decoration: underline; color: var(--primary); }

    /* SOCIAL LOGIN */
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
      padding: 12px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
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
      .login-container { grid-template-columns: 1fr; }
      .left-section { display: none; } /* Sembunyikan gambar di HP */
      .right-section { padding: 20px; }
    }
  </style>
</head>
<body>

<div class="login-container">
  
  <!-- BAGIAN KIRI: GAMBAR -->
  <div class="left-section">
    <div class="brand-top">
      <i class="fa-solid fa-plane-departure"></i> Laskar Trip
    </div>
    
    <div class="content-bottom">
      <h2>Welcome back!</h2>
      <p>Jelajahi keindahan Swiss dari Van Java. Login untuk mengelola perjalanan wisatamu.</p>
      
      <div class="footer-links">
        <span>© <?= date('Y') ?> Laskar Trip</span>
        <a href="#">Terms & Conditions</a>
        <a href="#">Privacy Policy</a>
      </div>
    </div>
  </div>

  <!-- BAGIAN KANAN: FORM -->
  <div class="right-section">
    <div class="form-wrapper">
      
      <div class="form-header">
        <h1>Login ke Akun</h1>
        <p>Selamat datang kembali! Silakan masukkan detail Anda.</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-box">
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form action="" method="POST">
        <!-- Input Username/Email -->
        <div class="form-group">
          <label for="login_id">Username atau Email</label>
          <div class="input-wrapper">
            <input 
              type="text" 
              id="login_id" 
              name="login_id" 
              class="form-control" 
              placeholder="Contoh: user@laskartrip.com"
              value="<?= isset($_POST['login_id']) ? htmlspecialchars($_POST['login_id']) : '' ?>"
              required
            >
          </div>
        </div>

        <!-- Input Password -->
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <input 
              type="password" 
              id="password" 
              name="password" 
              class="form-control" 
              placeholder="Masukkan password Anda"
              required
            >
            <i class="fa-regular fa-eye-slash toggle-password" id="togglePassword"></i>
          </div>
        </div>

        <!-- Remember Me & Forgot Pass -->
        <div class="form-actions">
          <label class="remember-me">
            <input type="checkbox" name="remember"> Ingat saya
          </label>
          <a href="#" class="forgot-link">Lupa password?</a>
        </div>

        <!-- Tombol Login -->
        <button type="submit" class="btn-submit">
          Masuk Sekarang <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <!-- Link Daftar -->
      <div class="register-text">
        Belum punya akun? <a href="register.php">Daftar sekarang</a>
      </div>

      <!-- Social Login -->
      <div class="divider">
        <span>Atau masuk dengan</span>
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
  // Script untuk Show/Hide Password
  const togglePassword = document.querySelector('#togglePassword');
  const password = document.querySelector('#password');

  togglePassword.addEventListener('click', function (e) {
    // Ubah tipe input
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    
    // Ubah ikon mata
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
  });
</script>

</body>
</html>