<?php
if (!isset($_SESSION)) session_start();

$role = $_SESSION['user']['role'];
?>

<style>
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100%;
        width: 230px;
        background: #263238;
        color: white;
        padding-top: 20px;
    }
    .sidebar h2 {
        text-align: center;
        margin-bottom: 30px;
        font-size: 20px;
        font-weight: bold;
        color: #fff;
    }
    .sidebar a {
        display: block;
        color: white;
        padding: 12px 20px;
        text-decoration: none;
        font-size: 15px;
    }
    .sidebar a:hover {
        background: #37474F;
    }
    .main-content {
        margin-left: 240px;
        padding: 20px;
    }
</style>

<div class="sidebar">
    <h2>RAPOR SMA AL ISHLAH</h2>

    <?php if ($role === 'admin'): ?>
        <a href="/admin/admin_dashboard.php">Dashboard Admin</a>
        <a href="/admin/teacher_list.php">Data Guru</a>
        <a href="/admin/admin_add_teacher.php">Tambah Guru</a>
        <a href="/admin/student_list.php">Data Siswa</a>
        <a href="/admin/student_add.php">Tambah Siswa</a>

    <?php elseif ($role === 'guru'): ?>
        <a href="/guru/guru_dashboard.php">Dashboard Guru</a>
        <a href="/guru/nilai_pengetahuan.php">Nilai Pengetahuan</a>
        <a href="/guru/nilai_keterampilan.php">Nilai Keterampilan</a>
        <a href="../auth/change_password.php">Ubah Password</a>


    <?php elseif ($role === 'walikelas'): ?>
        <a href="/walikelas/wali_dashboard.php">Dashboard Wali Kelas</a>
        <a href="/walikelas/rekap_nilai.php">Rekap Nilai</a>
        <a href="../auth/change_password.php">Ubah Password</a>

    <?php endif; ?>

    <a href="/auth/logout.php" style="background:#D32F2F;">Logout</a>
</div>
