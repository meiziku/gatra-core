<?php
/**
 * Database Auto-Initializer & Seeder
 * Mendukung MySQL dan SQLite secara transparan
 */

function initialize_database($pdo) {
    // Cek apakah tabel 'users' sudah ada
    try {
        $check = $pdo->query("SELECT 1 FROM users LIMIT 1");
        if ($check !== false) {
            // Pastikan kolom tanggal_keluar sudah ada di tabel anggota
            try {
                $pdo->exec("ALTER TABLE anggota ADD COLUMN tanggal_keluar DATE NULL");
            } catch (Exception $e) {}

            // Pastikan kolom asuransi sudah ada di tabel pinjaman
            try {
                $pdo->exec("ALTER TABLE pinjaman ADD COLUMN asuransi DECIMAL(15,2) DEFAULT 0");
            } catch (Exception $e) {}

            // Pastikan tabel pengaturan tersedia dan terisi
            ensure_pengaturan_table($pdo);

            // Pastikan tabel simpanan pendidikan tersedia
            ensure_simpanan_pendidikan_tables($pdo);

            // Pastikan tabel buku kas (Kas Umum, Kas Bank, Kas Toko) tersedia
            ensure_buku_kas_table($pdo);

            return; // Database sudah terinisialisasi
        }
    } catch (Exception $e) {
        // Tabel belum ada, lanjutkan instalasi
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        // SQL Schema untuk SQLite
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL,
                nama_lengkap VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS anggota (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                no_anggota VARCHAR(30) UNIQUE NOT NULL,
                nama VARCHAR(100) NOT NULL,
                nik VARCHAR(30),
                alamat TEXT,
                no_telp VARCHAR(30),
                pekerjaan VARCHAR(100),
                tanggal_bergabung DATE,
                tanggal_keluar DATE,
                status VARCHAR(20) DEFAULT 'aktif',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS simpanan (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                anggota_id INTEGER NOT NULL,
                jenis_simpanan VARCHAR(30) NOT NULL, -- pokok, wajib, sukarela
                tipe_transaksi VARCHAR(20) NOT NULL, -- setor, tarik
                nominal DECIMAL(15,2) NOT NULL,
                tanggal DATE NOT NULL,
                keterangan VARCHAR(255),
                petugas VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS pinjaman (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                no_pinjaman VARCHAR(40) UNIQUE NOT NULL,
                anggota_id INTEGER NOT NULL,
                jumlah_pinjaman DECIMAL(15,2) NOT NULL,
                bunga_persen DECIMAL(5,2) NOT NULL DEFAULT 1.0, -- misal 1% per bulan
                lama_bulan INTEGER NOT NULL, -- tenor
                angsuran_pokok DECIMAL(15,2) NOT NULL,
                angsuran_bunga DECIMAL(15,2) NOT NULL,
                total_angsuran_bulanan DECIMAL(15,2) NOT NULL,
                total_harus_dibayar DECIMAL(15,2) NOT NULL,
                tanggal_pengajuan DATE NOT NULL,
                tanggal_pencairan DATE,
                status VARCHAR(20) DEFAULT 'berjalan', -- menunggu, disetujui, ditolak, berjalan, lunas
                keterangan TEXT,
                asuransi DECIMAL(15,2) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS angsuran (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pinjaman_id INTEGER NOT NULL,
                angsuran_ke INTEGER NOT NULL,
                jumlah_bayar DECIMAL(15,2) NOT NULL,
                angsuran_pokok DECIMAL(15,2) NOT NULL,
                angsuran_bunga DECIMAL(15,2) NOT NULL,
                denda DECIMAL(15,2) DEFAULT 0,
                tanggal_bayar DATE NOT NULL,
                petugas VARCHAR(100),
                keterangan VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pinjaman_id) REFERENCES pinjaman(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS kas_transaksi (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kategori VARCHAR(20) NOT NULL, -- pemasukan, pengeluaran
                jenis VARCHAR(100) NOT NULL,
                nominal DECIMAL(15,2) NOT NULL,
                tanggal DATE NOT NULL,
                keterangan TEXT,
                petugas VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    } else {
        // Schema untuk MySQL / MariaDB (Rumahweb cPanel)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) UNIQUE NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `role` ENUM('admin', 'anggota') NOT NULL,
                `nama_lengkap` VARCHAR(100) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS `anggota` (
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

            CREATE TABLE IF NOT EXISTS `simpanan` (
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

            CREATE TABLE IF NOT EXISTS `pinjaman` (
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

            CREATE TABLE IF NOT EXISTS `angsuran` (
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

            CREATE TABLE IF NOT EXISTS `kas_transaksi` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `kategori` ENUM('pemasukan', 'pengeluaran') NOT NULL,
                `jenis` VARCHAR(100) NOT NULL,
                `nominal` DECIMAL(15,2) NOT NULL,
                `tanggal` DATE NOT NULL,
                `keterangan` TEXT NULL,
                `petugas` VARCHAR(100) NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    // Seed Data Awal
    seed_initial_data($pdo);
    ensure_pengaturan_table($pdo);
    ensure_simpanan_pendidikan_tables($pdo);
    ensure_buku_kas_table($pdo);
}

function seed_initial_data($pdo) {
    // 1. Akun Admin
    $passAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'admin', ?)");
    $stmt->execute(['admin', $passAdmin, 'Administrator Utama']);

    // 2. Akun Anggota 1: Budi Santoso
    $passBudi = password_hash('budi123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'anggota', ?)");
    $stmt->execute(['budi', $passBudi, 'Budi Santoso']);
    $userBudiId = $pdo->lastInsertId();

    $stmtAnggota = $pdo->prepare("INSERT INTO anggota (user_id, no_anggota, nama, nik, alamat, no_telp, pekerjaan, tanggal_bergabung, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
    $stmtAnggota->execute([
        $userBudiId,
        'KOP-2024-001',
        'Budi Santoso',
        '3274011205850001',
        'Jl. Melati No. 14, Harjamukti, Cirebon',
        '081234567890',
        'Wiraswasta Kuliner',
        '2024-01-15'
    ]);
    $anggotaBudiId = $pdo->lastInsertId();

    // 3. Akun Anggota 2: Siti Aminah
    $passSiti = password_hash('siti123', PASSWORD_DEFAULT);
    $stmt->execute(['siti', $passSiti, 'Siti Aminah']);
    $userSitiId = $pdo->lastInsertId();

    $stmtAnggota->execute([
        $userSitiId,
        'KOP-2024-002',
        'Siti Aminah',
        '3274022508920002',
        'Jl. Pemuda No. 88, Kesambi, Cirebon',
        '085678901234',
        'Guru Sekolah Dasar',
        '2024-02-10'
    ]);
    $anggotaSitiId = $pdo->lastInsertId();

    // 4. Akun Anggota 3: Hendra Wijaya
    $passHendra = password_hash('hendra123', PASSWORD_DEFAULT);
    $stmt->execute(['hendra', $passHendra, 'Hendra Wijaya']);
    $userHendraId = $pdo->lastInsertId();

    $stmtAnggota->execute([
        $userHendraId,
        'KOP-2025-003',
        'Hendra Wijaya',
        '3274031502880003',
        'Jl. Kartini No. 42, Kejaksan, Cirebon',
        '081987654321',
        'Pegawai Swasta',
        '2025-03-01'
    ]);
    $anggotaHendraId = $pdo->lastInsertId();

    // Seed Simpanan Budi
    $stmtSimp = $pdo->prepare("INSERT INTO simpanan (anggota_id, jenis_simpanan, tipe_transaksi, nominal, tanggal, keterangan, petugas) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtSimp->execute([$anggotaBudiId, 'pokok', 'setor', 500000, '2024-01-15', 'Simpanan Pokok Awal', 'Admin']);
    $stmtSimp->execute([$anggotaBudiId, 'wajib', 'setor', 500000, '2024-05-10', 'Simpanan Wajib akumulasi 10 bln', 'Admin']);
    $stmtSimp->execute([$anggotaBudiId, 'sukarela', 'setor', 2500000, '2024-06-01', 'Setoran Sukarela Tabungan Hari Raya', 'Admin']);

    // Seed Simpanan Siti
    $stmtSimp->execute([$anggotaSitiId, 'pokok', 'setor', 500000, '2024-02-10', 'Simpanan Pokok Awal', 'Admin']);
    $stmtSimp->execute([$anggotaSitiId, 'wajib', 'setor', 600000, '2024-07-15', 'Simpanan Wajib 12 bln', 'Admin']);
    $stmtSimp->execute([$anggotaSitiId, 'sukarela', 'setor', 4200000, '2024-08-01', 'Tabungan Berjangka Ringan', 'Admin']);

    // Seed Simpanan Hendra
    $stmtSimp->execute([$anggotaHendraId, 'pokok', 'setor', 500000, '2025-03-01', 'Simpanan Pokok Awal', 'Admin']);
    $stmtSimp->execute([$anggotaHendraId, 'wajib', 'setor', 400000, '2025-07-01', 'Simpanan Wajib 8 bln', 'Admin']);
    $stmtSimp->execute([$anggotaHendraId, 'sukarela', 'setor', 1500000, '2025-08-15', 'Simpanan Sukarela', 'Admin']);

    // Seed Pinjaman Budi Santoso (Rp 12.000.000, 12 Bulan, Bunga 1% = Rp 120.000/bln, Pokok = Rp 1.000.000)
    $stmtPinj = $pdo->prepare("INSERT INTO pinjaman 
        (no_pinjaman, anggota_id, jumlah_pinjaman, bunga_persen, lama_bulan, angsuran_pokok, angsuran_bunga, total_angsuran_bulanan, total_harus_dibayar, tanggal_pengajuan, tanggal_pencairan, status, keterangan) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'berjalan', ?)");
    
    $stmtPinj->execute([
        'PINJ-2026-001',
        $anggotaBudiId,
        12000000,
        1.0,
        12,
        1000000,
        120000,
        1120000,
        13440000,
        '2026-01-05',
        '2026-01-10',
        'Pinjaman Modal Usaha Katering'
    ]);
    $pinjamanBudiId = $pdo->lastInsertId();

    // Seed Angsuran Budi (Sudah bayar 4 kali)
    $stmtAngs = $pdo->prepare("INSERT INTO angsuran (pinjaman_id, angsuran_ke, jumlah_bayar, angsuran_pokok, angsuran_bunga, denda, tanggal_bayar, petugas, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtAngs->execute([$pinjamanBudiId, 1, 1120000, 1000000, 120000, 0, '2026-02-10', 'Kasir Admin', 'Angsuran Bulan ke-1']);
    $stmtAngs->execute([$pinjamanBudiId, 2, 1120000, 1000000, 120000, 0, '2026-03-09', 'Kasir Admin', 'Angsuran Bulan ke-2']);
    $stmtAngs->execute([$pinjamanBudiId, 3, 1120000, 1000000, 120000, 0, '2026-04-10', 'Kasir Admin', 'Angsuran Bulan ke-3']);
    $stmtAngs->execute([$pinjamanBudiId, 4, 1120000, 1000000, 120000, 0, '2026-05-10', 'Kasir Admin', 'Angsuran Bulan ke-4']);

    // Seed Pinjaman Siti Aminah (Rp 5.000.000, 6 Bulan, Bunga 1% = Rp 50.000/bln, Pokok = Rp 833.333, Total = Rp 883.333)
    $stmtPinj->execute([
        'PINJ-2026-002',
        $anggotaSitiId,
        5000000,
        1.0,
        6,
        833333,
        50000,
        883333,
        5300000,
        '2026-02-01',
        '2026-02-05',
        'Pinjaman Renovasi Rumah Ringan'
    ]);
    $pinjamanSitiId = $pdo->lastInsertId();

    // Siti bayar 5 angsuran
    for ($i = 1; $i <= 5; $i++) {
        $bln = str_pad($i + 2, 2, '0', STR_PAD_LEFT);
        $stmtAngs->execute([$pinjamanSitiId, $i, 883333, 833333, 50000, 0, "2026-{$bln}-05", 'Kasir Admin', "Angsuran Bulan ke-{$i}"]);
    }

    // Seed Kas Transaksi
    $stmtKas = $pdo->prepare("INSERT INTO kas_transaksi (kategori, jenis, nominal, tanggal, keterangan, petugas) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtKas->execute(['pemasukan', 'Pendapatan Bunga Pinjaman', 530000, '2026-05-31', 'Akumulasi bunga pinjaman anggota Mei 2026', 'Admin']);
    $stmtKas->execute(['pemasukan', 'Biaya Administrasi Pinjaman', 250000, '2026-01-10', 'Admin pencairan pinjaman', 'Admin']);
    $stmtKas->execute(['pengeluaran', 'Biaya Operasional Kantor', 350000, '2026-05-15', 'Pembelian ATK dan cetak buku tabungan', 'Admin']);
    $stmtKas->execute(['pengeluaran', 'Bagi Hasil / Jasa Simpanan', 420000, '2026-06-01', 'Penyaluran bagi hasil simpanan sukarela', 'Admin']);

    // Pastikan tabel pengaturan diinisialisasi
    ensure_pengaturan_table($pdo);
}

/**
 * Memastikan tabel pengaturan tersedia beserta data default koperasi dan pembagian SHU
 */
function ensure_pengaturan_table($pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    try {
        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan (kunci VARCHAR(100) PRIMARY KEY, nilai TEXT);");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `pengaturan` (`kunci` VARCHAR(100) PRIMARY KEY, `nilai` TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        // 1. Data Koperasi Default
        $stmtCekData = $pdo->prepare("SELECT 1 FROM pengaturan WHERE kunci = 'data_koperasi' LIMIT 1");
        $stmtCekData->execute();
        if (!$stmtCekData->fetchColumn()) {
            $defaultData = json_encode([
                'tahun_buku'     => date('Y'),
                'nama_koperasi'  => 'Koperasi Simpan Pinjam Gatra Teknika',
                'alamat'         => 'Jl. By Pass Sunyaragi No. 45, Cirebon, Jawa Barat 45132',
                'telepon'        => '(0231) 482910 / 0812-3456-7890',
                'email'          => 'kontak@gatrateknika.co.id',
                'ketua'          => 'Drs. H. Mulyadi, M.M.',
                'sekretaris'     => 'Ratna Dewi, S.E.',
                'bendahara'      => 'Arief Budiman, S.Ak.',
                'pengelola_sp'   => 'Bambang Irawan',
                'pengelola_toko' => 'Dewi Lestari'
            ], JSON_UNESCAPED_UNICODE);

            $stmtIns = $pdo->prepare("REPLACE INTO pengaturan (kunci, nilai) VALUES ('data_koperasi', ?)");
            $stmtIns->execute([$defaultData]);
        }

        // 2. Data Pembagian SHU Default
        $stmtCekShu = $pdo->prepare("SELECT 1 FROM pengaturan WHERE kunci = 'pembagian_shu' LIMIT 1");
        $stmtCekShu->execute();
        if (!$stmtCekShu->fetchColumn()) {
            $defaultShu = json_encode([
                ['id' => 1, 'nama' => 'Jasa Modal (Simpanan) Anggota', 'persen' => 25.00, 'locked' => true],
                ['id' => 2, 'nama' => 'Jasa Usaha Anggota - Simpan Pinjam', 'persen' => 25.00, 'locked' => true],
                ['id' => 3, 'nama' => 'Dana Cadangan', 'persen' => 20.00, 'locked' => true],
                ['id' => 4, 'nama' => 'Dana Pemdaker', 'persen' => 5.00, 'locked' => false],
                ['id' => 5, 'nama' => 'Dana Pengurus', 'persen' => 10.00, 'locked' => false],
                ['id' => 6, 'nama' => 'Dana Kesejahteraan Pegawai', 'persen' => 5.00, 'locked' => false],
                ['id' => 7, 'nama' => 'Dana Pendidikan', 'persen' => 5.00, 'locked' => false],
                ['id' => 8, 'nama' => 'Dana Sosial', 'persen' => 5.00, 'locked' => false],
            ], JSON_UNESCAPED_UNICODE);

            $stmtIns = $pdo->prepare("REPLACE INTO pengaturan (kunci, nilai) VALUES ('pembagian_shu', ?)");
            $stmtIns->execute([$defaultShu]);
        }

        // 3. Data Rekening & Saldo Awal Neraca Default
        $stmtCekNeraca = $pdo->prepare("SELECT 1 FROM pengaturan WHERE kunci = 'neraca_saldo' LIMIT 1");
        $stmtCekNeraca->execute();
        if (!$stmtCekNeraca->fetchColumn()) {
            $defaultNeraca = [
                'A1' => [
                    'nama_kategori' => 'A.1. Aset Lancar',
                    'items' => [
                        ['kode' => 'A.1.1', 'nama' => 'Kas Simpan Pinjam', 'saldo' => 0],
                        ['kode' => 'A.1.2', 'nama' => 'Kas Pusat', 'saldo' => 0],
                        ['kode' => 'A.1.3', 'nama' => 'Kas Bank', 'saldo' => 0],
                        ['kode' => 'A.1.4', 'nama' => 'Kas Toko', 'saldo' => 0],
                        ['kode' => 'A.1.5', 'nama' => 'Piutang Pinjaman Anggota', 'saldo' => 0],
                    ]
                ],
                'A2' => [
                    'nama_kategori' => 'A.2. Aset Tetap',
                    'items' => [
                        ['kode' => 'A.2.1', 'nama' => 'Inventaris', 'saldo' => 0],
                        ['kode' => 'A.2.2', 'nama' => 'Akumulasi Penyusutan Inventaris', 'saldo' => 0, 'is_kontra' => true],
                    ]
                ],
                'B1' => [
                    'nama_kategori' => 'B.1. Kewajiban Lancar',
                    'items' => [
                        ['kode' => 'B.1.1', 'nama' => 'Beban yang akan dibayar', 'saldo' => 0],
                        ['kode' => 'B.1.2', 'nama' => 'Manasuka', 'saldo' => 0],
                        ['kode' => 'B.1.3', 'nama' => 'Simpanan Pendidikan', 'saldo' => 0],
                    ]
                ],
                'B2' => [
                    'nama_kategori' => 'B.2. Dana',
                    'items' => [
                        ['kode' => 'B.2.1', 'nama' => 'Pendidikan', 'saldo' => 0],
                        ['kode' => 'B.2.2', 'nama' => 'Pengurus', 'saldo' => 0],
                        ['kode' => 'B.2.3', 'nama' => 'Kesejahteraan Pegawai', 'saldo' => 0],
                        ['kode' => 'B.2.4', 'nama' => 'Sosial', 'saldo' => 0],
                        ['kode' => 'B.2.5', 'nama' => 'Pemdaker', 'saldo' => 0],
                    ]
                ],
                'B3' => [
                    'nama_kategori' => 'B.3. Ekuitas/Modal',
                    'items' => [
                        ['kode' => 'B.3.1', 'nama' => 'Simpanan Pokok', 'saldo' => 0],
                        ['kode' => 'B.3.2', 'nama' => 'Simpanan Wajib', 'saldo' => 0],
                        ['kode' => 'B.3.3', 'nama' => 'Seragam', 'saldo' => 0],
                    ]
                ],
                'B4' => [
                    'nama_kategori' => 'B.4. SHU',
                    'items' => [
                        ['kode' => 'B.4.1', 'nama' => 'SHU Tahun Sebelumnya', 'saldo' => 0],
                        ['kode' => 'B.4.2', 'nama' => 'SHU Tahun Berjalan', 'saldo' => 0],
                    ]
                ]
            ];
            $stmtIns = $pdo->prepare("REPLACE INTO pengaturan (kunci, nilai) VALUES ('neraca_saldo', ?)");
            $stmtIns->execute([json_encode($defaultNeraca, JSON_UNESCAPED_UNICODE)]);
        }

        // 4. Data Rekening Pendapatan & Pengeluaran (Laba/Rugi) Default
        $stmtCekLr = $pdo->prepare("SELECT 1 FROM pengaturan WHERE kunci = 'akun_labarugi' LIMIT 1");
        $stmtCekLr->execute();
        if (!$stmtCekLr->fetchColumn()) {
            $defaultLr = [
                'pendapatan' => [
                    ['kode' => 'A.1', 'nama' => 'Pendapatan Bunga Pinjaman'],
                    ['kode' => 'A.2', 'nama' => 'Pendapatan Penjualan Produk'],
                    ['kode' => 'A.3', 'nama' => 'Pendapatan Penjualan Jasa'],
                    ['kode' => 'A.4', 'nama' => 'Pendapatan Bunga Bank'],
                    ['kode' => 'A.5', 'nama' => 'Pendapatan Lain-Lain'],
                ],
                'pengeluaran' => [
                    ['kode' => 'B.1', 'nama' => 'Jasa Simpanan Sukarela'],
                    ['kode' => 'B.2', 'nama' => 'Jasa Bank'],
                    ['kode' => 'B.3', 'nama' => 'Beban Asuransi'],
                    ['kode' => 'B.4', 'nama' => 'Beban Audit'],
                    ['kode' => 'B.5', 'nama' => 'Beban Pajak'],
                    ['kode' => 'B.6', 'nama' => 'Beban Rapat'],
                    ['kode' => 'B.7', 'nama' => 'Beban Perjalanan Dinas'],
                    ['kode' => 'B.8', 'nama' => 'Beban Pelatihan'],
                    ['kode' => 'B.9', 'nama' => 'Beban Honor Pengurus'],
                    ['kode' => 'B.10', 'nama' => 'Beban Organisasi'],
                    ['kode' => 'B.11', 'nama' => 'Beban Gaji Karyawan'],
                    ['kode' => 'B.12', 'nama' => 'Beban Konsumsi'],
                    ['kode' => 'B.13', 'nama' => 'Beban ATK'],
                    ['kode' => 'B.14', 'nama' => 'Beban Listrik, Telepon dan Air'],
                    ['kode' => 'B.15', 'nama' => 'Beban Internet'],
                    ['kode' => 'B.16', 'nama' => 'Beban Ongkos Kirim'],
                    ['kode' => 'B.17', 'nama' => 'Beban Perbaikan dan Pemeliharaan'],
                    ['kode' => 'B.18', 'nama' => 'Beban Operasional'],
                    ['kode' => 'B.19', 'nama' => 'Beban Sewa'],
                    ['kode' => 'B.20', 'nama' => 'Beban Pembelian Aset'],
                    ['kode' => 'B.21', 'nama' => 'Beban Penyusutan Inventaris'],
                ]
            ];
            $stmtIns = $pdo->prepare("REPLACE INTO pengaturan (kunci, nilai) VALUES ('akun_labarugi', ?)");
            $stmtIns->execute([json_encode($defaultLr, JSON_UNESCAPED_UNICODE)]);
        }
    } catch (Exception $e) {}
}

/**
 * Memastikan tabel simpanan pendidikan dan setoran tersedia
 */
function ensure_simpanan_pendidikan_tables($pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    try {
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS simpanan_pendidikan (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    no_rekening VARCHAR(40) UNIQUE NOT NULL,
                    anggota_id INTEGER NOT NULL,
                    target_dana DECIMAL(15,2) NOT NULL,
                    tenor_bulan INTEGER NOT NULL,
                    setoran_bulanan DECIMAL(15,2) NOT NULL,
                    tanggal_mulai DATE NOT NULL,
                    tanggal_jatuh_tempo DATE,
                    status VARCHAR(20) DEFAULT 'aktif',
                    keterangan TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS simpanan_pendidikan_setoran (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    simpanan_pendidikan_id INTEGER NOT NULL,
                    setoran_ke INTEGER NOT NULL,
                    nominal DECIMAL(15,2) NOT NULL,
                    tanggal_bayar DATE NOT NULL,
                    keterangan VARCHAR(50) DEFAULT 'Langsung',
                    petugas VARCHAR(100),
                    catatan VARCHAR(255),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (simpanan_pendidikan_id) REFERENCES simpanan_pendidikan(id) ON DELETE CASCADE
                );
            ");
        } else {
            $pdo->exec("
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
            ");
        }

        // Seed 1 contoh data pendaftaran jika tabel masih kosong
        $cek = $pdo->query("SELECT COUNT(*) FROM simpanan_pendidikan")->fetchColumn();
        if ($cek == 0) {
            $anggotaSiti = $pdo->query("SELECT id FROM anggota WHERE nama LIKE '%Siti%' LIMIT 1")->fetchColumn();
            if ($anggotaSiti) {
                $tglMulai = '2026-01-15';
                $tglJatuhTempo = date('Y-m-d', strtotime('+36 month', strtotime($tglMulai)));
                
                $stmtInsSP = $pdo->prepare("INSERT INTO simpanan_pendidikan 
                    (no_rekening, anggota_id, target_dana, tenor_bulan, setoran_bulanan, tanggal_mulai, tanggal_jatuh_tempo, status, keterangan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif', ?)");
                $stmtInsSP->execute([
                    'SP-PEND-2026-001',
                    $anggotaSiti,
                    10000000,
                    36,
                    248700,
                    $tglMulai,
                    $tglJatuhTempo,
                    'Simpanan persiapan masuk SMA anak'
                ]);
                $spId = $pdo->lastInsertId();

                // Tambahkan 3 bulan setoran contoh
                $stmtSetor = $pdo->prepare("INSERT INTO simpanan_pendidikan_setoran 
                    (simpanan_pendidikan_id, setoran_ke, nominal, tanggal_bayar, keterangan, petugas) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmtSetor->execute([$spId, 1, 248700, '2026-01-15', 'Gaji', 'Admin']);
                $stmtSetor->execute([$spId, 2, 248700, '2026-02-15', 'Gaji', 'Admin']);
                $stmtSetor->execute([$spId, 3, 248700, '2026-03-15', 'Gaji', 'Admin']);
            }
        }
    } catch (Exception $e) {}
}

/**
 * Memastikan tabel buku kas (Kas Umum, Kas Bank, Kas Toko) tersedia
 */
function ensure_buku_kas_table($pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    try {
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS buku_kas (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tipe_kas VARCHAR(30) NOT NULL, -- 'kas_umum', 'kas_bank', 'kas_toko'
                    tanggal DATE NOT NULL,
                    no_bukti VARCHAR(50) NOT NULL,
                    keterangan TEXT NOT NULL,
                    kategori VARCHAR(150) NOT NULL,
                    jenis_transaksi VARCHAR(20) NOT NULL, -- 'masuk', 'keluar'
                    kas_masuk DECIMAL(15,2) DEFAULT 0,
                    kas_keluar DECIMAL(15,2) DEFAULT 0,
                    petugas VARCHAR(100) NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_buku_kas_tipe ON buku_kas(tipe_kas, tanggal);
            ");
        } else {
            $pdo->exec("
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
            ");
        }

        // Seed data awal jika tabel masih kosong
        $cek = $pdo->query("SELECT COUNT(*) FROM buku_kas")->fetchColumn();
        if ($cek == 0) {
            $sampleKas = [
                // Kas Umum
                [
                    'tipe_kas' => 'kas_umum',
                    'tanggal' => '2026-01-05',
                    'no_bukti' => 'BKM-KU-2026-001',
                    'keterangan' => 'Pengisian kas tunai operasional awal tahun',
                    'kategori' => '[A.1.2] Kas Pusat',
                    'jenis_transaksi' => 'masuk',
                    'kas_masuk' => 5000000,
                    'kas_keluar' => 0,
                    'petugas' => 'Admin'
                ],
                [
                    'tipe_kas' => 'kas_umum',
                    'tanggal' => '2026-01-20',
                    'no_bukti' => 'BKK-KU-2026-001',
                    'keterangan' => 'Pembelian kertas HVS, ordner, dan ATK kantor',
                    'kategori' => '[B.13] Beban ATK',
                    'jenis_transaksi' => 'keluar',
                    'kas_masuk' => 0,
                    'kas_keluar' => 350000,
                    'petugas' => 'Admin'
                ],
                [
                    'tipe_kas' => 'kas_umum',
                    'tanggal' => '2026-02-15',
                    'no_bukti' => 'BKK-KU-2026-002',
                    'keterangan' => 'Konsumsi rapat koordinasi bulanan pengurus',
                    'kategori' => '[B.12] Beban Konsumsi',
                    'jenis_transaksi' => 'keluar',
                    'kas_masuk' => 0,
                    'kas_keluar' => 250000,
                    'petugas' => 'Admin'
                ],

                // Kas Bank
                [
                    'tipe_kas' => 'kas_bank',
                    'tanggal' => '2026-01-02',
                    'no_bukti' => 'BKM-KB-2026-001',
                    'keterangan' => 'Penempatan dana rekening giro operasional bank',
                    'kategori' => '[A.1.3] Kas Bank',
                    'jenis_transaksi' => 'masuk',
                    'kas_masuk' => 25000000,
                    'kas_keluar' => 0,
                    'petugas' => 'Admin'
                ],
                [
                    'tipe_kas' => 'kas_bank',
                    'tanggal' => '2026-01-31',
                    'no_bukti' => 'BKM-KB-2026-002',
                    'keterangan' => 'Pendapatan bagi hasil / bunga giro bank Januari 2026',
                    'kategori' => '[A.4] Pendapatan Bunga Bank',
                    'jenis_transaksi' => 'masuk',
                    'kas_masuk' => 275000,
                    'kas_keluar' => 0,
                    'petugas' => 'Admin'
                ],
                [
                    'tipe_kas' => 'kas_bank',
                    'tanggal' => '2026-01-31',
                    'no_bukti' => 'BKK-KB-2026-001',
                    'keterangan' => 'Biaya administrasi bulanan pengelolaan rekening bank',
                    'kategori' => '[B.2] Jasa Bank',
                    'jenis_transaksi' => 'keluar',
                    'kas_masuk' => 0,
                    'kas_keluar' => 25000,
                    'petugas' => 'Admin'
                ],

                // Kas Toko
                [
                    'tipe_kas' => 'kas_toko',
                    'tanggal' => '2026-01-03',
                    'no_bukti' => 'BKM-KT-2026-001',
                    'keterangan' => 'Modal awal kas laci kasir unit toko / waserda',
                    'kategori' => '[A.1.4] Kas Toko',
                    'jenis_transaksi' => 'masuk',
                    'kas_masuk' => 2000000,
                    'kas_keluar' => 0,
                    'petugas' => 'Admin'
                ],
                [
                    'tipe_kas' => 'kas_toko',
                    'tanggal' => '2026-01-15',
                    'no_bukti' => 'BKM-KT-2026-002',
                    'keterangan' => 'Penerimaan hasil penjualan sembako dan barang toko',
                    'kategori' => '[A.2] Pendapatan Penjualan Produk',
                    'jenis_transaksi' => 'masuk',
                    'kas_masuk' => 3850000,
                    'kas_keluar' => 0,
                    'petugas' => 'Admin'
                ],
                [
                    'tipe_kas' => 'kas_toko',
                    'tanggal' => '2026-01-25',
                    'no_bukti' => 'BKK-KT-2026-001',
                    'keterangan' => 'Kulakan tambahan stok barang dagangan toko',
                    'kategori' => '[B.18] Beban Operasional',
                    'jenis_transaksi' => 'keluar',
                    'kas_masuk' => 0,
                    'kas_keluar' => 1500000,
                    'petugas' => 'Admin'
                ],
            ];

            $stmtInsKas = $pdo->prepare("INSERT INTO buku_kas 
                (tipe_kas, tanggal, no_bukti, keterangan, kategori, jenis_transaksi, kas_masuk, kas_keluar, petugas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($sampleKas as $sk) {
                $stmtInsKas->execute([
                    $sk['tipe_kas'],
                    $sk['tanggal'],
                    $sk['no_bukti'],
                    $sk['keterangan'],
                    $sk['kategori'],
                    $sk['jenis_transaksi'],
                    $sk['kas_masuk'],
                    $sk['kas_keluar'],
                    $sk['petugas']
                ]);
            }
        }
    } catch (Exception $e) {}
}
