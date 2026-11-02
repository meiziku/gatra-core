<?php
/**
 * Simulasi & Form Pengajuan Pinjaman - Anggota Koperasi Gatra Mandiri
 */
$pageTitle = 'Simulasi Pinjaman';
$pageSubtitle = 'Hitung estimasi cicilan bulanan dan ajukan pembiayaan koperasi';
$activePage = 'simulasi';

require_once __DIR__ . '/header.php';

$anggotaId = $memberProfile['id'];

// Handle Pengajuan Pinjaman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan_pinjaman') {
    $jumlah_pinjaman = (float)$_POST['jumlah_pinjaman'];
    $lama_bulan = (int)$_POST['lama_bulan'];
    $bunga_persen = 1.0; // 1% flat per bulan standar koperasi
    $keterangan = trim($_POST['keterangan'] ?? '');

    $angsuran_pokok = $jumlah_pinjaman / $lama_bulan;
    $angsuran_bunga = $jumlah_pinjaman * ($bunga_persen / 100);
    $total_angsuran_bulanan = $angsuran_pokok + $angsuran_bunga;
    $total_harus_dibayar = $total_angsuran_bulanan * $lama_bulan;

    $count = $db->query("SELECT COUNT(*) FROM pinjaman")->fetchColumn() + 1;
    $no_pinjaman = 'AJU-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

    if ($jumlah_pinjaman >= 500000 && $lama_bulan > 0) {
        $stmt = $db->prepare("INSERT INTO pinjaman 
            (no_pinjaman, anggota_id, jumlah_pinjaman, bunga_persen, lama_bulan, angsuran_pokok, angsuran_bunga, total_angsuran_bulanan, total_harus_dibayar, tanggal_pengajuan, status, keterangan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu', ?)");
        
        $stmt->execute([
            $no_pinjaman,
            $anggotaId,
            $jumlah_pinjaman,
            $bunga_persen,
            $lama_bulan,
            $angsuran_pokok,
            $angsuran_bunga,
            $total_angsuran_bulanan,
            $total_harus_dibayar,
            date('Y-m-d'),
            $keterangan
        ]);

        set_flash('success', "Pengajuan pinjaman ($no_pinjaman) sebesar " . rupiah($jumlah_pinjaman) . " berhasil diajukan! Menunggu verifikasi pengurus koperasi.");
        header('Location: pengajuan.php');
        exit;
    } else {
        set_flash('error', 'Nominal pinjaman minimal adalah Rp 500.000.');
    }
}
?>

<div style="display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: flex-start;">
  
  <!-- Kalkulator Simulasi Interaktif -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <h3>Kalkulator Simulasi Pinjaman</h3>
      <span class="badge badge-success">Bunga Ringan 1.0% / Bulan Flat</span>
    </div>
    <div class="card-body">
      <form method="POST" action="pengajuan.php">
        <input type="hidden" name="action" value="ajukan_pinjaman">

        <div class="form-group">
          <label class="form-label" style="font-size:0.9rem;">Rencana Jumlah Pinjaman (Rp) *</label>
          <input type="number" id="calc_jumlah_pinjaman" name="jumlah_pinjaman" class="form-control" style="font-size:1.1rem; font-weight:700;" value="5000000" min="500000" max="100000000" step="500000" required>
          <div class="form-text">Mulai dari Rp 500.000 hingga Rp 100.000.000 sesuai kebutuhan modal.</div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Jangka Waktu (Tenor) *</label>
            <select id="calc_tenor" name="lama_bulan" class="form-select" required>
              <option value="3">3 Bulan</option>
              <option value="6">6 Bulan</option>
              <option value="12" selected>12 Bulan (1 Tahun)</option>
              <option value="18">18 Bulan (1.5 Tahun)</option>
              <option value="24">24 Bulan (2 Tahun)</option>
              <option value="36">36 Bulan (3 Tahun)</option>
            </select>
          </div>

          <div class="form-col form-group">
            <label class="form-label">Suku Bunga Koperasi</label>
            <input type="text" class="form-control" value="1.0% Per Bulan" readonly style="background-color:#f8fafc; font-weight:600;">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Tujuan / Keperluan Pinjaman</label>
          <textarea name="keterangan" class="form-control" rows="2" placeholder="Contoh: Tambahan modal usaha warung atau kebutuhan renovasi"></textarea>
        </div>

        <!-- Result Box -->
        <div style="background: linear-gradient(135deg, #f0fdf4, #ecfdf5); border: 1px solid var(--primary-border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 22px;">
          <div style="font-size:0.82rem; font-weight:700; color:var(--primary-dark); text-transform:uppercase; margin-bottom:12px;">
            Hasil Perhitungan Estimasi Cicilan:
          </div>

          <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div>
              <span style="font-size:0.8rem; color:var(--text-muted);">Angsuran Pokok / Bln:</span>
              <div id="sim_pokok_bulan" class="table-amount" style="font-size:1rem; font-weight:700; color:var(--secondary);">Rp 0</div>
            </div>
            <div>
              <span style="font-size:0.8rem; color:var(--text-muted);">Jasa Bunga / Bln:</span>
              <div id="sim_bunga_bulan" class="table-amount" style="font-size:1rem; font-weight:700; color:var(--secondary);">Rp 0</div>
            </div>
          </div>

          <div style="border-top:1px dashed var(--primary-border); padding-top:14px; display:flex; justify-content:space-between; align-items:baseline;">
            <div>
              <span style="font-size:0.85rem; font-weight:600; color:var(--primary-dark);">Cicilan Bulanan Anda:</span>
              <div id="sim_total_bulan" class="table-amount text-green" style="font-size:1.6rem; font-weight:800;">Rp 0</div>
            </div>
            <div style="text-align:right;">
              <span style="font-size:0.75rem; color:var(--text-muted);">Total Pengembalian:</span>
              <div id="sim_total_kembali" style="font-weight:700; color:var(--secondary); font-size:0.95rem;">Rp 0</div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem;">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Kirim Pengajuan Pinjaman Sekarang
        </button>
      </form>
    </div>
  </div>

  <!-- Info Syarat & Ketentuan -->
  <div class="card" style="margin-bottom:0;">
    <div class="card-header">
      <h3>Ketentuan Pinjaman</h3>
    </div>
    <div class="card-body" style="font-size: 0.85rem; color: var(--secondary); line-height: 1.6;">
      <ul style="padding-left: 18px; margin-bottom: 16px; display: flex; flex-direction: column; gap: 8px;">
        <li>Telah terdaftar resmi sebagai anggota aktif Gatra Teknika.</li>
        <li>Telah melunasi simpanan pokok dan rutin membayar simpanan wajib.</li>
        <li>Bunga pinjaman <strong>1.0% per bulan (flat)</strong> tanpa biaya tersembunyi.</li>
        <li>Pembayaran angsuran jatuh tempo setiap tanggal 10 setiap bulannya.</li>
        <li>Pencairan dana langsung dilakukan setelah verifikasi pengurus.</li>
      </ul>

      <div style="background-color: var(--bg-subtle); padding: 12px; border-radius: var(--radius-sm); border-left: 3px solid var(--primary);">
        <strong>Butuh Bantuan Pengurus?</strong><br>
        <small style="color:var(--text-muted);">Hubungi layanan kantor koperasi di jam kerja (Senin - Sabtu: 08.00 - 16.00 WIB).</small>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
