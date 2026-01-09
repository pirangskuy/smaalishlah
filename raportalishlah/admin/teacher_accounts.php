<?php
// admin/teacher_accounts.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

/*
   Daftar akun guru diambil dari tabel users (role = 'guru'),
   lalu di-join ke tabel teachers lewat kolom guru_id.
*/

$sql = "
    SELECT 
        u.id          AS user_id,
        u.username    AS username,
        u.created_at  AS user_created_at,
        t.id          AS teacher_id,
        t.name        AS teacher_name,
        t.mapel       AS teacher_mapel
    FROM users u
    LEFT JOIN teachers t ON u.guru_id = t.id
    WHERE u.role = 'guru'
    ORDER BY t.name ASC, u.username ASC
";

$stmt = $pdo->query($sql);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Akun Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="admin_dashboard.php">Admin - Daftar Akun Guru</a>
    <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
  </div>
</nav>

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Daftar Akun Guru</h4>
        <!-- nanti bisa ditambah tombol generate akun massal -->
    </div>

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width:5%;">ID User</th>
                        <th style="width:25%;">Nama Guru</th>
                        <th style="width:15%;">Username</th>
                        <th style="width:20%;">Mata Pelajaran</th>
                        <th style="width:20%;">Dibuat Pada</th>
                        <th style="width:15%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Belum ada akun guru di tabel users (role = 'guru').
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($accounts as $row): ?>
                        <tr>
                            <td><?= (int)$row['user_id']; ?></td>
                            <td>
                                <?= $row['teacher_name']
                                    ? htmlspecialchars($row['teacher_name'])
                                    : '<span class="text-danger">Belum terhubung ke data guru</span>'; ?>
                            </td>
                            <td><?= htmlspecialchars($row['username']); ?></td>
                            <td>
                                <?= $row['teacher_mapel']
                                    ? htmlspecialchars($row['teacher_mapel'])
                                    : '<span class="text-muted">-</span>'; ?>
                            </td>
                            <td><?= htmlspecialchars($row['user_created_at']); ?></td>
                            <td>
                                <a href="teacher_account_edit.php?id=<?= (int)$row['user_id']; ?>" 
                                   class="btn btn-warning btn-sm">
                                    Edit Akun
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm mt-3">
                &laquo; Kembali ke Dashboard
            </a>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
