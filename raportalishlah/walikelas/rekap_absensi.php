<?php
// walikelas/rekap_absensi.php
// REKAP ABSENSI RAPOR (FINAL & TERKUNCI TAHUN + SEMESTER)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

/* =====================================================
   CONFIG & AUTH
===================================================== */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/wali_auth.php';

requireWaliKelas();

/* =====================================================
   DATA WALI KELAS
===================================================== */
$waliGuru  = null;
$kelasWali = null;

if (isset($_SESSION['guru']) && is_array($_SESSION['guru'])) {
    $waliGuru  = $_SESSION['guru'];
    $kelasWali = $waliGuru['kelas_wali'] ?? null;
}

if (!$kelasWali) {
    if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'walikelas') {
        die("<p style='color:red;'>Session wali kelas tidak valid.</p>");
    }

    $user   = $_SESSION['user'];
    $guruId = $user['guru_id'] ?? null;

    $stmt = $guruId
        ? $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1")
        : $pdo->prepare("SELECT * FROM teachers WHERE username = ? LIMIT 1");

    $stmt->execute([$guruId ?: $user['username']]);
    $waliGuru = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$waliGuru) {
        die("<p style='color:red;'>Data wali kelas tidak ditemukan.</p>");
    }

    $_SESSION['guru'] = $waliGuru;
    $kelasWali        = $waliGuru['kelas_wali'] ?? null;
}

if (!$kelasWali) {
    die("<p style='color:red;'>Guru belum memiliki kelas wali.</p>");
}

/* =====================================================
   FILTER TAHUN AJARAN & SEMESTER
===================================================== */
$tahunAjaran = $_GET['tahun_ajaran'] ?? '2024/2025';
$semester    = $_GET['semester'] ?? 'Ganjil';

if (!in_array($semester, ['Ganjil','Genap'], true)) {
    $semester = 'Ganjil';
}

/* =====================================================
   SIMPAN REKAP ABSENSI
===================================================== */
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tahunAjaran = $_POST['tahun_ajaran'];
    $semester    = $_POST['semester'];
    $rekapPost   = $_POST['rekap'] ?? [];

    $stmtSave = $pdo->prepare("
        INSERT INTO attendance_rekap
        (student_id, tahun_ajaran, semester, hadir, sakit, izin, alfa)
        VALUES (:sid, :ta, :sem, :hadir, :sakit, :izin, :alfa)
        ON DUPLICATE KEY UPDATE
            hadir = VALUES(hadir),
            sakit = VALUES(sakit),
            izin  = VALUES(izin),
            alfa  = VALUES(alfa)
    ");

    foreach ($rekapPost as $sid => $row) {
        $stmtSave->execute([
            'sid'   => (int)$sid,
            'ta'    => $tahunAjaran,
            'sem'   => $semester,
            'hadir' => (int)($row['hadir'] ?? 0),
            'sakit' => (int)($row['sakit'] ?? 0),
            'izin'  => (int)($row['izin']  ?? 0),
            'alfa'  => (int)($row['alfa']  ?? 0),
        ]);
    }

    $msg = "Rekap absensi berhasil disimpan.";
}

/* =====================================================
   AMBIL DATA SISWA
===================================================== */
$stmt = $pdo->prepare("
    SELECT id, nama
    FROM siswa
    WHERE kelas = ?
    ORDER BY nama ASC
");
$stmt->execute([$kelasWali]);
$siswaList = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   AMBIL REKAP ABSENSI (ONLY attendance_rekap)
===================================================== */
$absensi = [];

if ($siswaList) {
    $ids = array_column($siswaList, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT *
        FROM attendance_rekap
        WHERE student_id IN ($in)
          AND tahun_ajaran = ?
          AND semester = ?
    ");
    $stmt->execute(array_merge($ids, [$tahunAjaran, $semester]));

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $absensi[$r['student_id']] = [
            'hadir' => (int)$r['hadir'],
            'sakit' => (int)$r['sakit'],
            'izin'  => (int)$r['izin'],
            'alfa'  => (int)$r['alfa'],
        ];
    }
}

/* default kosong */
foreach ($siswaList as $s) {
    if (!isset($absensi[$s['id']])) {
        $absensi[$s['id']] = ['hadir'=>0,'sakit'=>0,'izin'=>0,'alfa'=>0];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Rekap Absensi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-4">

<h4>Rekap Absensi Kelas <?= htmlspecialchars($kelasWali) ?></h4>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-3">
    <label class="form-label">Tahun Ajaran</label>
    <input name="tahun_ajaran" class="form-control" value="<?= htmlspecialchars($tahunAjaran) ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Semester</label>
    <select name="semester" class="form-select">
      <option value="Ganjil" <?= $semester==='Ganjil'?'selected':''; ?>>Ganjil</option>
      <option value="Genap"  <?= $semester==='Genap'?'selected':''; ?>>Genap</option>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-primary w-100">Tampilkan</button>
  </div>
</form>

<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran) ?>">
<input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">

<table class="table table-bordered">
<thead class="table-light">
<tr>
<th>No</th>
<th>Nama</th>
<th>Hadir</th>
<th>Sakit</th>
<th>Izin</th>
<th>Alfa</th>
</tr>
</thead>
<tbody>
<?php foreach ($siswaList as $i => $s): $r = $absensi[$s['id']]; ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($s['nama']) ?></td>
<td><input type="number" min="0" class="form-control" name="rekap[<?= $s['id'] ?>][hadir]" value="<?= $r['hadir'] ?>"></td>
<td><input type="number" min="0" class="form-control" name="rekap[<?= $s['id'] ?>][sakit]" value="<?= $r['sakit'] ?>"></td>
<td><input type="number" min="0" class="form-control" name="rekap[<?= $s['id'] ?>][izin]" value="<?= $r['izin'] ?>"></td>
<td><input type="number" min="0" class="form-control" name="rekap[<?= $s['id'] ?>][alfa]" value="<?= $r['alfa'] ?>"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<button class="btn btn-success">Simpan Rekap</button>
<a href="wali_dashboard.php" class="btn btn-secondary">Kembali</a>

</form>
</div>

</body>
</html>
