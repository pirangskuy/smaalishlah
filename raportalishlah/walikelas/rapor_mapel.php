<?php
// walikelas/rapor_mapel.php
// Tampilan FORMAT NILAI RAPORT MATA PELAJARAN (per mapel, per kelas wali)

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireWaliKelas();

$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    die("guru_id kosong. Hubungi admin.");
}

// data wali (guru yang menjadi wali)
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$wali = $stmtGuru->fetch(PDO::FETCH_ASSOC);

if (!$wali || !$wali['kelas_wali']) {
    die("Akun wali belum memiliki kelas_wali. Hubungi admin.");
}

$kelasWali = $wali['kelas_wali'];
$namaWali  = $wali['name'] ?? ($_SESSION['user']['username'] ?? '-');

// tahun & semester (sesuai yang digunakan guru ketika setor)
$tahun_ajaran_default = '2025/2026';
$semester_default      = 'Ganjil';

// ambil list mapel yang sudah ada di rapor_mapel untuk kelas ini
$stmtMapel = $pdo->prepare("
    SELECT DISTINCT m.id, m.nama
    FROM rapor_mapel r
    JOIN mapel m ON r.mapel_id = m.id
    WHERE r.kelas = ? AND r.tahun_ajaran = ? AND r.semester = ?
    ORDER BY m.nama
");
$stmtMapel->execute([$kelasWali, $tahun_ajaran_default, $semester_default]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// pilih mapel
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;

$rows = [];
$mapelAktif = null;
$namaGuruMapel = '';
$isPAI = false;
$isPPKn = false;

if ($selectedMapelId) {
    $stmtMapel2 = $pdo->prepare("SELECT * FROM mapel WHERE id = ? LIMIT 1");
    $stmtMapel2->execute([$selectedMapelId]);
    $mapelAktif = $stmtMapel2->fetch(PDO::FETCH_ASSOC);

    if ($mapelAktif) {
        $nm = strtolower($mapelAktif['nama']);
        $isPAI  = (strpos($nm,'pai') !== false) || (strpos($nm,'ibadah syariah') !== false) || (strpos($nm,'agama') !== false);
        $isPPKn = (strpos($nm,'ppkn') !== false) || (strpos($nm,'ppk') !== false);
    }

    // ambil data rapor_mapel join siswa & guru mapel
    $stmt = $pdo->prepare("
        SELECT r.*, s.nis, s.nisn, s.nama AS nama_siswa,
               s.jk, s.jenis_kelamin,
               m.nama AS nama_mapel, m.kkm,
               g.name AS guru_mapel
        FROM rapor_mapel r
        JOIN siswa s   ON r.siswa_id = s.id
        JOIN mapel m   ON r.mapel_id = m.id
        JOIN teachers g ON r.guru_id = g.id
        WHERE r.kelas = ?
          AND r.mapel_id = ?
          AND r.tahun_ajaran = ?
          AND r.semester = ?
        ORDER BY s.nama
    ");
    $stmt->execute([$kelasWali, $selectedMapelId, $tahun_ajaran_default, $semester_default]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        $namaGuruMapel = $rows[0]['guru_mapel'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rapor Mapel - Wali Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .header-rapor {
            background-color: #2ecc71;
            color: #000;
            padding: 15px 20px;
            border-radius: 4px 4px 0 0;
            border: 1px solid #ccc;
            border-bottom: none;
        }
        .header-rapor h4 {
            margin: 0 0 10px 0;
            font-weight: bold;
            text-align: center;
        }
        .header-rapor table td {
            padding: 2px 8px;
            font-size: 13px;
        }
        .table-wrap {
            border: 1px solid #ccc;
            border-radius: 0 0 4px 4px;
            overflow: hidden;
            background: #fff;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="wali_dashboard.php">Dashboard Wali Kelas</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-white">
                        <?= htmlspecialchars($namaWali); ?> (Wali Kelas <?= htmlspecialchars($kelasWali); ?>)
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

    <!-- FILTER MAPEL -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Pilih Mata Pelajaran</label>
                    <select name="mapel_id" class="form-select" required>
                        <option value="">-- Pilih Mapel --</option>
                        <?php foreach ($mapelList as $m): ?>
                            <option value="<?= $m['id']; ?>" <?= $selectedMapelId == $m['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($m['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tahun Pelajaran</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($tahun_ajaran_default); ?>" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Semester</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($semester_default); ?>" disabled>
                </div>
                <div class="col-12 d-flex justify-content-end mt-2">
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedMapelId && !empty($rows)): ?>

        <!-- HEADER SEPERTI EXCEL -->
        <div class="header-rapor">
            <h4>FORMAT NILAI RAPORT MATA PELAJARAN</h4>
            <table>
                <tr>
                    <td style="width:130px;">Nama Sekolah</td><td>:</td>
                    <td>SMA Al Ishlah Pontianak</td>
                </tr>
                <tr>
                    <td>Tahun Pelajaran</td><td>:</td>
                    <td><?= htmlspecialchars($tahun_ajaran_default); ?></td>
                </tr>
                <tr>
                    <td>Semester</td><td>:</td>
                    <td><?= htmlspecialchars($semester_default); ?></td>
                </tr>
                <tr>
                    <td>Kelas</td><td>:</td>
                    <td><?= htmlspecialchars($kelasWali); ?></td>
                </tr>
                <tr>
                    <td>Mata Pelajaran</td><td>:</td>
                    <td><?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td>Guru Mapel</td><td>:</td>
                    <td><?= htmlspecialchars($namaGuruMapel); ?></td>
                </tr>
            </table>
        </div>

        <!-- TABEL NILAI -->
        <div class="table-wrap shadow-sm mb-5">
            <table class="table table-bordered table-sm mb-0 align-middle">
                <thead class="table-secondary text-center">
                    <tr>
                        <th rowspan="2" style="width:40px;">Urut</th>
                        <th colspan="2">Nomor</th>
                        <th rowspan="2">Nama Siswa</th>
                        <th rowspan="2" style="width:70px;">L/P</th>
                        <th colspan="2">Pengetahuan</th>
                        <th colspan="2">Keterampilan</th>
                        <?php if ($isPAI || $isPPKn): ?>
                            <th colspan="2">Sikap</th>
                        <?php endif; ?>
                        <th rowspan="2">Deskripsi Pengetahuan</th>
                        <th rowspan="2">Deskripsi Keterampilan</th>
                        <?php if ($isPAI || $isPPKn): ?>
                            <th rowspan="2">
                                <?= $isPAI ? 'Deskripsi Sikap Spiritual' : 'Deskripsi Sikap Sosial'; ?>
                            </th>
                        <?php endif; ?>
                    </tr>
                    <tr>
                        <th style="width:110px;">NIS</th>
                        <th style="width:120px;">NISN</th>
                        <th>Nilai</th><th>Pred</th>
                        <th>Nilai</th><th>Pred</th>
                        <?php if ($isPAI || $isPPKn): ?>
                            <th>Nilai</th><th>Pred</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($rows as $row):
                        $lp = $row['jk'] ?? ($row['jenis_kelamin'] ?? '');
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['nis'] ?? ''); ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['nisn'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                            <td class="text-center"><?= htmlspecialchars($lp); ?></td>

                            <td class="text-center">
                                <?= $row['nilai_pengetahuan'] !== null ? number_format($row['nilai_pengetahuan'],1) : '-'; ?>
                            </td>
                            <td class="text-center">
                                <?= htmlspecialchars($row['predikat_pengetahuan'] ?? ''); ?>
                            </td>

                            <td class="text-center">
                                <?= $row['nilai_keterampilan'] !== null ? number_format($row['nilai_keterampilan'],1) : '-'; ?>
                            </td>
                            <td class="text-center">
                                <?= htmlspecialchars($row['predikat_keterampilan'] ?? ''); ?>
                            </td>

                            <?php if ($isPAI || $isPPKn): ?>
                                <td class="text-center">
                                    <?= $row['nilai_sikap'] !== null ? number_format($row['nilai_sikap'],1) : '-'; ?>
                                </td>
                                <td class="text-center">
                                    <?= htmlspecialchars($row['predikat_sikap'] ?? ''); ?>
                                </td>
                            <?php endif; ?>

                            <td><?= htmlspecialchars($row['deskripsi_pengetahuan'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($row['deskripsi_keterampilan'] ?? ''); ?></td>
                            <?php if ($isPAI || $isPPKn): ?>
                                <td><?= htmlspecialchars($row['deskripsi_sikap'] ?? ''); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($selectedMapelId): ?>

        <div class="alert alert-warning">
            Belum ada nilai yang disetor guru mapel untuk kelas ini.
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
