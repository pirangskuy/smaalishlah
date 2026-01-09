<?php
// guru/guru_dashboard.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

// Ambil guru_id dari session user
$guruId = $_SESSION['user']['guru_id'] ?? null;

if (!$guruId) {
    echo "<p style='color:red;'>Akun guru belum terhubung ke data guru (guru_id kosong di tabel users). Hubungi admin untuk mengisi kolom guru_id.</p>";
    exit;
}

// === NOTIFIKASI: KELAS YANG BELUM DISETOR NILAINYA (sesuaikan dgn DB nyata) ===
$kelasBelumSetor = [];
try {
    $sqlNotif = "
        SELECT k.nama_kelas
        FROM kelas k
        JOIN mapel m ON m.kelas_id = k.id AND m.teacher_id = :guru_id
        LEFT JOIN setor_nilai sn 
            ON sn.kelas_id = k.id 
           AND sn.mapel_id = m.id 
           AND sn.guru_id = :guru_id
        WHERE sn.id IS NULL
        GROUP BY k.nama_kelas
        ORDER BY k.nama_kelas
    ";
    $stmtNotif = $pdo->prepare($sqlNotif);
    $stmtNotif->execute(['guru_id' => $guruId]);
    $kelasBelumSetor = $stmtNotif->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $kelasBelumSetor = []; // kalau error, kosongkan saja
}

// Ambil data guru dari tabel teachers
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$guruId]);
$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    echo "<p style='color:red;'>Data guru dengan ID {$guruId} tidak ditemukan di tabel teachers. Hubungi admin.</p>";
    exit;
}

// Theme (dark mode) via cookie
$theme = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark' : 'light';

// === AMBIL SEMUA MAPEL YANG DIAMPU GURU INI DARI TABEL mapel ===
$stmtMapelGuru = $pdo->prepare("SELECT nama FROM mapel WHERE teacher_id = ? ORDER BY nama");
$stmtMapelGuru->execute([$guruId]);
$mapelGuruList = $stmtMapelGuru->fetchAll(PDO::FETCH_COLUMN);

// Deteksi PAI & PPKn berdasarkan NAMA MAPEL di tabel mapel
$isPAI  = false;
$isPPKn = false;

foreach ($mapelGuruList as $namaMapel) {
    $nm = strtolower(trim($namaMapel));
    if (strpos($nm, 'pai') !== false 
        || strpos($nm, 'ibadah syariah') !== false 
        || strpos($nm, 'agama') !== false) {
        $isPAI = true;
    }
    if (strpos($nm, 'ppkn') !== false 
        || strpos($nm, 'ppk') !== false) {
        $isPPKn = true;
    }
}

// Daftar mapel untuk ditampilkan di halaman
$mapelGabung = $mapelGuruList ? implode(', ', $mapelGuruList) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Guru - Rapor Digital SMA Al Ishlah</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <!-- Panggil CSS eksternal -->
    <link rel="stylesheet" href="../assets/css/guru_dashboard.css">
</head>
<body class="<?= $theme === 'dark' ? 'dark' : ''; ?>">

<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-title">Rapor Digital</div>
            <div class="brand-subtitle">SMA Al Ishlah • Guru</div>
        </div>

        <div class="menu-title">Menu Utama</div>
        <a href="guru_dashboard.php" class="menu-link active"> Dashboard</a>
        <a href="profile.php" class="menu-link"> Profil Saya</a>

        <hr>

        <div class="menu-title">Input & Rekap Nilai</div>
        <a href="nilai_pengetahuan.php" class="menu-link"> Input Nilai Pengetahuan</a>
        <a href="nilai_keterampilan.php" class="menu-link"> Input Nilai Keterampilan</a>
        <a href="nilai_uts_uas.php" class="menu-link"> Input Nilai UTS &amp; UAS</a>
        <a href="rekap_nilai.php" class="menu-link"> Rekap Nilai Siswa</a>
        <a href="rekap_uts_uas.php" class="menu-link"> Rekap UTS &amp; UAS</a>
        <a href="setor_nilai_mapel.php" class="menu-link"> Setor Nilai ke Wali Kelas</a>

        <hr>

        <div class="menu-title">Pengaturan KD</div>
        <a href="kd_list.php" class="menu-link"> Kelola KD Pengetahuan</a>
        <a href="kd_keterampilan_list.php" class="menu-link"> Kelola KD Keterampilan</a>

        <?php if ($isPAI): ?>
            <hr>
            <div class="menu-title">Sikap Spiritual (PAI)</div>
            <a href="sikap_spiritual_pai.php" class="menu-link"> Input Sikap Spiritual</a>
        <?php endif; ?>

        <?php if ($isPPKn): ?>
            <hr>
            <div class="menu-title">Sikap Sosial (PPKn)</div>
            <a href="sikap_sosial_ppkn.php" class="menu-link"> Input Sikap Sosial</a>
        <?php endif; ?>

        <hr>
        <a href="../auth/logout.php" class="menu-link"> Logout</a>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="main">
        <div class="topbar">
            <div>
                <div class="topbar-title">Dashboard Guru</div>
                <div class="topbar-subtitle">Rapor Digital SMA Al Ishlah</div>
            </div>
            <div class="topbar-right">
                <!-- TOGGLE DARK MODE ANIMASI -->
                <div class="theme-toggle-wrapper">
                    <input type="checkbox"
                           id="darkToggle"
                           class="dark-checkbox"
                           <?= $theme === 'dark' ? 'checked' : ''; ?>>
                    <label for="darkToggle" class="toggle-label">
                        <div class="toggle-track"></div>
                        <div class="toggle-knob"></div>
                    </label>
                </div>

                <div class="user-badge">
                    Guru: <span><?= htmlspecialchars($guru['name']); ?></span>
                </div>
            </div>
        </div>

        <section class="card">
            <h2>Selamat Datang, <strong><?= htmlspecialchars($guru['name']); ?></strong></h2>
            <p class="muted">
                Ini adalah beranda utama untuk mengelola nilai dan kompetensi dasar sesuai mata pelajaran yang Anda ampu.
            </p>

            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value">
                    <?= htmlspecialchars($_SESSION['user']['username'] ?? '-') ?>
                    <span class="badge-role">Guru</span>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Mapel Diampu</span>
                <span class="info-value">
                    <?php if ($mapelGabung): ?>
                        <?= htmlspecialchars($mapelGabung); ?>
                    <?php else: ?>
                        <span class="muted">Belum ada mapel yang dihubungkan ke guru ini di tabel <code>mapel</code>.</span>
                    <?php endif; ?>
                </span>
            </div>

            <!-- Notifikasi setoran nilai -->
            <div class="section-title">Notifikasi Setoran Nilai</div>
            <?php if (!empty($kelasBelumSetor)): ?>
                <p class="section-text">
                    Anda belum menyetor nilai untuk kelas berikut:
                </p>
                <ul class="section-text" style="margin-top:6px;">
                    <?php foreach ($kelasBelumSetor as $namaKelas): ?>
                        <li>Belum setor nilai kelas <b><?= htmlspecialchars($namaKelas); ?></b></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="section-text">
                    Semua kelas yang Anda ampu sudah disetor nilainya ke wali kelas. 
                </p>
            <?php endif; ?>

            <div class="highlight-box">
                Gunakan menu di sebelah kiri untuk <b>menginput nilai</b>, 
                <b>mengelola KD</b>, dan <b>menyetor nilai ke wali kelas</b>. 
                Semua data akan terintegrasi ke rapor digital siswa.
            </div>

            <div class="section-title">Kelola KD Pengetahuan</div>
            <p class="section-text">
                Untuk mengisi <b>KD Pengetahuan</b> (misalnya:
                <i>“Memahami struktur teks laporan hasil observasi”</i>,
                <i>“Menganalisis unsur kebahasaan teks eksposisi”</i>, dan seterusnya),
                silakan gunakan menu <b>Kelola KD Pengetahuan</b> di sidebar.
                Setelah KD tersimpan, halaman <b>Input Nilai Pengetahuan</b> akan otomatis
                menampilkan daftar KD tersebut.
            </p>

            <div class="section-title">Pengisian Nilai Harian &amp; Ujian</div>
            <p class="section-text">
                Nilai harian per KD, nilai keterampilan, serta nilai UTS dan UAS
                dapat diinput melalui menu terkait. Sistem akan membantu merekap nilai
                dan menyiapkan data untuk disetor ke wali kelas secara terstruktur.
            </p>

            <?php if ($isPAI): ?>
                <div class="section-title">Sikap Spiritual (PAI)</div>
                <p class="section-text">
                    Karena Anda mengampu mata pelajaran PAI / Ibadah Syariah / Agama,
                    tersedia fitur <b>Input Sikap Spiritual</b> untuk menilai:
                    taat beribadah, rasa syukur, berdoa, toleransi, peduli sesama, tawakal, dan memberi salam.
                </p>
            <?php endif; ?>

            <?php if ($isPPKn): ?>
                <div class="section-title">Sikap Sosial (PPKn)</div>
                <p class="section-text">
                    Karena Anda mengampu mata pelajaran PPKn, tersedia fitur
                    <b>Input Sikap Sosial</b> untuk menilai: jujur, disiplin, santun, peduli lingkungan,
                    tanggung jawab, responsif, dan pro-aktif sesuai karakter profil pelajar Pancasila.
                </p>
            <?php endif; ?>
        </section>
    </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('darkToggle');
    if (!checkbox) return;

    // Pastikan checkbox sesuai dengan kelas body saat load
    checkbox.checked = document.body.classList.contains('dark');

    checkbox.addEventListener('change', function () {
        const isDark = checkbox.checked;
        document.body.classList.toggle('dark', isDark);
        document.cookie = 'theme=' + (isDark ? 'dark' : 'light') + '; path=/; max-age=31536000';
    });
});
</script>

</body>
</html>
