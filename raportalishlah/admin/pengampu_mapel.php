<?php
// admin/pengampu_mapel.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

// ===============================
// AMBIL DATA MAPEL
// ===============================
$mapelStmt = $pdo->query("
    SELECT m.id, m.nama, m.kkm, m.teacher_id, t.name AS guru_nama
    FROM mapel m
    LEFT JOIN teachers t ON m.teacher_id = t.id
    ORDER BY m.nama ASC
");
$mapelList = $mapelStmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// AMBIL SEMUA GURU
// ===============================
$guruStmt = $pdo->query("SELECT id, name FROM teachers ORDER BY name ASC");
$guruList = $guruStmt->fetchAll(PDO::FETCH_ASSOC);

$message = "";

// ===============================
// MAPEL YANG SEDANG DI-EDIT
// ===============================
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;

// ===============================
// PROSES SIMPAN
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $mapelId   = (int)($_POST['mapel_id'] ?? 0);
    $teacherId = isset($_POST['teacher_id']) && $_POST['teacher_id'] !== ''
        ? (int)$_POST['teacher_id']
        : null; // kosong = copot guru

    if ($mapelId > 0) {
        $update = $pdo->prepare("UPDATE mapel SET teacher_id = ? WHERE id = ?");
        $update->execute([$teacherId, $mapelId]);

        $message = "Guru pengampu berhasil diperbarui.";
        $selectedMapelId = $mapelId;

        // refresh mapel
        $mapelStmt = $pdo->query("
            SELECT m.id, m.nama, m.kkm, m.teacher_id, t.name AS guru_nama
            FROM mapel m
            LEFT JOIN teachers t ON m.teacher_id = t.id
            ORDER BY m.nama ASC
        ");
        $mapelList = $mapelStmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $message = "Mapel belum dipilih.";
    }
}

// ===============================
// CARI MAPEL YANG SEDANG DI-EDIT
// ===============================
$currentMapel = null;
if ($selectedMapelId) {
    foreach ($mapelList as $m) {
        if ((int)$m['id'] === $selectedMapelId) {
            $currentMapel = $m;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengampu Mapel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_pengampu_mapel.css">
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

<div class="container1">

    <div class="card">

        <h3>Atur / Edit Guru Pengampu Mapel</h3>

        <?php if ($message): ?>
            <div class="alert"><?= $message ?></div>
        <?php endif; ?>

        <form method="post">

            <div class="form-row">

                <div class="form-group">
                    <label>Mapel</label>
                    <select name="mapel_id" required>
                        <option value="">-- Pilih Mapel --</option>
                        <?php foreach ($mapelList as $m): ?>
                            <option value="<?= $m['id']; ?>"
                                <?= ($selectedMapelId == $m['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($m['nama']); ?> 
                                (Guru saat ini:
                                <?= $m['guru_nama'] ? htmlspecialchars($m['guru_nama']) : 'Belum ditetapkan' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Guru Pengampu</label>
                    <select name="teacher_id">
                        <option value="">— Kosongkan / Copot Guru Pengampu —</option>

                        <?php foreach ($guruList as $g): ?>
                            <option value="<?= $g['id']; ?>"
                                <?php if ($currentMapel && (int)$currentMapel['teacher_id'] === (int)$g['id']) echo 'selected'; ?>>
                                <?= htmlspecialchars($g['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">
                        Pilih guru atau pilih “Kosongkan / Copot” untuk menghapus guru pengampu.
                    </div>
                </div>

            </div>

            <button type="submit" class="btn-submit">Simpan Perubahan</button>
        </form>
    </div>
</div>


<div class="box-wrapper1">
    <div class="left-side">
        <h3>Daftar Mapel & Guru Pengampu</h3>
    </div>

    <div class="container">
        <div class="custom-card">
            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <tr>
                            <th>ID</th>
                            <th>Nama Mapel</th>
                            <th>KKM</th>
                            <th>Guru Pengampu</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($mapelList as $m): ?>
                            <tr>
                                <td><?= $m['id']; ?></td>
                                <td><?= htmlspecialchars($m['nama']); ?></td>
                                <td><?= (int)$m['kkm']; ?></td>
                                <td>
                                    <?= $m['guru_nama']
                                        ? htmlspecialchars($m['guru_nama'])
                                        : '<span class="text-muted">Belum ditetapkan</span>' ?>
                                </td>
                                <td class="text-center">
                                    <a href="pengampu_mapel.php?mapel_id=<?= $m['id']; ?>" class="btn-ubah">
                                        Ubah / Copot
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($mapelList)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada data mapel.</td>
                            </tr>
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
