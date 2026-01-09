<?php
// guru/rekap_keterampilan.php
// Rekap nilai keterampilan per KD + deskripsi otomatis seperti Excel

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    die("guru_id kosong. Hubungi admin.");
}

// --- Helper deskripsi otomatis per KD ---
function deskripsi_keterampilan_kd(string $predikat, string $judulKd): string
{
    $judulKd = trim($judulKd);
    if ($judulKd === '') {
        $judulKd = 'kompetensi keterampilan yang dinilai';
    }

    switch (strtoupper($predikat)) {
        case 'A':
            $frase = 'memiliki keterampilan sangat baik dalam ';
            break;
        case 'B':
            $frase = 'memiliki keterampilan baik dalam ';
            break;
        case 'C':
            $frase = 'memiliki keterampilan mulai membaik dalam ';
            break;
        default: // D atau kosong
            $frase = 'perlu memaksimalkan keterampilan dalam ';
            break;
    }

    return 'Peserta didik ' . $frase . $judulKd . ' dengan benar.';
}

// --- Data guru (untuk tampilan) ---
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);
$namaGuru = $guru['name'] ?? ($_SESSION['user']['username'] ?? '-');

// --- Daftar mapel yang diampu guru ---
$stmtMapel = $pdo->prepare("
    SELECT id, nama, kkm
    FROM mapel
    WHERE teacher_id = ?
    ORDER BY nama
");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Kelas tetap 10 / 11 / 12
$kelasList = ['10','11','12'];

// --- Ambil filter dari GET ---
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
$selectedKelas   = $_GET['kelas'] ?? '';
$selectedKdId    = isset($_GET['kd_id']) ? (int)$_GET['kd_id'] : 0;

// Untuk judul header (Nama mapel & KD)
$mapelAktif = null;
$kdList     = [];
$kdAktif    = null;
$siswaList  = [];
$nilaiMap   = []; // [siswa_id] => ['nilai_akhir'=>.., 'predikat'=>.., 'deskripsi_otomatis'=>..]

if ($selectedMapelId) {
    // Mapel aktif
    $stmtOneMapel = $pdo->prepare("SELECT * FROM mapel WHERE id = ? LIMIT 1");
    $stmtOneMapel->execute([$selectedMapelId]);
    $mapelAktif = $stmtOneMapel->fetch(PDO::FETCH_ASSOC);

    // Semua KD untuk mapel (buat dropdown KD)
    $stmtKd = $pdo->prepare("
        SELECT id, kd_ke, deskripsi
        FROM kd_keterampilan
        WHERE mapel_id = ?
        ORDER BY kd_ke
    ");
    $stmtKd->execute([$selectedMapelId]);
    $kdList = $stmtKd->fetchAll(PDO::FETCH_ASSOC);

    // Kalau kd_id belum dipilih, otomatis ambil KD pertama (jika ada)
    if ($selectedKdId === 0 && !empty($kdList)) {
        $selectedKdId = (int)$kdList[0]['id'];
    }

    // Detail KD aktif
    foreach ($kdList as $rowKd) {
        if ((int)$rowKd['id'] === $selectedKdId) {
            $kdAktif = $rowKd;
            break;
        }
    }
}

if ($selectedMapelId && $selectedKelas !== '' && $selectedKdId && $kdAktif) {

    // Ambil siswa di kelas
    $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($siswaList)) {
        $siswaIds = array_column($siswaList, 'id');
        $in       = implode(',', array_fill(0, count($siswaIds), '?'));

        // Ambil nilai_akhir & predikat dari tabel nilai_keterampilan
        $sqlNilai = "
            SELECT siswa_id, nilai_akhir, predikat
            FROM nilai_keterampilan
            WHERE mapel_id = ?
              AND kd_id    = ?
              AND siswa_id IN ($in)
        ";
        $params = array_merge([$selectedMapelId, $selectedKdId], $siswaIds);
        $stmtNilai = $pdo->prepare($sqlNilai);
        $stmtNilai->execute($params);
        $rows = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

        $judulKd = $kdAktif['deskripsi'] ?? '';
        foreach ($rows as $r) {
            $sid = (int)$r['siswa_id'];
            $nilaiAkhir = $r['nilai_akhir'];
            $pred = $r['predikat'] ?? '';

            $desk = '';
            if ($pred !== '' && $nilaiAkhir !== null) {
                $desk = deskripsi_keterampilan_kd($pred, $judulKd);
            }

            $nilaiMap[$sid] = [
                'nilai_akhir'        => $nilaiAkhir,
                'predikat'           => $pred,
                'deskripsi_otomatis' => $desk,
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Keterampilan per KD</title>
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

    <!-- FILTER -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Rekap Nilai Keterampilan per KD</h5>

            <?php if (empty($mapelList)): ?>
                <div class="alert alert-warning">
                    Anda belum dihubungkan dengan mapel apapun di tabel <code>mapel</code>. 
                    Hubungi admin untuk mengatur guru pengampu mapel.
                </div>
            <?php endif; ?>

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
                    <label class="form-label">KD</label>
                    <select name="kd_id" class="form-select" <?= $selectedMapelId ? '' : 'disabled'; ?>>
                        <?php if (empty($kdList)): ?>
                            <option value="">(KD belum dibuat)</option>
                        <?php else: ?>
                            <?php foreach ($kdList as $kd): ?>
                                <option value="<?= $kd['id']; ?>"
                                    <?= $selectedKdId == $kd['id'] ? 'selected' : ''; ?>>
                                    KD <?= htmlspecialchars($kd['kd_ke']); ?> - 
                                    <?= htmlspecialchars($kd['deskripsi']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL REKAP -->
    <?php if ($selectedMapelId && $selectedKelas !== '' && $selectedKdId && $kdAktif && !empty($siswaList)): ?>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-success text-white">
                KETERAMPILAN &mdash; KD <?= htmlspecialchars($kdAktif['kd_ke']); ?>
                <br>
                <small>
                    Mapel: <?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?> |
                    KKM: <?= (int)($mapelAktif['kkm'] ?? 0); ?> |
                    Kelas: <?= htmlspecialchars($selectedKelas); ?>
                </small>
                <br>
                <small>
                    Judul KD: <?= htmlspecialchars($kdAktif['deskripsi']); ?>
                </small>
            </div>
            <div class="card-body table-responsive">

                <table class="table table-bordered table-sm align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width:40px;">No</th>
                            <th style="width:120px;">NIS</th>
                            <th style="width:120px;">NISN</th>
                            <th>Nama Peserta Didik</th>
                            <th style="width:60px;">L/P</th>
                            <th style="width:80px;">Nilai</th>
                            <th style="width:70px;">Predikat</th>
                            <th>Deskripsi KD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($siswaList as $s): 
                            $sid  = $s['id'];
                            $rowN = $nilaiMap[$sid] ?? null;

                            $nilai  = $rowN['nilai_akhir'] ?? null;
                            $pred   = $rowN['predikat'] ?? '';
                            $desk   = $rowN['deskripsi_otomatis'] ?? '';

                            $lp = $s['jk'] ?? ($s['jenis_kelamin'] ?? '');
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td class="text-center"><?= htmlspecialchars($s['nis'] ?? ''); ?></td>
                            <td class="text-center"><?= htmlspecialchars($s['nisn'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($s['nama']); ?></td>
                            <td class="text-center"><?= htmlspecialchars($lp); ?></td>
                            <td class="text-center">
                                <?= $nilai !== null ? number_format($nilai, 0) : '-'; ?>
                            </td>
                            <td class="text-center">
                                <?= htmlspecialchars($pred ?: '-'); ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($desk); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>

    <?php elseif ($selectedMapelId && $selectedKelas !== '' && !empty($siswaList) && !$kdAktif): ?>

        <div class="alert alert-warning">
            KD belum dipilih atau tidak ditemukan untuk mapel ini.
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
