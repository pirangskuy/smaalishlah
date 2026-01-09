<?php
// guru/nilai_pengetahuan.php

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

// Ambil data guru (opsional, untuk tampil nama)
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);

$namaGuru = $guru['name'] ?? $_SESSION['user']['username'];

// Ambil mapel yang diampu guru ini
$stmtMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE teacher_id = ? ORDER BY nama");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Daftar kelas (sederhana)
$kelasList = ['10', '11', '12'];

// Pilihan tahun ajaran & semester
$tahunOptions = [
    '2023/2024',
    '2024/2025',
    '2025/2026',
];
$semesterOptions = ['Ganjil', 'Genap'];

// Ambil pilihan mapel & kelas & tahun ajaran & semester dari GET
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
$selectedKelas   = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$selectedTA      = isset($_GET['tahun_ajaran']) ? $_GET['tahun_ajaran'] : '2024/2025';
$selectedSem     = isset($_GET['semester']) ? $_GET['semester'] : 'Ganjil';

$kdList        = [];
$siswaList     = [];
$mapelAktif    = null;
$existingData  = []; // [siswa_id][kd_id] = row dari nilai_pengetahuan

if ($selectedMapelId && $selectedKelas !== '') {
    // Ambil detail mapel aktif
    $stmtOneMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE id = ? LIMIT 1");
    $stmtOneMapel->execute([$selectedMapelId]);
    $mapelAktif = $stmtOneMapel->fetch(PDO::FETCH_ASSOC);

    // KD pengetahuan untuk mapel ini
    $stmtKd = $pdo->prepare("
        SELECT id, kd_ke, deskripsi
        FROM kd_pengetahuan
        WHERE mapel_id = ?
        ORDER BY kd_ke
    ");
    $stmtKd->execute([$selectedMapelId]);
    $kdList = $stmtKd->fetchAll(PDO::FETCH_ASSOC);

    // Siswa di kelas tersebut
    $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    // Ambil nilai yang sudah ada (semua komponen)
    if (!empty($siswaList) && !empty($kdList)) {
        $stmtNilai = $pdo->prepare("
            SELECT siswa_id, kd_id,
                   p1, p2, p3, rata_penugasan,
                   u1, u2, rata_ulangan,
                   nilai_akhir, predikat
            FROM nilai_pengetahuan
            WHERE mapel_id = ?
              AND tahun_ajaran = ?
              AND semester = ?
        ");
        $stmtNilai->execute([$selectedMapelId, $selectedTA, $selectedSem]);
        $rowsNilai = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rowsNilai as $row) {
            $sid = $row['siswa_id'];
            $kid = $row['kd_id'];
            $existingData[$sid][$kid] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Nilai Pengetahuan</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .nilai-ratatugas,
        .nilai-rataulangan {
            background-color: #e8f5e9 !important; /* hijau muda */
            font-weight: 600;
        }
        .nilai-akhir-kd {
            background-color: #c8e6c9 !important; /* hijau lebih tegas */
            font-weight: 700;
        }
        .nilai-akhir-kd.tidak-tuntas {
            background-color: #ffebee !important; /* merah muda jika belum tuntas */
        }
        .predikat-badge {
            font-size: 0.75rem;
        }
        .table-scroll {
            overflow-x: auto;
        }
    </style>
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

    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success">
            Nilai pengetahuan berhasil disimpan.
        </div>
    <?php elseif (!empty($_GET['msg'])): ?>
        <div class="alert alert-warning">
            <?= htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="row mb-3">
        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-1">Input Nilai Pengetahuan</h2>
                    <p class="text-muted mb-0">Silakan pilih mata pelajaran, kelas, tahun ajaran, dan semester.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Pilih Mapel & Kelas & TA & Semester -->
    <div class="row mb-4">
        <div class="col-md-10">
            <div class="card shadow-sm">
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

                        <div class="col-md-3">
                            <label class="form-label">Kelas</label>
                            <select name="kelas" class="form-select" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelasList as $k): ?>
                                    <option value="<?= htmlspecialchars($k); ?>"
                                        <?= $selectedKelas === $k ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($k); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tahun Ajaran</label>
                            <select name="tahun_ajaran" class="form-select" required>
                                <?php foreach ($tahunOptions as $ta): ?>
                                    <option value="<?= htmlspecialchars($ta); ?>"
                                        <?= $selectedTA === $ta ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($ta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select" required>
                                <?php foreach ($semesterOptions as $sem): ?>
                                    <option value="<?= htmlspecialchars($sem); ?>"
                                        <?= $selectedSem === $sem ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($sem); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Tampilkan</button>
                        </div>

                    </form>

                    <?php if (empty($mapelList)): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            Anda belum terdaftar mengampu mata pelajaran apapun di tabel <code>mapel</code>. 
                            Silakan hubungi admin.
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Input Nilai -->
    <?php if ($selectedMapelId && $selectedKelas !== ''): ?>

        <?php if (empty($kdList)): ?>
            <div class="alert alert-warning">
                Belum ada KD Pengetahuan untuk mata pelajaran ini.  
                Silakan tambahkan KD di menu admin (tabel <code>kd_pengetahuan</code>).
            </div>
        <?php elseif (empty($siswaList)): ?>
            <div class="alert alert-warning">
                Belum ada siswa di kelas <strong><?= htmlspecialchars($selectedKelas); ?></strong>.
            </div>
        <?php else: ?>

            <div class="row mb-5">
                <div class="col">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            Input Nilai Pengetahuan
                            <br>
                            <small>
                                Mapel: 
                                <?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?> 
                                (KKM: <?= (int)($mapelAktif['kkm'] ?? 0); ?>)
                                | Kelas: <?= htmlspecialchars($selectedKelas); ?>
                                | TA: <?= htmlspecialchars($selectedTA); ?>
                                | Semester: <?= htmlspecialchars($selectedSem); ?>
                            </small>
                        </div>
                        <div class="card-body table-scroll">

                            <form method="post" action="simpan_pengetahuan.php" id="form-nilai-pengetahuan">
                                <!-- Info yang dibawa ke proses simpan -->
                                <input type="hidden" name="mapel_id" value="<?= $selectedMapelId; ?>">
                                <input type="hidden" name="kelas" value="<?= htmlspecialchars($selectedKelas); ?>">
                                <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($selectedTA); ?>">
                                <input type="hidden" name="semester" value="<?= htmlspecialchars($selectedSem); ?>">

                                <table class="table table-bordered table-sm align-middle">
                                    <thead>
                                        <tr class="table-light">
                                            <th rowspan="2" style="min-width: 180px; vertical-align: middle;">Nama Siswa</th>
                                            <?php foreach ($kdList as $kd): ?>
                                                <th class="text-center" colspan="10">
                                                    KD <?= htmlspecialchars($kd['kd_ke']); ?><br>
                                                    <small><?= htmlspecialchars($kd['deskripsi']); ?></small>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                        <tr class="table-light">
                                            <?php foreach ($kdList as $kd): ?>
                                                <th class="text-center">P1</th>
                                                <th class="text-center">P2</th>
                                                <th class="text-center">P3</th>
                                                <th class="text-center">Rata<br>Tugas</th>
                                                <th class="text-center">U1</th>
                                                <th class="text-center">U2</th>
                                                <th class="text-center">Rata<br>Ulangan</th>
                                                <th class="text-center">Rem 1</th>
                                                <th class="text-center">Rem 2</th>
                                                <th class="text-center">Nilai KD<br><small>(A/B/C/D)</small></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($siswaList as $s): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($s['nama']); ?></td>
                                                <?php foreach ($kdList as $kd): ?>
                                                    <?php
                                                        $sid      = $s['id'];
                                                        $kid      = $kd['id'];
                                                        $rowNilai = $existingData[$sid][$kid] ?? null;

                                                        $p1Val    = $rowNilai['p1']             ?? '';
                                                        $p2Val    = $rowNilai['p2']             ?? '';
                                                        $p3Val    = $rowNilai['p3']             ?? '';
                                                        $rtVal    = $rowNilai['rata_penugasan'] ?? '';
                                                        $u1Val    = $rowNilai['u1']             ?? '';
                                                        $u2Val    = $rowNilai['u2']             ?? '';
                                                        $ruVal    = $rowNilai['rata_ulangan']   ?? '';
                                                        $naExist  = $rowNilai['nilai_akhir']    ?? '';
                                                        $pred     = $rowNilai['predikat']       ?? '-';
                                                        $kkmMapel = (int)($mapelAktif['kkm'] ?? 0);
                                                    ?>
                                                    <!-- P1 -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="komponen[<?= $sid; ?>][<?= $kid; ?>][p1]"
                                                            class="form-control form-control-sm text-center nilai-komponen"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-jenis="p1"
                                                            value="<?= $p1Val !== '' ? htmlspecialchars($p1Val) : ''; ?>"
                                                        >
                                                    </td>
                                                    <!-- P2 -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="komponen[<?= $sid; ?>][<?= $kid; ?>][p2]"
                                                            class="form-control form-control-sm text-center nilai-komponen"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-jenis="p2"
                                                            value="<?= $p2Val !== '' ? htmlspecialchars($p2Val) : ''; ?>"
                                                        >
                                                    </td>
                                                    <!-- P3 -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="komponen[<?= $sid; ?>][<?= $kid; ?>][p3]"
                                                            class="form-control form-control-sm text-center nilai-komponen"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-jenis="p3"
                                                            value="<?= $p3Val !== '' ? htmlspecialchars($p3Val) : ''; ?>"
                                                        >
                                                    </td>
                                                    <!-- Rata Tugas -->
                                                    <td class="text-center">
                                                        <input
                                                            type="text"
                                                            class="form-control form-control-sm text-center nilai-ratatugas"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            readonly
                                                            value="<?= $rtVal !== '' ? htmlspecialchars($rtVal) : ''; ?>"
                                                        >
                                                    </td>
                                                    <!-- U1 -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="komponen[<?= $sid; ?>][<?= $kid; ?>][u1]"
                                                            class="form-control form-control-sm text-center nilai-komponen"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-jenis="u1"
                                                            value="<?= $u1Val !== '' ? htmlspecialchars($u1Val) : ''; ?>"
                                                        >
                                                    </td>
                                                    <!-- U2 -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="komponen[<?= $sid; ?>][<?= $kid; ?>][u2]"
                                                            class="form-control form-control-sm text-center nilai-komponen"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-jenis="u2"
                                                            value="<?= $u2Val !== '' ? htmlspecialchars($u2Val) : ''; ?>"
                                                        >
                                                    </td>
                                                    <!-- Rata Ulangan -->
                                                    <td class="text-center">
                                                        <input
                                                            type="text"
                                                            class="form-control form-control-sm text-center nilai-rataulangan"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            readonly
                                                            value="<?= $ruVal !== '' ? htmlspecialchars($ruVal) : ''; ?>"
                                                        >
                                                    </td>
                                                    <!-- Remedial 1 -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="komponen[<?= $sid; ?>][<?= $kid; ?>][rem1]"
                                                            class="form-control form-control-sm text-center nilai-komponen"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-jenis="rem1"
                                                        >
                                                    </td>
                                                    <!-- Remedial 2 -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="komponen[<?= $sid; ?>][<?= $kid; ?>][rem2]"
                                                            class="form-control form-control-sm text-center nilai-komponen"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-jenis="rem2"
                                                        >
                                                    </td>
                                                    <!-- Nilai Akhir KD -->
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="nilai[<?= $sid; ?>][<?= $kid; ?>]"
                                                            class="form-control form-control-sm text-center nilai-akhir-kd"
                                                            min="0" max="100" step="1"
                                                            data-siswa="<?= $sid; ?>"
                                                            data-kd="<?= $kid; ?>"
                                                            data-kkm="<?= $kkmMapel; ?>"
                                                            value="<?= $naExist !== '' ? htmlspecialchars($naExist) : ''; ?>"
                                                            readonly
                                                        >
                                                        <div class="mt-1">
                                                            <span
                                                                class="badge bg-secondary predikat-badge"
                                                                data-siswa="<?= $sid; ?>"
                                                                data-kd="<?= $kid; ?>"
                                                            ><?= htmlspecialchars($pred); ?></span>
                                                        </div>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="mt-3 text-end">
                                    <button type="submit" class="btn btn-success">
                                        Simpan Nilai
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($selectedMapelId && $selectedKelas !== '' && !empty($kdList) && !empty($siswaList)): ?>
<script>
// Kirim deskripsi KD & KKM ke JS untuk membuat deskripsi otomatis (tooltip)
window.KD_DESKRIPSI = <?= json_encode(array_column($kdList, 'deskripsi', 'id')); ?>;
window.KKM_MAPEL = <?= (int)($mapelAktif['kkm'] ?? 0); ?>;
</script>
<?php endif; ?>

<script>
// JS: hitung rata-rata tugas, ulangan, remedial → nilai akhir KD, predikat & deskripsi (tooltip)

document.addEventListener('DOMContentLoaded', function () {
    const inputsKomponen = document.querySelectorAll('.nilai-komponen');
    const KKM_MAPEL = window.KKM_MAPEL || 0;
    const KD_DESKRIPSI = window.KD_DESKRIPSI || {};

    function parseNum(v) {
        if (v === null || v === undefined) return null;
        v = String(v).trim();
        if (v === '') return null;
        const n = parseFloat(v.replace(',', '.'));
        return isNaN(n) ? null : n;
    }

    function avg(values) {
        const valid = values.filter(v => v !== null);
        if (!valid.length) return null;
        const sum = valid.reduce((a, b) => a + b, 0);
        return sum / valid.length;
    }

    function calcPredikat(nilai) {
        if (nilai === null) return '-';
        if (nilai >= 90) return 'A';
        if (nilai >= 80) return 'B';
        if (nilai >= 70) return 'C';
        return 'D';
    }

    function makeDeskripsi(predikat, kdId) {
        const descKD = KD_DESKRIPSI[String(kdId)] || '(materi KD)';
        switch (predikat) {
            case 'A':
                return 'Memiliki kemampuan sangat baik dalam memahami ' + descKD + ' dengan benar.';
            case 'B':
                return 'Memiliki kemampuan baik dalam memahami ' + descKD + ' dengan benar.';
            case 'C':
                return 'Memiliki kemampuan yang mulai membaik dalam memahami ' + descKD + ' dengan benar.';
            case 'D':
                return 'Perlu memaksimalkan kemampuan dalam memahami ' + descKD + ' dengan benar.';
            default:
                return '';
        }
    }

    function recalcFor(siswaId, kdId) {
        const selectorBase = '.nilai-komponen[data-siswa="' + siswaId + '"][data-kd="' + kdId + '"]';

        function getVal(jenis) {
            const el = document.querySelector(selectorBase + '[data-jenis="' + jenis + '"]');
            return el ? parseNum(el.value) : null;
        }

        // Ambil semua komponen
        const p1   = getVal('p1');
        const p2   = getVal('p2');
        const p3   = getVal('p3');
        const u1   = getVal('u1');
        const u2   = getVal('u2');
        const rem1 = getVal('rem1');
        const rem2 = getVal('rem2');

        // Rata-rata tugas & ulangan
        const rtugas   = avg([p1, p2, p3]);
        const rulangan = avg([u1, u2]);

        const rtugasInput = document.querySelector('.nilai-ratatugas[data-siswa="' + siswaId + '"][data-kd="' + kdId + '"]');
        const rulanganInput = document.querySelector('.nilai-rataulangan[data-siswa="' + siswaId + '"][data-kd="' + kdId + '"]');
        const naInput = document.querySelector('.nilai-akhir-kd[data-siswa="' + siswaId + '"][data-kd="' + kdId + '"]');
        const predBadge = document.querySelector('.predikat-badge[data-siswa="' + siswaId + '"][data-kd="' + kdId + '"]');

        if (!rtugasInput || !rulanganInput || !naInput || !predBadge) return;

        rtugasInput.value   = rtugas   !== null ? rtugas.toFixed(1)   : '';
        rulanganInput.value = rulangan !== null ? rulangan.toFixed(1) : '';

        // Hitung nilai awal (sebelum remedial)
        let na = null;
        if (rtugas !== null && rulangan !== null) {
            na = (rtugas + rulangan) / 2;
        } else if (rtugas !== null) {
            na = rtugas;
        } else if (rulangan !== null) {
            na = rulangan;
        }

        // Terapkan remedial jika lebih tinggi dari nilai awal
        if (rem1 !== null) {
            if (na === null || rem1 > na) na = rem1;
        }
        if (rem2 !== null) {
            if (na === null || rem2 > na) na = rem2;
        }

        if (na === null) {
            naInput.value = '';
            naInput.classList.remove('tidak-tuntas');
            predBadge.textContent = '-';
            predBadge.className = 'badge bg-secondary predikat-badge';
            naInput.removeAttribute('title');
            predBadge.removeAttribute('title');
            return;
        }

        // Clamp & pembulatan
        if (na < 0)   na = 0;
        if (na > 100) na = 100;
        na = Math.round(na);

        naInput.value = na;

        // Tuntas / tidak tuntas
        const kkmAttr = parseNum(naInput.getAttribute('data-kkm'));
        const kkm = KKM_MAPEL || (kkmAttr || 0);
        const tuntas = (kkm > 0 && na >= kkm);

        if (tuntas) {
            naInput.classList.remove('tidak-tuntas');
        } else {
            naInput.classList.add('tidak-tuntas');
        }

        // Predikat
        const pred = calcPredikat(na);
        predBadge.textContent = pred;

        // Warna badge predikat
        let cls = 'badge predikat-badge ';
        switch (pred) {
            case 'A': cls += 'bg-success'; break;
            case 'B': cls += 'bg-primary'; break;
            case 'C': cls += 'bg-warning text-dark'; break;
            case 'D': cls += 'bg-danger'; break;
            default:  cls += 'bg-secondary'; break;
        }
        predBadge.className = cls;

        // Deskripsi otomatis -> simpan di tooltip (title)
        const deskripsi = makeDeskripsi(pred, kdId);
        if (deskripsi) {
            naInput.title = deskripsi;
            predBadge.title = deskripsi;
        } else {
            naInput.removeAttribute('title');
            predBadge.removeAttribute('title');
        }
    }

    function sanitizeInput(input) {
        let val = input.value.trim();
        if (val === '') {
            return;
        }
        let num = parseNum(val);
        if (num === null) {
            input.value = '';
            return;
        }
        if (num < 0)   num = 0;
        if (num > 100) num = 100;
        input.value = num;
    }

    inputsKomponen.forEach(function (input) {
        input.addEventListener('input', function () {
            sanitizeInput(input);
            const siswaId = input.getAttribute('data-siswa');
            const kdId    = input.getAttribute('data-kd');
            if (siswaId && kdId) {
                recalcFor(siswaId, kdId);
            }
        });
    });

    // Inisialisasi ulang untuk data lama (prefill)
    const pasangan = new Set();
    inputsKomponen.forEach(function (input) {
        const siswaId = input.getAttribute('data-siswa');
        const kdId    = input.getAttribute('data-kd');
        if (siswaId && kdId) {
            pasangan.add(siswaId + '|' + kdId);
        }
    });
    pasangan.forEach(function (key) {
        const [sid, kid] = key.split('|');
        recalcFor(sid, kid);
    });

    // Validasi global sebelum submit (nilai KD 0–100 atau kosong)
    const form = document.getElementById('form-nilai-pengetahuan');
    if (form) {
        form.addEventListener('submit', function (e) {
            const semuaNa = document.querySelectorAll('.nilai-akhir-kd');
            let invalid = false;
            semuaNa.forEach(function (input) {
                const v = input.value.trim();
                if (v === '') return;
                const n = parseNum(v);
                if (n === null || n < 0 || n > 100) invalid = true;
            });
            if (invalid) {
                e.preventDefault();
                alert('Pastikan semua Nilai KD berada antara 0 s.d. 100 atau dikosongkan.');
            }
        });
    }
});
</script>

</body>
</html>
