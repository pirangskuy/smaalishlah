<?php
// admin/wali_add.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$message = "";

// Ambil daftar guru yang belum punya kelas_wali
$stmt = $pdo->query("
    SELECT id, name, mapel 
    FROM teachers 
    WHERE kelas_wali IS NULL 
    ORDER BY name
");
$guruList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses simpan wali kelas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $guru_id = $_POST['guru_id'] ?? '';
    $kelas   = $_POST['kelas_wali'] ?? '';

    if ($guru_id === '' || $kelas === '') {
        $message = '<div class="alert alert-danger">Semua field wajib diisi.</div>';
    } else {
        $update = $pdo->prepare("UPDATE teachers SET kelas_wali = ? WHERE id = ?");
        $update->execute([$kelas, $guru_id]);

        header("Location: wali_list.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Wali Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_wali_add.css">
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

            <h3>Tambah Wali Kelas</h3>

            <?= $message ?>

            <form method="post">

                <div class="mb-3">
                    <label class="form-label">Pilih Guru</label>
                    <select name="guru_id" class="form-select" required>
                        <option value="">-- pilih guru --</option>
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?= $g['id'] ?>">
                                <?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['mapel']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kelas Yang Diampu</label>
                    <select name="kelas_wali" class="form-select" required>
                        <option value="">-- pilih kelas --</option>
                        <option value="10">10</option>
                        <option value="11">11</option>
                        <option value="12">12</option>
                    </select>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a href="wali_list.php" class="btn-secondary">Lihat Data Wali Kelas</a>
                    <a href="admin_dashboard.php" class="btn btn-outline-secondary">Kembali ke Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
