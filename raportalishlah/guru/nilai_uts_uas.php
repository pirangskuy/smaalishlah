<?php
// guru/nilai_uts_uas.php
// Input nilai UTS & UAS per siswa untuk satu mapel & kelas

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

// Ambil guru_id dari session user
$guruId = $_SESSION['user']['guru_id'] ?? null;

if (!$guruId) {
    echo "<p style='color:red;'>Akun guru belum terhubung ke data guru (guru_id kosong di tabel users). Hubungi admin.</p>";
    exit;
}

// Data guru (untuk tampilan di navbar)
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);
$namaGuru = $guru['name'] ?? $_SESSION['user']['username'];

// Mapel yang diampu guru ini
$stmtMapel = $pdo->prepare("
    SELECT id, nama, kkm
    FROM mapel
    WHERE teacher_id = ?
    ORDER BY nama
");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Kelas: 10, 11, 12
$kelasList = ['10', '11', '12'];

// Ambil filter
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
$selectedKelas   = isset($_GET['kelas']) ? $_GET['kelas'] : '';

$siswaList  = [];
$nilaiUTS   = [];
$nilaiUAS   = [];
$mapelAktif = null;

if ($selectedMapelId && $selectedKelas !== '') {

    // Detail mapel aktif
    $stmtOneMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE id = ? LIMIT 1");
    $stmtOneMapel->execute([$selectedMapelId]);
    $mapelAktif = $stmtOneMapel->fetch(PDO::FETCH_ASSOC);

    // Siswa di kelas tsb
    $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($siswaList)) {
        $siswaIds = array_column($siswaList, 'id');
        $in = implode(',', array_fill(0, count($siswaIds), '?'));

        // Ambil nilai UTS & UAS yang sudah pernah disimpan
        $params = $siswaIds;
        array_unshift($params, $selectedMapelId); // mapel_id di depan

        $sqlNilai = "
            SELECT siswa_id, uts, uas
            FROM nilai_uts_uas
            WHERE mapel_id = ?
              AND siswa_id IN ($in)
        ";

        $stmtNilai = $pdo->prepare($sqlNilai);
        $stmtNilai->execute($params);
        $rows = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $sid = $row['siswa_id'];
            $nilaiUTS[$sid] = $row['uts'];
            $nilaiUAS[$sid] = $row['uas'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Nilai UTS & UAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="guru_dashboard.php">Dashboard Guru</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-white">
                        <?= htmlspecialchars($namaGuru); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../auth/logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">

    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
        <div class="alert alert-success">
            Nilai UTS & UAS berhasil disimpan.
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="row mb-3">
        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-1">Input Nilai UTS & UAS</h2>
                    <p class="text-muted mb-0">
                        Nilai ini sesuai dengan kolom UTS dan UAS di Excel. Nanti akan digabung dengan nilai harian (per KD) untuk menghitung nilai akhir.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Mapel & Kelas -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">

                    <?php if (empty($mapelList)): ?>
                        <div class="alert alert-warning mb-3">
                            Anda belum terdaftar mengampu mata pelajaran apapun di tabel <code>mapel</code>.
                            Silakan hubungi admin.
                        </div>
                    <?php endif; ?>

                    <form method="get" class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Mata Pelajaran</label>
                            <select name="mapel_id" class="form-select" required>
                                <option value="">-- Pilih Mapel --</option>
                                <?php foreach ($mapelList as $m): ?>
                                    <option value="<?= $m['id']; ?>"
                                        <?= $selectedMapelId == $m['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($m['nama']); ?> (KKM: <?= (int)$m['kkm']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Kelas</label>
                            <select name="kelas" class="form-select" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelasList as $k): ?>
                                    <option value="<?= $k; ?>" <?= $selectedKelas === $k ? 'selected' : ''; ?>>
                                        <?= $k; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Input UTS & UAS -->
    <?php if ($selectedMapelId && $selectedKelas !== ''): ?>

        <?php if (empty($siswaList)): ?>
            <div class="alert alert-warning">
                Belum ada siswa di kelas <strong><?= htmlspecialchars($selectedKelas); ?></strong>.
            </div>
        <?php else: ?>

            <div class="row mb-5">
                <div class="col">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            Input Nilai UTS & UAS
                            <br>
                            <small>
                                Mapel: <?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?>
                                (KKM: <?= (int)($mapelAktif['kkm'] ?? 0); ?>)
                                | Kelas: <?= htmlspecialchars($selectedKelas); ?>
                            </small>
                        </div>
                        <div class="card-body table-responsive">

                            <form method="post" action="simpan_uts_uas.php">
                                <input type="hidden" name="mapel_id" value="<?= $selectedMapelId; ?>">
                                <input type="hidden" name="kelas" value="<?= htmlspecialchars($selectedKelas); ?>">

                                <table class="table table-bordered table-sm align-middle">
                                    <thead class="table-light">
                                        <tr class="text-center">
                                            <th style="min-width: 200px;">Nama Siswa</th>
                                            <th style="width: 120px;">UTS</th>
                                            <th style="width: 120px;">UAS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($siswaList as $s): 
                                            $sid = $s['id'];
                                            $vUTS = $nilaiUTS[$sid] ?? null;
                                            $vUAS = $nilaiUAS[$sid] ?? null;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($s['nama']); ?></td>
                                                <td class="text-center">
                                                    <input type="number"
                                                           name="uts[<?= $sid; ?>]"
                                                           class="form-control form-control-sm text-center"
                                                           min="0" max="100" step="1"
                                                           value="<?= $vUTS !== null ? (float)$vUTS : ''; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <input type="number"
                                                           name="uas[<?= $sid; ?>]"
                                                           class="form-control form-control-sm text-center"
                                                           min="0" max="100" step="1"
                                                           value="<?= $vUAS !== null ? (float)$vUAS : ''; ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="mt-3 text-end">
                                    <button type="submit" class="btn btn-success">
                                        Simpan Nilai UTS & UAS
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
