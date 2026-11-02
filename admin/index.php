<?php
/**
 * Dashboard Admin - Koperasi Gatra Mandiri
 */
$pageTitle = 'Dashboard Ringkasan';
$pageSubtitle = 'Ringkasan operasional dan status keuangan koperasi';
$activePage = 'dashboard';

require_once __DIR__ . '/header.php';

// 1. Hitung Total Anggota
$totalAnggota = $db->query("SELECT COUNT(*) FROM anggota WHERE status = 'aktif'")->fetchColumn() ?: 0;

// 2. Hitung Total Simpanan
$setorSimpanan = $db->query("SELECT SUM(nominal) FROM simpanan WHERE tipe_transaksi = 'setor'")->fetchColumn() ?: 0;
$tarikSimpanan = $db->query("SELECT SUM(nominal) FROM simpanan WHERE tipe_transaksi = 'tarik'")->fetchColumn() ?: 0;
$totalSimpanan = $setorSimpanan - $tarikSimpanan;

// 3. Hitung Pinjaman & Sisa Pinjaman
$totalPinjamanDiberikan = $db->query("SELECT SUM(jumlah_pinjaman) FROM pinjaman WHERE status IN ('berjalan', 'lunas')")->fetchColumn() ?: 0;
$totalKewajibanPinjaman = $db->query("SELECT SUM(total_harus_dibayar) FROM pinjaman WHERE status = 'berjalan'")->fetchColumn() ?: 0;
$totalAngsuranMasuk = $db->query("SELECT SUM(jumlah_bayar) FROM angsuran")->fetchColumn() ?: 0;

// Hitung sisa pinjaman berjalan
$stmtSisa = $db->query("
    SELECT 
        p.id,
        p.total_harus_dibayar,
        COALESCE(SUM(a.jumlah_bayar), 0) as terbayar
    FROM pinjaman p
    LEFT JOIN angsuran a ON p.id = a.pinjaman_id
    WHERE p.status = 'berjalan'
    GROUP BY p.id
");
$sisaPinjamanBerjalan = 0;
while ($row = $stmtSisa->fetch()) {
    $sisa = $row['total_harus_dibayar'] - $row['terbayar'];
    if ($sisa > 0) $sisaPinjamanBerjalan += $sisa;
}

// 4. Transaksi Simpanan Terbaru (5 data)
$recentSimpanan = $db->query("
    SELECT s.*, a.nama as nama_anggota, a.no_anggota 
    FROM simpanan s
    JOIN anggota a ON s.anggota_id = a.id
    ORDER BY s.id DESC LIMIT 5
")->fetchAll();

// 5. Pinjaman Berjalan Terbaru (5 data)
$recentPinjaman = $db->query("
    SELECT p.*, a.nama as nama_anggota, a.no_anggota,
           COALESCE(SUM(ag.jumlah_bayar), 0) as total_dibayar,
           COUNT(ag.id) as angsuran_terbayar
    FROM pinjaman p
    JOIN anggota a ON p.anggota_id = a.id
    LEFT JOIN angsuran ag ON p.id = ag.pinjaman_id
    WHERE p.status = 'berjalan'
    GROUP BY p.id
    ORDER BY p.id DESC LIMIT 5
")->fetchAll();
?>

<!-- Quick Actions Banner -->
<div class="card" style="background: linear-gradient(135deg, #064e3b, #047857); color: #ffffff; border: none; margin-bottom: 24px;">
  <div class="card-body" style="padding: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
    <div>
      <h3 style="color:#ffffff; font-size: 1.25rem; font-weight: 800; margin-bottom: 4px;">Selamat Bertugas, <?= htmlspecialchars($currentUser['nama_lengkap']) ?>!</h3>
      <p style="color: #a7f3d0; font-size: 0.85rem;">Input data simpanan, pinjaman, dan laporan keuangan koperasi secara akurat dan real-time.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
      <a href="simpanan.php?action=tambah" class="btn" style="background: #ffffff; color: #064e3b; font-weight: 700;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Input Simpanan
      </a>
      <a href="pinjaman.php?action=tambah" class="btn" style="background: #10b981; color: #ffffff; font-weight: 700; border: none;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Input Pinjaman
      </a>
      <a href="angsuran.php" class="btn" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3);">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        Catat Angsuran
      </a>
    </div>
  </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <!-- Total Anggota -->
  <div class="stat-card">
    <div>
      <div class="stat-title">Anggota Aktif</div>
      <div class="stat-value"><?= number_format($totalAnggota) ?> <span style="font-size:0.9rem; font-weight:600; color:var(--text-muted);">Orang</span></div>
      <div class="stat-hint"><a href="anggota.php" style="color:var(--primary); font-weight:600;">Lihat Semua &rarr;</a></div>
    </div>
    <div class="stat-icon green">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>
  </div>

  <!-- Total Simpanan -->
  <div class="stat-card">
    <div>
      <div class="stat-title">Total Simpanan Anggota</div>
      <div class="stat-value text-green"><?= rupiah($totalSimpanan) ?></div>
      <div class="stat-hint">Pokok, Wajib & Sukarela</div>
    </div>
    <div class="stat-icon blue">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>

  <!-- Total Pinjaman Disalurkan -->
  <div class="stat-card">
    <div>
      <div class="stat-title">Pinjaman Disalurkan</div>
      <div class="stat-value text-blue"><?= rupiah($totalPinjamanDiberikan) ?></div>
      <div class="stat-hint">Total pembiayaan modal</div>
    </div>
    <div class="stat-icon amber">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
    </div>
  </div>

  <!-- Sisa Tagihan Pinjaman -->
  <div class="stat-card">
    <div>
      <div class="stat-title">Sisa Pinjaman Belum Lunas</div>
      <div class="stat-value text-red"><?= rupiah($sisaPinjamanBerjalan) ?></div>
      <div class="stat-hint">Dari seluruh pinjaman aktif</div>
    </div>
    <div class="stat-icon red">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
  <!-- Pinjaman Berjalan & Sisa Tagihan -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <h3>Pinjaman Berjalan Anggota</h3>
      <a href="pinjaman.php" class="btn btn-secondary btn-sm">Kelola Semua</a>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>No. Pinjaman</th>
            <th>Anggota</th>
            <th>Pinjaman</th>
            <th>Sisa Tagihan</th>
            <th>Progress</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentPinjaman)): ?>
            <tr><td colspan="5" style="text-align:center; color:var(--text-subtle); padding:24px;">Belum ada data pinjaman berjalan.</td></tr>
          <?php else: ?>
            <?php foreach ($recentPinjaman as $p): 
              $sisa = $p['total_harus_dibayar'] - $p['total_dibayar'];
              $percent = ($p['total_harus_dibayar'] > 0) ? round(($p['total_dibayar'] / $p['total_harus_dibayar']) * 100) : 0;
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($p['no_pinjaman']) ?></strong></td>
                <td>
                  <div><?= htmlspecialchars($p['nama_anggota']) ?></div>
                  <small style="color:var(--text-subtle)"><?= htmlspecialchars($p['no_anggota']) ?></small>
                </td>
                <td class="table-amount"><?= rupiah($p['jumlah_pinjaman']) ?></td>
                <td class="table-amount text-red"><?= rupiah($sisa) ?></td>
                <td>
                  <div style="display:flex; align-items:center; gap:8px;">
                    <div style="flex:1; background:#e2e8f0; height:6px; border-radius:3px; overflow:hidden;">
                      <div style="background:var(--primary); height:100%; width: <?= $percent ?>%;"></div>
                    </div>
                    <span style="font-size:0.75rem; font-weight:700;"><?= $p['angsuran_terbayar'] ?>/<?= $p['lama_bulan'] ?> bln</span>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Simpanan Terbaru -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <h3>Mutasi Simpanan Terkini</h3>
      <a href="simpanan.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Anggota</th>
            <th>Jenis</th>
            <th>Nominal</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentSimpanan)): ?>
            <tr><td colspan="5" style="text-align:center; color:var(--text-subtle); padding:24px;">Belum ada data transaksi simpanan.</td></tr>
          <?php else: ?>
            <?php foreach ($recentSimpanan as $s): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($s['tanggal'])) ?></td>
                <td>
                  <div><?= htmlspecialchars($s['nama_anggota']) ?></div>
                  <small style="color:var(--text-subtle)"><?= htmlspecialchars($s['no_anggota']) ?></small>
                </td>
                <td>
                  <span class="badge badge-info"><?= ucfirst($s['jenis_simpanan']) ?></span>
                </td>
                <td class="table-amount <?= $s['tipe_transaksi'] === 'setor' ? 'text-green' : 'text-red' ?>">
                  <?= ($s['tipe_transaksi'] === 'setor' ? '+' : '-') ?> <?= rupiah($s['nominal']) ?>
                </td>
                <td>
                  <span class="badge <?= $s['tipe_transaksi'] === 'setor' ? 'badge-success' : 'badge-danger' ?>">
                    <?= ucfirst($s['tipe_transaksi']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
