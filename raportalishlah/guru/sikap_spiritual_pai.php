<?php
// guru/sikap_spiritual_pai.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

// ===== Helper fungsi sikap =====
function mode_value(array $values): ?int {
    $filtered = [];
    foreach ($values as $v) {
        if ($v === null || $v === '') continue;
        $n = (int)$v;
        if ($n <= 0) continue;
        $filtered[] = $n;
    }
    if (empty($filtered)) return null;

    $counts = [];
    foreach ($filtered as $n) {
        if (!isset($counts[$n])) $counts[$n] = 0;
        $counts[$n]++;
    }
    arsort($counts); // sort by frekuensi desc
    $maxFreq    = reset($counts);
    $candidates = array_keys(array_filter($counts, fn($f) => $f === $maxFreq));
    sort($candidates);
    return $candidates[0];
}

function predikat_sikap(?int $nilai): ?string {
    if ($nilai === null) return null;
    if ($nilai <= 1) return 'D';
    if ($nilai <= 2) return 'C';
    if ($nilai <= 3) return 'B';
    return 'A';
}

function kategori_sikap(?int $nilai): ?string {
    if ($nilai === null) return null;
    if ($nilai >= 4) return 'sudah berkembang';
    if ($nilai >= 3) return 'mulai berkembang';
    return 'perlu bimbingan';
}

function hitung_sikap_spiritual(array $n): array {
    $list = [
        $n['taat_beribadah'] ?? null,
        $n['rasa_syukur']    ?? null,
        $n['berdoa']         ?? null,
        $n['toleransi']      ?? null,
        $n['peduli_sesama']  ?? null,
        $n['tawakal']        ?? null,
        $n['memberi_salam']  ?? null,
    ];

    $dominan  = mode_value($list);
    $pred     = predikat_sikap($dominan);
    $kategori = kategori_sikap($dominan);

    $deskripsi = null;
    if ($kategori !== null) {
        $deskripsi = "Sikap peserta didik dalam taat beribadah, rasa syukur, "
                   . "berdoa, toleransi, peduli sesama, berserah diri (tawakal), "
                   . "dan memberi salam {$kategori}.";
    }

    return [
        'nilai_dominan' => $dominan,
        'predikat'      => $pred,
        'kategori'      => $kategori,
        'deskripsi'     => $deskripsi,
    ];
}

// ===== Ambil data guru & cek dia PAI =====
$guruId = $_SESSION['user']['guru_id'] ?? null;

if (!$guruId) {
    echo "<p style='color:red;'>Akun guru belum terhubung ke data guru (guru_id kosong di tabel users).</p>";
    exit;
}

$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    echo "<p style='color:red;'>Data guru tidak ditemukan.</p>";
    exit;
}

$mapelGuru = strtolower(trim($guru['mapel'] ?? ''));
$isPAI = (strpos($mapelGuru, 'pai') !== false)
      || (strpos($mapelGuru, 'ibadah syariah') !== false)
      || (strpos($mapelGuru, 'agama') !== false);

if (!$isPAI) {
    echo "<p style='color:red;'>Halaman ini khusus guru PAI. Mapel Anda: "
       . htmlspecialchars($guru['mapel'] ?? '-') . ".</p>";
    exit;
}

// Cari mapel PAI di tabel mapel untuk guru ini
$stmtMapel = $pdo->prepare("
    SELECT id, nama, kkm 
    FROM mapel 
    WHERE teacher_id = ? 
      AND (
        LOWER(nama) LIKE '%pai%' 
        OR LOWER(nama) LIKE '%ibadah syariah%' 
        OR LOWER(nama) LIKE '%agama%'
      )
    ORDER BY nama
");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

if (!$mapelList) {
    echo "<p style='color:red;'>Mapel PAI untuk guru ini belum diatur di tabel mapel. Hubungi admin.</p>";
    exit;
}

// Daftar kelas tetap: 10, 11, 12
$kelasList = ['10', '11', '12'];

// Ambil filter
$selectedMapelId = isset($_REQUEST['mapel_id']) ? (int)$_REQUEST['mapel_id'] : (int)$mapelList[0]['id'];
$selectedKelas   = $_REQUEST['kelas'] ?? '';
$tahunAjaran     = $_REQUEST['tahun_ajaran'] ?? '2025/2026';
$semester        = $_REQUEST['semester'] ?? 'Genap';

$successMessage = '';

// ===== PROSES SIMPAN (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedKelas !== '') {

    // Ambil ulang daftar siswa di kelas ini untuk loop simpan
    $stmtSiswaPost = $pdo->prepare("SELECT id FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswaPost->execute([$selectedKelas]);
    $siswaListPost = $stmtSiswaPost->fetchAll(PDO::FETCH_ASSOC);

    // Ambil semua array nilai dari POST
    $postTaat    = $_POST['taat_beribadah'] ?? [];
    $postSyukur  = $_POST['rasa_syukur'] ?? [];
    $postBerdoa  = $_POST['berdoa'] ?? [];
    $postToler   = $_POST['toleransi'] ?? [];
    $postPeduli  = $_POST['peduli_sesama'] ?? [];
    $postTawakal = $_POST['tawakal'] ?? [];
    $postSalam   = $_POST['memberi_salam'] ?? [];

    try {
        $pdo->beginTransaction();

        $upsert = $pdo->prepare("
            INSERT INTO sikap_spiritual
              (siswa_id,mapel_id,tahun_ajaran,semester,
               taat_beribadah,rasa_syukur,berdoa,toleransi,
               peduli_sesama,tawakal,memberi_salam,
               nilai_dominan,predikat,kategori,deskripsi)
            VALUES
              (:siswa_id,:mapel_id,:ta,:sem,
               :taat,:syukur,:berdoa,:toler,
               :peduli,:tawakal,:salam,
               :dom,:pred,:kat,:desk)
            ON DUPLICATE KEY UPDATE
               taat_beribadah=VALUES(taat_beribadah),
               rasa_syukur=VALUES(rasa_syukur),
               berdoa=VALUES(berdoa),
               toleransi=VALUES(toleransi),
               peduli_sesama=VALUES(peduli_sesama),
               tawakal=VALUES(tawakal),
               memberi_salam=VALUES(memberi_salam),
               nilai_dominan=VALUES(nilai_dominan),
               predikat=VALUES(predikat),
               kategori=VALUES(kategori),
               deskripsi=VALUES(deskripsi)
        ");

        foreach ($siswaListPost as $s) {

            $sid = (int)$s['id'];

            // Amankan semua input → "" menjadi null
            $rowNilai = [
                'taat_beribadah' => ($postTaat[$sid]    ?? null) ?: null,
                'rasa_syukur'    => ($postSyukur[$sid]  ?? null) ?: null,
                'berdoa'         => ($postBerdoa[$sid]  ?? null) ?: null,
                'toleransi'      => ($postToler[$sid]   ?? null) ?: null,
                'peduli_sesama'  => ($postPeduli[$sid]  ?? null) ?: null,
                'tawakal'        => ($postTawakal[$sid] ?? null) ?: null,
                'memberi_salam'  => ($postSalam[$sid]   ?? null) ?: null,
            ];

            // Hitung nilai dominan + predikat + deskripsi
            $hasil = hitung_sikap_spiritual($rowNilai);

            // Kalau semua nilai kosong → skip (tidak usah insert)
            if ($hasil['nilai_dominan'] === null) {
                continue;
            }

            $upsert->execute([
                ':siswa_id' => $sid,
                ':mapel_id' => $selectedMapelId,
                ':ta'       => $tahunAjaran,
                ':sem'      => $semester,
                ':taat'     => $rowNilai['taat_beribadah'],
                ':syukur'   => $rowNilai['rasa_syukur'],
                ':berdoa'   => $rowNilai['berdoa'],
                ':toler'    => $rowNilai['toleransi'],
                ':peduli'   => $rowNilai['peduli_sesama'],
                ':tawakal'  => $rowNilai['tawakal'],
                ':salam'    => $rowNilai['memberi_salam'],
                ':dom'      => $hasil['nilai_dominan'],
                ':pred'     => $hasil['predikat'],
                ':kat'      => $hasil['kategori'],
                ':desk'     => $hasil['deskripsi'],
            ]);
        }

        $pdo->commit();
        $successMessage = "Data sikap spiritual berhasil disimpan.";

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Gagal menyimpan data: " . htmlspecialchars($e->getMessage()));
    }
}

// ===== Ambil siswa + nilai yang sudah ada (untuk tampilan form) =====
$siswaList = [];
$existing  = [];

if ($selectedKelas !== '') {
    $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

    if ($siswaList) {
        $siswaIds = array_column($siswaList, 'id');
        $in       = implode(',', array_fill(0, count($siswaIds), '?'));
        $params   = $siswaIds;
        $params[] = $selectedMapelId;
        $params[] = $tahunAjaran;
        $params[] = $semester;

        $sqlNilai = "
            SELECT *
            FROM sikap_spiritual
            WHERE siswa_id IN ($in)
              AND mapel_id = ?
              AND tahun_ajaran = ?
              AND semester = ?
        ";
        $stmtNilai = $pdo->prepare($sqlNilai);
        $stmtNilai->execute($params);
        $rows = $stmtNilai->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $existing[$r['siswa_id']] = $r;
        }
    }
}

// ambil nama mapel aktif
$mapelAktif = null;
foreach ($mapelList as $m) {
    if ((int)$m['id'] === $selectedMapelId) {
        $mapelAktif = $m;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sikap Spiritual PAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="guru_dashboard.php">Guru - Sikap Spiritual (PAI)</a>
    <span class="navbar-text text-white">
        <?= htmlspecialchars($guru['name']); ?>
    </span>
  </div>
</nav>

<div class="container">

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Filter Data</h5>
            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <form method="get" class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Mapel PAI</label>
                    <select name="mapel_id" class="form-select" required>
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
                    <input type="text" name="tahun_ajaran" class="form-control"
                           value="<?= htmlspecialchars($tahunAjaran); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                        <option value="Genap"  <?= $semester === 'Genap'  ? 'selected' : ''; ?>>Genap</option>
                    </select>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                </div>

            </form>
        </div>
    </div>

    <?php if ($selectedKelas !== '' && $siswaList): ?>
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-secondary text-white">
                Input Sikap Spiritual - 
                Mapel: <?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?> |
                Kelas: <?= htmlspecialchars($selectedKelas); ?> |
                TA: <?= htmlspecialchars($tahunAjaran); ?> |
                Semester: <?= htmlspecialchars($semester); ?>
            </div>
            <div class="card-body table-responsive">
                <form method="post">
                    <input type="hidden" name="mapel_id" value="<?= $selectedMapelId; ?>">
                    <input type="hidden" name="kelas" value="<?= htmlspecialchars($selectedKelas); ?>">
                    <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran); ?>">
                    <input type="hidden" name="semester" value="<?= htmlspecialchars($semester); ?>">

                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Siswa</th>
                                <th class="text-center">Taat Beribadah</th>
                                <th class="text-center">Rasa Syukur</th>
                                <th class="text-center">Berdoa</th>
                                <th class="text-center">Toleransi</th>
                                <th class="text-center">Peduli Sesama</th>
                                <th class="text-center">Tawakal</th>
                                <th class="text-center">Memberi Salam</th>
                                <th class="text-center">Predikat</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($siswaList as $s): 
                            $sid = $s['id'];
                            $ex  = $existing[$sid] ?? [];
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($s['nama']); ?></td>
                                <?php
                                    $fields = [
                                        'taat_beribadah' => 'taat_beribadah',
                                        'rasa_syukur'    => 'rasa_syukur',
                                        'berdoa'         => 'berdoa',
                                        'toleransi'      => 'toleransi',
                                        'peduli_sesama'  => 'peduli_sesama',
                                        'tawakal'        => 'tawakal',
                                        'memberi_salam'  => 'memberi_salam',
                                    ];
                                ?>
                                <?php foreach ($fields as $key => $col): ?>
                                    <td class="text-center">
                                        <select name="<?= $key; ?>[<?= $sid; ?>]" class="form-select form-select-sm">
                                            <option value="">-</option>
                                            <?php for ($i=1; $i<=4; $i++): ?>
                                                <option value="<?= $i; ?>"
                                                    <?= (isset($ex[$col]) && (int)$ex[$col] === $i) ? 'selected' : ''; ?>>
                                                    <?= $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-center">
                                    <?= htmlspecialchars($ex['predikat'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-success">Simpan Sikap Spiritual</button>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($selectedKelas !== ''): ?>
        <div class="alert alert-warning">Belum ada siswa di kelas ini.</div>
    <?php endif; ?>

</div>

</body>
</html>
