<?php
// guru/rekap_uts_uas.php
// Rekap UTS / UAS per mapel, per kelas, dengan keterangan tuntas berdasarkan KKM

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

// Ambil guru_id
$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    echo "<p style='color:red;'>Akun guru belum terhubung ke data guru (guru_id kosong di tabel users).</p>";
    exit;
}

// Data guru
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);
$namaGuru = $guru['name'] ?? ($_SESSION['user']['username'] ?? '-');

// Mapel yang diampu guru
$stmtMapel = $pdo->prepare("
    SELECT id, nama, kkm
    FROM mapel
    WHERE teacher_id = ?
    ORDER BY nama
");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Kelas 10/11/12
$kelasList = ['10', '11', '12'];

// Ambil filter
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
$selectedKelas   = $_GET['kelas'] ?? '';
$jenis           = $_GET['jenis'] ?? 'uts'; // uts / uas

if ($jenis !== 'uts' && $jenis !== 'uas') {
    $jenis = 'uts';
}

$judulUlangan = ($jenis === 'uts') ? 'ULANGAN TENGAH SEMESTER' : 'ULANGAN AKHIR SEMESTER';

$siswaList  = [];
$nilaiMap   = [];
$mapelAktif = null;

if ($selectedMapelId && $selectedKelas !== '') {

    // Data mapel aktif
    $stmtOneMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE id = ? LIMIT 1");
    $stmtOneMapel->execute([$selectedMapelId]);
    $mapelAktif = $stmtOneMapel->fetch(PDO::FETCH_ASSOC);

    // Data siswa kelas tsb
    $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($siswaList)) {
        $siswaIds = array_column($siswaList, 'id');
        $in = implode(',', array_fill(0, count($siswaIds), '?'));

        $params = $siswaIds;
        array_unshift($params, $selectedMapelId); // mapel_id di depan

        $sql = "
            SELECT siswa_id, uts, uas
            FROM nilai_uts_uas
            WHERE mapel_id = ?
              AND siswa_id IN ($in)
        ";
        $stmtNilai = $pdo->prepare($sql);
        $stmtNilai->execute($params);
        $rows = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $sid = $row['siswa_id'];
            $nilaiMap[$sid] = [
                'uts' => $row['uts'],
                'uas' => $row['uas'],
            ];
        }
    }
}

// Ambil KKM
$kkm = isset($mapelAktif['kkm']) ? (float)$mapelAktif['kkm'] : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($judulUlangan); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .header-box {
            background: #f1c40f;
            padding: 15px 20px;
            border-radius: 4px 4px 0 0;
            border: 1px solid #ccc;
            border-bottom: none;
        }
        .header-box h3 {
            margin: 0 0 10px 0;
            font-weight: bold;
            text-align: center;
        }
        .header-box table td {
            padding: 2px 8px;
            font-size: 13px;
        }
        .table-ulang {
            border: 1px solid #ccc;
            border-radius: 0 0 4px 4px;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="guru_dashboard.php">Dashboard Guru</a>
        <div class="collapse navbar-collapse">
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

    <!-- Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">

                <div class="col-md-4">
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

                <div class="col-md-2">
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

                <div class="col-md-3">
                    <label class="form-label">Jenis Ulangan</label>
                    <select name="jenis" class="form-select" required>
                        <option value="uts" <?= $jenis === 'uts' ? 'selected' : ''; ?>>UTS (Ulangan Tengah Semester)</option>
                        <option value="uas" <?= $jenis === 'uas' ? 'selected' : ''; ?>>UAS (Ulangan Akhir Semester)</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>

            </form>
        </div>
    </div>

    <?php if ($selectedMapelId && $selectedKelas !== '' && !empty($siswaList)): ?>

        <div class="header-box">
            <h3><?= htmlspecialchars($judulUlangan); ?></h3>
            <table>
                <tr>
                    <td style="width:120px;">KKM Mapel</td>
                    <td>:</td>
                    <td><strong><?= $kkm ?: '-'; ?></strong></td>
                </tr>
                <tr>
                    <td>Mata Pelajaran</td>
                    <td>:</td>
                    <td><?= htmlspecialchars($mapelAktif['nama'] ?? '-'); ?></td>
                </tr>
                <!-- Nanti bisa dibuat dinamis dari tabel tahun_ajaran, semester dsb -->
                <tr>
                    <td>Tahun Pelajaran</td>
                    <td>:</td>
                    <td>2025/2026</td>
                </tr>
                <tr>
                    <td>Semester</td>
                    <td>:</td>
                    <td>Ganjil</td>
                </tr>
                <tr>
                    <td>Kelas</td>
                    <td>:</td>
                    <td><?= htmlspecialchars($selectedKelas); ?></td>
                </tr>
            </table>
        </div>

        <div class="table-ulang bg-white shadow-sm">
            <table class="table table-bordered table-sm mb-0 align-middle">
                <thead class="table-secondary text-center">
                    <tr>
                        <th style="width:50px;">Urut</th>
                        <th style="width:110px;">NIS</th>
                        <th style="width:120px;">NISN</th>
                        <th>Nama Peserta Didik</th>
                        <th style="width:60px;">L/P</th>
                        <th style="width:80px;">Nilai</th>
                        <th style="width:150px;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    foreach ($siswaList as $s):
                        $sid = $s['id'];
                        $rowNilai = $nilaiMap[$sid] ?? null;
                        $nilai = null;
                        if ($rowNilai) {
                            $nilai = ($jenis === 'uts') ? $rowNilai['uts'] : $rowNilai['uas'];
                        }
                        $nilaiNum = ($nilai !== null) ? (float)$nilai : null;

                        if ($nilaiNum === null) {
                            $ket = '-';
                        } elseif ($kkm > 0 && $nilaiNum >= $kkm) {
                            $ket = 'Sudah Tuntas';
                        } else {
                            $ket = 'Belum Tuntas';
                        }

                        // Ambil L/P (sesuaikan dengan kolom yang ada: jk atau jenis_kelamin)
                        $lp = $s['jk'] ?? ($s['jenis_kelamin'] ?? '');
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td class="text-center"><?= htmlspecialchars($s['nis'] ?? ''); ?></td>
                            <td class="text-center"><?= htmlspecialchars($s['nisn'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($s['nama']); ?></td>
                            <td class="text-center"><?= htmlspecialchars($lp); ?></td>
                            <td class="text-center">
                                <?= $nilaiNum !== null ? number_format($nilaiNum, 0) : '-'; ?>
                            </td>
                            <td class="text-center"><?= $ket; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($selectedMapelId && $selectedKelas !== ''): ?>

        <div class="alert alert-warning">
            Belum ada siswa di kelas ini, atau belum ada nilai UTS/UAS yang diinput.
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
