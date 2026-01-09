<?php
// admin/wali_edit.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

if (!isset($_GET['id'])) {
    die("ID tidak ditemukan");
}

$id = (int)$_GET['id'];
if ($id <= 0) {
    die("ID tidak valid");
}

// Ambil data guru
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->execute([$id]);
$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    die("Guru tidak ditemukan");
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $kelas_wali = $_POST['kelas_wali'] ?? '';

    // Jika kelas != '' → cek apakah kelas sudah dipakai wali lain
    if ($kelas_wali !== '') {
        $cek = $pdo->prepare("
            SELECT name FROM teachers 
            WHERE kelas_wali = ? AND id != ?
            LIMIT 1
        ");
        $cek->execute([$kelas_wali, $id]);
        $sudahAda = $cek->fetch(PDO::FETCH_ASSOC);

        if ($sudahAda) {
            $error = "Kelas $kelas_wali sudah memiliki wali: <b>" . htmlspecialchars($sudahAda['name']) . "</b>!";
        }
    }

    // Jika tidak ada error → update
    if (!$error) {
        $stmtUpd = $pdo->prepare("UPDATE teachers SET kelas_wali = ? WHERE id = ?");
        $stmtUpd->execute([
            $kelas_wali === '' ? null : $kelas_wali,
            $id
        ]);

        $success = "Wali kelas berhasil diperbarui!";

        // Refresh data guru
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        $guru = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Daftar kelas: hanya 10,11,12
$kelasList = ['10', '11', '12'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Wali Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_wali_edit.css">
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

            <h3>Edit Wali Kelas</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <p><b>Nama Guru:</b> <?= htmlspecialchars($guru['name']); ?></p>
            <p><b>Mapel:</b> <?= htmlspecialchars($guru['mapel']); ?></p>

            <form method="post">

                <div class="mb-3">
                    <label class="form-label">Pilih Kelas Wali</label>
                    <select name="kelas_wali" class="form-select">
                        <option value="">— Tidak Menjadi Wali —</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?= $k; ?>"
                                <?= ($guru['kelas_wali'] === $k ? 'selected' : ''); ?>>
                                <?= $k; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Pilih kelas 10 / 11 / 12. Satu kelas hanya boleh mempunyai satu wali.
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    <a href="wali_list.php" class="btn-secondary">Lihat Data Wali Kelas</a>
                </div>

            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
