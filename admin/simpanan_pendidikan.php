<?php
require_once '../config/database.php';
require_once '../config/helper.php';

require_admin();

$pageTitle = 'Simpanan Pendidikan';
$pageSubtitle = 'Pengelolaan kepesertaan dan setoran simpanan berjangka pendidikan anggota';
$activePage = 'simpanan_pendidikan';

$matriks = get_matriks_simpanan_pendidikan();
$kolomTenor = [
    ['tahun' => 6, 'bulan' => 72],
    ['tahun' => 5, 'bulan' => 60],
    ['tahun' => 4, 'bulan' => 48],
    ['tahun' => 3, 'bulan' => 36],
    ['tahun' => 2, 'bulan' => 24],
    ['tahun' => 1, 'bulan' => 12]
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Pendaftaran Peserta Baru
    if ($action === 'daftar_peserta') {
        $anggotaId = (int)($_POST['anggota_id'] ?? 0);
        $targetDana = (float)($_POST['target_dana'] ?? 0);
        $tenorBulan = (int)($_POST['tenor_bulan'] ?? 0);
        $tglMulai = parse_dmy_to_ymd($_POST['tanggal_mulai'] ?? date('d/m/Y'));
        $keterangan = trim($_POST['keterangan'] ?? '');

        if (!$anggotaId || !$targetDana || !$tenorBulan) {
            set_flash('error', 'Harap lengkapi semua kolom wajib pendaftaran simpanan pendidikan.');
            redirect('simpanan_pendidikan.php');
        }

        // Hitung setoran bulanan resmi berdasarkan matriks
        $setoranBulanan = get_setoran_simpanan_pendidikan($targetDana, $tenorBulan);
        if ($setoranBulanan <= 0) {
            set_flash('error', 'Kombinasi target dana dan jangka waktu tidak valid.');
            redirect('simpanan_pendidikan.php');
        }

        // Hitung tanggal jatuh tempo
        $tglJatuhTempo = date('Y-m-d', strtotime("+$tenorBulan month", strtotime($tglMulai)));

        // Generate No. Rekening Unik: SP-PEND-YYYY-XXXX
        $year = date('Y', strtotime($tglMulai));
        $prefix = "SP-PEND-{$year}-";
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM simpanan_pendidikan WHERE no_rekening LIKE ?");
        $stmtCount->execute([$prefix . '%']);
        $nextNum = $stmtCount->fetchColumn() + 1;
        $noRekening = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        try {
            $stmtIns = $db->prepare("INSERT INTO simpanan_pendidikan 
                (no_rekening, anggota_id, target_dana, tenor_bulan, setoran_bulanan, tanggal_mulai, tanggal_jatuh_tempo, status, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif', ?)");
            $stmtIns->execute([
                $noRekening,
                $anggotaId,
                $targetDana,
                $tenorBulan,
                $setoranBulanan,
                $tglMulai,
                $tglJatuhTempo,
                $keterangan
            ]);

            set_flash('success', "Pendaftaran Simpanan Pendidikan berhasil dibuat dengan No. Rekening {$noRekening} (Target: " . rupiah($targetDana) . ", Setoran: " . rupiah($setoranBulanan) . "/bln).");
        } catch (Exception $e) {
            set_flash('error', 'Gagal mendaftarkan simpanan pendidikan: ' . $e->getMessage());
        }

        redirect('simpanan_pendidikan.php');
    }

    // 2. Input Setoran Bulanan
    if ($action === 'bayar_setoran') {
        $spId = (int)($_POST['simpanan_pendidikan_id'] ?? 0);
        $nominalRaw = preg_replace('/[^0-9]/', '', $_POST['nominal'] ?? '0');
        $nominal = (float)$nominalRaw;
        $tglBayar = parse_dmy_to_ymd($_POST['tanggal_bayar'] ?? date('d/m/Y'));
        $keterangan = trim($_POST['keterangan'] ?? 'Langsung');
        $catatan = trim($_POST['catatan'] ?? '');
        $petugas = current_user()['nama_lengkap'] ?? 'Admin';

        if (!$spId || $nominal <= 0) {
            set_flash('error', 'Pilih rekening simpanan pendidikan dan masukkan nominal setoran yang valid.');
            redirect('simpanan_pendidikan.php');
        }

        // Ambil data rekening SP
        $stmtSp = $db->prepare("SELECT * FROM simpanan_pendidikan WHERE id = ?");
        $stmtSp->execute([$spId]);
        $sp = $stmtSp->fetch(PDO::FETCH_ASSOC);

        if (!$sp) {
            set_flash('error', 'Data rekening simpanan pendidikan tidak ditemukan.');
            redirect('simpanan_pendidikan.php');
        }

        // Hitung setoran ke-berapa
        $stmtCekSetor = $db->prepare("SELECT COUNT(*) FROM simpanan_pendidikan_setoran WHERE simpanan_pendidikan_id = ?");
        $stmtCekSetor->execute([$spId]);
        $setoranKe = $stmtCekSetor->fetchColumn() + 1;

        try {
            $stmtInsSetor = $db->prepare("INSERT INTO simpanan_pendidikan_setoran 
                (simpanan_pendidikan_id, setoran_ke, nominal, tanggal_bayar, keterangan, petugas, catatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtInsSetor->execute([
                $spId,
                $setoranKe,
                $nominal,
                $tglBayar,
                $keterangan,
                $petugas,
                $catatan
            ]);

            // Cek apakah sudah lunas mencapai tenor
            if ($setoranKe >= $sp['tenor_bulan']) {
                $db->prepare("UPDATE simpanan_pendidikan SET status = 'selesai' WHERE id = ?")->execute([$spId]);
                set_flash('success', "Setoran ke-{$setoranKe} berhasil dicatat! Program simpanan pendidikan untuk rekening {$sp['no_rekening']} telah mencapai target tenor (Selesai).");
            } else {
                set_flash('success', "Setoran ke-{$setoranKe} sebesar " . rupiah($nominal) . " untuk rekening {$sp['no_rekening']} berhasil dicatat.");
            }
        } catch (Exception $e) {
            set_flash('error', 'Gagal mencatat setoran: ' . $e->getMessage());
        }

        redirect('simpanan_pendidikan.php');
    }

    // 3. Ubah Status Rekening
    if ($action === 'ubah_status') {
        $spId = (int)($_POST['id'] ?? 0);
        $statusBaru = $_POST['status'] ?? 'aktif';

        if (in_array($statusBaru, ['aktif', 'selesai', 'batal'])) {
            $stmtUp = $db->prepare("UPDATE simpanan_pendidikan SET status = ? WHERE id = ?");
            $stmtUp->execute([$statusBaru, $spId]);
            set_flash('success', "Status simpanan pendidikan berhasil diperbarui menjadi: " . ucfirst($statusBaru));
        }
        redirect('simpanan_pendidikan.php');
    }

    // 4. Hapus Transaksi Setoran
    if ($action === 'hapus_setoran') {
        $setoranId = (int)($_POST['setoran_id'] ?? 0);
        try {
            $stmtDel = $db->prepare("DELETE FROM simpanan_pendidikan_setoran WHERE id = ?");
            $stmtDel->execute([$setoranId]);
            set_flash('success', 'Transaksi setoran simpanan pendidikan berhasil dihapus.');
        } catch (Exception $e) {
            set_flash('error', 'Gagal menghapus setoran: ' . $e->getMessage());
        }
        redirect('simpanan_pendidikan.php');
    }
}

// Ambil daftar anggota aktif untuk dropdown pendaftaran
$stmtAnggota = $db->query("SELECT id, no_anggota, nama, pekerjaan, no_telp FROM anggota WHERE status = 'aktif' ORDER BY nama ASC");
$listAnggota = $stmtAnggota->fetchAll(PDO::FETCH_ASSOC);

// Ambil data statistik ringkasan
$totalPesertaAktif = $db->query("SELECT COUNT(*) FROM simpanan_pendidikan WHERE status = 'aktif'")->fetchColumn();
$totalTargetDana = $db->query("SELECT COALESCE(SUM(target_dana), 0) FROM simpanan_pendidikan WHERE status = 'aktif'")->fetchColumn();
$totalSimpananTerkumpul = $db->query("SELECT COALESCE(SUM(nominal), 0) FROM simpanan_pendidikan_setoran")->fetchColumn();

$blnIni = date('Y-m');
$stmtSetorBlnIni = $db->prepare("SELECT COALESCE(SUM(nominal), 0) FROM simpanan_pendidikan_setoran WHERE tanggal_bayar LIKE ?");
$stmtSetorBlnIni->execute([$blnIni . '%']);
$setoranBulanIni = $stmtSetorBlnIni->fetchColumn();

// Ambil daftar rekening simpanan pendidikan beserta agregat setorannya
$sqlSp = "SELECT sp.*, a.nama as nama_anggota, a.no_anggota, a.no_telp,
                 COUNT(sps.id) as total_setoran_kali,
                 COALESCE(SUM(sps.nominal), 0) as total_setoran_nominal
          FROM simpanan_pendidikan sp
          JOIN anggota a ON sp.anggota_id = a.id
          LEFT JOIN simpanan_pendidikan_setoran sps ON sp.id = sps.simpanan_pendidikan_id
          GROUP BY sp.id
          ORDER BY sp.id DESC";
$listSp = $db->query($sqlSp)->fetchAll(PDO::FETCH_ASSOC);

// Ambil riwayat setoran masuk terbaru
$sqlSetoran = "SELECT sps.*, sp.no_rekening, sp.target_dana, sp.tenor_bulan, sp.setoran_bulanan,
                      a.nama as nama_anggota, a.no_anggota
               FROM simpanan_pendidikan_setoran sps
               JOIN simpanan_pendidikan sp ON sps.simpanan_pendidikan_id = sp.id
               JOIN anggota a ON sp.anggota_id = a.id
               ORDER BY sps.tanggal_bayar DESC, sps.id DESC
               LIMIT 50";
$listSetoran = $db->query($sqlSetoran)->fetchAll(PDO::FETCH_ASSOC);

// Preset target & tenor dari URL jika diarahkan dari tabel matriks
$presetTarget = (int)($_GET['daftar_target'] ?? 0);
$presetTenor = (int)($_GET['daftar_tenor'] ?? 0);
$autoOpenDaftar = ($presetTarget > 0 && $presetTenor > 0) || (isset($_GET['action']) && $_GET['action'] === 'baru');

include 'header.php';
?>

<!-- Header Actions -->
<div class="row" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--secondary); margin-bottom: 4px;">Simpanan Pendidikan</h2>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Khusus anggota terdaftar &bull; Setoran terencana dengan jaminan pencairan target dana</p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <!-- Tombol 1: Tabel Matriks & Simulasi (Modal Popup Cepat & Navigasi) -->
    <button type="button" class="btn btn-secondary" onclick="openModal('modalLihatMatriks')" style="border: 1px solid #bfdbfe; background: #eff6ff; color: #1e40af;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Tabel Matriks & Simulasi
    </button>

    <!-- Tombol 2: Input Setoran Bulanan -->
    <button type="button" class="btn btn-secondary" onclick="openModal('modalInputSetoran')" style="background: #ffffff;">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      + Input Setoran Bulanan
    </button>

    <!-- Tombol 3: Pendaftaran Baru -->
    <button type="button" class="btn btn-primary" onclick="openModal('modalDaftarPeserta')">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
      + Pendaftaran Baru
    </button>
  </div>
</div>

<!-- Stat Cards (1 Baris ke Samping) -->
<style>
.stats-grid-sp {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}
@media (max-width: 1024px) {
  .stats-grid-sp {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 576px) {
  .stats-grid-sp {
    grid-template-columns: 1fr;
  }
}
</style>
<div class="stats-grid stats-grid-sp">
  <div class="stat-card">
    <div>
      <div class="stat-title">Peserta Aktif</div>
      <div class="stat-value"><?= number_format($totalPesertaAktif, 0, ',', '.') ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted);">Anggota</span></div>
      <div class="stat-hint">Rekening berjangka aktif</div>
    </div>
    <div class="stat-icon blue">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Target Dana Dikelola</div>
      <div class="stat-value"><?= rupiah($totalTargetDana) ?></div>
      <div class="stat-hint">Komitmen pencairan masa depan</div>
    </div>
    <div class="stat-icon orange">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Dana Terkumpul</div>
      <div class="stat-value" style="color: var(--primary);"><?= rupiah($totalSimpananTerkumpul) ?></div>
      <div class="stat-hint">Total setoran masuk simpanan</div>
    </div>
    <div class="stat-icon green">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Setoran Bulan Ini</div>
      <div class="stat-value"><?= rupiah($setoranBulanIni) ?></div>
      <div class="stat-hint"><?= date('F Y') ?></div>
    </div>
    <div class="stat-icon purple">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
    </div>
  </div>
</div>

<!-- Main Table Card: Daftar Anggota Terdaftar -->
<div class="card" style="margin-bottom: 24px;">
  <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
    <div>
      <h3 style="margin: 0; font-weight: 800;">Daftar Rekening Simpanan Pendidikan</h3>
      <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
        Hanya untuk anggota terdaftar. Setiap rekening memiliki target dana dan setoran bulanan tetap.
      </p>
    </div>
    <div class="card-header-actions" style="display: flex; gap: 8px;">
      <input type="text" class="form-control" style="width: 250px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari nama, no rekening, no anggota..." data-table-search="tableSimpananPendidikan">
    </div>
  </div>

  <div class="table-freeze-container">
    <table class="table" id="tableSimpananPendidikan">
      <thead>
        <tr>
          <th>No. Rekening</th>
          <th>Anggota</th>
          <th style="text-align: right;">Target Dana (Rp)</th>
          <th>Tenor</th>
          <th style="text-align: right;">Setoran / Bln (Rp)</th>
          <th style="min-width: 180px;">Progres Tabungan (Rp)</th>
          <th>Jatuh Tempo</th>
          <th>Status</th>
          <th style="text-align: right; width: 140px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listSp)): ?>
          <tr>
            <td colspan="9" style="text-align:center; padding: 36px; color: var(--text-subtle);">
              Belum ada anggota yang mendaftar simpanan pendidikan.<br>
              <button type="button" class="btn btn-primary" onclick="openModal('modalDaftarPeserta')" style="margin-top: 10px;">
                + Daftarkan Anggota Sekarang
              </button>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($listSp as $item): 
            $progressPersen = $item['tenor_bulan'] > 0 ? min(100, round(($item['total_setoran_kali'] / $item['tenor_bulan']) * 100)) : 0;
            $statusBadgeClass = [
                'aktif' => 'badge-success',
                'selesai' => 'badge-info',
                'batal' => 'badge-danger'
            ][$item['status']] ?? 'badge-secondary';
          ?>
            <tr>
              <td>
                <strong style="color: var(--secondary);"><?= htmlspecialchars($item['no_rekening']) ?></strong>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= tgl_dmy($item['tanggal_mulai']) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($item['nama_anggota']) ?></strong>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($item['no_anggota']) ?> &bull; <?= htmlspecialchars($item['no_telp'] ?? '-') ?></div>
              </td>
              <td class="table-amount" style="font-weight: 800; color: #047857;">
                <?= nominal($item['target_dana']) ?>
              </td>
              <td>
                <span class="badge badge-info" style="font-weight: 700;">
                  <?= $item['tenor_bulan'] ?> Bulan (<?= round($item['tenor_bulan'] / 12) ?> Thn)
                </span>
              </td>
              <td class="table-amount" style="font-weight: 700;">
                <?= nominal($item['setoran_bulanan']) ?>
              </td>
              <td>
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 3px;">
                  <span><strong><?= $item['total_setoran_kali'] ?></strong> / <?= $item['tenor_bulan'] ?> bln</span>
                  <span style="font-weight: 700; color: var(--primary);"><?= nominal($item['total_setoran_nominal']) ?></span>
                </div>
                <div style="background: #e2e8f0; height: 7px; border-radius: 4px; overflow: hidden;">
                  <div style="background: <?= $progressPersen >= 100 ? '#10b981' : '#3b82f6' ?>; width: <?= $progressPersen ?>%; height: 100%;"></div>
                </div>
              </td>
              <td style="font-size: 0.85rem;">
                <?= tgl_dmy($item['tanggal_jatuh_tempo']) ?>
              </td>
              <td>
                <span class="badge <?= $statusBadgeClass ?>" style="text-transform: capitalize;">
                  <?= htmlspecialchars($item['status']) ?>
                </span>
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 6px; align-items: center;">
                  <!-- Tombol 1: Input Bayar Setoran Bulanan (Icon Plus) -->
                  <?php if ($item['status'] === 'aktif'): ?>
                    <button type="button" class="btn btn-secondary btn-icon" style="color: #047857; background: #ecfdf5; border-color: #a7f3d0;" 
                            onclick="bukaModalBayarLangsung(<?= $item['id'] ?>)" title="Bayar Setoran Bulanan">
                      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                  <?php endif; ?>

                  <!-- Tombol 2: Lihat Kartu Riwayat Simpanan (Icon Mata) -->
                  <button type="button" class="btn btn-secondary btn-icon" style="color: #1e40af; background: #eff6ff; border-color: #bfdbfe;" 
                          data-sp='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>'
                          onclick="lihatDetailSp(this)" title="Lihat Kartu Riwayat Simpanan">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </button>

                  <!-- Tombol 3: Ubah Status Rekening (Icon Gear) -->
                  <button type="button" class="btn btn-secondary btn-icon" style="color: #475569; background: #f1f5f9; border-color: #cbd5e1;" 
                          data-sp='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>'
                          onclick="bukaModalUbahStatus(this)" title="Ubah Status">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Riwayat Setoran Simpanan Pendidikan Masuk -->
<div class="card">
  <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
    <div>
      <h3 style="margin: 0; font-weight: 800;">Riwayat Setoran Simpanan Pendidikan</h3>
      <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">
        Catatan transaksi pembayaran bulanan simpanan pendidikan anggota masuk ke kas
      </p>
    </div>
    <div class="card-header-actions">
      <input type="text" class="form-control" style="width: 240px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari bukti / nama..." data-table-search="tableSetoranPendidikan">
    </div>
  </div>

  <div class="table-responsive">
    <table class="table" id="tableSetoranPendidikan">
      <thead>
        <tr>
          <th>Tanggal Bayar</th>
          <th>No. Rekening</th>
          <th>Nama Anggota</th>
          <th>Setoran Ke</th>
          <th style="text-align: right;">Nominal Setoran (Rp)</th>
          <th>Keterangan</th>
          <th>Petugas</th>
          <th>Catatan</th>
          <th style="text-align: right; width: 60px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listSetoran)): ?>
          <tr><td colspan="9" style="text-align:center; padding: 32px; color: var(--text-subtle);">Belum ada riwayat setoran simpanan pendidikan.</td></tr>
        <?php else: ?>
          <?php foreach ($listSetoran as $setor): ?>
            <tr>
              <td><?= tgl_dmy($setor['tanggal_bayar']) ?></td>
              <td><strong><?= htmlspecialchars($setor['no_rekening']) ?></strong></td>
              <td><?= htmlspecialchars($setor['nama_anggota']) ?></td>
              <td>
                <span class="badge badge-info">
                  Bulan ke-<?= $setor['setoran_ke'] ?> / <?= $setor['tenor_bulan'] ?>
                </span>
              </td>
              <td class="table-amount" style="font-weight: 800; color: #047857; text-align: right;">
                <?= nominal($setor['nominal']) ?>
              </td>
              <td>
                <?php 
                  $ket = trim($setor['keterangan'] ?? '');
                  if ($ket === 'Langsung'): ?>
                    <span class="badge badge-success" style="font-size:0.75rem; font-weight:700;">Langsung</span>
                <?php elseif ($ket === 'TF'): ?>
                    <span class="badge badge-info" style="font-size:0.75rem; font-weight:700;">TF</span>
                <?php elseif ($ket === 'Gaji'): ?>
                    <span class="badge badge-warning" style="font-size:0.75rem; font-weight:700;">Gaji</span>
                <?php elseif ($ket === 'TPP'): ?>
                    <span class="badge badge-purple" style="font-size:0.75rem; font-weight:700; background:#f3e8ff; color:#7e22ce;">TPP</span>
                <?php else: ?>
                    <span style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($ket ?: '-') ?></span>
                <?php endif; ?>
              </td>
              <td><small><?= htmlspecialchars($setor['petugas'] ?? 'Admin') ?></small></td>
              <td><small><?= htmlspecialchars($setor['catatan'] ?: '-') ?></small></td>
              <td style="text-align: right;">
                <button type="button" class="btn btn-danger btn-icon" 
                        onclick="konfirmasiHapusSetoran(<?= $setor['id'] ?>, '<?= htmlspecialchars($setor['no_rekening']) ?>', '<?= rupiah($setor['nominal']) ?>')" title="Hapus Setoran">
                  <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: PENDAFTARAN ANGGOTA SIMPANAN PENDIDIKAN BARU                       -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalDaftarPeserta">
  <div class="modal-card" style="max-width: 680px;">
    <div class="modal-header">
      <div>
        <h3 style="margin: 0;">Pendaftaran Simpanan Pendidikan Baru</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: var(--text-muted);">
          Khusus anggota terdaftar &bull; Setoran wajib bulanan terikat matriks resmi
        </p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalDaftarPeserta')">&times;</button>
    </div>

    <form action="simpanan_pendidikan.php" method="POST">
      <input type="hidden" name="action" value="daftar_peserta">

      <div class="modal-body">
        <!-- Searchable Anggota Selector -->
        <div class="form-group">
          <label class="form-label">Pilih Anggota *</label>
          <input type="hidden" name="anggota_id" id="hidden_sp_anggota_id" required>

          <div class="searchable-dropdown" id="spAnggotaDropdown" style="position: relative;">
            <div class="dropdown-trigger form-control" id="spAnggotaTrigger" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: #fff;">
              <span id="spAnggotaSelectedText" style="color: var(--text-muted);">-- Cari / Pilih Anggota --</span>
              <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <div class="dropdown-menu-custom" id="spAnggotaMenu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: var(--shadow-lg); z-index: 1050; margin-top: 4px; max-height: 280px; overflow-y: auto;">
              <div style="padding: 8px; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; background: #fff;">
                <input type="text" id="spAnggotaSearch" class="form-control" placeholder="Ketik nama atau No. Anggota..." style="font-size: 0.85rem; padding: 6px 10px;">
              </div>
              <div id="spAnggotaList">
                <?php foreach ($listAnggota as $a): ?>
                  <div class="dropdown-item-custom sp-anggota-opt" 
                       data-id="<?= $a['id'] ?>" 
                       data-nama="<?= htmlspecialchars($a['nama']) ?>" 
                       data-no="<?= htmlspecialchars($a['no_anggota']) ?>"
                       style="padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                    <div style="font-weight: 600; color: var(--text-main); font-size: 0.9rem;"><?= htmlspecialchars($a['nama']) ?></div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; gap: 8px;">
                      <span>No: <strong><?= htmlspecialchars($a['no_anggota']) ?></strong></span>
                      <span>&bull;</span>
                      <span><?= htmlspecialchars($a['pekerjaan'] ?? 'Anggota') ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div id="spAnggotaEmpty" style="display: none; padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.82rem;">
                  Tidak ada anggota yang cocok dengan pencarian.
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Target Dana yang Diterima *</label>
            <select name="target_dana" id="formDaftarTarget" class="form-select" onchange="updateDaftarKalkulasi()" required style="font-weight: 700; color: #047857;">
              <?php foreach ($matriks as $target => $v): ?>
                <option value="<?= $target ?>" <?= ($presetTarget == $target || (!$presetTarget && $target == 10000000)) ? 'selected' : '' ?>>
                  <?= rupiah($target) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-col form-group">
            <label class="form-label">Jangka Waktu (Tenor) *</label>
            <select name="tenor_bulan" id="formDaftarTenor" class="form-select" onchange="updateDaftarKalkulasi()" required style="font-weight: 700;">
              <option value="12" <?= $presetTenor == 12 ? 'selected' : '' ?>>1 Tahun (12 Bulan)</option>
              <option value="24" <?= $presetTenor == 24 ? 'selected' : '' ?>>2 Tahun (24 Bulan)</option>
              <option value="36" <?= ($presetTenor == 36 || (!$presetTenor && 36)) ? 'selected' : '' ?>>3 Tahun (36 Bulan)</option>
              <option value="48" <?= $presetTenor == 48 ? 'selected' : '' ?>>4 Tahun (48 Bulan)</option>
              <option value="60" <?= $presetTenor == 60 ? 'selected' : '' ?>>5 Tahun (60 Bulan)</option>
              <option value="72" <?= $presetTenor == 72 ? 'selected' : '' ?>>6 Tahun (72 Bulan)</option>
            </select>
          </div>
        </div>

        <!-- Rincian Setoran Bulanan Otomatis -->
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="font-size: 0.85rem; font-weight: 700; color: #166534;">Setoran Wajib Bulanan (Resmi):</span>
            <span id="daftarSetoranPreview" style="font-size: 1.25rem; font-weight: 800; color: #047857;">Rp 0 / bln</span>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.8rem; color: #15803d; border-top: 1px dashed #bbf7d0; padding-top: 8px;">
            <div>Total Simpanan Disetor: <strong id="daftarTotalDisetor">Rp 0</strong></div>
            <div style="text-align: right;">Keuntungan/Bonus: <strong id="daftarKeuntungan" style="color: #1d4ed8;">Rp 0</strong></div>
          </div>
        </div>

        <div class="form-row">
          <!-- Tanggal Mulai dd/mm/yyyy -->
          <div class="form-col form-group">
            <label class="form-label">Tanggal Mulai Simpanan *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal_mulai" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required style="padding-right: 40px; font-weight: 600;">
              <button type="button" class="btn-date-picker" tabindex="-1" onclick="triggerDatePicker(this)" style="position: absolute; right: 4px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;" title="Buka Kalender">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" tabindex="-1" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>

          <div class="form-col form-group">
            <label class="form-label">Tujuan / Keterangan (Opsional)</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Persiapan biaya masuk SMP anak">
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalDaftarPeserta')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Daftarkan Peserta
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: INPUT SETORAN SIMPANAN PENDIDIKAN                                   -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalInputSetoran">
  <div class="modal-card" style="max-width: 580px;">
    <div class="modal-header">
      <div>
        <h3 style="margin: 0;">Form Setoran Simpanan Pendidikan</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: var(--text-muted);">
          Pencatatan setoran bulanan anggota untuk simpanan pendidikan
        </p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalInputSetoran')">&times;</button>
    </div>

    <form action="simpanan_pendidikan.php" method="POST">
      <input type="hidden" name="action" value="bayar_setoran">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Pilih Rekening Simpanan Pendidikan *</label>
          <select name="simpanan_pendidikan_id" id="selectRekeningSp" class="form-select" onchange="onSelectRekeningSp(this)" required>
            <option value="">-- Pilih Rekening Anggota --</option>
            <?php foreach ($listSp as $sp): ?>
              <?php if ($sp['status'] === 'aktif'): ?>
                <option value="<?= $sp['id'] ?>" 
                        data-target="<?= $sp['target_dana'] ?>"
                        data-tenor="<?= $sp['tenor_bulan'] ?>"
                        data-setoran="<?= $sp['setoran_bulanan'] ?>"
                        data-setoranke="<?= $sp['total_setoran_kali'] + 1 ?>"
                        data-nama="<?= htmlspecialchars($sp['nama_anggota']) ?>"
                        data-rekening="<?= htmlspecialchars($sp['no_rekening']) ?>">
                  <?= htmlspecialchars($sp['no_rekening']) ?> - <?= htmlspecialchars($sp['nama_anggota']) ?> (Setoran ke-<?= $sp['total_setoran_kali'] + 1 ?> dari <?= $sp['tenor_bulan'] ?>)
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="boxInfoRekening" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 0.85rem;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span style="color: #1e40af;">Target Dana: <strong id="infoSpTarget">Rp 0</strong></span>
            <span style="color: #1e40af;">Tenor: <strong id="infoSpTenor">0 Bulan</strong></span>
          </div>
          <div style="color: #1e3a8a;">
            Pembayaran untuk: <strong id="infoSpBulanKe" style="color: #047857;">Bulan ke-X</strong>
          </div>
        </div>

        <div class="form-row">
          <!-- Nominal dengan Rp & titik ribuan -->
          <div class="form-col form-group">
            <label class="form-label">Nominal Setoran (Rp) *</label>
            <input type="text" name="nominal" id="inputSetoranNominal" class="form-control" placeholder="Contoh: Rp 248.700" oninput="formatRupiahInput(this)" required style="font-weight: 700; color: #047857;">
          </div>

          <!-- Tanggal Bayar format dd/mm/yyyy -->
          <div class="form-col form-group">
            <label class="form-label">Tanggal Bayar *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal_bayar" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required style="padding-right: 40px; font-weight: 600;">
              <button type="button" class="btn-date-picker" tabindex="-1" onclick="triggerDatePicker(this)" style="position: absolute; right: 4px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;" title="Buka Kalender">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" tabindex="-1" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>
        </div>

        <div class="form-row">
          <!-- Keterangan Dropdown: Langsung, TF, Gaji, TPP -->
          <div class="form-col form-group">
            <label class="form-label">Keterangan Metode *</label>
            <select name="keterangan" class="form-select" required>
              <option value="Langsung">Langsung (Kasir Tunai)</option>
              <option value="TF">TF (Transfer Bank)</option>
              <option value="Gaji">Gaji (Potong Gaji)</option>
              <option value="TPP">TPP (Potong TPP)</option>
            </select>
          </div>

          <div class="form-col form-group">
            <label class="form-label">Catatan Tambahan (Opsional)</label>
            <input type="text" name="catatan" class="form-control" placeholder="Contoh: Bukti slip transfer BJB">
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalInputSetoran')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Proses Setoran
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: DETAIL / KARTU RIWAYAT SIMPANAN PENDIDIKAN                          -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalDetailSp">
  <div class="modal-card" style="max-width: 780px;">
    <div class="modal-header">
      <div>
        <h3 id="detTitleRekening" style="margin: 0;">Kartu Simpanan Pendidikan</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: var(--text-muted);" id="detSubtitleAnggota">
          Informasi buku rekening tabungan berjangka pendidikan anggota
        </p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalDetailSp')">&times;</button>
    </div>

    <div class="modal-body" id="detContentBody">
      <div style="text-align: center; padding: 24px;">Memuat data...</div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modalDetailSp')">Tutup</button>
      <button type="button" class="btn btn-primary" onclick="window.print()">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Kartu Simpanan
      </button>
    </div>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: UBAH STATUS SIMPANAN PENDIDIKAN                                     -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalUbahStatusSp">
  <div class="modal-card" style="max-width: 440px;">
    <div class="modal-header">
      <h3 style="margin: 0;">Ubah Status Rekening</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalUbahStatusSp')">&times;</button>
    </div>

    <form action="simpanan_pendidikan.php" method="POST">
      <input type="hidden" name="action" value="ubah_status">
      <input type="hidden" name="id" id="ubahStatusId">

      <div class="modal-body">
        <p style="font-size: 0.9rem; color: var(--text-main); margin-bottom: 12px;">
          Ubah status kepesertaan simpanan pendidikan untuk rekening <strong id="ubahStatusNoRekening">-</strong>:
        </p>

        <div class="form-group">
          <label class="form-label">Pilih Status Baru:</label>
          <select name="status" id="ubahStatusSelect" class="form-select" required>
            <option value="aktif">Aktif (Sedang Berjalan)</option>
            <option value="selesai">Selesai (Sudah Penuh / Lunas)</option>
            <option value="batal">Batal / Nonaktif</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalUbahStatusSp')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: KONFIRMASI HAPUS SETORAN                                            -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalHapusSetoranSp">
  <div class="modal-card" style="max-width: 440px;">
    <div class="modal-header">
      <h3 style="color: #dc2626; margin: 0;">Hapus Transaksi Setoran</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalHapusSetoranSp')">&times;</button>
    </div>

    <form action="simpanan_pendidikan.php" method="POST">
      <input type="hidden" name="action" value="hapus_setoran">
      <input type="hidden" name="setoran_id" id="hapusSetoranId">

      <div class="modal-body">
        <p style="font-size: 0.9rem; color: var(--text-main); margin-bottom: 12px;">
          Apakah Anda yakin ingin membatalkan dan menghapus setoran sebesar <strong id="hapusSetoranNominal">Rp 0</strong> untuk rekening <strong id="hapusSetoranRekening">-</strong>?
        </p>
        <div style="font-size: 0.8rem; color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 6px;">
          Perhatian: Tindakan ini akan mengurangi akumulasi saldo simpanan pada rekening terkait.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalHapusSetoranSp')">Batal</button>
        <button type="submit" class="btn btn-danger" style="background: #dc2626; border-color: #dc2626; color: #fff;">Konfirmasi Hapus</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: LIHAT TABEL MATRIKS & SIMULASI CEPAT                                -->
<!-- ========================================================================= -->
<div class="modal-overlay" id="modalLihatMatriks">
  <div class="modal-card" style="max-width: 960px; max-height: 92vh;">
    <div class="modal-header">
      <div>
        <h3 style="margin: 0; color: #1e40af; display: flex; align-items: center; gap: 8px;">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Tabel Matriks Resmi & Simulasi Simpanan Pendidikan
        </h3>
        <p style="margin: 2px 0 0 0; font-size: 0.82rem; color: var(--text-muted);">
          16 Pilihan Target Dana (1jt s/d 50jt) &bull; Tenor 1 s/d 6 Tahun (12 s/d 72 Bulan)
        </p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalLihatMatriks')">&times;</button>
    </div>

    <div class="modal-body" style="padding: 16px;">
      <!-- Quick Simulator Inside Modal -->
      <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; align-items: end;">
          <div>
            <label style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a; display: block; margin-bottom: 4px;">Pilih Target Dana:</label>
            <select id="modalSimTarget" class="form-select" onchange="modalHitungSimulasi()" style="font-weight: 700; padding: 6px 10px; font-size: 0.88rem;">
              <?php foreach ($matriks as $target => $v): ?>
                <option value="<?= $target ?>" <?= $target == 10000000 ? 'selected' : '' ?>><?= rupiah($target) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a; display: block; margin-bottom: 4px;">Pilih Tenor:</label>
            <select id="modalSimTenor" class="form-select" onchange="modalHitungSimulasi()" style="font-weight: 700; padding: 6px 10px; font-size: 0.88rem;">
              <option value="12">1 Tahun (12 Bulan)</option>
              <option value="24">2 Tahun (24 Bulan)</option>
              <option value="36" selected>3 Tahun (36 Bulan)</option>
              <option value="48">4 Tahun (48 Bulan)</option>
              <option value="60">5 Tahun (60 Bulan)</option>
              <option value="72">6 Tahun (72 Bulan)</option>
            </select>
          </div>
          <div>
            <label style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a; display: block; margin-bottom: 4px;">Setoran Bulanan:</label>
            <div id="modalSimSetoran" style="font-size: 1.15rem; font-weight: 800; color: #047857; background: #fff; padding: 6px 12px; border-radius: 6px; border: 1px solid #a7f3d0;">
              Rp 248.700 / bln
            </div>
          </div>
          <div>
            <button type="button" class="btn btn-primary" onclick="modalPilihPaketIni()" style="width: 100%; padding: 8px 12px; font-size: 0.84rem;">
              Gunakan Paket Ini &rarr;
            </button>
          </div>
        </div>
      </div>

      <!-- Matrix Table Scrollable -->
      <div class="table-freeze-container" style="max-height: 520px;">
        <table class="matrix-table" style="width: 100%; border-collapse: separate; border-spacing: 0; font-family: 'Plus Jakarta Sans', sans-serif;">
          <thead>
            <tr style="background-color: #f8fafc;">
              <th style="border: 1.5px solid #0f172a; padding: 8px 12px; font-size: 0.9rem; font-weight: 800; text-align: left; position: sticky; top: 0; z-index: 25; background-color: #f8fafc;">Tahun</th>
              <?php foreach ($kolomTenor as $col): ?>
                <th style="border: 1.5px solid #0f172a; padding: 8px 12px; font-size: 0.95rem; font-weight: 800; text-align: right; position: sticky; top: 0; z-index: 25; background-color: #f8fafc;"><?= $col['tahun'] ?></th>
              <?php endforeach; ?>
            </tr>
            <tr style="background-color: #f1f5f9;">
              <th style="border: 1.5px solid #0f172a; padding: 8px 12px; font-size: 0.9rem; font-weight: 800; text-align: left; position: sticky; top: 38px; z-index: 25; background-color: #f1f5f9;">Bulan</th>
              <?php foreach ($kolomTenor as $col): ?>
                <th style="border: 1.5px solid #0f172a; padding: 8px 12px; font-size: 0.95rem; font-weight: 800; text-align: right; position: sticky; top: 38px; z-index: 25; background-color: #f1f5f9;"><?= $col['bulan'] ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($matriks as $target => $barisTenor): ?>
              <tr style="border: 1px solid #0f172a;">
                <td style="border: 1px solid #0f172a; padding: 7px 12px; font-weight: 800; font-size: 0.9rem; text-align: right; background: #fff;">
                  <?= number_format($target, 0, ',', ',') ?>
                </td>
                <?php foreach ($kolomTenor as $col): 
                  $tenorBulan = $col['bulan'];
                  $nominal = $barisTenor[$tenorBulan] ?? 0;
                ?>
                  <td class="modal-matrix-cell" 
                      data-target="<?= $target ?>" 
                      data-tenor="<?= $tenorBulan ?>" 
                      onclick="modalPilihCell(<?= $target ?>, <?= $tenorBulan ?>)"
                      style="border: 1px solid #0f172a; padding: 7px 12px; font-size: 0.88rem; text-align: right; font-weight: 600; cursor: pointer;">
                    <?= number_format($nominal, 0, ',', ',') ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal-footer" style="justify-content: space-between;">
      <a href="simpanan_pendidikan_tabel.php" class="btn btn-secondary" style="color: #1e40af;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        Buka Halaman Penuh Matriks
      </a>
      <button type="button" class="btn btn-primary" onclick="closeModal('modalLihatMatriks')">Tutup</button>
    </div>
  </div>
</div>

<style>
.modal-matrix-cell:hover {
  background-color: #dbeafe !important;
  color: #1e40af !important;
  font-weight: 800 !important;
}
.modal-matrix-cell.active-cell {
  background-color: #bfdbfe !important;
  color: #1d4ed8 !important;
  font-weight: 800 !important;
  outline: 2px solid #2563eb;
}
</style>

<script>
// Immediate Safety Modal Helpers
if (typeof window.openModal !== 'function') {
  window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  };
}
if (typeof window.closeModal !== 'function') {
  window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  };
}

const matriksData = <?= json_encode($matriks) ?>;

function formatRpJs(num) {
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

// Update live estimation when selecting Target & Tenor in Pendaftaran Modal
function updateDaftarKalkulasi() {
  const target = parseInt(document.getElementById('formDaftarTarget').value) || 0;
  const tenor = parseInt(document.getElementById('formDaftarTenor').value) || 0;

  let setoran = 0;
  if (matriksData[target] && matriksData[target][tenor]) {
    setoran = matriksData[target][tenor];
  }

  const totalDisetor = setoran * tenor;
  const keuntungan = target - totalDisetor;

  document.getElementById('daftarSetoranPreview').innerText = formatRpJs(setoran) + ' / bln';
  document.getElementById('daftarTotalDisetor').innerText = formatRpJs(totalDisetor);
  
  if (keuntungan > 0) {
    const persen = ((keuntungan / totalDisetor) * 100).toFixed(2);
    document.getElementById('daftarKeuntungan').innerText = '+' + formatRpJs(keuntungan) + ' (' + persen + '%)';
  } else {
    document.getElementById('daftarKeuntungan').innerText = formatRpJs(0);
  }
}

// Buka modal bayar langsung untuk rekening tertentu
function bukaModalBayarLangsung(spId) {
  const select = document.getElementById('selectRekeningSp');
  if (select) {
    select.value = typeof spId === 'object' ? spId.id : spId;
    onSelectRekeningSp(select);
  }
  openModal('modalInputSetoran');
}

// Callback when selecting rekening in Input Setoran modal
function onSelectRekeningSp(el) {
  const opt = el.options[el.selectedIndex];
  const boxInfo = document.getElementById('boxInfoRekening');
  const inputNominal = document.getElementById('inputSetoranNominal');

  if (!opt || !opt.value) {
    boxInfo.style.display = 'none';
    inputNominal.value = '';
    return;
  }

  const target = opt.getAttribute('data-target');
  const tenor = opt.getAttribute('data-tenor');
  const setoran = opt.getAttribute('data-setoran');
  const setoranke = opt.getAttribute('data-setoranke');

  document.getElementById('infoSpTarget').innerText = formatRpJs(target);
  document.getElementById('infoSpTenor').innerText = tenor + ' Bulan';
  document.getElementById('infoSpBulanKe').innerText = 'Bulan ke-' + setoranke + ' dari ' + tenor;
  boxInfo.style.display = 'block';

  inputNominal.value = formatRpJs(setoran);
}

// Buka modal ubah status
function bukaModalUbahStatus(el) {
  let sp;
  if (typeof el === 'object' && el.dataset && el.dataset.sp) {
    sp = JSON.parse(el.dataset.sp);
  } else {
    sp = el;
  }
  document.getElementById('ubahStatusId').value = sp.id;
  document.getElementById('ubahStatusNoRekening').innerText = sp.no_rekening + ' (' + sp.nama_anggota + ')';
  document.getElementById('ubahStatusSelect').value = sp.status;
  openModal('modalUbahStatusSp');
}

// Konfirmasi hapus setoran
function konfirmasiHapusSetoran(id, noRekening, nominal) {
  document.getElementById('hapusSetoranId').value = id;
  document.getElementById('hapusSetoranRekening').innerText = noRekening;
  document.getElementById('hapusSetoranNominal').innerText = nominal;
  openModal('modalHapusSetoranSp');
}

// Lihat Detail / Kartu Simpanan Pendidikan
function lihatDetailSp(el) {
  let sp;
  if (typeof el === 'object' && el.dataset && el.dataset.sp) {
    sp = JSON.parse(el.dataset.sp);
  } else {
    sp = el;
  }

  document.getElementById('detTitleRekening').innerText = 'Kartu Simpanan: ' + sp.no_rekening;
  document.getElementById('detSubtitleAnggota').innerText = sp.nama_anggota + ' (' + sp.no_anggota + ')';

  const body = document.getElementById('detContentBody');
  const persen = sp.tenor_bulan > 0 ? Math.min(100, Math.round((sp.total_setoran_kali / sp.tenor_bulan) * 100)) : 0;
  const sisaBulan = Math.max(0, sp.tenor_bulan - sp.total_setoran_kali);
  const totalRencanaDisetor = sp.setoran_bulanan * sp.tenor_bulan;
  const estimasiBonus = sp.target_dana - totalRencanaDisetor;

  body.innerHTML = `
    <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
      <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <div>
          <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85;">No. Rekening Simpanan</span>
          <h2 style="margin: 2px 0 0 0; font-size: 1.4rem; font-weight: 800;">${sp.no_rekening}</h2>
        </div>
        <div style="text-align: right;">
          <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85;">Target Dana Diterima</span>
          <h2 style="margin: 2px 0 0 0; font-size: 1.4rem; font-weight: 800; color: #a7f3d0;">${formatRpJs(sp.target_dana)}</h2>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 0.85rem;">
        <div>
          <div style="opacity: 0.8; font-size: 0.75rem;">Nama Anggota:</div>
          <strong>${sp.nama_anggota}</strong>
        </div>
        <div>
          <div style="opacity: 0.8; font-size: 0.75rem;">Setoran Bulanan:</div>
          <strong>${formatRpJs(sp.setoran_bulanan)} / bln</strong>
        </div>
        <div>
          <div style="opacity: 0.8; font-size: 0.75rem;">Tenor:</div>
          <strong>${sp.tenor_bulan} Bulan (${Math.round(sp.tenor_bulan/12)} Thn)</strong>
        </div>
        <div>
          <div style="opacity: 0.8; font-size: 0.75rem;">Jatuh Tempo:</div>
          <strong>${sp.tanggal_jatuh_tempo || '-'}</strong>
        </div>
      </div>
    </div>

    <!-- Progress Card -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
        <span style="font-size: 0.9rem; font-weight: 700; color: var(--secondary);">Progres Tabungan Anggota:</span>
        <span style="font-size: 0.95rem; font-weight: 800; color: #047857;">${persen}% (${sp.total_setoran_kali} dari ${sp.tenor_bulan} Bulan)</span>
      </div>
      <div style="background: #e2e8f0; height: 10px; border-radius: 6px; overflow: hidden; margin-bottom: 12px;">
        <div style="background: ${persen >= 100 ? '#10b981' : '#3b82f6'}; width: ${persen}%; height: 100%; transition: width 0.3s ease;"></div>
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 0.85rem;">
        <div>Total Masuk: <strong style="color: #047857;">${formatRpJs(sp.total_setoran_nominal)}</strong></div>
        <div>Sisa Setoran: <strong>${sisaBulan} Bulan</strong></div>
        <div>Bonus/Keuntungan: <strong style="color: #1d4ed8;">+${formatRpJs(estimasiBonus)}</strong></div>
      </div>
    </div>

    <div style="font-size: 0.9rem; font-weight: 700; margin-bottom: 8px;">Keterangan / Catatan Pendaftaran:</div>
    <p style="font-size: 0.85rem; color: var(--text-muted); background: #fff; border: 1px solid #e2e8f0; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px;">
      ${sp.keterangan || 'Tidak ada catatan pendaftaran.'}
    </p>
  `;

  openModal('modalDetailSp');
}

// Simulasi di Modal Lihat Matriks
function modalHitungSimulasi() {
  const target = parseInt(document.getElementById('modalSimTarget').value) || 0;
  const tenor = parseInt(document.getElementById('modalSimTenor').value) || 0;
  let setoran = 0;
  if (matriksData[target] && matriksData[target][tenor]) {
    setoran = matriksData[target][tenor];
  }
  document.getElementById('modalSimSetoran').innerText = formatRpJs(setoran) + ' / bln';

  document.querySelectorAll('.modal-matrix-cell').forEach(cell => {
    if (parseInt(cell.dataset.target) === target && parseInt(cell.dataset.tenor) === tenor) {
      cell.classList.add('active-cell');
    } else {
      cell.classList.remove('active-cell');
    }
  });
}

function modalPilihCell(target, tenor) {
  document.getElementById('modalSimTarget').value = target;
  document.getElementById('modalSimTenor').value = tenor;
  modalHitungSimulasi();
}

function modalPilihPaketIni() {
  const target = document.getElementById('modalSimTarget').value;
  const tenor = document.getElementById('modalSimTenor').value;
  closeModal('modalLihatMatriks');

  document.getElementById('formDaftarTarget').value = target;
  document.getElementById('formDaftarTenor').value = tenor;
  updateDaftarKalkulasi();
  openModal('modalDaftarPeserta');
}

// Searchable Anggota Selector setup for Pendaftaran Modal
document.addEventListener('DOMContentLoaded', function() {
  updateDaftarKalkulasi();
  modalHitungSimulasi();

  const trigger = document.getElementById('spAnggotaTrigger');
  const menu = document.getElementById('spAnggotaMenu');
  const searchInput = document.getElementById('spAnggotaSearch');
  const emptyNotice = document.getElementById('spAnggotaEmpty');
  const options = document.querySelectorAll('#spAnggotaList .sp-anggota-opt');
  const hiddenId = document.getElementById('hidden_sp_anggota_id');
  const selectedText = document.getElementById('spAnggotaSelectedText');

  if (trigger && menu) {
    trigger.addEventListener('click', function(e) {
      e.stopPropagation();
      const isOpen = menu.style.display === 'block';
      menu.style.display = isOpen ? 'none' : 'block';
      if (!isOpen && searchInput) {
        setTimeout(() => searchInput.focus(), 50);
      }
    });

    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        let matchedCount = 0;

        options.forEach(opt => {
          const nama = (opt.getAttribute('data-nama') || '').toLowerCase();
          const no = (opt.getAttribute('data-no') || '').toLowerCase();
          if (nama.includes(query) || no.includes(query)) {
            opt.style.display = 'block';
            matchedCount++;
          } else {
            opt.style.display = 'none';
          }
        });

        if (emptyNotice) {
          emptyNotice.style.display = matchedCount === 0 ? 'block' : 'none';
        }
      });

      if (typeof initSearchDropdownKeyNav === 'function') {
        initSearchDropdownKeyNav({
          input: searchInput,
          list: document.getElementById('spAnggotaList'),
          itemSelector: '.sp-anggota-opt',
          onEscape: () => { menu.style.display = 'none'; }
        });
      }
    }

    options.forEach(opt => {
      opt.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nama = this.getAttribute('data-nama');
        const no = this.getAttribute('data-no');

        hiddenId.value = id;
        selectedText.innerHTML = `<strong>${nama}</strong> <span style="font-size:0.8rem; color:var(--text-muted); font-weight:normal;">(${no})</span>`;
        selectedText.style.color = 'var(--text-main)';
        menu.style.display = 'none';
      });

      opt.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f1f5f9';
      });
      opt.addEventListener('mouseleave', function() {
        this.style.backgroundColor = 'transparent';
      });
    });

    document.addEventListener('click', function(e) {
      if (!trigger.contains(e.target) && !menu.contains(e.target)) {
        menu.style.display = 'none';
      }
    });
  }

  <?php if ($autoOpenDaftar): ?>
    openModal('modalDaftarPeserta');
  <?php endif; ?>
});
</script>

<?php include 'footer.php'; ?>
