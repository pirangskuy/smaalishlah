<?php
// walikelas/wali_dashboard.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireWaliKelas();

// Ambil guru_id dari session user
$guruId = $_SESSION['user']['guru_id'] ?? null;

if (!$guruId) {
    echo "<p style='color:red;'>Akun wali kelas belum terhubung ke data guru. Hubungi admin.</p>";
    exit;
}

// Ambil data wali kelas
$stmt = $pdo->prepare("SELECT id, name, kelas_wali FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$guruId]);
$wali = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wali) {
    echo "<p style='color:red;'>Data wali kelas tidak ditemukan. Hubungi admin.</p>";
    exit;
}

$namaWali  = $wali['name'];
$kelasWali = $wali['kelas_wali'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Wali Kelas</title>

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --ishlah-green: #1b5e20;
    --ishlah-soft: #2e7d32;
    --ishlah-yellow: #ffc107;
}

body {
    background: #f1f8f3;
    min-height: 100vh;
}

.navbar-islah {
    background: linear-gradient(90deg, var(--ishlah-green), var(--ishlah-soft));
    box-shadow: 0 3px 12px rgba(0,0,0,.2);
}

.card-menu {
    border-radius: 14px;
    transition: .2s ease;
}

.card-menu:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,.1);
}

.icon-menu {
    font-size: 38px;
    color: var(--ishlah-green);
}

footer {
    margin-top: 40px;
    font-size: 0.75rem;
    color: #777;
    text-align: center;
}
</style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-islah mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="wali_dashboard.php">
            <i class="bi bi-mortarboard-fill me-1"></i> Wali Kelas – SMA Al Ishlah
        </a>

        <div class="ms-auto d-flex align-items-center">
            <span class="text-white me-3">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($namaWali); ?> — Kelas <?= htmlspecialchars($kelasWali); ?>
            </span>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container">

    <!-- HEADER -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <h3 class="fw-bold mb-1">Selamat datang, <?= htmlspecialchars($namaWali); ?> 👋</h3>
            <p class="text-muted mb-0">Dashboard Wali Kelas</p>
            <h6 class="fw-bold text-success mt-2">Kelas <?= htmlspecialchars($kelasWali); ?></h6>
        </div>
    </div>

    <!-- MENU GRID -->
    <div class="row g-4">

        <!-- Rekap Nilai -->
        <div class="col-md-4">
            <div class="card card-menu shadow-sm text-center p-4">
                <div class="icon-menu mb-2">
                    <i class="bi bi-clipboard-data"></i>
                </div>
                <h5 class="fw-bold">Rekap Nilai Siswa</h5>
                <p class="text-muted small">
                    Nilai akhir pengetahuan, keterampilan, dan deskripsi rapor.
                </p>
                <a href="rekap_nilai.php" class="btn btn-success rounded-pill">
                    Buka
                </a>
            </div>
        </div>

        <!-- Rekap Absensi -->
        <div class="col-md-4">
            <div class="card card-menu shadow-sm text-center p-4">
                <div class="icon-menu mb-2">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <h5 class="fw-bold">Rekap Absensi</h5>
                <p class="text-muted small">
                    Data sakit, izin, alfa, dan keterlambatan siswa.
                </p>
                <a href="rekap_absensi.php" class="btn btn-warning rounded-pill">
                    Buka
                </a>
            </div>
        </div>

        <!-- Catatan Wali -->
        <div class="col-md-4">
            <div class="card card-menu shadow-sm text-center p-4">
                <div class="icon-menu mb-2">
                    <i class="bi bi-journal-text"></i>
                </div>
                <h5 class="fw-bold">Catatan Wali Kelas</h5>
                <p class="text-muted small">
                    Catatan sikap, karakter, dan perkembangan siswa.
                </p>
                <a href="catatan_wali.php" class="btn btn-primary rounded-pill">
                    Buka
                </a>
            </div>
        </div>

        <!-- Penilaian Ekstrakurikuler -->
        <div class="col-md-4">
            <div class="card card-menu shadow-sm text-center p-4">
                <div class="icon-menu mb-2">
                    <i class="bi bi-award"></i>
                </div>
                <h5 class="fw-bold">Penilaian Ekstrakurikuler</h5>
                <p class="text-muted small">
                    Input nilai <b>Pramuka</b> dan <b>Kultum</b> siswa.
                </p>
                <a href="penilaian_ekskul.php" class="btn btn-success rounded-pill">
                    Buka
                </a>
            </div>
        </div>

        <!-- ✅ PRESTASI SISWA (BARU) -->
        <div class="col-md-4">
            <div class="card card-menu shadow-sm text-center p-4">
                <div class="icon-menu mb-2">
                    <i class="bi bi-trophy"></i>
                </div>
                <h5 class="fw-bold">Prestasi Siswa</h5>
                <p class="text-muted small">
                    Input prestasi siswa untuk tampil otomatis di cetak rapor.
                </p>
                <a href="prestasi.php" class="btn btn-danger rounded-pill">
                    Buka
                </a>
            </div>
        </div>

        <!-- Cetak Rapor -->
        <div class="col-md-4">
            <div class="card card-menu shadow-sm text-center p-4">
                <div class="icon-menu mb-2">
                    <i class="bi bi-printer"></i>
                </div>
                <h5 class="fw-bold">Cetak Rapor</h5>
                <p class="text-muted small">
                    Cetak rapor sesuai format Excel Wali Kelas.
                </p>
                <a href="cetak_rapor.php" class="btn btn-secondary rounded-pill">
                    Cetak
                </a>
            </div>
        </div>

    </div>

    <footer class="mt-5">
        Sistem Rapor Digital SMA Al Ishlah — Panel Wali Kelas<br>
        © <?= date('Y'); ?> All rights reserved.
    </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
