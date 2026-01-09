<?php
// admin/teacher_add.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$message = "";
$error   = "";

// Proses jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name          = trim($_POST['name'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $passwordPlain = $_POST['password'] ?? '';
    $mapel         = trim($_POST['mapel'] ?? '');

    // Validasi sederhana
    if ($name === '' || $username === '' || $passwordPlain === '' || $mapel === '') {
        $error = "Semua field wajib diisi.";
    } else {
        try {
            // Cek username sudah dipakai di users atau teachers
            $cekUser = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $cekUser->execute([$username]);
            $existingUser = $cekUser->fetch();

            if ($existingUser) {
                $error = "Username sudah dipakai di tabel users. Gunakan username lain.";
            } else {
                $cekTeacher = $pdo->prepare("SELECT id FROM teachers WHERE username = ? LIMIT 1");
                $cekTeacher->execute([$username]);
                $existingTeacher = $cekTeacher->fetch();

                if ($existingTeacher) {
                    $error = "Username sudah dipakai di tabel teachers. Gunakan username lain.";
                } else {
                    // Semua aman, simpan guru + buat akun users
                    $pdo->beginTransaction();

                    // Hash password
                    $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

                    // 1) Insert ke TEACHERS
                    $stmtGuru = $pdo->prepare("
                        INSERT INTO teachers (name, username, password, mapel)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtGuru->execute([$name, $username, $passwordHash, $mapel]);

                    $teacherId = $pdo->lastInsertId();

                    // 2) Insert ke USERS (role guru)
                    $stmtUser = $pdo->prepare("
                        INSERT INTO users (username, password, role, guru_id)
                        VALUES (?, ?, 'guru', ?)
                    ");
                    $stmtUser->execute([$username, $passwordHash, $teacherId]);

                    $pdo->commit();

                    $message = "Guru <strong>" . htmlspecialchars($name) . "</strong> berhasil ditambahkan beserta akun login guru.";
                }
            }

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
    <title>Tambah Guru - Rapor SMA Al-Ishlah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_teacher_add.css">
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
                    <i class="bi bi-shield-lock"></i> Admin
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

            <h3>Tambah Guru</h3>

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

                <div class="form-group">
                    <label>Nama Guru</label>
                    <input type="text" name="name" required
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    <small>Username ini akan dipakai guru untuk login.</small>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <small>Beritahu password ini ke guru, dan minta segera diganti setelah login.</small>
                </div>

                <div class="form-group">
                    <label>Mata Pelajaran</label>
                    <input type="text" name="mapel" required
                        value="<?= htmlspecialchars($_POST['mapel'] ?? '') ?>">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a href="teacher_list.php" class="btn-secondary">Lihat Data Guru</a>
                    <a href="admin_dashboard.php" class="btn btn-outline-secondary">Kembali ke Dashboard</a>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
