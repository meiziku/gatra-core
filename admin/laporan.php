<?php
/**
 * Portal Laporan Keuangan Koperasi Simpan Pinjam Gatra Teknika
 * 
 * 6 Tab Laporan Utama:
 * 1. Laporan Simpanan Wajib
 * 2. Laporan Simpanan Manasuka (Sukarela)
 * 3. Laporan Hasil Usaha (Laba / Rugi) - Kolom bulanan Jan - Des & Total
 * 4. Neraca - Kategori dari tab=neraca, isi dari transaksi
 * 5. Rekap SHU - Rincian hak SHU per anggota (Pokok, Wajib, Pinjaman, Bunga Manasuka 3%/thn)
 * 6. Laporan Pembagian SHU - Alokasi pos AD/ART dari tab=shu dikali SHU Tahun Berjalan
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

require_admin();

$currentUser = current_user();
$activePage = 'laporan';

// =========================================================================
// 1. PARAMETER TAB & FILTER PERIODE
// =========================================================================
$validTabs = [
    'simpanan_wajib'      => 'Laporan Simpanan Wajib',
    'simpanan_manasuka'   => 'Laporan Simpanan Manasuka',
    'laporan_hasil_usaha' => 'Laporan Hasil Usaha',
    'neraca'              => 'Neraca',
    'rekap_shu'           => 'Rekap SHU',
    'pembagian_shu'       => 'Laporan Pembagian SHU'
];

$rawTab = $_GET['tab'] ?? 'simpanan_wajib';
if ($rawTab === 'simpanan') $rawTab = 'simpanan_wajib';
if ($rawTab === 'kas') $rawTab = 'laporan_hasil_usaha';

$tab = array_key_exists($rawTab, $validTabs) ? $rawTab : 'simpanan_wajib';
$pageTitle = $validTabs[$tab];
$pageSubtitle = 'Laporan keuangan dan pertanggungjawaban operasional koperasi';

// Periode tanggal & Tahun Pembukuan
$tglMulaiRaw = $_GET['tgl_mulai'] ?? date('Y-01-01');
$tglSelesaiRaw = $_GET['tgl_selesai'] ?? date('Y-12-31');
$tglMulai = parse_dmy_to_ymd($tglMulaiRaw);
$tglSelesai = parse_dmy_to_ymd($tglSelesaiRaw);
$tahunBuku = (int)date('Y', strtotime($tglMulai));

$monthNames = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

// Helper: Hitung bunga manasuka 3%/tahun dihitung harian sesuai lama saldo mengendap
if (!function_exists('hitung_bunga_manasuka_harian')) {
    function hitung_bunga_manasuka_harian($db, $anggotaId, $startDate, $endDate, $rateTahunan = 0.03) {
        // 1. Saldo awal sebelum startDate
        $stmtAwal = $db->prepare("
            SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) 
            FROM simpanan 
            WHERE anggota_id = ? AND jenis_simpanan IN ('sukarela', 'manasuka') AND tanggal < ?
        ");
        $stmtAwal->execute([$anggotaId, $startDate]);
        $currentBalance = (float)$stmtAwal->fetchColumn();

        // 2. Transaksi sukarela/manasuka selama rentang
        $stmtMutasi = $db->prepare("
            SELECT tanggal, tipe_transaksi, nominal 
            FROM simpanan 
            WHERE anggota_id = ? AND jenis_simpanan IN ('sukarela', 'manasuka') AND tanggal BETWEEN ? AND ?
            ORDER BY tanggal ASC, id ASC
        ");
        $stmtMutasi->execute([$anggotaId, $startDate, $endDate]);
        $mutasiRows = $stmtMutasi->fetchAll(PDO::FETCH_ASSOC);

        $tglCurrent = new DateTime($startDate);
        $tglEnd = new DateTime($endDate);
        $totalBunga = 0.0;
        $dailyRate = $rateTahunan / 365.0;

        foreach ($mutasiRows as $mut) {
            $tglMut = new DateTime($mut['tanggal']);
            if ($tglMut > $tglCurrent) {
                $diffDays = $tglCurrent->diff($tglMut)->days;
                if ($currentBalance > 0 && $diffDays > 0) {
                    $totalBunga += $currentBalance * $dailyRate * $diffDays;
                }
                $tglCurrent = clone $tglMut;
            }
            if ($mut['tipe_transaksi'] === 'setor') {
                $currentBalance += (float)$mut['nominal'];
            } else {
                $currentBalance -= (float)$mut['nominal'];
            }
        }

        // Sisa hari hingga akhir periode (inklusif)
        if ($tglEnd >= $tglCurrent) {
            $diffDays = $tglCurrent->diff($tglEnd)->days + 1;
            if ($currentBalance > 0 && $diffDays > 0) {
                $totalBunga += $currentBalance * $dailyRate * $diffDays;
            }
        }

        return $totalBunga;
    }
}

// =========================================================================
// 2. DATA LOAD & KALKULASI BERDASARKAN TAB AKTIF
// =========================================================================

// A. SIMPANAN WAJIB
$dataSimpananWajib = [];
$totWajibAwal = 0; $totWajibSetor = 0; $totWajibTarik = 0; $totWajibAkhir = 0;
if ($tab === 'simpanan_wajib' || isset($_GET['export'])) {
    $stmtSW = $db->prepare("
        SELECT a.id, a.no_anggota, a.nama, a.pekerjaan, a.no_telp,
               COALESCE(SUM(CASE WHEN s.jenis_simpanan = 'wajib' AND s.tanggal < ? AND s.tipe_transaksi = 'setor' THEN s.nominal
                                 WHEN s.jenis_simpanan = 'wajib' AND s.tanggal < ? AND s.tipe_transaksi = 'tarik' THEN -s.nominal
                                 ELSE 0 END), 0) AS saldo_awal,
               COALESCE(SUM(CASE WHEN s.jenis_simpanan = 'wajib' AND s.tanggal BETWEEN ? AND ? AND s.tipe_transaksi = 'setor' THEN s.nominal
                                 ELSE 0 END), 0) AS setor_periode,
               COALESCE(SUM(CASE WHEN s.jenis_simpanan = 'wajib' AND s.tanggal BETWEEN ? AND ? AND s.tipe_transaksi = 'tarik' THEN s.nominal
                                 ELSE 0 END), 0) AS tarik_periode
        FROM anggota a
        LEFT JOIN simpanan s ON a.id = s.anggota_id AND s.jenis_simpanan = 'wajib'
        GROUP BY a.id
        ORDER BY a.no_anggota ASC
    ");
    $stmtSW->execute([$tglMulai, $tglMulai, $tglMulai, $tglSelesai, $tglMulai, $tglSelesai]);
    $dataSimpananWajib = $stmtSW->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dataSimpananWajib as &$sw) {
        $sw['saldo_akhir'] = $sw['saldo_awal'] + $sw['setor_periode'] - $sw['tarik_periode'];
        $totWajibAwal += $sw['saldo_awal'];
        $totWajibSetor += $sw['setor_periode'];
        $totWajibTarik += $sw['tarik_periode'];
        $totWajibAkhir += $sw['saldo_akhir'];
    }
    unset($sw);
}

// B. SIMPANAN MANASUKA (SUKARELA)
$dataSimpananManasuka = [];
$totManasukaAwal = 0; $totManasukaSetor = 0; $totManasukaTarik = 0; $totManasukaAkhir = 0;
if ($tab === 'simpanan_manasuka' || isset($_GET['export'])) {
    $stmtSM = $db->prepare("
        SELECT a.id, a.no_anggota, a.nama, a.pekerjaan, a.no_telp,
               COALESCE(SUM(CASE WHEN s.jenis_simpanan IN ('sukarela', 'manasuka') AND s.tanggal < ? AND s.tipe_transaksi = 'setor' THEN s.nominal
                                 WHEN s.jenis_simpanan IN ('sukarela', 'manasuka') AND s.tanggal < ? AND s.tipe_transaksi = 'tarik' THEN -s.nominal
                                 ELSE 0 END), 0) AS saldo_awal,
               COALESCE(SUM(CASE WHEN s.jenis_simpanan IN ('sukarela', 'manasuka') AND s.tanggal BETWEEN ? AND ? AND s.tipe_transaksi = 'setor' THEN s.nominal
                                 ELSE 0 END), 0) AS setor_periode,
               COALESCE(SUM(CASE WHEN s.jenis_simpanan IN ('sukarela', 'manasuka') AND s.tanggal BETWEEN ? AND ? AND s.tipe_transaksi = 'tarik' THEN s.nominal
                                 ELSE 0 END), 0) AS tarik_periode
        FROM anggota a
        LEFT JOIN simpanan s ON a.id = s.anggota_id AND s.jenis_simpanan IN ('sukarela', 'manasuka')
        GROUP BY a.id
        ORDER BY a.no_anggota ASC
    ");
    $stmtSM->execute([$tglMulai, $tglMulai, $tglMulai, $tglSelesai, $tglMulai, $tglSelesai]);
    $dataSimpananManasuka = $stmtSM->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dataSimpananManasuka as &$sm) {
        $sm['saldo_akhir'] = $sm['saldo_awal'] + $sm['setor_periode'] - $sm['tarik_periode'];
        $totManasukaAwal += $sm['saldo_awal'];
        $totManasukaSetor += $sm['setor_periode'];
        $totManasukaTarik += $sm['tarik_periode'];
        $totManasukaAkhir += $sm['saldo_akhir'];
    }
    unset($sm);
}

// C. LAPORAN HASIL USAHA (LABA / RUGI) - BULANAN JANUARI S/D DESEMBER
// Kategori diambil dari pengaturan.php?tab=labarugi, isi dihitung dari input transaksi
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
$lrConfig = $rawLr ? json_decode($rawLr, true) : $defaultLr;
if (!is_array($lrConfig) || empty($lrConfig)) $lrConfig = $defaultLr;

// Preload seluruh data angsuran dan buku_kas dalam 1 tahun buku
$stmtAngsuranTahun = $db->prepare("SELECT tanggal_bayar, angsuran_bunga FROM angsuran WHERE tanggal_bayar BETWEEN ? AND ?");
$stmtAngsuranTahun->execute(["$tahunBuku-01-01", "$tahunBuku-12-31"]);
$rowsAngsuranTahun = $stmtAngsuranTahun->fetchAll(PDO::FETCH_ASSOC);

$bungaAngsuranPerBulan = array_fill(1, 12, 0.0);
foreach ($rowsAngsuranTahun as $ra) {
    $m = (int)date('n', strtotime($ra['tanggal_bayar']));
    $bungaAngsuranPerBulan[$m] += (float)$ra['angsuran_bunga'];
}

$stmtBKTahun = $db->prepare("SELECT tanggal, kategori, jenis_transaksi, kas_masuk, kas_keluar FROM buku_kas WHERE tanggal BETWEEN ? AND ?");
$stmtBKTahun->execute(["$tahunBuku-01-01", "$tahunBuku-12-31"]);
$rowsBKTahun = $stmtBKTahun->fetchAll(PDO::FETCH_ASSOC);

// Hitung data Pendapatan Bulanan
$listPendapatanBulanan = [];
$subtotalPendBln = array_fill(1, 12, 0.0);
$totalHasilPendapatan = 0;

foreach ($lrConfig['pendapatan'] ?? [] as $p) {
    $kode = trim($p['kode'] ?? '');
    $nama = trim($p['nama'] ?? '');
    if ($kode === '' && $nama === '') continue;

    $blnData = array_fill(1, 12, 0.0);

    // Jika Pendapatan Bunga Pinjaman (A.1), akumulasikan angsuran bunga
    if ($kode === 'A.1' || stripos($nama, 'bunga pinjaman') !== false) {
        for ($m = 1; $m <= 12; $m++) {
            $blnData[$m] += $bungaAngsuranPerBulan[$m];
        }
    }

    // Ambil dari buku_kas
    foreach ($rowsBKTahun as $bk) {
        $kat = $bk['kategori'];
        if ((strpos($kat, "[$kode]") !== false) || (stripos($kat, $nama) !== false)) {
            $m = (int)date('n', strtotime($bk['tanggal']));
            $blnData[$m] += (float)$bk['kas_masuk'];
        }
    }

    $totAkun = array_sum($blnData);
    for ($m = 1; $m <= 12; $m++) {
        $subtotalPendBln[$m] += $blnData[$m];
    }
    $totalHasilPendapatan += $totAkun;

    $listPendapatanBulanan[] = [
        'kode'  => $kode,
        'nama'  => $nama,
        'bln'   => $blnData,
        'total' => $totAkun
    ];
}

// Hitung data Beban Bulanan
$listPengeluaranBulanan = [];
$subtotalBebanBln = array_fill(1, 12, 0.0);
$totalHasilPengeluaran = 0;

foreach ($lrConfig['pengeluaran'] ?? [] as $p) {
    $kode = trim($p['kode'] ?? '');
    $nama = trim($p['nama'] ?? '');
    if ($kode === '' && $nama === '') continue;

    $blnData = array_fill(1, 12, 0.0);

    foreach ($rowsBKTahun as $bk) {
        $kat = $bk['kategori'];
        if ((strpos($kat, "[$kode]") !== false) || (stripos($kat, $nama) !== false)) {
            $m = (int)date('n', strtotime($bk['tanggal']));
            $blnData[$m] += (float)$bk['kas_keluar'];
        }
    }

    $totAkun = array_sum($blnData);
    for ($m = 1; $m <= 12; $m++) {
        $subtotalBebanBln[$m] += $blnData[$m];
    }
    $totalHasilPengeluaran += $totAkun;

    $listPengeluaranBulanan[] = [
        'kode'  => $kode,
        'nama'  => $nama,
        'bln'   => $blnData,
        'total' => $totAkun
    ];
}

$shuBulanan = array_fill(1, 12, 0.0);
for ($m = 1; $m <= 12; $m++) {
    $shuBulanan[$m] = $subtotalPendBln[$m] - $subtotalBebanBln[$m];
}
$shuTahunBerjalan = $totalHasilPendapatan - $totalHasilPengeluaran;
$marginShu = $totalHasilPendapatan > 0 ? ($shuTahunBerjalan / $totalHasilPendapatan) * 100 : 0;

// D. NERACA SALDO BERJALAN
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
$neracaConfig = $rawNeraca ? json_decode($rawNeraca, true) : $defaultNeraca;
if (!is_array($neracaConfig) || empty($neracaConfig)) $neracaConfig = $defaultNeraca;

$neracaCalculated = [];
$totalAktivaNeraca = 0;
$totalPasivaNeraca = 0;

foreach ($neracaConfig as $grpKey => $grp) {
    $grpItems = [];
    $grpSubtotal = 0;

    foreach ($grp['items'] ?? [] as $item) {
        $kode = trim($item['kode'] ?? '');
        $nama = trim($item['nama'] ?? '');
        $namaLower = strtolower($nama);
        $saldoAwal = (float)($item['saldo'] ?? 0);
        $isKontra = !empty($item['is_kontra']);
        $saldoReal = $saldoAwal;

        if ($grpKey === 'A1') {
            if ($kode === 'A.1.1' || strpos($namaLower, 'simpan pinjam') !== false || strpos($namaLower, 'kas sp') !== false) {
                $mutasiSP = (float)$db->query("
                    SELECT (
                        (SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE tanggal <= '$tglSelesai') +
                        (SELECT COALESCE(SUM(nominal), 0) FROM simpanan_pendidikan_setoran WHERE tanggal_bayar <= '$tglSelesai') -
                        (SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman WHERE tanggal_pencairan <= '$tglSelesai' AND status IN ('berjalan', 'lunas')) +
                        (SELECT COALESCE(SUM(jumlah_bayar), 0) FROM angsuran WHERE tanggal_bayar <= '$tglSelesai') +
                        (SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE tipe_kas = 'kas_sp' AND tanggal <= '$tglSelesai')
                    )
                ")->fetchColumn();
                $saldoReal = $saldoAwal + $mutasiSP;
            } elseif ($kode === 'A.1.2' || strpos($namaLower, 'pusat') !== false || strpos($namaLower, 'kas umum') !== false) {
                $mutasiKU = (float)$db->query("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE tipe_kas = 'kas_umum' AND tanggal <= '$tglSelesai'")->fetchColumn();
                $saldoReal = $saldoAwal + $mutasiKU;
            } elseif ($kode === 'A.1.3' || strpos($namaLower, 'bank') !== false) {
                $mutasiKB = (float)$db->query("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE tipe_kas = 'kas_bank' AND tanggal <= '$tglSelesai'")->fetchColumn();
                $saldoReal = $saldoAwal + $mutasiKB;
            } elseif ($kode === 'A.1.4' || strpos($namaLower, 'toko') !== false) {
                $mutasiKT = (float)$db->query("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE tipe_kas = 'kas_toko' AND tanggal <= '$tglSelesai'")->fetchColumn();
                $saldoReal = $saldoAwal + $mutasiKT;
            } elseif ($kode === 'A.1.5' || strpos($namaLower, 'piutang') !== false) {
                $totCair = (float)$db->query("SELECT COALESCE(SUM(jumlah_pinjaman), 0) FROM pinjaman WHERE tanggal_pencairan <= '$tglSelesai' AND status IN ('berjalan', 'lunas')")->fetchColumn();
                $totPokok = (float)$db->query("SELECT COALESCE(SUM(angsuran_pokok), 0) FROM angsuran WHERE tanggal_bayar <= '$tglSelesai'")->fetchColumn();
                $saldoReal = $saldoAwal + max(0, $totCair - $totPokok);
            } else {
                $mutasiLain = (float)$db->query("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE (kategori LIKE '%[$kode]%' OR kategori LIKE '%$nama%') AND tanggal <= '$tglSelesai'")->fetchColumn();
                $saldoReal = $saldoAwal + $mutasiLain;
            }
        } elseif ($grpKey === 'A2') {
            $mutasiAT = (float)$db->query("SELECT COALESCE(SUM(kas_keluar - kas_masuk), 0) FROM buku_kas WHERE (kategori LIKE '%[$kode]%' OR kategori LIKE '%$nama%') AND tanggal <= '$tglSelesai'")->fetchColumn();
            $saldoReal = $saldoAwal + $mutasiAT;
        } elseif ($grpKey === 'B1') {
            if (strpos($namaLower, 'manasuka') !== false || strpos($namaLower, 'sukarela') !== false) {
                $saldoReal = $saldoAwal + (float)$db->query("SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE jenis_simpanan IN ('sukarela', 'manasuka') AND tanggal <= '$tglSelesai'")->fetchColumn();
            } elseif (strpos($namaLower, 'pendidikan') !== false) {
                $saldoReal = $saldoAwal + (float)$db->query("SELECT COALESCE(SUM(nominal), 0) FROM simpanan_pendidikan_setoran WHERE tanggal_bayar <= '$tglSelesai'")->fetchColumn();
            } else {
                $mutasiKew = (float)$db->query("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE (kategori LIKE '%[$kode]%' OR kategori LIKE '%$nama%') AND tanggal <= '$tglSelesai'")->fetchColumn();
                $saldoReal = $saldoAwal + $mutasiKew;
            }
        } elseif ($grpKey === 'B2') {
            $mutasiDana = (float)$db->query("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE (kategori LIKE '%[$kode]%' OR kategori LIKE '%$nama%') AND tanggal <= '$tglSelesai'")->fetchColumn();
            $saldoReal = $saldoAwal + $mutasiDana;
        } elseif ($grpKey === 'B3') {
            if (strpos($namaLower, 'simpanan pokok') !== false) {
                $saldoReal = $saldoAwal + (float)$db->query("SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE jenis_simpanan = 'pokok' AND tanggal <= '$tglSelesai'")->fetchColumn();
            } elseif (strpos($namaLower, 'simpanan wajib') !== false) {
                $saldoReal = $saldoAwal + (float)$db->query("SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE jenis_simpanan = 'wajib' AND tanggal <= '$tglSelesai'")->fetchColumn();
            } else {
                $mutasiMod = (float)$db->query("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE (kategori LIKE '%[$kode]%' OR kategori LIKE '%$nama%') AND tanggal <= '$tglSelesai'")->fetchColumn();
                $saldoReal = $saldoAwal + $mutasiMod;
            }
        } elseif ($grpKey === 'B4') {
            if ($kode === 'B.4.2' || strpos($namaLower, 'tahun berjalan') !== false) {
                $saldoReal = $shuTahunBerjalan;
            } else {
                $saldoReal = $saldoAwal;
            }
        }

        $grpItems[] = [
            'kode'      => $kode,
            'nama'      => $nama,
            'saldo'     => $saldoReal,
            'is_kontra' => $isKontra
        ];

        if ($grpKey === 'A1') {
            $totalAktivaNeraca += $saldoReal;
            $grpSubtotal += $saldoReal;
        } elseif ($grpKey === 'A2') {
            if ($isKontra) {
                $totalAktivaNeraca -= $saldoReal;
                $grpSubtotal -= $saldoReal;
            } else {
                $totalAktivaNeraca += $saldoReal;
                $grpSubtotal += $saldoReal;
            }
        } else {
            $totalPasivaNeraca += $saldoReal;
            $grpSubtotal += $saldoReal;
        }
    }

    $neracaCalculated[$grpKey] = [
        'nama_kategori' => $grp['nama_kategori'] ?? $grpKey,
        'subtotal'      => $grpSubtotal,
        'items'         => $grpItems
    ];
}

$selisihNeraca = abs($totalAktivaNeraca - $totalPasivaNeraca);
$isNeracaBalance = ($selisihNeraca < 1.0);

// E. PENGATURAN PEMBAGIAN SHU RESMI (AD/ART)
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
$shuListConfig = $rawShu ? json_decode($rawShu, true) : $defaultShu;
if (!is_array($shuListConfig) || empty($shuListConfig)) $shuListConfig = $defaultShu;

$shuPembagianCalculated = [];
$totalAlokasiShuRp = 0;
$totalPersenShu = 0;
$alokasiJasaModalRp = 0;
$alokasiJasaUsahaRp = 0;
$dasarShuBagi = max(0, $shuTahunBerjalan);

foreach ($shuListConfig as $pos) {
    $persen = (float)($pos['persen'] ?? 0);
    $nominalAlokasi = ($persen / 100) * $dasarShuBagi;
    $totalAlokasiShuRp += $nominalAlokasi;
    $totalPersenShu += $persen;

    if ($pos['id'] == 1) $alokasiJasaModalRp = $nominalAlokasi;
    if ($pos['id'] == 2) $alokasiJasaUsahaRp = $nominalAlokasi;

    $shuPembagianCalculated[] = [
        'id'      => $pos['id'] ?? 0,
        'nama'    => $pos['nama'] ?? '',
        'persen'  => $persen,
        'nominal' => $nominalAlokasi
    ];
}

// F. REKAP SHU PER ANGGOTA (POKOK, WAJIB, JASA PINJAMAN, BUNGA MANASUKA 3%/THN)
$dataRekapShuAnggota = [];
$totAllSP = 0; $totAllSHUSP = 0;
$totAllSW = 0; $totAllSHUSW = 0;
$totAllJP = 0; $totAllSHUJP = 0;
$totAllBungaManasuka = 0;
$totAllSHUAnggota = 0;

if ($tab === 'rekap_shu' || $tab === 'pembagian_shu' || isset($_GET['export'])) {
    // Total Pokok & Wajib seluruh anggota s/d akhir periode
    $totPokokAll = (float)$db->query("SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE jenis_simpanan = 'pokok' AND tanggal <= '$tglSelesai'")->fetchColumn();
    $totWajibAll = (float)$db->query("SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE jenis_simpanan = 'wajib' AND tanggal <= '$tglSelesai'")->fetchColumn();
    $totPokokWajibAll = $totPokokAll + $totWajibAll;

    // Total Jasa Pinjaman (Bunga Angsuran) seluruh anggota s/d akhir periode
    $totBungaPinjamanAll = (float)$db->query("SELECT COALESCE(SUM(angsuran_bunga), 0) FROM angsuran WHERE tanggal_bayar <= '$tglSelesai'")->fetchColumn();

    // Query anggota
    $anggotaList = $db->query("SELECT id, no_anggota, nama, pekerjaan FROM anggota ORDER BY no_anggota ASC")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($anggotaList as $a) {
        $aid = $a['id'];

        // 1. Simpanan Pokok Anggota
        $sp = (float)$db->query("SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE anggota_id = $aid AND jenis_simpanan = 'pokok' AND tanggal <= '$tglSelesai'")->fetchColumn();

        // 2. Simpanan Wajib Anggota
        $sw = (float)$db->query("SELECT COALESCE(SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END), 0) FROM simpanan WHERE anggota_id = $aid AND jenis_simpanan = 'wajib' AND tanggal <= '$tglSelesai'")->fetchColumn();

        // 3. Jasa Pinjaman (Bunga yang dibayar anggota)
        $jp = (float)$db->query("
            SELECT COALESCE(SUM(ag.angsuran_bunga), 0) 
            FROM angsuran ag 
            JOIN pinjaman p ON ag.pinjaman_id = p.id
            WHERE p.anggota_id = $aid AND ag.tanggal_bayar <= '$tglSelesai'
        ")->fetchColumn();

        // SHU Simpanan Pokok = sp * jasa_modal / (total_pokok + total_wajib)
        $shuSP = ($totPokokWajibAll > 0) ? ($sp * $alokasiJasaModalRp / $totPokokWajibAll) : 0;

        // SHU Simpanan Wajib = sw * jasa_modal / (total_pokok + total_wajib)
        $shuSW = ($totPokokWajibAll > 0) ? ($sw * $alokasiJasaModalRp / $totPokokWajibAll) : 0;

        // SHU Jasa Pinjaman = jp * jasa_usaha / total_jasa_pinjaman
        $shuJP = ($totBungaPinjamanAll > 0) ? ($jp * $alokasiJasaUsahaRp / $totBungaPinjamanAll) : 0;

        // Bunga Manasuka = 3%/tahun dihitung harian
        $bungaManasuka = hitung_bunga_manasuka_harian($db, $aid, "$tahunBuku-01-01", "$tahunBuku-12-31", 0.03);

        // Total SHU Diterima Anggota
        $totalDiterima = $shuSP + $shuSW + $shuJP + $bungaManasuka;

        $dataRekapShuAnggota[] = [
            'id'             => $aid,
            'no_anggota'     => $a['no_anggota'],
            'nama'           => $a['nama'],
            'pekerjaan'      => $a['pekerjaan'],
            'sp'             => $sp,
            'shu_sp'         => $shuSP,
            'sw'             => $sw,
            'shu_sw'         => $shuSW,
            'jp'             => $jp,
            'shu_jp'         => $shuJP,
            'bunga_manasuka' => $bungaManasuka,
            'total_diterima' => $totalDiterima
        ];

        $totAllSP += $sp;
        $totAllSHUSP += $shuSP;
        $totAllSW += $sw;
        $totAllSHUSW += $shuSW;
        $totAllJP += $jp;
        $totAllSHUJP += $shuJP;
        $totAllBungaManasuka += $bungaManasuka;
        $totAllSHUAnggota += $totalDiterima;
    }
}

// =========================================================================
// 3. EXPORT EXCEL (CSV) PER TAB
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    ini_set('display_errors', '0');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_' . ucfirst($tab) . '_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8

    fputcsv($out, ['KOPERASI SIMPAN PINJAM GATRA TEKNIKA'], ';', '"', '\\');
    fputcsv($out, [strtoupper($validTabs[$tab])], ';', '"', '\\');
    fputcsv($out, ['Periode: ' . tgl_dmy($tglMulai) . ' s/d ' . tgl_dmy($tglSelesai) . ' (Tahun ' . $tahunBuku . ')'], ';', '"', '\\');
    fputcsv($out, ['Tanggal Unduh: ' . tgl_indo(date('Y-m-d'), true)], ';', '"', '\\');
    fputcsv($out, [], ';', '"', '\\');

    if ($tab === 'simpanan_wajib') {
        fputcsv($out, ['No', 'No. Anggota', 'Nama Anggota', 'Pekerjaan', 'No. Telp', 'Saldo Awal', 'Setoran Periode', 'Penarikan Periode', 'Saldo Akhir'], ';', '"', '\\');
        $no = 1;
        foreach ($dataSimpananWajib as $r) {
            fputcsv($out, [$no++, $r['no_anggota'], $r['nama'], $r['pekerjaan'], $r['no_telp'], $r['saldo_awal'], $r['setor_periode'], $r['tarik_periode'], $r['saldo_akhir']], ';', '"', '\\');
        }
        fputcsv($out, ['TOTAL KESELURUHAN', '', '', '', '', $totWajibAwal, $totWajibSetor, $totWajibTarik, $totWajibAkhir], ';', '"', '\\');
    } elseif ($tab === 'simpanan_manasuka') {
        fputcsv($out, ['No', 'No. Anggota', 'Nama Anggota', 'Pekerjaan', 'No. Telp', 'Saldo Awal', 'Setoran Periode', 'Penarikan Periode', 'Saldo Akhir'], ';', '"', '\\');
        $no = 1;
        foreach ($dataSimpananManasuka as $r) {
            fputcsv($out, [$no++, $r['no_anggota'], $r['nama'], $r['pekerjaan'], $r['no_telp'], $r['saldo_awal'], $r['setor_periode'], $r['tarik_periode'], $r['saldo_akhir']], ';', '"', '\\');
        }
        fputcsv($out, ['TOTAL KESELURUHAN', '', '', '', '', $totManasukaAwal, $totManasukaSetor, $totManasukaTarik, $totManasukaAkhir], ';', '"', '\\');
    } elseif ($tab === 'laporan_hasil_usaha') {
        $header = ['Kode Akun', 'Nama Rekening Pendapatan & Beban'];
        for ($m = 1; $m <= 12; $m++) $header[] = $monthNames[$m];
        $header[] = 'Total (Rp)';
        fputcsv($out, $header, ';', '"', '\\');

        fputcsv($out, ['I. PENDAPATAN USAHA', ''], ';', '"', '\\');
        foreach ($listPendapatanBulanan as $p) {
            $row = [$p['kode'], $p['nama']];
            for ($m = 1; $m <= 12; $m++) $row[] = $p['bln'][$m];
            $row[] = $p['total'];
            fputcsv($out, $row, ';', '"', '\\');
        }
        $rowSubPend = ['', 'TOTAL PENDAPATAN USAHA'];
        for ($m = 1; $m <= 12; $m++) $rowSubPend[] = $subtotalPendBln[$m];
        $rowSubPend[] = $totalHasilPendapatan;
        fputcsv($out, $rowSubPend, ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');

        fputcsv($out, ['II. BEBAN OPERASIONAL', ''], ';', '"', '\\');
        foreach ($listPengeluaranBulanan as $p) {
            $row = [$p['kode'], $p['nama']];
            for ($m = 1; $m <= 12; $m++) $row[] = $p['bln'][$m];
            $row[] = $p['total'];
            fputcsv($out, $row, ';', '"', '\\');
        }
        $rowSubBeban = ['', 'TOTAL BEBAN OPERASIONAL'];
        for ($m = 1; $m <= 12; $m++) $rowSubBeban[] = $subtotalBebanBln[$m];
        $rowSubBeban[] = $totalHasilPengeluaran;
        fputcsv($out, $rowSubBeban, ';', '"', '\\');
        fputcsv($out, [], ';', '"', '\\');

        $rowSHU = ['', 'SISA HASIL USAHA (SHU) BULANAN'];
        for ($m = 1; $m <= 12; $m++) $rowSHU[] = $shuBulanan[$m];
        $rowSHU[] = $shuTahunBerjalan;
        fputcsv($out, $rowSHU, ';', '"', '\\');
    } elseif ($tab === 'neraca') {
        fputcsv($out, ['NERACA KOPERASI SIMPAN PINJAM GATRA TEKNIKA'], ';', '"', '\\');
        fputcsv($out, ['Kode', 'Akun Neraca', 'Saldo (Rp)'], ';', '"', '\\');
        foreach ($neracaCalculated as $gk => $g) {
            fputcsv($out, [$gk, $g['nama_kategori'], $g['subtotal']], ';', '"', '\\');
            foreach ($g['items'] as $it) {
                fputcsv($out, ['  ' . $it['kode'], '  ' . $it['nama'] . ($it['is_kontra'] ? ' (Kontra)' : ''), $it['saldo']], ';', '"', '\\');
            }
        }
        fputcsv($out, [], ';', '"', '\\');
        fputcsv($out, ['TOTAL AKTIVA', '', $totalAktivaNeraca], ';', '"', '\\');
        fputcsv($out, ['TOTAL PASIVA', '', $totalPasivaNeraca], ';', '"', '\\');
        fputcsv($out, ['STATUS NERACA', '', $isNeracaBalance ? 'SEIMBANG (BALANCE)' : 'SELISIH: ' . rupiah($selisihNeraca)], ';', '"', '\\');
    } elseif ($tab === 'rekap_shu') {
        fputcsv($out, ['REKAPITULASI HAK SISA HASIL USAHA (SHU) PER ANGGOTA'], ';', '"', '\\');
        fputcsv($out, ['No', 'No. Anggota', 'Nama Anggota', 'Simp. Pokok', 'Simp. Wajib', 'Jasa Pinjaman', 'SHU Pokok', 'SHU Wajib', 'SHU Pinjaman', 'Bunga Manasuka', 'Total Diterima'], ';', '"', '\\');
        $no = 1;
        foreach ($dataRekapShuAnggota as $r) {
            fputcsv($out, [
                $no++,
                $r['no_anggota'],
                $r['nama'],
                $r['sp'],
                $r['sw'],
                $r['jp'],
                round($r['shu_sp']),
                round($r['shu_sw']),
                round($r['shu_jp']),
                round($r['bunga_manasuka']),
                round($r['total_diterima'])
            ], ';', '"', '\\');
        }
        fputcsv($out, [
            'TOTAL KESELURUHAN', '', '',
            $totAllSP,
            $totAllSW,
            $totAllJP,
            round($totAllSHUSP),
            round($totAllSHUSW),
            round($totAllSHUJP),
            round($totAllBungaManasuka),
            round($totAllSHUAnggota)
        ], ';', '"', '\\');
    } elseif ($tab === 'pembagian_shu') {
        fputcsv($out, ['ALOKASI POS PEMBAGIAN SHU RESMI (AD/ART)'], ';', '"', '\\');
        fputcsv($out, ['No', 'Pos Alokasi SHU', 'Persentase (%)', 'Alokasi Dana (Rp)'], ';', '"', '\\');
        $no = 1;
        foreach ($shuPembagianCalculated as $pos) {
            fputcsv($out, [$no++, $pos['nama'], $pos['persen'] . '%', $pos['nominal']], ';', '"', '\\');
        }
        fputcsv($out, ['TOTAL PEMBAGIAN SHU', '', '100%', $totalAlokasiShuRp], ';', '"', '\\');
    }

    fclose($out);
    exit;
}

$pageSelf = 'laporan.php';
$csvUrl = "laporan.php?tab=" . urlencode($tab) . "&tgl_mulai=" . urlencode($tglMulai) . "&tgl_selesai=" . urlencode($tglSelesai) . "&export=csv";

$activePage = 'laporan';
$activeTab = $tab;
include __DIR__ . '/header.php';
?>

<!-- Header Halaman & Action Buttons -->
<div class="row" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
  <div>
    <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--secondary); margin-bottom: 4px;">
      <?= htmlspecialchars($pageTitle) ?>
    </h2>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
      <?= htmlspecialchars($pageSubtitle) ?> &bull; Tahun Buku <strong><?= $tahunBuku ?></strong>
    </p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;" class="no-print">
    <a href="<?= $csvUrl ?>" class="btn btn-secondary" style="border: 1px solid #cbd5e1; background: #ffffff;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Unduh Excel (CSV)
    </a>
    <button type="button" class="btn btn-secondary" onclick="window.print()" style="border: 1px solid #cbd5e1; background: #ffffff;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Cetak / PDF
    </button>
  </div>
</div>

<!-- Print-Only Header Resmi Koperasi (Tanpa Kolom Tanda Tangan) -->
<div class="print-header">
  <h2 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 2px;">KOPERASI SIMPAN PINJAM GATRA TEKNIKA</h2>
  <p style="font-size: 0.88rem; margin-bottom: 4px;">Jl. By Pass Sunyaragi No. 45, Cirebon, Jawa Barat &bull; Telp: (0231) 482910</p>
  <div style="border-bottom: 2px solid #0f172a; margin: 12px 0;"></div>
  <p style="font-size: 1.05rem; font-weight: 800; text-transform: uppercase; margin: 4px 0;">
    <?= strtoupper($pageTitle) ?>
  </p>
  <p style="font-size: 0.85rem; color: #334155;">
    Periode Pembukuan: <strong><?= tgl_dmy($tglMulai) ?></strong> s/d <strong><?= tgl_dmy($tglSelesai) ?></strong> &bull; Tahun Buku <strong><?= $tahunBuku ?></strong> &bull; Dicetak: <?= tgl_indo(date('Y-m-d'), true) ?>
  </p>
</div>

<!-- Card Filter Parameter Periode (No-Print) -->
<div class="card no-print" style="margin-bottom: 24px;">
  <div class="card-header" style="padding: 12px 20px;">
    <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
      Filter Tahun & Periode Pembukuan
    </h3>
    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
      <a href="laporan.php?tab=<?= urlencode($tab) ?>&tgl_mulai=2026-01-01&tgl_selesai=2026-12-31" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Tahun 2026</a>
      <a href="laporan.php?tab=<?= urlencode($tab) ?>&tgl_mulai=2025-01-01&tgl_selesai=2025-12-31" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Tahun 2025</a>
      <a href="laporan.php?tab=<?= urlencode($tab) ?>&tgl_mulai=<?= date('Y-01-01') ?>&tgl_selesai=<?= date('Y-12-31') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Tahun <?= date('Y') ?></a>
      <a href="laporan.php?tab=<?= urlencode($tab) ?>&tgl_mulai=<?= date('Y-m-01') ?>&tgl_selesai=<?= date('Y-m-t') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Bulan Ini</a>
      <a href="laporan.php?tab=<?= urlencode($tab) ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Reset</a>
    </div>
  </div>

  <form method="GET" action="laporan.php" style="padding: 16px 20px;">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; align-items: end;">
      
      <!-- Dari Tanggal -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Dari Tanggal:</label>
        <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
          <input type="text" name="tgl_mulai" class="form-control date-dmy-input" value="<?= tgl_dmy($tglMulai) ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" style="padding-right: 36px; font-size: 0.86rem;">
          <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 2px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted);" title="Buka Kalender">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </button>
          <input type="date" class="native-date-hidden" value="<?= $tglMulai ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
        </div>
      </div>

      <!-- Sampai Tanggal -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Sampai Tanggal:</label>
        <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
          <input type="text" name="tgl_selesai" class="form-control date-dmy-input" value="<?= tgl_dmy($tglSelesai) ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" style="padding-right: 36px; font-size: 0.86rem;">
          <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 2px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted);" title="Buka Kalender">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </button>
          <input type="date" class="native-date-hidden" value="<?= $tglSelesai ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
        </div>
      </div>

      <!-- Tombol Submit -->
      <div style="margin: 0;">
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 9px 16px; font-size: 0.88rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          Tampilkan Laporan
        </button>
      </div>

    </div>
  </form>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: LAPORAN SIMPANAN WAJIB                                             -->
<!-- ========================================================================= -->
<?php if ($tab === 'simpanan_wajib'): ?>
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
      <div>
        <div class="stat-title">Saldo Awal Simpanan Wajib</div>
        <div class="stat-value text-blue"><?= rupiah($totWajibAwal) ?></div>
        <div class="stat-hint">Sebelum <?= tgl_dmy($tglMulai) ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Setoran Periode Ini</div>
        <div class="stat-value text-green">+ <?= rupiah($totWajibSetor) ?></div>
        <div class="stat-hint">Penerimaan simpanan wajib</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Penarikan Periode Ini</div>
        <div class="stat-value text-red">- <?= rupiah($totWajibTarik) ?></div>
        <div class="stat-hint">Penarikan anggota keluar</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Saldo Akhir Simpanan Wajib</div>
        <div class="stat-value text-blue" style="font-weight: 800;"><?= rupiah($totWajibAkhir) ?></div>
        <div class="stat-hint">Posisi per <?= tgl_dmy($tglSelesai) ?></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header no-print" style="flex-wrap: wrap; gap: 12px;">
      <div>
        <h3 style="margin: 0; font-weight: 800;">Daftar Rekap Simpanan Wajib per Anggota</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
          Total <strong><?= count($dataSimpananWajib) ?></strong> anggota koperasi terdaftar
        </p>
      </div>
      <div>
        <input type="text" class="form-control" style="width: 250px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari nama, no. anggota..." data-table-search="tableSimpananWajib">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table" id="tableSimpananWajib">
        <thead>
          <tr>
            <th style="width: 50px;">No</th>
            <th style="width: 140px;">No. Anggota</th>
            <th>Nama Anggota</th>
            <th>Pekerjaan / Unit</th>
            <th style="text-align: right; width: 150px;">Saldo Awal</th>
            <th style="text-align: right; width: 150px;">Setoran Periode</th>
            <th style="text-align: right; width: 140px;">Penarikan</th>
            <th style="text-align: right; width: 160px;">Saldo Akhir</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dataSimpananWajib)): ?>
            <tr><td colspan="8" style="text-align:center; padding: 36px; color: var(--text-subtle);">Belum ada data simpanan wajib anggota.</td></tr>
          <?php else: ?>
            <?php $no = 1; foreach ($dataSimpananWajib as $sw): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars($sw['no_anggota']) ?></strong></td>
                <td>
                  <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($sw['nama']) ?></div>
                  <small style="color: var(--text-muted);"><?= htmlspecialchars($sw['no_telp'] ?? '-') ?></small>
                </td>
                <td><span class="badge badge-neutral"><?= htmlspecialchars($sw['pekerjaan'] ?? '-') ?></span></td>
                <td class="table-amount"><?= rupiah($sw['saldo_awal']) ?></td>
                <td class="table-amount <?= $sw['setor_periode'] > 0 ? 'text-green' : '' ?>" style="font-weight: 600;">
                  <?= $sw['setor_periode'] > 0 ? rupiah($sw['setor_periode']) : '-' ?>
                </td>
                <td class="table-amount <?= $sw['tarik_periode'] > 0 ? 'text-red' : '' ?>">
                  <?= $sw['tarik_periode'] > 0 ? rupiah($sw['tarik_periode']) : '-' ?>
                </td>
                <td class="table-amount text-blue" style="font-weight: 800;">
                  <?= rupiah($sw['saldo_akhir']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1;">
            <td colspan="4" style="padding: 14px 18px; text-transform: uppercase;">TOTAL KESELURUHAN SIMPANAN WAJIB</td>
            <td class="table-amount"><?= rupiah($totWajibAwal) ?></td>
            <td class="table-amount text-green"><?= rupiah($totWajibSetor) ?></td>
            <td class="table-amount text-red"><?= rupiah($totWajibTarik) ?></td>
            <td class="table-amount text-blue" style="font-size: 1.05rem;"><?= rupiah($totWajibAkhir) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

<!-- ========================================================================= -->
<!-- TAB 2: LAPORAN SIMPANAN MANASUKA (SUKARELA)                              -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'simpanan_manasuka'): ?>
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
      <div>
        <div class="stat-title">Saldo Awal Manasuka</div>
        <div class="stat-value text-blue"><?= rupiah($totManasukaAwal) ?></div>
        <div class="stat-hint">Sebelum <?= tgl_dmy($tglMulai) ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Setoran Periode Ini</div>
        <div class="stat-value text-green">+ <?= rupiah($totManasukaSetor) ?></div>
        <div class="stat-hint">Tabungan sukarela masuk</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Penarikan Periode Ini</div>
        <div class="stat-value text-red">- <?= rupiah($totManasukaTarik) ?></div>
        <div class="stat-hint">Pengambilan sukarela</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Saldo Akhir Manasuka</div>
        <div class="stat-value text-blue" style="font-weight: 800;"><?= rupiah($totManasukaAkhir) ?></div>
        <div class="stat-hint">Kewajiban lancar koperasi</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header no-print" style="flex-wrap: wrap; gap: 12px;">
      <div>
        <h3 style="margin: 0; font-weight: 800;">Daftar Rekap Simpanan Manasuka (Sukarela) per Anggota</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
          Total <strong><?= count($dataSimpananManasuka) ?></strong> anggota terdaftar
        </p>
      </div>
      <div>
        <input type="text" class="form-control" style="width: 250px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari nama, no. anggota..." data-table-search="tableSimpananManasuka">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table" id="tableSimpananManasuka">
        <thead>
          <tr>
            <th style="width: 50px;">No</th>
            <th style="width: 140px;">No. Anggota</th>
            <th>Nama Anggota</th>
            <th>Pekerjaan / Unit</th>
            <th style="text-align: right; width: 150px;">Saldo Awal</th>
            <th style="text-align: right; width: 150px;">Setoran Periode</th>
            <th style="text-align: right; width: 140px;">Penarikan</th>
            <th style="text-align: right; width: 160px;">Saldo Akhir</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dataSimpananManasuka)): ?>
            <tr><td colspan="8" style="text-align:center; padding: 36px; color: var(--text-subtle);">Belum ada data simpanan manasuka anggota.</td></tr>
          <?php else: ?>
            <?php $no = 1; foreach ($dataSimpananManasuka as $sm): ?>
              <tr>
                <td><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars($sm['no_anggota']) ?></strong></td>
                <td>
                  <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($sm['nama']) ?></div>
                  <small style="color: var(--text-muted);"><?= htmlspecialchars($sm['no_telp'] ?? '-') ?></small>
                </td>
                <td><span class="badge badge-neutral"><?= htmlspecialchars($sm['pekerjaan'] ?? '-') ?></span></td>
                <td class="table-amount"><?= rupiah($sm['saldo_awal']) ?></td>
                <td class="table-amount <?= $sm['setor_periode'] > 0 ? 'text-green' : '' ?>" style="font-weight: 600;">
                  <?= $sm['setor_periode'] > 0 ? rupiah($sm['setor_periode']) : '-' ?>
                </td>
                <td class="table-amount <?= $sm['tarik_periode'] > 0 ? 'text-red' : '' ?>">
                  <?= $sm['tarik_periode'] > 0 ? rupiah($sm['tarik_periode']) : '-' ?>
                </td>
                <td class="table-amount text-blue" style="font-weight: 800;">
                  <?= rupiah($sm['saldo_akhir']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1;">
            <td colspan="4" style="padding: 14px 18px; text-transform: uppercase;">TOTAL KESELURUHAN SIMPANAN MANASUKA</td>
            <td class="table-amount"><?= rupiah($totManasukaAwal) ?></td>
            <td class="table-amount text-green"><?= rupiah($totManasukaSetor) ?></td>
            <td class="table-amount text-red"><?= rupiah($totManasukaTarik) ?></td>
            <td class="table-amount text-blue" style="font-size: 1.05rem;"><?= rupiah($totManasukaAkhir) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

<!-- ========================================================================= -->
<!-- TAB 3: LAPORAN HASIL USAHA (LABA / RUGI) - PER BULAN JANUARI - DESEMBER  -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'laporan_hasil_usaha'): ?>
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
      <div>
        <div class="stat-title">Total Pendapatan Usaha (Tahun <?= $tahunBuku ?>)</div>
        <div class="stat-value text-green"><?= rupiah($totalHasilPendapatan) ?></div>
        <div class="stat-hint">Bunga SP + Penjualan + Jasa + Bank</div>
      </div>
      <div class="stat-icon green">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Total Beban Usaha (Tahun <?= $tahunBuku ?>)</div>
        <div class="stat-value text-red"><?= rupiah($totalHasilPengeluaran) ?></div>
        <div class="stat-hint">Beban operasional & belanja organisasi</div>
      </div>
      <div class="stat-icon red">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Sisa Hasil Usaha (SHU) Tahun <?= $tahunBuku ?></div>
        <div class="stat-value <?= $shuTahunBerjalan >= 0 ? 'text-green' : 'text-red' ?>" style="font-weight: 800; font-size: 1.6rem;">
          <?= rupiah($shuTahunBerjalan) ?>
        </div>
        <div class="stat-hint">Marjin Surplus: <strong><?= number_format($marginShu, 1, ',', '.') ?>%</strong></div>
      </div>
      <div class="stat-icon blue">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="flex-wrap: wrap; gap: 10px;">
      <div>
        <h3 style="margin: 0; font-weight: 800;">Laporan Perhitungan Hasil Usaha Bulanan (Tahun <?= $tahunBuku ?>)</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
          Rincian pendapatan dan beban per bulan (Januari s/d Desember) dari input transaksi riil.
        </p>
      </div>
    </div>

    <div class="table-freeze-container" style="overflow-x: auto; max-width: 100%;">
      <table class="table" style="min-width: 1300px; font-size: 0.82rem;">
        <thead>
          <tr style="background: #f8fafc; border-bottom: 2px solid #cbd5e1;">
            <th style="width: 70px; position: sticky; left: 0; background: #f8fafc; z-index: 2;">Kode</th>
            <th style="min-width: 220px; position: sticky; left: 70px; background: #f8fafc; z-index: 2; box-shadow: 2px 0 4px -2px rgba(0,0,0,0.1);">Uraian Rekening</th>
            <?php foreach ($monthNames as $mNum => $mLabel): ?>
              <th style="text-align: right; width: 85px; padding: 10px 8px;"><?= $mLabel ?></th>
            <?php endforeach; ?>
            <th style="text-align: right; width: 130px; background: #f1f5f9; font-weight: 800; padding: 10px 12px;">Total (Rp)</th>
          </tr>
        </thead>
        <tbody>
          
          <!-- I. PENDAPATAN USAHA -->
          <tr style="background: #f1f5f9; font-weight: 800;">
            <td style="position: sticky; left: 0; background: #f1f5f9; z-index: 2;">I</td>
            <td colspan="13" style="position: sticky; left: 70px; background: #f1f5f9; z-index: 2; color: var(--secondary);">
              I. PENDAPATAN USAHA
            </td>
          </tr>
          <?php foreach ($listPendapatanBulanan as $p): ?>
            <tr>
              <td style="position: sticky; left: 0; background: #fff; z-index: 1;">
                <span class="badge badge-success" style="font-size: 0.72rem;"><?= htmlspecialchars($p['kode']) ?></span>
              </td>
              <td style="position: sticky; left: 70px; background: #fff; z-index: 1; font-weight: 600; color: var(--text-main); box-shadow: 2px 0 4px -2px rgba(0,0,0,0.1);">
                <?= htmlspecialchars($p['nama']) ?>
              </td>
              <?php for ($m = 1; $m <= 12; $m++): 
                $val = $p['bln'][$m];
              ?>
                <td class="table-amount <?= $val > 0 ? 'text-green' : '' ?>" style="padding: 8px 6px;">
                  <?= $val > 0 ? number_format($val, 0, ',', '.') : '-' ?>
                </td>
              <?php endfor; ?>
              <td class="table-amount text-green" style="font-weight: 800; background: #f8fafc; padding: 8px 12px;">
                <?= $p['total'] > 0 ? rupiah($p['total']) : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr style="background: #ecfdf5; font-weight: 800; border-top: 1px solid #a7f3d0; border-bottom: 2px solid #a7f3d0;">
            <td style="position: sticky; left: 0; background: #ecfdf5; z-index: 1;"></td>
            <td style="position: sticky; left: 70px; background: #ecfdf5; z-index: 1; color: #065f46; box-shadow: 2px 0 4px -2px rgba(0,0,0,0.1);">
              SUBTOTAL PENDAPATAN (A)
            </td>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <td class="table-amount text-green" style="font-weight: 700; padding: 10px 6px;">
                <?= $subtotalPendBln[$m] > 0 ? number_format($subtotalPendBln[$m], 0, ',', '.') : '-' ?>
              </td>
            <?php endfor; ?>
            <td class="table-amount text-green" style="font-size: 0.95rem; font-weight: 900; background: #d1fae5; padding: 10px 12px;">
              <?= rupiah($totalHasilPendapatan) ?>
            </td>
          </tr>

          <!-- II. BEBAN OPERASIONAL -->
          <tr style="background: #f1f5f9; font-weight: 800;">
            <td style="position: sticky; left: 0; background: #f1f5f9; z-index: 2;">II</td>
            <td colspan="13" style="position: sticky; left: 70px; background: #f1f5f9; z-index: 2; color: var(--secondary); padding-top: 16px;">
              II. BEBAN OPERASIONAL & USAHA
            </td>
          </tr>
          <?php foreach ($listPengeluaranBulanan as $p): ?>
            <tr>
              <td style="position: sticky; left: 0; background: #fff; z-index: 1;">
                <span class="badge badge-danger" style="font-size: 0.72rem;"><?= htmlspecialchars($p['kode']) ?></span>
              </td>
              <td style="position: sticky; left: 70px; background: #fff; z-index: 1; font-weight: 500; color: var(--text-main); box-shadow: 2px 0 4px -2px rgba(0,0,0,0.1);">
                <?= htmlspecialchars($p['nama']) ?>
              </td>
              <?php for ($m = 1; $m <= 12; $m++): 
                $val = $p['bln'][$m];
              ?>
                <td class="table-amount <?= $val > 0 ? 'text-red' : '' ?>" style="padding: 8px 6px;">
                  <?= $val > 0 ? number_format($val, 0, ',', '.') : '-' ?>
                </td>
              <?php endfor; ?>
              <td class="table-amount text-red" style="font-weight: 700; background: #f8fafc; padding: 8px 12px;">
                <?= $p['total'] > 0 ? rupiah($p['total']) : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr style="background: #fef2f2; font-weight: 800; border-top: 1px solid #fecaca; border-bottom: 2px solid #fecaca;">
            <td style="position: sticky; left: 0; background: #fef2f2; z-index: 1;"></td>
            <td style="position: sticky; left: 70px; background: #fef2f2; z-index: 1; color: #991b1b; box-shadow: 2px 0 4px -2px rgba(0,0,0,0.1);">
              SUBTOTAL BEBAN OPERASIONAL (B)
            </td>
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <td class="table-amount text-red" style="font-weight: 700; padding: 10px 6px;">
                <?= $subtotalBebanBln[$m] > 0 ? number_format($subtotalBebanBln[$m], 0, ',', '.') : '-' ?>
              </td>
            <?php endfor; ?>
            <td class="table-amount text-red" style="font-size: 0.95rem; font-weight: 900; background: #fee2e2; padding: 10px 12px;">
              <?= rupiah($totalHasilPengeluaran) ?>
            </td>
          </tr>

          <!-- III. SISA HASIL USAHA (SHU) -->
          <tr style="background: #eff6ff; font-weight: 900; border-top: 3px double #93c5fd; font-size: 0.88rem;">
            <td style="position: sticky; left: 0; background: #eff6ff; z-index: 2;"></td>
            <td style="position: sticky; left: 70px; background: #eff6ff; z-index: 2; color: #1e40af; box-shadow: 2px 0 4px -2px rgba(0,0,0,0.1); padding: 14px 10px;">
              SISA HASIL USAHA (SHU) (A - B)
            </td>
            <?php for ($m = 1; $m <= 12; $m++): 
              $s = $shuBulanan[$m];
            ?>
              <td class="table-amount <?= $s >= 0 ? 'text-green' : 'text-red' ?>" style="font-weight: 800; padding: 14px 6px;">
                <?= $s != 0 ? number_format($s, 0, ',', '.') : '-' ?>
              </td>
            <?php endfor; ?>
            <td class="table-amount <?= $shuTahunBerjalan >= 0 ? 'text-green' : 'text-red' ?>" style="font-size: 1.05rem; font-weight: 900; background: #dbeafe; padding: 14px 12px;">
              <?= rupiah($shuTahunBerjalan) ?>
            </td>
          </tr>

        </tbody>
      </table>
    </div>
  </div>

<!-- ========================================================================= -->
<!-- TAB 4: NERACA                                                             -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'neraca'): ?>
  <!-- Status Keseimbangan Neraca -->
  <div style="margin-bottom: 20px;">
    <?php if ($isNeracaBalance): ?>
      <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px; color: #065f46;">
          <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <strong style="font-size: 0.95rem;">Status Neraca: SEIMBANG (BALANCE)</strong>
        </div>
        <div style="font-size: 0.88rem; color: #047857;">
          Total Aktiva = Total Pasiva (<strong><?= rupiah($totalAktivaNeraca) ?></strong>)
        </div>
      </div>
    <?php else: ?>
      <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px; color: #92400e;">
          <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <div>
            <strong style="font-size: 0.95rem;">Perhatian: Terdapat Selisih Neraca</strong>
            <div style="font-size: 0.8rem; margin-top: 2px;">Pastikan saldo awal pada Tab Pengaturan &gt; Neraca sudah seimbang.</div>
          </div>
        </div>
        <div style="font-size: 0.88rem; color: #b45309; font-weight: 700;">
          Selisih: <?= rupiah($selisihNeraca) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; align-items: start;">
    
    <!-- SISI KIRI: AKTIVA / ASET -->
    <div class="card">
      <div class="card-header" style="background: #f8fafc;">
        <h3 style="margin: 0; font-weight: 800; color: var(--secondary);">AKTIVA (ASET KOPERASI)</h3>
        <span class="badge badge-info" style="font-size: 0.85rem; font-weight: 700;"><?= rupiah($totalAktivaNeraca) ?></span>
      </div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th style="width: 100px;">Kode</th>
              <th>Nama Rekening</th>
              <th style="text-align: right; width: 170px;">Saldo (Rp)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['A1', 'A2'] as $k): 
              if (!isset($neracaCalculated[$k])) continue;
              $g = $neracaCalculated[$k];
            ?>
              <tr style="background: #f1f5f9; font-weight: 800;">
                <td colspan="2" style="color: var(--secondary);"><?= htmlspecialchars($g['nama_kategori']) ?></td>
                <td class="table-amount" style="font-weight: 800; color: var(--secondary);"><?= rupiah($g['subtotal']) ?></td>
              </tr>
              <?php foreach ($g['items'] as $it): ?>
                <tr>
                  <td><span class="badge badge-neutral"><?= htmlspecialchars($it['kode']) ?></span></td>
                  <td>
                    <?= htmlspecialchars($it['nama']) ?>
                    <?php if ($it['is_kontra']): ?>
                      <span class="badge badge-danger" style="font-size: 0.65rem; margin-left: 4px;">Akun Kontra (-)</span>
                    <?php endif; ?>
                  </td>
                  <td class="table-amount <?= $it['is_kontra'] ? 'text-red' : '' ?>" style="font-weight: 600;">
                    <?= $it['is_kontra'] ? '(' . rupiah($it['saldo']) . ')' : rupiah($it['saldo']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background: #eff6ff; font-weight: 900; border-top: 2px solid #93c5fd;">
              <td colspan="2" style="padding: 14px 18px; color: #1e40af; font-size: 1rem;">TOTAL AKTIVA / ASET</td>
              <td class="table-amount text-blue" style="font-size: 1.15rem;"><?= rupiah($totalAktivaNeraca) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- SISI KANAN: PASIVA (KEWAJIBAN, DANA, EKUITAS, SHU) -->
    <div class="card">
      <div class="card-header" style="background: #f8fafc;">
        <h3 style="margin: 0; font-weight: 800; color: var(--secondary);">PASIVA (KEWAJIBAN & EKUITAS)</h3>
        <span class="badge badge-info" style="font-size: 0.85rem; font-weight: 700;"><?= rupiah($totalPasivaNeraca) ?></span>
      </div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th style="width: 100px;">Kode</th>
              <th>Nama Rekening</th>
              <th style="text-align: right; width: 170px;">Saldo (Rp)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['B1', 'B2', 'B3', 'B4'] as $k): 
              if (!isset($neracaCalculated[$k])) continue;
              $g = $neracaCalculated[$k];
            ?>
              <tr style="background: #f1f5f9; font-weight: 800;">
                <td colspan="2" style="color: var(--secondary);"><?= htmlspecialchars($g['nama_kategori']) ?></td>
                <td class="table-amount" style="font-weight: 800; color: var(--secondary);"><?= rupiah($g['subtotal']) ?></td>
              </tr>
              <?php foreach ($g['items'] as $it): ?>
                <tr>
                  <td><span class="badge badge-neutral"><?= htmlspecialchars($it['kode']) ?></span></td>
                  <td>
                    <?= htmlspecialchars($it['nama']) ?>
                    <?php if ($it['kode'] === 'B.4.2'): ?>
                      <small style="color: var(--text-muted); display: block;">(Otomatis dari Laporan Hasil Usaha)</small>
                    <?php endif; ?>
                  </td>
                  <td class="table-amount" style="font-weight: 600;">
                    <?= rupiah($it['saldo']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background: #eff6ff; font-weight: 900; border-top: 2px solid #93c5fd;">
              <td colspan="2" style="padding: 14px 18px; color: #1e40af; font-size: 1rem;">TOTAL PASIVA</td>
              <td class="table-amount text-blue" style="font-size: 1.15rem;"><?= rupiah($totalPasivaNeraca) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  </div>

<!-- ========================================================================= -->
<!-- TAB 5: REKAP SHU - RINCIAN SHU HAK ANGGOTA (POKOK, WAJIB, PINJAMAN, BUNGA) -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'rekap_shu'): ?>
  
  <!-- Stat Cards: Dasar Pembagian SHU & Bunga Manasuka -->
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
      <div>
        <div class="stat-title">SHU Bersih Tahun <?= $tahunBuku ?></div>
        <div class="stat-value text-green" style="font-size: 1.45rem;"><?= rupiah($dasarShuBagi) ?></div>
        <div class="stat-hint">Surplus Laporan Hasil Usaha</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Jasa Modal Simpanan (25%)</div>
        <div class="stat-value text-blue"><?= rupiah($alokasiJasaModalRp) ?></div>
        <div class="stat-hint">Alokasi SHU Pokok & Wajib</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Jasa Usaha Anggota (25%)</div>
        <div class="stat-value text-blue"><?= rupiah($alokasiJasaUsahaRp) ?></div>
        <div class="stat-hint">Alokasi SHU Jasa Pinjaman SP</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Bunga Simpanan Manasuka</div>
        <div class="stat-value text-green"><?= rupiah($totAllBungaManasuka) ?></div>
        <div class="stat-hint">3%/tahun (dihitung harian)</div>
      </div>
    </div>
    <div class="stat-card">
      <div>
        <div class="stat-title">Total Hak Seluruh Anggota</div>
        <div class="stat-value text-green" style="font-weight: 800; font-size: 1.45rem;"><?= rupiah($totAllSHUAnggota) ?></div>
        <div class="stat-hint">SHU Jasa + Bunga Manasuka</div>
      </div>
    </div>
  </div>

  <!-- Petunjuk Rumus Perhitungan Resmi -->
  <div class="card no-print" style="margin-bottom: 20px; background: #f0fdf4; border: 1px solid #bbf7d0;">
    <div class="card-body" style="padding: 14px 20px; font-size: 0.82rem; color: #166534; line-height: 1.6;">
      <strong style="font-size: 0.88rem; display: block; margin-bottom: 4px;">📐 Ketentuan & Rumus Perhitungan SHU Hak Anggota:</strong>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;">
        <div>&bull; <strong>SHU Simpanan Pokok:</strong> (Simp. Pokok Anggota / Total Pokok+Wajib Seluruh) &times; Jasa Modal Simpanan</div>
        <div>&bull; <strong>SHU Simpanan Wajib:</strong> (Simp. Wajib Anggota / Total Pokok+Wajib Seluruh) &times; Jasa Modal Simpanan</div>
        <div>&bull; <strong>SHU Jasa Pinjaman:</strong> (Jasa Pinjaman Anggota / Total Jasa Pinjaman Seluruh) &times; Jasa Usaha Simpan Pinjam</div>
        <div>&bull; <strong>Bunga Manasuka:</strong> 3% per tahun dihitung berdasarkan lama saldo mengendap secara harian</div>
      </div>
    </div>
  </div>

  <!-- Main Table: Rekapitulasi SHU Per Anggota -->
  <div class="card">
    <div class="card-header no-print" style="flex-wrap: wrap; gap: 12px;">
      <div>
        <h3 style="margin: 0; font-weight: 800;">Rekapitulasi Hak Sisa Hasil Usaha (SHU) per Anggota</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
          Daftar alokasi SHU Simpanan Pokok, SHU Simpanan Wajib, SHU Jasa Pinjaman, dan Bunga Simpanan Manasuka.
        </p>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span class="badge badge-info" style="font-size: 0.76rem; padding: 6px 10px; font-weight: 600;">
          💡 Klik judul kolom untuk mengurutkan (A-Z / 1-9)
        </span>
        <input type="text" class="form-control" style="width: 240px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari nama, no. anggota..." data-table-search="tableRekapShu">
      </div>
    </div>

    <style>
    /* Styling Header Spreadsheet Interaktif */
    .table th.th-sortable {
      cursor: pointer;
      user-select: none;
      position: relative;
      transition: background-color 0.15s ease, color 0.15s ease;
      border-right: 1px solid #cbd5e1;
      padding: 10px 12px !important;
    }
    .table th.th-sortable:hover {
      background-color: #e2e8f0 !important;
    }
    .th-content {
      display: flex;
      align-items: center;
      gap: 6px;
      width: 100%;
    }
    .sort-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 18px;
      height: 18px;
      font-size: 0.72rem;
      color: #94a3b8;
      border-radius: 3px;
      background: rgba(0,0,0,0.04);
      transition: all 0.15s ease;
      flex-shrink: 0;
    }
    .th-sortable:hover .sort-icon {
      color: var(--secondary);
      background: rgba(0,0,0,0.08);
    }
    .th-sortable.sorted-asc {
      background-color: #e0f2fe !important;
      color: #0369a1 !important;
    }
    .th-sortable.sorted-asc .sort-icon {
      color: #0284c7;
      background: #bae6fd;
      font-weight: 900;
    }
    .th-sortable.sorted-desc {
      background-color: #e0f2fe !important;
      color: #0369a1 !important;
    }
    .th-sortable.sorted-desc .sort-icon {
      color: #0284c7;
      background: #bae6fd;
      font-weight: 900;
    }
    </style>

    <div class="table-freeze-container" style="overflow-x: auto; max-width: 100%;">
      <table class="table" id="tableRekapShu" style="min-width: 1300px; font-size: 0.82rem;">
        <thead>
          <tr style="background: #f8fafc; border-bottom: 2px solid #cbd5e1;">
            <th class="th-sortable" data-col="0" data-type="number" style="width: 55px; text-align: center; white-space: nowrap;" title="Urutkan berdasarkan Nomor">
              <div class="th-content" style="justify-content: center;">
                <span>No</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="1" data-type="text" style="width: 130px; white-space: nowrap;" title="Urutkan berdasarkan Nomor Anggota">
              <div class="th-content">
                <span>No. Anggota</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="2" data-type="text" style="min-width: 170px; white-space: nowrap;" title="Urutkan berdasarkan Nama Anggota">
              <div class="th-content">
                <span>Nama Anggota</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="3" data-type="amount" style="text-align: right; width: 130px; white-space: nowrap;" title="Urutkan berdasarkan Simp. Pokok">
              <div class="th-content" style="justify-content: flex-end;">
                <span>Simp. Pokok</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="4" data-type="amount" style="text-align: right; width: 130px; white-space: nowrap;" title="Urutkan berdasarkan Simp. Wajib">
              <div class="th-content" style="justify-content: flex-end;">
                <span>Simp. Wajib</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="5" data-type="amount" style="text-align: right; width: 135px; white-space: nowrap;" title="Urutkan berdasarkan Jasa Pinjaman">
              <div class="th-content" style="justify-content: flex-end;">
                <span>Jasa Pinjaman</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="6" data-type="amount" style="text-align: right; width: 130px; color: #1e40af; background: #eff6ff; white-space: nowrap;" title="Urutkan berdasarkan SHU Pokok">
              <div class="th-content" style="justify-content: flex-end;">
                <span>SHU Pokok</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="7" data-type="amount" style="text-align: right; width: 130px; color: #1e40af; background: #eff6ff; white-space: nowrap;" title="Urutkan berdasarkan SHU Wajib">
              <div class="th-content" style="justify-content: flex-end;">
                <span>SHU Wajib</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="8" data-type="amount" style="text-align: right; width: 135px; color: #1e40af; background: #eff6ff; white-space: nowrap;" title="Urutkan berdasarkan SHU Pinjaman">
              <div class="th-content" style="justify-content: flex-end;">
                <span>SHU Pinjaman</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="9" data-type="amount" style="text-align: right; width: 140px; color: #047857; background: #ecfdf5; white-space: nowrap;" title="Urutkan berdasarkan Bunga Manasuka">
              <div class="th-content" style="justify-content: flex-end;">
                <span>Bunga Manasuka</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
            <th class="th-sortable" data-col="10" data-type="amount" style="text-align: right; width: 155px; color: #15803d; font-weight: 800; background: #dcfce7; white-space: nowrap;" title="Urutkan berdasarkan Total Diterima">
              <div class="th-content" style="justify-content: flex-end;">
                <span>Total Diterima</span>
                <span class="sort-icon">⇅</span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dataRekapShuAnggota)): ?>
            <tr><td colspan="11" style="text-align:center; padding: 36px; color: var(--text-subtle);">Belum ada data transaksi anggota.</td></tr>
          <?php else: ?>
            <?php $no = 1; foreach ($dataRekapShuAnggota as $r): ?>
              <tr>
                <td style="text-align: center;"><?= $no++ ?></td>
                <td><strong><?= htmlspecialchars($r['no_anggota']) ?></strong></td>
                <td>
                  <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($r['nama']) ?></div>
                  <small style="color: var(--text-muted);"><?= htmlspecialchars($r['pekerjaan'] ?? '-') ?></small>
                </td>
                <td class="table-amount"><?= rupiah($r['sp']) ?></td>
                <td class="table-amount"><?= rupiah($r['sw']) ?></td>
                <td class="table-amount"><?= rupiah($r['jp']) ?></td>
                <td class="table-amount text-blue" style="font-weight: 700; background: #f8fafc;"><?= rupiah($r['shu_sp']) ?></td>
                <td class="table-amount text-blue" style="font-weight: 700; background: #f8fafc;"><?= rupiah($r['shu_sw']) ?></td>
                <td class="table-amount text-blue" style="font-weight: 700; background: #f8fafc;"><?= rupiah($r['shu_jp']) ?></td>
                <td class="table-amount text-green" style="font-weight: 600; background: #f0fdf4;"><?= rupiah($r['bunga_manasuka']) ?></td>
                <td class="table-amount text-green" style="font-weight: 900; font-size: 0.95rem; background: #ecfdf5;">
                  <?= rupiah($r['total_diterima']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background: #f1f5f9; font-weight: 900; border-top: 2px solid #cbd5e1; font-size: 0.85rem;">
            <td colspan="3" style="padding: 14px 16px; text-transform: uppercase;">TOTAL KESELURUHAN</td>
            <td class="table-amount"><?= rupiah($totAllSP) ?></td>
            <td class="table-amount"><?= rupiah($totAllSW) ?></td>
            <td class="table-amount"><?= rupiah($totAllJP) ?></td>
            <td class="table-amount text-blue" style="background: #dbeafe;"><?= rupiah($totAllSHUSP) ?></td>
            <td class="table-amount text-blue" style="background: #dbeafe;"><?= rupiah($totAllSHUSW) ?></td>
            <td class="table-amount text-blue" style="background: #dbeafe;"><?= rupiah($totAllSHUJP) ?></td>
            <td class="table-amount text-green" style="background: #bbf7d0;"><?= rupiah($totAllBungaManasuka) ?></td>
            <td class="table-amount text-green" style="font-size: 1.05rem; background: #86efac;"><?= rupiah($totAllSHUAnggota) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

<!-- ========================================================================= -->
<!-- TAB 6: LAPORAN PEMBAGIAN SHU (SESUAI AD/ART)                              -->
<!-- ========================================================================= -->
<?php elseif ($tab === 'pembagian_shu'): ?>
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
      <div>
        <div class="stat-title">SHU Bersih yang Dibagikan</div>
        <div class="stat-value text-green" style="font-size: 1.6rem;"><?= rupiah($dasarShuBagi) ?></div>
        <div class="stat-hint">Berdasarkan hasil pembukuan tahun berjalan</div>
      </div>
      <div class="stat-icon green">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
    </div>

    <div class="stat-card">
      <div>
        <div class="stat-title">Alokasi Jasa Modal (Simpanan)</div>
        <div class="stat-value text-blue"><?= rupiah($alokasiJasaModalRp) ?></div>
        <div class="stat-hint">Dibagikan proporsional simpanan anggota</div>
      </div>
      <div class="stat-icon blue">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      </div>
    </div>

    <div class="stat-card">
      <div>
        <div class="stat-title">Alokasi Jasa Usaha (Pinjaman SP)</div>
        <div class="stat-value text-blue"><?= rupiah($alokasiJasaUsahaRp) ?></div>
        <div class="stat-hint">Dibagikan proporsional partisipasi pinjaman</div>
      </div>
      <div class="stat-icon blue">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      </div>
    </div>
  </div>

  <!-- Tabel Alokasi Pos Pembagian SHU Resmi (AD/ART) -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <div>
        <h3 style="margin: 0; font-weight: 800;">Alokasi Pos Pembagian SHU Resmi (Sesuai AD/ART)</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
          Persentase disinkronkan langsung dari <a href="pengaturan.php?tab=shu" target="_blank" style="color:var(--primary); text-decoration:underline;">Pengaturan Pembagian SHU</a>.
        </p>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr style="background: #f8fafc;">
            <th style="width: 50px;">No</th>
            <th>Pos Pembagian SHU</th>
            <th style="text-align: right; width: 150px;">Persentase (%)</th>
            <th style="text-align: right; width: 220px;">Jumlah Alokasi (Rp)</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = 1; foreach ($shuPembagianCalculated as $pos): ?>
            <tr>
              <td><?= $no++ ?></td>
              <td style="font-weight: 600; color: var(--text-main);">
                <?= htmlspecialchars($pos['nama']) ?>
              </td>
              <td style="text-align: right; font-weight: 700; color: var(--secondary);">
                <?= number_format($pos['persen'], 2, ',', '.') ?>%
              </td>
              <td class="table-amount text-blue" style="font-weight: 700;">
                <?= rupiah($pos['nominal']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background: #eff6ff; font-weight: 900; border-top: 2px solid #93c5fd; font-size: 1rem;">
            <td colspan="2" style="padding: 14px 18px; color: #1e40af;">TOTAL PEMBAGIAN SHU (100%)</td>
            <td style="text-align: right; color: #1e40af; font-size: 1.05rem;"><?= number_format($totalPersenShu, 1, ',', '.') ?>%</td>
            <td class="table-amount text-green" style="font-size: 1.15rem;"><?= rupiah($totalAlokasiShuRp) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

<?php endif; ?>

<script>
// Filter tabel pencarian cepat
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-table-search]').forEach(input => {
    input.addEventListener('input', function() {
      const tableId = this.getAttribute('data-table-search');
      const table = document.getElementById(tableId);
      if (!table) return;

      const q = this.value.trim().toLowerCase();
      const rows = table.querySelectorAll('tbody tr');
      rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = (q === '' || text.includes(q)) ? '' : 'none';
      });
    });
  });

  // Aktifkan Fitur Spreadsheet Sort untuk Rekap SHU
  initRekapShuSpreadsheetSort();
});

// Fitur Urutkan Tabel (Spreadsheet Sort) untuk Rekap SHU
function initRekapShuSpreadsheetSort() {
  const table = document.getElementById('tableRekapShu');
  if (!table) return;

  const headers = table.querySelectorAll('thead th.th-sortable');
  const tbody = table.querySelector('tbody');
  if (!tbody) return;

  headers.forEach(th => {
    th.addEventListener('click', function() {
      const colIndex = parseInt(this.getAttribute('data-col'), 10);
      const colType = this.getAttribute('data-type') || 'text';
      const isAsc = this.classList.contains('sorted-asc');
      const newDirection = isAsc ? 'desc' : 'asc';

      // Reset semua header lain
      headers.forEach(h => {
        h.classList.remove('sorted-asc', 'sorted-desc');
        const icon = h.querySelector('.sort-icon');
        if (icon) icon.textContent = '⇅';
      });

      // Terapkan indikator arah pengurutan pada header aktif
      this.classList.add(newDirection === 'asc' ? 'sorted-asc' : 'sorted-desc');
      const curIcon = this.querySelector('.sort-icon');
      if (curIcon) curIcon.textContent = (newDirection === 'asc' ? '▲' : '▼');

      // Ambil seluruh baris data di tbody (abaikan baris kosong jika ada)
      const rows = Array.from(tbody.querySelectorAll('tr'));
      if (rows.length <= 1 && rows[0].querySelector('td[colspan]')) return;

      rows.sort((rowA, rowB) => {
        const cellA = rowA.children[colIndex];
        const cellB = rowB.children[colIndex];
        if (!cellA || !cellB) return 0;

        let valA = cellA.textContent.trim();
        let valB = cellB.textContent.trim();

        if (colType === 'amount' || colType === 'number') {
          // Bersihkan Rp, titik ribuan, spasi
          let numA = parseFloat(valA.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
          let numB = parseFloat(valB.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
          return newDirection === 'asc' ? (numA - numB) : (numB - numA);
        } else {
          valA = valA.toLowerCase();
          valB = valB.toLowerCase();
          if (valA < valB) return newDirection === 'asc' ? -1 : 1;
          if (valA > valB) return newDirection === 'asc' ? 1 : -1;
          return 0;
        }
      });

      // Render ulang urutan baris ke tbody & update nomor urut ranking 1..N
      rows.forEach((row, idx) => {
        const noCell = row.children[0];
        if (noCell) noCell.textContent = idx + 1;
        tbody.appendChild(row);
      });
    });
  });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
