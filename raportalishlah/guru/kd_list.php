<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    die("Akun guru tidak valid (guru_id kosong). Hubungi admin.");
}

// Ambil semua mapel yang diampu guru ini
$stmtMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE teacher_id = ? ORDER BY nama");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Jika guru belum dihubungkan dengan mapel apa pun
if (empty($mapelList)) {
    $kdList = [];
} else {
    // Ambil semua KD Pengetahuan untuk mapel-mapel guru ini
    $mapelIds = array_column($mapelList, 'id');
    $in = implode(',', array_fill(0, count($mapelIds), '?'));

    $stmtKd = $pdo->prepare("
        SELECT id, mapel_id, kd_ke, deskripsi
        FROM kd_pengetahuan
        WHERE mapel_id IN ($in)
        ORDER BY mapel_id, kd_ke
    ");
    $stmtKd->execute($mapelIds);
    $kdList = $stmtKd->fetchAll(PDO::FETCH_ASSOC);
}

// Untuk lookup nama mapel lebih cepat
$mapelNamaById = [];
foreach ($mapelList as $m) {
    $mapelNamaById[$m['id']] = $m['nama'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola KD Pengetahuan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">
    <h3>KD Pengetahuan Mata Pelajaran Anda</h3>
    <p>Guru: <strong><?= htmlspecialchars($_SESSION['user']['username']); ?></strong></p>

    <?php if (empty($mapelList)): ?>
        <div class="alert alert-warning">
            Anda belum dihubungkan dengan mata pelajaran apa pun di tabel <code>mapel</code>. 
            Silakan hubungi admin untuk mengatur guru pengampu mapel.
        </div>
    <?php else: ?>
        <a href="guru_dashboard.php" class="btn btn-secondary btn-sm mb-3">Kembali</a>
        <a href="kd_add.php" class="btn btn-primary btn-sm mb-3">Tambah KD Pengetahuan</a>

        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark">
                <tr>
                    <th style="width: 180px;">Mapel</th>
                    <th style="width: 70px;">KD Ke-</th>
                    <th>Deskripsi</th>
                    <th style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kdList)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            Belum ada KD Pengetahuan untuk mapel yang Anda ampu.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kdList as $kd): 
                        $mapelNama = $mapelNamaById[$kd['mapel_id']] ?? 'Mapel tidak diketahui';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($mapelNama); ?></td>
                            <td class="text-center"><?= (int)$kd['kd_ke']; ?></td>
                            <td><?= htmlspecialchars($kd['deskripsi']); ?></td>
                            <td class="text-center">
                                <!-- Nanti kalau mau bisa tambah tombol Edit -->
                                <a href="kd_delete.php?id=<?= $kd['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Hapus KD ini?');">
                                    Hapus
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
