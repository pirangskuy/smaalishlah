<?php
// auth/change_password.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

requireLogin();
requireAdmin();

$errors  = [];
$success = "";

// Tambah admin baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if ($username === '' || $password === '' || $password_confirm === '') {
            $errors[] = "Username dan password wajib diisi.";
        } elseif ($password !== $password_confirm) {
            $errors[] = "Konfirmasi password tidak sama.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password minimal 6 karakter.";
        } else {
            // Cek username sudah dipakai atau belum
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Username sudah digunakan.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
                $stmt->execute([$username, $hash]);
                $success = "Akun admin baru berhasil dibuat.";
            }
        }
    }

    // Update admin (username dan/atau password)
    if ($action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if ($id <= 0) {
            $errors[] = "ID admin tidak valid.";
        } elseif ($username === '') {
            $errors[] = "Username tidak boleh kosong.";
        } else {
            // Cek apakah admin ada
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
            $stmt->execute([$id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                $errors[] = "Akun admin tidak ditemukan.";
            } else {
                // Cek username bentrok dengan admin lain
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "Username sudah digunakan admin lain.";
                } else {
                    // Siapkan query update
                    if ($password !== '') {
                        if ($password !== $password_confirm) {
                            $errors[] = "Konfirmasi password tidak sama.";
                        } elseif (strlen($password) < 6) {
                            $errors[] = "Password minimal 6 karakter.";
                        } else {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE id = ? AND role = 'admin'");
                            $stmt->execute([$username, $hash, $id]);
                            $success = "Akun admin berhasil diperbarui (username & password).";
                        }
                    } else {
                        // Hanya ubah username
                        $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ? AND role = 'admin'");
                        $stmt->execute([$username, $id]);
                        $success = "Akun admin berhasil diperbarui (username).";
                    }
                }
            }
        }
    }
}

// Ambil semua akun admin
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'admin' ORDER BY id ASC");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usernameLogin = $_SESSION['user']['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Akun Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/auth_change_password.css">
    
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
                <span class="navbar-text me-3 text-white">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($usernameLogin) ?>
                </span>

                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>


<div class="wrapper">

    <div class="page-header">
        <h2 class="page-title">Kelola Akun Admin</h2>
        <p class="page-subtitle">
            Manajemen akun administrator sistem
        </p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert-box alert-error">
        <div class="alert-content">
            <strong>Terjadi Kesalahan</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-box alert-success">
            <div class="alert-content">
                <strong>Berhasil</strong>
                <p><?= htmlspecialchars($success) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Tambah Akun Admin Baru</div>
        <div class="card-body">
            <form method="post" class="form-grid-4">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Ulangi Password</label>
                <input type="password" name="password_confirm" required>
            </div>

            <div class="form-group align-bottom">
                <label>&nbsp;</label>
                <button type="submit" class="btn-save-admin">
                    Simpan
                </button>
            </div>
        </form>

        </div>
    </div>

    <div class="card">
        <div class="card-header">Daftar Akun Admin</div>
        <div class="card-body">

            <?php if (empty($admins)): ?>
                <p class="empty-text">Belum ada akun admin lain.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                        <tr>
                            <th class="td-action">ID</th>
                            <th>Username</th>
                            <th>Password Baru</th>
                            <th>Ulangi Password</th>
                            <th class="td-action">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($admins as $a): ?>
                            <tr>
                                <form method="post">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">

                                    <td class="td-action"><?= (int)$a['id'] ?></td>
                                    <td>
                                        <input type="text" name="username"
                                               value="<?= htmlspecialchars($a['username']) ?>" required>
                                    </td>
                                    <td>
                                        <input type="password" name="password"
                                               placeholder="Kosongkan jika tidak diubah">
                                    </td>
                                    <td>
                                        <input type="password" name="password_confirm"
                                               placeholder="Ulangi jika diisi">
                                    </td>
                                    <td class="td-action">
                                        <button type="submit" class="btn-save-table">
                                            Simpan
                                        </button>
                                    </td>

                                </form>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <a href="../admin/admin_dashboard.php" class="link-back-dashboard">
                Kembali ke Dashboard
            </a>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
