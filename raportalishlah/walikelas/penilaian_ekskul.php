<?php
// walikelas/penilaian_ekskul.php

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

// ====== AUTO DESKRIPSI ======
function ekskulAutoDesc(string $ekskul, string $predikat): string {
    $ekskul = trim($ekskul);
    switch ($predikat) {
        case 'A': return "Sangat Baik dan Aktif Setiap Kegiatan $ekskul";
        case 'B': return "Baik dan Aktif Setiap Kegiatan $ekskul";
        case 'C': return "Cukup, perlu meningkatkan keaktifan dalam kegiatan $ekskul";
        case 'D': return "Kurang, perlu pembinaan dan bimbingan dalam kegiatan $ekskul";
        default:  return "";
    }
}

// Default filter
$semester    = $_GET['semester'] ?? 'Ganjil';
$tahunAjaran = $_GET['tahun_ajaran'] ?? (date('Y') . '/' . (date('Y') + 1));
if (!in_array($semester, ['Ganjil','Genap'], true)) $semester = 'Ganjil';

// Ambil siswa sesuai kelas wali
$stmtSiswa = $pdo->prepare("SELECT id, nis, nama, kelas FROM siswa WHERE kelas = ? ORDER BY nama ASC");
$stmtSiswa->execute([$kelasWali]);
$siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

// Ambil nilai ekskul yang sudah ada
$nilaiMap = [];
if ($siswaList) {
    $ids = array_map(fn($r) => (int)$r['id'], $siswaList);
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT * FROM ekskul_nilai
            WHERE siswa_id IN ($ph)
              AND semester = ?
              AND tahun_ajaran = ?";
    $params = array_merge($ids, [$semester, $tahunAjaran]);

    $stmtN = $pdo->prepare($sql);
    $stmtN->execute($params);

    foreach ($stmtN->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nilaiMap[(int)$row['siswa_id']] = $row;
    }
}

$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semester    = $_POST['semester'] ?? $semester;
    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? $tahunAjaran);
    if (!in_array($semester, ['Ganjil','Genap'], true)) $semester = 'Ganjil';

    $student_ids = $_POST['siswa_id'] ?? [];

    $pramuka_predikat   = $_POST['pramuka_predikat'] ?? [];
    $pramuka_catatan    = $_POST['pramuka_catatan'] ?? [];
    $pramuka_deskripsi  = $_POST['pramuka_deskripsi'] ?? [];

    $kultum_predikat    = $_POST['kultum_predikat'] ?? [];
    $kultum_catatan     = $_POST['kultum_catatan'] ?? [];
    $kultum_deskripsi   = $_POST['kultum_deskripsi'] ?? [];

    $allowed = ['A','B','C','D','-'];

    try {
        $pdo->beginTransaction();

        $stmtUpsert = $pdo->prepare("
            INSERT INTO ekskul_nilai
              (siswa_id, kelas, tahun_ajaran, semester,
               pramuka_predikat, pramuka_catatan, pramuka_deskripsi,
               kultum_predikat, kultum_catatan, kultum_deskripsi,
               created_by_guru_id)
            VALUES
              (:sid, :kelas, :ta, :sem,
               :pp, :pc, :pd,
               :kp, :kc, :kd,
               :gid)
            ON DUPLICATE KEY UPDATE
              kelas = VALUES(kelas),
              pramuka_predikat = VALUES(pramuka_predikat),
              pramuka_catatan  = VALUES(pramuka_catatan),
              pramuka_deskripsi= VALUES(pramuka_deskripsi),
              kultum_predikat  = VALUES(kultum_predikat),
              kultum_catatan   = VALUES(kultum_catatan),
              kultum_deskripsi = VALUES(kultum_deskripsi),
              created_by_guru_id = VALUES(created_by_guru_id),
              updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($student_ids as $sid) {
            $sid = (int)$sid;
            if ($sid <= 0) continue;

            $pp = $pramuka_predikat[$sid] ?? '-';
            $pc = trim($pramuka_catatan[$sid] ?? '');
            $pd = trim($pramuka_deskripsi[$sid] ?? '');

            $kp = $kultum_predikat[$sid] ?? '-';
            $kc = trim($kultum_catatan[$sid] ?? '');
            $kd = trim($kultum_deskripsi[$sid] ?? '');

            if (!in_array($pp, $allowed, true)) $pp = '-';
            if (!in_array($kp, $allowed, true)) $kp = '-';

            // fallback: kalau deskripsi kosong, hitung otomatis
            if ($pd === '') $pd = ekskulAutoDesc('Pramuka', $pp);
            if ($kd === '') $kd = ekskulAutoDesc('Kultum',  $kp);

            $stmtUpsert->execute([
                ':sid'   => $sid,
                ':kelas' => $kelasWali,
                ':ta'    => $tahunAjaran,
                ':sem'   => $semester,

                ':pp'    => $pp,
                ':pc'    => ($pc !== '') ? $pc : null,
                ':pd'    => ($pd !== '') ? $pd : null,

                ':kp'    => $kp,
                ':kc'    => ($kc !== '') ? $kc : null,
                ':kd'    => ($kd !== '') ? $kd : null,

                ':gid'   => $guruId,
            ]);
        }

        $pdo->commit();
        header("Location: penilaian_ekskul.php?semester=" . urlencode($semester) . "&tahun_ajaran=" . urlencode($tahunAjaran) . "&saved=1");
        exit;

    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = "Gagal menyimpan: " . htmlspecialchars($e->getMessage());
    }
}

if (($_GET['saved'] ?? '') === '1') $success = "Nilai ekstrakurikuler berhasil disimpan.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Penilaian Ekstrakurikuler</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{--ishlah-green:#1b5e20;--ishlah-soft:#2e7d32;}
body{background:#f1f8f3;min-height:100vh;}
.navbar-islah{background:linear-gradient(90deg,var(--ishlah-green),var(--ishlah-soft));box-shadow:0 3px 12px rgba(0,0,0,.2);}
.card-soft{border-radius:14px;}
.predikat{width:90px;}
.catatan{min-width:240px;}
.descbox{min-width:320px;}
.small-desc{font-size:.82rem; line-height:1.15rem;}
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
      <h4 class="fw-bold mb-1">Penilaian Ekstrakurikuler</h4>
      <div class="text-muted">Pilih predikat A/B/C/D lalu deskripsi akan <b>terisi otomatis</b> (Pramuka & Kultum).</div>

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
                <th>Nama</th>

                <th class="text-center" colspan="3">Pramuka</th>
                <th class="text-center" colspan="3">Kultum</th>
              </tr>
              <tr class="table-light">
                <th></th><th></th><th></th>
                <th class="predikat">Predikat</th>
                <th class="descbox">Deskripsi (Auto)</th>
                <th class="catatan">Catatan (Opsional)</th>
                <th class="predikat">Predikat</th>
                <th class="descbox">Deskripsi (Auto)</th>
                <th class="catatan">Catatan (Opsional)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($siswaList as $i => $s):
                $sid = (int)$s['id'];
                $old = $nilaiMap[$sid] ?? [];

                $pp = $old['pramuka_predikat'] ?? '-';
                $pc = $old['pramuka_catatan'] ?? '';
                $pd = $old['pramuka_deskripsi'] ?? ekskulAutoDesc('Pramuka', $pp);

                $kp = $old['kultum_predikat'] ?? '-';
                $kc = $old['kultum_catatan'] ?? '';
                $kd = $old['kultum_deskripsi'] ?? ekskulAutoDesc('Kultum', $kp);
              ?>
              <tr>
                <td class="text-center"><?= $i+1 ?></td>
                <td><?= htmlspecialchars($s['nis'] ?? '-') ?></td>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($s['nama']) ?></div>
                  <div class="text-muted small">Kelas: <?= htmlspecialchars($s['kelas'] ?? $kelasWali) ?></div>
                  <input type="hidden" name="siswa_id[]" value="<?= $sid ?>">
                </td>

                <!-- PRAMUKA -->
                <td>
                  <select class="form-select form-select-sm"
                          name="pramuka_predikat[<?= $sid ?>]"
                          data-sid="<?= $sid ?>" data-ekskul="Pramuka"
                          onchange="autoDesc(this)">
                    <?php foreach (['A','B','C','D','-'] as $opt): ?>
                      <option value="<?= $opt ?>" <?= $pp===$opt?'selected':''; ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>

                <td>
                  <input type="hidden" name="pramuka_deskripsi[<?= $sid ?>]" id="pramuka_desc_<?= $sid ?>"
                         value="<?= htmlspecialchars($pd) ?>">
                  <small class="text-muted small-desc d-block" id="pramuka_desc_text_<?= $sid ?>">
                    <?= htmlspecialchars($pd) ?>
                  </small>
                </td>

                <td>
                  <input class="form-control form-control-sm"
                         name="pramuka_catatan[<?= $sid ?>]"
                         value="<?= htmlspecialchars($pc) ?>"
                         placeholder="Opsional">
                </td>

                <!-- KULTUM -->
                <td>
                  <select class="form-select form-select-sm"
                          name="kultum_predikat[<?= $sid ?>]"
                          data-sid="<?= $sid ?>" data-ekskul="Kultum"
                          onchange="autoDesc(this)">
                    <?php foreach (['A','B','C','D','-'] as $opt): ?>
                      <option value="<?= $opt ?>" <?= $kp===$opt?'selected':''; ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>

                <td>
                  <input type="hidden" name="kultum_deskripsi[<?= $sid ?>]" id="kultum_desc_<?= $sid ?>"
                         value="<?= htmlspecialchars($kd) ?>">
                  <small class="text-muted small-desc d-block" id="kultum_desc_text_<?= $sid ?>">
                    <?= htmlspecialchars($kd) ?>
                  </small>
                </td>

                <td>
                  <input class="form-control form-control-sm"
                         name="kultum_catatan[<?= $sid ?>]"
                         value="<?= htmlspecialchars($kc) ?>"
                         placeholder="Opsional">
                </td>
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
            <i class="bi bi-save2"></i> Simpan Nilai
          </button>
        </div>
      </form>

      <?php endif; ?>

    </div>
  </div>

</div>

<script>
function descMap(ekskul, pred) {
  if (pred === 'A') return `Sangat Baik dan Aktif Setiap Kegiatan ${ekskul}`;
  if (pred === 'B') return `Baik dan Aktif Setiap Kegiatan ${ekskul}`;
  if (pred === 'C') return `Cukup, perlu meningkatkan keaktifan dalam kegiatan ${ekskul}`;
  if (pred === 'D') return `Kurang, perlu pembinaan dan bimbingan dalam kegiatan ${ekskul}`;
  return '';
}

function autoDesc(sel){
  const sid = sel.dataset.sid;
  const ekskul = sel.dataset.ekskul;
  const pred = sel.value;
  const d = descMap(ekskul, pred);

  if (ekskul === 'Pramuka') {
    document.getElementById(`pramuka_desc_${sid}`).value = d;
    document.getElementById(`pramuka_desc_text_${sid}`).textContent = d;
  } else {
    document.getElementById(`kultum_desc_${sid}`).value = d;
    document.getElementById(`kultum_desc_text_${sid}`).textContent = d;
  }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
