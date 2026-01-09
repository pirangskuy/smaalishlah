<?php
// walikelas/catatan_wali.php
// Input Catatan Wali Kelas -> tersimpan ke tabel rapor.catatan_wali
// Terhubung otomatis ke cetak_rapor.php karena cetak_rapor membaca rapor.catatan_wali

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireWaliKelas();

// Ambil guru_id dari session user
$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    echo "<p style='color:red;'>Akun wali kelas belum terhubung ke data guru. Hubungi admin.</p>";
    exit;
}

// Ambil data wali kelas
$stmt = $pdo->prepare("SELECT id, name, kelas_wali FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$guruId]);
$wali = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wali) {
    echo "<p style='color:red;'>Data wali kelas tidak ditemukan. Hubungi admin.</p>";
    exit;
}

$namaWali  = $wali['name'];
$kelasWali = $wali['kelas_wali'];

if (!$kelasWali) {
    echo "<p style='color:red;'>Guru ini belum memiliki kelas wali. Isi kolom <code>kelas_wali</code> di tabel <code>teachers</code>.</p>";
    exit;
}

/**
 * OPSI TAHUN AJARAN + SEMESTER (yang benar-benar ada di DB untuk kelas ini)
 * Sama konsepnya dengan cetak_rapor.php biar sinkron.
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

// default kalau opsi kosong
$defaultTA  = $opts[0]['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1));
$defaultSem = $opts[0]['semester'] ?? 'Ganjil';

$tahunAjaran = $_GET['tahun_ajaran'] ?? $defaultTA;
$semester    = $_GET['semester'] ?? $defaultSem;
$siswaId     = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;

if (!in_array($semester, ['Ganjil','Genap'], true)) $semester = $defaultSem;

// Flash message sederhana
$flash = $_SESSION['flash_catatan'] ?? null;
unset($_SESSION['flash_catatan']);

/**
 * SIMPAN (POST)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postSiswaId  = (int)($_POST['siswa_id'] ?? 0);
    $postTA       = trim((string)($_POST['tahun_ajaran'] ?? $tahunAjaran));
    $postSem      = trim((string)($_POST['semester'] ?? $semester));
    $catatanWali  = trim((string)($_POST['catatan_wali'] ?? ''));

    if ($postSiswaId <= 0) {
        $_SESSION['flash_catatan'] = ['type'=>'danger', 'msg'=>'Siswa tidak valid.'];
        header("Location: catatan_wali.php?tahun_ajaran=" . urlencode($postTA) . "&semester=" . urlencode($postSem));
        exit;
    }
    if (!in_array($postSem, ['Ganjil','Genap'], true)) $postSem = $defaultSem;

    // Pastikan siswa milik kelas wali
    $stmtCheck = $pdo->prepare("SELECT id, nama, kelas FROM siswa WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$postSiswaId]);
    $siswaCek = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$siswaCek || (string)$siswaCek['kelas'] !== (string)$kelasWali) {
        $_SESSION['flash_catatan'] = ['type'=>'danger', 'msg'=>'Siswa ini bukan dari kelas yang Anda wali.'];
        header("Location: catatan_wali.php?tahun_ajaran=" . urlencode($postTA) . "&semester=" . urlencode($postSem));
        exit;
    }

    // Cek apakah baris rapor sudah ada
    $stmtExist = $pdo->prepare("
        SELECT id FROM rapor
        WHERE siswa_id = ? AND tahun_ajaran = ? AND semester = ?
        LIMIT 1
    ");
    $stmtExist->execute([$postSiswaId, $postTA, $postSem]);
    $exist = $stmtExist->fetch(PDO::FETCH_ASSOC);

    try {
        if ($exist) {
            // UPDATE
            $stmtUp = $pdo->prepare("
                UPDATE rapor
                SET catatan_wali = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$catatanWali, (int)$exist['id']]);
        } else {
            // INSERT minimal (asumsi kolom lainnya nullable)
            $stmtIn = $pdo->prepare("
                INSERT INTO rapor (siswa_id, tahun_ajaran, semester, catatan_wali)
                VALUES (?, ?, ?, ?)
            ");
            $stmtIn->execute([$postSiswaId, $postTA, $postSem, $catatanWali]);
        }

        $_SESSION['flash_catatan'] = ['type'=>'success', 'msg'=>'Catatan wali berhasil disimpan.'];
    } catch (Throwable $e) {
        $_SESSION['flash_catatan'] = ['type'=>'danger', 'msg'=>'Gagal menyimpan: ' . $e->getMessage()];
    }

    header("Location: catatan_wali.php?siswa_id=" . $postSiswaId .
           "&tahun_ajaran=" . urlencode($postTA) .
           "&semester=" . urlencode($postSem));
    exit;
}

/**
 * MODE 1: LIST SISWA (belum pilih siswa)
 */
if ($siswaId <= 0) {
    // Ambil siswa + catatan (LEFT JOIN rapor)
    $stmtList = $pdo->prepare("
        SELECT s.id, s.nama, s.nis, s.nisn, r.catatan_wali
        FROM siswa s
        LEFT JOIN rapor r
          ON r.siswa_id = s.id
         AND r.tahun_ajaran = :ta
         AND r.semester = :sem
        WHERE s.kelas = :kelas
        ORDER BY s.nama ASC
    ");
    $stmtList->execute([
        ':ta'    => $tahunAjaran,
        ':sem'   => $semester,
        ':kelas' => $kelasWali
    ]);
    $siswaList = $stmtList->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Catatan Wali Kelas</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            :root { --ishlah-green:#1b5e20; --ishlah-soft:#2e7d32; }
            body { background:#f1f8f3; }
            .navbar-islah { background: linear-gradient(90deg, var(--ishlah-green), var(--ishlah-soft)); }
            .badge-soft { background:#e8f5e9; color:#1b5e20; }
            .truncate { max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        </style>
    </head>
    <body>

    <nav class="navbar navbar-expand-lg navbar-islah mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-white" href="wali_dashboard.php">
                <i class="bi bi-journal-text me-1"></i> Catatan Wali Kelas
            </a>
            <div class="ms-auto text-white small">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($namaWali); ?> — Kelas <?= htmlspecialchars($kelasWali); ?>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h5 class="fw-bold mb-1">Pilih Tahun Ajaran & Semester</h5>
                <p class="text-muted small mb-3">Agar catatan tersimpan sesuai periode dan otomatis tampil di Cetak Rapor.</p>

                <form method="get" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Tahun Ajaran (Tersimpan)</label>
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
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Kelas</label>
                        <input class="form-control" value="<?= htmlspecialchars($kelasWali); ?>" disabled>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-success w-100" type="submit">
                            <i class="bi bi-check2-circle me-1"></i> Terapkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0">Daftar Siswa</h5>
                    <span class="badge badge-soft"><?= htmlspecialchars($tahunAjaran) ?> • <?= htmlspecialchars($semester) ?></span>
                </div>

                <?php if (empty($siswaList)): ?>
                    <div class="alert alert-warning">Belum ada siswa di kelas ini.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;">No</th>
                                    <th style="width:120px;">NIS</th>
                                    <th style="width:120px;">NISN</th>
                                    <th>Nama</th>
                                    <th>Catatan</th>
                                    <th style="width:180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $no=1; foreach ($siswaList as $s): ?>
                                <?php
                                  $has = !empty(trim((string)($s['catatan_wali'] ?? '')));
                                  $snippet = $has ? trim((string)$s['catatan_wali']) : '';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($s['nis'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($s['nisn'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($s['nama'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($has): ?>
                                            <span class="badge text-bg-success">Ada</span>
                                            <span class="ms-2 truncate"><?= htmlspecialchars($snippet); ?></span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Kosong</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a class="btn btn-sm btn-primary"
                                           href="catatan_wali.php?siswa_id=<?= (int)$s['id']; ?>&tahun_ajaran=<?= urlencode($tahunAjaran); ?>&semester=<?= urlencode($semester); ?>">
                                            <i class="bi bi-pencil-square"></i> Isi/Edit
                                        </a>
                                        <a class="btn btn-sm btn-outline-dark"
                                           target="_blank"
                                           href="cetak_rapor.php?siswa_id=<?= (int)$s['id']; ?>&tahun_ajaran=<?= urlencode($tahunAjaran); ?>&semester=<?= urlencode($semester); ?>">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <a href="wali_dashboard.php" class="btn btn-secondary mt-3">
                    <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * MODE 2: FORM EDIT CATATAN SISWA
 */
$stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE id = ? LIMIT 1");
$stmtSiswa->execute([$siswaId]);
$siswa = $stmtSiswa->fetch(PDO::FETCH_ASSOC);

if (!$siswa) die("<p style='color:red;'>Data siswa tidak ditemukan.</p>");
if ((string)$siswa['kelas'] !== (string)$kelasWali) die("<p style='color:red;'>Siswa ini bukan dari kelas yang Anda wali.</p>");

// Ambil catatan dari tabel rapor
$stmtR = $pdo->prepare("
    SELECT id, catatan_wali
    FROM rapor
    WHERE siswa_id = ? AND tahun_ajaran = ? AND semester = ?
    LIMIT 1
");
$stmtR->execute([$siswaId, $tahunAjaran, $semester]);
$raporRow = $stmtR->fetch(PDO::FETCH_ASSOC);
$catatan = $raporRow['catatan_wali'] ?? '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Isi Catatan Wali - <?= htmlspecialchars($siswa['nama'] ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --ishlah-green:#1b5e20; --ishlah-soft:#2e7d32; }
        body { background:#f1f8f3; }
        .navbar-islah { background: linear-gradient(90deg, var(--ishlah-green), var(--ishlah-soft)); }
        textarea { min-height: 220px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-islah mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="catatan_wali.php?tahun_ajaran=<?= urlencode($tahunAjaran) ?>&semester=<?= urlencode($semester) ?>">
            <i class="bi bi-arrow-left-circle me-1"></i> Kembali
        </a>
        <div class="ms-auto text-white small">
            <i class="bi bi-person-circle me-1"></i>
            <?= htmlspecialchars($namaWali); ?> — Kelas <?= htmlspecialchars($kelasWali); ?>
        </div>
    </div>
</nav>

<div class="container mb-5">

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold mb-1">Catatan Wali Kelas</h5>
                    <div class="text-muted small">
                        Siswa: <b><?= htmlspecialchars($siswa['nama'] ?? '') ?></b>
                        • NIS: <?= htmlspecialchars($siswa['nis'] ?? '') ?>
                        • Kelas: <?= htmlspecialchars($siswa['kelas'] ?? '') ?>
                    </div>
                    <div class="mt-2">
                        <span class="badge text-bg-success"><?= htmlspecialchars($tahunAjaran) ?></span>
                        <span class="badge text-bg-warning text-dark"><?= htmlspecialchars($semester) ?></span>
                    </div>
                </div>

                <div class="mt-3 mt-md-0">
                    <a class="btn btn-outline-dark"
                       target="_blank"
                       href="cetak_rapor.php?siswa_id=<?= (int)$siswaId; ?>&tahun_ajaran=<?= urlencode($tahunAjaran); ?>&semester=<?= urlencode($semester); ?>">
                        <i class="bi bi-printer"></i> Lihat di Cetak Rapor
                    </a>
                </div>
            </div>

            <hr>

            <form method="post">
                <input type="hidden" name="siswa_id" value="<?= (int)$siswaId ?>">
                <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran) ?>">
                <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">

                <label class="form-label fw-bold">Isi Catatan</label>
                <textarea name="catatan_wali" class="form-control" placeholder="Tulis catatan wali kelas..."><?= htmlspecialchars($catatan) ?></textarea>

                <div class="form-text mt-2">
                    Tips: tulis ringkas, jelas, mencerminkan sikap, kedisiplinan, dan saran perbaikan siswa.
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save2"></i> Simpan
                    </button>

                    <a class="btn btn-secondary"
                       href="catatan_wali.php?tahun_ajaran=<?= urlencode($tahunAjaran) ?>&semester=<?= urlencode($semester) ?>">
                        Batal
                    </a>
                </div>
            </form>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
