# Sistem Informasi Koperasi Simpan Pinjam (KSP Gatra Mandiri)

Aplikasi web modern untuk Koperasi Simpan Pinjam dengan portal khusus **Administrator / Pengurus** dan portal khusus **Anggota Koperasi**.

Website ini dibuat dengan arsitektur **PHP & MySQL / MariaDB** yang ringan, aman, responsif, dan siap langsung di-deploy ke penyedia hosting seperti **Rumahweb.com** (Paket Hosting Medium) maupun dijalankan di lingkungan lokal (**Laragon**).

---

## 🌟 Fitur Utama

### 1. Halaman Login (`login.php`)
- Tampilan modern, bersih, dan profesional berstandar perbankan/koperasi syariah/konvensional.
- Dilengkapi **Tombol Akses Demo 1-Klik** untuk mempermudah pengujian:
  - 👑 **Admin**: `admin` / `admin123`
  - 👤 **Anggota 1 (Budi)**: `budi` / `budi123` (simpanan aktif & pinjaman berjalan)
  - 👤 **Anggota 2 (Siti)**: `siti` / `siti123` (simpanan besar & pinjaman hampir lunas)
  - 👤 **Anggota 3 (Hendra)**: `hendra` / `hendra123` (bebas pinjaman)
- Sistem deteksi hak akses otomatis: pengurus diarahkan ke panel admin, anggota diarahkan ke portal anggota.

### 2. Halaman Admin / Pengurus (`admin/`)
- **Dashboard Ringkasan (`admin/index.php`)**:
  - Statistik real-time: Total Anggota, Total Simpanan Terkumpul, Portofolio Pinjaman, dan Sisa Tagihan Pinjaman Belum Terbayar.
  - Tabel ringkasan pinjaman berjalan dengan progress bar persentase pembayaran.
  - Mutasi simpanan anggota terkini.
- **Input & Kelola Simpanan (`admin/simpanan.php`)**:
  - Pencatatan transaksi simpanan pokok, simpanan wajib bulanan, dan simpanan sukarela.
  - Dukungan transaksi setor dan tarik dana simpanan.
- **Input & Penerbitan Pinjaman (`admin/pinjaman.php`)**:
  - Formulir penerbitan pinjaman baru dilengkapi kalkulator cicilan real-time (pokok + bunga flat per bulan).
  - Pemantauan sisa pinjaman setiap anggota.
- **Pembayaran Angsuran (`admin/angsuran.php`)**:
  - Input pembayaran cicilan pinjaman bulanan.
  - Sistem otomatis menghitung sisa tagihan pinjaman dan mengubah status menjadi "LUNAS" saat pembayaran selesai.
- **Manajemen Anggota (`admin/anggota.php`)**:
  - Pendaftaran anggota baru dengan pembuatan akun login otomatis dan pencatatan simpanan pokok awal.
- **Laporan Koperasi (`admin/laporan.php`)**:
  - Laporan rekapitulasi simpanan per anggota.
  - Laporan daftar pinjaman dan total sisa pinjaman berjalan.
  - Laporan arus kas buku kas & estimasi Sisa Hasil Usaha (SHU).
  - Mode cetak dokumen resmi (*print-ready layout*) lengkap dengan kolom tanda tangan ketua & bendahara.

### 3. Halaman Anggota (`anggota/`)
- **Dashboard Anggota (`anggota/index.php`)**:
  - **Kartu Digital Anggota Koperasi** bernuansa Gold & Emerald dengan nomor keanggotaan unik (`KOP-XXXX-XXX`).
  - Ringkasan sisa saldo simpanan secara transparan.
  - Ringkasan sisa kewajiban pinjaman dan nominal cicilan bulanan.
- **Rincian Sisa Simpanan (`anggota/simpanan.php`)**:
  - Rincian saldo simpanan pokok, wajib, dan sukarela.
  - Buku rekening koran / mutasi transaksi simpanan yang dapat dicetak.
- **Sisa Pinjaman & Tagihan (`anggota/pinjaman.php`)**:
  - Rincian kontrak pinjaman aktif.
  - Tabel jadwal angsuran bulan ke-1 s/d selesai dengan tanda status lunas / aktif.
- **Simulasi Pinjaman (`anggota/pengajuan.php`)**:
  - Kalkulator simulasi pinjaman interaktif bagi anggota yang ingin menghitung estimasi cicilan sebelum mengajukan pembiayaan baru.

---

## 📁 Struktur Direktori

```
gatra-core/
├── admin/                     # Modul Pengurus / Administrator Koperasi
│   ├── index.php              # Dashboard statistik admin
│   ├── anggota.php            # Direktori & input anggota
│   ├── simpanan.php           # Input & data simpanan
│   ├── pinjaman.php           # Input & daftar pinjaman
│   ├── angsuran.php           # Input angsuran & pelunasan
│   ├── laporan.php            # Laporan keuangan & cetak dokumen
│   ├── header.php             # Layout header & navigasi admin
│   └── footer.php             # Layout footer admin
├── anggota/                   # Modul Anggota Koperasi
│   ├── index.php              # Dashboard & kartu digital anggota
│   ├── simpanan.php           # Rincian sisa simpanan & buku tabungan
│   ├── pinjaman.php           # Rincian sisa pinjaman & jadwal angsuran
│   ├── pengajuan.php          # Simulasi kalkulator cicilan
│   ├── header.php             # Layout header & navigasi anggota
│   └── footer.php             # Layout footer anggota
├── assets/                    # Aset Desain & Interaksi
│   ├── css/style.css          # Desain sistem responsif, modern, & print-ready
│   └── js/main.js             # Kalkulator cicilan, live search, & modal logic
├── config/                    # Konfigurasi Sistem
│   ├── database.php           # Koneksi cerdas MySQL (Rumahweb) & fallback SQLite
│   ├── helper.php             # Fungsi format rupiah, tanggal indo, auth session
│   └── init_db.php            # Auto-installer tabel dan data demo
├── database.sql               # Database SQL siap impor ke phpMyAdmin Rumahweb
├── index.php                  # Gerbang utama aplikasi (auto-redirect)
├── login.php                  # Halaman login modern
├── logout.php                 # Handler logout sistem
├── PANDUAN_HOSTING_RUMAHWEB.md# Petunjuk upload & hosting di Rumahweb
└── README.md                  # Dokumentasi proyek
```

---

## 🚀 Cara Menjalankan di Lokal (Laragon)

1. Pastikan folder berada di: `c:\laragon\www\gatra-core`
2. Buka aplikasi Laragon &rarr; klik **Start All** (Apache & MySQL).
3. Buka browser dan kunjungi:
   ```
   http://localhost/gatra-core/
   ```
   *atau jika menggunakan Virtual Host Laragon:*
   ```
   http://gatra-core.test/
   ```
4. Sistem otomatis menginisialisasi database dan siap dipakai!

---

## 🌐 Panduan Upload ke Rumahweb

Silakan baca dokumen lengkap di file: **[PANDUAN_HOSTING_RUMAHWEB.md](file:///c:/laragon/www/gatra-core/PANDUAN_HOSTING_RUMAHWEB.md)**.
