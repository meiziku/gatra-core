<?php
/**
 * Rincian Sisa Pinjaman & Jadwal Angsuran Anggota - Koperasi Gatra Mandiri
 */
$pageTitle = 'Sisa Pinjaman & Tagihan';
$pageSubtitle = 'Rincian pinjaman aktif, jadwal angsuran bulanan, dan sisa kewajiban Anda';
$activePage = 'pinjaman';

require_once __DIR__ . '/header.php';

$anggotaId = $memberProfile['id'];

// Ambil pinjaman aktif
$stmtPinj = $db->prepare("
    SELECT p.*,
           COALESCE(SUM(a.jumlah_bayar), 0) as total_dibayar,
           COALESCE(SUM(a.angsuran_pokok), 0) as pokok_dibayar,
           COALESCE(SUM(a.angsuran_bunga), 0) as bunga_dibayar,
           COUNT(a.id) as angsuran_terbayar
    FROM pinjaman p
    LEFT JOIN angsuran a ON p.id = a.pinjaman_id
    WHERE p.anggota_id = ? AND p.status = 'berjalan'
    GROUP BY p.id
    LIMIT 1
");
$stmtPinj->execute([$anggotaId]);
$pinjaman = $stmtPinj->fetch();

$listAngsuran = [];
if ($pinjaman) {
    // Ambil seluruh riwayat pembayaran angsuran pinjaman ini
    $stmtAngs = $db->prepare("
        SELECT * FROM angsuran 
        WHERE pinjaman_id = ? 
        ORDER BY angsuran_ke ASC
    ");
    $stmtAngs->execute([$pinjaman['id']]);
    $listAngsuran = $stmtAngs->fetchAll();

    $sisaTagihanTotal = $pinjaman['total_harus_dibayar'] - $pinjaman['total_dibayar'];
    $sisaPokok = $pinjaman['jumlah_pinjaman'] - $pinjaman['pokok_dibayar'];
    $sisaBulan = $pinjaman['lama_bulan'] - $pinjaman['angsuran_terbayar'];
}
?>

<?php if (!$pinjaman): ?>
  <!-- State Jika Tidak Ada Pinjaman Aktif -->
  <div class="card" style="text-align: center; padding: 48px 24px;">
    <div style="font-size: 3.5rem; margin-bottom: 12px;">🎉</div>
    <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--secondary); margin-bottom: 8px;">
      Anda Bebas Tanggungan Pinjaman
    </h2>
    <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 24px; font-size: 0.95rem;">
      Saat ini Anda tidak memiliki sisa kewajiban pinjaman yang berjalan di Gatra Teknika. 
      Jika membutuhkan tambahan modal usaha atau kebutuhan mendesak, Anda dapat melakukan simulasi pengajuan.
    </p>
    <a href="pengajuan.php" class="btn btn-primary" style="padding: 12px 24px;">
      <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
      Kalkulator Simulasi Pinjaman Baru
    </a>
  </div>

<?php else: ?>
  <!-- Print Header -->
  <div class="print-header">
    <h2 style="font-size: 1.4rem; font-weight:800;">KOPERASI SIMPAN PINJAM GATRA TEKNIKA</h2>
    <p style="font-size: 0.9rem;">RINCIAN KONTRAK & JADWAL ANGSURAN PINJAMAN</p>
    <div style="display: flex; justify-content: space-between; margin-top: 14px; font-size: 0.85rem; text-align: left;">
      <div>
        <div>No. Kontrak : <strong><?= htmlspecialchars($pinjaman['no_pinjaman']) ?></strong></div>
        <div>Nama Anggota : <strong><?= htmlspecialchars($memberProfile['nama']) ?></strong> (<?= htmlspecialchars($memberProfile['no_anggota']) ?>)</div>
      </div>
      <div>
        <div>Plafon : <strong><?= rupiah($pinjaman['jumlah_pinjaman']) ?></strong></div>
        <div>Sisa Tagihan : <strong class="text-red"><?= rupiah($sisaTagihanTotal) ?></strong></div>
      </div>
    </div>
  </div>

  <!-- Stat Cards Sisa Pinjaman -->
  <div class="stats-grid">
    <div class="stat-card" style="border-left: 4px solid var(--danger);">
      <div>
        <div class="stat-title" style="color:var(--danger); font-weight:700;">TOTAL SISA PINJAMAN</div>
        <div class="stat-value text-red"><?= rupiah(max(0, $sisaTagihanTotal)) ?></div>
        <div class="stat-hint">Sisa <?= $sisaBulan ?> bulan angsuran lagi</div>
      </div>
      <div class="stat-icon red">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
    </div>

    <div class="stat-card">
      <div>
        <div class="stat-title">Sisa Pokok Pinjaman</div>
        <div class="stat-value text-blue"><?= rupiah(max(0, $sisaPokok)) ?></div>
        <div class="stat-hint">Dari total plafon <?= rupiah($pinjaman['jumlah_pinjaman']) ?></div>
      </div>
      <div class="stat-icon blue">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      </div>
    </div>

    <div class="stat-card">
      <div>
        <div class="stat-title">Angsuran Per Bulan</div>
        <div class="stat-value text-green"><?= rupiah($pinjaman['total_angsuran_bulanan']) ?></div>
        <div class="stat-hint">Pokok: <?= rupiah($pinjaman['angsuran_pokok']) ?> + Bunga: <?= rupiah($pinjaman['angsuran_bunga']) ?></div>
      </div>
      <div class="stat-icon green">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      </div>
    </div>

    <div class="stat-card">
      <div>
        <div class="stat-title">Total Sudah Terbayar</div>
        <div class="stat-value text-green"><?= rupiah($pinjaman['total_dibayar']) ?></div>
        <div class="stat-hint">Terbayar <?= $pinjaman['angsuran_terbayar'] ?> dari <?= $pinjaman['lama_bulan'] ?> kali</div>
      </div>
      <div class="stat-icon green">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
    </div>
  </div>

  <!-- Detail Informasi Kontrak & Jadwal Angsuran -->
  <div class="card">
    <div class="card-header no-print">
      <div>
        <h3>Rincian Kartu Angsuran Pinjaman (<?= htmlspecialchars($pinjaman['no_pinjaman']) ?>)</h3>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
          Tgl Pencairan: <?= date('d/m/Y', strtotime($pinjaman['tanggal_pencairan'] ?? $pinjaman['tanggal_pengajuan'])) ?> &bull; Keperluan: <?= htmlspecialchars($pinjaman['keterangan'] ?? '-') ?>
        </p>
      </div>
      <div class="card-header-actions">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Cetak Kartu Angsuran
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Bulan Ke-</th>
            <th>Tagihan Bulanan</th>
            <th>Pokok</th>
            <th>Jasa Bunga (<?= $pinjaman['bunga_persen'] ?>%)</th>
            <th>Tgl Bayar</th>
            <th>Status</th>
            <th>Penerima</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          // Mapping angsuran yang sudah dibayar berdasarkan angsuran_ke
          $paidMap = [];
          foreach ($listAngsuran as $la) {
              $paidMap[$la['angsuran_ke']] = $la;
          }

          for ($k = 1; $k <= $pinjaman['lama_bulan']; $k++):
            $isPaid = isset($paidMap[$k]);
            $bayarData = $isPaid ? $paidMap[$k] : null;
          ?>
            <tr style="<?= $isPaid ? 'background-color: #fbfdfc;' : '' ?>">
              <td><strong>Angsuran ke-<?= $k ?></strong></td>
              <td class="table-amount"><?= rupiah($pinjaman['total_angsuran_bulanan']) ?></td>
              <td class="table-amount"><?= rupiah($pinjaman['angsuran_pokok']) ?></td>
              <td class="table-amount"><?= rupiah($pinjaman['angsuran_bunga']) ?></td>
              <td><?= $isPaid ? date('d/m/Y', strtotime($bayarData['tanggal_bayar'])) : '-' ?></td>
              <td>
                <?php if ($isPaid): ?>
                  <span class="badge badge-success">✓ Lunas</span>
                <?php elseif ($k === ($pinjaman['angsuran_terbayar'] + 1)): ?>
                  <span class="badge badge-warning">⏳ Tagihan Aktif</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Belum Jatuh Tempo</span>
                <?php endif; ?>
              </td>
              <td><small><?= $isPaid ? htmlspecialchars($bayarData['petugas'] ?? 'Kasir') : '-' ?></small></td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
