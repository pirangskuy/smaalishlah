<?php
// admin/admin_dashboard.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$username = $_SESSION['user']['username'] ?? 'Admin';

// Statistik sederhana untuk dashboard
$jumlahGuru  = (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$jumlahSiswa = (int)$pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$jumlahMapel = (int)$pdo->query("SELECT COUNT(*) FROM mapel")->fetchColumn();
$jumlahWali  = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE kelas_wali IS NOT NULL AND kelas_wali <> ''")->fetchColumn();

// Theme (dark mode) via cookie (sama dengan guru)
$theme = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - Rapor SMA Al Ishlah</title>

    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (opsional, untuk ikon kecil) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- CSS khusus dashboard admin -->
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
</head>
<body class="<?= $theme === 'dark' ? 'dark' : ''; ?>">

<nav class="navbar navbar-expand-lg navbar-islah mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="admin_dashboard.php">
            <div class="logo-placeholder">
                <img src="../media/AL ISLHAH.jpg" alt="Logo SMA Al Ishlah" class="logo-img">
            </div>
            <div class="d-flex flex-column lh-1 brand-text">
                <span class="brand-title-text">SMA AL ISHLAH</span>
                <small class="badge-school mt-1">Sistem Rapor Digital</small>
            </div>
        </a>

        <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="navbarMain">
            <div class="d-flex align-items-center gap-3">

                <!-- Toggle dark mode mini -->
                <div class="theme-toggle-wrapper">
                    <input type="checkbox"
                           id="adminDarkToggle"
                           class="dark-checkbox"
                           <?= $theme === 'dark' ? 'checked' : ''; ?>>
                    <label for="adminDarkToggle" class="toggle-label">
                        <div class="toggle-track"></div>
                        <div class="toggle-knob"></div>
                    </label>
                </div>

                <div class="nav-username d-flex align-items-center text-white-50">
                    <i class="bi bi-person-circle me-1"></i>
                    <span><?= htmlspecialchars($username) ?></span>
                </div>

                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ====== LAYOUT BARU: KONTEN KIRI + SIDEBAR KANAN ====== -->
<div class="admin-layout">

    <!-- KONTEN UTAMA (KIRI) -->
    <main class="admin-main">
        <div class="container mb-4">

            <!-- Hero & Statistik -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card hero-card">
                        <div class="card-body p-4 p-md-4">
                            <div class="row align-items-center g-4">
                                <div class="col-md-7">
                                    <div class="hero-ribbon mb-2">
                                        <span class="section-title-chip">Dashboard Admin</span>
                                    </div>
                                    <h1 class="h3 mb-2 hero-title">
                                        Selamat datang, <span class="hero-title-highlight"><?= htmlspecialchars($username) ?></span>
                                    </h1>
                                    <p class="mb-3 hero-subtitle">
                                        Kelola data guru, siswa, wali kelas, dan mata pelajaran SMA Al Ishlah dalam satu
                                        panel yang rapi, modern, dan terintegrasi.
                                    </p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge rounded-pill badge-mode-admin">
                                            <i class="bi bi-shield-lock me-1"></i> Mode Admin
                                        </span>
                                        <span class="badge rounded-pill badge-rapor">
                                            <i class="bi bi-journal-check me-1"></i> Rapor Kurikulum Sekolah
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="row text-center g-3 stats-grid">
                                        <div class="col-6 col-md-6">
                                            <div class="stat-pill p-3">
                                                <div class="stat-icon guru">
                                                    <i class="bi bi-people-fill"></i>
                                                </div>
                                                <div class="fw-bold fs-5"><?= $jumlahGuru; ?></div>
                                                <div class="text-muted small">Guru</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-6">
                                            <div class="stat-pill p-3">
                                                <div class="stat-icon wali">
                                                    <i class="bi bi-person-badge-fill"></i>
                                                </div>
                                                <div class="fw-bold fs-5"><?= $jumlahWali; ?></div>
                                                <div class="text-muted small">Wali Kelas</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-6">
                                            <div class="stat-pill p-3">
                                                <div class="stat-icon siswa">
                                                    <i class="bi bi-mortarboard-fill"></i>
                                                </div>
                                                <div class="fw-bold fs-5"><?= $jumlahSiswa; ?></div>
                                                <div class="text-muted small">Siswa</div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-6">
                                            <div class="stat-pill p-3">
                                                <div class="stat-icon mapel">
                                                    <i class="bi bi-journal-bookmark-fill"></i>
                                                </div>
                                                <div class="fw-bold fs-5"><?= $jumlahMapel; ?></div>
                                                <div class="text-muted small">Mapel</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> <!-- row -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manajemen & Pengaturan -->
            <div class="row g-3">
                <!-- Manajemen Data -->
                <div class="col-md-8">
                    <div class="card card-section mb-3">
                        <div class="card-header card-header-islah d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-grid-3x3-gap-fill me-2"></i> Manajemen Data</span>
                            <span class="badge bg-warning text-dark badge-data-utama">Data Utama Sekolah</span>
                        </div>
                        <div class="card-body">
                            <div class="row gy-3">

                                <!-- Wali Kelas -->
                                <div class="col-md-6">
                                    <h5 class="h6 mb-2 section-block-title">
                                        <i class="bi bi-person-badge me-1 text-success"></i> Wali Kelas
                                    </h5>
                                    <ul class="list-group list-group-flush shadow-soft rounded section-list">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="wali_add.php">Tambah Wali Kelas</a>
                                            <i class="bi bi-plus-circle text-success"></i>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="wali_list.php">Daftar Wali Kelas</a>
                                            <i class="bi bi-list-ul text-secondary"></i>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="wali_accounts.php">Akun Wali Kelas</a>
                                            <i class="bi bi-person-lines-fill text-warning"></i>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Guru -->
                                <div class="col-md-6">
                                    <h5 class="h6 mb-2 section-block-title">
                                        <i class="bi bi-person-video3 me-1 text-success"></i> Guru
                                    </h5>
                                    <ul class="list-group list-group-flush shadow-soft rounded section-list">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="teacher_add.php">Tambah Guru</a>
                                            <i class="bi bi-plus-circle text-success"></i>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="teacher_list.php">Data Guru</a>
                                            <i class="bi bi-card-list text-secondary"></i>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="teacher_accounts.php">Daftar Akun Guru</a>
                                            <i class="bi bi-key-fill text-warning"></i>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Siswa -->
                                <div class="col-md-6">
                                    <h5 class="h6 mb-2 section-block-title">
                                        <i class="bi bi-mortarboard me-1 text-success"></i> Siswa
                                    </h5>
                                    <ul class="list-group list-group-flush shadow-soft rounded section-list">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="student_add.php">Tambah Siswa</a>
                                            <i class="bi bi-plus-circle text-success"></i>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="student_list.php">Data Siswa</a>
                                            <i class="bi bi-people-fill text-secondary"></i>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Mapel & Pengampu -->
                                <div class="col-md-6">
                                    <h5 class="h6 mb-2 section-block-title">
                                        <i class="bi bi-journal-text me-1 text-success"></i> Mapel & Pengampu
                                    </h5>
                                    <ul class="list-group list-group-flush shadow-soft rounded section-list">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="mapel_list.php">Daftar Mapel</a>
                                            <i class="bi bi-journal-bookmark text-secondary"></i>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="pengampu_mapel.php">Atur Guru Pengampu Mapel</a>
                                            <i class="bi bi-diagram-3-fill text-warning"></i>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Jadwal Pelajaran -->
                                <div class="col-md-6">
                                    <h5 class="h6 mb-2 section-block-title">
                                        <i class="bi bi-calendar-week me-1 text-success"></i> Jadwal Pelajaran
                                    </h5>
                                    <ul class="list-group list-group-flush shadow-soft rounded section-list">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="jadwal_pelajaran.php">Kelola Jadwal Pelajaran</a>
                                            <i class="bi bi-clock-history text-secondary"></i>
                                        </li>
                                    </ul>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel Akun -->
                <div class="col-md-4">
                    <div class="card card-section mb-3">
                        <div class="card-header card-header-islah">
                            <i class="bi bi-gear-fill me-2"></i> Pengaturan Akun
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">
                                Kelola akun admin dan keamanan akses sistem rapor.
                            </p>
                            <ul class="list-group list-group-flush mb-3 shadow-soft rounded section-list">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="../auth/change_password.php">Kelola Akun Admin</a>
                                    <i class="bi bi-shield-lock text-success"></i>
                                </li>
                            </ul>
                            <a href="../auth/logout.php" class="btn btn-outline-danger w-100">
                                <i class="bi bi-box-arrow-right me-1"></i> Logout
                            </a>
                        </div>
                    </div>

                    <div class="card border-0 shadow-soft">
                        <div class="card-body py-3">
                            <p class="mb-1 small text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Sistem Rapor Digital SMA Al Ishlah
                            </p>
                            <footer class="footer-text">
                                &copy; <?= date('Y'); ?> SMA Al Ishlah. Semua hak cipta dilindungi.
                                Design by @Kurniawan Alhamdani Pandayu Putra,Gibran Danuarta, Nurhasanah Inuy
                                
                            </footer>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- SIDEBAR KANAN BARU -->
    <aside class="admin-sidebar-right">
        <h6 class="sidebar-title">Navigasi Cepat</h6>
        <p class="sidebar-subtitle">Pilih halaman pengelolaan:</p>

        <a href="admin_dashboard.php" class="sidebar-link active">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="teacher_list.php" class="sidebar-link">
            <i class="bi bi-person-video3"></i>
            <span>Data Guru</span>
        </a>
        <a href="student_list.php" class="sidebar-link">
            <i class="bi bi-mortarboard"></i>
            <span>Data Siswa</span>
        </a>
        <a href="wali_list.php" class="sidebar-link">
            <i class="bi bi-person-badge"></i>
            <span>Wali Kelas</span>
        </a>
        <a href="mapel_list.php" class="sidebar-link">
            <i class="bi bi-journal-bookmark"></i>
            <span>Daftar Mapel</span>
        </a>
        <a href="pengampu_mapel.php" class="sidebar-link">
            <i class="bi bi-diagram-3"></i>
            <span>Pengampu Mapel</span>
        </a>
        <a href="jadwal_pelajaran.php" class="sidebar-link">
            <i class="bi bi-calendar-week"></i>
            <span>Jadwal Pelajaran</span>
        </a>
        <hr class="sidebar-divider">
        <a href="../auth/change_password.php" class="sidebar-link">
            <i class="bi bi-gear"></i>
            <span>Pengaturan Akun</span>
        </a>
        <a href="../auth/logout.php" class="sidebar-link sidebar-link-danger">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </aside>

</div><!-- /.admin-layout -->

<!-- Bootstrap JS (opsional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script dark mode (sinkron dengan cookie) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('adminDarkToggle');
    if (!checkbox) return;

    // Pastikan checkbox sesuai kelas body saat load
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
