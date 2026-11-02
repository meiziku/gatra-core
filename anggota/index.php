<?php
/**
 * Dashboard Portal Anggota - Koperasi Gatra Mandiri
 */
$pageTitle = 'Dashboard Anggota';
$pageSubtitle = 'Informasi kartu anggota, saldo simpanan, dan sisa pinjaman Anda';
$activePage = 'dashboard';

require_once __DIR__ . '/header.php';

$anggotaId = $memberProfile['id'];

// 1. Hitung Rincian Simpanan Anggota
$simpananPokok = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE anggota_id = $anggotaId AND jenis_simpanan='pokok'")->fetchColumn() ?: 0;
$simpananWajib = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE anggota_id = $anggotaId AND jenis_simpanan='wajib'")->fetchColumn() ?: 0;
$simpananSukarela = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE anggota_id = $anggotaId AND jenis_simpanan='sukarela'")->fetchColumn() ?: 0;
$totalSimpanan = $simpananPokok + $simpananWajib + $simpananSukarela;

// 2. Ambil Pinjaman Berjalan Anggota
$stmtPinj = $db->prepare("
    SELECT p.*,
           COALESCE(SUM(a.jumlah_bayar), 0) as total_dibayar,
           COALESCE(SUM(a.angsuran_pokok), 0) as pokok_dibayar,
           COUNT(a.id) as angsuran_terbayar
    FROM pinjaman p
    LEFT JOIN angsuran a ON p.id = a.pinjaman_id
    WHERE p.anggota_id = ? AND p.status = 'berjalan'
    GROUP BY p.id
    LIMIT 1
");
$stmtPinj->execute([$anggotaId]);
$pinjamanAktif = $stmtPinj->fetch();

$sisaPinjaman = 0;
$angsuranBulanan = 0;
$progressPersen = 0;

if ($pinjamanAktif) {
    $sisaPinjaman = $pinjamanAktif['total_harus_dibayar'] - $pinjamanAktif['total_dibayar'];
    $angsuranBulanan = $pinjamanAktif['total_angsuran_bulanan'];
    if ($pinjamanAktif['total_harus_dibayar'] > 0) {
        $progressPersen = round(($pinjamanAktif['total_dibayar'] / $pinjamanAktif['total_harus_dibayar']) * 100);
    }
}

// 3. Mutasi Terkini Anggota (5 Transaksi Simpanan Terakhir)
$stmtMutasi = $db->prepare("
    SELECT * FROM simpanan 
    WHERE anggota_id = ? 
    ORDER BY tanggal DESC, id DESC LIMIT 5
");
$stmtMutasi->execute([$anggotaId]);
$mutasiTerbaru = $stmtMutasi->fetchAll();
?>

<!-- Top Overview: Kartu Anggota & Highlight Keuangan -->
<div style="display: grid; grid-template-columns: minmax(320px, 440px) 1fr; gap: 24px; margin-bottom: 28px; align-items: stretch;">
  
  <!-- Digital Membership Card -->
  <div class="member-card-gold" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
    <div>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div style="display:flex; align-items:center; gap:8px;">
          <img src="../assets/img/logo.png" alt="Logo" style="width:24px; height:24px; object-fit:contain; filter:brightness(0) invert(1);">
          <div style="font-weight:800; font-size:1.05rem; letter-spacing:0.03em;">GATRA TEKNIKA</div>
        </div>
        <span class="badge" style="background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.3); font-size:0.7rem;">KARTU ANGGOTA</span>
      </div>
      <div class="card-chip"></div>
      <div class="card-number"><?= htmlspecialchars($memberProfile['no_anggota']) ?></div>
    </div>

    <div class="card-holder">
      <div>
        <div style="font-size:0.7rem; opacity:0.8; text-transform:uppercase;">Nama Anggota</div>
        <div class="card-holder-name"><?= htmlspecialchars($memberProfile['nama']) ?></div>
      </div>
      <div style="text-align:right;">
        <div class="card-holder-since">Terdaftar Sejak</div>
        <div style="font-weight:700; font-size:0.85rem;"><?= date('m/Y', strtotime($memberProfile['tanggal_bergabung'])) ?></div>
      </div>
    </div>
  </div>

  <!-- Ringkasan Cepat 2 Kartu Besar -->
  <div style="display: flex; flex-direction: column; gap: 16px; justify-content: center;">
    
    <!-- Total Simpanan Card -->
    <div class="card" style="margin-bottom: 0; border-left: 5px solid var(--primary);">
      <div class="card-body" style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
          <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            Total Sisa Simpanan Anda
          </div>
          <div class="table-amount text-green" style="font-size: 1.85rem; margin-top: 4px;">
            <?= rupiah($totalSimpanan) ?>
          </div>
          <div style="font-size: 0.78rem; color: var(--text-subtle); margin-top: 4px;">
            Akumulasi Pokok, Wajib, dan Sukarela yang aman tersimpan.
          </div>
        </div>
        <a href="simpanan.php" class="btn btn-outline-primary btn-sm">
          Rincian &rarr;
        </a>
      </div>
    </div>

    <!-- Sisa Pinjaman Card -->
    <div class="card" style="margin-bottom: 0; border-left: 5px solid <?= $pinjamanAktif ? 'var(--danger)' : 'var(--success)' ?>;">
      <div class="card-body" style="padding: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
          <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
            Sisa Kewajiban Pinjaman
          </div>
          <?php if ($pinjamanAktif): ?>
            <div class="table-amount text-red" style="font-size: 1.85rem; margin-top: 4px;">
              <?= rupiah($sisaPinjaman) ?>
            </div>
            <div style="font-size: 0.78rem; color: var(--danger); margin-top: 4px;">
              Tagihan Angsuran: <strong><?= rupiah($angsuranBulanan) ?></strong> / bulan
            </div>
          <?php else: ?>
            <div class="table-amount text-green" style="font-size: 1.6rem; margin-top: 4px;">
              Bebas Pinjaman 🎉
            </div>
            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
              Anda tidak memiliki pinjaman aktif saat ini.
            </div>
          <?php endif; ?>
        </div>
        <a href="pinjaman.php" class="btn btn-secondary btn-sm">
          <?= $pinjamanAktif ? 'Jadwal Tagihan &rarr;' : 'Ajukan Pinjaman &rarr;' ?>
        </a>
      </div>
    </div>

  </div>
</div>

<!-- Rincian 3 Jenis Simpanan -->
<div class="stats-grid">
  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Pokok</div>
      <div class="stat-value text-green"><?= rupiah($simpananPokok) ?></div>
      <div class="stat-hint">Setoran awal keanggotaan</div>
    </div>
    <div class="stat-icon green">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Wajib</div>
      <div class="stat-value text-blue"><?= rupiah($simpananWajib) ?></div>
      <div class="stat-hint">Iuran bulanan anggota</div>
    </div>
    <div class="stat-icon blue">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Sukarela</div>
      <div class="stat-value text-green"><?= rupiah($simpananSukarela) ?></div>
      <div class="stat-hint">Tabungan dapat ditarik</div>
    </div>
    <div class="stat-icon amber">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>
</div>

<!-- Informasi Pinjaman Berjalan & Mutasi Terkini -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
  
  <!-- Status Pinjaman Aktif -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <h3>Status Pinjaman Aktif</h3>
      <a href="pinjaman.php" class="btn btn-secondary btn-sm">Lihat Detail</a>
    </div>
    <div class="card-body">
      <?php if ($pinjamanAktif): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
          <div>
            <span style="font-size:0.75rem; color:var(--text-subtle);">Nomor Kontrak:</span>
            <div style="font-weight:700; color:var(--secondary);"><?= htmlspecialchars($pinjamanAktif['no_pinjaman']) ?></div>
          </div>
          <span class="badge badge-warning">Berjalan</span>
        </div>

        <div style="background-color:var(--bg-subtle); border-radius:var(--radius-sm); padding:16px; margin-bottom:16px;">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:0.85rem;">
            <div>
              <span style="color:var(--text-muted);">Plafon Pinjaman:</span>
              <div style="font-weight:700;"><?= rupiah($pinjamanAktif['jumlah_pinjaman']) ?></div>
            </div>
            <div>
              <span style="color:var(--text-muted);">Jangka Waktu:</span>
              <div style="font-weight:700;"><?= $pinjamanAktif['lama_bulan'] ?> Bulan</div>
            </div>
            <div>
              <span style="color:var(--text-muted);">Total Terbayar:</span>
              <div style="font-weight:700; color:var(--primary);"><?= rupiah($pinjamanAktif['total_dibayar']) ?></div>
            </div>
            <div>
              <span style="color:var(--text-muted);">Sisa Kewajiban:</span>
              <div style="font-weight:700; color:var(--danger);"><?= rupiah($sisaPinjaman) ?></div>
            </div>
          </div>
        </div>

        <div>
          <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:6px;">
            <span>Kemajuan Pembayaran Angsuran:</span>
            <strong><?= $pinjamanAktif['angsuran_terbayar'] ?> dari <?= $pinjamanAktif['lama_bulan'] ?> Bulan (<?= $progressPersen ?>%)</strong>
          </div>
          <div style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
            <div style="background:var(--primary); height:100%; width: <?= $progressPersen ?>%;"></div>
          </div>
        </div>
      <?php else: ?>
        <div style="text-align:center; padding:32px 16px; color:var(--text-muted);">
          <div style="font-size:2.5rem; margin-bottom:8px;">✅</div>
          <div style="font-weight:700; color:var(--secondary); margin-bottom:4px;">Tidak Ada Pinjaman Berjalan</div>
          <p style="font-size:0.85rem; margin-bottom:16px;">Anda tidak memiliki tanggungan pinjaman saat ini.</p>
          <a href="pengajuan.php" class="btn btn-outline-primary btn-sm">Simulasi Pinjaman Baru</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mutasi Simpanan Terkini -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <h3>Mutasi Simpanan Terakhir</h3>
      <a href="simpanan.php" class="btn btn-secondary btn-sm">Buku Simpanan</a>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Jenis</th>
            <th>Nominal</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($mutasiTerbaru)): ?>
            <tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-subtle);">Belum ada mutasi simpanan.</td></tr>
          <?php else: ?>
            <?php foreach ($mutasiTerbaru as $m): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($m['tanggal'])) ?></td>
                <td>
                  <span class="badge badge-info"><?= ucfirst($m['jenis_simpanan']) ?></span>
                </td>
                <td class="table-amount <?= $m['tipe_transaksi'] === 'setor' ? 'text-green' : 'text-red' ?>">
                  <?= $m['tipe_transaksi'] === 'setor' ? '+' : '-' ?> <?= rupiah($m['nominal']) ?>
                </td>
                <td><small style="color:var(--text-muted);"><?= htmlspecialchars($m['keterangan'] ?? '-') ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
