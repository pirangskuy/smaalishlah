<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$stmt = $pdo->query("SELECT * FROM teachers ORDER BY name ASC");
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Guru - Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_teacher_list.css">

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
        <p class="total-text">Total: <?= count($teachers); ?> guru</p>
    </div>
</div>


<div class="box-wrapper1">
    <div class="top-bar">
        <div class="left-side">
            <h3>Daftar Guru</h3>
        </div>
        <div class="right-side">
            <a href="teacher_add.php" class="btn-tambah">+ Tambah Guru</a>
        </div>
    </div>

    <div class="container">
        <div class="custom-card">
            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas Wali</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($teachers as $t): ?>
                        <tr>
                            <td class="text-center"><?= $t['id'] ?></td>
                            <td><?= htmlspecialchars($t['name']) ?></td>
                            <td><?= htmlspecialchars($t['username']) ?></td>
                            <td><?= htmlspecialchars($t['mapel']) ?></td>
                            <td><?= $t['kelas_wali'] ? htmlspecialchars($t['kelas_wali']) : '-' ?></td>

                            <td class="text-center">
                                <a href="teacher_edit.php?id=<?= $t['id'] ?>" class="btn-edit">
                                    Edit
                                </a>

                                <a href="teacher_delete.php?id=<?= $t['id'] ?>"
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
