<?php
// guru/kd_keterampilan_add.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireGuru();

$guruId = $_SESSION['user']['guru_id'] ?? null;
if (!$guruId) {
    die("Akun guru tidak valid (guru_id kosong). Hubungi admin.");
}

// Ambil mapel guru
$stmtMapel = $pdo->prepare("
    SELECT id, nama, kkm 
    FROM mapel 
    WHERE teacher_id = ? 
    ORDER BY nama
");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mapelId   = (int)($_POST['mapel_id'] ?? 0);
    $kdKe      = (int)($_POST['kd_ke'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (!$mapelId || !$kdKe || $deskripsi === '') {
        $message = "<div class='alert alert-danger'>Mapel, nomor KD, dan deskripsi wajib diisi.</div>";
    } else {
        // Simpan ke tabel kd_keterampilan
        $stmt = $pdo->prepare("
            INSERT INTO kd_keterampilan (mapel_id, kd_ke, deskripsi)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$mapelId, $kdKe, $deskripsi]);

        header('Location: kd_keterampilan_list.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah KD Keterampilan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .header-excel {
            background: #f57c00;
            color: #fff;
            padding: 6px 10px;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
            font-size: 0.9rem;
        }
        .subheader-excel {
            background: #1976d2;
            color: #fff;
            padding: 4px 10px;
            font-size: 0.8rem;
        }
        .deskripsi-box {
            background: #fff9c4;
            border: 1px solid #fbc02d;
            min-height: 120px;
            padding: 8px;
            border-radius: 0 0 4px 4px;
        }
        textarea.form-control {
            font-size: .9rem;
        }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <h3>Tambah KD Keterampilan</h3>
    <p>Guru: <strong><?= htmlspecialchars($_SESSION['user']['username']); ?></strong></p>

    <a href="kd_keterampilan_list.php" class="btn btn-secondary btn-sm mb-3">Kembali ke Daftar KD Keterampilan</a>

    <?php if ($message): ?>
        <div class="mb-3"><?= $message; ?></div>
    <?php endif; ?>

    <?php if (empty($mapelList)): ?>
        <div class="alert alert-warning">
            Anda belum dihubungkan dengan mata pelajaran di tabel <code>mapel</code>.
            Hubungi admin untuk mengatur guru pengampu mapel.
        </div>
    <?php else: ?>
        <form method="post" class="card shadow-sm">
            <div class="card-body">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Mata Pelajaran</label>
                        <select name="mapel_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Mapel --</option>
                            <?php foreach ($mapelList as $m): ?>
                                <option value="<?= $m['id']; ?>">
                                    <?= htmlspecialchars($m['nama']); ?> (KKM: <?= (int)$m['kkm']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">KD Ke-</label>
                        <input type="number" name="kd_ke" min="1" class="form-control form-control-sm" required>
                    </div>
                </div>

                <!-- Blok ala Excel -->
                <div class="mb-2">
                    <div class="header-excel">
                        Hasil Penilaian Keterampilan – <span id="labelKd">KD Ke - …</span>
                    </div>
                    <div class="subheader-excel">
                        Deskripsi KD Keterampilan
                    </div>
                    <div class="deskripsi-box">
                        <textarea name="deskripsi" class="form-control border-0 bg-transparent" rows="4"
                                  placeholder="Contoh: menunjukkan sikap cinta tanah air dan moderasi beragama dengan benar"
                                  required></textarea>
                    </div>
                    <small class="text-muted">
                        Deskripsi ini akan digunakan sebagai dasar pembuatan deskripsi nilai keterampilan di rapor (KD-1, KD-2, dst).
                    </small>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-success">
                        Simpan KD Keterampilan
                    </button>
                </div>

            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Update label "KD Ke - ..." saat user mengetik nomor KD
document.addEventListener('DOMContentLoaded', function () {
    const inputKd = document.querySelector('input[name="kd_ke"]');
    const labelKd = document.getElementById('labelKd');
    if (inputKd && labelKd) {
        inputKd.addEventListener('input', function () {
            const v = this.value || '…';
            labelKd.textContent = 'KD Ke - ' + v;
        });
    }
});
</script>

</body>
</html>
 