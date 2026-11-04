<?php
/**
 * Pengaturan Sistem & Data Koperasi - Admin Gatra Teknika
 * Tab 1: Data Koperasi
 * Tab 2: Pembagian SHU
 * Tab 3: Neraca (Kode, Rekening, Saldo Awal)
 * Tab 4: Laba / Rugi (Kode, Rekening Pendapatan & Pengeluaran)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_admin();

$tab = $_GET['tab'] ?? 'koperasi';
if (!in_array($tab, ['koperasi', 'shu', 'neraca', 'labarugi', 'backup'])) {
    $tab = 'koperasi';
}

// =========================================================================
// 1. HANDLE POST: DATA KOPERASI
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_koperasi') {
    $dataKoperasi = [
        'tahun_buku'     => trim($_POST['tahun_buku'] ?? date('Y')),
        'nama_koperasi'  => trim($_POST['nama_koperasi'] ?? ''),
        'alamat'         => trim($_POST['alamat'] ?? ''),
        'telepon'        => trim($_POST['telepon'] ?? ''),
        'email'          => trim($_POST['email'] ?? ''),
        'ketua'          => trim($_POST['ketua'] ?? ''),
        'sekretaris'     => trim($_POST['sekretaris'] ?? ''),
        'bendahara'      => trim($_POST['bendahara'] ?? ''),
        'pengelola_sp'   => trim($_POST['pengelola_sp'] ?? ''),
        'pengelola_toko' => trim($_POST['pengelola_toko'] ?? ''),
    ];

    set_setting('data_koperasi', json_encode($dataKoperasi, JSON_UNESCAPED_UNICODE));
    set_flash('success', 'Data Identitas Koperasi berhasil disimpan dan diperbarui.');
    redirect('pengaturan.php?tab=koperasi');
}

// =========================================================================
// 2. HANDLE POST: PEMBAGIAN SHU (PERSENTASE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_shu') {
    $namaList   = $_POST['shu_nama'] ?? [];
    $persenList = $_POST['shu_persen'] ?? [];
    $lockedList = $_POST['shu_locked'] ?? [];

    $shuData = [];
    $totalPersen = 0;

    foreach ($namaList as $i => $nama) {
        $cleanNama = trim($nama);
        if ($cleanNama === '') continue;

        // Bersihkan format persentase (mengubah koma jadi titik untuk parsing numerik)
        $rawPersen = str_replace(['%', ' '], '', $persenList[$i] ?? '0');
        $rawPersen = str_replace(',', '.', $rawPersen);
        $floatPersen = round((float)$rawPersen, 2);

        $rowNum = $i + 1;
        // Pos No. 1, 2, dan 3 terkunci wajib (tidak boleh dihapus)
        $isLocked = ($rowNum === 1 || $rowNum === 2 || $rowNum === 3) || !empty($lockedList[$i]);

        $shuData[] = [
            'id'     => $rowNum,
            'nama'   => $cleanNama,
            'persen' => $floatPersen,
            'locked' => $isLocked
        ];

        $totalPersen += $floatPersen;
    }

    set_setting('pembagian_shu', json_encode($shuData, JSON_UNESCAPED_UNICODE));
    
    $totalFormatted = number_format($totalPersen, 2, ',', '.');
    if (abs($totalPersen - 100.0) < 0.001) {
        set_flash('success', "Ketentuan Pembagian SHU berhasil disimpan. Total alokasi seimbang: <strong>{$totalFormatted}%</strong>");
    } else {
        set_flash('warning', "Ketentuan Pembagian SHU disimpan. Perhatian: Total alokasi saat ini <strong>{$totalFormatted}%</strong> (disarankan berjumlah tepat 100,00%).");
    }
    
    redirect('pengaturan.php?tab=shu');
}

// =========================================================================
// 3. HANDLE POST: NERACA & SALDO AWAL
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_neraca') {
    $catKeys = ['A1', 'A2', 'B1', 'B2', 'B3', 'B4'];
    $catNames = [
        'A1' => 'A.1. Aset Lancar',
        'A2' => 'A.2. Aset Tetap',
        'B1' => 'B.1. Kewajiban Lancar',
        'B2' => 'B.2. Dana',
        'B3' => 'B.3. Ekuitas/Modal',
        'B4' => 'B.4. SHU',
    ];

    $neracaData = [];
    $totalAset = 0;
    $totalPasiva = 0;

    foreach ($catKeys as $ck) {
        $items = [];
        $kodes   = $_POST['neraca'][$ck]['kode'] ?? [];
        $namas   = $_POST['neraca'][$ck]['nama'] ?? [];
        $saldos  = $_POST['neraca'][$ck]['saldo'] ?? [];
        $kontras = $_POST['neraca'][$ck]['kontra'] ?? [];

        foreach ($kodes as $idx => $kd) {
            $cleanKd   = trim($kd);
            $cleanNama = trim($namas[$idx] ?? '');
            if ($cleanKd === '' && $cleanNama === '') continue;

            $rawSaldo = str_replace(['Rp', '.', ' '], '', $saldos[$idx] ?? '0');
            $rawSaldo = str_replace(',', '.', $rawSaldo);
            $numSaldo = (float)$rawSaldo;

            $isKontra = !empty($kontras[$idx]);

            $items[] = [
                'kode'      => $cleanKd,
                'nama'      => $cleanNama,
                'saldo'     => $numSaldo,
                'is_kontra' => $isKontra
            ];

            // Akumulasi hitungan aktiva vs pasiva
            if ($ck === 'A1') {
                $totalAset += $numSaldo;
            } elseif ($ck === 'A2') {
                if ($isKontra) {
                    $totalAset -= $numSaldo;
                } else {
                    $totalAset += $numSaldo;
                }
            } else {
                $totalPasiva += $numSaldo;
            }
        }

        $neracaData[$ck] = [
            'nama_kategori' => $catNames[$ck],
            'items'         => $items
        ];
    }

    set_setting('neraca_saldo', json_encode($neracaData, JSON_UNESCAPED_UNICODE));

    $selisih = abs($totalAset - $totalPasiva);
    if ($selisih < 0.01) {
        set_flash('success', 'Kode Akun dan Saldo Awal Neraca berhasil disimpan. Status: <strong>Neraca Seimbang (Balance)</strong>.');
    } else {
        $formattedSelisih = format_rupiah($selisih);
        set_flash('warning', "Data Saldo Awal Neraca disimpan. Perhatian: <strong>Aktiva dan Pasiva belum seimbang</strong> (Selisih: {$formattedSelisih}).");
    }

    redirect('pengaturan.php?tab=neraca');
}

// =========================================================================
// 4. HANDLE POST: REKENING LABA / RUGI
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_labarugi') {
    $pendapatanKode = $_POST['pendapatan_kode'] ?? [];
    $pendapatanNama = $_POST['pendapatan_nama'] ?? [];

    $pengeluaranKode = $_POST['pengeluaran_kode'] ?? [];
    $pengeluaranNama = $_POST['pengeluaran_nama'] ?? [];

    $listPendapatan = [];
    foreach ($pendapatanKode as $idx => $kd) {
        $cleanKd   = trim($kd);
        $cleanNama = trim($pendapatanNama[$idx] ?? '');
        if ($cleanKd === '' && $cleanNama === '') continue;

        $listPendapatan[] = [
            'kode' => $cleanKd,
            'nama' => $cleanNama
        ];
    }

    $listPengeluaran = [];
    foreach ($pengeluaranKode as $idx => $kd) {
        $cleanKd   = trim($kd);
        $cleanNama = trim($pengeluaranNama[$idx] ?? '');
        if ($cleanKd === '' && $cleanNama === '') continue;

        $listPengeluaran[] = [
            'kode' => $cleanKd,
            'nama' => $cleanNama
        ];
    }

    $lrData = [
        'pendapatan'  => $listPendapatan,
        'pengeluaran' => $listPengeluaran
    ];

    set_setting('akun_labarugi', json_encode($lrData, JSON_UNESCAPED_UNICODE));
    set_flash('success', 'Daftar Kode dan Rekening Pendapatan serta Pengeluaran (Laba/Rugi) berhasil disimpan.');
    redirect('pengaturan.php?tab=labarugi');
}

// =========================================================================
// LOAD DATA DARI DATABASE (DENGAN FALLBACK DEFAULT RESMI)
// =========================================================================

// A. Data Koperasi
$defaultKoperasi = [
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
];
$rawKoperasi = get_setting('data_koperasi');
$koperasi = $rawKoperasi ? json_decode($rawKoperasi, true) : $defaultKoperasi;
if (!is_array($koperasi)) $koperasi = $defaultKoperasi;

// B. Data Pembagian SHU (Pos 1, 2, dan 3 Wajib Terkunci)
$defaultShu = [
    ['id' => 1, 'nama' => 'Jasa Modal (Simpanan) Anggota', 'persen' => 25.00, 'locked' => true],
    ['id' => 2, 'nama' => 'Jasa Usaha Anggota - Simpan Pinjam', 'persen' => 25.00, 'locked' => true],
    ['id' => 3, 'nama' => 'Dana Cadangan', 'persen' => 20.00, 'locked' => true],
    ['id' => 4, 'nama' => 'Dana Pemdaker', 'persen' => 5.00, 'locked' => false],
    ['id' => 5, 'nama' => 'Dana Pengurus', 'persen' => 10.00, 'locked' => false],
    ['id' => 6, 'nama' => 'Dana Kesejahteraan Pegawai', 'persen' => 5.00, 'locked' => false],
    ['id' => 7, 'nama' => 'Dana Pendidikan', 'persen' => 5.00, 'locked' => false],
    ['id' => 8, 'nama' => 'Dana Sosial', 'persen' => 5.00, 'locked' => false],
];
$rawShu = get_setting('pembagian_shu');
$shuList = $rawShu ? json_decode($rawShu, true) : $defaultShu;
if (!is_array($shuList) || empty($shuList)) $shuList = $defaultShu;

$totalShuSaatIni = 0;
foreach ($shuList as $item) {
    $totalShuSaatIni += (float)($item['persen'] ?? 0);
}

// C. Data Neraca Saldo Awal
$defaultNeraca = [
    'A1' => [
        'nama_kategori' => 'A.1. Aset Lancar',
        'items' => [
            ['kode' => 'A.1.1', 'nama' => 'Kas Simpan Pinjam', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'A.1.2', 'nama' => 'Kas Pusat', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'A.1.3', 'nama' => 'Kas Bank', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'A.1.4', 'nama' => 'Kas Toko', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'A.1.5', 'nama' => 'Piutang Pinjaman Anggota', 'saldo' => 0, 'is_kontra' => false],
        ]
    ],
    'A2' => [
        'nama_kategori' => 'A.2. Aset Tetap',
        'items' => [
            ['kode' => 'A.2.1', 'nama' => 'Inventaris', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'A.2.2', 'nama' => 'Akumulasi Penyusutan Inventaris', 'saldo' => 0, 'is_kontra' => true],
        ]
    ],
    'B1' => [
        'nama_kategori' => 'B.1. Kewajiban Lancar',
        'items' => [
            ['kode' => 'B.1.1', 'nama' => 'Beban yang akan dibayar', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.1.2', 'nama' => 'Manasuka', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.1.3', 'nama' => 'Simpanan Pendidikan', 'saldo' => 0, 'is_kontra' => false],
        ]
    ],
    'B2' => [
        'nama_kategori' => 'B.2. Dana',
        'items' => [
            ['kode' => 'B.2.1', 'nama' => 'Pendidikan', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.2.2', 'nama' => 'Pengurus', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.2.3', 'nama' => 'Kesejahteraan Pegawai', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.2.4', 'nama' => 'Sosial', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.2.5', 'nama' => 'Pemdaker', 'saldo' => 0, 'is_kontra' => false],
        ]
    ],
    'B3' => [
        'nama_kategori' => 'B.3. Ekuitas/Modal',
        'items' => [
            ['kode' => 'B.3.1', 'nama' => 'Simpanan Pokok', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.3.2', 'nama' => 'Simpanan Wajib', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.3.3', 'nama' => 'Seragam', 'saldo' => 0, 'is_kontra' => false],
        ]
    ],
    'B4' => [
        'nama_kategori' => 'B.4. SHU',
        'items' => [
            ['kode' => 'B.4.1', 'nama' => 'SHU Tahun Sebelumnya', 'saldo' => 0, 'is_kontra' => false],
            ['kode' => 'B.4.2', 'nama' => 'SHU Tahun Berjalan', 'saldo' => 0, 'is_kontra' => false],
        ]
    ]
];
$rawNeraca = get_setting('neraca_saldo');
$neraca = $rawNeraca ? json_decode($rawNeraca, true) : $defaultNeraca;
if (!is_array($neraca) || empty($neraca)) $neraca = $defaultNeraca;

// D. Data Laba / Rugi (Pendapatan & Pengeluaran)
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
$rawLr = get_setting('akun_labarugi');
$lr = $rawLr ? json_decode($rawLr, true) : $defaultLr;
if (!is_array($lr) || empty($lr)) $lr = $defaultLr;

// =========================================================================
// 5. HANDLE BACKUP DATA (DOWNLOAD JSON)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $tablesOrder = [
        'users',
        'anggota',
        'pengaturan',
        'buku_kas',
        'kas_transaksi',
        'pinjaman',
        'angsuran',
        'simpanan',
        'simpanan_pendidikan',
        'simpanan_pendidikan_setoran'
    ];

    $currentUser = current_user();
    $backup = [
        'app' => 'Koperasi Gatra Core',
        'version' => '1.0',
        'exported_at' => date('Y-m-d H:i:s'),
        'exported_by' => $currentUser['nama_lengkap'] ?? 'Administrator',
        'tables' => []
    ];

    foreach ($tablesOrder as $table) {
        try {
            $stmt = $db->query("SELECT * FROM $table");
            $backup['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $backup['tables'][$table] = [];
        }
    }

    $filename = 'backup_gatra_koperasi_' . date('Y-m-d_His') . '.json';
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// 6. HANDLE RESTORE DATA (UPLOAD JSON)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_backup') {
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Silakan pilih file cadangan (.json) yang valid untuk diunggah.');
        redirect('pengaturan.php?tab=backup');
    }

    $tmpPath = $_FILES['backup_file']['tmp_name'];
    $rawContent = file_get_contents($tmpPath);
    $backupData = json_decode($rawContent, true);

    if (!is_array($backupData) || !isset($backupData['tables']) || !is_array($backupData['tables'])) {
        set_flash('error', 'Format file cadangan tidak valid atau rusak (struktur data JSON tidak sesuai).');
        redirect('pengaturan.php?tab=backup');
    }

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Urutan penghapusan tabel (anak terlebih dahulu)
    $deleteOrder = [
        'simpanan_pendidikan_setoran',
        'simpanan_pendidikan',
        'angsuran',
        'pinjaman',
        'simpanan',
        'kas_transaksi',
        'buku_kas',
        'pengaturan',
        'anggota',
        'users'
    ];

    // Urutan pemasukan data (induk terlebih dahulu)
    $insertOrder = [
        'users',
        'anggota',
        'pengaturan',
        'buku_kas',
        'kas_transaksi',
        'pinjaman',
        'angsuran',
        'simpanan',
        'simpanan_pendidikan',
        'simpanan_pendidikan_setoran'
    ];

    try {
        $db->beginTransaction();

        // Nonaktifkan pemeriksaan foreign key sementara
        if ($driver === 'sqlite') {
            $db->exec("PRAGMA foreign_keys = OFF;");
        } else {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
        }

        // Hapus data yang ada saat ini
        foreach ($deleteOrder as $table) {
            try {
                $db->exec("DELETE FROM $table");
            } catch (Exception $e) {}
        }

        // Masukkan data dari file cadangan
        $restoredCounts = [];
        foreach ($insertOrder as $table) {
            if (!isset($backupData['tables'][$table]) || !is_array($backupData['tables'][$table])) {
                continue;
            }

            $rows = $backupData['tables'][$table];
            $count = count($rows);
            $restoredCounts[$table] = $count;

            if ($count === 0) continue;

            $cols = array_keys($rows[0]);
            if ($driver === 'sqlite') {
                $colNames = implode(', ', array_map(function($c) { return '"' . str_replace('"', '""', $c) . '"'; }, $cols));
            } else {
                $colNames = implode(', ', array_map(function($c) { return '`' . str_replace('`', '``', $c) . '`'; }, $cols));
            }
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));

            $stmt = $db->prepare("INSERT INTO $table ($colNames) VALUES ($placeholders)");
            foreach ($rows as $row) {
                $stmt->execute(array_values($row));
            }

            // Sesuaikan sequence untuk SQLite
            if ($driver === 'sqlite') {
                try {
                    $maxId = (int)$db->query("SELECT MAX(id) FROM $table")->fetchColumn();
                    if ($maxId > 0) {
                        $db->prepare("REPLACE INTO sqlite_sequence (name, seq) VALUES (?, ?)")->execute([$table, $maxId]);
                    }
                } catch (Exception $e) {}
            }
        }

        // Pastikan minimal akun admin tetap ada jika users kosong
        $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount === 0) {
            $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES ('admin', ?, 'admin', 'Administrator Koperasi')")->execute([$defaultPass]);
        }

        // Aktifkan kembali foreign key
        if ($driver === 'sqlite') {
            $db->exec("PRAGMA foreign_keys = ON;");
        } else {
            $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }

        $db->commit();

        $totalRestored = array_sum($restoredCounts);
        set_flash('success', "Proses Restore berhasil! Total <strong>{$totalRestored}</strong> baris data dari " . count($restoredCounts) . " tabel berhasil dipulihkan.");
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        try {
            if ($driver === 'sqlite') {
                $db->exec("PRAGMA foreign_keys = ON;");
            } else {
                $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }
        } catch (Exception $ex) {}

        set_flash('error', 'Gagal memulihkan data: ' . $e->getMessage());
    }

    redirect('pengaturan.php?tab=backup');
}

// Statistik Data untuk Tab Backup
$tableStats = [
    'users'                       => ['label' => 'Akun Pengguna & Admin', 'icon' => '👤', 'count' => 0],
    'anggota'                     => ['label' => 'Data Anggota Koperasi', 'icon' => '👥', 'count' => 0],
    'simpanan'                    => ['label' => 'Transaksi Simpanan (Pokok, Wajib, Sukarela)', 'icon' => '💰', 'count' => 0],
    'pinjaman'                    => ['label' => 'Data Pinjaman Anggota', 'icon' => '📋', 'count' => 0],
    'angsuran'                    => ['label' => 'Riwayat Pembayaran Angsuran', 'icon' => '💳', 'count' => 0],
    'buku_kas'                    => ['label' => 'Buku Kas (Umum, Bank, Toko & Mutasi)', 'icon' => '📖', 'count' => 0],
    'kas_transaksi'               => ['label' => 'Kas SP Transaksi Operasional', 'icon' => '🏷️', 'count' => 0],
    'simpanan_pendidikan'         => ['label' => 'Rekening Simpanan Pendidikan', 'icon' => '🎓', 'count' => 0],
    'simpanan_pendidikan_setoran' => ['label' => 'Setoran Simpanan Pendidikan', 'icon' => '📥', 'count' => 0],
    'pengaturan'                  => ['label' => 'Konfigurasi Sistem, SHU & Rekening', 'icon' => '⚙️', 'count' => 0],
];
if ($tab === 'backup') {
    foreach ($tableStats as $tbl => &$info) {
        try {
            $info['count'] = (int)$db->query("SELECT COUNT(*) FROM $tbl")->fetchColumn();
        } catch (Exception $e) {
            $info['count'] = 0;
        }
    }
    unset($info);
}

$pageTitle = 'Pengaturan Koperasi';
$pageSubtitle = 'Kelola data identitas, pembagian SHU, neraca saldo awal, serta rekening laba/rugi';
$activePage = 'pengaturan';

require_once __DIR__ . '/header.php';
?>

<!-- =========================================================================
     TAB NAVIGATION BAR
     ========================================================================= -->
<div class="card" style="margin-bottom: 24px;">
  <div class="card-body" style="padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <!-- Tab 1: Data Koperasi -->
      <a href="pengaturan.php?tab=koperasi" class="btn <?= $tab === 'koperasi' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 9px 18px; font-weight: 600;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        Data Koperasi
      </a>

      <!-- Tab 2: Pembagian SHU -->
      <a href="pengaturan.php?tab=shu" class="btn <?= $tab === 'shu' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 9px 18px; font-weight: 600;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Pembagian SHU
      </a>

      <!-- Tab 3: Neraca Saldo Awal -->
      <a href="pengaturan.php?tab=neraca" class="btn <?= $tab === 'neraca' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 9px 18px; font-weight: 600;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
        Neraca
      </a>

      <!-- Tab 4: Laba / Rugi -->
      <a href="pengaturan.php?tab=labarugi" class="btn <?= $tab === 'labarugi' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 9px 18px; font-weight: 600;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        Laba / Rugi
      </a>

      <!-- Tab 5: Backup & Restore -->
      <a href="pengaturan.php?tab=backup" class="btn <?= $tab === 'backup' ? 'btn-primary' : 'btn-secondary' ?>" style="padding: 9px 18px; font-weight: 600;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
        Backup & Restore
      </a>
    </div>

    <div>
      <span class="badge badge-info" style="font-size: 0.8rem; padding: 6px 12px;">
        Tahun Buku Aktif: <?= htmlspecialchars($koperasi['tahun_buku'] ?? date('Y')) ?>
      </span>
    </div>
  </div>
</div>

<?php if ($tab === 'koperasi'): ?>
  <!-- =========================================================================
       TAB 1: DATA KOPERASI
       ========================================================================= -->
  <div class="card">
    <div class="card-header">
      <div>
        <h3 style="margin-bottom: 2px;">Data Identitas Koperasi</h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">Lengkapi data legalitas instansi, alamat kontak, dan jajaran kepengurusan koperasi</p>
      </div>
    </div>
    <div class="card-body">
      <form method="POST" action="pengaturan.php?tab=koperasi" autocomplete="off">
        <input type="hidden" name="action" value="save_koperasi">

        <!-- Bagian 1: Identitas Lembaga -->
        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--secondary); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px dashed var(--border-color); display: flex; align-items: center; gap: 8px;">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          Identitas & Legalitas Koperasi
        </h4>

        <div class="form-row">
          <div class="form-col form-group" style="flex: 1; max-width: 220px;">
            <label class="form-label">Tahun Buku: *</label>
            <input type="number" name="tahun_buku" class="form-control" value="<?= htmlspecialchars($koperasi['tahun_buku'] ?? date('Y')) ?>" placeholder="Contoh: 2026" required>
          </div>
          <div class="form-col form-group" style="flex: 3;">
            <label class="form-label">Nama Koperasi: *</label>
            <input type="text" name="nama_koperasi" class="form-control" value="<?= htmlspecialchars($koperasi['nama_koperasi'] ?? '') ?>" placeholder="Contoh: Koperasi Simpan Pinjam Gatra Teknika" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat: *</label>
          <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat jalan, gedung, nomor, kota, kode pos" required><?= htmlspecialchars($koperasi['alamat'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Telepon: *</label>
            <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($koperasi['telepon'] ?? '') ?>" placeholder="Contoh: (0231) 482910 / 08123456789" required>
          </div>
          <div class="form-col form-group">
            <label class="form-label">Email: *</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($koperasi['email'] ?? '') ?>" placeholder="Contoh: kontak@gatrateknika.co.id" required>
          </div>
        </div>

        <!-- Bagian 2: Jajaran Pengurus & Pengelola -->
        <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--secondary); margin-top: 24px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px dashed var(--border-color); display: flex; align-items: center; gap: 8px;">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          Susunan Pengurus & Pengelola
        </h4>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Ketua: *</label>
            <input type="text" name="ketua" class="form-control" value="<?= htmlspecialchars($koperasi['ketua'] ?? '') ?>" placeholder="Nama lengkap Ketua Koperasi" required>
          </div>
          <div class="form-col form-group">
            <label class="form-label">Sekretaris: *</label>
            <input type="text" name="sekretaris" class="form-control" value="<?= htmlspecialchars($koperasi['sekretaris'] ?? '') ?>" placeholder="Nama lengkap Sekretaris" required>
          </div>
          <div class="form-col form-group">
            <label class="form-label">Bendahara: *</label>
            <input type="text" name="bendahara" class="form-control" value="<?= htmlspecialchars($koperasi['bendahara'] ?? '') ?>" placeholder="Nama lengkap Bendahara" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Pengelola SP: *</label>
            <input type="text" name="pengelola_sp" class="form-control" value="<?= htmlspecialchars($koperasi['pengelola_sp'] ?? '') ?>" placeholder="Nama Pengelola Unit Simpan Pinjam" required>
          </div>
          <div class="form-col form-group">
            <label class="form-label">Pengelola Toko: *</label>
            <input type="text" name="pengelola_toko" class="form-control" value="<?= htmlspecialchars($koperasi['pengelola_toko'] ?? '') ?>" placeholder="Nama Pengelola Unit Toko" required>
          </div>
        </div>

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
          <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 0.95rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Simpan Data Koperasi
          </button>
        </div>
      </form>
    </div>
  </div>

<?php elseif ($tab === 'shu'): ?>
  <!-- =========================================================================
       TAB 2: PEMBAGIAN SHU (PERSENTASE)
       ========================================================================= -->
  <div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
      <div>
        <h3 style="margin-bottom: 2px;">Ketentuan & Persentase Pembagian SHU</h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">Atur porsi persentase alokasi Sisa Hasil Usaha (SHU) berdasarkan AD/ART Koperasi</p>
      </div>

      <div class="card-header-actions">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisShu()" style="font-weight: 600;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Tambah Pos SHU Baru
        </button>
      </div>
    </div>

    <div class="card-body">
      <!-- Informasi Ketentuan -->
      <div style="background-color: var(--bg-subtle); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 20px; font-size: 0.84rem; color: var(--secondary); line-height: 1.6;">
        ℹ️ <strong>Ketentuan Alokasi SHU:</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
          <li>Pos <strong>No. 1 (Jasa Modal)</strong>, <strong>No. 2 (Jasa Usaha)</strong>, dan <strong>No. 3 (Dana Cadangan)</strong> merupakan pos pokok wajib sehingga <strong>tidak dapat dihapus</strong>, namun nama dan persentasenya tetap dapat disesuaikan.</li>
          <li>Pos No. 4 dan seterusnya dapat dihapus atau ditambah pos baru sesuai AD/ART.</li>
          <li>Kolom persentase mendukung dua angka di belakang koma (contoh: <code>80,00%</code>, <code>25,00%</code>, <code>2,50%</code>).</li>
        </ul>
      </div>

      <form method="POST" action="pengaturan.php?tab=shu" id="formShu">
        <input type="hidden" name="action" value="save_shu">

        <div class="table-responsive">
          <table class="table" id="tableShu">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">No.</th>
                <th>Nama Pos Alokasi SHU</th>
                <th style="width: 200px; text-align: right;">Persentase (%)</th>
                <th style="width: 100px; text-align: center;">Aksi</th>
              </tr>
            </thead>
            <tbody id="tbodyShu">
              <?php foreach ($shuList as $idx => $item): 
                $num = $idx + 1;
                // Pos 1, 2, dan 3 adalah pos pokok wajib yang tidak dapat dihapus
                $isLocked = ($num === 1 || $num === 2 || $num === 3) || !empty($item['locked']);
                $valPersen = number_format((float)($item['persen'] ?? 0), 2, ',', '.');
              ?>
                <tr class="shu-row">
                  <!-- Nomor Baris -->
                  <td style="text-align: center; font-weight: 700; color: var(--text-muted);" class="shu-number">
                    <?= $num ?>
                  </td>

                  <!-- Nama Pos Alokasi -->
                  <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <input type="text" name="shu_nama[]" class="form-control" value="<?= htmlspecialchars($item['nama'] ?? '') ?>" placeholder="Masukkan nama pos alokasi SHU" required>
                      <?php if ($isLocked): ?>
                        <span class="badge badge-info" style="font-size: 0.72rem; white-space: nowrap;" title="Pos pokok wajib, nama dan persentase dapat disesuaikan namun tidak dapat dihapus">🔒 Wajib</span>
                      <?php endif; ?>
                    </div>
                    <input type="hidden" name="shu_locked[]" value="<?= $isLocked ? '1' : '0' ?>">
                  </td>

                  <!-- Input Persentase (Dua angka di belakang koma) -->
                  <td>
                    <div style="position: relative; display: flex; align-items: center;">
                      <input type="text" 
                             name="shu_persen[]" 
                             class="form-control shu-input-persen" 
                             value="<?= $valPersen ?>" 
                             placeholder="0,00" 
                             oninput="hitungTotalShu()" 
                             onblur="formatDuaDesimal(this)" 
                             style="text-align: right; font-weight: 700; padding-right: 30px;" 
                             required>
                      <span style="position: absolute; right: 10px; font-weight: 700; color: var(--text-muted); pointer-events: none;">%</span>
                    </div>
                  </td>

                  <!-- Tombol Aksi Hapus (Terkunci untuk No 1, 2 & 3) -->
                  <td style="text-align: center;">
                    <?php if ($isLocked): ?>
                      <button type="button" class="btn btn-secondary btn-icon" disabled style="opacity: 0.4; cursor: not-allowed;" title="Pos No. <?= $num ?> tidak bisa dihapus">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-danger btn-icon" onclick="hapusBarisShu(this)" title="Hapus Pos SHU">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background-color: var(--bg-subtle); font-weight: 800;">
                <td colspan="2" style="text-align: right; font-size: 0.95rem;">
                  TOTAL ALOKASI SHU:
                </td>
                <td style="text-align: right; font-size: 1.05rem;">
                  <span id="labelTotalPersen" style="color: var(--primary);">
                    <?= number_format($totalShuSaatIni, 2, ',', '.') ?>%
                  </span>
                </td>
                <td style="text-align: center;">
                  <span id="badgeStatusTotal" class="badge <?= (abs($totalShuSaatIni - 100.0) < 0.01) ? 'badge-success' : 'badge-warning' ?>" style="font-size: 0.75rem;">
                    <?= (abs($totalShuSaatIni - 100.0) < 0.01) ? '100% Pas' : 'Belum 100%' ?>
                  </span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="tambahBarisShu()">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Tambah Pos Baru
            </button>
          </div>

          <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 0.95rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Simpan Pembagian SHU
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function formatDuaDesimal(input) {
    let val = input.value.trim().replace('%', '').trim();
    if (val === '') {
      input.value = '0,00';
      hitungTotalShu();
      return;
    }
    val = val.replace(',', '.');
    let num = parseFloat(val);
    if (isNaN(num) || num < 0) num = 0;
    if (num > 100) num = 100;
    input.value = num.toFixed(2).replace('.', ',');
    hitungTotalShu();
  }

  function hitungTotalShu() {
    const inputs = document.querySelectorAll('.shu-input-persen');
    let total = 0;
    inputs.forEach(inp => {
      let val = inp.value.trim().replace('%', '').trim().replace(',', '.');
      let num = parseFloat(val);
      if (!isNaN(num)) total += num;
    });

    const totalFixed = total.toFixed(2);
    const totalFormatted = totalFixed.replace('.', ',');
    const labelTotal = document.getElementById('labelTotalPersen');
    const badgeStatus = document.getElementById('badgeStatusTotal');

    if (labelTotal) labelTotal.textContent = totalFormatted + '%';
    if (badgeStatus) {
      if (Math.abs(total - 100.0) < 0.001) {
        labelTotal.style.color = 'var(--primary)';
        badgeStatus.className = 'badge badge-success';
        badgeStatus.textContent = '100% Pas';
      } else if (total > 100) {
        labelTotal.style.color = '#dc2626';
        badgeStatus.className = 'badge badge-danger';
        badgeStatus.textContent = 'Lebih (' + (total - 100).toFixed(2).replace('.', ',') + '%)';
      } else {
        labelTotal.style.color = '#d97706';
        badgeStatus.className = 'badge badge-warning';
        badgeStatus.textContent = 'Kurang (' + (100 - total).toFixed(2).replace('.', ',') + '%)';
      }
    }
  }

  function tambahBarisShu() {
    const tbody = document.getElementById('tbodyShu');
    const currentRows = tbody.querySelectorAll('.shu-row').length;
    const newNum = currentRows + 1;

    const tr = document.createElement('tr');
    tr.className = 'shu-row';
    tr.innerHTML = `
      <td style="text-align: center; font-weight: 700; color: var(--text-muted);" class="shu-number">
        ${newNum}
      </td>
      <td>
        <input type="text" name="shu_nama[]" class="form-control" placeholder="Nama pos alokasi SHU baru" required autofocus>
        <input type="hidden" name="shu_locked[]" value="0">
      </td>
      <td>
        <div style="position: relative; display: flex; align-items: center;">
          <input type="text" 
                 name="shu_persen[]" 
                 class="form-control shu-input-persen" 
                 value="0,00" 
                 placeholder="0,00" 
                 oninput="hitungTotalShu()" 
                 onblur="formatDuaDesimal(this)" 
                 style="text-align: right; font-weight: 700; padding-right: 30px;" 
                 required>
          <span style="position: absolute; right: 10px; font-weight: 700; color: var(--text-muted); pointer-events: none;">%</span>
        </div>
      </td>
      <td style="text-align: center;">
        <button type="button" class="btn btn-danger btn-icon" onclick="hapusBarisShu(this)" title="Hapus Pos SHU">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
    renumberShuRows();
    hitungTotalShu();
  }

  function hapusBarisShu(btn) {
    const row = btn.closest('tr');
    if (row) {
      row.remove();
      renumberShuRows();
      hitungTotalShu();
    }
  }

  function renumberShuRows() {
    const rows = document.querySelectorAll('.shu-row');
    rows.forEach((r, idx) => {
      const numCell = r.querySelector('.shu-number');
      if (numCell) numCell.textContent = idx + 1;
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    hitungTotalShu();
  });
  </script>

<?php elseif ($tab === 'neraca'): ?>
  <!-- =========================================================================
       TAB 3: NERACA & SALDO AWAL
       ========================================================================= -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
      <div>
        <h3 style="margin-bottom: 2px;">Kode Rekening & Saldo Awal Neraca</h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">Kelola kode rekening, nama perkiraan, dan saldo awal untuk komponen Aset, Kewajiban, dan Ekuitas</p>
      </div>

      <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload()" title="Muat ulang data">
          <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Reset
        </button>
      </div>
    </div>

    <div class="card-body">
      <!-- Petunjuk Neraca -->
      <div style="background-color: var(--bg-subtle); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 20px; font-size: 0.84rem; color: var(--secondary); line-height: 1.6;">
        ℹ️ <strong>Petunjuk Pengisian Saldo Awal Neraca:</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
          <li>Isikan <strong>Kode Rekening</strong>, <strong>Nama Rekening</strong>, dan <strong>Saldo Awal (Rp)</strong> untuk setiap pos Neraca.</li>
          <li>Akun <strong>Akumulasi Penyusutan Inventaris</strong> secara otomatis dihitung sebagai <em>Akun Kontra (Pengurang Aset Tetap)</em>.</li>
          <li>Prinsip Keseimbangan Akuntansi: <strong>TOTAL ASET (AKTIVA) = TOTAL KEWAJIBAN & EKUITAS (PASIVA)</strong>.</li>
        </ul>
      </div>

      <!-- Ringkasan Keseimbangan Realtime (Balance Widget) -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <!-- Box Total Aset -->
        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; border-radius: var(--radius); padding: 16px 20px;">
          <div style="font-size: 0.8rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">TOTAL ASET (AKTIVA)</div>
          <div id="displayTotalAset" style="font-size: 1.45rem; font-weight: 800; color: #15803d; margin-top: 4px;">Rp 0</div>
          <div style="font-size: 0.76rem; color: #166534; margin-top: 2px;">Aset Lancar + Nilai Buku Aset Tetap</div>
        </div>

        <!-- Box Total Kewajiban & Ekuitas -->
        <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #93c5fd; border-radius: var(--radius); padding: 16px 20px;">
          <div style="font-size: 0.8rem; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">TOTAL KEWAJIBAN & EKUITAS</div>
          <div id="displayTotalPasiva" style="font-size: 1.45rem; font-weight: 800; color: #1d4ed8; margin-top: 4px;">Rp 0</div>
          <div style="font-size: 0.76rem; color: #1e40af; margin-top: 2px;">Kewajiban Lancar + Dana + Ekuitas + SHU</div>
        </div>

        <!-- Box Status Keseimbangan -->
        <div id="boxStatusBalance" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: var(--radius); padding: 16px 20px;">
          <div style="font-size: 0.8rem; font-weight: 700; color: var(--secondary); text-transform: uppercase; letter-spacing: 0.5px;">STATUS KESEIMBANGAN</div>
          <div id="badgeNeracaStatus" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.95rem; font-weight: 800; padding: 6px 14px; border-radius: 999px; margin-top: 6px; background-color: #e2e8f0; color: #475569;">
            Memeriksa...
          </div>
          <div id="textNeracaSelisih" style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">Selisih: Rp 0</div>
        </div>
      </div>

      <form method="POST" action="pengaturan.php?tab=neraca" id="formNeraca">
        <input type="hidden" name="action" value="save_neraca">

        <!-- ===============================================================
             BAGIAN A: ASET (AKTIVA)
             =============================================================== -->
        <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--primary-light);">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="background: var(--primary); color: #fff; width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">A</span>
              <div>
                <h4 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--secondary);">A. ASET (AKTIVA)</h4>
                <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Pos harta lancar dan aset tetap koperasi</p>
              </div>
            </div>
            <div style="text-align: right;">
              <span style="font-size: 0.78rem; color: var(--text-muted); display: block;">Subtotal Aset:</span>
              <strong id="subtotalAsetRingkasan" style="font-size: 1.05rem; color: var(--primary);">Rp 0</strong>
            </div>
          </div>

          <!-- A.1. Aset Lancar -->
          <?php 
            $catA1 = $neraca['A1'] ?? $defaultNeraca['A1']; 
            $itemsA1 = $catA1['items'] ?? [];
          ?>
          <div style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <h5 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--secondary); display: flex; align-items: center; gap: 6px;">
                <span class="badge badge-info" style="font-size: 0.72rem; padding: 3px 8px;">A.1</span>
                Aset Lancar
              </h5>
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisNeraca('A1', 'A.1')" style="font-size: 0.76rem; padding: 4px 10px;">
                + Tambah Akun Aset Lancar
              </button>
            </div>

            <div class="table-responsive">
              <table class="table" style="margin-bottom: 0;">
                <thead>
                  <tr style="background: var(--bg-subtle);">
                    <th style="width: 140px;">Kode Akun</th>
                    <th>Nama Rekening</th>
                    <th style="width: 240px; text-align: right;">Saldo Awal (Rp)</th>
                    <th style="width: 60px; text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="tbody_neraca_A1">
                  <?php foreach ($itemsA1 as $idx => $it): 
                    $saldoFormatted = number_format((float)($it['saldo'] ?? 0), 0, ',', '.');
                  ?>
                    <tr class="neraca-row" data-cat="A1">
                      <td>
                        <input type="text" name="neraca[A1][kode][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="A.1.x" required>
                        <input type="hidden" name="neraca[A1][kontra][]" value="0">
                      </td>
                      <td>
                        <input type="text" name="neraca[A1][nama][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening" required>
                      </td>
                      <td>
                        <div style="position: relative; display: flex; align-items: center;">
                          <span style="position: absolute; left: 10px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">Rp</span>
                          <input type="text" 
                                 name="neraca[A1][saldo][]" 
                                 class="form-control form-control-sm neraca-saldo-input" 
                                 value="<?= $saldoFormatted ?>" 
                                 oninput="onInputRupiah(this); hitungSemuaNeraca();" 
                                 style="text-align: right; font-weight: 700; padding-left: 36px;" 
                                 required>
                        </div>
                      </td>
                      <td style="text-align: center;">
                        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisNeraca(this)" title="Hapus Akun">
                          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="background: #f8fafc; font-weight: 700; font-size: 0.88rem;">
                    <td colspan="2" style="text-align: right; color: var(--secondary);">Total A.1. Aset Lancar:</td>
                    <td style="text-align: right; color: var(--primary);" id="subtotal_A1">Rp 0</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- A.2. Aset Tetap -->
          <?php 
            $catA2 = $neraca['A2'] ?? $defaultNeraca['A2']; 
            $itemsA2 = $catA2['items'] ?? [];
          ?>
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <h5 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--secondary); display: flex; align-items: center; gap: 6px;">
                <span class="badge badge-info" style="font-size: 0.72rem; padding: 3px 8px;">A.2</span>
                Aset Tetap
              </h5>
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisNeraca('A2', 'A.2')" style="font-size: 0.76rem; padding: 4px 10px;">
                + Tambah Akun Aset Tetap
              </button>
            </div>

            <div class="table-responsive">
              <table class="table" style="margin-bottom: 0;">
                <thead>
                  <tr style="background: var(--bg-subtle);">
                    <th style="width: 140px;">Kode Akun</th>
                    <th>Nama Rekening</th>
                    <th style="width: 240px; text-align: right;">Saldo Awal (Rp)</th>
                    <th style="width: 60px; text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="tbody_neraca_A2">
                  <?php foreach ($itemsA2 as $idx => $it): 
                    $saldoFormatted = number_format((float)($it['saldo'] ?? 0), 0, ',', '.');
                    $isKontra = !empty($it['is_kontra']) || (stripos($it['nama'] ?? '', 'Akumulasi') !== false);
                  ?>
                    <tr class="neraca-row" data-cat="A2" data-kontra="<?= $isKontra ? '1' : '0' ?>">
                      <td>
                        <input type="text" name="neraca[A2][kode][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="A.2.x" required>
                        <input type="hidden" name="neraca[A2][kontra][]" value="<?= $isKontra ? '1' : '0' ?>">
                      </td>
                      <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                          <input type="text" name="neraca[A2][nama][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening" required>
                          <?php if ($isKontra): ?>
                            <span class="badge badge-warning" style="font-size: 0.7rem; white-space: nowrap;" title="Akun ini mengurangi total aset tetap">[-] Kontra</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <div style="position: relative; display: flex; align-items: center;">
                          <span style="position: absolute; left: 10px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">Rp</span>
                          <input type="text" 
                                 name="neraca[A2][saldo][]" 
                                 class="form-control form-control-sm neraca-saldo-input" 
                                 value="<?= $saldoFormatted ?>" 
                                 oninput="onInputRupiah(this); hitungSemuaNeraca();" 
                                 style="text-align: right; font-weight: 700; padding-left: 36px; <?= $isKontra ? 'color: #b91c1c;' : '' ?>" 
                                 required>
                        </div>
                      </td>
                      <td style="text-align: center;">
                        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisNeraca(this)" title="Hapus Akun">
                          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="background: #f8fafc; font-weight: 700; font-size: 0.88rem;">
                    <td colspan="2" style="text-align: right; color: var(--secondary);">Nilai Buku A.2. Aset Tetap (Inventaris - Akumulasi Penyusutan):</td>
                    <td style="text-align: right; color: var(--primary);" id="subtotal_A2">Rp 0</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- ===============================================================
             BAGIAN B: KEWAJIBAN DAN EKUITAS (PASIVA)
             =============================================================== -->
        <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--primary-light);">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="background: #2563eb; color: #fff; width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">B</span>
              <div>
                <h4 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--secondary);">B. KEWAJIBAN DAN EKUITAS (PASIVA)</h4>
                <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Kewajiban lancar, titipan dana, simpanan ekuitas modal, dan SHU</p>
              </div>
            </div>
            <div style="text-align: right;">
              <span style="font-size: 0.78rem; color: var(--text-muted); display: block;">Subtotal Pasiva:</span>
              <strong id="subtotalPasivaRingkasan" style="font-size: 1.05rem; color: #2563eb;">Rp 0</strong>
            </div>
          </div>

          <!-- B.1. Kewajiban Lancar -->
          <?php 
            $catB1 = $neraca['B1'] ?? $defaultNeraca['B1']; 
            $itemsB1 = $catB1['items'] ?? [];
          ?>
          <div style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <h5 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--secondary); display: flex; align-items: center; gap: 6px;">
                <span class="badge badge-info" style="font-size: 0.72rem; padding: 3px 8px;">B.1</span>
                Kewajiban Lancar
              </h5>
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisNeraca('B1', 'B.1')" style="font-size: 0.76rem; padding: 4px 10px;">
                + Tambah Akun Kewajiban
              </button>
            </div>

            <div class="table-responsive">
              <table class="table" style="margin-bottom: 0;">
                <thead>
                  <tr style="background: var(--bg-subtle);">
                    <th style="width: 140px;">Kode Akun</th>
                    <th>Nama Rekening</th>
                    <th style="width: 240px; text-align: right;">Saldo Awal (Rp)</th>
                    <th style="width: 60px; text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="tbody_neraca_B1">
                  <?php foreach ($itemsB1 as $idx => $it): 
                    $saldoFormatted = number_format((float)($it['saldo'] ?? 0), 0, ',', '.');
                  ?>
                    <tr class="neraca-row" data-cat="B1">
                      <td>
                        <input type="text" name="neraca[B1][kode][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="B.1.x" required>
                        <input type="hidden" name="neraca[B1][kontra][]" value="0">
                      </td>
                      <td>
                        <input type="text" name="neraca[B1][nama][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening" required>
                      </td>
                      <td>
                        <div style="position: relative; display: flex; align-items: center;">
                          <span style="position: absolute; left: 10px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">Rp</span>
                          <input type="text" 
                                 name="neraca[B1][saldo][]" 
                                 class="form-control form-control-sm neraca-saldo-input" 
                                 value="<?= $saldoFormatted ?>" 
                                 oninput="onInputRupiah(this); hitungSemuaNeraca();" 
                                 style="text-align: right; font-weight: 700; padding-left: 36px;" 
                                 required>
                        </div>
                      </td>
                      <td style="text-align: center;">
                        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisNeraca(this)" title="Hapus Akun">
                          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="background: #f8fafc; font-weight: 700; font-size: 0.88rem;">
                    <td colspan="2" style="text-align: right; color: var(--secondary);">Total B.1. Kewajiban Lancar:</td>
                    <td style="text-align: right; color: #2563eb;" id="subtotal_B1">Rp 0</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- B.2. Dana -->
          <?php 
            $catB2 = $neraca['B2'] ?? $defaultNeraca['B2']; 
            $itemsB2 = $catB2['items'] ?? [];
          ?>
          <div style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <h5 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--secondary); display: flex; align-items: center; gap: 6px;">
                <span class="badge badge-info" style="font-size: 0.72rem; padding: 3px 8px;">B.2</span>
                Dana-Dana
              </h5>
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisNeraca('B2', 'B.2')" style="font-size: 0.76rem; padding: 4px 10px;">
                + Tambah Akun Dana
              </button>
            </div>

            <div class="table-responsive">
              <table class="table" style="margin-bottom: 0;">
                <thead>
                  <tr style="background: var(--bg-subtle);">
                    <th style="width: 140px;">Kode Akun</th>
                    <th>Nama Rekening</th>
                    <th style="width: 240px; text-align: right;">Saldo Awal (Rp)</th>
                    <th style="width: 60px; text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="tbody_neraca_B2">
                  <?php foreach ($itemsB2 as $idx => $it): 
                    $saldoFormatted = number_format((float)($it['saldo'] ?? 0), 0, ',', '.');
                  ?>
                    <tr class="neraca-row" data-cat="B2">
                      <td>
                        <input type="text" name="neraca[B2][kode][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="B.2.x" required>
                        <input type="hidden" name="neraca[B2][kontra][]" value="0">
                      </td>
                      <td>
                        <input type="text" name="neraca[B2][nama][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening" required>
                      </td>
                      <td>
                        <div style="position: relative; display: flex; align-items: center;">
                          <span style="position: absolute; left: 10px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">Rp</span>
                          <input type="text" 
                                 name="neraca[B2][saldo][]" 
                                 class="form-control form-control-sm neraca-saldo-input" 
                                 value="<?= $saldoFormatted ?>" 
                                 oninput="onInputRupiah(this); hitungSemuaNeraca();" 
                                 style="text-align: right; font-weight: 700; padding-left: 36px;" 
                                 required>
                        </div>
                      </td>
                      <td style="text-align: center;">
                        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisNeraca(this)" title="Hapus Akun">
                          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="background: #f8fafc; font-weight: 700; font-size: 0.88rem;">
                    <td colspan="2" style="text-align: right; color: var(--secondary);">Total B.2. Dana:</td>
                    <td style="text-align: right; color: #2563eb;" id="subtotal_B2">Rp 0</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- B.3. Ekuitas / Modal -->
          <?php 
            $catB3 = $neraca['B3'] ?? $defaultNeraca['B3']; 
            $itemsB3 = $catB3['items'] ?? [];
          ?>
          <div style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <h5 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--secondary); display: flex; align-items: center; gap: 6px;">
                <span class="badge badge-info" style="font-size: 0.72rem; padding: 3px 8px;">B.3</span>
                Ekuitas / Modal
              </h5>
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisNeraca('B3', 'B.3')" style="font-size: 0.76rem; padding: 4px 10px;">
                + Tambah Akun Ekuitas
              </button>
            </div>

            <div class="table-responsive">
              <table class="table" style="margin-bottom: 0;">
                <thead>
                  <tr style="background: var(--bg-subtle);">
                    <th style="width: 140px;">Kode Akun</th>
                    <th>Nama Rekening</th>
                    <th style="width: 240px; text-align: right;">Saldo Awal (Rp)</th>
                    <th style="width: 60px; text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="tbody_neraca_B3">
                  <?php foreach ($itemsB3 as $idx => $it): 
                    $saldoFormatted = number_format((float)($it['saldo'] ?? 0), 0, ',', '.');
                  ?>
                    <tr class="neraca-row" data-cat="B3">
                      <td>
                        <input type="text" name="neraca[B3][kode][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="B.3.x" required>
                        <input type="hidden" name="neraca[B3][kontra][]" value="0">
                      </td>
                      <td>
                        <input type="text" name="neraca[B3][nama][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening" required>
                      </td>
                      <td>
                        <div style="position: relative; display: flex; align-items: center;">
                          <span style="position: absolute; left: 10px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">Rp</span>
                          <input type="text" 
                                 name="neraca[B3][saldo][]" 
                                 class="form-control form-control-sm neraca-saldo-input" 
                                 value="<?= $saldoFormatted ?>" 
                                 oninput="onInputRupiah(this); hitungSemuaNeraca();" 
                                 style="text-align: right; font-weight: 700; padding-left: 36px;" 
                                 required>
                        </div>
                      </td>
                      <td style="text-align: center;">
                        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisNeraca(this)" title="Hapus Akun">
                          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="background: #f8fafc; font-weight: 700; font-size: 0.88rem;">
                    <td colspan="2" style="text-align: right; color: var(--secondary);">Total B.3. Ekuitas/Modal:</td>
                    <td style="text-align: right; color: #2563eb;" id="subtotal_B3">Rp 0</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- B.4. Sisa Hasil Usaha (SHU) -->
          <?php 
            $catB4 = $neraca['B4'] ?? $defaultNeraca['B4']; 
            $itemsB4 = $catB4['items'] ?? [];
          ?>
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <h5 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: var(--secondary); display: flex; align-items: center; gap: 6px;">
                <span class="badge badge-info" style="font-size: 0.72rem; padding: 3px 8px;">B.4</span>
                Sisa Hasil Usaha (SHU)
              </h5>
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisNeraca('B4', 'B.4')" style="font-size: 0.76rem; padding: 4px 10px;">
                + Tambah Akun SHU
              </button>
            </div>

            <div class="table-responsive">
              <table class="table" style="margin-bottom: 0;">
                <thead>
                  <tr style="background: var(--bg-subtle);">
                    <th style="width: 140px;">Kode Akun</th>
                    <th>Nama Rekening</th>
                    <th style="width: 240px; text-align: right;">Saldo Awal (Rp)</th>
                    <th style="width: 60px; text-align: center;">Aksi</th>
                  </tr>
                </thead>
                <tbody id="tbody_neraca_B4">
                  <?php foreach ($itemsB4 as $idx => $it): 
                    $saldoFormatted = number_format((float)($it['saldo'] ?? 0), 0, ',', '.');
                  ?>
                    <tr class="neraca-row" data-cat="B4">
                      <td>
                        <input type="text" name="neraca[B4][kode][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="B.4.x" required>
                        <input type="hidden" name="neraca[B4][kontra][]" value="0">
                      </td>
                      <td>
                        <input type="text" name="neraca[B4][nama][]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening" required>
                      </td>
                      <td>
                        <div style="position: relative; display: flex; align-items: center;">
                          <span style="position: absolute; left: 10px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">Rp</span>
                          <input type="text" 
                                 name="neraca[B4][saldo][]" 
                                 class="form-control form-control-sm neraca-saldo-input" 
                                 value="<?= $saldoFormatted ?>" 
                                 oninput="onInputRupiah(this); hitungSemuaNeraca();" 
                                 style="text-align: right; font-weight: 700; padding-left: 36px;" 
                                 required>
                        </div>
                      </td>
                      <td style="text-align: center;">
                        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisNeraca(this)" title="Hapus Akun">
                          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr style="background: #f8fafc; font-weight: 700; font-size: 0.88rem;">
                    <td colspan="2" style="text-align: right; color: var(--secondary);">Total B.4. SHU:</td>
                    <td style="text-align: right; color: #2563eb;" id="subtotal_B4">Rp 0</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <!-- Tombol Aksi Simpan Neraca -->
        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
          <button type="submit" class="btn btn-primary" style="padding: 11px 28px; font-size: 0.95rem; font-weight: 700;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Simpan Neraca & Saldo Awal
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function formatRibuan(angka) {
    let number_string = angka.toString().replace(/[^,\d]/g, ''),
        split = number_string.split(','),
        sisa = split[0].length % 3,
        rupiah = split[0].substr(0, sisa),
        ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
      let separator = sisa ? '.' : '';
      rupiah += separator + ribuan.join('.');
    }
    return split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
  }

  function onInputRupiah(el) {
    let raw = el.value.replace(/[^0-9]/g, '');
    if (raw === '') raw = '0';
    el.value = formatRibuan(raw);
  }

  function parseAngka(val) {
    if (!val) return 0;
    let clean = val.toString().replace(/[^0-9]/g, '');
    let num = parseFloat(clean);
    return isNaN(num) ? 0 : num;
  }

  function hitungSemuaNeraca() {
    const categories = ['A1', 'A2', 'B1', 'B2', 'B3', 'B4'];
    let totals = {};

    categories.forEach(cat => {
      let subtotal = 0;
      const rows = document.querySelectorAll(`tr.neraca-row[data-cat="${cat}"]`);
      rows.forEach(r => {
        const inp = r.querySelector('.neraca-saldo-input');
        if (inp) {
          const val = parseAngka(inp.value);
          const isKontra = r.dataset.kontra === '1';
          if (isKontra) {
            subtotal -= val;
          } else {
            subtotal += val;
          }
        }
      });
      totals[cat] = subtotal;
      const elSub = document.getElementById(`subtotal_${cat}`);
      if (elSub) {
        elSub.textContent = 'Rp ' + formatRibuan(Math.abs(subtotal));
        if (subtotal < 0) elSub.textContent = '- Rp ' + formatRibuan(Math.abs(subtotal));
      }
    });

    const totalAset = (totals['A1'] || 0) + (totals['A2'] || 0);
    const totalPasiva = (totals['B1'] || 0) + (totals['B2'] || 0) + (totals['B3'] || 0) + (totals['B4'] || 0);
    const selisih = Math.abs(totalAset - totalPasiva);

    // Update Banner Display
    const elTotAset = document.getElementById('displayTotalAset');
    const elTotPasiva = document.getElementById('displayTotalPasiva');
    const elSubAsetRingkasan = document.getElementById('subtotalAsetRingkasan');
    const elSubPasivaRingkasan = document.getElementById('subtotalPasivaRingkasan');

    if (elTotAset) elTotAset.textContent = 'Rp ' + formatRibuan(totalAset);
    if (elTotPasiva) elTotPasiva.textContent = 'Rp ' + formatRibuan(totalPasiva);
    if (elSubAsetRingkasan) elSubAsetRingkasan.textContent = 'Rp ' + formatRibuan(totalAset);
    if (elSubPasivaRingkasan) elSubPasivaRingkasan.textContent = 'Rp ' + formatRibuan(totalPasiva);

    // Update Status Balance
    const badgeStatus = document.getElementById('badgeNeracaStatus');
    const textSelisih = document.getElementById('textNeracaSelisih');
    const boxStatus = document.getElementById('boxStatusBalance');

    if (badgeStatus) {
      if (selisih < 1) {
        badgeStatus.style.backgroundColor = '#dcfce7';
        badgeStatus.style.color = '#15803d';
        badgeStatus.innerHTML = '✓ NERACA SEIMBANG (BALANCE)';
        if (textSelisih) {
          textSelisih.textContent = 'Aktiva dan Pasiva tepat sama';
          textSelisih.style.color = '#15803d';
        }
        if (boxStatus) {
          boxStatus.style.background = '#f0fdf4';
          boxStatus.style.borderColor = '#86efac';
        }
      } else {
        badgeStatus.style.backgroundColor = '#fee2e2';
        badgeStatus.style.color = '#b91c1c';
        badgeStatus.innerHTML = '⚠️ BELUM SEIMBANG';
        if (textSelisih) {
          textSelisih.textContent = 'Selisih: Rp ' + formatRibuan(selisih);
          textSelisih.style.color = '#b91c1c';
        }
        if (boxStatus) {
          boxStatus.style.background = '#fff1f2';
          boxStatus.style.borderColor = '#fca5a5';
        }
      }
    }
  }

  function tambahBarisNeraca(catKey, catPrefix) {
    const tbody = document.getElementById(`tbody_neraca_${catKey}`);
    if (!tbody) return;

    const count = tbody.querySelectorAll('tr.neraca-row').length + 1;
    const defaultKode = `${catPrefix}.${count}`;

    const tr = document.createElement('tr');
    tr.className = 'neraca-row';
    tr.dataset.cat = catKey;
    tr.dataset.kontra = '0';

    tr.innerHTML = `
      <td>
        <input type="text" name="neraca[${catKey}][kode][]" class="form-control form-control-sm" value="${defaultKode}" placeholder="${catPrefix}.x" required>
        <input type="hidden" name="neraca[${catKey}][kontra][]" value="0">
      </td>
      <td>
        <input type="text" name="neraca[${catKey}][nama][]" class="form-control form-control-sm" placeholder="Nama rekening baru" required autofocus>
      </td>
      <td>
        <div style="position: relative; display: flex; align-items: center;">
          <span style="position: absolute; left: 10px; font-weight: 600; color: var(--text-muted); font-size: 0.85rem; pointer-events: none;">Rp</span>
          <input type="text" 
                 name="neraca[${catKey}][saldo][]" 
                 class="form-control form-control-sm neraca-saldo-input" 
                 value="0" 
                 oninput="onInputRupiah(this); hitungSemuaNeraca();" 
                 style="text-align: right; font-weight: 700; padding-left: 36px;" 
                 required>
        </div>
      </td>
      <td style="text-align: center;">
        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisNeraca(this)" title="Hapus Akun">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
      </td>
    `;

    tbody.appendChild(tr);
    hitungSemuaNeraca();
  }

  function hapusBarisNeraca(btn) {
    const row = btn.closest('tr');
    if (row) {
      row.remove();
      hitungSemuaNeraca();
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    hitungSemuaNeraca();
  });
  </script>

<?php elseif ($tab === 'labarugi'): ?>
  <!-- =========================================================================
       TAB 4: LABA / RUGI (PENDAPATAN & PENGELUARAN)
       ========================================================================= -->
  <div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
      <div>
        <h3 style="margin-bottom: 2px;">Daftar Rekening Laba / Rugi</h3>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">Kelola kode dan nama rekening pembukuan untuk pos Pendapatan dan pos Pengeluaran (Beban)</p>
      </div>
    </div>

    <div class="card-body">
      <!-- Petunjuk Laba Rugi -->
      <div style="background-color: var(--bg-subtle); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 24px; font-size: 0.84rem; color: var(--secondary); line-height: 1.6;">
        ℹ️ <strong>Ketentuan Rekening Laba / Rugi:</strong>
        <ul style="margin: 6px 0 0 18px; padding: 0;">
          <li>Daftar akun di bawah ini menjadi referensi transaksi penerimaan kas pendapatan dan pengeluaran beban operasional koperasi.</li>
          <li>Anda dapat menyesuaikan <strong>Kode Rekening</strong>, mengganti <strong>Nama Rekening</strong>, menghapus akun yang tidak dipakai, atau menambahkan pos akun baru.</li>
        </ul>
      </div>

      <form method="POST" action="pengaturan.php?tab=labarugi" id="formLabaRugi">
        <input type="hidden" name="action" value="save_labarugi">

        <!-- ===============================================================
             BAGIAN A: REKENING PENDAPATAN
             =============================================================== -->
        <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--primary-light); flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="background: var(--primary); color: #fff; width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">A</span>
              <div>
                <h4 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--secondary);">A. PENDAPATAN</h4>
                <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Pos penerimaan operasional, bunga pinjaman, penjualan produk/jasa, dan penerimaan lain-lain</p>
              </div>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisLr('pendapatan')" style="font-weight: 600;">
              <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Tambah Rekening Pendapatan
            </button>
          </div>

          <div class="table-responsive">
            <table class="table" style="margin-bottom: 0;">
              <thead>
                <tr style="background: var(--bg-subtle);">
                  <th style="width: 50px; text-align: center;">No.</th>
                  <th style="width: 180px;">Kode Rekening</th>
                  <th>Nama Rekening Pendapatan</th>
                  <th style="width: 80px; text-align: center;">Aksi</th>
                </tr>
              </thead>
              <tbody id="tbodyPendapatan">
                <?php 
                  $listPendapatan = $lr['pendapatan'] ?? $defaultLr['pendapatan'];
                  foreach ($listPendapatan as $idx => $it): 
                    $num = $idx + 1;
                ?>
                  <tr class="lr-row lr-pendapatan-row">
                    <td style="text-align: center; font-weight: 700; color: var(--text-muted);" class="lr-num">
                      <?= $num ?>
                    </td>
                    <td>
                      <input type="text" name="pendapatan_kode[]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="A.<?= $num ?>" required>
                    </td>
                    <td>
                      <input type="text" name="pendapatan_nama[]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening pendapatan" required>
                    </td>
                    <td style="text-align: center;">
                      <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisLr(this, 'pendapatan')" title="Hapus Akun">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ===============================================================
             BAGIAN B: REKENING PENGELUARAN (BEBAN)
             =============================================================== -->
        <div style="background-color: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid var(--primary-light); flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span style="background: #dc2626; color: #fff; width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">B</span>
              <div>
                <h4 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--secondary);">B. PENGELUARAN (BEBAN)</h4>
                <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">Pos biaya operasional, jasa simpanan, honor, gaji, konsumsi, utilitas, dan penyusutan aset</p>
              </div>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm" onclick="tambahBarisLr('pengeluaran')" style="font-weight: 600;">
              <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Tambah Rekening Pengeluaran
            </button>
          </div>

          <div class="table-responsive">
            <table class="table" style="margin-bottom: 0;">
              <thead>
                <tr style="background: var(--bg-subtle);">
                  <th style="width: 50px; text-align: center;">No.</th>
                  <th style="width: 180px;">Kode Rekening</th>
                  <th>Nama Rekening Pengeluaran / Beban</th>
                  <th style="width: 80px; text-align: center;">Aksi</th>
                </tr>
              </thead>
              <tbody id="tbodyPengeluaran">
                <?php 
                  $listPengeluaran = $lr['pengeluaran'] ?? $defaultLr['pengeluaran'];
                  foreach ($listPengeluaran as $idx => $it): 
                    $num = $idx + 1;
                ?>
                  <tr class="lr-row lr-pengeluaran-row">
                    <td style="text-align: center; font-weight: 700; color: var(--text-muted);" class="lr-num">
                      <?= $num ?>
                    </td>
                    <td>
                      <input type="text" name="pengeluaran_kode[]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['kode'] ?? '') ?>" placeholder="B.<?= $num ?>" required>
                    </td>
                    <td>
                      <input type="text" name="pengeluaran_nama[]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['nama'] ?? '') ?>" placeholder="Nama rekening pengeluaran" required>
                    </td>
                    <td style="text-align: center;">
                      <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisLr(this, 'pengeluaran')" title="Hapus Akun">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Tombol Aksi Simpan Laba Rugi -->
        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
          <button type="submit" class="btn btn-primary" style="padding: 11px 28px; font-size: 0.95rem; font-weight: 700;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Simpan Rekening Laba / Rugi
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function tambahBarisLr(tipe) {
    const isPendapatan = (tipe === 'pendapatan');
    const tbody = document.getElementById(isPendapatan ? 'tbodyPendapatan' : 'tbodyPengeluaran');
    if (!tbody) return;

    const count = tbody.querySelectorAll('tr.lr-row').length + 1;
    const prefix = isPendapatan ? 'A' : 'B';
    const inputNameKd = isPendapatan ? 'pendapatan_kode[]' : 'pengeluaran_kode[]';
    const inputNameNm = isPendapatan ? 'pendapatan_nama[]' : 'pengeluaran_nama[]';

    const tr = document.createElement('tr');
    tr.className = `lr-row lr-${tipe}-row`;
    tr.innerHTML = `
      <td style="text-align: center; font-weight: 700; color: var(--text-muted);" class="lr-num">
        ${count}
      </td>
      <td>
        <input type="text" name="${inputNameKd}" class="form-control form-control-sm" value="${prefix}.${count}" placeholder="${prefix}.${count}" required>
      </td>
      <td>
        <input type="text" name="${inputNameNm}" class="form-control form-control-sm" placeholder="Nama rekening ${tipe} baru" required autofocus>
      </td>
      <td style="text-align: center;">
        <button type="button" class="btn btn-danger btn-icon" style="width: 28px; height: 28px;" onclick="hapusBarisLr(this, '${tipe}')" title="Hapus Akun">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  }

  function hapusBarisLr(btn, tipe) {
    const row = btn.closest('tr');
    if (row) {
      const tbody = row.closest('tbody');
      row.remove();
      if (tbody) {
        const rows = tbody.querySelectorAll('tr.lr-row');
        rows.forEach((r, idx) => {
          const numCell = r.querySelector('.lr-num');
          if (numCell) numCell.textContent = idx + 1;
        });
      }
    }
  }
  </script>

<?php elseif ($tab === 'backup'): ?>
  <!-- =========================================================================
       TAB 5: BACKUP & RESTORE DATA MANUAL
       ========================================================================= -->
  <style>
  .backup-restore-grid {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 24px;
    align-items: flex-start;
  }
  @media (max-width: 992px) {
    .backup-restore-grid {
      grid-template-columns: 1fr;
    }
  }
  </style>

  <div class="backup-restore-grid">
    
    <!-- CARD 1: BACKUP / UNDUH CADANGAN DATA -->
    <div class="card" style="margin-bottom: 0;">
      <div class="card-header" style="background: linear-gradient(to right, #f0fdf4, #ffffff);">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: #dcfce7; color: var(--primary);">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          </span>
          <div>
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--secondary);">Cadangkan Data (Backup Manual)</h3>
            <small style="color: var(--text-muted);">Unduh seluruh database transaksi koperasi ke file arsip</small>
          </div>
        </div>
      </div>
      <div class="card-body">
        <p style="font-size: 0.88rem; color: var(--secondary); line-height: 1.6; margin-bottom: 16px;">
          Fitur ini mengekspor seluruh data yang telah Anda inputkan ke dalam sebuah file cadangan berformat <strong>.JSON</strong>. Data ini mencakup rekening anggota, buku kas umum/bank/toko, pinjaman, angsuran, simpanan, simpanan pendidikan, hingga konfigurasi SHU dan Neraca.
        </p>

        <div style="background: #f8fafc; border: 1.5px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px 16px; margin-bottom: 20px;">
          <div style="font-size: 0.85rem; font-weight: 700; color: var(--secondary); margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
            <span>Ringkasan Data Tersimpan Saat Ini</span>
            <span class="badge badge-success" style="font-size: 0.75rem;">Aktif & Siap Diunduh</span>
          </div>
          <table style="width: 100%; font-size: 0.82rem; border-collapse: collapse;">
            <tbody>
              <?php 
                $totalSemuaBaris = 0;
                foreach ($tableStats as $tblKey => $tbl): 
                  $totalSemuaBaris += $tbl['count'];
              ?>
                <tr style="border-bottom: 1px solid #edf2f7;">
                  <td style="padding: 6px 0; color: var(--secondary);">
                    <span style="margin-right: 6px;"><?= $tbl['icon'] ?></span>
                    <?= htmlspecialchars($tbl['label']) ?>
                  </td>
                  <td style="padding: 6px 0; text-align: right; font-weight: 700; color: var(--primary);">
                    <?= number_format($tbl['count'], 0, ',', '.') ?> baris
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr style="font-weight: 800; background: #f1f5f9;">
                <td style="padding: 8px 6px; color: var(--secondary);">TOTAL SELURUH DATA</td>
                <td style="padding: 8px 6px; text-align: right; color: var(--primary); font-size: 0.9rem;">
                  <?= number_format($totalSemuaBaris, 0, ',', '.') ?> baris
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style="display: flex; flex-direction: column; gap: 8px;">
          <a href="pengaturan.php?action=download_backup" class="btn btn-primary" style="padding: 12px 20px; font-size: 0.95rem; font-weight: 700; justify-content: center; width: 100%;">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Download Cadangan Data Sekarang (.json)
          </a>
          <small style="color: var(--text-muted); text-align: center; font-size: 0.78rem;">
            Tip: Disarankan mengunduh cadangan secara berkala (misal: mingguan atau bulanan) sebagai antisipasi keselamatan data.
          </small>
        </div>
      </div>
    </div>

    <!-- CARD 2: RESTORE / PULIHKAN DATA CADANGAN -->
    <div class="card" style="margin-bottom: 0;">
      <div class="card-header" style="background: linear-gradient(to right, #fef2f2, #ffffff);">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; background: #fee2e2; color: var(--danger);">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          </span>
          <div>
            <h3 style="margin: 0; font-size: 1.1rem; color: var(--danger);">Pulihkan Data (Restore Manual)</h3>
            <small style="color: var(--text-muted);">Kembalikan seluruh data dari file cadangan .json yang pernah diunduh</small>
          </div>
        </div>
      </div>
      <div class="card-body">
        <!-- Kotak Peringatan Danger -->
        <div style="background-color: #fef2f2; border: 1.5px solid #fecaca; border-radius: var(--radius-sm); padding: 14px 16px; margin-bottom: 18px; color: #991b1b; font-size: 0.85rem; line-height: 1.5;">
          <div style="display: flex; align-items: flex-start; gap: 10px;">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 1px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
              <strong>PERHATIAN PENTING:</strong>
              <div style="margin-top: 4px;">Proses Restore akan <strong>menimpa dan mengganti seluruh data yang saat ini tersimpan</strong> di sistem dengan isi file cadangan yang diunggah. Tindakan ini tidak dapat dibatalkan.</div>
            </div>
          </div>
        </div>

        <form method="POST" action="pengaturan.php?tab=backup" enctype="multipart/form-data" id="formRestoreBackup">
          <input type="hidden" name="action" value="restore_backup">

          <div class="form-group">
            <label class="form-label" style="font-weight: 700;">Pilih File Cadangan (.json) *</label>
            <div style="border: 2px dashed #cbd5e1; border-radius: var(--radius-sm); padding: 20px; text-align: center; background: #f8fafc; cursor: pointer;" onclick="document.getElementById('inputBackupFile').click();">
              <svg width="32" height="32" fill="none" stroke="#64748b" viewBox="0 0 24 24" style="margin: 0 auto 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
              <div style="font-size: 0.9rem; font-weight: 600; color: var(--secondary);" id="labelFileName">Klik untuk memilih file cadangan JSON</div>
              <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">Hanya mendukung file .json yang diekspor dari sistem Koperasi Gatra Core</div>
            </div>
            <input type="file" id="inputBackupFile" name="backup_file" accept=".json,application/json" required style="display: none;" onchange="handleFileSelect(this)">
          </div>

          <div class="form-group" style="margin-top: 16px;">
            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; user-select: none;">
              <input type="checkbox" id="chkKonfirmasiRestore" required style="width: 18px; height: 18px; margin-top: 2px; accent-color: var(--danger);">
              <span style="font-size: 0.85rem; color: var(--secondary); line-height: 1.4;">
                Saya memahami konsekuensinya dan menyatakan setuju untuk menimpa data yang sedang aktif dengan data dari file cadangan yang dipilih.
              </span>
            </label>
          </div>

          <div style="margin-top: 20px;">
            <button type="submit" id="btnSubmitRestore" class="btn btn-danger" style="padding: 12px 20px; font-size: 0.95rem; font-weight: 700; justify-content: center; width: 100%;" disabled>
              <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Mulai Pulihkan (Restore) Data
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>

  <!-- Modal Konfirmasi Restore -->
  <div class="modal-overlay" id="modalKonfirmasiRestore">
    <div class="modal-card" style="max-width: 480px;">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #fee2e2; color: var(--danger);">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          </span>
          <h3 style="margin: 0; color: var(--danger); font-size: 1.1rem;">Konfirmasi Akhir Restore Data</h3>
        </div>
        <button type="button" class="modal-close-btn" onclick="closeModal('modalKonfirmasiRestore')">&times;</button>
      </div>
      <div class="modal-body">
        <p style="font-size: 0.95rem; color: var(--secondary); margin-bottom: 12px; line-height: 1.5;">
          Apakah Anda benar-benar yakin ingin memulihkan database dari file: <strong id="modalRestoreFileName" style="color: var(--primary);">-</strong>?
        </p>
        <div style="background-color: #fff7ed; border: 1px solid #ffedd5; border-radius: var(--radius-sm); padding: 12px; font-size: 0.82rem; color: #9a3412; line-height: 1.5;">
          ⚠️ Semua data anggota, transaksi kas, simpanan, dan pinjaman yang telah dibuat sejak cadangan ini dibuat akan tertimpa dan tidak dapat dipulihkan kembali.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalKonfirmasiRestore')">Batal</button>
        <button type="button" class="btn btn-danger" onclick="executeRestore()">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Ya, Timpa & Pulihkan Sekarang
        </button>
      </div>
    </div>
  </div>

  <script>
  function handleFileSelect(input) {
    const lbl = document.getElementById('labelFileName');
    const chk = document.getElementById('chkKonfirmasiRestore');
    const btn = document.getElementById('btnSubmitRestore');
    if (input.files && input.files[0]) {
      const file = input.files[0];
      lbl.innerHTML = `<strong style="color: var(--primary);">${file.name}</strong> (${(file.size / 1024).toFixed(1)} KB)`;
      btn.disabled = !chk.checked;
    } else {
      lbl.textContent = 'Klik untuk memilih file cadangan JSON';
      btn.disabled = true;
    }
  }

  document.getElementById('chkKonfirmasiRestore')?.addEventListener('change', function() {
    const fileInput = document.getElementById('inputBackupFile');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
    document.getElementById('btnSubmitRestore').disabled = !(this.checked && hasFile);
  });

  document.getElementById('formRestoreBackup')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('inputBackupFile');
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
      alert('Silakan pilih file cadangan .json terlebih dahulu.');
      return;
    }
    document.getElementById('modalRestoreFileName').textContent = fileInput.files[0].name;
    openModal('modalKonfirmasiRestore');
  });

  function executeRestore() {
    closeModal('modalKonfirmasiRestore');
    const btn = document.getElementById('btnSubmitRestore');
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Sedang Memulihkan Database... Mohon Tunggu...';
    document.getElementById('formRestoreBackup').submit();
  }
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
