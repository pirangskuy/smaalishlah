<?php
// admin/wali_accounts.php
// Kelola akun login untuk wali kelas (role = 'walikelas')

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

$message = '';

// Ambil semua guru yang sudah ditetapkan sebagai wali kelas
$stmtWali = $pdo->query("
    SELECT t.id, t.name, t.kelas_wali
    FROM teachers t
    WHERE t.kelas_wali IS NOT NULL AND t.kelas_wali <> ''
    ORDER BY t.kelas_wali, t.name
");
$waliList = $stmtWali->fetchAll(PDO::FETCH_ASSOC);

// Ambil info akun walikelas yg sudah ada
// users: id, username, role, guru_id
$stmtUsers = $pdo->query("
    SELECT id, username, guru_id
    FROM users
    WHERE role = 'walikelas'
");
$akunMap = []; // [guru_id] => row user
foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $akunMap[$u['guru_id']] = $u;
}

// PROSES BUAT AKUN BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $guruId   = (int)($_POST['guru_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$guruId || $username === '' || $password === '') {
        $message = "<div class='alert alert-danger'>Guru, username, dan password wajib diisi.</div>";
    } else {
        // Cek apakah guru ini benar wali kelas
        $stmtCheckGuru = $pdo->prepare("
            SELECT id, name, kelas_wali
            FROM teachers
            WHERE id = ? AND kelas_wali IS NOT NULL AND kelas_wali <> ''
            LIMIT 1
        ");
        $stmtCheckGuru->execute([$guruId]);
        $g = $stmtCheckGuru->fetch(PDO::FETCH_ASSOC);

        if (!$g) {
            $message = "<div class='alert alert-danger'>Guru ini bukan wali kelas yang terdaftar.</div>";
        } else {
            // Cek username belum dipakai
            $stmtCheckUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmtCheckUser->execute([$username]);
            $countUser = (int)$stmtCheckUser->fetchColumn();

            if ($countUser > 0) {
                $message = "<div class='alert alert-danger'>Username sudah dipakai. Silakan gunakan username lain.</div>";
            } else {
                // Cek apakah guru ini sudah punya akun walikelas
                $stmtCheckWaliUser = $pdo->prepare("
                    SELECT COUNT(*) FROM users WHERE guru_id = ? AND role = 'walikelas'
                ");
                $stmtCheckWaliUser->execute([$guruId]);
                $countWaliUser = (int)$stmtCheckWaliUser->fetchColumn();

                if ($countWaliUser > 0) {
                    $message = "<div class='alert alert-warning'>Guru ini sudah memiliki akun wali kelas.</div>";
                } else {
                    // Insert akun baru
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO users (username, password, role, guru_id)
                        VALUES (?, ?, 'walikelas', ?)
                    ");
                    $stmtInsert->execute([$username, $hash, $guruId]);

                    // Refresh data akunMap
                    header("Location: wali_accounts.php?success=1");
                    exit;
                }
            }
        }
    }
}

// Jika sukses dari redirect
if (isset($_GET['success']) && $_GET['success'] == 1 && $message === '') {
    $message = "<div class='alert alert-success'>Akun wali kelas berhasil dibuat.</div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Akun Wali Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">Admin Rapor SMA Al Ishlah</a>
        <div class="d-flex">
            <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mb-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-1">Kelola Akun Wali Kelas</h2>
                    <p class="text-muted mb-0">
                        Halaman ini digunakan untuk membuat akun login dengan role <strong>walikelas</strong>,
                        terhubung ke guru yang sudah ditetapkan sebagai wali kelas.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="row mb-3">
            <div class="col-md-8">
                <?= $message ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Form buat akun baru -->
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    Buat Akun Wali Kelas
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="create">

                        <div class="mb-2">
                            <label class="form-label">Pilih Guru / Wali Kelas</label>
                            <select name="guru_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($waliList as $w): ?>
                                    <option value="<?= $w['id']; ?>">
                                        <?= htmlspecialchars($w['name']); ?> 
                                        (Kelas <?= htmlspecialchars($w['kelas_wali']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control form-control-sm" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control form-control-sm" required>
                        </div>

                        <button type="submit" class="btn btn-success btn-sm w-100 mt-2">
                            Simpan Akun
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar wali & akunnya -->
        <div class="col-md-8 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    Daftar Wali Kelas & Akun Login
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Guru</th>
                                <th>Kelas Wali</th>
                                <th>Username Akun Wali</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($waliList)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        Belum ada guru yang ditetapkan sebagai wali kelas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; ?>
                                <?php foreach ($waliList as $w): 
                                    $akun = $akunMap[$w['id']] ?? null;
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($w['name']); ?></td>
                                        <td><?= htmlspecialchars($w['kelas_wali']); ?></td>
                                        <td>
                                            <?php if ($akun): ?>
                                                <span class="badge text-bg-success">
                                                    <?= htmlspecialchars($akun['username']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-danger small">
                                                    Belum ada akun wali kelas
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p class="text-muted mb-0" style="font-size: .8rem;">
                        Catatan: Bila satu guru merangkap guru mapel dan wali kelas,
                        dianjurkan membuat <strong>dua akun berbeda</strong>:
                        satu dengan role <code>guru</code>, satu dengan role <code>walikelas</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
