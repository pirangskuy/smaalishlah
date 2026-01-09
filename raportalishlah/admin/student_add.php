<?php
// admin/student_add.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama          = trim($_POST['nama'] ?? '');
    $nis           = trim($_POST['nis'] ?? '');
    $nisn          = trim($_POST['nisn'] ?? '');
    $kelas         = $_POST['kelas'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;

    // Validasi sederhana
    if ($nama === '' || $nis === '' || $kelas === '') {
        $error = "Nama, NIS, dan Kelas wajib diisi.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO siswa (nama, nis, nisn, kelas, tanggal_lahir)
                VALUES (:nama, :nis, :nisn, :kelas, :tanggal_lahir)
            ");
            $stmt->execute([
                ':nama'          => $nama,
                ':nis'           => $nis,
                ':nisn'          => $nisn !== '' ? $nisn : null,
                ':kelas'         => $kelas,          // hanya 10 / 11 / 12
                ':tanggal_lahir' => $tanggal_lahir !== '' ? $tanggal_lahir : null,
            ]);

            $message = "Siswa berhasil ditambahkan!";
        } catch (PDOException $e) {
            $error = "Gagal menambahkan siswa: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Siswa Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_student_add.css">
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

<div class="container">

    <div class="card">
        <div class="card-body">

            <h3>Tambah siswa</h3>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">

                <div class="mb-3">
                    <label>Nama Siswa</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>NIS</label>
                    <input type="text" name="nis" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>NISN</label>
                    <input type="text" name="nisn" class="form-control"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                </div>

                <div class="mb-3">
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-select" required>
                        <option value="">-- Pilih Kelas --</option>
                        <option value="10">10</option>
                        <option value="11">11</option>
                        <option value="12">12</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="form-control">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a href="student_list.php" class="btn-secondary">Lihat Data Siswa</a>
                    <a href="admin_dashboard.php" class="btn btn-outline-secondary">Kembali ke Dashboard</a>
                </div>

            </form>

        </div>
    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
