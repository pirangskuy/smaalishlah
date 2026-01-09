<?php
// admin/jadwal_pelajaran.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/auth.php';

requireLogin();
requireAdmin();

// --- helper kecil urutkan hari di tampilan ---
$hariOrder = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];

// --- ambil filter GET/POST ---
$tahunAjaran = $_REQUEST['tahun_ajaran'] ?? '2025/2026';
$semester    = $_REQUEST['semester'] ?? 'Genap';
$kelas       = $_REQUEST['kelas'] ?? '10';

// --- handle HAPUS ---
if (isset($_GET['hapus_id'])) {
    $hapusId = (int)$_GET['hapus_id'];
    if ($hapusId > 0) {
        $del = $pdo->prepare("DELETE FROM jadwal_pelajaran WHERE id = ?");
        $del->execute([$hapusId]);
        header("Location: jadwal_pelajaran.php?tahun_ajaran=" . urlencode($tahunAjaran) .
                                  "&semester=" . urlencode($semester) .
                                  "&kelas=" . urlencode($kelas));
        exit;
    }
}

// --- handle TAMBAH jadwal (POST) ---
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {

    $tahunAjaran = trim($_POST['tahun_ajaran'] ?? $tahunAjaran);
    $semester    = $_POST['semester'] ?? $semester;
    $kelas       = $_POST['kelas'] ?? $kelas;
    $hari        = $_POST['hari'] ?? 'Senin';
    $jamMulai    = $_POST['jam_mulai'] ?? '';
    $jamSelesai  = $_POST['jam_selesai'] ?? '';
    $mapelId     = (int)($_POST['mapel_id'] ?? 0);
    $teacherId   = (int)($_POST['teacher_id'] ?? 0);
    $ket         = trim($_POST['keterangan'] ?? '');

    if ($tahunAjaran === '' || !$mapelId || !$teacherId || $jamMulai === '' || $jamSelesai === '') {
        $message = "Semua field wajib diisi (kecuali keterangan).";
    } else {
        $ins = $pdo->prepare("
            INSERT INTO jadwal_pelajaran
              (tahun_ajaran, semester, kelas, hari, jam_mulai, jam_selesai,
               mapel_id, teacher_id, keterangan)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $tahunAjaran, $semester, $kelas, $hari,
            $jamMulai, $jamSelesai,
            $mapelId, $teacherId, $ket !== '' ? $ket : null
        ]);

        $message = "Jadwal berhasil ditambahkan.";
    }
}

// --- data dropdown mapel & guru ---
$mapelList = $pdo->query("SELECT id, nama FROM mapel ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$guruList  = $pdo->query("SELECT id, name FROM teachers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// --- ambil daftar jadwal untuk filter terpilih ---
$stmtJadwal = $pdo->prepare("
    SELECT j.*, m.nama AS mapel_nama, t.name AS guru_nama
    FROM jadwal_pelajaran j
    JOIN mapel m   ON j.mapel_id = m.id
    JOIN teachers t ON j.teacher_id = t.id
    WHERE j.tahun_ajaran = ?
      AND j.semester = ?
      AND j.kelas = ?
    ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'),
             j.jam_mulai
");
$stmtJadwal->execute([$tahunAjaran, $semester, $kelas]);
$jadwalList = $stmtJadwal->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Jadwal Pelajaran</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin_jadwal_pelajaran.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-islah mb-4">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="admin_dashboard.php">
            <div class="logo-placeholder">
                <img src="../media/AL ISLHAH.jpg" alt="Logo SMA Al Ishlah" class="logo-img">
            </div>
            <div class="d-flex flex-column lh-1 brand-text">
                <span class="brand-title-text">SMA AL ISHLAH</span>
                 <small class="badge-school mt-1">Sistem Rapor Digital</small>
            </div>
        </a>

        <div class="collapse navbar-collapse justify-content-end" id="navbarMain">
            <div class="d-flex align-items-center gap-3">
                <span class="admin-mode-badge">
                    <i class="bi bi-shield-lock"></i> Mode Admin
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="box-wrapper">
    <div class="top-bar">
        <a href="admin_dashboard.php" class="btn-back">Kembali ke Dashboard</a>
    </div>
</div>

<div class="card-custom">
    <div class="card-body-custom">
        <div class="left-side">
            <h3>Filter Jadwal</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert-custom"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="get" class="form-row">
            <div class="form-group">
                <label>Tahun Ajaran</label>
                <input type="text" name="tahun_ajaran"
                       value="<?= htmlspecialchars($tahunAjaran); ?>">
            </div>

            <div class="form-group">
                <label>Semester</label>
                <select name="semester">
                    <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="Genap"  <?= $semester === 'Genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>

            <div class="form-group">
                <label>Kelas</label>
                <select name="kelas">
                    <option value="10" <?= $kelas === '10' ? 'selected' : ''; ?>>10</option>
                    <option value="11" <?= $kelas === '11' ? 'selected' : ''; ?>>11</option>
                    <option value="12" <?= $kelas === '12' ? 'selected' : ''; ?>>12</option>
                </select>
            </div>

            <div class="form-group button-group">
                <button type="submit" class="btn-submit">Terapkan Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="box-wrapper1">
    <div class="left-side">
        <h3>
            Jadwal Kelas <?= htmlspecialchars($kelas); ?> |
            TA <?= htmlspecialchars($tahunAjaran); ?> |
            Semester <?= htmlspecialchars($semester); ?>
        </h3>
    </div>

    <div class="container">
        <div class="custom-card">
            <div class="table-wrapper">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Mapel</th>
                            <th>Guru</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($jadwalList)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Belum ada jadwal untuk filter ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jadwalList as $j): ?>
                                <tr>
                                    <td><?= htmlspecialchars($j['hari']); ?></td>
                                    <td>
                                        <?= substr($j['jam_mulai'],0,5) . ' - ' . substr($j['jam_selesai'],0,5); ?>
                                    </td>
                                    <td><?= htmlspecialchars($j['mapel_nama']); ?></td>
                                    <td><?= htmlspecialchars($j['guru_nama']); ?></td>
                                    <td style="text-align: center;">
                                        <a href="jadwal_pelajaran.php?hapus_id=<?= $j['id']; ?>
                                            &tahun_ajaran=<?= urlencode($tahunAjaran); ?>
                                            &semester=<?= urlencode($semester); ?>
                                            &kelas=<?= urlencode($kelas); ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Hapus jadwal ini?');">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="box-wrapper2">
    <div class="card-body-custom">
        <div class="left-side">
            <h3>Tambah Jadwal Baru</h3>
        </div>

        <form method="post" class="form-grid">
            <input type="hidden" name="aksi" value="tambah">

            <div class="form-group">
                <label>Tahun Ajaran</label>
                <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($tahunAjaran); ?>" required>
            </div>

            <div class="form-group">
                <label>Semester</label>
                <select name="semester" required>
                    <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="Genap" <?= $semester === 'Genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>

            <div class="form-group">
                <label>Kelas</label>
                <select name="kelas" required>
                    <option value="10" <?= $kelas === '10' ? 'selected' : ''; ?>>10</option>
                    <option value="11" <?= $kelas === '11' ? 'selected' : ''; ?>>11</option>
                    <option value="12" <?= $kelas === '12' ? 'selected' : ''; ?>>12</option>
                </select>
            </div>

            <div class="form-group">
                <label>Hari</label>
                <select name="hari" required>
                    <?php foreach ($hariOrder as $h): ?>
                        <option value="<?= $h; ?>"><?= $h; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group small">
                <label>Mulai</label>
                <input type="time" name="jam_mulai" required>
            </div>

            <div class="form-group small">
                <label>Selesai</label>
                <input type="time" name="jam_selesai" required>
            </div>

            <div class="form-group wide">
                <label>Mata Pelajaran</label>
                <select name="mapel_id" required>
                    <option value="">-- Pilih Mapel --</option>
                    <?php foreach ($mapelList as $m): ?>
                        <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group wide">
                <label>Guru Pengampu</label>
                <select name="teacher_id" required>
                    <option value="">-- Pilih Guru --</option>
                    <?php foreach ($guruList as $g): ?>
                        <option value="<?= $g['id']; ?>"><?= htmlspecialchars($g['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group full">
                <label>Keterangan (opsional)</label>
                <input type="text" name="keterangan" placeholder="Misal: Jam BK, Yasinan, dsb.">
            </div>

            <div class="form-submit">
                <button type="submit" class="btn-submit">
                    Simpan Jadwal
                </button>
            </div>
        </form>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
