<?php
// admin/student_list.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

// Ambil filter kelas dari GET (boleh kosong = semua)
$filterKelas = $_GET['kelas'] ?? '';

// Siapkan query dasar
if ($filterKelas === '10' || $filterKelas === '11' || $filterKelas === '12') {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY kelas ASC, nama ASC");
    $stmt->execute([$filterKelas]);
} else {
    // Semua kelas
    $filterKelas = ''; // pastikan bersih
    $stmt = $pdo->query("SELECT * FROM siswa ORDER BY kelas ASC, nama ASC");
}

$siswa = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Siswa</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_student_list.css">
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
        <div class="filter-card-keren">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="kelas" class="col-form-label">Filter Kelas:</label>
                    </div>
                    <div class="col-auto">
                        <select name="kelas" id="kelas" class="form-select form-select-sm">
                            <option value="" <?= $filterKelas === '' ? 'selected' : '' ?>>Semua Kelas</option>
                            <option value="10" <?= $filterKelas === '10' ? 'selected' : '' ?>>10</option>
                            <option value="11" <?= $filterKelas === '11' ? 'selected' : '' ?>>11</option>
                            <option value="12" <?= $filterKelas === '12' ? 'selected' : '' ?>>12</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
                        <a href="student_list.php" class="btn btn-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="box-wrapper1">
    <div class="top-bar">
        <div class="left-side">
            <h3>Daftar Siswa</h3>
            <a href="student_import.php" class="btn btn-outline-primary btn-sm">⬆ Import Siswa (CSV)</a>
        </div>
        <div class="right-side">
            <a href="student_add.php" class="btn-tambah">+ Tambah Siswa</a>
        </div>
    </div>

     <div class="container">
        <div class="custom-card">
            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="text-center">ID</th>
                            <th >Nama</th>
                            <th >NIS</th>
                            <th >NISN</th>
                            <th >Kelas</th>
                            <th >Tanggal Lahir</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                <?php if (empty($siswa)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada data siswa.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($siswa as $s): ?>
                        <tr>
                            <td class="text-center"><?= $s['id'] ?></td>
                            <td><?= htmlspecialchars($s['nama']); ?></td>
                            <td><?= htmlspecialchars($s['nis']); ?></td>
                            <td><?= htmlspecialchars($s['nisn']); ?></td>
                            <td><?= htmlspecialchars($s['kelas']); ?></td>
                            <td><?= htmlspecialchars($s['tanggal_lahir']); ?></td>

                            <td class="text-center">
                                <a href="student_edit.php?id=<?= $s['id'] ?>" class="btn-edit">
                                    Edit
                                </a>

                                <a href="student_delete.php?id=<?= $s['id'] ?>"
                                onclick="return confirm('Yakin hapus guru ini?')"
                                class="btn-delete">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>

                </table>

            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
