<?php
/**
 * Core Controller & View Buku Kas (Kas Umum, Kas Bank, Kas Toko)
 * Koperasi Simpan Pinjam Gatra Teknika
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';

require_admin();

$currentUser = current_user();

// Validasi tipe kas
if (!isset($tipeKas) || !in_array($tipeKas, ['kas_umum', 'kas_bank', 'kas_toko'])) {
    $tipeKas = 'kas_umum';
    $pageTitle = 'Kas Umum';
    $pageSubtitle = 'Buku kas umum operasional koperasi';
    $activePage = 'kas_umum';
    $prefixBuktiM = 'BKM-KU';
    $prefixBuktiK = 'BKK-KU';
    $badgeKas = 'badge-success';
}

$pageSelf = basename($_SERVER['PHP_SELF']);

// =========================================================================
// 1. HANDLE POST ACTIONS (TAMBAH, EDIT, HAPUS)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // TAMBAH TRANSAKSI
    if ($action === 'tambah_kas') {
        $tanggalRaw = $_POST['tanggal'] ?? date('d/m/Y');
        $tanggal = parse_dmy_to_ymd($tanggalRaw);
        $noBukti = trim($_POST['no_bukti'] ?? '');
        $jenisTransaksi = ($_POST['jenis_transaksi'] ?? 'masuk') === 'keluar' ? 'keluar' : 'masuk';
        $kategori = trim($_POST['kategori'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        // Bersihkan nominal Rupiah
        $rawNominal = str_replace(['Rp', '.', ' '], '', $_POST['nominal'] ?? '0');
        $rawNominal = str_replace(',', '.', $rawNominal);
        $nominal = (float)$rawNominal;

        if ($nominal <= 0) {
            set_flash('error', 'Nominal kas harus lebih besar dari Rp 0.');
            header("Location: $pageSelf");
            exit;
        }

        if ($noBukti === '') {
            $prefix = ($jenisTransaksi === 'masuk') ? $prefixBuktiM : $prefixBuktiK;
            $ym = date('Ym', strtotime($tanggal));
            $c = $db->query("SELECT COUNT(*) FROM buku_kas WHERE tipe_kas = '$tipeKas'")->fetchColumn() + 1;
            $noBukti = "{$prefix}-{$ym}-" . str_pad($c, 3, '0', STR_PAD_LEFT);
        }

        $kasMasuk = ($jenisTransaksi === 'masuk') ? $nominal : 0;
        $kasKeluar = ($jenisTransaksi === 'keluar') ? $nominal : 0;
        $petugas = $currentUser['nama_lengkap'] ?? 'Admin';

        try {
            $stmt = $db->prepare("INSERT INTO buku_kas 
                (tipe_kas, tanggal, no_bukti, keterangan, kategori, jenis_transaksi, kas_masuk, kas_keluar, petugas) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tipeKas,
                $tanggal,
                $noBukti,
                $keterangan,
                $kategori,
                $jenisTransaksi,
                $kasMasuk,
                $kasKeluar,
                $petugas
            ]);

            set_flash('success', "Transaksi {$pageTitle} (No. Bukti: {$noBukti}) sebesar " . rupiah($nominal) . " berhasil disimpan.");
        } catch (Exception $e) {
            set_flash('error', 'Gagal menyimpan transaksi: ' . $e->getMessage());
        }

        header("Location: $pageSelf");
        exit;
    }

    // EDIT TRANSAKSI
    if ($action === 'edit_kas') {
        $id = (int)($_POST['id'] ?? 0);
        $tanggalRaw = $_POST['tanggal'] ?? date('d/m/Y');
        $tanggal = parse_dmy_to_ymd($tanggalRaw);
        $noBukti = trim($_POST['no_bukti'] ?? '');
        $jenisTransaksi = ($_POST['jenis_transaksi'] ?? 'masuk') === 'keluar' ? 'keluar' : 'masuk';
        $kategori = trim($_POST['kategori'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        $rawNominal = str_replace(['Rp', '.', ' '], '', $_POST['nominal'] ?? '0');
        $rawNominal = str_replace(',', '.', $rawNominal);
        $nominal = (float)$rawNominal;

        if ($id <= 0 || $nominal <= 0) {
            set_flash('error', 'Data perubahan tidak valid.');
            header("Location: $pageSelf");
            exit;
        }

        $kasMasuk = ($jenisTransaksi === 'masuk') ? $nominal : 0;
        $kasKeluar = ($jenisTransaksi === 'keluar') ? $nominal : 0;

        try {
            $stmt = $db->prepare("UPDATE buku_kas SET 
                tanggal = ?, no_bukti = ?, keterangan = ?, kategori = ?, jenis_transaksi = ?, kas_masuk = ?, kas_keluar = ?
                WHERE id = ? AND tipe_kas = ?");
            $stmt->execute([
                $tanggal,
                $noBukti,
                $keterangan,
                $kategori,
                $jenisTransaksi,
                $kasMasuk,
                $kasKeluar,
                $id,
                $tipeKas
            ]);

            set_flash('success', "Transaksi (No. Bukti: {$noBukti}) berhasil diperbarui.");
        } catch (Exception $e) {
            set_flash('error', 'Gagal memperbarui transaksi: ' . $e->getMessage());
        }

        header("Location: $pageSelf");
        exit;
    }

    // MUTASI KAS ANTAR UNIT (KHUSUS KAS UMUM)
    if ($action === 'mutasi_kas' && $tipeKas === 'kas_umum') {
        $tanggalRaw = $_POST['tanggal'] ?? date('d/m/Y');
        $tanggal = parse_dmy_to_ymd($tanggalRaw);
        $arahMutasi = ($_POST['arah_mutasi'] ?? 'keluar') === 'masuk' ? 'masuk' : 'keluar';
        $kasTerkait = trim($_POST['kas_terkait'] ?? '');
        $noBukti = trim($_POST['no_bukti'] ?? '');
        $keteranganUser = trim($_POST['keterangan'] ?? '');

        $validTerkait = [
            'kas_bank' => 'Kas Bank',
            'kas_toko' => 'Kas Toko',
            'kas_sp'   => 'Kas SP'
        ];

        if (!array_key_exists($kasTerkait, $validTerkait)) {
            set_flash('error', 'Pilih unit kas terkait yang valid (Kas Bank, Kas Toko, atau Kas SP).');
            header("Location: $pageSelf");
            exit;
        }

        $rawNominal = str_replace(['Rp', '.', ' '], '', $_POST['nominal'] ?? '0');
        $rawNominal = str_replace(',', '.', $rawNominal);
        $nominal = (float)$rawNominal;

        if ($nominal <= 0) {
            set_flash('error', 'Nominal mutasi kas harus lebih besar dari Rp 0.');
            header("Location: $pageSelf");
            exit;
        }

        $namaTerkait = $validTerkait[$kasTerkait];
        $ym = date('Ym', strtotime($tanggal));

        if ($noBukti === '') {
            $countMut = $db->query("SELECT COUNT(*) FROM buku_kas WHERE no_bukti LIKE 'MUT-%'")->fetchColumn() + 1;
            $noBukti = "MUT-{$ym}-" . str_pad($countMut, 3, '0', STR_PAD_LEFT);
        }

        $petugas = $currentUser['nama_lengkap'] ?? 'Admin';

        try {
            $db->beginTransaction();

            if ($arahMutasi === 'keluar') {
                // 1. Kas Umum: Kas Keluar
                $ketUmum = "Mutasi kas keluar ke {$namaTerkait}" . ($keteranganUser !== '' ? " - {$keteranganUser}" : "");
                $katUmum = "[Mutasi] Pengeluaran Kas ke {$namaTerkait}";
                $stmt1 = $db->prepare("INSERT INTO buku_kas 
                    (tipe_kas, tanggal, no_bukti, keterangan, kategori, jenis_transaksi, kas_masuk, kas_keluar, petugas) 
                    VALUES (?, ?, ?, ?, ?, 'keluar', 0, ?, ?)");
                $stmt1->execute(['kas_umum', $tanggal, $noBukti, $ketUmum, $katUmum, $nominal, $petugas]);

                // 2. Kas Terkait: Kas Masuk
                $ketLain = "Penerimaan mutasi kas dari Kas Umum" . ($keteranganUser !== '' ? " - {$keteranganUser}" : "");
                $katLain = "[Mutasi] Penerimaan Kas dari Kas Umum";
                $stmt2 = $db->prepare("INSERT INTO buku_kas 
                    (tipe_kas, tanggal, no_bukti, keterangan, kategori, jenis_transaksi, kas_masuk, kas_keluar, petugas) 
                    VALUES (?, ?, ?, ?, ?, 'masuk', ?, 0, ?)");
                $stmt2->execute([$kasTerkait, $tanggal, $noBukti, $ketLain, $katLain, $nominal, $petugas]);

                $msg = "Mutasi kas keluar ke <strong>{$namaTerkait}</strong> sebesar " . rupiah($nominal) . " berhasil disimpan dan otomatis dicatat sebagai kas masuk di {$namaTerkait}.";
            } else {
                // 1. Kas Umum: Kas Masuk
                $ketUmum = "Penerimaan mutasi kas dari {$namaTerkait}" . ($keteranganUser !== '' ? " - {$keteranganUser}" : "");
                $katUmum = "[Mutasi] Penerimaan Kas dari {$namaTerkait}";
                $stmt1 = $db->prepare("INSERT INTO buku_kas 
                    (tipe_kas, tanggal, no_bukti, keterangan, kategori, jenis_transaksi, kas_masuk, kas_keluar, petugas) 
                    VALUES (?, ?, ?, ?, ?, 'masuk', ?, 0, ?)");
                $stmt1->execute(['kas_umum', $tanggal, $noBukti, $ketUmum, $katUmum, $nominal, $petugas]);

                // 2. Kas Terkait: Kas Keluar
                $ketLain = "Mutasi pengeluaran kas ke Kas Umum" . ($keteranganUser !== '' ? " - {$keteranganUser}" : "");
                $katLain = "[Mutasi] Pengeluaran Kas ke Kas Umum";
                $stmt2 = $db->prepare("INSERT INTO buku_kas 
                    (tipe_kas, tanggal, no_bukti, keterangan, kategori, jenis_transaksi, kas_masuk, kas_keluar, petugas) 
                    VALUES (?, ?, ?, ?, ?, 'keluar', 0, ?, ?)");
                $stmt2->execute([$kasTerkait, $tanggal, $noBukti, $ketLain, $katLain, $nominal, $petugas]);

                $msg = "Mutasi penerimaan kas dari <strong>{$namaTerkait}</strong> sebesar " . rupiah($nominal) . " berhasil disimpan dan otomatis dicatat sebagai kas keluar di {$namaTerkait}.";
            }

            $db->commit();
            set_flash('success', $msg);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            set_flash('error', 'Gagal memproses mutasi kas: ' . $e->getMessage());
        }

        header("Location: $pageSelf");
        exit;
    }

    // HAPUS TRANSAKSI
    if ($action === 'hapus_kas') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmtCek = $db->prepare("SELECT no_bukti FROM buku_kas WHERE id = ?");
            $stmtCek->execute([$id]);
            $noBkt = $stmtCek->fetchColumn();

            if ($noBkt && strpos($noBkt, 'MUT-') === 0) {
                // Hapus kedua sisi transaksi mutasi agar selalu seimbang
                $db->prepare("DELETE FROM buku_kas WHERE no_bukti = ?")->execute([$noBkt]);
                set_flash('success', "Transaksi mutasi ({$noBkt}) dan pasangannya di kas terkait berhasil dihapus.");
            } else {
                $db->prepare("DELETE FROM buku_kas WHERE id = ? AND tipe_kas = ?")->execute([$id, $tipeKas]);
                set_flash('success', 'Catatan transaksi buku kas berhasil dihapus.');
            }
        }
        header("Location: $pageSelf");
        exit;
    }
}

// =========================================================================
// 2. FILTER PARAMETERS & PERIODE PEMBUKUAN
// =========================================================================
$tglMulaiRaw = $_GET['tgl_mulai'] ?? date('Y-01-01');
$tglSelesaiRaw = $_GET['tgl_selesai'] ?? date('Y-12-31');
$tglMulai = parse_dmy_to_ymd($tglMulaiRaw);
$tglSelesai = parse_dmy_to_ymd($tglSelesaiRaw);

$filterArus = $_GET['arus'] ?? 'semua'; // semua, masuk, keluar
$filterKategori = trim($_GET['kategori'] ?? 'semua');

// =========================================================================
// 3. HITUNG SALDO AWAL (NERACA + TRANSAKSI SEBELUM TGL MULAI)
// =========================================================================
$saldoAwalNeraca = get_saldo_awal_neraca_kas($tipeKas);

$stmtPrev = $db->prepare("
    SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) 
    FROM buku_kas 
    WHERE tipe_kas = ? AND tanggal < ?
");
$stmtPrev->execute([$tipeKas, $tglMulai]);
$mutasiSebelumMulai = (float)$stmtPrev->fetchColumn();

$saldoAwalPeriode = $saldoAwalNeraca + $mutasiSebelumMulai;

// =========================================================================
// 4. AMBIL TRANSAKSI PERIODE INI
// =========================================================================
$sql = "SELECT * FROM buku_kas WHERE tipe_kas = ? AND tanggal BETWEEN ? AND ?";
$params = [$tipeKas, $tglMulai, $tglSelesai];

if ($filterArus === 'masuk') {
    $sql .= " AND jenis_transaksi = 'masuk'";
} elseif ($filterArus === 'keluar') {
    $sql .= " AND jenis_transaksi = 'keluar'";
}

if ($filterKategori !== 'semua' && $filterKategori !== '') {
    $sql .= " AND kategori = ?";
    $params[] = $filterKategori;
}

$sql .= " ORDER BY tanggal ASC, id ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filteredData = [];
$runningSaldo = $saldoAwalPeriode;
$totalMasukPeriode = 0;
$totalKeluarPeriode = 0;

foreach ($rawRows as $r) {
    $masuk = (float)$r['kas_masuk'];
    $keluar = (float)$r['kas_keluar'];
    $runningSaldo += ($masuk - $keluar);
    $r['saldo'] = $runningSaldo;

    $totalMasukPeriode += $masuk;
    $totalKeluarPeriode += $keluar;
    $filteredData[] = $r;
}

$saldoAkhirPeriode = $saldoAwalPeriode + $totalMasukPeriode - $totalKeluarPeriode;

// Hitung Saldo Keseluruhan Sepanjang Waktu
$stmtAll = $db->prepare("SELECT COALESCE(SUM(kas_masuk - kas_keluar), 0) FROM buku_kas WHERE tipe_kas = ?");
$stmtAll->execute([$tipeKas]);
$saldoFisikSaatIni = $saldoAwalNeraca + (float)$stmtAll->fetchColumn();

// Ambil Kategori Terstruktur dari Neraca & Laba Rugi
$kategoriGrouped = get_kategori_kas_grouped();

// No Bukti Rekomendasi Baru
$ym = date('Ym');
$stmtCountYM = $db->prepare("SELECT COUNT(*) FROM buku_kas WHERE tipe_kas = ? AND no_bukti LIKE ?");
$stmtCountYM->execute([$tipeKas, "%-{$ym}-%"]);
$nextNum = $stmtCountYM->fetchColumn() + 1;
$rekomNoBuktiMasuk = "{$prefixBuktiM}-{$ym}-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
$rekomNoBuktiKeluar = "{$prefixBuktiK}-{$ym}-" . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

// No Bukti Rekomendasi Mutasi
$stmtCountMut = $db->query("SELECT COUNT(*) FROM buku_kas WHERE no_bukti LIKE 'MUT-%'");
$nextNumMut = (int)$stmtCountMut->fetchColumn() + 1;
$rekomNoBuktiMut = "MUT-{$ym}-" . str_pad($nextNumMut, 3, '0', STR_PAD_LEFT);

// =========================================================================
// 5. EXPORT CSV
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Buku_' . ucfirst($tipeKas) . '_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($out, ['BUKU ' . strtoupper($pageTitle) . ' - KOPERASI GATRA TEKNIKA'], ';');
    fputcsv($out, ['Periode: ' . tgl_dmy($tglMulai) . ' s/d ' . tgl_dmy($tglSelesai)], ';');
    fputcsv($out, ['Tanggal Unduh: ' . tgl_indo(date('Y-m-d'), true)], ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['Tanggal', 'No. Bukti', 'Keterangan', 'Kategori', 'Kas Masuk', 'Kas Keluar', 'Saldo'], ';');

    // Saldo Awal
    fputcsv($out, [
        tgl_dmy($tglMulai),
        'SALDO-AWAL',
        'Saldo Awal Periode',
        'Saldo Awal Neraca',
        0,
        0,
        $saldoAwalPeriode
    ], ';');

    foreach ($filteredData as $row) {
        fputcsv($out, [
            tgl_dmy($row['tanggal']),
            $row['no_bukti'],
            $row['keterangan'],
            $row['kategori'],
            $row['kas_masuk'],
            $row['kas_keluar'],
            $row['saldo']
        ], ';');
    }

    fputcsv($out, [], ';');
    fputcsv($out, ['TOTAL MUTASI PERIODE', '', '', '', $totalMasukPeriode, $totalKeluarPeriode, $saldoAkhirPeriode], ';');
    fclose($out);
    exit;
}

include __DIR__ . '/header.php';
?>

<!-- Header Halaman & Action Buttons -->
<div class="row" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
  <div>
    <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--secondary); margin-bottom: 4px;">
      <?= htmlspecialchars($pageTitle) ?>
    </h2>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
      <?= htmlspecialchars($pageSubtitle) ?>
    </p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;" class="no-print">
    <?php if ($tipeKas === 'kas_umum'): ?>
    <button type="button" class="btn btn-secondary" onclick="bukaModalMutasiKas()" style="border: 1px solid #0284c7; background: #f0f9ff; color: #0284c7; font-weight: 700;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
      🔁 Mutasi Antar Kas
    </button>
    <?php endif; ?>
    <button type="button" class="btn btn-primary" onclick="bukaModalTambahKas()">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      + Input Transaksi <?= htmlspecialchars($pageTitle) ?>
    </button>
    <a href="<?= $pageSelf ?>?tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_selesai=<?= urlencode($tglSelesai) ?>&arus=<?= urlencode($filterArus) ?>&kategori=<?= urlencode($filterKategori) ?>&export=csv" 
       class="btn btn-secondary" style="border: 1px solid #cbd5e1; background: #ffffff;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Unduh Excel (CSV)
    </a>
    <button type="button" class="btn btn-secondary" onclick="window.print()" style="border: 1px solid #cbd5e1; background: #ffffff;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Cetak / PDF
    </button>
  </div>
</div>

<!-- Print-Only Header Resmi Koperasi -->
<div class="print-header">
  <h2 style="font-size: 1.4rem; font-weight: 800; margin-bottom: 2px;">KOPERASI SIMPAN PINJAM GATRA TEKNIKA</h2>
  <p style="font-size: 0.88rem; margin-bottom: 4px;">Jl. By Pass Sunyaragi No. 45, Cirebon, Jawa Barat &bull; Telp: (0231) 482910</p>
  <div style="border-bottom: 2px solid #0f172a; margin: 12px 0;"></div>
  <p style="font-size: 1.05rem; font-weight: 800; text-transform: uppercase; margin: 4px 0;">
    BUKU LAPORAN <?= strtoupper($pageTitle) ?>
  </p>
  <p style="font-size: 0.85rem; color: #334155;">
    Periode Pembukuan: <strong><?= tgl_dmy($tglMulai) ?></strong> s/d <strong><?= tgl_dmy($tglSelesai) ?></strong> &bull; Dicetak: <?= tgl_indo(date('Y-m-d'), true) ?>
  </p>
</div>

<!-- Card Filter Parameter (No-Print) -->
<div class="card no-print" style="margin-bottom: 24px;">
  <div class="card-header" style="padding: 14px 20px;">
    <h3 style="font-size: 1rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
      Filter Parameter <?= htmlspecialchars($pageTitle) ?>
    </h3>
    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
      <a href="<?= $pageSelf ?>?tgl_mulai=<?= date('Y-m-01') ?>&tgl_selesai=<?= date('Y-m-t') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Bulan Ini</a>
      <a href="<?= $pageSelf ?>?tgl_mulai=<?= date('Y-01-01') ?>&tgl_selesai=<?= date('Y-12-31') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Tahun <?= date('Y') ?></a>
      <a href="<?= $pageSelf ?>?tgl_mulai=2020-01-01&tgl_selesai=<?= date('Y-12-31') ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Semua Waktu</a>
      <a href="<?= $pageSelf ?>" class="btn btn-secondary btn-sm" style="font-size: 0.78rem;">Reset</a>
    </div>
  </div>

  <form method="GET" action="<?= $pageSelf ?>" style="padding: 16px 20px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end;">
      
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

      <!-- Arus Kas -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Arus Kas:</label>
        <select name="arus" class="form-select" style="font-size: 0.86rem; padding: 8px 12px;">
          <option value="semua" <?= $filterArus === 'semua' ? 'selected' : '' ?>>Semua (Masuk & Keluar)</option>
          <option value="masuk" <?= $filterArus === 'masuk' ? 'selected' : '' ?>>Kas Masuk Saja</option>
          <option value="keluar" <?= $filterArus === 'keluar' ? 'selected' : '' ?>>Kas Keluar Saja</option>
        </select>
      </div>

      <!-- Filter Kategori (Dari Neraca & Laba Rugi) -->
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Kategori Rekening:</label>
        <select name="kategori" class="form-select" style="font-size: 0.86rem; padding: 8px 12px;">
          <option value="semua">Semua Kategori</option>
          <?php foreach ($kategoriGrouped as $grpTitle => $items): ?>
            <?php if (!empty($items)): ?>
              <optgroup label="<?= htmlspecialchars($grpTitle) ?>">
                <?php foreach ($items as $it): ?>
                  <option value="<?= htmlspecialchars($it['val']) ?>" <?= $filterKategori === $it['val'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($it['val']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          <?php endforeach; ?>
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

<!-- Main Table Card: Buku Kas -->
<div class="card">
  <div class="card-header no-print" style="flex-wrap: wrap; gap: 12px;">
    <div>
      <h3 style="margin: 0; font-weight: 800;">Daftar Transaksi <?= htmlspecialchars($pageTitle) ?></h3>
      <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
        Menampilkan <strong><?= count($filteredData) ?></strong> catatan transaksi pada periode terpilih
      </p>
    </div>
    <div class="card-header-actions">
      <input type="text" class="form-control" style="width: 250px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari bukti, ket, kategori..." data-table-search="tableBukuKas">
    </div>
  </div>

  <div class="table-freeze-container">
    <table class="table" id="tableBukuKas">
      <thead>
        <tr>
          <th style="width: 105px;">Tanggal</th>
          <th style="width: 140px;">No. Bukti</th>
          <th>Keterangan</th>
          <th style="width: 220px;">Kategori</th>
          <th style="text-align: right; width: 145px;">Kas Masuk</th>
          <th style="text-align: right; width: 145px;">Kas Keluar</th>
          <th style="text-align: right; width: 160px;">Saldo</th>
          <th style="text-align: center; width: 100px;" class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <!-- Baris Saldo Awal Periode -->
        <tr style="background-color: #f8fafc; font-weight: 700; border-bottom: 2px solid #e2e8f0;">
          <td><?= tgl_dmy($tglMulai) ?></td>
          <td><span class="badge badge-neutral" style="font-size:0.75rem;">SALDO-AWAL</span></td>
          <td style="color: var(--secondary);">
            <em>Saldo awal <?= htmlspecialchars($pageTitle) ?> sebelum periode <?= tgl_dmy($tglMulai) ?></em>
          </td>
          <td><span class="badge badge-info" style="font-size:0.75rem;">Saldo Awal Neraca</span></td>
          <td class="table-amount">-</td>
          <td class="table-amount">-</td>
          <td class="table-amount text-blue" style="font-weight: 800; font-size: 0.95rem;">
            <?= rupiah($saldoAwalPeriode) ?>
          </td>
          <td class="no-print" style="text-align: center; color: var(--text-muted);">-</td>
        </tr>

        <?php if (empty($filteredData)): ?>
          <tr>
            <td colspan="8" style="text-align: center; padding: 36px; color: var(--text-subtle);">
              Belum ada catatan transaksi <?= htmlspecialchars($pageTitle) ?> pada periode ini.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($filteredData as $row): 
            $masuk = (float)$row['kas_masuk'];
            $keluar = (float)$row['kas_keluar'];
          ?>
            <tr>
              <td><?= tgl_dmy($row['tanggal']) ?></td>
              <td>
                <strong style="color: var(--secondary); font-size: 0.85rem;">
                  <?= htmlspecialchars($row['no_bukti']) ?>
                </strong>
              </td>
              <td>
                <div style="font-weight: 600; color: var(--text-main); font-size: 0.88rem;">
                  <?= htmlspecialchars($row['keterangan']) ?>
                </div>
              </td>
              <td>
                <span class="badge <?= $masuk > 0 ? 'badge-success' : 'badge-neutral' ?>" style="font-size: 0.75rem; font-weight: 600; white-space: normal; text-align: left; display: inline-block; max-width: 210px; line-height: 1.3; padding: 4px 8px;">
                  <?= htmlspecialchars($row['kategori']) ?>
                </span>
              </td>
              
              <!-- Kas Masuk -->
              <td class="table-amount <?= $masuk > 0 ? 'text-green' : '' ?>" style="font-weight: 700;">
                <?= $masuk > 0 ? rupiah($masuk) : '-' ?>
              </td>

              <!-- Kas Keluar -->
              <td class="table-amount <?= $keluar > 0 ? 'text-red' : '' ?>" style="font-weight: 700;">
                <?= $keluar > 0 ? rupiah($keluar) : '-' ?>
              </td>

              <!-- Saldo -->
              <td class="table-amount" style="font-weight: 800; color: var(--secondary);">
                <?= rupiah($row['saldo']) ?>
              </td>

              <!-- Aksi -->
              <td class="no-print" style="text-align: center;">
                <div style="display: inline-flex; gap: 4px;">
                  <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 7px;" title="Edit Transaksi"
                          onclick='bukaModalEditKas(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </button>
                  <form method="POST" action="<?= $pageSelf ?>" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi bukti <?= htmlspecialchars($row['no_bukti']) ?> ini?')">
                    <input type="hidden" name="action" value="hapus_kas">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 7px; color: #ef4444;" title="Hapus Transaksi">
                      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>

      <!-- Footer Akumulasi Periode -->
      <tfoot>
        <tr style="background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1;">
          <td colspan="4" style="padding: 14px 18px; text-transform: uppercase;">
            TOTAL MUTASI PERIODE INI (<?= tgl_dmy($tglMulai) ?> s/d <?= tgl_dmy($tglSelesai) ?>)
          </td>
          <td class="table-amount text-green" style="font-size: 0.98rem;">
            <?= rupiah($totalMasukPeriode) ?>
          </td>
          <td class="table-amount text-red" style="font-size: 0.98rem;">
            <?= rupiah($totalKeluarPeriode) ?>
          </td>
          <td class="table-amount text-blue" style="font-size: 1.05rem;">
            <?= rupiah($saldoAkhirPeriode) ?>
          </td>
          <td class="no-print"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: TAMBAH TRANSAKSI KAS                                              -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalTambahKas" style="display:none;">
  <div class="modal-card" style="max-width: 580px;">
    <div class="modal-header">
      <h3 style="margin: 0; font-weight: 800; color: var(--secondary);">
        + Input Transaksi <?= htmlspecialchars($pageTitle) ?>
      </h3>
      <button type="button" class="modal-close" onclick="closeModal('modalTambahKas')">&times;</button>
    </div>

    <form method="POST" action="<?= $pageSelf ?>" id="formTambahKas">
      <input type="hidden" name="action" value="tambah_kas">

      <div class="modal-body" style="padding: 20px 24px;">
        
        <!-- Jenis Arus Kas -->
        <div class="form-group">
          <label class="form-label">Jenis Arus Transaksi *</label>
          <div style="display: flex; gap: 14px; margin-top: 4px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px; flex: 1;" id="lblArusMasuk">
              <input type="radio" name="jenis_transaksi" value="masuk" checked onchange="toggleArusTransaksi('masuk', 'tambah')">
              <strong class="text-green">🟢 Kas Masuk (Penerimaan)</strong>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px; flex: 1;" id="lblArusKeluar">
              <input type="radio" name="jenis_transaksi" value="keluar" onchange="toggleArusTransaksi('keluar', 'tambah')">
              <strong class="text-red">🔴 Kas Keluar (Pengeluaran)</strong>
            </label>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <!-- Tanggal -->
          <div class="form-group">
            <label class="form-label">Tanggal Transaksi *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required>
              <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 2px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted);" title="Buka Kalender">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>

          <!-- No. Bukti -->
          <div class="form-group">
            <label class="form-label">No. Bukti / Referensi *</label>
            <input type="text" name="no_bukti" id="inputNoBuktiTambah" class="form-control" value="<?= htmlspecialchars($rekomNoBuktiMasuk) ?>" required>
          </div>
        </div>

        <!-- Kategori Rekening (Dengan Kolom Pencarian Cepat) -->
        <div class="form-group" style="position: relative;">
          <label class="form-label">Kategori Rekening (Neraca & Laba/Rugi) *</label>
          <input type="hidden" name="kategori" id="hidden_kategori_tambah" required>

          <!-- Trigger Box Dropdown Pencarian -->
          <div id="kategoriTriggerTambah" class="form-control" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; min-height: 42px; padding: 8px 12px; background: #fff; user-select: none;">
            <span id="kategoriTriggerTextTambah" style="color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.88rem;">
              -- Cari & Pilih Kategori Rekening --
            </span>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); flex-shrink: 0; margin-left: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>

          <!-- Dropdown Menu dengan Search Bar -->
          <div id="kategoriMenuTambah" class="custom-search-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.18), 0 8px 10px -6px rgba(0,0,0,0.1); z-index: 1060; overflow: hidden;">
            <div style="padding: 10px; border-bottom: 1px solid var(--border-color); background: #f8fafc;">
              <div style="position: relative; display: flex; align-items: center;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="position: absolute; left: 10px; color: var(--text-muted); pointer-events: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="kategoriSearchTambah" class="form-control form-control-sm" placeholder="Ketik kode atau nama rekening (misal: bunga, kas, atk, gaji)..." style="padding-left: 32px; font-size: 0.84rem;" autocomplete="off">
              </div>
            </div>

            <div id="kategoriListTambah" style="max-height: 240px; overflow-y: auto;">
              <?php foreach ($kategoriGrouped as $grpTitle => $items): ?>
                <?php if (!empty($items)): ?>
                  <div class="kategori-group" style="border-bottom: 1px solid #f1f5f9;">
                    <div style="padding: 6px 12px; background: #f8fafc; font-size: 0.73rem; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                      <?= htmlspecialchars($grpTitle) ?>
                    </div>
                    <?php foreach ($items as $it): 
                      $searchKey = strtolower($it['val'] . ' ' . $it['kode'] . ' ' . $it['nama']);
                    ?>
                      <div class="kategori-opt-item-tambah" 
                           data-val="<?= htmlspecialchars($it['val']) ?>"
                           data-arus="<?= $it['arus'] ?>"
                           data-text="<?= htmlspecialchars($searchKey) ?>"
                           style="padding: 9px 14px; cursor: pointer; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; transition: background 0.15s ease;"
                           onmouseover="this.style.backgroundColor='#f1f5f9'"
                           onmouseout="this.style.backgroundColor='#ffffff'">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                          <span style="font-weight: 600; color: var(--secondary);">
                            <?= htmlspecialchars($it['val']) ?>
                          </span>
                          <span class="badge <?= $it['arus'] === 'masuk' ? 'badge-success' : ($it['arus'] === 'keluar' ? 'badge-danger' : 'badge-neutral') ?>" style="font-size: 0.68rem; padding: 2px 6px; flex-shrink: 0;">
                            <?= ucfirst($it['arus']) ?>
                          </span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>

              <div id="kategoriEmptyNoticeTambah" style="display: none; padding: 18px; text-align: center; color: var(--text-muted); font-size: 0.82rem;">
                Tidak ada rekening yang cocok dengan kata kunci pencarian.
              </div>
            </div>
          </div>

          <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 4px;">
            Pilihan bersumber langsung dari <a href="pengaturan.php?tab=neraca" target="_blank" style="color:var(--primary); text-decoration:underline;">Tab Neraca</a> dan <a href="pengaturan.php?tab=labarugi" target="_blank" style="color:var(--primary); text-decoration:underline;">Tab Laba/Rugi</a>.
          </small>
        </div>

        <!-- Nominal -->
        <div class="form-group">
          <label class="form-label">Nominal (Rp) *</label>
          <input type="text" name="nominal" class="form-control" placeholder="Rp 0" required oninput="formatRupiahInput(this)" style="font-size: 1.1rem; font-weight: 700;">
        </div>

        <!-- Keterangan -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Keterangan Transaksi *</label>
          <textarea name="keterangan" class="form-control" rows="3" placeholder="Contoh: Pembelian perlengkapan kantor, penerimaan omset harian..." required></textarea>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambahKas')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Simpan Transaksi
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT TRANSAKSI KAS                                                -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalEditKas" style="display:none;">
  <div class="modal-card" style="max-width: 580px;">
    <div class="modal-header">
      <h3 style="margin: 0; font-weight: 800; color: var(--secondary);">
        Edit Transaksi <?= htmlspecialchars($pageTitle) ?>
      </h3>
      <button type="button" class="modal-close" onclick="closeModal('modalEditKas')">&times;</button>
    </div>

    <form method="POST" action="<?= $pageSelf ?>" id="formEditKas">
      <input type="hidden" name="action" value="edit_kas">
      <input type="hidden" name="id" id="edit_id" value="">

      <div class="modal-body" style="padding: 20px 24px;">
        
        <!-- Jenis Arus Kas -->
        <div class="form-group">
          <label class="form-label">Jenis Arus Transaksi *</label>
          <div style="display: flex; gap: 14px; margin-top: 4px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px; flex: 1;" id="lblEditMasuk">
              <input type="radio" name="jenis_transaksi" id="edit_arus_masuk" value="masuk">
              <strong class="text-green">🟢 Kas Masuk</strong>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px; flex: 1;" id="lblEditKeluar">
              <input type="radio" name="jenis_transaksi" id="edit_arus_keluar" value="keluar">
              <strong class="text-red">🔴 Kas Keluar</strong>
            </label>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <!-- Tanggal -->
          <div class="form-group">
            <label class="form-label">Tanggal Transaksi *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal" id="edit_tanggal" class="form-control date-dmy-input" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required>
              <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 2px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted);" title="Buka Kalender">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" id="edit_tanggal_native" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>

          <!-- No. Bukti -->
          <div class="form-group">
            <label class="form-label">No. Bukti / Referensi *</label>
            <input type="text" name="no_bukti" id="edit_no_bukti" class="form-control" required>
          </div>
        </div>

        <!-- Kategori Rekening (Dengan Kolom Pencarian Cepat) -->
        <div class="form-group" style="position: relative;">
          <label class="form-label">Kategori Rekening (Neraca & Laba/Rugi) *</label>
          <input type="hidden" name="kategori" id="hidden_kategori_edit" required>

          <!-- Trigger Box Dropdown Pencarian Edit -->
          <div id="kategoriTriggerEdit" class="form-control" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; min-height: 42px; padding: 8px 12px; background: #fff; user-select: none;">
            <span id="kategoriTriggerTextEdit" style="color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.88rem;">
              -- Cari & Pilih Kategori Rekening --
            </span>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); flex-shrink: 0; margin-left: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>

          <!-- Dropdown Menu dengan Search Bar Edit -->
          <div id="kategoriMenuEdit" class="custom-search-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.18), 0 8px 10px -6px rgba(0,0,0,0.1); z-index: 1060; overflow: hidden;">
            <div style="padding: 10px; border-bottom: 1px solid var(--border-color); background: #f8fafc;">
              <div style="position: relative; display: flex; align-items: center;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="position: absolute; left: 10px; color: var(--text-muted); pointer-events: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="kategoriSearchEdit" class="form-control form-control-sm" placeholder="Ketik kode atau nama rekening..." style="padding-left: 32px; font-size: 0.84rem;" autocomplete="off">
              </div>
            </div>

            <div id="kategoriListEdit" style="max-height: 240px; overflow-y: auto;">
              <?php foreach ($kategoriGrouped as $grpTitle => $items): ?>
                <?php if (!empty($items)): ?>
                  <div class="kategori-group" style="border-bottom: 1px solid #f1f5f9;">
                    <div style="padding: 6px 12px; background: #f8fafc; font-size: 0.73rem; font-weight: 800; text-transform: uppercase; color: #475569; letter-spacing: 0.5px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                      <?= htmlspecialchars($grpTitle) ?>
                    </div>
                    <?php foreach ($items as $it): 
                      $searchKey = strtolower($it['val'] . ' ' . $it['kode'] . ' ' . $it['nama']);
                    ?>
                      <div class="kategori-opt-item-edit" 
                           data-val="<?= htmlspecialchars($it['val']) ?>"
                           data-arus="<?= $it['arus'] ?>"
                           data-text="<?= htmlspecialchars($searchKey) ?>"
                           style="padding: 9px 14px; cursor: pointer; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; transition: background 0.15s ease;"
                           onmouseover="this.style.backgroundColor='#f1f5f9'"
                           onmouseout="this.style.backgroundColor='#ffffff'">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                          <span style="font-weight: 600; color: var(--secondary);">
                            <?= htmlspecialchars($it['val']) ?>
                          </span>
                          <span class="badge <?= $it['arus'] === 'masuk' ? 'badge-success' : ($it['arus'] === 'keluar' ? 'badge-danger' : 'badge-neutral') ?>" style="font-size: 0.68rem; padding: 2px 6px; flex-shrink: 0;">
                            <?= ucfirst($it['arus']) ?>
                          </span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>

              <div id="kategoriEmptyNoticeEdit" style="display: none; padding: 18px; text-align: center; color: var(--text-muted); font-size: 0.82rem;">
                Tidak ada rekening yang cocok dengan kata kunci pencarian.
              </div>
            </div>
          </div>
        </div>

        <!-- Nominal -->
        <div class="form-group">
          <label class="form-label">Nominal (Rp) *</label>
          <input type="text" name="nominal" id="edit_nominal" class="form-control" placeholder="Rp 0" required oninput="formatRupiahInput(this)" style="font-size: 1.1rem; font-weight: 700;">
        </div>

        <!-- Keterangan -->
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Keterangan Transaksi *</label>
          <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="3" required></textarea>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditKas')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Perbarui Transaksi
        </button>
      </div>
    </form>
  </div>
</div>

<?php if ($tipeKas === 'kas_umum'): ?>
<!-- ========================================================================= -->
<!-- MODAL: MUTASI ANTAR KAS (KHUSUS KAS UMUM)                                -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalMutasiKas" style="display:none;">
  <div class="modal-card" style="max-width: 580px;">
    <div class="modal-header">
      <h3 style="margin: 0; font-weight: 800; color: var(--secondary);">
        🔁 Mutasi Antar Kas Koperasi
      </h3>
      <button type="button" class="modal-close" onclick="closeModal('modalMutasiKas')">&times;</button>
    </div>

    <form method="POST" action="<?= $pageSelf ?>" id="formMutasiKas">
      <input type="hidden" name="action" value="mutasi_kas">

      <div class="modal-body" style="padding: 20px 24px;">
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 0.85rem; color: #0369a1; line-height: 1.5;">
          <strong>ℹ Info Mutasi Otomatis:</strong> Setiap mutasi antar kas yang dicatat di sini akan otomatis membuat catatan berpasangan di unit kas tujuan/sumber secara seimbang (double-entry).
        </div>

        <!-- Arah Mutasi -->
        <div class="form-group">
          <label class="form-label">Arah Aliran Mutasi *</label>
          <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 4px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff;" id="lblArahKeluar">
              <input type="radio" name="arah_mutasi" value="keluar" checked onchange="updateMutasiPreview()">
              <div>
                <strong style="color: #dc2626;">Kas Keluar dari Kas Umum &rarr; Masuk ke Unit Kas Terkait</strong>
                <div style="font-size: 0.78rem; color: var(--text-muted);">Saldo Kas Umum berkurang, Kas tujuan bertambah (misal: setor ke bank, modal kasir toko, atau kas SP)</div>
              </div>
            </label>
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff;" id="lblArahMasuk">
              <input type="radio" name="arah_mutasi" value="masuk" onchange="updateMutasiPreview()">
              <div>
                <strong style="color: #16a34a;">Kas Masuk ke Kas Umum &larr; Diambil dari Unit Kas Terkait</strong>
                <div style="font-size: 0.78rem; color: var(--text-muted);">Saldo Kas Umum bertambah, Kas sumber berkurang (misal: penarikan bank, setoran kasir toko, atau kas SP)</div>
              </div>
            </label>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <!-- Unit Kas Terkait -->
          <div class="form-group">
            <label class="form-label">Unit Kas Terkait *</label>
            <select name="kas_terkait" id="mutasi_kas_terkait" class="form-select" required onchange="updateMutasiPreview()" style="font-weight: 600;">
              <option value="kas_bank">Kas Bank</option>
              <option value="kas_toko">Kas Toko</option>
              <option value="kas_sp">Kas SP (Simpan Pinjam)</option>
            </select>
          </div>

          <!-- No. Bukti -->
          <div class="form-group">
            <label class="form-label">No. Bukti Mutasi *</label>
            <input type="text" name="no_bukti" id="inputNoBuktiMut" class="form-control" value="<?= htmlspecialchars($rekomNoBuktiMut) ?>" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <!-- Tanggal -->
          <div class="form-group">
            <label class="form-label">Tanggal Transaksi *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required>
              <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 2px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted);" title="Buka Kalender">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>

          <!-- Nominal -->
          <div class="form-group">
            <label class="form-label">Nominal Mutasi (Rp) *</label>
            <input type="text" name="nominal" id="mutasi_nominal" class="form-control" placeholder="Rp 0" required oninput="formatRupiahInput(this); updateMutasiPreview();" style="font-size: 1.05rem; font-weight: 700;">
          </div>
        </div>

        <!-- Keterangan Tambahan -->
        <div class="form-group" style="margin-bottom: 8px;">
          <label class="form-label">Keterangan / Keperluan (Opsional)</label>
          <textarea name="keterangan" id="mutasi_keterangan" class="form-control" rows="2" placeholder="Contoh: Setor tunai ke rekening bank operasional, alokasi kas kasir toko..." oninput="updateMutasiPreview()"></textarea>
        </div>

        <!-- Live Preview Efek Pembukuan -->
        <div id="mutasiPreviewBox" style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 12px; font-size: 0.82rem; color: #334155;">
          <strong>Simulasi Pembukuan Berpasangan:</strong>
          <div id="mutasiPreviewText" style="margin-top: 4px; line-height: 1.4;">
            - Kas Umum: Pengeluaran (Rp 0)<br>
            - Kas Bank: Penerimaan (Rp 0)
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalMutasiKas')">Batal</button>
        <button type="submit" class="btn btn-primary" style="background: #0284c7; border-color: #0284c7;">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
          Proses Mutasi Kas
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const rekomMasuk = <?= json_encode($rekomNoBuktiMasuk) ?>;
const rekomKeluar = <?= json_encode($rekomNoBuktiKeluar) ?>;
const rekomMut = <?= json_encode($rekomNoBuktiMut) ?>;

function bukaModalMutasiKas() {
  const form = document.getElementById('formMutasiKas');
  if (form) form.reset();
  const inputBukti = document.getElementById('inputNoBuktiMut');
  if (inputBukti) inputBukti.value = rekomMut;
  updateMutasiPreview();
  openModal('modalMutasiKas');
}

function updateMutasiPreview() {
  const arahEl = document.querySelector('#formMutasiKas input[name="arah_mutasi"]:checked');
  const arah = arahEl ? arahEl.value : 'keluar';
  const selectTerkait = document.getElementById('mutasi_kas_terkait');
  const namaTerkait = selectTerkait ? selectTerkait.options[selectTerkait.selectedIndex].text : 'Unit Kas Terkait';
  const nomEl = document.getElementById('mutasi_nominal');
  const nom = (nomEl && nomEl.value && nomEl.value.trim() !== '') ? nomEl.value : 'Rp 0';
  const previewDiv = document.getElementById('mutasiPreviewText');

  if (!previewDiv) return;

  if (arah === 'keluar') {
    previewDiv.innerHTML = `
      <div style="color: #dc2626;">&bull; <strong>Kas Umum:</strong> Kas Keluar sebesar <strong>${nom}</strong></div>
      <div style="color: #16a34a; margin-top: 2px;">&bull; <strong>${namaTerkait}:</strong> Kas Masuk sebesar <strong>${nom}</strong> (Otomatis tercatat)</div>
    `;
  } else {
    previewDiv.innerHTML = `
      <div style="color: #16a34a;">&bull; <strong>Kas Umum:</strong> Kas Masuk sebesar <strong>${nom}</strong></div>
      <div style="color: #dc2626; margin-top: 2px;">&bull; <strong>${namaTerkait}:</strong> Kas Keluar sebesar <strong>${nom}</strong> (Otomatis tercatat)</div>
    `;
  }
}

function bukaModalTambahKas() {
  const form = document.getElementById('formTambahKas');
  if (form) form.reset();
  
  document.getElementById('hidden_kategori_tambah').value = '';
  const trigText = document.getElementById('kategoriTriggerTextTambah');
  if (trigText) {
    trigText.textContent = '-- Cari & Pilih Kategori Rekening --';
    trigText.style.color = 'var(--text-muted)';
    trigText.style.fontWeight = 'normal';
  }

  const radioMasuk = document.querySelector('#formTambahKas input[name="jenis_transaksi"][value="masuk"]');
  if (radioMasuk) radioMasuk.checked = true;
  toggleArusTransaksi('masuk', 'tambah');

  openModal('modalTambahKas');
}

function toggleArusTransaksi(arus, mode) {
  if (mode === 'tambah') {
    const inputBukti = document.getElementById('inputNoBuktiTambah');
    if (inputBukti) {
      if (arus === 'masuk') {
        inputBukti.value = rekomMasuk;
      } else {
        inputBukti.value = rekomKeluar;
      }
    }
  }
}

function setupSearchableDropdown(type) {
  const isTambah = (type === 'tambah');
  const trigger = document.getElementById(isTambah ? 'kategoriTriggerTambah' : 'kategoriTriggerEdit');
  const menu = document.getElementById(isTambah ? 'kategoriMenuTambah' : 'kategoriMenuEdit');
  const searchInput = document.getElementById(isTambah ? 'kategoriSearchTambah' : 'kategoriSearchEdit');
  const emptyNotice = document.getElementById(isTambah ? 'kategoriEmptyNoticeTambah' : 'kategoriEmptyNoticeEdit');
  const hiddenInput = document.getElementById(isTambah ? 'hidden_kategori_tambah' : 'hidden_kategori_edit');
  const triggerText = document.getElementById(isTambah ? 'kategoriTriggerTextTambah' : 'kategoriTriggerTextEdit');
  const itemClass = isTambah ? '.kategori-opt-item-tambah' : '.kategori-opt-item-edit';

  if (!trigger || !menu) return;

  trigger.addEventListener('click', function(e) {
    e.stopPropagation();
    const isVisible = (menu.style.display === 'block');
    document.querySelectorAll('.custom-search-dropdown-menu').forEach(m => m.style.display = 'none');
    if (!isVisible) {
      menu.style.display = 'block';
      if (searchInput) {
        searchInput.value = '';
        filterKategoriList('');
        setTimeout(() => searchInput.focus(), 60);
      }
    }
  });

  if (searchInput) {
    searchInput.addEventListener('click', function(e) { e.stopPropagation(); });
    searchInput.addEventListener('input', function() {
      filterKategoriList(this.value.trim().toLowerCase());
    });
  }

  function filterKategoriList(keyword) {
    let totalVisible = 0;
    const groups = menu.querySelectorAll('.kategori-group');
    groups.forEach(grp => {
      let groupVisible = 0;
      const items = grp.querySelectorAll(itemClass);
      items.forEach(it => {
        const text = it.getAttribute('data-text') || '';
        if (keyword === '' || text.includes(keyword)) {
          it.style.display = 'block';
          groupVisible++;
          totalVisible++;
        } else {
          it.style.display = 'none';
        }
      });
      grp.style.display = (groupVisible > 0) ? 'block' : 'none';
    });

    if (emptyNotice) {
      emptyNotice.style.display = (totalVisible === 0) ? 'block' : 'none';
    }
  }

  const allItems = menu.querySelectorAll(itemClass);
  allItems.forEach(it => {
    it.addEventListener('click', function(e) {
      e.stopPropagation();
      const val = this.getAttribute('data-val');
      const arus = this.getAttribute('data-arus');

      if (hiddenInput) hiddenInput.value = val;
      if (triggerText) {
        triggerText.textContent = val;
        triggerText.style.color = 'var(--secondary)';
        triggerText.style.fontWeight = '700';
      }
      menu.style.display = 'none';

      // Auto check radio jika ada ketentuan arus kas
      if (isTambah) {
        if (arus === 'masuk') {
          const radio = document.querySelector('#formTambahKas input[name="jenis_transaksi"][value="masuk"]');
          if (radio) { radio.checked = true; toggleArusTransaksi('masuk', 'tambah'); }
        } else if (arus === 'keluar') {
          const radio = document.querySelector('#formTambahKas input[name="jenis_transaksi"][value="keluar"]');
          if (radio) { radio.checked = true; toggleArusTransaksi('keluar', 'tambah'); }
        }
      } else {
        if (arus === 'masuk') {
          const radio = document.getElementById('edit_arus_masuk');
          if (radio) radio.checked = true;
        } else if (arus === 'keluar') {
          const radio = document.getElementById('edit_arus_keluar');
          if (radio) radio.checked = true;
        }
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', function() {
  setupSearchableDropdown('tambah');
  setupSearchableDropdown('edit');

  // Tutup dropdown jika klik di luar
  document.addEventListener('click', function(e) {
    document.querySelectorAll('.custom-search-dropdown-menu').forEach(m => {
      m.style.display = 'none';
    });
  });

  // Validasi form tambah agar kategori wajib dipilih
  const formTambah = document.getElementById('formTambahKas');
  if (formTambah) {
    formTambah.addEventListener('submit', function(e) {
      const kat = document.getElementById('hidden_kategori_tambah').value.trim();
      if (!kat) {
        e.preventDefault();
        alert('Silakan cari dan pilih kategori rekening terlebih dahulu.');
        document.getElementById('kategoriTriggerTambah').click();
      }
    });
  }

  // Validasi form edit agar kategori wajib dipilih
  const formEdit = document.getElementById('formEditKas');
  if (formEdit) {
    formEdit.addEventListener('submit', function(e) {
      const kat = document.getElementById('hidden_kategori_edit').value.trim();
      if (!kat) {
        e.preventDefault();
        alert('Silakan cari dan pilih kategori rekening terlebih dahulu.');
        document.getElementById('kategoriTriggerEdit').click();
      }
    });
  }
});

function bukaModalEditKas(data) {
  document.getElementById('edit_id').value = data.id || '';
  
  // Format tanggal dd/mm/yyyy
  if (data.tanggal) {
    const parts = data.tanggal.split('-');
    if (parts.length === 3) {
      document.getElementById('edit_tanggal').value = parts[2] + '/' + parts[1] + '/' + parts[0];
      document.getElementById('edit_tanggal_native').value = data.tanggal;
    }
  }

  document.getElementById('edit_no_bukti').value = data.no_bukti || '';
  
  // Set kategori pada searchable dropdown edit
  const kat = data.kategori || '';
  document.getElementById('hidden_kategori_edit').value = kat;
  const trigText = document.getElementById('kategoriTriggerTextEdit');
  if (trigText) {
    trigText.textContent = kat ? kat : '-- Cari & Pilih Kategori Rekening --';
    trigText.style.color = kat ? 'var(--secondary)' : 'var(--text-muted)';
    trigText.style.fontWeight = kat ? '700' : 'normal';
  }

  document.getElementById('edit_keterangan').value = data.keterangan || '';

  const masuk = parseFloat(data.kas_masuk) || 0;
  const keluar = parseFloat(data.kas_keluar) || 0;
  const nominal = masuk > 0 ? masuk : keluar;
  
  document.getElementById('edit_nominal').value = 'Rp ' + Math.round(nominal).toLocaleString('id-ID');

  if (masuk > 0) {
    document.getElementById('edit_arus_masuk').checked = true;
  } else {
    document.getElementById('edit_arus_keluar').checked = true;
  }

  openModal('modalEditKas');
}
</script>

<?php include __DIR__ . '/footer.php'; ?>

