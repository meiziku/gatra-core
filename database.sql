-- ============================================================
-- SQL Schema & Data Awal untuk Koperasi Simpan Pinjam Gatra Mandiri
-- Cocok untuk diimpor langsung ke phpMyAdmin Rumahweb / Laragon MySQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS `koperasi_gatra` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `koperasi_gatra`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `angsuran`;
DROP TABLE IF EXISTS `pinjaman`;
DROP TABLE IF EXISTS `simpanan`;
DROP TABLE IF EXISTS `kas_transaksi`;
DROP TABLE IF EXISTS `anggota`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Tabel Users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'anggota') NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Anggota
CREATE TABLE `anggota` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `no_anggota` VARCHAR(30) UNIQUE NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `nik` VARCHAR(30) NULL,
  `alamat` TEXT NULL,
  `no_telp` VARCHAR(30) NULL,
  `pekerjaan` VARCHAR(100) NULL,
  `tanggal_bergabung` DATE NOT NULL,
  `tanggal_keluar` DATE NULL,
  `status` ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Simpanan
CREATE TABLE `simpanan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `anggota_id` INT NOT NULL,
  `jenis_simpanan` ENUM('pokok', 'wajib', 'sukarela') NOT NULL,
  `tipe_transaksi` ENUM('setor', 'tarik') NOT NULL DEFAULT 'setor',
  `nominal` DECIMAL(15,2) NOT NULL,
  `tanggal` DATE NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `petugas` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`anggota_id`) REFERENCES `anggota`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Pinjaman
CREATE TABLE `pinjaman` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `no_pinjaman` VARCHAR(40) UNIQUE NOT NULL,
  `anggota_id` INT NOT NULL,
  `jumlah_pinjaman` DECIMAL(15,2) NOT NULL,
  `bunga_persen` DECIMAL(5,2) NOT NULL DEFAULT 1.0,
  `lama_bulan` INT NOT NULL,
  `angsuran_pokok` DECIMAL(15,2) NOT NULL,
  `angsuran_bunga` DECIMAL(15,2) NOT NULL,
  `total_angsuran_bulanan` DECIMAL(15,2) NOT NULL,
  `total_harus_dibayar` DECIMAL(15,2) NOT NULL,
  `tanggal_pengajuan` DATE NOT NULL,
  `tanggal_pencairan` DATE NULL,
  `status` ENUM('menunggu', 'disetujui', 'ditolak', 'berjalan', 'lunas') DEFAULT 'berjalan',
  `keterangan` TEXT NULL,
  `asuransi` DECIMAL(15,2) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`anggota_id`) REFERENCES `anggota`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Angsuran
CREATE TABLE `angsuran` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pinjaman_id` INT NOT NULL,
  `angsuran_ke` INT NOT NULL,
  `jumlah_bayar` DECIMAL(15,2) NOT NULL,
  `angsuran_pokok` DECIMAL(15,2) NOT NULL,
  `angsuran_bunga` DECIMAL(15,2) NOT NULL,
  `denda` DECIMAL(15,2) DEFAULT 0,
  `tanggal_bayar` DATE NOT NULL,
  `petugas` VARCHAR(100) NULL,
  `keterangan` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`pinjaman_id`) REFERENCES `pinjaman`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Kas Transaksi
CREATE TABLE `kas_transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kategori` ENUM('pemasukan', 'pengeluaran') NOT NULL,
  `jenis` VARCHAR(100) NOT NULL,
  `nominal` DECIMAL(15,2) NOT NULL,
  `tanggal` DATE NOT NULL,
  `keterangan` TEXT NULL,
  `petugas` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel Simpanan Pendidikan
CREATE TABLE IF NOT EXISTS `simpanan_pendidikan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `no_rekening` VARCHAR(40) UNIQUE NOT NULL,
  `anggota_id` INT NOT NULL,
  `target_dana` DECIMAL(15,2) NOT NULL,
  `tenor_bulan` INT NOT NULL,
  `setoran_bulanan` DECIMAL(15,2) NOT NULL,
  `tanggal_mulai` DATE NOT NULL,
  `tanggal_jatuh_tempo` DATE NULL,
  `status` ENUM('aktif', 'selesai', 'batal') DEFAULT 'aktif',
  `keterangan` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`anggota_id`) REFERENCES `anggota`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabel Setoran Simpanan Pendidikan
CREATE TABLE IF NOT EXISTS `simpanan_pendidikan_setoran` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `simpanan_pendidikan_id` INT NOT NULL,
  `setoran_ke` INT NOT NULL,
  `nominal` DECIMAL(15,2) NOT NULL,
  `tanggal_bayar` DATE NOT NULL,
  `keterangan` VARCHAR(50) DEFAULT 'Langsung',
  `petugas` VARCHAR(100) NULL,
  `catatan` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`simpanan_pendidikan_id`) REFERENCES `simpanan_pendidikan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tabel Pengaturan Sistem
CREATE TABLE IF NOT EXISTS `pengaturan` (
  `kunci` VARCHAR(100) PRIMARY KEY,
  `nilai` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pengaturan` (`kunci`, `nilai`) VALUES
('data_koperasi', '{"tahun_buku":"2026","nama_koperasi":"Koperasi Simpan Pinjam Gatra Teknika","alamat":"Jl. By Pass Sunyaragi No. 45, Cirebon, Jawa Barat 45132","telepon":"(0231) 482910 / 0812-3456-7890","email":"kontak@gatrateknika.co.id","ketua":"Drs. H. Mulyadi, M.M.","sekretaris":"Ratna Dewi, S.E.","bendahara":"Arief Budiman, S.Ak.","pengelola_sp":"Bambang Irawan","pengelola_toko":"Dewi Lestari"}'),
('pembagian_shu', '[{"id":1,"nama":"Jasa Modal (Simpanan) Anggota","persen":25.00,"locked":true},{"id":2,"nama":"Jasa Usaha Anggota - Simpan Pinjam","persen":25.00,"locked":true},{"id":3,"nama":"Dana Cadangan","persen":20.00,"locked":true},{"id":4,"nama":"Dana Pemdaker","persen":5.00,"locked":false},{"id":5,"nama":"Dana Pengurus","persen":10.00,"locked":false},{"id":6,"nama":"Dana Kesejahteraan Pegawai","persen":5.00,"locked":false},{"id":7,"nama":"Dana Pendidikan","persen":5.00,"locked":false},{"id":8,"nama":"Dana Sosial","persen":5.00,"locked":false}]'),
('neraca_saldo', '{"A1":{"nama_kategori":"A.1. Aset Lancar","items":[{"kode":"A.1.1","nama":"Kas Simpan Pinjam","saldo":0},{"kode":"A.1.2","nama":"Kas Pusat","saldo":0},{"kode":"A.1.3","nama":"Kas Bank","saldo":0},{"kode":"A.1.4","nama":"Kas Toko","saldo":0},{"kode":"A.1.5","nama":"Piutang Pinjaman Anggota","saldo":0}]},"A2":{"nama_kategori":"A.2. Aset Tetap","items":[{"kode":"A.2.1","nama":"Inventaris","saldo":0},{"kode":"A.2.2","nama":"Akumulasi Penyusutan Inventaris","saldo":0,"is_kontra":true}]},"B1":{"nama_kategori":"B.1. Kewajiban Lancar","items":[{"kode":"B.1.1","nama":"Beban yang akan dibayar","saldo":0},{"kode":"B.1.2","nama":"Manasuka","saldo":0},{"kode":"B.1.3","nama":"Simpanan Pendidikan","saldo":0}]},"B2":{"nama_kategori":"B.2. Dana","items":[{"kode":"B.2.1","nama":"Pendidikan","saldo":0},{"kode":"B.2.2","nama":"Pengurus","saldo":0},{"kode":"B.2.3","nama":"Kesejahteraan Pegawai","saldo":0},{"kode":"B.2.4","nama":"Sosial","saldo":0},{"kode":"B.2.5","nama":"Pemdaker","saldo":0}]},"B3":{"nama_kategori":"B.3. Ekuitas/Modal","items":[{"kode":"B.3.1","nama":"Simpanan Pokok","saldo":0},{"kode":"B.3.2","nama":"Simpanan Wajib","saldo":0},{"kode":"B.3.3","nama":"Seragam","saldo":0}]},"B4":{"nama_kategori":"B.4. SHU","items":[{"kode":"B.4.1","nama":"SHU Tahun Sebelumnya","saldo":0},{"kode":"B.4.2","nama":"SHU Tahun Berjalan","saldo":0}]}}'),
('akun_labarugi', '{"pendapatan":[{"kode":"A.1","nama":"Pendapatan Bunga Pinjaman"},{"kode":"A.2","nama":"Pendapatan Penjualan Produk"},{"kode":"A.3","nama":"Pendapatan Penjualan Jasa"},{"kode":"A.4","nama":"Pendapatan Bunga Bank"},{"kode":"A.5","nama":"Pendapatan Lain-Lain"}],"pengeluaran":[{"kode":"B.1","nama":"Jasa Simpanan Sukarela"},{"kode":"B.2","nama":"Jasa Bank"},{"kode":"B.3","nama":"Beban Asuransi"},{"kode":"B.4","nama":"Beban Audit"},{"kode":"B.5","nama":"Beban Pajak"},{"kode":"B.6","nama":"Beban Rapat"},{"kode":"B.7","nama":"Beban Perjalanan Dinas"},{"kode":"B.8","nama":"Beban Pelatihan"},{"kode":"B.9","nama":"Beban Honor Pengurus"},{"kode":"B.10","nama":"Beban Organisasi"},{"kode":"B.11","nama":"Beban Gaji Karyawan"},{"kode":"B.12","nama":"Beban Konsumsi"},{"kode":"B.13","nama":"Beban ATK"},{"kode":"B.14","nama":"Beban Listrik, Telepon dan Air"},{"kode":"B.15","nama":"Beban Internet"},{"kode":"B.16","nama":"Beban Ongkos Kirim"},{"kode":"B.17","nama":"Beban Perbaikan dan Pemeliharaan"},{"kode":"B.18","nama":"Beban Operasional"},{"kode":"B.19","nama":"Beban Sewa"},{"kode":"B.20","nama":"Beban Pembelian Aset"},{"kode":"B.21","nama":"Beban Penyusutan Inventaris"}]}');

-- --------------------------------------------------------
-- Akun Administrator Utama (Password: admin123)
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama_lengkap`) VALUES
(1, 'admin', '$2y$10$p0b3h3tGZ9YqX93.lZtP8e5mYm.C4G9P/Z0c1C7NqLzXF4fCiq40W', 'admin', 'Administrator Utama');

-- --------------------------------------------------------
-- Struktur Tabel `buku_kas` (Kas Umum, Kas Bank, Kas Toko)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `buku_kas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipe_kas` VARCHAR(30) NOT NULL,
  `tanggal` DATE NOT NULL,
  `no_bukti` VARCHAR(50) NOT NULL,
  `keterangan` TEXT NOT NULL,
  `kategori` VARCHAR(150) NOT NULL,
  `jenis_transaksi` VARCHAR(20) NOT NULL,
  `kas_masuk` DECIMAL(15,2) DEFAULT 0,
  `kas_keluar` DECIMAL(15,2) DEFAULT 0,
  `petugas` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_buku_kas_tipe` (`tipe_kas`, `tanggal`),
  INDEX `idx_buku_kas_bukti` (`no_bukti`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
