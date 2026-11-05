<?php
require_once '../config/database.php';
require_once '../config/helper.php';

require_admin();

$pageTitle = 'Laporan Kas SP';
$pageSubtitle = 'Buku kas konsolidasi arus kas masuk dan kas keluar dari seluruh transaksi keuangan SP';
$activePage = 'laporan_kas_sp';

$currentUser = current_user();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
$isSqlite = ($driver === 'sqlite');

// Filter Parameters
$tglMulaiRaw = $_GET['tgl_mulai'] ?? date('Y-01-01');
$tglSelesaiRaw = $_GET['tgl_selesai'] ?? date('Y-12-31');
$tglMulai = parse_dmy_to_ymd($tglMulaiRaw);
$tglSelesai = parse_dmy_to_ymd($tglSelesaiRaw);

$filterModul = $_GET['modul'] ?? 'semua'; // semua, simpanan, simpanan_pendidikan, pinjaman, angsuran
$filterArus = $_GET['arus'] ?? 'semua'; // semua, masuk, keluar
$filterMetode = $_GET['metode'] ?? 'semua'; // semua, Langsung, TF, Gaji, TPP

// String Concatenation Helpers for cross-database compatibility (SQLite & MySQL)
if ($isSqlite) {
    $cSimp = "'SIMP-' || s.id";
    $cJenisSimp = "'Simpanan ' || s.jenis_simpanan";
    $cSpBln = "'Simpanan Pendidikan (Bln ke-' || sps.setoran_ke || ')'";
    $cSpKet = "sps.keterangan || CASE WHEN sps.catatan != '' AND sps.catatan IS NOT NULL THEN ' - ' || sps.catatan ELSE '' END";
    $cPinjKet = "'Pencairan Pinjaman Tenor ' || p.lama_bulan || ' Bln' || CASE WHEN p.keterangan != '' AND p.keterangan IS NOT NULL THEN ' - ' || p.keterangan ELSE '' END";
    $cAngsJenis = "'Angsuran Pinjaman (Ke-' || ag.angsuran_ke || ')'";
    $cAngsKet = "ag.keterangan || ' (Pokok: ' || ROUND(ag.angsuran_pokok) || ', Bunga: ' || ROUND(ag.angsuran_bunga) || ')'";
} else {
    $cSimp = "CONCAT('SIMP-', s.id)";
    $cJenisSimp = "CONCAT('Simpanan ', s.jenis_simpanan)";
    $cSpBln = "CONCAT('Simpanan Pendidikan (Bln ke-', sps.setoran_ke, ')')";
    $cSpKet = "CONCAT(sps.keterangan, IF(sps.catatan != '' AND sps.catatan IS NOT NULL, CONCAT(' - ', sps.catatan), ''))";
    $cPinjKet = "CONCAT('Pencairan Pinjaman Tenor ', p.lama_bulan, ' Bln', IF(p.keterangan != '' AND p.keterangan IS NOT NULL, CONCAT(' - ', p.keterangan), ''))";
    $cAngsJenis = "CONCAT('Angsuran Pinjaman (Ke-', ag.angsuran_ke, ')')";
    $cAngsKet = "CONCAT(ag.keterangan, ' (Pokok: ', ROUND(ag.angsuran_pokok), ', Bunga: ', ROUND(ag.angsuran_bunga), ')')";
}

// Master Unified SP Query
$masterSql = "
    -- 1. Transaksi Simpanan Anggota (Pokok, Wajib, Sukarela)
    SELECT 
        s.tanggal as tanggal,
        s.created_at as created_at,
        $cSimp as no_transaksi,
        CASE 
            WHEN s.jenis_simpanan = 'pokok' THEN 'Simpanan Pokok'
            WHEN s.jenis_simpanan = 'wajib' THEN 'Simpanan Wajib'
            WHEN s.jenis_simpanan = 'sukarela' AND s.tipe_transaksi = 'setor' THEN 'Simpanan Sukarela'
            WHEN s.jenis_simpanan = 'sukarela' AND s.tipe_transaksi = 'tarik' THEN 'Penarikan Sukarela'
            ELSE $cJenisSimp
        END as jenis_transaksi,
        'simpanan' as modul,
        s.jenis_simpanan as sub_jenis,
        COALESCE(a.nama, 'Anggota Non-Aktif / Terhapus') as nama_anggota,
        COALESCE(a.no_anggota, '-') as no_anggota,
        s.keterangan as metode_raw,
        s.keterangan as metode_ket,
        s.petugas as petugas,
        CASE WHEN s.tipe_transaksi = 'setor' THEN s.nominal ELSE 0 END as kas_masuk,
        CASE WHEN s.tipe_transaksi = 'tarik' THEN s.nominal ELSE 0 END as kas_keluar
    FROM simpanan s
    LEFT JOIN anggota a ON s.anggota_id = a.id

    UNION ALL

    -- 2. Transaksi Simpanan Pendidikan
    SELECT 
        sps.tanggal_bayar as tanggal,
        sps.created_at as created_at,
        COALESCE(sp.no_rekening, 'SP-PEND') as no_transaksi,
        $cSpBln as jenis_transaksi,
        'simpanan_pendidikan' as modul,
        'pendidikan' as sub_jenis,
        COALESCE(a.nama, 'Anggota Non-Aktif / Terhapus') as nama_anggota,
        COALESCE(a.no_anggota, '-') as no_anggota,
        sps.keterangan as metode_raw,
        $cSpKet as metode_ket,
        sps.petugas as petugas,
        sps.nominal as kas_masuk,
        0 as kas_keluar
    FROM simpanan_pendidikan_setoran sps
    LEFT JOIN simpanan_pendidikan sp ON sps.simpanan_pendidikan_id = sp.id
    LEFT JOIN anggota a ON sp.anggota_id = a.id

    UNION ALL

    -- 3. Transaksi Pencairan Pinjaman Anggota
    SELECT 
        COALESCE(p.tanggal_pencairan, p.tanggal_pengajuan) as tanggal,
        p.created_at as created_at,
        p.no_pinjaman as no_transaksi,
        'Pencairan Pinjaman' as jenis_transaksi,
        'pinjaman' as modul,
        'pencairan' as sub_jenis,
        COALESCE(a.nama, 'Anggota Non-Aktif / Terhapus') as nama_anggota,
        COALESCE(a.no_anggota, '-') as no_anggota,
        'Tunai' as metode_raw,
        $cPinjKet as metode_ket,
        'Admin' as petugas,
        0 as kas_masuk,
        p.jumlah_pinjaman as kas_keluar
    FROM pinjaman p
    LEFT JOIN anggota a ON p.anggota_id = a.id
    WHERE LOWER(COALESCE(p.status, 'berjalan')) IN ('berjalan', 'lunas', 'disetujui')

    UNION ALL

    -- 4. Transaksi Premi Asuransi Pinjaman (jika asuransi > 0)
    SELECT 
        COALESCE(p.tanggal_pencairan, p.tanggal_pengajuan) as tanggal,
        p.created_at as created_at,
        p.no_pinjaman as no_transaksi,
        'Premi Asuransi Pinjaman' as jenis_transaksi,
        'pinjaman' as modul,
        'asuransi' as sub_jenis,
        COALESCE(a.nama, 'Anggota Non-Aktif / Terhapus') as nama_anggota,
        COALESCE(a.no_anggota, '-') as no_anggota,
        'Kasir' as metode_raw,
        'Penerimaan Premi Asuransi Pinjaman Anggota' as metode_ket,
        'Admin' as petugas,
        p.asuransi as kas_masuk,
        0 as kas_keluar
    FROM pinjaman p
    LEFT JOIN anggota a ON p.anggota_id = a.id
    WHERE LOWER(COALESCE(p.status, 'berjalan')) IN ('berjalan', 'lunas', 'disetujui') AND p.asuransi > 0

    UNION ALL

    -- 5. Transaksi Pembayaran Angsuran Pinjaman
    SELECT 
        ag.tanggal_bayar as tanggal,
        ag.created_at as created_at,
        COALESCE(p.no_pinjaman, 'PINJ') as no_transaksi,
        $cAngsJenis as jenis_transaksi,
        'angsuran' as modul,
        'angsuran' as sub_jenis,
        COALESCE(a.nama, 'Anggota Non-Aktif / Terhapus') as nama_anggota,
        COALESCE(a.no_anggota, '-') as no_anggota,
        ag.keterangan as metode_raw,
        $cAngsKet as metode_ket,
        ag.petugas as petugas,
        ag.jumlah_bayar as kas_masuk,
        0 as kas_keluar
    FROM angsuran ag
    LEFT JOIN pinjaman p ON ag.pinjaman_id = p.id
    LEFT JOIN anggota a ON p.anggota_id = a.id

    UNION ALL

    -- 6. Transaksi Mutasi Antar Kas Masuk/Keluar SP
    SELECT 
        bk.tanggal as tanggal,
        bk.created_at as created_at,
        bk.no_bukti as no_transaksi,
        bk.kategori as jenis_transaksi,
        'mutasi' as modul,
        'mutasi' as sub_jenis,
        'Kas Koperasi' as nama_anggota,
        'KAS-SP' as no_anggota,
        'Mutasi' as metode_raw,
        bk.keterangan as metode_ket,
        bk.petugas as petugas,
        bk.kas_masuk as kas_masuk,
        bk.kas_keluar as kas_keluar
    FROM buku_kas bk
    WHERE bk.tipe_kas = 'kas_sp'

    ORDER BY tanggal ASC, created_at ASC
";

// 1. Hitung Saldo Awal Kas SP sebelum $tglMulai (Semua Transaksi sebelum periode)
$sqlSaldoAwal = "
    SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) as saldo_awal
    FROM ($masterSql) as master_sp
    WHERE tanggal < '$tglMulai'
";
$saldoAwalPeriode = (float)$db->query($sqlSaldoAwal)->fetchColumn();

// 2. Ambil Semua Transaksi Periode Ini
$allRows = $db->query($masterSql)->fetchAll(PDO::FETCH_ASSOC);

// Konsolidasi Transaksi Potongan Gaji dan Potongan TPP pada Tanggal yang Sama
$processedRows = [];
$groupGaji = [];
$groupTpp = [];
$groupGajiTpp = [];

foreach ($allRows as $r) {
    $tgl = $r['tanggal'];
    $modul = $r['modul'];
    $masuk = (float)$r['kas_masuk'];
    $metodeStr = strtolower(($r['metode_raw'] ?? '') . ' ' . ($r['metode_ket'] ?? ''));

    $isEligibleModul = in_array($modul, ['simpanan', 'simpanan_pendidikan', 'angsuran']);

    if ($isEligibleModul && $masuk > 0) {
        $hasGaji = (strpos($metodeStr, 'gaji') !== false);
        $hasTpp = (strpos($metodeStr, 'tpp') !== false);

        if ($hasGaji && $hasTpp) {
            if (!isset($groupGajiTpp[$tgl])) {
                $groupGajiTpp[$tgl] = [
                    'tanggal' => $tgl,
                    'created_at' => $r['created_at'],
                    'kas_masuk' => 0,
                    'count' => 0
                ];
            }
            $groupGajiTpp[$tgl]['kas_masuk'] += $masuk;
            $groupGajiTpp[$tgl]['count']++;
            continue;
        } elseif ($hasGaji) {
            if (!isset($groupGaji[$tgl])) {
                $groupGaji[$tgl] = [
                    'tanggal' => $tgl,
                    'created_at' => $r['created_at'],
                    'kas_masuk' => 0,
                    'count' => 0
                ];
            }
            $groupGaji[$tgl]['kas_masuk'] += $masuk;
            $groupGaji[$tgl]['count']++;
            continue;
        } elseif ($hasTpp) {
            if (!isset($groupTpp[$tgl])) {
                $groupTpp[$tgl] = [
                    'tanggal' => $tgl,
                    'created_at' => $r['created_at'],
                    'kas_masuk' => 0,
                    'count' => 0
                ];
            }
            $groupTpp[$tgl]['kas_masuk'] += $masuk;
            $groupTpp[$tgl]['count']++;
            continue;
        }
    }

    $processedRows[] = $r;
}

// Tambahkan baris konsolidasi Gaji
foreach ($groupGaji as $tgl => $grp) {
    $blnName = nama_bulan((int)date('n', strtotime($tgl)));
    $processedRows[] = [
        'tanggal' => $tgl,
        'created_at' => $grp['created_at'],
        'no_transaksi' => 'POT-GAJI-' . date('Ymd', strtotime($tgl)),
        'jenis_transaksi' => 'Potongan Gaji',
        'modul' => 'simpanan',
        'sub_jenis' => 'gaji',
        'nama_anggota' => 'Kolektif Anggota',
        'no_anggota' => 'KOLEKTIF',
        'metode_raw' => 'Gaji',
        'metode_ket' => 'Potongan Gaji Bulan ' . $blnName . ' (' . $grp['count'] . ' transaksi)',
        'petugas' => 'Admin',
        'kas_masuk' => $grp['kas_masuk'],
        'kas_keluar' => 0
    ];
}

// Tambahkan baris konsolidasi TPP
foreach ($groupTpp as $tgl => $grp) {
    $blnName = nama_bulan((int)date('n', strtotime($tgl)));
    $processedRows[] = [
        'tanggal' => $tgl,
        'created_at' => $grp['created_at'],
        'no_transaksi' => 'POT-TPP-' . date('Ymd', strtotime($tgl)),
        'jenis_transaksi' => 'Potongan TPP',
        'modul' => 'simpanan',
        'sub_jenis' => 'tpp',
        'nama_anggota' => 'Kolektif Anggota',
        'no_anggota' => 'KOLEKTIF',
        'metode_raw' => 'TPP',
        'metode_ket' => 'Potongan TPP Bulan ' . $blnName . ' (' . $grp['count'] . ' transaksi)',
        'petugas' => 'Admin',
        'kas_masuk' => $grp['kas_masuk'],
        'kas_keluar' => 0
    ];
}

// Tambahkan baris konsolidasi Gaji & TPP jika ada
foreach ($groupGajiTpp as $tgl => $grp) {
    $blnName = nama_bulan((int)date('n', strtotime($tgl)));
    $processedRows[] = [
        'tanggal' => $tgl,
        'created_at' => $grp['created_at'],
        'no_transaksi' => 'POT-G&T-' . date('Ymd', strtotime($tgl)),
        'jenis_transaksi' => 'Potongan Gaji & TPP',
        'modul' => 'simpanan',
        'sub_jenis' => 'gaji_tpp',
        'nama_anggota' => 'Kolektif Anggota',
        'no_anggota' => 'KOLEKTIF',
        'metode_raw' => 'Gaji / TPP',
        'metode_ket' => 'Potongan Gaji & TPP Bulan ' . $blnName . ' (' . $grp['count'] . ' transaksi)',
        'petugas' => 'Admin',
        'kas_masuk' => $grp['kas_masuk'],
        'kas_keluar' => 0
    ];
}

// Urutkan kembali berdasarkan tanggal ASC, created_at ASC
usort($processedRows, function($a, $b) {
    $cmp = strcmp($a['tanggal'], $b['tanggal']);
    if ($cmp !== 0) return $cmp;
    return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
});

$allRows = $processedRows;

// Filter data di level PHP sesuai parameter
$filteredData = [];
$totalMasukPeriode = 0;
$totalKeluarPeriode = 0;

// Breakdown Akumulasi Total Keseluruhan (Sepanjang Waktu)
$allTimeMasuk = 0;
$allTimeKeluar = 0;

// Breakdown Kategori Periode
$katMasukSimpanan = 0;
$katMasukPendidikan = 0;
$katMasukAngsuran = 0;
$katMasukAsuransi = 0;
$katKeluarPinjaman = 0;
$katKeluarTarikSimpanan = 0;

$runningSaldo = $saldoAwalPeriode;

foreach ($allRows as $r) {
    $tgl = $r['tanggal'];
    $masuk = (float)$r['kas_masuk'];
    $keluar = (float)$r['kas_keluar'];

    $allTimeMasuk += $masuk;
    $allTimeKeluar += $keluar;

    // Cek apakah masuk dalam filter tanggal
    if ($tgl >= $tglMulai && $tgl <= $tglSelesai) {
        // Cek filter Modul
        if ($filterModul !== 'semua' && $r['modul'] !== $filterModul) {
            continue;
        }

        // Cek filter Arus Kas
        if ($filterArus === 'masuk' && $masuk <= 0) continue;
        if ($filterArus === 'keluar' && $keluar <= 0) continue;

        // Cek filter Metode
        if ($filterMetode !== 'semua') {
            $metodeStr = strtoupper($r['metode_raw'] . ' ' . $r['metode_ket']);
            if (strpos($metodeStr, strtoupper($filterMetode)) === false) {
                continue;
            }
        }

        $runningSaldo += ($masuk - $keluar);
        $r['saldo_kumulatif'] = $runningSaldo;

        $totalMasukPeriode += $masuk;
        $totalKeluarPeriode += $keluar;

        // Breakdown stats
        if ($r['modul'] === 'simpanan' && $masuk > 0) $katMasukSimpanan += $masuk;
        if ($r['modul'] === 'simpanan_pendidikan') $katMasukPendidikan += $masuk;
        if ($r['modul'] === 'angsuran') $katMasukAngsuran += $masuk;
        if ($r['sub_jenis'] === 'asuransi') $katMasukAsuransi += $masuk;
        if ($r['modul'] === 'pinjaman' && $keluar > 0) $katKeluarPinjaman += $keluar;
        if ($r['sub_jenis'] === 'sukarela' && $keluar > 0) $katKeluarTarikSimpanan += $keluar;

        $filteredData[] = $r;
    }
}

$saldoAkhirPeriode = $saldoAwalPeriode + $totalMasukPeriode - $totalKeluarPeriode;
$saldoKasSpSaatIni = $allTimeMasuk - $allTimeKeluar;

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_level()) {
        ob_end_clean();
    }
    ini_set('display_errors', '0');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Kas_SP_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($out, ['LAPORAN BUKU KAS TRANSAKSI KEUANGAN SP - KOPERASI GATRA TEKNIKA'], ';', '"', '\\');
    fputcsv($out, ['Periode: ' . tgl_dmy($tglMulai) . ' s/d ' . tgl_dmy($tglSelesai)], ';', '"', '\\');
    fputcsv($out, ['Tanggal Unduh: ' . tgl_indo(date('Y-m-d'), true)], ';', '"', '\\');
    fputcsv($out, [], ';', '"', '\\');
    fputcsv($out, [
        'No',
        'Tanggal',
        'No. Referensi',
        'Sumber Transaksi',
        'No. Anggota',
        'Nama Anggota',
        'Metode & Keterangan',
        'Kas Masuk',
        'Kas Keluar',
        'Saldo Kas Kumulatif (Rp)'
    ], ';', '"', '\\');

    $no = 1;
    foreach ($filteredData as $item) {
        fputcsv($out, [
            $no++,
            tgl_dmy($item['tanggal']),
            $item['no_transaksi'],
            $item['jenis_transaksi'],
            $item['no_anggota'],
            $item['nama_anggota'],
            $item['metode_ket'],
            $item['kas_masuk'],
            $item['kas_keluar'],
            $item['saldo_kumulatif']
        ], ';', '"', '\\');
    }

    fputcsv($out, [], ';', '"', '\\');
    fputcsv($out, ['', '', '', 'TOTAL PERIODE', '', '', '', $totalMasukPeriode, $totalKeluarPeriode, $saldoAkhirPeriode], ';', '"', '\\');
    fclose($out);
    exit;
}

include 'header.php';
?>

<!-- Header Laporan & Action Buttons -->
<div class="row" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
  <div>
    <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--secondary); margin-bottom: 4px;">
      Laporan Kas Simpan Pinjam (Kas SP)
    </h2>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
      Konsolidasi otomatis seluruh penerimaan & pengeluaran kas dari modul Transaksi Keuangan SP.
    </p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="laporan_kas_sp.php?tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>&modul=<?= urlencode($filterModul) ?>&arus=<?= urlencode($filterArus) ?>&metode=<?= urlencode($filterMetode) ?>&export=csv" 
       class="btn btn-secondary" style="border: 1px solid #cbd5e1; background: #ffffff;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Unduh Excel (CSV)
    </a>
    <button type="button" class="btn btn-primary" onclick="window.print()">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Cetak / Simpan PDF
    </button>
  </div>
</div>

<!-- Print-Only Header Resmi Koperasi -->
<div class="print-header">
  <h2 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 2px;">KOPERASI SIMPAN PINJAM GATRA TEKNIKA</h2>
  <p style="font-size: 0.88rem; margin-bottom: 4px;">Jl. By Pass Sunyaragi No. 45, Cirebon, Jawa Barat &bull; Telp: (0231) 482910</p>
  <div style="border-bottom: 2px solid #0f172a; margin: 12px 0;"></div>
  <p style="font-size: 1.05rem; font-weight: 800; text-transform: uppercase; margin: 4px 0;">BUKU LAPORAN KAS TRANSAKSI KEUANGAN SP</p>
  <p style="font-size: 0.85rem; color: #334155;">
    Periode Pembukuan: <strong><?= tgl_dmy($tglMulai) ?></strong> s/d <strong><?= tgl_dmy($tglSelesai) ?></strong> &bull; Dicetak: <?= tgl_indo(date('Y-m-d'), true) ?>
  </p>
</div>

<!-- Breakdown Pills Section (No-Print) -->
<div class="card no-print" style="margin-bottom: 20px; background: #f8fafc; border: 1px solid var(--border-color);">
  <div style="padding: 14px 20px;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 10px;">
      Rincian Transaksi Keuangan SP Periode Ini:
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.84rem;">
      <span style="background: #ffffff; padding: 6px 12px; border-radius: 6px; border: 1px solid #bbf7d0; color: #166534;">
        💰 Simpanan Masuk: <strong><?= rupiah($katMasukSimpanan) ?></strong>
      </span>
      <span style="background: #ffffff; padding: 6px 12px; border-radius: 6px; border: 1px solid #bfdbfe; color: #1e40af;">
        🎓 Simpanan Pendidikan: <strong><?= rupiah($katMasukPendidikan) ?></strong>
      </span>
      <span style="background: #ffffff; padding: 6px 12px; border-radius: 6px; border: 1px solid #c7d2fe; color: #3730a3;">
        💳 Angsuran Masuk: <strong><?= rupiah($katMasukAngsuran) ?></strong>
      </span>
      <?php if ($katMasukAsuransi > 0): ?>
        <span style="background: #ffffff; padding: 6px 12px; border-radius: 6px; border: 1px solid #e9d5ff; color: #6b21a8;">
          🛡️ Asuransi Masuk: <strong><?= rupiah($katMasukAsuransi) ?></strong>
        </span>
      <?php endif; ?>
      <span style="background: #ffffff; padding: 6px 12px; border-radius: 6px; border: 1px solid #fecaca; color: #991b1b;">
        📤 Pencairan Pinjaman: <strong><?= rupiah($katKeluarPinjaman) ?></strong>
      </span>
      <?php if ($katKeluarTarikSimpanan > 0): ?>
        <span style="background: #ffffff; padding: 6px 12px; border-radius: 6px; border: 1px solid #fed7aa; color: #9a3412;">
          🔻 Penarikan Simpanan: <strong><?= rupiah($katKeluarTarikSimpanan) ?></strong>
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Card Filter Parameter (No-Print) -->
<div class="card no-print" style="margin-bottom: 24px;">
  <div class="card-header" style="padding: 14px 20px;">
    <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
      Filter Parameter Kas SP
    </h3>
    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
      <a href="laporan_kas_sp.php?tgl_mulai=<?= date('Y-m-01') ?>&tgl_selesai=<?= date('Y-m-t') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Bulan Ini</a>
      <a href="laporan_kas_sp.php?tgl_mulai=<?= date('Y-01-01') ?>&tgl_selesai=<?= date('Y-12-31') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Tahun <?= date('Y') ?></a>
      <a href="laporan_kas_sp.php?tgl_mulai=2020-01-01&tgl_selesai=<?= date('Y-12-31') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Semua Waktu</a>
      <a href="laporan_kas_sp.php" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Reset</a>
    </div>
  </div>

  <form method="GET" action="laporan_kas_sp.php" style="padding: 16px 20px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end;">
      
      <!-- Dari Tanggal -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Dari Tanggal:</label>
        <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
          <input type="text" name="tgl_mulai" class="form-control date-dmy-input" value="<?= tgl_dmy($tglMulai) ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" style="padding-right: 36px; font-size: 0.86rem;">
          <button type="button" class="btn-date-picker" tabindex="-1" onclick="triggerDatePicker(this)" style="position: absolute; right: 2px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted);" title="Buka Kalender">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </button>
          <input type="date" class="native-date-hidden" tabindex="-1" value="<?= $tglMulai ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
        </div>
      </div>

      <!-- Sampai Tanggal -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Sampai Tanggal:</label>
        <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
          <input type="text" name="tgl_selesai" class="form-control date-dmy-input" value="<?= tgl_dmy($tglSelesai) ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" style="padding-right: 36px; font-size: 0.86rem;">
          <button type="button" class="btn-date-picker" tabindex="-1" onclick="triggerDatePicker(this)" style="position: absolute; right: 2px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted);" title="Buka Kalender">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </button>
          <input type="date" class="native-date-hidden" tabindex="-1" value="<?= $tglSelesai ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
        </div>
      </div>

      <!-- Modul / Sumber -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Sumber Transaksi SP:</label>
        <select name="modul" class="form-select" style="font-size: 0.86rem; padding: 8px 12px;">
          <option value="semua" <?= $filterModul === 'semua' ? 'selected' : '' ?>>Semua Modul SP</option>
          <option value="simpanan" <?= $filterModul === 'simpanan' ? 'selected' : '' ?>>Simpanan Anggota</option>
          <option value="simpanan_pendidikan" <?= $filterModul === 'simpanan_pendidikan' ? 'selected' : '' ?>>Simpanan Pendidikan</option>
          <option value="pinjaman" <?= $filterModul === 'pinjaman' ? 'selected' : '' ?>>Pencairan Pinjaman</option>
          <option value="angsuran" <?= $filterModul === 'angsuran' ? 'selected' : '' ?>>Bayar Angsuran</option>
          <option value="mutasi" <?= $filterModul === 'mutasi' ? 'selected' : '' ?>>Mutasi Antar Kas</option>
        </select>
      </div>

      <!-- Arus Kas -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Arus Kas:</label>
        <select name="arus" class="form-select" style="font-size: 0.86rem; padding: 8px 12px;">
          <option value="semua" <?= $filterArus === 'semua' ? 'selected' : '' ?>>Semua (Masuk & Keluar)</option>
          <option value="masuk" <?= $filterArus === 'masuk' ? 'selected' : '' ?>>Kas Masuk Saja</option>
          <option value="keluar" <?= $filterArus === 'keluar' ? 'selected' : '' ?>>Kas Keluar Saja</option>
        </select>
      </div>

      <!-- Metode Pembayaran -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Metode Pembayaran:</label>
        <select name="metode" class="form-select" style="font-size: 0.86rem; padding: 8px 12px;">
          <option value="semua" <?= $filterMetode === 'semua' ? 'selected' : '' ?>>Semua Metode</option>
          <option value="Langsung" <?= $filterMetode === 'Langsung' ? 'selected' : '' ?>>Langsung / Tunai</option>
          <option value="TF" <?= $filterMetode === 'TF' ? 'selected' : '' ?>>TF (Transfer)</option>
          <option value="Gaji" <?= $filterMetode === 'Gaji' ? 'selected' : '' ?>>Gaji (Potong Gaji)</option>
          <option value="TPP" <?= $filterMetode === 'TPP' ? 'selected' : '' ?>>TPP (Potong TPP)</option>
        </select>
      </div>

      <!-- Tombol Submit Filter -->
      <div style="margin: 0;">
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 9px 16px; font-size: 0.88rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          Terapkan Filter
        </button>
      </div>

    </div>
  </form>
</div>

<!-- Main Table: Buku Kas Transaksi Keuangan SP -->
<div class="card">
  <div class="card-header no-print" style="flex-wrap: wrap; gap: 12px;">
    <div>
      <h3 style="margin: 0; font-weight: 800;">Buku Kas Transaksi Keuangan SP</h3>
      <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
        Menampilkan <strong><?= count($filteredData) ?></strong> transaksi pada periode terpilih
      </p>
    </div>
    <div class="card-header-actions">
      <input type="text" class="form-control" style="width: 250px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari nama, no bukti, ket..." data-table-search="tableKasSp">
    </div>
  </div>

  <div class="table-freeze-container">
    <table class="table" id="tableKasSp">
      <thead>
        <tr>
          <th style="width: 105px;">Tanggal</th>
          <th style="width: 130px;">No. Bukti / Ref</th>
          <th>Sumber Transaksi</th>
          <th>Anggota</th>
          <th>Metode & Keterangan</th>
          <th style="text-align: right; width: 140px;">Kas Masuk (Rp)</th>
          <th style="text-align: right; width: 140px;">Kas Keluar (Rp)</th>
          <th style="text-align: right; width: 155px;">Saldo Kas (Rp)</th>
        </tr>
      </thead>
      <tbody>
        <!-- Baris Saldo Awal Periode -->
        <tr style="background-color: #f8fafc; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
          <td><?= tgl_dmy($tglMulai) ?></td>
          <td><span class="badge badge-neutral" style="font-size:0.75rem;">SALDO-AWAL</span></td>
          <td colspan="3" style="color: var(--secondary);">
            <em>Saldo Kas SP sebelum periode <?= tgl_dmy($tglMulai) ?></em>
          </td>
          <td class="table-amount">-</td>
          <td class="table-amount">-</td>
          <td class="table-amount text-blue" style="font-weight: 800; font-size: 0.95rem;">
            <?= nominal($saldoAwalPeriode) ?>
          </td>
        </tr>

        <?php if (empty($filteredData)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-subtle);">
              Tidak ada catatan transaksi keuangan SP yang cocok dengan filter pada periode ini.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($filteredData as $row): 
            $masuk = (float)$row['kas_masuk'];
            $keluar = (float)$row['kas_keluar'];

            // Tentukan style badge berdasarkan sumber transaksi
            $badgeColor = 'badge-neutral';
            if ($row['modul'] === 'simpanan') {
                $badgeColor = ($row['sub_jenis'] === 'sukarela' && $keluar > 0) ? 'badge-danger' : 'badge-success';
            } elseif ($row['modul'] === 'simpanan_pendidikan') {
                $badgeColor = 'badge-purple';
            } elseif ($row['modul'] === 'pinjaman') {
                $badgeColor = ($row['sub_jenis'] === 'asuransi') ? 'badge-info' : 'badge-warning';
            } elseif ($row['modul'] === 'angsuran') {
                $badgeColor = 'badge-info';
            } elseif ($row['modul'] === 'mutasi') {
                $badgeColor = 'badge-warning';
            }
          ?>
            <tr>
              <td><?= tgl_dmy($row['tanggal']) ?></td>
              <td>
                <strong style="color: var(--secondary); font-size: 0.85rem;"><?= htmlspecialchars($row['no_transaksi']) ?></strong>
              </td>
              <td>
                <span class="badge <?= $badgeColor ?>" style="font-size: 0.75rem; font-weight: 700;">
                  <?= htmlspecialchars($row['jenis_transaksi']) ?>
                </span>
              </td>
              <td>
                <strong style="font-size: 0.88rem;"><?= htmlspecialchars($row['nama_anggota']) ?></strong>
                <div style="font-size: 0.74rem; color: var(--text-muted);"><?= htmlspecialchars($row['no_anggota']) ?></div>
              </td>
              <td>
                <div>
                  <?php 
                    $metode = trim($row['metode_raw'] ?? '');
                    if ($metode === 'Langsung' || strpos($metode, 'Langsung') !== false): ?>
                      <span class="badge badge-success" style="font-size: 0.7rem; padding: 2px 6px;">Langsung</span>
                  <?php elseif ($metode === 'TF' || strpos($metode, 'TF') !== false): ?>
                      <span class="badge badge-info" style="font-size: 0.7rem; padding: 2px 6px;">TF</span>
                  <?php elseif ($metode === 'Gaji' || strpos($metode, 'Gaji') !== false): ?>
                      <span class="badge badge-warning" style="font-size: 0.7rem; padding: 2px 6px;">Gaji</span>
                  <?php elseif ($metode === 'TPP' || strpos($metode, 'TPP') !== false): ?>
                      <span class="badge" style="font-size: 0.7rem; padding: 2px 6px; background:#f3e8ff; color:#7e22ce;">TPP</span>
                  <?php endif; ?>
                  <span style="font-size: 0.82rem; color: var(--text-main); margin-left: 4px;">
                    <?= htmlspecialchars($row['metode_ket']) ?>
                  </span>
                </div>
              </td>
              
              <!-- Kas Masuk -->
              <td class="table-amount <?= $masuk > 0 ? 'text-green' : '' ?>" style="font-weight: 700;">
                <?= $masuk > 0 ? nominal($masuk) : '-' ?>
              </td>

              <!-- Kas Keluar -->
              <td class="table-amount <?= $keluar > 0 ? 'text-red' : '' ?>" style="font-weight: 700;">
                <?= $keluar > 0 ? nominal($keluar) : '-' ?>
              </td>

              <!-- Saldo Kumulatif -->
              <td class="table-amount" style="font-weight: 800; color: var(--secondary);">
                <?= nominal($row['saldo_kumulatif']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>

      <!-- Footer Akumulasi Periode -->
      <tfoot>
        <tr style="background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1;">
          <td colspan="5" style="padding: 14px 18px; text-transform: uppercase;">
            TOTAL ARUS KAS PERIODE INI (<?= tgl_dmy($tglMulai) ?> s/d <?= tgl_dmy($tglSelesai) ?>)
          </td>
          <td class="table-amount text-green" style="font-size: 0.98rem;">
            <?= nominal($totalMasukPeriode) ?>
          </td>
          <td class="table-amount text-red" style="font-size: 0.98rem;">
            <?= nominal($totalKeluarPeriode) ?>
          </td>
          <td class="table-amount text-blue" style="font-size: 1.05rem;">
            <?= nominal($saldoAkhirPeriode) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>

