<?php
// admin_login.php
session_start();
require 'koneksi.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? ''); // username atau email
    $password = $_POST['password'] ?? '';

    if ($login_id === '' || $password === '') {
        $errors[] = 'Username/Email dan password wajib diisi.';
    }

    if (empty($errors)) {
        // cek ke tabel admins, BUKAN users
        $stmt = mysqli_prepare($conn,
            "SELECT id, username, email, password FROM admins
             WHERE username = ? OR email = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ss", $login_id, $login_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin  = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($admin && password_verify($password, $admin['password'])) {
            // login admin sukses → simpan session khusus admin
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];

            // arahkan ke dashboard admin
            header("Location: admin.php"); 
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
    <meta charset="UTF-8">
    <title>Login Admin - LaskarTrip</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        *{box-sizing:border-box;margin:0;padding:0}

        :root{
            --primary:#2563eb;
            --primary-soft:#1d4ed8;
            --accent:#22c55e;
            --text-main:#f9fafb;
            --text-muted:#e5e7eb;
        }

        body{
            min-height:100vh;
            font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            color:var(--text-main);
            background:#020617;
            position:relative;
            overflow:hidden;
        }

        /* Background foto + overlay gelap */
        body::before{
            content:"";
            position:fixed;
            inset:0;
            background:
              linear-gradient(to right,rgba(15,23,42,.92) 0%,rgba(15,23,42,.72) 35%,rgba(15,23,42,.55) 60%,rgba(15,23,42,.86) 100%),
              url('https://images.unsplash.com/photo-1504609773096-104ff2c73ba4?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'); /* GANTI ke path fotomu */
            background-size:cover;
            background-position:center;
            z-index:-2;
        }

        /* blur lembut di pinggir, biar mirip contoh */
        body::after{
            content:"";
            position:fixed;
            inset:0;
            backdrop-filter:blur(3px);
            -webkit-backdrop-filter:blur(3px);
            z-index:-1;
        }

        .layout{
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px 16px;
        }

        .login-shell{
            width:100%;
            max-width:1100px;
            display:grid;
            grid-template-columns:minmax(0,1.15fr) minmax(0,0.9fr);
            gap:40px;
            padding:32px 32px;
        }

        /* KIRI – teks hero */
        .hero{
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            max-width:420px;
        }

        .hero-logo{
            font-size:18px;
            letter-spacing:.12em;
            font-weight:700;
        }
        .hero-logo span{
            color:var(--accent);
        }

        .hero-main{
            margin-top:40px;
        }

        .hero-title{
            font-size:38px;
            line-height:1.05;
            font-weight:800;
        }

        .hero-sub{
            margin-top:14px;
            font-size:13px;
            color:var(--text-muted);
            max-width:320px;
        }

        .hero-sub span{
            color:#ffffff;
            font-weight:600;
        }

        .hero-foot{
            margin-top:40px;
            font-size:11px;
            color:rgba(226,232,240,.85);
        }

        /* KANAN – kartu login kaca */
        .auth-wrap{
            display:flex;
            align-items:center;
            justify-content:flex-end;
        }

        .auth-card{
            width:100%;
            max-width:380px;
            border-radius:26px;
            padding:22px 22px 18px;
            background:rgba(15,23,42,.78);
            border:1px solid rgba(148,163,184,.6);
            box-shadow:
              0 24px 60px rgba(15,23,42,.95),
              0 0 0 1px rgba(15,23,42,.4);
            backdrop-filter:blur(24px);
            -webkit-backdrop-filter:blur(24px);
        }

        .auth-title{
            font-size:18px;
            font-weight:700;
            margin-bottom:4px;
        }

        .auth-subtitle{
            font-size:12px;
            color:#cbd5f5;
            margin-bottom:18px;
        }

        .error-box{
            font-size:12px;
            color:#fecaca;
            background:rgba(248,113,113,.17);
            border-radius:12px;
            border:1px solid rgba(248,113,113,.7);
            padding:8px 10px;
            margin-bottom:10px;
        }
        .error-box ul{list-style:none;}
        .error-box li::before{content:"• ";}

        .field{
            margin-bottom:12px;
        }
        .field label{
            display:block;
            font-size:12px;
            font-weight:500;
            margin-bottom:4px;
            color:#e5e7eb;
        }

        .input-wrap{
            position:relative;
        }
        .input-icon{
            position:absolute;
            left:11px;
            top:50%;
            transform:translateY(-50%);
            font-size:13px;
            color:#9ca3af;
            pointer-events:none;
        }

        .input{
            width:100%;
            padding:9px 11px 9px 32px;
            border-radius:12px;
            border:1px solid rgba(148,163,184,.6);
            font-size:13px;
            outline:none;
            background:rgba(15,23,42,.75);
            color:#e5e7eb;
            transition:border-color .15s, box-shadow .15s, background .15s;
        }

        .input::placeholder{
            color:#64748b;
        }

        .input:focus{
            border-color:#60a5fa;
            background:rgba(15,23,42,.9);
            box-shadow:0 0 0 1px rgba(59,130,246,.5);
        }

        .login-btn{
            width:100%;
            border:none;
            border-radius:999px;
            margin-top:8px;
            padding:9px 16px;
            font-size:14px;
            font-weight:600;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            color:#ffffff;
            cursor:pointer;
            box-shadow:0 18px 40px rgba(37,99,235,.9);
            transition:transform .14s, box-shadow .14s, filter .14s;
        }

        .login-btn span.icon{
            font-size:15px;
            margin-top:1px;
        }

        .login-btn:hover{
            filter:brightness(1.05);
            transform:translateY(-1px);
            box-shadow:0 22px 50px rgba(37,99,235,1);
        }

        .login-btn:active{
            transform:translateY(0);
            box-shadow:0 12px 32px rgba(37,99,235,.85);
        }

        .auth-footer{
            margin-top:14px;
            text-align:center;
            font-size:11px;
            color:#9ca3af;
        }

        .auth-footer a{
            color:#bfdbfe;
            text-decoration:none;
            font-weight:500;
        }

        .auth-footer a:hover{
            text-decoration:underline;
        }

        @media (max-width:900px){
            .login-shell{
                grid-template-columns:1fr;
                gap:28px;
                padding:24px 18px;
            }
            .hero{
                max-width:none;
            }
            .hero-main{
                margin-top:28px;
            }
            .auth-wrap{
                justify-content:flex-start;
            }
        }

        @media (max-width:640px){
            .hero-main{
                margin-top:20px;
            }
            .hero-title{
                font-size:30px;
            }
            .auth-card{
                max-width:100%;
            }
        }
    </style>
</head>
<body>

<div class="layout">
    <div class="login-shell">
        <!-- KIRI: hero copy -->
        <section class="hero">
            <div>
                <div class="hero-logo">LASKAR<span>TRIP</span></div>
                <div class="hero-main">
                    <h1 class="hero-title">
                        Kelola<br>
                        Perjalanan<br>
                        Pengguna.
                    </h1>
                    <p class="hero-sub">
                        Panel <span>Admin</span> untuk memantau hotel, booking,
                        dan pengalaman pengguna di LaskarTrip dalam satu tempat.
                    </p>
                </div>
            </div>

            <p class="hero-foot">
                Akses hanya untuk admin yang berwenang. Aktivitas login tercatat
                demi keamanan sistem.
            </p>
        </section>

        <!-- KANAN: card login kaca -->
        <section class="auth-wrap">
            <div class="auth-card">
                <h2 class="auth-title">Masuk Dashboard Admin</h2>
                <p class="auth-subtitle">
                    Gunakan akun admin LaskarTrip untuk mengelola konten dan data.
                </p>

                <?php if (!empty($errors)): ?>
                    <div class="error-box">
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="field">
                        <label for="login_id">Username atau Email</label>
                        <div class="input-wrap">
                            <span class="input-icon">👤</span>
                            <input
                                type="text"
                                id="login_id"
                                name="login_id"
                                class="input"
                                value="<?= isset($login_id) ? htmlspecialchars($login_id) : '' ?>"
                                placeholder="admin@laskartrip.com"
                                required
                            >
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <span class="input-icon">🔒</span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="input"
                                placeholder="Masukkan password"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="login-btn">
                        <span>Masuk Dashboard</span>
                        <span class="icon">➜</span>
                    </button>
                </form>

                <div class="auth-footer">
                    Kembali ke website utama? <a href="index.php">Klik di sini</a>
                </div>
            </div>
        </section>
    </div>
</div>
</body>
</html>
