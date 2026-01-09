<?php
// guru/profile.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

// Ambil guru_id dari session
$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    die("<p style='color:red;'>guru_id tidak ditemukan di session. Hubungi admin.</p>");
}

// Ambil data guru
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$guruId]);
$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    die("<p style='color:red;'>Data guru tidak ditemukan di tabel teachers. Hubungi admin.</p>");
}

$success = '';
$error   = '';

// Proses update foto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES['foto']['tmp_name'];
        $fileName = $_FILES['foto']['name'];
        $fileSize = $_FILES['foto']['size'];

        // Batas ukuran misal 2MB
        $maxSize = 2 * 1024 * 1024;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            $error = "Format file harus JPG, JPEG, PNG, atau WEBP.";
        } elseif ($fileSize > $maxSize) {
            $error = "Ukuran file maksimal 2MB.";
        } else {
            // Nama file baru, misal: guru_3_20251128xxxx.jpg
            $newName = 'guru_' . $guruId . '_' . time() . '.' . $ext;
            $targetDir = __DIR__ . '/../media/guru/';
            $targetPath = $targetDir . $newName;

            // Pastikan folder ada
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }

            if (move_uploaded_file($fileTmp, $targetPath)) {
                // Hapus foto lama kalau ada
                if (!empty($guru['foto'])) {
                    $oldPath = $targetDir . $guru['foto'];
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                // Update di database
                $stmtUpdate = $pdo->prepare("UPDATE teachers SET foto = ? WHERE id = ?");
                $stmtUpdate->execute([$newName, $guruId]);

                $success = "Foto profil berhasil diperbarui.";
                // Refresh data guru
                $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
                $stmt->execute([$guruId]);
                $guru = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "Gagal mengupload file. Coba lagi.";
            }
        }
    } else {
        $error = "Pilih file foto terlebih dahulu.";
    }
}

// Siapkan data tampilan
$namaGuru = $guru['name'] ?? 'Guru';
$kelasWali = $guru['kelas_wali'] ?? null;
$inisial  = mb_strtoupper(mb_substr($namaGuru, 0, 1));

$fotoFile = $guru['foto'] ?? '';
$avatarUrl = '';
if (!empty($fotoFile)) {
    $avatarUrl = '../media/guru/' . $fotoFile;
}

// Theme (dark mode) via cookie
$theme = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Guru - Rapor Digital SMA Al Ishlah</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --hijau-utama: #2e8f44;
            --hijau-tua: #256f34;
            --kuning-utama: #ffdd00;
            --abu-bg: #f4f6f8;
            --bg-card: #ffffff;
            --teks-utama: #2c3e50;
            --teks-muted: #7f8c8d;
        }

        body.dark {
            --abu-bg: #111827;
            --bg-card: #1f2937;
            --teks-utama: #e5e7eb;
            --teks-muted: #9ca3af;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Poppins', Arial, sans-serif;
            background: var(--abu-bg);
            color: var(--teks-utama);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR SINGKAT (bisa dibuat sama dengan dashboard) */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--hijau-utama), var(--hijau-tua));
            color: #ffffff;
            padding: 20px 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-size: 16px;
            font-weight: 600;
        }

        .brand-subtitle {
            font-size: 11px;
            opacity: 0.9;
        }

        .menu-link {
            display: block;
            padding: 8px 10px;
            margin-bottom: 4px;
            text-decoration: none;
            color: #f9f9f9;
            font-size: 13px;
            border-radius: 6px;
        }

        .menu-link:hover {
            background: rgba(255,255,255,0.12);
        }

        .menu-link.active {
            background: rgba(255,255,255,0.2);
            font-weight: 600;
        }

        .main {
            flex: 1;
            padding: 20px 28px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .topbar-title {
            font-size: 20px;
            font-weight: 600;
        }

        .topbar-subtitle {
            font-size: 13px;
            color: var(--teks-muted);
        }

        .theme-toggle {
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 20px 22px;
            max-width: 700px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .avatar-big {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #e8f5ec;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
            color: var(--hijau-tua);
            overflow: hidden;
            margin-bottom: 10px;
        }

        .avatar-big img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .muted {
            font-size: 13px;
            color: var(--teks-muted);
        }

        .info-row {
            margin: 8px 0;
            font-size: 14px;
        }

        .info-label {
            display: inline-block;
            min-width: 110px;
            font-weight: 600;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        input[type="file"] {
            font-size: 13px;
        }

        button[type="submit"] {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            background: var(--hijau-utama);
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }

        button[type="submit"]:hover {
            background: var(--hijau-tua);
        }

        @media (max-width: 900px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; height: auto; }
            .main { padding: 15px; }
            .card { max-width: 100%; }
        }
    </style>
</head>
<body class="<?php echo $theme === 'dark' ? 'dark' : ''; ?>">

<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">
                <img src="../media/AL ISLHAH.jpg" alt="Logo SMA Al Ishlah">
            </div>
            <div class="brand-text">
                <div class="brand-title">Rapor Digital</div>
                <div class="brand-subtitle">SMA Al Ishlah</div>
            </div>
        </div>

        <a href="guru_dashboard.php" class="menu-link"> Dashboard</a>
        <a href="profile.php" class="menu-link active"> Profil Saya</a>
        <a href="../auth/logout.php" class="menu-link"> Logout</a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div>
                <div class="topbar-title">Profil Guru</div>
                <div class="topbar-subtitle">Kelola foto dan data dasar akun Anda</div>
            </div>
            <button class="theme-toggle" id="themeToggle" title="Ganti tema">
                <?= $theme === 'dark' ? '☀️' : '🌙'; ?>
            </button>
        </div>

        <section class="card">
            <?php if ($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div style="display:flex; gap:18px; align-items:center; margin-bottom:12px;">
                <div class="avatar-big">
                    <?php if (!empty($avatarUrl)): ?>
                        <img src="<?= htmlspecialchars($avatarUrl); ?>" alt="Foto Guru">
                    <?php else: ?>
                        <?= htmlspecialchars($inisial); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h2 style="margin:0;"><?= htmlspecialchars($namaGuru); ?></h2>
                    <p class="muted" style="margin-top:4px;">
                        <?= htmlspecialchars($_SESSION['user']['username'] ?? '-') ?>
                        <?php if (!empty($kelasWali)): ?>
                            • Wali Kelas <?= htmlspecialchars($kelasWali); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="info-row">
                <span class="info-label">Nama</span>
                <span><?= htmlspecialchars($namaGuru); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Username</span>
                <span><?= htmlspecialchars($_SESSION['user']['username'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Kelas Wali</span>
                <span><?= !empty($kelasWali) ? htmlspecialchars($kelasWali) : '<span class="muted">Bukan wali kelas</span>'; ?></span>
            </div>

            <hr style="margin:18px 0; border:none; border-top:1px solid #e5e7eb;">

            <h3 style="font-size:15px; margin-top:0;">Ganti Foto Profil</h3>
            <p class="muted">
                Unggah foto wajah yang jelas dengan format <b>JPG/PNG/WEBP</b>, ukuran maksimal <b>2MB</b>.
            </p>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                <button type="submit">Simpan Foto Baru</button>
            </form>
        </section>
    </main>
</div>

<script>
    // Toggle dark mode dengan cookie
    const btn = document.getElementById('themeToggle');
    btn.addEventListener('click', function () {
        const isDark = document.body.classList.toggle('dark');
        document.cookie = 'theme=' + (isDark ? 'dark' : 'light') + '; path=/; max-age=31536000';
        btn.textContent = isDark ? '☀️' : '🌙';
    });
</script>

</body>
</html>
