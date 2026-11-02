<?php
/**
 * Rincian Sisa Simpanan & Buku Tabungan Anggota - Koperasi Gatra Mandiri
 */
$pageTitle = 'Sisa & Mutasi Simpanan';
$pageSubtitle = 'Rincian saldo simpanan pokok, wajib, sukarela dan buku mutasi Anda';
$activePage = 'simpanan';

require_once __DIR__ . '/header.php';

$anggotaId = $memberProfile['id'];

// 1. Hitung Saldo per Jenis
$simpananPokok = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE anggota_id = $anggotaId AND jenis_simpanan='pokok'")->fetchColumn() ?: 0;
$simpananWajib = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE anggota_id = $anggotaId AND jenis_simpanan='wajib'")->fetchColumn() ?: 0;
$simpananSukarela = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE anggota_id = $anggotaId AND jenis_simpanan='sukarela'")->fetchColumn() ?: 0;
$totalSimpanan = $simpananPokok + $simpananWajib + $simpananSukarela;

// Filter Jenis jika ada
$filterJenis = $_GET['jenis'] ?? '';
$sqlFilter = "";
$params = [$anggotaId];
if (!empty($filterJenis)) {
    $sqlFilter = " AND jenis_simpanan = ?";
    $params[] = $filterJenis;
}

$stmtMutasi = $db->prepare("
    SELECT * FROM simpanan 
    WHERE anggota_id = ? $sqlFilter
    ORDER BY tanggal ASC, id ASC
");
$stmtMutasi->execute($params);
$riwayatSimpanan = $stmtMutasi->fetchAll();
?>

<!-- Print-Only Header -->
<div class="print-header">
  <h2 style="font-size: 1.4rem; font-weight:800;">KOPERASI SIMPAN PINJAM GATRA TEKNIKA</h2>
  <p style="font-size: 0.9rem;">REKENING KORAN / BUKU MUTASI SIMPANAN ANGGOTA</p>
  <div style="display: flex; justify-content: space-between; margin-top: 14px; font-size: 0.85rem; text-align: left;">
    <div>
      <div>No. Anggota : <strong><?= htmlspecialchars($memberProfile['no_anggota']) ?></strong></div>
      <div>Nama Anggota : <strong><?= htmlspecialchars($memberProfile['nama']) ?></strong></div>
    </div>
    <div>
      <div>Tanggal Cetak : <?= tgl_indo(date('Y-m-d'), true) ?></div>
      <div>Total Saldo : <strong><?= rupiah($totalSimpanan) ?></strong></div>
    </div>
  </div>
</div>

<!-- Kartu Ringkasan Sisa Saldo -->
<div class="stats-grid">
  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Pokok</div>
      <div class="stat-value text-green"><?= rupiah($simpananPokok) ?></div>
      <div class="stat-hint">Tetap selama menjadi anggota</div>
    </div>
    <div class="stat-icon green">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Wajib</div>
      <div class="stat-value text-blue"><?= rupiah($simpananWajib) ?></div>
      <div class="stat-hint">Iuran akumulasi setiap bulan</div>
    </div>
    <div class="stat-icon blue">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Sukarela</div>
      <div class="stat-value text-green"><?= rupiah($simpananSukarela) ?></div>
      <div class="stat-hint">Bisa disetor & ditarik sewaktu-waktu</div>
    </div>
    <div class="stat-icon amber">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
    </div>
  </div>

  <div class="stat-card" style="border: 2px solid var(--primary);">
    <div>
      <div class="stat-title" style="color:var(--primary); font-weight:800;">TOTAL SISA SALDO</div>
      <div class="stat-value text-green"><?= rupiah($totalSimpanan) ?></div>
      <div class="stat-hint">Dana simpanan aktif Anda</div>
    </div>
    <div class="stat-icon green">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>
</div>

<!-- Tabel Mutasi Simpanan -->
<div class="card">
  <div class="card-header no-print">
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
      <h3>Buku Mutasi Transaksi Simpanan</h3>
      <div style="display: flex; gap: 6px;">
        <a href="simpanan.php" class="btn <?= empty($filterJenis) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Semua</a>
        <a href="simpanan.php?jenis=pokok" class="btn <?= $filterJenis === 'pokok' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Pokok</a>
        <a href="simpanan.php?jenis=wajib" class="btn <?= $filterJenis === 'wajib' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Wajib</a>
        <a href="simpanan.php?jenis=sukarela" class="btn <?= $filterJenis === 'sukarela' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Sukarela</a>
      </div>
    </div>

    <div class="card-header-actions">
      <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Mutasi
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Jenis Simpanan</th>
          <th>Transaksi</th>
          <th>Debet (Tarik)</th>
          <th>Kredit (Setor)</th>
          <th>Saldo Berjalan</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($riwayatSimpanan)): ?>
          <tr><td colspan="7" style="text-align:center; padding:32px; color:var(--text-subtle);">Belum ada riwayat transaksi simpanan pada kategori ini.</td></tr>
        <?php else: ?>
          <?php 
          $runningSaldo = 0;
          foreach ($riwayatSimpanan as $rs): 
            $isSetor = ($rs['tipe_transaksi'] === 'setor');
            if ($isSetor) {
              $runningSaldo += $rs['nominal'];
            } else {
              $runningSaldo -= $rs['nominal'];
            }
          ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($rs['tanggal'])) ?></td>
              <td><span class="badge badge-info">Simpanan <?= ucfirst($rs['jenis_simpanan']) ?></span></td>
              <td>
                <span class="badge <?= $isSetor ? 'badge-success' : 'badge-danger' ?>">
                  <?= ucfirst($rs['tipe_transaksi']) ?>
                </span>
              </td>
              <td class="table-amount text-red"><?= !$isSetor ? rupiah($rs['nominal']) : '-' ?></td>
              <td class="table-amount text-green"><?= $isSetor ? rupiah($rs['nominal']) : '-' ?></td>
              <td class="table-amount" style="font-weight:800;"><?= rupiah($runningSaldo) ?></td>
              <td><small style="color:var(--text-muted);"><?= htmlspecialchars($rs['keterangan'] ?? '-') ?></small></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
