# Panduan Lengkap Upload & Hosting Website Koperasi di Rumahweb (Paket Medium)

Website Koperasi **KSP GATRA MANDIRI** ini dirancang secara khusus agar 100% kompatibel dan langsung siap dijalankan di paket **Hosting Medium Rumahweb** (cPanel / MariaDB / PHP 8.x).

---

## Spesifikasi Paket Hosting Anda (Rumahweb Medium)
Sesuai paket yang Anda pilih:
- **Penyimpanan:** Unlimited SSD Storage
- **Database:** Unlimited MariaDB Databases
- **Web Server:** CloudLinux / Apache LiteSpeed
- **PHP Version:** PHP 8.1 - 8.3 didukung penuh
- **SSL:** Gratis Let's Encrypt (HTTPS otomatis)
- **Domain:** Gratis Domain bawaan paket

---

## Langkah 1: Buat Database MariaDB di cPanel Rumahweb

1. Login ke panel hosting Anda: `https://namadomainanda.com/cpanel` (atau via ClientZone Rumahweb).
2. Cari menu **MySQL® Databases** (di bagian Databases).
3. Buat database baru:
   - Masukkan nama database, misalnya: `koperasi` (nama lengkap menjadi: `usernamecpanel_koperasi`).
   - Klik **Create Database**.
4. Buat user database:
   - Di bagian *Add New User*, buat username (misal: `admin_kop`) dan kata sandi yang kuat.
   - Klik **Create User**.
5. Hubungkan User ke Database:
   - Di bagian *Add User To Database*, pilih user dan database yang baru dibuat.
   - Klik **Add**.
   - Centang **ALL PRIVILEGES** (Semua hak akses).
   - Klik **Make Changes**.

---

## Langkah 2: Impor Data Awal (`database.sql`) via phpMyAdmin

1. Kembali ke halaman utama cPanel, klik menu **phpMyAdmin**.
2. Di sebelah kiri, klik nama database Anda (`usernamecpanel_koperasi`).
3. Klik tab **Import** di bagian atas.
4. Klik tombol **Choose File** (Pilih File) dan pilih file `database.sql` yang ada di folder proyek ini.
5. Gulir ke bawah dan klik tombol **Import / Go**.
6. Selesai! Semua tabel (`users`, `anggota`, `simpanan`, `pinjaman`, `angsuran`, `kas_transaksi`) dan data contoh awal telah terpasang.

---

## Langkah 3: Upload File Website ke `public_html`

1. Di komputer lokal Anda, kompres semua file dan folder proyek ini ke dalam format `.zip` (misal: `koperasi.zip`).
   - *Catatan: Pastikan file `index.php`, `login.php`, folder `admin/`, `anggota/`, `config/`, `assets/` ada di dalam zip.*
2. Buka cPanel Rumahweb &rarr; klik **File Manager**.
3. Buka folder **`public_html`**.
4. Klik tombol **Upload** di bagian atas, lalu pilih file `koperasi.zip`.
5. Setelah upload selesai (indikator hijau 100%), kembali ke File Manager dan klik kanan pada `koperasi.zip` lalu pilih **Extract**.
6. Hapus file `koperasi.zip` agar direktori tetap rapi.

---

## Langkah 4: Sesuaikan Konfigurasi Database (`config/database.php`)

1. Di dalam File Manager cPanel, buka folder **`config`**.
2. Klik kanan pada file **`database.php`**, pilih **Edit**.
3. Ubah bagian konfigurasi sesuai database yang dibuat pada Langkah 1:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'usernamecpanel_admin_kop');  // Sesuaikan user cPanel Anda
   define('DB_PASS', 'PasswordDatabaseAnda123');   // Sesuaikan password database Anda
   define('DB_NAME', 'usernamecpanel_koperasi');   // Sesuaikan nama database Anda
   define('DB_DRIVER_MODE', 'mysql');              // Pakai driver MySQL MariaDB
   ```
4. Klik **Save Changes**.

---

## Langkah 5: Akses & Uji Coba Website Anda

Buka browser Anda dan kunjungi domain Anda:
```
https://namadomainanda.com/
```
Website akan otomatis mengarahkan ke halaman login yang bersih dan modern.

### Akun Uji Coba Bawaan:
1. **Pengurus / Admin Koperasi:**
   - **Username:** `admin`
   - **Password:** `admin123`
   - *Akses:* Dashboard statistik, input simpanan, input pinjaman baru, input angsuran pelunasan, input buku kas, dan cetak laporan.

2. **Anggota 1 (Budi Santoso - Ada pinjaman & simpanan):**
   - **Username:** `budi`
   - **Password:** `budi123`
   - *Akses:* Melihat kartu anggota digital, total simpanan (Rp 3.500.000), sisa pinjaman (Rp 8.960.000), jadwal tagihan bulanan.

3. **Anggota 2 (Siti Aminah - Simpanan tinggi & pinjaman hampir lunas):**
   - **Username:** `siti`
   - **Password:** `siti123`

4. **Anggota 3 (Hendra Wijaya - Bebas pinjaman):**
   - **Username:** `hendra`
   - **Password:** `hendra123`

---

## Tips Tambahan:
- **Aktivasi SSL Gratis:** Di cPanel Rumahweb, cari menu **SSL/TLS Status**, centang domain Anda dan klik **Run AutoSSL** agar website bertanda gembok aman (`https://`).
- **Backup Rutin:** Manfaatkan fitur cPanel Backup atau fitur Weekly Offshore Backup Rumahweb yang sudah termasuk dalam paket Medium Anda.
