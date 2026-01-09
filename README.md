# Raport Digital — SMA Al-Ishlah

Aplikasi web **Raport Digital SMA Al-Ishlah** berbasis **PHP + MySQL (PDO)** untuk membantu proses penilaian dan pelaporan rapor secara terstruktur: pengelolaan data guru/siswa, input nilai (pengetahuan & keterampilan), KD, sikap, absensi, ekskul, prestasi, hingga **cetak rapor**.

> Cocok untuk penggunaan internal sekolah / demo proyek.  
> Untuk production, pastikan menerapkan praktik keamanan (lihat bagian **Keamanan**).

---

## Daftar Isi
- [Fitur](#fitur)
- [Teknologi](#teknologi)
- [Struktur Proyek](#struktur-proyek)
- [Quick Start (Lokal - Laragon)](#quick-start-lokal---laragon)
- [Konfigurasi Database](#konfigurasi-database)
- [Membuat Admin Pertama Kali](#membuat-admin-pertama-kali)
- [Checklist Production](#checklist-production)
- [Troubleshooting](#troubleshooting)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

---

## Fitur

### Role: Admin
- Manajemen **Guru** (tambah/edit/hapus)
- Manajemen **Siswa** (tambah/edit/hapus + import)
- Manajemen akun (akun guru/wali, reset password)
- Pengaturan **pengampu mapel** & **jadwal pelajaran**

### Role: Guru
- Kelola **KD Pengetahuan** & **KD Keterampilan**
- Input nilai: **Pengetahuan**, **Keterampilan**, **UTS/UAS**
- Input sikap: **Spiritual** & **Sosial**
- Rekap nilai per mapel

### Role: Wali Kelas
- Rekap nilai kelas & rekap rapor
- Rekap **absensi**
- Input **ekskul**, **prestasi**, dan **catatan wali**
- **Cetak rapor** (multi halaman)

---

## Teknologi
- PHP (disarankan 8.x, minimal 7.4+)
- MySQL/MariaDB
- PDO (`pdo`, `pdo_mysql`)
- HTML/CSS (asset di `raportalishlah/assets/`)

---

## Struktur Proyek

Ringkasnya:

- `index.php` → entry point / redirect
- `raportalishlah/`
  - `auth/` → login, logout, middleware/guard role
  - `admin/` → dashboard & manajemen data
  - `guru/` → input & rekap nilai/KD/sikap
  - `walikelas/` → rapor, absensi, ekskul, prestasi, cetak
  - `config/` → konfigurasi database (PDO)
  - `layout/` → header/footer/sidebar
  - `assets/` → CSS
  - `media/` → gambar/asset sekolah

---

## Quick Start (Lokal - Laragon)

1) Clone repo ke folder Laragon:
```bash
cd C:\laragon\www
git clone https://github.com/pirangskuy/smaalishlah.git
