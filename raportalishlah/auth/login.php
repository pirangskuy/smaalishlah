<?php
// auth/login.php

ob_start();

// load config utama (session_start, koneksi $pdo, dll.)
require_once __DIR__ . '/../config.php';

$error = '';

// ambil error dari query string kalau ada (misalnya dari logout atau redirect lain)
if (!empty($_GET['error'])) {
    $error = $_GET['error'];
}

// proses jika form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username dan password wajib diisi!";
    } else {
        try {
            // pastikan objek $pdo tersedia
            if (!isset($pdo)) {
                throw new Exception("Koneksi database belum terinisialisasi.");
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // simpan info user di session
                $_SESSION['user'] = [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'role'     => $user['role'],
                    'guru_id'  => $user['guru_id'],
                ];

                // arahkan sesuai role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: ../admin/admin_dashboard.php");
                        exit;
                    case 'guru':
                        header("Location: ../guru/guru_dashboard.php");
                        exit;
                    case 'walikelas':
                        header("Location: ../walikelas/wali_dashboard.php");
                        exit;
                    default:
                        session_destroy();
                        $error = "Role pengguna tidak dikenal.";
                }
            } else {
                $error = "Username atau password salah!";
            }
        } catch (Exception $e) {
            // tampilkan pesan error internal (sementara untuk debug)
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Rapor Digital - SMA Al Ishlah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --ishlah-green: #1b5e20;
            --ishlah-green-soft: #2e7d32;
            --ishlah-yellow: #ffc107;
            --ishlah-yellow-soft: #fff59d;
            --ishlah-bg: #f4f7f4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', Arial, sans-serif;
            height: 100vh;
            display: flex;
            background: radial-gradient(circle at top left, #f9fff5 0, #f4f7f4 30%, #eef3ee 70%, #e8f0e8 100%);
            color: #234021;
        }

        .wrapper {
            display: flex;
            flex: 1;
            min-height: 100vh;
        }

        /* BAGIAN KIRI: Background Foto Sekolah */
        .left-bg {
            flex: 1.1;
            background: url('../media/alishlah1.jpg') no-repeat center center/cover;
            position: relative;
            overflow: hidden;
            transition: background-image 0.6s ease;
        }

        .left-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 77, 64, 0.5), rgba(56, 142, 60, 0.55));
        }

        .left-caption {
            position: absolute;
            left: 40px;
            bottom: 60px;
            color: #fffde7;
            max-width: 340px;
            transition: opacity 0.5s ease;
        }

        .left-caption h1 {
            margin: 0 0 6px;
            font-size: 26px;
            font-weight: 600;
        }

        .left-caption p {
            margin: 0;
            font-size: 13px;
            opacity: .9;
        }

        .slide-dots {
            position: absolute;
            left: 40px;
            bottom: 24px;
            display: flex;
            gap: 8px;
            z-index: 3;
        }

        .slide-dots .dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            background: transparent;
            cursor: pointer;
            transition: background 0.25s ease, transform 0.25s ease;
        }

        .slide-dots .dot.active {
            background: #fffde7;
            transform: scale(1.1);
        }

        /* BAGIAN KANAN: Form Login */
        .right-login {
            flex: 0.9;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-box {
            background: rgba(255, 255, 255, 0.96);
            padding: 30px 32px 26px;
            border-radius: 18px;
            box-shadow: 0 14px 35px rgba(0,0,0,0.16);
            width: 360px;
            max-width: 100%;
            text-align: center;
            border-top: 4px solid var(--ishlah-yellow);
            border-bottom: 4px solid var(--ishlah-green-soft);
        }

        .logo-circle {
            width: 74px;
            height: 74px;
            border-radius: 999px;
            overflow: hidden;
            margin: 0 auto 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.22);
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .badge-small {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            background: var(--ishlah-yellow-soft);
            color: #795548;
            font-size: 10px;
            letter-spacing: .13em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        h2 {
            margin: 0 0 10px;
            font-size: 20px;
            color: #2d5f2e;
            font-weight: 600;
        }

        .app-subtitle {
            font-size: 12px;
            color: #777;
            margin-bottom: 18px;
        }

        form {
            text-align: left;
            margin-top: 10px;
        }

        label {
            display: block;
            font-size: 12px;
            margin-top: 10px;
            margin-bottom: 2px;
            color: #345234;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 9px 10px;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
            background: rgba(255, 255, 255, 0.98);
            font-size: 13px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--ishlah-green-soft);
            box-shadow: 0 0 0 2px rgba(67, 160, 71, 0.25);
        }

        button {
            margin-top: 18px;
            width: 100%;
            padding: 10px;
            background: linear-gradient(to right, var(--ishlah-yellow), var(--ishlah-green-soft));
            color: white;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        button:hover {
            filter: brightness(1.03);
        }

        .error {
            color: #c62828;
            background: #ffebee;
            border: 1px solid #ffcdd2;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 10px;
            text-align: left;
        }

        .login-footer {
            margin-top: 14px;
            font-size: 11px;
            color: #999;
        }

        .login-footer span {
            color: var(--ishlah-green-soft);
            font-weight: 500;
        }

        @media (max-width: 900px) {
            .wrapper {
                flex-direction: column;
            }
            .left-bg {
                height: 35vh;
                flex: 0 0 auto;
            }
            .right-login {
                flex: 1;
            }
            .left-caption {
                left: 20px;
                bottom: 60px;
                max-width: 260px;
            }
            .slide-dots {
                left: 20px;
                bottom: 20px;
            }
            .left-caption h1 {
                font-size: 20px;
            }
        }

        @media (max-width: 600px) {
            body {
                background: linear-gradient(to bottom, #f9fff5, #e8f5e9);
            }
            .left-bg {
                display: none;
            }
            .wrapper {
                justify-content: center;
                align-items: center;
            }
            .right-login {
                flex: 1;
            }
        }
    </style>
</head>
<body>

<div class="wrapper">

    <!-- BAGIAN KIRI: FOTO SEKOLAH + SLIDER -->
    <div class="left-bg">
        <div class="left-overlay"></div>

        <div class="left-caption">
            <h1>SMA AL ISHLAH</h1>
            <p>Sistem Rapor Digital untuk mendukung administrasi penilaian guru, wali kelas, dan siswa secara terpusat.</p>
        </div>

        <div class="slide-dots">
            <span class="dot active" data-index="0"></span>
            <span class="dot" data-index="1"></span>
            <span class="dot" data-index="2"></span>
        </div>
    </div>

    <!-- BAGIAN KANAN: FORM LOGIN -->
    <div class="right-login">
        <div class="login-box">
            <div class="logo-circle">
                <img src="../media/AL ISLHAH.jpg" alt="Logo SMA Al Ishlah">
            </div>
            <div class="badge-small">Login Rapor Digital</div>
            <h2>LRD AL ISHLAH</h2>
            <div class="app-subtitle">Silakan masuk menggunakan akun admin, guru, atau wali kelas.</div>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" autocomplete="username" required>

                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>

                <button type="submit">Masuk</button>
            </form>

            <div class="login-footer">
                &copy; <?= date('Y'); ?> <span>SMA Al Ishlah</span> &middot; Sistem Rapor Digital
            </div>
        </div>
    </div>

</div>

<script>
    const slides = [
        {
            title: "SMA AL ISHLAH",
            text: "Sistem Rapor Digital untuk mendukung administrasi penilaian guru, wali kelas, dan siswa secara terpusat.",
            image: "../media/alishlah1.jpg"
        },
        {
            title: "Rapor Digital Terintegrasi",
            text: "Nilai pengetahuan, keterampilan, dan sikap tersimpan rapi dan dapat diakses kapan saja.",
            image: "../media/alishlah2.jpg"
        },
        {
            title: "Dukungan untuk Guru & Wali Kelas",
            text: "Mempermudah pengolahan nilai, rekap rapor, dan pemantauan perkembangan belajar siswa.",
            image: "../media/alishlah3.jpg"
        }
    ];

    let currentSlide = 0;
    let slideInterval = null;

    function showSlide(index) {
        const bg = document.querySelector('.left-bg');
        const caption = document.querySelector('.left-caption');
        const titleEl = caption.querySelector('h1');
        const textEl = caption.querySelector('p');
        const dots = document.querySelectorAll('.slide-dots .dot');

        const slide = slides[index];

        caption.style.opacity = 0;

        setTimeout(() => {
            if (slide.image && bg) {
                bg.style.backgroundImage = "url('" + slide.image + "')";
            }
            if (titleEl) titleEl.textContent = slide.title;
            if (textEl) textEl.textContent = slide.text;

            dots.forEach(d => d.classList.remove('active'));
            if (dots[index]) dots[index].classList.add('active');

            caption.style.opacity = 1;
        }, 250);
    }

    function startAutoSlide() {
        slideInterval = setInterval(() => {
            currentSlide = (currentSlide + 1) % slides.length;
            showSlide(currentSlide);
        }, 6000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        showSlide(0);
        startAutoSlide();

        const dots = document.querySelectorAll('.slide-dots .dot');
        dots.forEach(dot => {
            dot.addEventListener('click', function () {
                const index = parseInt(this.getAttribute('data-index'), 10);
                currentSlide = index;
                showSlide(currentSlide);

                if (slideInterval) {
                    clearInterval(slideInterval);
                    startAutoSlide();
                }
            });
        });
    });
</script>

</body>
</html>
