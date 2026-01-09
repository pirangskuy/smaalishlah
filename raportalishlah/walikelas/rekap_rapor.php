<?php
// walikelas/rekap_rapor.php
// Rekap nilai rapor lengkap (Pengetahuan & Keterampilan) untuk wali kelas

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();

// Pastikan hanya wali kelas yang boleh akses
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'walikelas') {
    echo "<p style='color:red;'>Akses hanya untuk wali kelas.</p>";
    exit;
}

$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    echo "<p style='color:red;'>Akun wali belum terhubung ke data guru (guru_id kosong). Hubungi admin.</p>";
    exit;
}

// Ambil data guru & kelas_wali
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    echo "<p style='color:red;'>Data guru dengan ID {$guruId} tidak ditemukan. Hubungi admin.</p>";
    exit;
}

$namaWali  = $guru['name'] ?? ($_SESSION['user']['username'] ?? 'Wali Kelas');
$kelasWali = $guru['kelas_wali'] ?? '';

if ($kelasWali === '' || $kelasWali === null) {
    echo "<p style='color:red;'>Kelas wali belum diatur pada akun guru ini. Hubungi admin untuk mengisi kolom <code>kelas_wali</code> di tabel <code>teachers</code>.</p>";
    exit;
}

// Pilihan Tahun Ajaran & Semester
$tahunOptions    = ['2023/2024', '2024/2025', '2025/2026'];
$semesterOptions = ['Ganjil', 'Genap'];

$selectedTA  = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '2024/2025';
$selectedSem = isset($_GET['semester']) ? $_GET['semester'] : 'Ganjil';

// Ambil semua mapel (urutkan sesuai kebutuhan)
$stmtMapel = $pdo->query("SELECT id, nama, kkm FROM mapel ORDER BY id ASC");
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua siswa di kelas wali
$stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
$stmtSiswa->execute([$kelasWali]);
$siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

// Helper predikat
function predikat_from_nilai($n) {
    if ($n === null) return '-';
    if ($n >= 90) return 'A';
    if ($n >= 80) return 'B';
    if ($n >= 70) return 'C';
    return 'D';
}

$rekapPengetahuan  = []; // [siswa_id][mapel_id] = nilai rata-rata pengetahuan
$rekapKeterampilan = []; // [siswa_id][mapel_id] = nilai rata-rata keterampilan

// Kalau ada siswa dan mapel, baru tarik nilai
if (!empty($siswaList) && !empty($mapelList)) {

    // ---- Ambil rekap PENGETAHUAN: rata-rata nilai_akhir per mapel per siswa ----
    $stmtPen = $pdo->prepare("
        SELECT np.siswa_id, np.mapel_id, AVG(np.nilai_akhir) AS rata_pengetahuan
        FROM nilai_pengetahuan np
        JOIN siswa s ON s.id = np.siswa_id
        WHERE s.kelas = ?
          AND np.tahun_ajaran = ?
          AND np.semester = ?
        GROUP BY np.siswa_id, np.mapel_id
    ");
    $stmtPen->execute([$kelasWali, $selectedTA, $selectedSem]);
    $rowsPen = $stmtPen->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsPen as $row) {
        $sid = $row['siswa_id'];
        $mid = $row['mapel_id'];
        $rekapPengetahuan[$sid][$mid] = (float)$row['rata_pengetahuan'];
    }

    // ---- Ambil rekap KETERAMPILAN: rata-rata nilai_akhir per mapel per siswa ----
    $stmtKet = $pdo->prepare("
        SELECT nk.siswa_id, nk.mapel_id, AVG(nk.nilai_akhir) AS rata_keterampilan
        FROM nilai_keterampilan nk
        JOIN siswa s ON s.id = nk.siswa_id
        WHERE s.kelas = ?
          AND nk.tahun_ajaran = ?
          AND nk.semester = ?
        GROUP BY nk.siswa_id, nk.mapel_id
    ");
    $stmtKet->execute([$kelasWali, $selectedTA, $selectedSem]);
    $rowsKet = $stmtKet->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rowsKet as $row) {
        $sid = $row['siswa_id'];
        $mid = $row['mapel_id'];
        $rekapKeterampilan[$sid][$mid] = (float)$row['rata_keterampilan'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Nilai Rapor - Wali Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .table-rekap th, .table-rekap td {
            font-size: 0.75rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        .badge-pred {
            font-size: 0.7rem;
        }
        .table-sticky thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #e0e0e0;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="wali_dashboard.php">Dashboard Wali Kelas</a>
        <div class="collapse navbar-collapse justify-content-end">
            <span class="navbar-text text-white me-3">
                <?= htmlspecialchars($namaWali); ?> (Wali Kelas <?= htmlspecialchars($kelasWali); ?>)
            </span>
            <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid">

    <!-- Header Rekap -->
    <div class="row mb-3">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-1">Rekap Nilai Rapor Kelas <?= htmlspecialchars($kelasWali); ?></h2>
                    <p class="text-muted mb-1">
                        Menampilkan nilai akhir Pengetahuan &amp; Keterampilan tiap mata pelajaran, hasil pengolahan KD oleh guru mapel.
                    </p>

                    <form method="get" class="row g-2 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">Tahun Ajaran</label>
                            <select name="tahun_ajaran" class="form-select form-select-sm">
                                <?php foreach ($tahunOptions as $ta): ?>
                                    <option value="<?= htmlspecialchars($ta); ?>"
                                        <?= $selectedTA === $ta ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($ta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select form-select-sm">
                                <?php foreach ($semesterOptions as $sem): ?>
                                    <option value="<?= htmlspecialchars($sem); ?>"
                                        <?= $selectedSem === $sem ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-sm">
                                Tampilkan Rekap
                            </button>
                        </div>
                    </form>

                    <small class="text-muted d-block mt-2">
                        Sekolah: <strong>SMA Al Ishlah</strong> |
                        Kelas: <strong><?= htmlspecialchars($kelasWali); ?></strong> |
                        Tahun Ajaran: <strong><?= htmlspecialchars($selectedTA); ?></strong> |
                        Semester: <strong><?= htmlspecialchars($selectedSem); ?></strong>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekap -->
    <?php if (empty($siswaList)): ?>
        <div class="alert alert-warning">
            Belum ada siswa di kelas <strong><?= htmlspecialchars($kelasWali); ?></strong>.
        </div>
    <?php elseif (empty($mapelList)): ?>
        <div class="alert alert-warning">
            Belum ada data mata pelajaran di tabel <code>mapel</code>.
        </div>
    <?php else: ?>

        <div class="card shadow-sm">
            <div class="card-body" style="overflow-x:auto;">

                <table class="table table-bordered table-sm table-rekap table-sticky mb-0">
                    <thead>
                        <!-- Baris 1: judul kolom utama -->
                        <tr class="table-light text-center align-middle">
                            <th rowspan="2">No</th>
                            <th rowspan="2">NIS</th>
                            <th rowspan="2">NISN</th>
                            <th rowspan="2" style="min-width:180px;">Nama Siswa</th>
                            <?php foreach ($mapelList as $m): ?>
                                <th colspan="4"><?= htmlspecialchars($m['nama']); ?></th>
                            <?php endforeach; ?>
                            <th rowspan="2" style="min-width:100px;">Aksi</th>
                        </tr>
                        <!-- Baris 2: sub kolom P/K -->
                        <tr class="table-light text-center align-middle">
                            <?php foreach ($mapelList as $m): ?>
                                <th>P</th>
                                <th>Pred P</th>
                                <th>K</th>
                                <th>Pred K</th>
                            <?php endforeach; ?>
                            <!-- kolom Aksi tidak punya sub-kolom -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach ($siswaList as $s):
                            $sid = $s['id'];
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($s['nis'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($s['nisn'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($s['nama'] ?? ''); ?></td>

                                <?php foreach ($mapelList as $m):
                                    $mid = $m['id'];
                                    $nP  = $rekapPengetahuan[$sid][$mid]  ?? null;
                                    $nK  = $rekapKeterampilan[$sid][$mid] ?? null;

                                    if ($nP !== null) {
                                        if ($nP < 0)   $nP = 0;
                                        if ($nP > 100) $nP = 100;
                                        $nP = round($nP);
                                    }
                                    if ($nK !== null) {
                                        if ($nK < 0)   $nK = 0;
                                        if ($nK > 100) $nK = 100;
                                        $nK = round($nK);
                                    }

                                    $predP = predikat_from_nilai($nP);
                                    $predK = predikat_from_nilai($nK);

                                    // class badge
                                    $clsP = 'bg-secondary';
                                    if     ($predP === 'A') $clsP = 'bg-success';
                                    elseif ($predP === 'B') $clsP = 'bg-primary';
                                    elseif ($predP === 'C') $clsP = 'bg-warning text-dark';
                                    elseif ($predP === 'D') $clsP = 'bg-danger';

                                    $clsK = 'bg-secondary';
                                    if     ($predK === 'A') $clsK = 'bg-success';
                                    elseif ($predK === 'B') $clsK = 'bg-primary';
                                    elseif ($predK === 'C') $clsK = 'bg-warning text-dark';
                                    elseif ($predK === 'D') $clsK = 'bg-danger';
                                ?>
                                    <td class="text-center"><?= $nP !== null ? $nP : ''; ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-pred <?= $clsP; ?>"><?= $predP; ?></span>
                                    </td>
                                    <td class="text-center"><?= $nK !== null ? $nK : ''; ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-pred <?= $clsK; ?>"><?= $predK; ?></span>
                                    </td>
                                <?php endforeach; ?>

                                <!-- Kolom Aksi: Cetak Rapor untuk siswa ini -->
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-primary"
                                       target="_blank"
                                       href="cetak_rapor.php?siswa_id=<?= (int)$sid; ?>&tahun_ajaran=<?= urlencode($selectedTA); ?>&semester=<?= urlencode($selectedSem); ?>">
                                        Cetak Rapor
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
