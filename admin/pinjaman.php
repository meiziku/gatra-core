<?php
/**
 * Halaman Pinjaman Anggota - Admin Koperasi Gatra Mandiri
 */
$pageTitle = 'Pinjaman Anggota';
$pageSubtitle = 'Input pinjaman baru dan pantau status sisa pinjaman berjalan';
$activePage = 'pinjaman';

require_once __DIR__ . '/header.php';

// Handle Input Pinjaman Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_pinjaman') {
    $anggota_id = (int)$_POST['anggota_id'];

    // Bersihkan nominal plafon pinjaman jika ada Rp dan titik ribuan
    $rawJumlah = str_replace(['Rp', '.', ' '], '', $_POST['jumlah_pinjaman'] ?? '0');
    $rawJumlah = str_replace(',', '.', $rawJumlah);
    $jumlah_pinjaman = (float)$rawJumlah;

    // Tenor manual dalam bulan
    $lama_bulan = (int)$_POST['lama_bulan'];

    // Bunga default 0.8%
    $rawBunga = str_replace(',', '.', $_POST['bunga_persen'] ?? '0.8');
    $bunga_persen = (float)$rawBunga;
    if ($bunga_persen <= 0) $bunga_persen = 0.8;

    // Asuransi pinjaman (opsional)
    $rawAsuransi = str_replace(['Rp', '.', ' '], '', $_POST['asuransi'] ?? '0');
    $rawAsuransi = str_replace(',', '.', $rawAsuransi);
    $asuransi = (float)$rawAsuransi;

    // Tanggal pengajuan dengan format dd/mm/yyyy
    $tanggal_pengajuan = parse_dmy_to_ymd($_POST['tanggal_pengajuan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Perhitungan pinjaman flat
    $angsuran_pokok = ($lama_bulan > 0) ? ($jumlah_pinjaman / $lama_bulan) : 0;
    $angsuran_bunga = $jumlah_pinjaman * ($bunga_persen / 100);
    $total_angsuran_bulanan = $angsuran_pokok + $angsuran_bunga;
    $total_harus_dibayar = $total_angsuran_bulanan * $lama_bulan;

    // Generate No. Pinjaman unik
    $count = $db->query("SELECT COUNT(*) FROM pinjaman")->fetchColumn() + 1;
    $no_pinjaman = 'PINJ-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

    if ($anggota_id > 0 && $jumlah_pinjaman > 0 && $lama_bulan > 0) {
        $stmt = $db->prepare("INSERT INTO pinjaman 
            (no_pinjaman, anggota_id, jumlah_pinjaman, bunga_persen, lama_bulan, angsuran_pokok, angsuran_bunga, total_angsuran_bulanan, total_harus_dibayar, tanggal_pengajuan, tanggal_pencairan, status, keterangan, asuransi) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'berjalan', ?, ?)");
        
        $stmt->execute([
            $no_pinjaman,
            $anggota_id,
            $jumlah_pinjaman,
            $bunga_persen,
            $lama_bulan,
            $angsuran_pokok,
            $angsuran_bunga,
            $total_angsuran_bulanan,
            $total_harus_dibayar,
            $tanggal_pengajuan,
            $tanggal_pengajuan, // pencairan langsung
            $keterangan,
            $asuransi
        ]);

        set_flash('success', "Pinjaman $no_pinjaman sebesar " . rupiah($jumlah_pinjaman) . " berhasil diterbitkan!");
        header('Location: pinjaman.php');
        exit;
    } else {
        set_flash('error', 'Silakan lengkapi data pinjaman dengan benar.');
    }
}

// Ambil daftar seluruh anggota
$daftarAnggota = $db->query("SELECT id, no_anggota, nama FROM anggota WHERE status = 'aktif' ORDER BY nama ASC")->fetchAll();

// Ambil list seluruh pinjaman beserta akumulasi angsuran terbayar
$listPinjaman = $db->query("
    SELECT p.*, a.nama as nama_anggota, a.no_anggota,
           COALESCE(SUM(ag.jumlah_bayar), 0) as total_dibayar,
           COALESCE(SUM(ag.angsuran_pokok), 0) as pokok_dibayar,
           COUNT(ag.id) as kali_angsuran
    FROM pinjaman p
    JOIN anggota a ON p.anggota_id = a.id
    LEFT JOIN angsuran ag ON p.id = ag.pinjaman_id
    GROUP BY p.id
    ORDER BY p.id DESC
")->fetchAll();
?>

<!-- Action Bar -->
<div class="card" style="margin-bottom: 24px;">
  <div class="card-header">
    <h3>Data Portofolio Pinjaman</h3>
    <div class="card-header-actions">
      <input type="text" class="form-control" style="width: 240px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari nomor/nama..." data-table-search="tablePinjaman">
      <button type="button" class="btn btn-primary" onclick="openModal('modalTambahPinjaman'); updateLoanEstimasi();">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Input Pinjaman Baru
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table" id="tablePinjaman">
      <thead>
        <tr>
          <th>No. Pinjaman</th>
          <th>Anggota</th>
          <th>Tgl Pencairan</th>
          <th>Plafon Pinjaman</th>
          <th>Tenor / Bunga</th>
          <th>Cicilan/Bulan</th>
          <th>Total Dibayar</th>
          <th>Sisa Pinjaman</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listPinjaman)): ?>
          <tr><td colspan="10" style="text-align:center; padding:32px; color:var(--text-subtle);">Belum ada pinjaman yang terdaftar.</td></tr>
        <?php else: ?>
          <?php foreach ($listPinjaman as $p): 
            $sisaTagihan = $p['total_harus_dibayar'] - $p['total_dibayar'];
            $isLunas = ($sisaTagihan <= 0);
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($p['no_pinjaman']) ?></strong></td>
              <td>
                <div><?= htmlspecialchars($p['nama_anggota']) ?></div>
                <small style="color:var(--text-subtle);"><?= htmlspecialchars($p['no_anggota']) ?></small>
              </td>
              <td><?= date('d/m/Y', strtotime($p['tanggal_pencairan'] ?? $p['tanggal_pengajuan'])) ?></td>
              <td class="table-amount"><?= rupiah($p['jumlah_pinjaman']) ?></td>
              <td>
                <div><strong><?= $p['lama_bulan'] ?> Bulan</strong></div>
                <small style="color:var(--text-muted);"><?= $p['bunga_persen'] ?>% / bln</small>
                <?php if (!empty($p['asuransi']) && (float)$p['asuransi'] > 0): ?>
                  <div style="font-size:0.75rem; color:#15803d; font-weight:600; margin-top:2px;">Asuransi: <?= rupiah($p['asuransi']) ?></div>
                <?php endif; ?>
              </td>
              <td class="table-amount text-blue"><?= rupiah($p['total_angsuran_bulanan']) ?></td>
              <td class="table-amount text-green">
                <?= rupiah($p['total_dibayar']) ?>
                <div style="font-size:0.75rem; color:var(--text-subtle);"><?= $p['kali_angsuran'] ?>x angsuran</div>
              </td>
              <td class="table-amount <?= $isLunas ? 'text-green' : 'text-red' ?>">
                <?= rupiah(max(0, $sisaTagihan)) ?>
              </td>
              <td>
                <?php if ($isLunas): ?>
                  <span class="badge badge-success">Lunas</span>
                <?php else: ?>
                  <span class="badge badge-warning">Berjalan</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$isLunas): ?>
                  <a href="angsuran.php?pinjaman_id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Catat Angsuran">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Bayar
                  </a>
                <?php else: ?>
                  <span style="font-size:0.75rem; color:var(--text-subtle);">Selesai</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Input Pinjaman Baru -->
<div class="modal-overlay" id="modalTambahPinjaman">
  <div class="modal-card" style="max-width: 620px;">
    <div class="modal-header">
      <h3>Penerbitan Pinjaman Baru</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalTambahPinjaman')">&times;</button>
    </div>
    <form method="POST" action="pinjaman.php">
      <input type="hidden" name="action" value="tambah_pinjaman">
      <div class="modal-body">
        
        <div class="form-group" style="position: relative;">
          <label class="form-label">Pilih Anggota Peminjam *</label>
          <input type="hidden" name="anggota_id" id="hidden_anggota_id" required>

          <!-- Trigger Box Dropdown Pencarian -->
          <div id="anggotaDropdownTrigger" class="form-control" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; min-height: 42px; padding: 8px 12px; background: #fff; user-select: none;">
            <span id="anggotaTriggerText" style="color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              -- Cari & Pilih Anggota Peminjam --
            </span>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); flex-shrink: 0; margin-left: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>

          <!-- Dropdown Menu dengan Search Bar -->
          <div id="anggotaDropdownMenu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); z-index: 1050; overflow: hidden;">
            <div style="padding: 10px; border-bottom: 1px solid var(--border-color); background: #f8fafc;">
              <div style="position: relative; display: flex; align-items: center;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="position: absolute; left: 10px; color: var(--text-muted); pointer-events: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="anggotaSearchInput" class="form-control form-control-sm" placeholder="Ketik nama atau nomor anggota..." style="padding-left: 32px; font-size: 0.84rem;" autocomplete="off">
              </div>
            </div>

            <div id="anggotaOptionsList" style="max-height: 220px; overflow-y: auto;">
              <div class="anggota-option" data-id="" data-text="" style="padding: 10px 14px; cursor: pointer; color: var(--text-muted); font-size: 0.85rem; border-bottom: 1px solid #f1f5f9;">
                -- Batalkan Pilihan --
              </div>
              <?php foreach ($daftarAnggota as $ag): 
                $searchKey = strtolower($ag['no_anggota'] . ' ' . $ag['nama']);
              ?>
                <div class="anggota-option" 
                     data-id="<?= $ag['id'] ?>"
                     data-nama="<?= htmlspecialchars($ag['nama']) ?>"
                     data-no="<?= htmlspecialchars($ag['no_anggota']) ?>"
                     data-text="<?= htmlspecialchars($searchKey) ?>"
                     style="padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                  <div style="font-weight: 700; color: var(--secondary); font-size: 0.88rem;">
                    <?= htmlspecialchars($ag['nama']) ?>
                  </div>
                  <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                    No. Anggota: <strong style="color: var(--primary);"><?= htmlspecialchars($ag['no_anggota']) ?></strong>
                  </div>
                </div>
              <?php endforeach; ?>

              <div id="anggotaEmptyNotice" style="display: none; padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.82rem;">
                Tidak ada anggota yang cocok dengan pencarian.
              </div>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Jumlah / Plafon Pinjaman (Rp) *</label>
            <input type="text" id="calc_jumlah_pinjaman" name="jumlah_pinjaman" class="form-control" placeholder="Contoh: Rp 10.000.000" value="Rp 5.000.000" oninput="formatRupiahInput(this); updateLoanEstimasi();" required style="font-weight: 700; color: var(--secondary);">
          </div>

          <div class="form-col form-group">
            <label class="form-label">Jangka Waktu (Tenor) *</label>
            <div style="position: relative; display: flex; align-items: center;">
              <input type="number" id="calc_tenor" name="lama_bulan" class="form-control" value="12" min="1" max="120" placeholder="Contoh: 12" oninput="updateLoanEstimasi();" onchange="updateLoanEstimasi();" required style="padding-right: 60px; font-weight: 700;">
              <span style="position: absolute; right: 12px; font-weight: 600; color: var(--text-muted); pointer-events: none;">Bulan</span>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Bunga Pinjaman (% / Bulan) *</label>
            <input type="number" id="calc_bunga" name="bunga_persen" class="form-control" value="0.8" step="0.1" oninput="updateLoanEstimasi();" required style="font-weight: 700;">
            <div class="form-text">Default: 0.8% flat per bulan</div>
          </div>

          <div class="form-col form-group">
            <label class="form-label">Asuransi Pinjaman (Rp)</label>
            <input type="text" id="calc_asuransi" name="asuransi" class="form-control" placeholder="Contoh: Rp 50.000" value="Rp 0" oninput="formatRupiahInput(this); updateLoanEstimasi();" style="font-weight: 700; color: var(--secondary);">
            <div class="form-text">Premi perlindungan pinjaman (opsional)</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Tanggal Pencairan *</label>
          <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
            <input type="text" name="tanggal_pengajuan" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required style="padding-right: 40px; font-weight: 600;">
            <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 4px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;" title="Buka Kalender">
              <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </button>
            <input type="date" class="native-date-hidden" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
          </div>
        </div>

        <!-- Real-time Loan Calculator Preview -->
        <div style="background-color: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 18px;">
          <h4 style="font-size:0.82rem; font-weight:700; color:var(--secondary); margin-bottom:10px; text-transform:uppercase;">
            Rincian Estimasi Cicilan Bulanan:
          </h4>
          <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 0.85rem;">
            <div>Pokok / Bulan: <strong id="sim_pokok_bulan" class="text-blue">Rp 0</strong></div>
            <div>Bunga / Bulan: <strong id="sim_bunga_bulan" class="text-blue">Rp 0</strong></div>
            <div>Biaya Asuransi: <strong id="sim_asuransi" class="text-green">Rp 0</strong></div>
            <div>Tenor Pinjaman: <strong id="sim_tenor" class="text-secondary">12 Bulan</strong></div>
            <div style="grid-column: span 2; border-top: 1px solid var(--border-color); padding-top: 8px;">
              Cicilan Total Per Bulan: <strong id="sim_total_bulan" style="font-size:1.05rem; color:var(--primary);">Rp 0</strong>
            </div>
            <div style="grid-column: span 2; font-size: 0.8rem; color: var(--text-muted);">
              Total Pengembalian: <span id="sim_total_kembali" style="font-weight:700; color:var(--secondary);">Rp 0</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Keperluan Pinjaman</label>
          <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Tambahan modal usaha perdagangan atau kebutuhan pendidikan">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambahPinjaman')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Terbitkan Pinjaman
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Fungsi Real-time Kalkulasi Estimasi Cicilan Pinjaman
window.updateLoanEstimasi = function() {
  const amountInput = document.getElementById('calc_jumlah_pinjaman');
  const tenorInput = document.getElementById('calc_tenor');
  const interestInput = document.getElementById('calc_bunga');
  const asuransiInput = document.getElementById('calc_asuransi');

  if (!amountInput || !tenorInput) return;

  const rawAmount = amountInput.value.replace(/[^0-9]/g, '');
  const amount = parseFloat(rawAmount) || 0;

  const months = parseInt(tenorInput.value) || 0;

  const rawRate = interestInput ? interestInput.value.toString().replace(',', '.') : '0.8';
  const rate = parseFloat(rawRate) || 0;

  const rawAsuransi = asuransiInput ? asuransiInput.value.replace(/[^0-9]/g, '') : '0';
  const asuransi = parseFloat(rawAsuransi) || 0;

  const pokokBulan = months > 0 ? (amount / months) : 0;
  const bungaBulan = amount * (rate / 100);
  const totalBulan = pokokBulan + bungaBulan;
  const totalPengembalian = totalBulan * months;

  const formatRp = (val) => 'Rp ' + Math.round(val).toLocaleString('id-ID');

  const elPokok = document.getElementById('sim_pokok_bulan');
  const elBunga = document.getElementById('sim_bunga_bulan');
  const elAsuransi = document.getElementById('sim_asuransi');
  const elTenor = document.getElementById('sim_tenor');
  const elTotal = document.getElementById('sim_total_bulan');
  const elKembali = document.getElementById('sim_total_kembali');

  if (elPokok) elPokok.textContent = formatRp(pokokBulan);
  if (elBunga) elBunga.textContent = formatRp(bungaBulan);
  if (elAsuransi) elAsuransi.textContent = formatRp(asuransi);
  if (elTenor) elTenor.textContent = (months > 0 ? months : 0) + ' Bulan';
  if (elTotal) elTotal.textContent = formatRp(totalBulan);
  if (elKembali) elKembali.textContent = formatRp(totalPengembalian);
};

document.addEventListener('DOMContentLoaded', function() {
  // Jalankan kalkulasi awal saat dokumen siap
  updateLoanEstimasi();

  const amountInput = document.getElementById('calc_jumlah_pinjaman');
  const tenorInput = document.getElementById('calc_tenor');
  const interestInput = document.getElementById('calc_bunga');
  const asuransiInput = document.getElementById('calc_asuransi');

  if (amountInput) amountInput.addEventListener('input', updateLoanEstimasi);
  if (tenorInput) {
    tenorInput.addEventListener('input', updateLoanEstimasi);
    tenorInput.addEventListener('change', updateLoanEstimasi);
  }
  if (interestInput) interestInput.addEventListener('input', updateLoanEstimasi);
  if (asuransiInput) asuransiInput.addEventListener('input', updateLoanEstimasi);

  const trigger = document.getElementById('anggotaDropdownTrigger');
  const menu = document.getElementById('anggotaDropdownMenu');
  const searchInput = document.getElementById('anggotaSearchInput');
  const emptyNotice = document.getElementById('anggotaEmptyNotice');
  const options = document.querySelectorAll('#anggotaOptionsList .anggota-option');
  const hiddenId = document.getElementById('hidden_anggota_id');
  const triggerText = document.getElementById('anggotaTriggerText');

  if (trigger && menu) {
    trigger.addEventListener('click', function(e) {
      e.stopPropagation();
      const isVisible = menu.style.display === 'block';
      if (isVisible) {
        menu.style.display = 'none';
      } else {
        menu.style.display = 'block';
        if (searchInput) {
          searchInput.value = '';
          filterAnggota('');
          setTimeout(() => searchInput.focus(), 50);
        }
      }
    });

    if (searchInput) {
      searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
      });

      searchInput.addEventListener('input', function() {
        filterAnggota(this.value.trim().toLowerCase());
      });
    }

    function filterAnggota(keyword) {
      let visible = 0;
      options.forEach(opt => {
        const text = opt.getAttribute('data-text') || '';
        const id = opt.getAttribute('data-id');

        if (id === '') {
          opt.style.display = keyword === '' ? 'block' : 'none';
          return;
        }

        if (keyword === '' || text.includes(keyword)) {
          opt.style.display = 'block';
          visible++;
        } else {
          opt.style.display = 'none';
        }
      });

      if (emptyNotice) {
        emptyNotice.style.display = (visible === 0 && keyword !== '') ? 'block' : 'none';
      }
    }

    options.forEach(opt => {
      opt.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nama = this.getAttribute('data-nama');
        const no = this.getAttribute('data-no');

        options.forEach(o => o.classList.remove('is-selected'));

        if (id) {
          this.classList.add('is-selected');
          hiddenId.value = id;
          triggerText.innerHTML = '<strong>' + no + '</strong> - ' + nama;
          triggerText.style.color = 'var(--secondary)';
        } else {
          hiddenId.value = '';
          triggerText.textContent = '-- Cari & Pilih Anggota Peminjam --';
          triggerText.style.color = 'var(--text-muted)';
        }
        menu.style.display = 'none';
      });
    });

    document.addEventListener('click', function(e) {
      if (!trigger.contains(e.target) && !menu.contains(e.target)) {
        menu.style.display = 'none';
      }
    });
  }

  // Pemicu buka modal otomatis jika URL parameter ?action=tambah
  <?php if (isset($_GET['action']) && $_GET['action'] === 'tambah'): ?>
    openModal('modalTambahPinjaman');
  <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
