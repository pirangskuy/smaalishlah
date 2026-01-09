<?php
// walikelas/prestasi.php
// Input prestasi per siswa (maks 4 baris) + filter tahun/semester
// Kompatibel dengan tabel prestasi versi kamu (student_id, tanpa no_urut & tanpa kelas)

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireWaliKelas();

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

// Filter
$semester    = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1));
if (!in_array($semester, ['Ganjil','Genap'], true)) $semester = 'Ganjil';

$success = $error = null;

/**
 * Ambil siswa kelas wali
 */
$stmtSiswa = $pdo->prepare("SELECT id, nis, nama, kelas FROM siswa WHERE kelas = ? ORDER BY nama ASC");
$stmtSiswa->execute([$kelasWali]);
$siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

/**
 * Ambil prestasi existing (maks 4) per siswa untuk tahun/semester ini
 * Struktur tabel prestasi kamu:
 * - student_id
 * - tahun_ajaran
 * - semester
 * - jenis_kegiatan
 * - keterangan
 */
$prestasiMap = []; // [student_id] => array of rows (max 4)
if (!empty($siswaList)) {
    $ids = array_map(fn($r) => (int)$r['id'], $siswaList);
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    $sql = "
        SELECT *
        FROM prestasi
        WHERE student_id IN ($ph)
          AND tahun_ajaran = ?
          AND semester = ?
        ORDER BY student_id ASC, id ASC
    ";
    $params = array_merge($ids, [$tahunAjaran, $semester]);

    $stmtP = $pdo->prepare($sql);
    $stmtP->execute($params);
    $rows = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $sid = (int)$r['student_id'];
        if (!isset($prestasiMap[$sid])) $prestasiMap[$sid] = [];
        // simpan max 4 saja
        if (count($prestasiMap[$sid]) < 4) {
            $prestasiMap[$sid][] = $r;
        }
    }
}

/**
 * SIMPAN
 * Karena tabel tidak punya no_urut:
 * - kita lakukan: hapus dulu prestasi siswa untuk tahun/semester tsb
 * - lalu insert ulang 0..4 baris sesuai input yang diisi
 * Ini cara paling stabil dan simpel.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semester    = $_POST['semester'] ?? $semester;
    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? $tahunAjaran);
    if (!in_array($semester, ['Ganjil','Genap'], true)) $semester = 'Ganjil';

    $student_ids = $_POST['student_id'] ?? []; // hidden per siswa

    // prestasi[SID][1..4][jenis|ket]
    $prestasiPost = $_POST['prestasi'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmtDelete = $pdo->prepare("
            DELETE FROM prestasi
            WHERE student_id = ?
              AND tahun_ajaran = ?
              AND semester = ?
        ");

        $stmtInsert = $pdo->prepare("
            INSERT INTO prestasi
              (student_id, tahun_ajaran, semester, jenis_kegiatan, keterangan, created_by_guru_id)
            VALUES
              (:sid, :ta, :sem, :jenis, :ket, :gid)
        ");

        foreach ($student_ids as $sidRaw) {
            $sid = (int)$sidRaw;
            if ($sid <= 0) continue;

            // hapus dulu yang lama untuk siswa ini di tahun/semester ini
            $stmtDelete->execute([$sid, $tahunAjaran, $semester]);

            // insert ulang max 4 baris yang diisi
            $slots = $prestasiPost[$sid] ?? [];
            $countInserted = 0;

            for ($i = 1; $i <= 4; $i++) {
                $jenis = trim($slots[$i]['jenis'] ?? '');
                $ket   = trim($slots[$i]['ket'] ?? '');

                // skip kalau dua-duanya kosong
                if ($jenis === '' && $ket === '') continue;

                // batasi 4 insert
                if ($countInserted >= 4) break;

                $stmtInsert->execute([
                    ':sid'   => $sid,
                    ':ta'    => $tahunAjaran,
                    ':sem'   => $semester,
                    ':jenis' => ($jenis !== '') ? $jenis : null,
                    ':ket'   => ($ket !== '') ? $ket : null,
                    ':gid'   => $guruId,
                ]);

                $countInserted++;
            }
        }

        $pdo->commit();
        header("Location: prestasi.php?semester=" . urlencode($semester) . "&tahun_ajaran=" . urlencode($tahunAjaran) . "&saved=1");
        exit;

    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = "Gagal menyimpan: " . htmlspecialchars($e->getMessage());
    }
}

if (($_GET['saved'] ?? '') === '1') $success = "Prestasi berhasil disimpan.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Prestasi Siswa</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{--ishlah-green:#1b5e20;--ishlah-soft:#2e7d32;}
body{background:#f1f8f3;min-height:100vh;}
.navbar-islah{background:linear-gradient(90deg,var(--ishlah-green),var(--ishlah-soft));box-shadow:0 3px 12px rgba(0,0,0,.2);}
.card-soft{border-radius:14px;}
.small-note{font-size:.85rem;color:#555;}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-islah mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold text-white" href="wali_dashboard.php">
      <i class="bi bi-arrow-left-circle me-1"></i> Kembali
    </a>
    <div class="ms-auto text-white d-flex align-items-center">
      <i class="bi bi-person-circle me-2"></i> <?= htmlspecialchars($namaWali) ?> — Kelas <?= htmlspecialchars($kelasWali) ?>
    </div>
  </div>
</nav>

<div class="container">

  <div class="card card-soft shadow-sm border-0 mb-3">
    <div class="card-body">
      <h4 class="fw-bold mb-1">Prestasi Siswa</h4>
      <div class="text-muted">Input prestasi maksimal <b>4 baris</b> per siswa untuk tahun ajaran & semester terpilih.</div>

      <form class="row g-2 mt-3" method="get">
        <div class="col-md-3">
          <label class="form-label small">Semester</label>
          <select name="semester" class="form-select">
            <option value="Ganjil" <?= $semester==='Ganjil'?'selected':''; ?>>Ganjil</option>
            <option value="Genap"  <?= $semester==='Genap'?'selected':''; ?>>Genap</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Tahun Ajaran</label>
          <input name="tahun_ajaran" class="form-control" value="<?= htmlspecialchars($tahunAjaran) ?>" placeholder="2025/2026">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-success w-100"><i class="bi bi-funnel"></i> Tampilkan</button>
        </div>
      </form>

      <div class="small-note mt-2">
        Catatan: Karena tabel prestasi kamu belum punya kolom <code>no_urut</code>, sistem akan menyimpan ulang maksimal 4 baris sesuai input (yang kosong tidak disimpan).
      </div>
    </div>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

  <div class="card card-soft shadow-sm border-0">
    <div class="card-body">

      <?php if (!$siswaList): ?>
        <div class="alert alert-warning mb-0">
          Tidak ada siswa untuk kelas <b><?= htmlspecialchars($kelasWali) ?></b>.
        </div>
      <?php else: ?>

      <form method="post">
        <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">
        <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran) ?>">

        <div class="table-responsive">
          <table class="table table-bordered align-middle bg-white">
            <thead class="table-light">
              <tr>
                <th style="width:60px;">No</th>
                <th style="width:120px;">NIS</th>
                <th style="min-width:200px;">Nama</th>
                <th style="min-width:320px;">Prestasi 1</th>
                <th style="min-width:320px;">Prestasi 2</th>
                <th style="min-width:320px;">Prestasi 3</th>
                <th style="min-width:320px;">Prestasi 4</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($siswaList as $i => $s):
                $sid = (int)$s['id'];
                $rows = $prestasiMap[$sid] ?? [];

                // siapkan 4 slot
                $slot = [];
                for ($k=1; $k<=4; $k++) {
                    $slot[$k] = ['jenis'=>'', 'ket'=>''];
                }
                // isi dari DB
                $idx=1;
                foreach ($rows as $r) {
                    if ($idx > 4) break;
                    $slot[$idx]['jenis'] = $r['jenis_kegiatan'] ?? '';
                    $slot[$idx]['ket']   = $r['keterangan'] ?? '';
                    $idx++;
                }
              ?>
              <tr>
                <td class="text-center"><?= $i+1 ?></td>
                <td><?= htmlspecialchars($s['nis'] ?? '-') ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($s['nama']) ?></div>
                  <div class="text-muted small">Kelas: <?= htmlspecialchars($s['kelas'] ?? $kelasWali) ?></div>
                  <input type="hidden" name="student_id[]" value="<?= $sid ?>">
                </td>

                <?php for ($k=1; $k<=4; $k++): ?>
                <td>
                  <input class="form-control form-control-sm mb-2"
                         name="prestasi[<?= $sid ?>][<?= $k ?>][jenis]"
                         value="<?= htmlspecialchars($slot[$k]['jenis']) ?>"
                         placeholder="Jenis kegiatan (cth: Lomba Basket)">
                  <input class="form-control form-control-sm"
                         name="prestasi[<?= $sid ?>][<?= $k ?>][ket]"
                         value="<?= htmlspecialchars($slot[$k]['ket']) ?>"
                         placeholder="Keterangan (cth: Juara 1 Tingkat Kota)">
                </td>
                <?php endfor; ?>

              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex gap-2 justify-content-end">
          <a href="wali_dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-circle"></i> Batal
          </a>
          <button class="btn btn-success">
            <i class="bi bi-save2"></i> Simpan Prestasi
          </button>
        </div>
      </form>

      <?php endif; ?>

    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
