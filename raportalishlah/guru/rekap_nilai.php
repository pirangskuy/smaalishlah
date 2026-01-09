<?php
// guru/rekap_nilai.php
// Rekap gabungan Pengetahuan + Keterampilan per mapel, per kelas

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

// Ambil data guru (untuk nama di navbar)
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);
$namaGuru = $guru['name'] ?? $_SESSION['user']['username'];

// Ambil mapel yang diampu guru ini
$stmtMapel = $pdo->prepare("
    SELECT id, nama, kkm
    FROM mapel
    WHERE teacher_id = ?
    ORDER BY nama
");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Kelas fix 10/11/12
$kelasList = ['10', '11', '12'];

// Ambil filter
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
$selectedKelas   = isset($_GET['kelas']) ? $_GET['kelas'] : '';

$siswaList     = [];
$nilaiPknMap   = []; // pengetahuan
$nilaiKetMap   = []; // keterampilan
$mapelAktif    = null;

// Fungsi predikat
function predikatDariNilai($n) {
    if ($n === null) return '-';
    if ($n >= 90) return 'A';
    if ($n >= 80) return 'B';
    if ($n >= 70) return 'C';
    return 'D';
}

if ($selectedMapelId && $selectedKelas !== '') {

    // Data mapel aktif
    $stmtOneMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE id = ? LIMIT 1");
    $stmtOneMapel->execute([$selectedMapelId]);
    $mapelAktif = $stmtOneMapel->fetch(PDO::FETCH_ASSOC);

    // Ambil siswa di kelas tsb
    $stmtSiswa = $pdo->prepare("SELECT id, nama FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($siswaList)) {
        $siswaIds = array_column($siswaList, 'id');
        $in = implode(',', array_fill(0, count($siswaIds), '?'));

        // ---------------- Pengetahuan ----------------
        $paramsPen = $siswaIds;
        array_unshift($paramsPen, $selectedMapelId); // mapel_id jadi parameter pertama

        $sqlPen = "
            SELECT siswa_id, AVG(nilai_akhir) AS rata
            FROM nilai_pengetahuan
            WHERE mapel_id = ?
              AND siswa_id IN ($in)
            GROUP BY siswa_id
        ";
        $stmtPen = $pdo->prepare($sqlPen);
        $stmtPen->execute($paramsPen);
        $rowsPen = $stmtPen->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rowsPen as $row) {
            $nilaiPknMap[$row['siswa_id']] = (float)$row['rata'];
        }

        // ---------------- Keterampilan ----------------
        $paramsKet = $siswaIds;
        array_unshift($paramsKet, $selectedMapelId);

        $sqlKet = "
            SELECT siswa_id, AVG(nilai_akhir) AS rata
            FROM nilai_keterampilan
            WHERE mapel_id = ?
              AND siswa_id IN ($in)
            GROUP BY siswa_id
        ";
        $stmtKet = $pdo->prepare($sqlKet);
        $stmtKet->execute($paramsKet);
        $rowsKet = $stmtKet->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rowsKet as $row) {
            $nilaiKetMap[$row['siswa_id']] = (float)$row['rata'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Nilai (Pengetahuan & Keterampilan)</title>
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

    <!-- Header -->
    <div class="row mb-3">
        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-1">Rekap Nilai Pengetahuan & Keterampilan</h2>
                    <p class="text-muted mb-0">
                        Rekap nilai per siswa untuk mapel yang Anda ampu: gabungan pengetahuan dan keterampilan, mirip ringkasan di Excel.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
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

    <!-- Tabel Rekap -->
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
                            Rekap Nilai
                            <br>
                            <small>
                                Mapel: <?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?> 
                                (KKM: <?= (int)($mapelAktif['kkm'] ?? 0); ?>)
                                | Kelas: <?= htmlspecialchars($selectedKelas); ?>
                            </small>
                        </div>
                        <div class="card-body table-responsive">

                            <table class="table table-bordered table-sm align-middle">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th style="min-width: 180px;">Nama Siswa</th>
                                        <th>Pengetahuan</th>
                                        <th>Keterampilan</th>
                                        <th>Nilai Akhir</th>
                                        <th>Predikat</th>
                                        <th>Ketuntasan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $kkm = isset($mapelAktif['kkm']) ? (float)$mapelAktif['kkm'] : 0;

                                    foreach ($siswaList as $s):
                                        $sid   = $s['id'];
                                        $nPen  = $nilaiPknMap[$sid] ?? null;
                                        $nKet  = $nilaiKetMap[$sid] ?? null;

                                        if ($nPen !== null && $nKet !== null) {
                                            $nAkhir = ($nPen + $nKet) / 2;
                                        } elseif ($nPen !== null) {
                                            $nAkhir = $nPen;
                                        } elseif ($nKet !== null) {
                                            $nAkhir = $nKet;
                                        } else {
                                            $nAkhir = null;
                                        }

                                        $pred = predikatDariNilai($nAkhir);
                                        $ket  = '-';
                                        if ($nAkhir !== null && $kkm > 0) {
                                            $ket = ($nAkhir >= $kkm) ? 'Tuntas' : 'Belum Tuntas';
                                        }
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['nama']); ?></td>
                                            <td class="text-center">
                                                <?= $nPen !== null ? number_format($nPen, 1) : '-'; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= $nKet !== null ? number_format($nKet, 1) : '-'; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= $nAkhir !== null ? number_format($nAkhir, 1) : '-'; ?>
                                            </td>
                                            <td class="text-center"><?= $pred; ?></td>
                                            <td class="text-center"><?= $ket; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

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
