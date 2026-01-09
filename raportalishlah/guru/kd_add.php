<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

$guruId = $_SESSION['user']['guru_id'] ?? null;

// Ambil semua mapel yang diampu guru
$stmtMapel = $pdo->prepare("SELECT * FROM mapel WHERE teacher_id = ?");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mapel_id = $_POST['mapel_id'] ?? '';
    $kd_ke = $_POST['kd_ke'] ?? '';
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($mapel_id == '' || $kd_ke == '' || $deskripsi == '') {
        $message = "<div class='alert alert-danger'>Semua field wajib diisi.</div>";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO kd_pengetahuan (mapel_id, kd_ke, deskripsi) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$mapel_id, $kd_ke, $deskripsi]);

        header("Location: kd_list.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tambah KD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-4">
    <h3>Tambah KD Pengetahuan</h3>

    <a href="kd_list.php" class="btn btn-secondary btn-sm mb-3">Kembali</a>

    <?= $message ?>

    <form method="post" class="card p-3 shadow-sm">

        <label class="form-label">Mata Pelajaran</label>
        <select name="mapel_id" class="form-select" required>
            <option value="">-- Pilih Mapel --</option>
            <?php foreach ($mapelList as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="form-label mt-3">KD Ke-</label>
        <input type="number" name="kd_ke" class="form-control" min="1" required>

        <label class="form-label mt-3">Deskripsi KD</label>
        <textarea name="deskripsi" class="form-control" rows="3" required></textarea>

        <button class="btn btn-success mt-3">Simpan KD</button>
    </form>
</div>

</body>
</html>
