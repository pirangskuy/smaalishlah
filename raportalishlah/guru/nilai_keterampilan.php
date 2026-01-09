<?php
// guru/nilai_keterampilan.php
// Input "Hasil Penilaian Keterampilan per KD" (angka akhir seperti di Excel Ket. KD-1, Ket. KD-2, dst)

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

// Ambil data guru (opsional, untuk tampilan nama)
$stmtGuru = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmtGuru->execute([$guruId]);
$guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);
$namaGuru = $guru['name'] ?? $_SESSION['user']['username'];

// Ambil mapel yang diampu guru ini (tabel mapel: id, nama, kkm, teacher_id)
$stmtMapel = $pdo->prepare("
    SELECT id, nama, kkm 
    FROM mapel 
    WHERE teacher_id = ? 
    ORDER BY nama
");
$stmtMapel->execute([$guruId]);
$mapelList = $stmtMapel->fetchAll(PDO::FETCH_ASSOC);

// Kelas fix: 10, 11, 12 (sesuai kebijakan aplikasi)
$kelasList = ['10', '11', '12'];

// ===== Tambahan: Tahun Ajaran & Semester default =====
$tahun_ajaran_default = '2025/2026';
$semester_default     = 'Ganjil';

// Ambil dari GET jika ada, supaya sinkron dengan setor_nilai_mapel
$tahunAjaran = $_GET['tahun_ajaran'] ?? $tahun_ajaran_default;
$semester    = $_GET['semester'] ?? $semester_default;

// Ambil pilihan mapel & kelas dari GET
$selectedMapelId = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
$selectedKelas   = isset($_GET['kelas']) ? $_GET['kelas'] : '';

$kdList     = [];
$siswaList  = [];
$mapelAktif = null;

if ($selectedMapelId && $selectedKelas !== '') {
    // Ambil detail mapel aktif (untuk judul)
    $stmtOneMapel = $pdo->prepare("SELECT id, nama, kkm FROM mapel WHERE id = ? LIMIT 1");
    $stmtOneMapel->execute([$selectedMapelId]);
    $mapelAktif = $stmtOneMapel->fetch(PDO::FETCH_ASSOC);

    // KD keterampilan untuk mapel ini (tabel: kd_keterampilan)
    // Struktur minimal: id, mapel_id, kd_ke, deskripsi
    $stmtKd = $pdo->prepare("
        SELECT id, kd_ke, deskripsi
        FROM kd_keterampilan
        WHERE mapel_id = ?
        ORDER BY kd_ke
    ");
    $stmtKd->execute([$selectedMapelId]);
    $kdList = $stmtKd->fetchAll(PDO::FETCH_ASSOC);

    // Siswa di kelas tersebut
    $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? ORDER BY nama");
    $stmtSiswa->execute([$selectedKelas]);
    $siswaList = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Nilai Keterampilan</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <!-- Notif jika baru saja disimpan -->
    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
        <div class="alert alert-success">Nilai keterampilan berhasil disimpan.</div>
    <?php endif; ?>

    <!-- Header -->
    <div class="row mb-3">
        <div class="col">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-1">Input Nilai Keterampilan per KD</h2>
                    <p class="text-muted mb-0">
                        Nilai yang diinput di sini adalah <b>nilai akhir keterampilan per KD</b>,
                        sama seperti kolom <i>“Hasil Penilaian Keterampilan KD …”</i> di Excel
                        (setelah Bapak/Ibu mengolah dari Praktik, Produk, Proyek, dsb).
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Pilih Mapel & Kelas -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($mapelList)): ?>
                        <div class="alert alert-warning mb-3">
                            Anda belum terdaftar mengampu mata pelajaran apapun di tabel <code>mapel</code>. 
                            Silakan hubungi admin untuk mengatur guru pengampu mapel.
                        </div>
                    <?php endif; ?>

                    <form method="get" class="row g-3">

                        <div class="col-md-6">
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
                                    <option value="<?= $k; ?>" <?= $selectedKelas === $k ? 'selected' : ''; ?>>
                                        <?= $k; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Hidden agar tahun ajaran & semester tetap ikut saat ganti mapel/kelas -->
                        <input type="hidden" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran); ?>">
                        <input type="hidden" name="semester" value="<?= htmlspecialchars($semester); ?>">

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Input Nilai Keterampilan -->
    <?php if ($selectedMapelId && $selectedKelas !== ''): ?>

        <?php if (empty($kdList)): ?>
            <div class="alert alert-warning">
                Belum ada KD Keterampilan untuk mata pelajaran ini.  
                Silakan tambahkan KD di menu admin (tabel <code>kd_keterampilan</code>).
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
                            Input Nilai Keterampilan per KD
                            <br>
                            <small>
                                Mapel: 
                                <?= htmlspecialchars($mapelAktif['nama'] ?? ''); ?> 
                                (KKM: <?= (int)($mapelAktif['kkm'] ?? 0); ?>)
                                | Kelas: <?= htmlspecialchars($selectedKelas); ?>
                            </small>
                        </div>
                        <div class="card-body table-responsive">

                            <form method="post" action="simpan_keterampilan.php">
                                <!-- Info yang dibawa ke proses simpan -->
                                <input type="hidden" name="mapel_id" value="<?= $selectedMapelId; ?>">
                                <input type="hidden" name="kelas" value="<?= htmlspecialchars($selectedKelas); ?>">

                                <!-- Tambahan: Tahun Ajaran & Semester yang akan disimpan ke tabel nilai_keterampilan -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Tahun Pelajaran</label>
                                        <input type="text" name="tahun_ajaran" class="form-control"
                                               value="<?= htmlspecialchars($tahunAjaran); ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Semester</label>
                                        <select name="semester" class="form-select" required>
                                            <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                                            <option value="Genap"  <?= $semester === 'Genap'  ? 'selected' : ''; ?>>Genap</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- END tambahan TA & Semester -->

                                <table class="table table-bordered table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 180px;">Nama Siswa</th>
                                            <?php foreach ($kdList as $kd): ?>
                                                <th class="text-center">
                                                    KD <?= htmlspecialchars($kd['kd_ke']); ?><br>
                                                    <small><?= htmlspecialchars($kd['deskripsi']); ?></small>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($siswaList as $s): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($s['nama']); ?></td>
                                                <?php foreach ($kdList as $kd): ?>
                                                    <td class="text-center">
                                                        <input
                                                            type="number"
                                                            name="nilai[<?= $s['id']; ?>][<?= $kd['id']; ?>]"
                                                            class="form-control form-control-sm text-center"
                                                            min="0" max="100" step="1"
                                                        >
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="mt-3 text-end">
                                    <button type="submit" class="btn btn-success">
                                        Simpan Nilai Keterampilan
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
</body>
</html>
