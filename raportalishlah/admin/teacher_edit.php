<?php
// admin/teacher_edit.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID guru tidak valid.");
}

// Ambil data guru
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Guru tidak ditemukan!");
}

$message = "";
$error   = "";

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name          = trim($_POST['name'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $passwordPlain = $_POST['password'] ?? '';
    $mapel         = trim($_POST['mapel'] ?? '');

    if ($name === '' || $username === '' || $mapel === '') {
        $error = "Nama, Username, dan Mata Pelajaran wajib diisi.";
    } else {
        try {
            $pdo->beginTransaction();

            // Cek bentrok username di TEACHERS selain guru ini
            $cekT = $pdo->prepare("SELECT id FROM teachers WHERE username = ? AND id <> ? LIMIT 1");
            $cekT->execute([$username, $id]);
            $dupT = $cekT->fetch();

            if ($dupT) {
                throw new Exception("Username sudah dipakai guru lain di tabel teachers.");
            }

            // Cek bentrok username di USERS selain guru ini
            $cekU = $pdo->prepare("SELECT id, guru_id FROM users WHERE username = ? AND guru_id <> ? LIMIT 1");
            $cekU->execute([$username, $id]);
            $dupU = $cekU->fetch();

            if ($dupU) {
                throw new Exception("Username sudah dipakai akun lain di tabel users.");
            }

            // 1) Update TEACHERS
            if ($passwordPlain !== '') {
                $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

                $stmtUpT = $pdo->prepare("
                    UPDATE teachers 
                    SET name = ?, username = ?, password = ?, mapel = ?
                    WHERE id = ?
                ");
                $stmtUpT->execute([$name, $username, $passwordHash, $mapel, $id]);
            } else {
                $stmtUpT = $pdo->prepare("
                    UPDATE teachers 
                    SET name = ?, username = ?, mapel = ?
                    WHERE id = ?
                ");
                $stmtUpT->execute([$name, $username, $mapel, $id]);
            }

            // 2) Update USERS yang terkait guru ini (berdasarkan guru_id)
            $stmtUser = $pdo->prepare("SELECT * FROM users WHERE guru_id = ? LIMIT 1");
            $stmtUser->execute([$id]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($userRow) {
                // Susun query update users
                if ($passwordPlain !== '') {
                    // Ganti password juga
                    $stmtUpU = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, password = ?
                        WHERE id = ?
                    ");
                    $stmtUpU->execute([
                        $username,
                        $passwordHash,
                        $userRow['id']
                    ]);
                } else {
                    // Hanya ganti username
                    $stmtUpU = $pdo->prepare("
                        UPDATE users 
                        SET username = ?
                        WHERE id = ?
                    ");
                    $stmtUpU->execute([
                        $username,
                        $userRow['id']
                    ]);
                }
            }

            $pdo->commit();

            $message = "Data guru berhasil diperbarui!";
            // Refresh data guru
            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Terjadi kesalahan database: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Data Guru - Rapor SMA Al-Ishlah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_teacher_edit.css">
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

            <h3>Edit Guru</h3>

            <?php if ($message): ?>
                        <div class="alert alert-success"><?= $message ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

            <form method="post">

                <div class="form-group">
                    <label>Nama Guru</label>
                    <input type="text" name="name" class="form-control"
                    value="<?= htmlspecialchars($teacher['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control"
                        value="<?= htmlspecialchars($teacher['username']) ?>" required>
                    <small>Username ini akan dipakai guru untuk login.</small>
                </div>

                <div class="form-group">
                    <label>Password (opsional)</label>
                    <input type="password" name="password" class="form-control">
                    <small>Kosongkan jika tidak ingin mengubah password.</small>
                </div>

                <div class="form-group">
                    <label>Mata Pelajaran</label>
                    <input type="text" name="mapel" class="form-control"
                        value="<?= htmlspecialchars($teacher['mapel']) ?>" required>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    <a href="teacher_list.php" class="btn-secondary">Lihat Data Guru</a>
                </div>

            </form>

        </div>
    </div>

</div>



<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
