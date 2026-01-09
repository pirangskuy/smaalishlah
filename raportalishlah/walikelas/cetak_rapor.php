<?php
// walikelas/cetak_rapor.php
// Cetak rapor 5 page + absensi + ekskul + prestasi
// + Dropdown Tahun Ajaran & Semester otomatis dari data yg tersimpan

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/wali_auth.php';

requireWaliKelas();

/**
 * 1. AMBIL DATA GURU WALI & KELAS WALI
 */
$waliGuru  = null;
$kelasWali = null;

if (isset($_SESSION['guru']) && is_array($_SESSION['guru'])) {
    $waliGuru  = $_SESSION['guru'];
    $kelasWali = $waliGuru['kelas_wali'] ?? null;
}

if (!$kelasWali) {
    if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'walikelas') {
        die("<p style='color:red;'>Session wali kelas tidak valid. Silakan login ulang.</p>");
    }

    $user   = $_SESSION['user'];
    $guruId = $user['guru_id'] ?? null;

    if ($guruId) {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
        $stmt->execute([$guruId]);
        $waliGuru = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE username = ? LIMIT 1");
        $stmt->execute([$user['username']]);
        $waliGuru = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$waliGuru) {
        die("<p style='color:red;'>Data guru wali tidak ditemukan pada tabel <code>teachers</code>.</p>");
    }

    $_SESSION['guru'] = $waliGuru;
    $kelasWali        = $waliGuru['kelas_wali'] ?? null;
}

if (!$kelasWali) {
    die("<p style='color:red;'>Guru ini belum memiliki kelas wali. Isi kolom <code>kelas_wali</code> di tabel <code>teachers</code>.</p>");
}

$namaWali = $waliGuru['name'] ?? 'Wali Kelas';

/**
 * 2. AMBIL OPSI TAHUN AJARAN + SEMESTER YANG BENAR-BENAR ADA DI DB (untuk kelas ini)
 *    Sumber: rapor_mapel, rapor, sikap_spiritual, sikap_sosial, ekskul_nilai, prestasi
 */
$opts = [];
try {
    $stmtOpt = $pdo->prepare("
        SELECT DISTINCT tahun_ajaran, semester FROM (
            SELECT rm.tahun_ajaran, rm.semester
            FROM rapor_mapel rm
            JOIN siswa s ON s.id = rm.siswa_id
            WHERE s.kelas = :kelas

            UNION
            SELECT r.tahun_ajaran, r.semester
            FROM rapor r
            JOIN siswa s ON s.id = r.siswa_id
            WHERE s.kelas = :kelas

            UNION
            SELECT sp.tahun_ajaran, sp.semester
            FROM sikap_spiritual sp
            JOIN siswa s ON s.id = sp.siswa_id
            WHERE s.kelas = :kelas

            UNION
            SELECT ss.tahun_ajaran, ss.semester
            FROM sikap_sosial ss
            JOIN siswa s ON s.id = ss.siswa_id
            WHERE s.kelas = :kelas

            UNION
            SELECT e.tahun_ajaran, e.semester
            FROM ekskul_nilai e
            JOIN siswa s ON s.id = e.siswa_id
            WHERE s.kelas = :kelas

            UNION
            SELECT p.tahun_ajaran, p.semester
            FROM prestasi p
            JOIN siswa s ON s.id = p.student_id
            WHERE s.kelas = :kelas
        ) x
        WHERE tahun_ajaran IS NOT NULL AND tahun_ajaran <> ''
          AND semester IS NOT NULL AND semester <> ''
        ORDER BY tahun_ajaran DESC, FIELD(semester,'Genap','Ganjil'), semester DESC
    ");
    $stmtOpt->execute([':kelas' => $kelasWali]);
    $opts = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $opts = [];
}

// default tahun+semester:
// - kalau ada data di DB -> pakai yang paling atas
// - kalau tidak ada -> fallback ke tahun sekarang & Ganjil
$defaultTA  = $opts[0]['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1));
$defaultSem = $opts[0]['semester'] ?? 'Ganjil';

// parameter dari GET
$tahunAjaran = $_GET['tahun_ajaran'] ?? $defaultTA;
$semester    = $_GET['semester'] ?? $defaultSem;
$siswaId     = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;

// validasi semester
if (!in_array($semester, ['Ganjil','Genap'], true)) $semester = $defaultSem;

/* ==========================================================
   MODE 1 : BELUM PILIH SISWA → TAMPILKAN LIST SISWA KELAS WALI
   ========================================================== */
if ($siswaId <= 0) {
    $stmtS = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtS->execute([$kelasWali]);
    $siswaList = $stmtS->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Pilih Siswa - Cetak Rapor</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            .badge-soft { background:#e8f5e9; color:#1b5e20; }
        </style>
    </head>
    <body class="bg-light">
    <div class="container mt-4 mb-5">
        <h3>Cetak Rapor Kelas <?= htmlspecialchars((string)$kelasWali); ?></h3>
        <p>Wali Kelas: <strong><?= htmlspecialchars($namaWali); ?></strong></p>

        <div class="alert alert-info small">
            Dropdown Tahun Ajaran & Semester ini otomatis menampilkan <b>data yang memang sudah tersimpan</b> di database untuk kelas ini.
        </div>

        <form method="get" class="row g-3 mb-3">
            <div class="col-md-5">
                <label class="form-label">Tahun Pelajaran (Tersimpan)</label>
                <select name="tahun_ajaran" class="form-select">
                    <?php if (empty($opts)): ?>
                        <option value="<?= htmlspecialchars($defaultTA) ?>" selected><?= htmlspecialchars($defaultTA) ?></option>
                    <?php else: ?>
                        <?php
                        $taUniq = [];
                        foreach ($opts as $o) $taUniq[$o['tahun_ajaran']] = true;
                        foreach (array_keys($taUniq) as $ta):
                        ?>
                            <option value="<?= htmlspecialchars($ta) ?>" <?= $tahunAjaran===$ta?'selected':''; ?>>
                                <?= htmlspecialchars($ta) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Semester (Tersimpan)</label>
                <select name="semester" class="form-select">
                    <option value="Ganjil" <?= $semester==='Ganjil'?'selected':''; ?>>Ganjil</option>
                    <option value="Genap"  <?= $semester==='Genap'?'selected':''; ?>>Genap</option>
                </select>
                <div class="form-text">
                    Dipakai untuk mengambil nilai & ekskul sesuai semester.
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label">Kelas</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars((string)$kelasWali); ?>" disabled>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Terapkan</button>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="card-title mb-0">Daftar Siswa</h5>
                    <span class="badge badge-soft">
                        <?= htmlspecialchars($tahunAjaran) ?> • <?= htmlspecialchars($semester) ?>
                    </span>
                </div>

                <?php if (empty($siswaList)): ?>
                    <div class="alert alert-warning">Belum ada siswa di kelas ini.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;">No</th>
                                    <th style="width:100px;">NIS</th>
                                    <th style="width:120px;">NISN</th>
                                    <th>Nama Siswa</th>
                                    <th style="width:150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no=1; foreach ($siswaList as $s): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($s['nis'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($s['nisn'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($s['nama']); ?></td>
                                        <td class="text-center">
                                            <a class="btn btn-sm btn-success"
                                               target="_blank"
                                               href="cetak_rapor.php?siswa_id=<?= (int)$s['id']; ?>&tahun_ajaran=<?= urlencode($tahunAjaran); ?>&semester=<?= urlencode($semester); ?>&autoprint=1">
                                                Cetak (Print)
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="wali_dashboard.php" class="btn btn-secondary mt-3">Kembali ke Dashboard</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

/* ==========================================================
   MODE 2 : SUDAH PILIH SISWA → CETAK 5 HALAMAN RAPOR
   ========================================================== */

/**
 * 3. DATA SISWA
 */
$stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE id = ? LIMIT 1");
$stmtSiswa->execute([$siswaId]);
$siswa = $stmtSiswa->fetch(PDO::FETCH_ASSOC);

if (!$siswa) {
    die("<p style='color:red;'>Data siswa tidak ditemukan.</p>");
}
if ((string)$siswa['kelas'] !== (string)$kelasWali) {
    die("<p style='color:red;'>Siswa ini bukan dari kelas yang Anda wali.</p>");
}

/**
 * 4. DATA RAPOR (catatan wali, dst)
 */
$stmtRapor = $pdo->prepare("
    SELECT *
    FROM rapor
    WHERE siswa_id = ? AND tahun_ajaran = ? AND semester = ?
    LIMIT 1
");
$stmtRapor->execute([$siswaId, $tahunAjaran, $semester]);
$raporRow = $stmtRapor->fetch(PDO::FETCH_ASSOC) ?: null;

$catatanWali = $raporRow['catatan_wali'] ?? '';

/**
 * 5. ABSENSI: attendance_rekap
 * NOTE: tabel kamu saat ini tidak memfilter tahun/semester (global). Jika ingin per semester, nanti kita upgrade skemanya.
 */
$hadir = $sakit = $izin = $alfa = 0;

$stmtAbs = $pdo->prepare("SELECT * FROM attendance_rekap WHERE student_id = ? LIMIT 1");
$stmtAbs->execute([$siswaId]);
$absRow = $stmtAbs->fetch(PDO::FETCH_ASSOC);

if ($absRow) {
    $hadir = (int)$absRow['hadir'];
    $sakit = (int)$absRow['sakit'];
    $izin  = (int)$absRow['izin'];
    $alfa  = (int)$absRow['alfa'];
} elseif ($raporRow && !empty($raporRow['kehadiran_json'])) {
    $k = json_decode($raporRow['kehadiran_json'], true);
    if (is_array($k)) {
        $hadir = (int)($k['hadir'] ?? 0);
        $sakit = (int)($k['sakit'] ?? 0);
        $izin  = (int)($k['izin'] ?? 0);
        $alfa  = (int)($k['alfa'] ?? 0);
    }
}

/**
 * 5B. EKSKUL: ambil dari ekskul_nilai (Pramuka & Kultum)
 */
$ekskulRows = [];

$stmtEks = $pdo->prepare("
    SELECT *
    FROM ekskul_nilai
    WHERE siswa_id = ?
      AND tahun_ajaran = ?
      AND semester = ?
    LIMIT 1
");
$stmtEks->execute([$siswaId, $tahunAjaran, $semester]);
$eksRow = $stmtEks->fetch(PDO::FETCH_ASSOC);

if ($eksRow) {
    if (!empty($eksRow['pramuka_predikat']) && $eksRow['pramuka_predikat'] !== '-') {
        $ekskulRows[] = [
            'nama'  => 'Pramuka',
            'nilai' => $eksRow['pramuka_predikat'],
            'desk'  => $eksRow['pramuka_deskripsi'] ?? ($eksRow['pramuka_catatan'] ?? ''),
        ];
    }
    if (!empty($eksRow['kultum_predikat']) && $eksRow['kultum_predikat'] !== '-') {
        $ekskulRows[] = [
            'nama'  => 'Kultum',
            'nilai' => $eksRow['kultum_predikat'],
            'desk'  => $eksRow['kultum_deskripsi'] ?? ($eksRow['kultum_catatan'] ?? ''),
        ];
    }
}

/**
 * 5C. PRESTASI: ambil dari tabel prestasi (student_id + tahun_ajaran + semester)
 */
$prestasiRows = [];
try {
    $stmtPrestasi = $pdo->prepare("
        SELECT jenis_kegiatan, keterangan
        FROM prestasi
        WHERE student_id = ?
          AND tahun_ajaran = ?
          AND semester = ?
        ORDER BY id ASC
        LIMIT 4
    ");
    $stmtPrestasi->execute([$siswaId, $tahunAjaran, $semester]);
    $prestasiRows = $stmtPrestasi->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $prestasiRows = [];
}

/**
 * 6. DATA SIKAP SPIRITUAL & SOSIAL
 */
$predSpiritual = '-';
$deskSpiritual = '';
$predSosial = '-';
$deskSosial = '';

$stmtSp = $pdo->prepare("
    SELECT predikat, deskripsi
    FROM sikap_spiritual
    WHERE siswa_id = ? AND tahun_ajaran = ? AND semester = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmtSp->execute([$siswaId, $tahunAjaran, $semester]);
if ($rowSp = $stmtSp->fetch(PDO::FETCH_ASSOC)) {
    $predSpiritual = $rowSp['predikat'] ?: '-';
    $deskSpiritual = $rowSp['deskripsi'] ?: '';
}

$stmtSo = $pdo->prepare("
    SELECT predikat, deskripsi
    FROM sikap_sosial
    WHERE siswa_id = ? AND tahun_ajaran = ? AND semester = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmtSo->execute([$siswaId, $tahunAjaran, $semester]);
if ($rowSo = $stmtSo->fetch(PDO::FETCH_ASSOC)) {
    $predSosial = $rowSo['predikat'] ?: '-';
    $deskSosial = $rowSo['deskripsi'] ?: '';
}

/**
 * 7. DATA NILAI MAPEL – rapor_mapel + mapel
 */
$stmtNilai = $pdo->prepare("
    SELECT rm.*, m.nama AS mapel_nama, m.kkm
    FROM rapor_mapel rm
    JOIN mapel m ON rm.mapel_id = m.id
    WHERE rm.siswa_id = ?
      AND rm.tahun_ajaran = ?
      AND rm.semester = ?
    ORDER BY m.nama ASC
");
$stmtNilai->execute([$siswaId, $tahunAjaran, $semester]);
$nilaiRows = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

/**
 * Kelompok mapel A/B/C
 */
$kelA_pen = []; $kelB_pen = []; $kelC_pen = [];
$kelA_ket = []; $kelB_ket = []; $kelC_ket = [];

function kelompok_mapel(string $nama): string {
    $n = strtolower($nama);
    $kelA = ['agama','pendidikan agama','ibadah','budi pekerti','ppkn','pancasila','bahasa indonesia','matematika','sejarah indonesia','bahasa inggris'];
    $kelB = ['seni budaya','penjas','pjok','prakarya','kewirausahaan','mulok','muatan lokal'];

    foreach ($kelA as $k) if (strpos($n, $k) !== false) return 'A';
    foreach ($kelB as $k) if (strpos($n, $k) !== false) return 'B';
    return 'C';
}

foreach ($nilaiRows as $r) {
    $kel = kelompok_mapel($r['mapel_nama']);

    $rowPen = [
        'mapel' => $r['mapel_nama'],
        'nilai' => $r['nilai_pengetahuan'],
        'pred'  => $r['predikat_pengetahuan'],
        'desk'  => $r['deskripsi_pengetahuan'],
    ];
    $rowKet = [
        'mapel' => $r['mapel_nama'],
        'nilai' => $r['nilai_keterampilan'],
        'pred'  => $r['predikat_keterampilan'],
        'desk'  => $r['deskripsi_keterampilan'],
    ];

    if ($kel === 'A') { $kelA_pen[]=$rowPen; $kelA_ket[]=$rowKet; }
    elseif ($kel === 'B') { $kelB_pen[]=$rowPen; $kelB_ket[]=$rowKet; }
    else { $kelC_pen[]=$rowPen; $kelC_ket[]=$rowKet; }
}

/**
 * 8. IDENTITAS SEKOLAH
 */
$namaSekolah   = 'SMA Al-Ishlah';
$alamatSekolah = 'Jl. H. Rais A. Rahman Gg. Lav';
$namaKepala    = 'Gusti Junianto, S.Pd., Gr';
$nipKepala     = 'JUKS.20023100813602419690';
$kotaSekolah   = 'Pontianak';
$tanggalCetak  = date('d F Y');

$kelasSiswa    = $siswa['kelas'] ?? '';
$namaSiswa     = $siswa['nama'] ?? '';
$nisSiswa      = $siswa['nis']  ?? '';
$nisnSiswa     = $siswa['nisn'] ?? '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Rapor - <?= htmlspecialchars($namaSiswa); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { background:#ccc; font-family:"Times New Roman", serif; margin:0; padding:10px; }
        .page {
            width:210mm; min-height:297mm;
            padding:15mm 20mm; margin:10px auto;
            background:#fff; box-shadow:0 0 5px rgba(0,0,0,.3);
            position:relative;
        }
        .page-break { page-break-after: always; }
        h1,h2,h3,h4,h5 { margin: 2px 0; }
        table { border-collapse: collapse; width:100%; font-size:11px; }
        th, td { border:1px solid #000; padding:4px; vertical-align: top; }
        .no-border td, .no-border th { border:none; }
        .text-center { text-align:center; }
        .mt-10 { margin-top:10px; }
        .mt-20 { margin-top:20px; }
        .mb-5 { margin-bottom:5px; }
        .underline { border-bottom:1px solid #000; padding-bottom:2px; }
        .box { border:1px solid #000; min-height:40mm; padding:5px; }
        .judul-section { font-weight:bold; margin-top:8px; }
        .small { font-size:10px; }

        @media print {
            body { background:#fff; padding:0; }
            .page { margin:0; box-shadow:none; page-break-after:always; }
            .page:last-child { page-break-after:auto; }
        }
    </style>
</head>
<body>

<?php
function header_rapor($namaSekolah, $alamatSekolah, $kelas, $semester, $tahunAjaran, $nama, $nis, $nisn) {
    ?>
    <table class="no-border" style="font-size:11px; width:100%; margin-bottom:6px;">
        <tr>
            <td style="width:120px;">Nama Sekolah</td><td style="width:5px;">:</td>
            <td><?= htmlspecialchars($namaSekolah); ?></td>
            <td style="width:70px;">Kelas</td><td style="width:5px;">:</td>
            <td><?= htmlspecialchars($kelas); ?></td>
        </tr>
        <tr>
            <td>Alamat</td><td>:</td>
            <td><?= htmlspecialchars($alamatSekolah); ?></td>
            <td>Semester</td><td>:</td>
            <td><?= htmlspecialchars($semester); ?></td>
        </tr>
        <tr>
            <td>Nama</td><td>:</td>
            <td><?= htmlspecialchars($nama); ?></td>
            <td>Tahun Pelajar</td><td>:</td>
            <td><?= htmlspecialchars($tahunAjaran); ?></td>
        </tr>
        <tr>
            <td>Nomor Induk/NISN</td><td>:</td>
            <td><?= htmlspecialchars($nis . ' / ' . $nisn); ?></td>
            <td></td><td></td><td></td>
        </tr>
    </table>
    <?php
}
?>

<!-- ========================= PAGE 1 : SIKAP ========================= -->
<div class="page page-break">
    <h3 class="text-center underline">CAPAIAN HASIL BELAJAR</h3>
    <?php header_rapor($namaSekolah, $alamatSekolah, $kelasSiswa, $semester, $tahunAjaran, $namaSiswa, $nisSiswa, $nisnSiswa); ?>

    <div class="judul-section">A. Sikap</div>

    <div class="mt-10">
        <strong>1. Sikap Spiritual</strong>
        <table class="mt-5">
            <tr><th style="width:80px;">Predikat</th><th>Deskripsi</th></tr>
            <tr>
                <td class="text-center"><?= htmlspecialchars($predSpiritual); ?></td>
                <td><?= nl2br(htmlspecialchars($deskSpiritual)); ?></td>
            </tr>
        </table>
    </div>

    <div class="mt-10">
        <strong>2. Sikap Sosial</strong>
        <table class="mt-5">
            <tr><th style="width:80px;">Predikat</th><th>Deskripsi</th></tr>
            <tr>
                <td class="text-center"><?= htmlspecialchars($predSosial); ?></td>
                <td><?= nl2br(htmlspecialchars($deskSosial)); ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- ========================= PAGE 2 : PENGETAHUAN ========================= -->
<div class="page page-break">
    <?php header_rapor($namaSekolah, $alamatSekolah, $kelasSiswa, $semester, $tahunAjaran, $namaSiswa, $nisSiswa, $nisnSiswa); ?>

    <div class="judul-section">B. Pengetahuan</div>
    <div class="small mb-5">Kriteria Ketuntasan Minimal : <?= !empty($nilaiRows) ? (int)($nilaiRows[0]['kkm'] ?? 70) : 70; ?></div>

    <strong>Kelompok Umum ( A )</strong>
    <table class="mt-5">
        <tr class="text-center">
            <th style="width:30px;">No</th><th>Mata Pelajaran</th><th style="width:60px;">Nilai</th><th style="width:50px;">Predik</th><th>Deskripsi</th>
        </tr>
        <?php if (empty($kelA_pen)): ?>
            <tr><td colspan="5" class="text-center small">Belum ada nilai.</td></tr>
        <?php else: ?>
            <?php $no=1; foreach ($kelA_pen as $r): ?>
            <tr>
                <td class="text-center"><?= $no++; ?></td>
                <td><?= htmlspecialchars($r['mapel']); ?></td>
                <td class="text-center"><?= $r['nilai'] !== null ? number_format($r['nilai'],0) : '-'; ?></td>
                <td class="text-center"><?= htmlspecialchars($r['pred'] ?: '-'); ?></td>
                <td><?= nl2br(htmlspecialchars($r['desk'])); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="mt-10">
        <strong>Kelompok Umum ( B )</strong>
        <table class="mt-5">
            <tr class="text-center">
                <th style="width:30px;">No</th><th>Mata Pelajaran</th><th style="width:60px;">Nilai</th><th style="width:50px;">Predik</th><th>Deskripsi</th>
            </tr>
            <?php if (empty($kelB_pen)): ?>
                <tr><td colspan="5" class="text-center small">Belum ada nilai.</td></tr>
            <?php else: ?>
                <?php $no=1; foreach ($kelB_pen as $r): ?>
                <tr>
                    <td class="text-center"><?= $no++; ?></td>
                    <td><?= htmlspecialchars($r['mapel']); ?></td>
                    <td class="text-center"><?= $r['nilai'] !== null ? number_format($r['nilai'],0) : '-'; ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['pred'] ?: '-'); ?></td>
                    <td><?= nl2br(htmlspecialchars($r['desk'])); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- ========================= PAGE 3 : PENGETAHUAN C + KETERAMPILAN A ========================= -->
<div class="page page-break">
    <?php header_rapor($namaSekolah, $alamatSekolah, $kelasSiswa, $semester, $tahunAjaran, $namaSiswa, $nisSiswa, $nisnSiswa); ?>

    <div class="judul-section">B. Pengetahuan (lanjutan)</div>
    <strong>Kelompok Peminatan ( C )</strong>
    <table class="mt-5">
        <tr class="text-center">
            <th style="width:30px;">No</th><th>Mata Pelajaran</th><th style="width:60px;">Nilai</th><th style="width:50px;">Predik</th><th>Deskripsi</th>
        </tr>
        <?php if (empty($kelC_pen)): ?>
            <tr><td colspan="5" class="text-center small">Belum ada nilai.</td></tr>
        <?php else: ?>
            <?php $no=1; foreach ($kelC_pen as $r): ?>
            <tr>
                <td class="text-center"><?= $no++; ?></td>
                <td><?= htmlspecialchars($r['mapel']); ?></td>
                <td class="text-center"><?= $r['nilai'] !== null ? number_format($r['nilai'],0) : '-'; ?></td>
                <td class="text-center"><?= htmlspecialchars($r['pred'] ?: '-'); ?></td>
                <td><?= nl2br(htmlspecialchars($r['desk'])); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="mt-10 judul-section">C. Keterampilan</div>
    <div class="small mb-5">Kriteria Ketuntasan Minimal : <?= !empty($nilaiRows) ? (int)($nilaiRows[0]['kkm'] ?? 70) : 70; ?></div>

    <strong>Kelompok Umum ( A )</strong>
    <table class="mt-5">
        <tr class="text-center">
            <th style="width:30px;">No</th><th>Mata Pelajaran</th><th style="width:60px;">Nilai</th><th style="width:50px;">Predik</th><th>Deskripsi</th>
        </tr>
        <?php if (empty($kelA_ket)): ?>
            <tr><td colspan="5" class="text-center small">Belum ada nilai.</td></tr>
        <?php else: ?>
            <?php $no=1; foreach ($kelA_ket as $r): ?>
            <tr>
                <td class="text-center"><?= $no++; ?></td>
                <td><?= htmlspecialchars($r['mapel']); ?></td>
                <td class="text-center"><?= $r['nilai'] !== null ? number_format($r['nilai'],0) : '-'; ?></td>
                <td class="text-center"><?= htmlspecialchars($r['pred'] ?: '-'); ?></td>
                <td><?= nl2br(htmlspecialchars($r['desk'])); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<!-- ========================= PAGE 4 : KETERAMPILAN LANJUTAN + EKSKUL/PRESTASI/ABSENSI/CATATAN ========================= -->
<div class="page page-break">
    <?php header_rapor($namaSekolah, $alamatSekolah, $kelasSiswa, $semester, $tahunAjaran, $namaSiswa, $nisSiswa, $nisnSiswa); ?>

    <div class="judul-section">C. Keterampilan (lanjutan)</div>

    <strong>Kelompok Umum ( B )</strong>
    <table class="mt-5">
        <tr class="text-center">
            <th style="width:30px;">No</th><th>Mata Pelajaran</th><th style="width:60px;">Nilai</th><th style="width:50px;">Predik</th><th>Deskripsi</th>
        </tr>
        <?php if (empty($kelB_ket)): ?>
            <tr><td colspan="5" class="text-center small">Belum ada nilai.</td></tr>
        <?php else: ?>
            <?php $no=1; foreach ($kelB_ket as $r): ?>
            <tr>
                <td class="text-center"><?= $no++; ?></td>
                <td><?= htmlspecialchars($r['mapel']); ?></td>
                <td class="text-center"><?= $r['nilai'] !== null ? number_format($r['nilai'],0) : '-'; ?></td>
                <td class="text-center"><?= htmlspecialchars($r['pred'] ?: '-'); ?></td>
                <td><?= nl2br(htmlspecialchars($r['desk'])); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="mt-10">
        <strong>Kelompok Peminatan ( C )</strong>
        <table class="mt-5">
            <tr class="text-center">
                <th style="width:30px;">No</th><th>Mata Pelajaran</th><th style="width:60px;">Nilai</th><th style="width:50px;">Predik</th><th>Deskripsi</th>
            </tr>
            <?php if (empty($kelC_ket)): ?>
                <tr><td colspan="5" class="text-center small">Belum ada nilai.</td></tr>
            <?php else: ?>
                <?php $no=1; foreach ($kelC_ket as $r): ?>
                <tr>
                    <td class="text-center"><?= $no++; ?></td>
                    <td><?= htmlspecialchars($r['mapel']); ?></td>
                    <td class="text-center"><?= $r['nilai'] !== null ? number_format($r['nilai'],0) : '-'; ?></td>
                    <td class="text-center"><?= htmlspecialchars($r['pred'] ?: '-'); ?></td>
                    <td><?= nl2br(htmlspecialchars($r['desk'])); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

    <!-- D. Ekstra Kurikuler (AUTO dari ekskul_nilai) -->
    <div class="mt-10 judul-section">D. Ekstra Kurikuler</div>
    <table class="mt-5">
        <tr class="text-center">
            <th style="width:30px;">No</th>
            <th style="width:150px;">Kegiatan Ekstra Kurikuler</th>
            <th style="width:60px;">Nilai</th>
            <th>Deskripsi</th>
        </tr>

        <?php
        $maxRow = 4;
        if (empty($ekskulRows)) {
            for ($i=1; $i<=$maxRow; $i++): ?>
                <tr>
                    <td class="text-center"><?= $i; ?></td>
                    <td>&nbsp;</td>
                    <td class="text-center">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            <?php endfor;
        } else {
            $no = 1;
            foreach ($ekskulRows as $er):
                if ($no > $maxRow) break; ?>
                <tr>
                    <td class="text-center"><?= $no++; ?></td>
                    <td><?= htmlspecialchars($er['nama']); ?></td>
                    <td class="text-center"><?= htmlspecialchars($er['nilai']); ?></td>
                    <td><?= nl2br(htmlspecialchars($er['desk'])); ?></td>
                </tr>
            <?php endforeach;

            while ($no <= $maxRow): ?>
                <tr>
                    <td class="text-center"><?= $no++; ?></td>
                    <td>&nbsp;</td>
                    <td class="text-center">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            <?php endwhile;
        }
        ?>
    </table>

    <!-- E. Prestasi (AUTO dari tabel prestasi) -->
    <div class="mt-10 judul-section">E. Prestasi</div>
    <table class="mt-5">
        <tr class="text-center">
            <th style="width:30px;">No</th>
            <th>Jenis Kegiatan</th>
            <th style="width:180px;">Keterangan</th>
        </tr>

        <?php
        $maxRowPrestasi = 4;
        if (empty($prestasiRows)) {
            for ($i=1; $i<=$maxRowPrestasi; $i++): ?>
                <tr>
                    <td class="text-center"><?= $i; ?></td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            <?php endfor;
        } else {
            $no = 1;
            foreach ($prestasiRows as $p):
                if ($no > $maxRowPrestasi) break; ?>
                <tr>
                    <td class="text-center"><?= $no++; ?></td>
                    <td><?= htmlspecialchars($p['jenis_kegiatan'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($p['keterangan'] ?? ''); ?></td>
                </tr>
            <?php endforeach;

            while ($no <= $maxRowPrestasi): ?>
                <tr>
                    <td class="text-center"><?= $no++; ?></td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            <?php endwhile;
        }
        ?>
    </table>

    <!-- F. Ketidakhadiran -->
    <div class="mt-10 judul-section">F. Ketidakhadiran</div>
    <table class="mt-5">
        <tr><td style="width:200px;">Sakit</td><td style="width:10px;">:</td><td><?= (int)$sakit; ?> hari</td></tr>
        <tr><td>Izin</td><td>:</td><td><?= (int)$izin; ?> hari</td></tr>
        <tr><td>Tanpa Keterangan</td><td>:</td><td><?= (int)$alfa; ?> hari</td></tr>
    </table>

    <!-- G. Catatan Wali Kelas -->
    <div class="mt-10 judul-section">G. Catatan Wali Kelas</div>
    <div class="box"><?= nl2br(htmlspecialchars($catatanWali)); ?></div>
</div>

<!-- ========================= PAGE 5 : TANGGAPAN ORANG TUA + TTD ========================= -->
<div class="page">
    <?php header_rapor($namaSekolah, $alamatSekolah, $kelasSiswa, $semester, $tahunAjaran, $namaSiswa, $nisSiswa, $nisnSiswa); ?>

    <div class="judul-section">H. Tanggapan Orang Tua/Wali</div>
    <div class="box" style="height:45mm;"></div>

    <table class="no-border mt-20" style="width:100%; font-size:11px;">
        <tr>
            <td class="text-left" style="width:40%;">
                Mengetahui,<br>Orang Tua/Wali,<br><br><br><br>
                ________________________
            </td>
            <td style="width:20%;"></td>
            <td class="text-left" style="width:40%;">
                <?= htmlspecialchars($kotaSekolah); ?>, <?= htmlspecialchars($tanggalCetak); ?><br>
                Wali Kelas,<br><br><br><br>
                <u><?= htmlspecialchars($namaWali); ?></u>
            </td>
        </tr>
    </table>

    <table class="no-border mt-20" style="width:100%; font-size:11px;">
        <tr>
            <td class="text-left">
                Mengetahui,<br>Kepala Sekolah,<br><br><br><br>
                <u><?= htmlspecialchars($namaKepala); ?></u><br>
                <span class="small"><?= htmlspecialchars($nipKepala); ?></span>
            </td>
        </tr>
    </table>
</div>

<?php if (($_GET['autoprint'] ?? '') === '1'): ?>
<script>
  window.addEventListener('load', () => window.print());
</script>
<?php endif; ?>

</body>
</html>
