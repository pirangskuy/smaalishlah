<?php
// guru/setor_nilai_mapel.php
// Rekap akhir per mapel, siap disetor ke wali kelas

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';
require_once __DIR__ . '/../includes/nilai_helper.php';

requireLogin();
requireGuru();

$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    die("guru_id kosong. Hubungi admin.");
}

// data guru & mapel
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);
$namaGuru = $guru['name'] ?? ($_SESSION['user']['username'] ?? '-');

$stmtMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE teacher_id = ? ORDER BY nama");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

$kelasList = ['10','11','12'];

// default (boleh diubah guru di form)
$tahun_ajaran_default = '2025/2026';
$semester_default      = 'Ganjil';

// nilai tahun ajaran & semester yang dipakai di HALAMAN (GET)
$tahunAjaran = $_GET['tahun_ajaran'] ?? $tahun_ajaran_default;
$semester    = $_GET['semester'] ?? $semester_default;

/**
 * Helper: deteksi apakah mapel ini PAI atau PPKn
 */
function mapel_is_pai(string $nama): bool {
    $nm = strtolower($nama);
    return strpos($nm, 'pai') !== false
        || strpos($nm, 'ibadah syariah') !== false
        || strpos($nm, 'agama') !== false;
}
function mapel_is_ppkn(string $nama): bool {
    $nm = strtolower($nama);
    return strpos($nm, 'ppkn') !== false
        || strpos($nm, 'ppk') !== false;
}

// ================== PROSES POST: SETOR NILAI ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'setor') {

    $mapel_id        = (int)($_POST['mapel_id'] ?? 0);
    $kelas           = $_POST['kelas'] ?? '';
    $tahunAjaranPost = $_POST['tahun_ajaran'] ?? $tahun_ajaran_default;
    $semesterPost    = $_POST['semester'] ?? $semester_default;

    if ($mapel_id <= 0 || $kelas === '') {
        die("Data setor tidak lengkap.");
    }

    // cek jenis mapel (PAI / PPKn / lain)
    $stmtM = $pdo->prepare("SELECT nama FROM mapel WHERE id = ? LIMIT 1");
    $stmtM->execute([$mapel_id]);
    $namaMapelRow = $stmtM->fetch(PDO::FETCH_ASSOC);
    $namaMapel = $namaMapelRow['nama'] ?? '';
    $isPAI  = mapel_is_pai($namaMapel);
    $isPPKn = mapel_is_ppkn($namaMapel);

    $nilaiPenArr  = $_POST['nilai_pengetahuan'] ?? [];
    $predPenArr   = $_POST['predikat_pengetahuan'] ?? [];
    $deskPenArr   = $_POST['deskripsi_pengetahuan'] ?? [];

    $nilaiKetArr  = $_POST['nilai_keterampilan'] ?? [];
    $predKetArr   = $_POST['predikat_keterampilan'] ?? [];
    $deskKetArr   = $_POST['deskripsi_keterampilan'] ?? [];

    $nilaiSikapArr = $_POST['nilai_sikap'] ?? [];
    $predSikapArr  = $_POST['predikat_sikap'] ?? [];
    $deskSikapArr  = $_POST['deskripsi_sikap'] ?? [];

    $utsArr       = $_POST['uts'] ?? [];
    $uasArr       = $_POST['uas'] ?? [];

    $pdo->beginTransaction();
    try {
        $sqlCek = $pdo->prepare("
            SELECT id FROM rapor_mapel
            WHERE tahun_ajaran = ? AND semester = ? AND siswa_id = ? AND mapel_id = ?
            LIMIT 1
        ");

        $sqlIns = $pdo->prepare("
            INSERT INTO rapor_mapel
            (tahun_ajaran, semester, kelas, siswa_id, mapel_id, guru_id,
             nilai_pengetahuan, predikat_pengetahuan, deskripsi_pengetahuan,
             nilai_keterampilan, predikat_keterampilan, deskripsi_keterampilan,
             nilai_sikap, predikat_sikap, deskripsi_sikap,
             uts, uas, tgl_setor)
            VALUES
            (:tahun_ajaran, :semester, :kelas, :siswa_id, :mapel_id, :guru_id,
             :n_pen, :p_pen, :d_pen,
             :n_ket, :p_ket, :d_ket,
             :n_sikap, :p_sikap, :d_sikap,
             :uts, :uas, NOW())
        ");

        $sqlUpd = $pdo->prepare("
            UPDATE rapor_mapel
            SET kelas = :kelas,
                nilai_pengetahuan = :n_pen,
                predikat_pengetahuan = :p_pen,
                deskripsi_pengetahuan = :d_pen,
                nilai_keterampilan = :n_ket,
                predikat_keterampilan = :p_ket,
                deskripsi_keterampilan = :d_ket,
                nilai_sikap = :n_sikap,
                predikat_sikap = :p_sikap,
                deskripsi_sikap = :d_sikap,
                uts = :uts,
                uas = :uas,
                tgl_setor = NOW()
            WHERE id = :id
        ");

        foreach ($nilaiPenArr as $siswa_id => $nPen) {
            $siswa_id = (int)$siswa_id;

            $n_pen   = $nPen !== '' ? (float)$nPen : null;
            $p_pen   = $predPenArr[$siswa_id] ?? null;
            $d_pen   = $deskPenArr[$siswa_id] ?? null;

            $n_ket   = isset($nilaiKetArr[$siswa_id]) && $nilaiKetArr[$siswa_id] !== '' ? (float)$nilaiKetArr[$siswa_id] : null;
            $p_ket   = $predKetArr[$siswa_id] ?? null;
            $d_ket   = $deskKetArr[$siswa_id] ?? null;

            // SIKAP: hanya dipakai jika PAI / PPKn
            if ($isPAI || $isPPKn) {
                $n_sikap = isset($nilaiSikapArr[$siswa_id]) && $nilaiSikapArr[$siswa_id] !== '' ? (float)$nilaiSikapArr[$siswa_id] : null;
                $p_sikap = $predSikapArr[$siswa_id] ?? null;
                $d_sikap = $deskSikapArr[$siswa_id] ?? null;
            } else {
                $n_sikap = null;
                $p_sikap = null;
                $d_sikap = null;
            }

            $uts     = isset($utsArr[$siswa_id]) && $utsArr[$siswa_id] !== '' ? (float)$utsArr[$siswa_id] : null;
            $uas     = isset($uasArr[$siswa_id]) && $uasArr[$siswa_id] !== '' ? (float)$uasArr[$siswa_id] : null;

            // kalau semua kosong, skip
            if ($n_pen === null && $n_ket === null && $n_sikap === null && $uts === null && $uas === null) {
                continue;
            }

            $sqlCek->execute([$tahunAjaranPost, $semesterPost, $siswa_id, $mapel_id]);
            $existing = $sqlCek->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $sqlUpd->execute([
                    ':kelas'    => $kelas,
                    ':n_pen'    => $n_pen,
                    ':p_pen'    => $p_pen,
                    ':d_pen'    => $d_pen,
                    ':n_ket'    => $n_ket,
                    ':p_ket'    => $p_ket,
                    ':d_ket'    => $d_ket,
                    ':n_sikap'  => $n_sikap,
                    ':p_sikap'  => $p_sikap,
                    ':d_sikap'  => $d_sikap,
                    ':uts'      => $uts,
                    ':uas'      => $uas,
                    ':id'       => $existing['id'],
                ]);
            } else {
                $sqlIns->execute([
                    ':tahun_ajaran' => $tahunAjaranPost,
                    ':semester'     => $semesterPost,
                    ':kelas'        => $kelas,
                    ':siswa_id'     => $siswa_id,
                    ':mapel_id'     => $mapel_id,
                    ':guru_id'      => $guruId,
                    ':n_pen'        => $n_pen,
                    ':p_pen'        => $p_pen,
                    ':d_pen'        => $d_pen,
                    ':n_ket'        => $n_ket,
                    ':p_ket'        => $p_ket,
                    ':d_ket'        => $d_ket,
                    ':n_sikap'      => $n_sikap,
                    ':p_sikap'      => $p_sikap,
                    ':d_sikap'      => $d_sikap,
                    ':uts'          => $uts,
                    ':uas'          => $uas,
                ]);
            }
        }

        $pdo->commit();
        // redirect sambil bawa tahun & semester yang dipakai
        header("Location: setor_nilai_mapel.php?mapel_id=$mapel_id&kelas=$kelas&tahun_ajaran=".urlencode($tahunAjaranPost)."&semester=".urlencode($semesterPost)."&saved=1");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Gagal menyimpan rapor_mapel: " . htmlspecialchars($e->getMessage()));
    }
}

// ================== MODE GET: TAMPILAN REKAP ==================
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
$selectedKelas   = $_GET['kelas'] ?? '';

$mapelAktif = null;
$siswaList  = [];
$dataRekap  = []; // [siswa_id] => data

$isPAI  = false;
$isPPKn = false;

if ($selectedMapelId && $selectedKelas !== '') {
    $stmtOneMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE id = ? LIMIT 1");
    $stmtOneMapel->execute([$selectedMapelId]);
    $mapelAktif = $stmtOneMapel->fetch(PDO::FETCH_ASSOC);

    if ($mapelAktif) {
        $isPAI  = mapel_is_pai($mapelAktif['nama']);
        $isPPKn = mapel_is_ppkn($mapelAktif['nama']);
    }

    // siswa
    $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($siswaList)) {
        $siswaIds = array_column($siswaList, 'id');
        $in = implode(',', array_fill(0, count($siswaIds), '?'));

        // ================= PENGETAHUAN =================
        // rata-rata nilai_akhir per siswa per mapel
        $sqlPen = "
            SELECT siswa_id, AVG(nilai_akhir) AS rata
            FROM nilai_pengetahuan
            WHERE mapel_id = ?
              AND tahun_ajaran = ?
              AND semester = ?
              AND siswa_id IN ($in)
            GROUP BY siswa_id
        ";
        $paramsPen = array_merge(
            [$selectedMapelId, $tahunAjaran, $semester],
            $siswaIds
        );
        $stmtPen = $pdo->prepare($sqlPen);
        $stmtPen->execute($paramsPen);
        foreach ($stmtPen->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dataRekap[$r['siswa_id']]['nh_pengetahuan'] = (float)$r['rata'];
        }

        // ================= KETERAMPILAN =================
        $sqlKet = "
            SELECT siswa_id, AVG(nilai_akhir) AS rata
            FROM nilai_keterampilan
            WHERE mapel_id = ?
              AND tahun_ajaran = ?
              AND semester = ?
              AND siswa_id IN ($in)
            GROUP BY siswa_id
        ";
        $paramsKet = array_merge(
            [$selectedMapelId, $tahunAjaran, $semester],
            $siswaIds
        );
        $stmtKet = $pdo->prepare($sqlKet);
        $stmtKet->execute($paramsKet);
        foreach ($stmtKet->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dataRekap[$r['siswa_id']]['nh_keterampilan'] = (float)$r['rata'];
        }

        // ================= UTS & UAS =================
        // tabel nilai_uts_uas belum punya tahun_ajaran & semester
        $sqlUU = "
            SELECT siswa_id, uts, uas
            FROM nilai_uts_uas
            WHERE mapel_id = ?
              AND siswa_id IN ($in)
        ";
        $paramsUU = array_merge([$selectedMapelId], $siswaIds);
        $stmtUU = $pdo->prepare($sqlUU);
        $stmtUU->execute($paramsUU);
        foreach ($stmtUU->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dataRekap[$r['siswa_id']]['uts'] = $r['uts'] !== null ? (float)$r['uts'] : null;
            $dataRekap[$r['siswa_id']]['uas'] = $r['uas'] !== null ? (float)$r['uas'] : null;
        }

        // ================= SIKAP (PAI / PPKn) =================
        if ($isPAI || $isPPKn) {
            if ($isPAI) {
                $sqlSikap = "
                    SELECT siswa_id, nilai_dominan, predikat, deskripsi
                    FROM sikap_spiritual
                    WHERE mapel_id = ?
                      AND tahun_ajaran = ?
                      AND semester = ?
                      AND siswa_id IN ($in)
                ";
                $isSpiritual = true;
            } else {
                $sqlSikap = "
                    SELECT siswa_id, nilai_dominan, predikat, deskripsi
                    FROM sikap_sosial
                    WHERE mapel_id = ?
                      AND tahun_ajaran = ?
                      AND semester = ?
                      AND siswa_id IN ($in)
                ";
                $isSpiritual = false;
            }

            $paramsSikap = array_merge(
                [$selectedMapelId, $tahunAjaran, $semester],
                $siswaIds
            );
            $stmtSikap = $pdo->prepare($sqlSikap);
            $stmtSikap->execute($paramsSikap);
            foreach ($stmtSikap->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $sid = $r['siswa_id'];
                $n  = $r['nilai_dominan'] !== null ? (float)$r['nilai_dominan'] : null;
                $p  = $r['predikat'] ?? null;
                $d  = $r['deskripsi'] ?? null;

                if ($n !== null && !$d) {
                    $d = deskripsi_sikap($n, $isSpiritual);
                }

                $dataRekap[$sid]['n_sikap'] = $n;
                $dataRekap[$sid]['p_sikap'] = $p ?: ($n !== null ? predikat_dari_nilai($n) : null);
                $dataRekap[$sid]['d_sikap'] = $d;
            }
        }

        // Hitung nilai akhir + deskripsi pengetahuan & keterampilan
        $namaMapel = $mapelAktif['nama'] ?? 'mapel';
        foreach ($siswaIds as $sid) {
            $nhP = $dataRekap[$sid]['nh_pengetahuan']  ?? null;
            $nhK = $dataRekap[$sid]['nh_keterampilan'] ?? null;
            $uts = $dataRekap[$sid]['uts'] ?? null;
            $uas = $dataRekap[$sid]['uas'] ?? null;

            $nPen = hitung_nilai_pengetahuan_rapor($nhP, $uts, $uas);
            $pPen = $nPen !== null ? predikat_dari_nilai($nPen) : '-';
            $dPen = $nPen !== null ? deskripsi_pengetahuan($nPen, $namaMapel) : '';

            $nKet = $nhK !== null ? round($nhK, 1) : null;
            $pKet = $nKet !== null ? predikat_dari_nilai($nKet) : '-';
            $dKet = $nKet !== null ? deskripsi_keterampilan($nKet, $namaMapel) : '';

            $dataRekap[$sid]['n_pen']  = $nPen;
            $dataRekap[$sid]['p_pen']  = $pPen;
            $dataRekap[$sid]['d_pen']  = $dPen;

            $dataRekap[$sid]['n_ket']  = $nKet;
            $dataRekap[$sid]['p_ket']  = $pKet;
            $dataRekap[$sid]['d_ket']  = $dKet;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Setor Nilai ke Wali Kelas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="guru_dashboard.php">Dashboard Guru</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-white"><?= htmlspecialchars($namaGuru); ?></span>
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
        <div class="alert alert-success">Nilai berhasil disetor ke wali kelas.</div>
    <?php endif; ?>

    <!-- FILTER -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="mapel_id" class="form-select" required>
                        <option value="">-- Pilih Mapel --</option>
                        <?php foreach ($mapelList as $m): ?>
                            <option value="<?= $m['id']; ?>" <?= $selectedMapelId == $m['id'] ? 'selected' : ''; ?>>
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
                    <label class="form-label">Tahun Pelajaran</label>
                    <input type="text" name="tahun_ajaran" class="form-control"
                           value="<?= htmlspecialchars($tahunAjaran); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                        <option value="Genap"  <?= $semester === 'Genap'  ? 'selected' : ''; ?>>Genap</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end mt-2">
                    <button type="submit" class="btn btn-primary">Tampilkan Rekap</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedMapelId && $selectedKelas !== '' && !empty($siswaList)): ?>
        <?php
            $isPAI  = $mapelAktif ? mapel_is_pai($mapelAktif['nama']) : false;
            $isPPKn = $mapelAktif ? mapel_is_ppkn($mapelAktif['nama']) : false;
        ?>
        <form method="post">
            <input type="hidden" name="aksi" value="setor">
            <input type="hidden" name="mapel_id" value="<?= $selectedMapelId; ?>">
            <input type="hidden" name="kelas" value="<?= htmlspecialchars($selectedKelas); ?>">
            <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran); ?>">
            <input type="hidden" name="semester" value="<?= htmlspecialchars($semester); ?>">

            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    FORMAT NILAI RAPOR MATA PELAJARAN
                    <br>
                    <small>
                        Tahun Pelajaran <?= htmlspecialchars($tahunAjaran); ?> •
                        Semester <?= htmlspecialchars($semester); ?> •
                        Kelas <?= htmlspecialchars($selectedKelas); ?> •
                        Mapel: <?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?> •
                        Guru: <?= htmlspecialchars($namaGuru); ?>
                    </small>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th rowspan="2" style="width:40px;">No</th>
                                <th rowspan="2" style="width:120px;">NIS</th>
                                <th rowspan="2" style="width:120px;">NISN</th>
                                <th rowspan="2">Nama Siswa</th>
                                <th rowspan="2" style="width:60px;">L/P</th>
                                <th colspan="2">Pengetahuan</th>
                                <th colspan="2">Keterampilan</th>
                                <?php if ($isPAI || $isPPKn): ?>
                                    <th colspan="2">
                                        <?= $isPAI ? 'Sikap Spiritual' : 'Sikap Sosial'; ?>
                                    </th>
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
                                <th>Nilai</th><th>Pred</th>
                                <th>Nilai</th><th>Pred</th>
                                <?php if ($isPAI || $isPPKn): ?>
                                    <th>Nilai</th><th>Pred</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no=1; foreach ($siswaList as $s):
                                $sid = $s['id'];
                                $r   = $dataRekap[$sid] ?? [];
                                $nPen = $r['n_pen'] ?? null;
                                $pPen = $r['p_pen'] ?? '-';
                                $dPen = $r['d_pen'] ?? '';
                                $nKet = $r['n_ket'] ?? null;
                                $pKet = $r['p_ket'] ?? '-';
                                $dKet = $r['d_ket'] ?? '';
                                $uts  = $r['uts']  ?? null;
                                $uas  = $r['uas']  ?? null;

                                $nSikap = $r['n_sikap'] ?? null;
                                $pSikap = $r['p_sikap'] ?? '-';
                                $dSikap = $r['d_sikap'] ?? '';

                                $lp   = $s['jk'] ?? ($s['jenis_kelamin'] ?? '');
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++; ?></td>
                                <td class="text-center"><?= htmlspecialchars($s['nis'] ?? ''); ?></td>
                                <td class="text-center"><?= htmlspecialchars($s['nisn'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($s['nama']); ?></td>
                                <td class="text-center"><?= htmlspecialchars($lp); ?></td>

                                <!-- PENGETAHUAN -->
                                <td class="text-center">
                                    <?= $nPen !== null ? number_format($nPen,1) : '-'; ?>
                                    <input type="hidden" name="nilai_pengetahuan[<?= $sid; ?>]" value="<?= $nPen; ?>">
                                    <input type="hidden" name="uts[<?= $sid; ?>]" value="<?= $uts; ?>">
                                    <input type="hidden" name="uas[<?= $sid; ?>]" value="<?= $uas; ?>">
                                </td>
                                <td class="text-center">
                                    <?= htmlspecialchars($pPen); ?>
                                    <input type="hidden" name="predikat_pengetahuan[<?= $sid; ?>]" value="<?= htmlspecialchars($pPen); ?>">
                                </td>

                                <!-- KETERAMPILAN -->
                                <td class="text-center">
                                    <?= $nKet !== null ? number_format($nKet,1) : '-'; ?>
                                    <input type="hidden" name="nilai_keterampilan[<?= $sid; ?>]" value="<?= $nKet; ?>">
                                </td>
                                <td class="text-center">
                                    <?= htmlspecialchars($pKet); ?>
                                    <input type="hidden" name="predikat_keterampilan[<?= $sid; ?>]" value="<?= htmlspecialchars($pKet); ?>">
                                </td>

                                <!-- SIKAP (hanya PAI / PPKn) -->
                                <?php if ($isPAI || $isPPKn): ?>
                                    <td class="text-center">
                                        <?= $nSikap !== null ? number_format($nSikap,1) : '-'; ?>
                                        <input type="hidden" name="nilai_sikap[<?= $sid; ?>]" value="<?= $nSikap; ?>">
                                    </td>
                                    <td class="text-center">
                                        <?= htmlspecialchars($pSikap ?: '-'); ?>
                                        <input type="hidden" name="predikat_sikap[<?= $sid; ?>]" value="<?= htmlspecialchars($pSikap); ?>">
                                    </td>
                                <?php endif; ?>

                                <!-- DESKRIPSI -->
                                <td>
                                    <?= htmlspecialchars($dPen); ?>
                                    <input type="hidden" name="deskripsi_pengetahuan[<?= $sid; ?>]" value="<?= htmlspecialchars($dPen); ?>">
                                </td>
                                <td>
                                    <?= htmlspecialchars($dKet); ?>
                                    <input type="hidden" name="deskripsi_keterampilan[<?= $sid; ?>]" value="<?= htmlspecialchars($dKet); ?>">
                                </td>
                                <?php if ($isPAI || $isPPKn): ?>
                                    <td>
                                        <?= htmlspecialchars($dSikap); ?>
                                        <input type="hidden" name="deskripsi_sikap[<?= $sid; ?>]" value="<?= htmlspecialchars($dSikap); ?>">
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-success">
                            Setor Nilai ke Wali Kelas
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
