<?php
// admin/student_edit.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

// Pastikan ID ada
if (!isset($_GET['id'])) {
    die("ID siswa tidak ditemukan.");
}

$id = (int)$_GET['id'];

// Ambil data siswa berdasarkan ID
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$siswa) {
    die("Data siswa tidak ditemukan.");
}

$message = '';
$error = '';

// Proses apabila form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama          = trim($_POST['nama']);
    $nis           = trim($_POST['nis']);
    $nisn          = trim($_POST['nisn']);
    $kelas         = $_POST['kelas'];
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;

    if ($nama === '' || $nis === '' || $kelas === '') {
        $error = "Nama, NIS, dan Kelas wajib diisi.";
    } else {
        try {
            $update = $pdo->prepare("
                UPDATE siswa
                SET nama = :nama,
                    nis = :nis,
                    nisn = :nisn,
                    kelas = :kelas,
                    tanggal_lahir = :tanggal_lahir
                WHERE id = :id
            ");

            $update->execute([
                ':nama'          => $nama,
                ':nis'           => $nis,
                ':nisn'          => $nisn !== '' ? $nisn : null,
                ':kelas'         => $kelas,
                ':tanggal_lahir' => $tanggal_lahir !== '' ? $tanggal_lahir : null,
                ':id'            => $id
            ]);

            $message = "Data siswa berhasil diperbarui!";
        } catch (PDOException $e) {
            $error = "Gagal memperbarui data: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Data Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_student_edit.css">
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

            <h3>Edit Data Siswa</h3>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label>Nama Siswa</label>
                    <input type="text" name="nama" class="form-control"
                    value="<?= htmlspecialchars($siswa['nama']); ?>" required>
                </div>

                <div class="mb-3">
                    <input type="text" name="nis" class="form-control"
                        value="<?= htmlspecialchars($siswa['nis']); ?>" required>
                </div>

                <div class="mb-3">
                    <label>NISN</label>
                    <input type="text" name="nisn" class="form-control"
                           value="<?= htmlspecialchars($siswa['nisn']); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Kelas</label>
                    <select name="kelas" class="form-select" required>
                        <option value="10" <?= $siswa['kelas']=='10' ? 'selected' : '' ?>>10</option>
                        <option value="11" <?= $siswa['kelas']=='11' ? 'selected' : '' ?>>11</option>
                        <option value="12" <?= $siswa['kelas']=='12' ? 'selected' : '' ?>>12</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="form-control"
                        value="<?= htmlspecialchars($siswa['tanggal_lahir']); ?>">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a href="student_list.php" class="btn-secondary">Lihat Data Siswa</a>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
