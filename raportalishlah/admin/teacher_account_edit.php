<?php
// admin/teacher_account_edit.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

if (!isset($_GET['id'])) {
    die("ID akun tidak ditemukan.");
}

$userId = (int)$_GET['id'];
if ($userId <= 0) {
    die("ID akun tidak valid.");
}

// Ambil data user + guru terkait
$sql = "
    SELECT 
        u.id         AS user_id,
        u.username   AS username,
        u.role       AS role,
        u.guru_id    AS guru_id,
        t.name       AS teacher_name,
        t.mapel      AS teacher_mapel
    FROM users u
    LEFT JOIN teachers t ON u.guru_id = t.id
    WHERE u.id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$akun = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$akun) {
    die("Akun tidak ditemukan.");
}

if ($akun['role'] !== 'guru') {
    // Boleh saja kamu batasi: hanya akun guru yang diedit di sini
    die("Halaman ini khusus untuk akun guru.");
}

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameBaru   = trim($_POST['username'] ?? '');
    $passwordBaru   = trim($_POST['password_baru'] ?? '');
    $konfirmasiPass = trim($_POST['password_konfirmasi'] ?? '');

    if ($usernameBaru === '') {
        $error = "Username tidak boleh kosong.";
    } else {
        // Cek apakah username sudah dipakai user lain
        $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?");
        $cek->execute([$usernameBaru, $userId]);
        $sudahAda = $cek->fetchColumn();

        if ($sudahAda > 0) {
            $error = "Username sudah dipakai akun lain. Gunakan username lain.";
        } else {
            try {
                // Siapkan query dasar untuk update username saja dulu
                if ($passwordBaru === '') {
                    // Hanya update username
                    $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $upd->execute([$usernameBaru, $userId]);
                    $message = "Username berhasil diperbarui.";
                } else {
                    // Ada password baru, cek konfirmasi
                    if ($passwordBaru !== $konfirmasiPass) {
                        $error = "Password baru dan konfirmasi tidak sama.";
                    } else {
                        // Hash password baru
                        $hash = password_hash($passwordBaru, PASSWORD_BCRYPT);

                        $upd = $pdo->prepare("
                            UPDATE users 
                            SET username = ?, password = ?
                            WHERE id = ?
                        ");
                        $upd->execute([$usernameBaru, $hash, $userId]);
                        $message = "Username dan password berhasil diperbarui.";
                    }
                }

            } catch (PDOException $e) {
                $error = "Gagal menyimpan perubahan: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Akun Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="teacher_accounts.php">Admin - Edit Akun Guru</a>
    <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
  </div>
</nav>

<div class="container">

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4 class="card-title mb-3">Edit Akun Guru</h4>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Nama Guru</label>
                <input type="text" class="form-control" 
                       value="<?= htmlspecialchars($akun['teacher_name'] ?? '(Belum terhubung ke data guru)'); ?>" 
                       disabled>
            </div>

            <div class="mb-3">
                <label class="form-label">Mapel</label>
                <input type="text" class="form-control" 
                       value="<?= htmlspecialchars($akun['teacher_mapel'] ?? '-'); ?>" 
                       disabled>
            </div>

            <form method="post" class="mt-3">

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control"
                           value="<?= htmlspecialchars($akun['username']); ?>" required>
                    <div class="form-text">
                        Username harus unik dan akan digunakan guru saat login.
                    </div>
                </div>

                <hr>

                <p class="text-muted mb-2">
                    Jika tidak ingin mengubah password, biarkan kolom di bawah ini kosong.
                </p>

                <div class="mb-3">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password_baru" class="form-control" autocomplete="new-password">
                </div>

                <div class="mb-3">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" name="password_konfirmasi" class="form-control" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
                <a href="teacher_accounts.php" class="btn btn-secondary">Kembali</a>

            </form>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
