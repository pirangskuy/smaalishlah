<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("<h3>Akses ditolak! Admin saja.</h3>");
}

$wali = $pdo->query("SELECT * FROM teachers WHERE kelas_wali IS NOT NULL ORDER BY kelas_wali")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Wali Kelas - SMA Al Ishlah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_wali_list.css">

</head>
<body class="bg-light">
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

        <div class="collapse navbar-collapse justify-content-end" id="navbarMain">
            <div class="d-flex align-items-center gap-3">
                <span class="admin-mode-badge">
                    <i class="bi bi-shield-lock"></i> Mode Admin
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="box-wrapper">
    <div class="top-bar">
        <a href="admin_dashboard.php" class="btn-back">Kembali ke Dashboard</a>
    </div>
</div>

<div class="box-wrapper1">
    <div class="top-bar">
        <div class="left-side">
            <h3>Daftar Wali Kelas</h3>
        </div>
        <div class="right-side">
            <a href="wali_add.php" class="btn-tambah">+ Tambah Wali Kelas</a>
        </div>
    </div>

    <div class="container">
        <div class="custom-card">
            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Nama Guru</th>
                            <th>Username</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas Wali</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach($wali as $w): ?>
                        <tr>
                            <td class="text-center"><?= $w['id'] ?></td>
                            <td><?= $w['name'] ?></td>
                            <td><?= $w['username'] ?></td>
                            <td><?= $w['mapel'] ?></td>
                            <td><?= $w['kelas_wali'] ?></td>
                        
                            <td class="text-center">
                                <a href="wali_edit.php?id=<?= $w['id'] ?>" class="btn-edit">
                                    Edit
                                </a>

                                <a href="wali_delete.php?id=<?= $w['id'] ?>"
                                onclick="return confirm('Yakin hapus guru ini?')"
                                class="btn-delete">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
