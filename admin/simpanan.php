<?php
/**
 * Halaman Input & Data Simpanan - Admin Koperasi Gatra Mandiri
 */
$pageTitle = 'Simpanan Anggota';
$pageSubtitle = 'Input transaksi dan kelola data simpanan pokok, wajib, dan sukarela';
$activePage = 'simpanan';

require_once __DIR__ . '/header.php';

// Handle Input Transaksi Simpanan Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_simpanan') {
    $anggota_id = (int)$_POST['anggota_id'];
    $jenis_simpanan = $_POST['jenis_simpanan'] ?? 'wajib';
    $tipe_transaksi = $_POST['tipe_transaksi'] ?? 'setor';

    // Bersihkan nominal dari format Rp dan titik pemisah ribuan
    $rawNominal = str_replace(['Rp', '.', ' '], '', $_POST['nominal'] ?? '0');
    $rawNominal = str_replace(',', '.', $rawNominal);
    $nominal = (float)$rawNominal;

    // Tanggal dengan format dd/mm/yyyy
    $tanggal = parse_dmy_to_ymd($_POST['tanggal'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $petugas = $currentUser['nama_lengkap'];

    if ($anggota_id > 0 && $nominal > 0) {
        $stmt = $db->prepare("INSERT INTO simpanan (anggota_id, jenis_simpanan, tipe_transaksi, nominal, tanggal, keterangan, petugas) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$anggota_id, $jenis_simpanan, $tipe_transaksi, $nominal, $tanggal, $keterangan, $petugas]);
        set_flash('success', 'Transaksi simpanan sebesar ' . rupiah($nominal) . ' berhasil disimpan!');
        header('Location: simpanan.php');
        exit;
    } else {
        set_flash('error', 'Silakan pilih anggota dan masukkan nominal yang valid.');
    }
}

// Ambil daftar seluruh anggota untuk dropdown modal
$daftarAnggota = $db->query("SELECT id, no_anggota, nama FROM anggota WHERE status = 'aktif' ORDER BY nama ASC")->fetchAll();

// Hitung total simpanan berdasarkan jenis
$totalPokok = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE jenis_simpanan='pokok'")->fetchColumn() ?: 0;
$totalWajib = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE jenis_simpanan='wajib'")->fetchColumn() ?: 0;
$totalSukarela = $db->query("SELECT SUM(CASE WHEN tipe_transaksi='setor' THEN nominal ELSE -nominal END) FROM simpanan WHERE jenis_simpanan='sukarela'")->fetchColumn() ?: 0;
$totalSemua = $totalPokok + $totalWajib + $totalSukarela;

// Ambil list transaksi simpanan
$listSimpanan = $db->query("
    SELECT s.*, a.nama as nama_anggota, a.no_anggota 
    FROM simpanan s
    JOIN anggota a ON s.anggota_id = a.id
    ORDER BY s.tanggal DESC, s.id DESC
")->fetchAll();
?>

<!-- Ringkasan Jenis Simpanan -->
<div class="stats-grid">
  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Pokok</div>
      <div class="stat-value text-green"><?= rupiah($totalPokok) ?></div>
      <div class="stat-hint">Setoran awal saat masuk</div>
    </div>
    <div class="stat-icon green">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Wajib</div>
      <div class="stat-value text-blue"><?= rupiah($totalWajib) ?></div>
      <div class="stat-hint">Iuran bulanan anggota</div>
    </div>
    <div class="stat-icon blue">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Simpanan Sukarela</div>
      <div class="stat-value text-green"><?= rupiah($totalSukarela) ?></div>
      <div class="stat-hint">Dapat disetor / ditarik kapan saja</div>
    </div>
    <div class="stat-icon amber">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
    </div>
  </div>

  <div class="stat-card">
    <div>
      <div class="stat-title">Total Kas Simpanan</div>
      <div class="stat-value"><?= rupiah($totalSemua) ?></div>
      <div class="stat-hint">Total dana simpanan koperasi</div>
    </div>
    <div class="stat-icon green">
      <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>
  </div>
</div>

<!-- Main Table Card -->
<div class="card">
  <div class="card-header">
    <h3>Daftar Riwayat Simpanan Anggota</h3>
    <div class="card-header-actions">
      <input type="text" class="form-control" style="width: 240px; padding: 7px 12px; font-size: 0.85rem;" placeholder="Cari nama atau No. Anggota..." data-table-search="tableSimpanan">
      <button type="button" class="btn btn-primary" onclick="openModal('modalTambahSimpanan')">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Input Simpanan
      </button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table" id="tableSimpanan">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tanggal</th>
          <th>No. Anggota</th>
          <th>Nama Anggota</th>
          <th>Jenis Simpanan</th>
          <th>Tipe</th>
          <th>Nominal</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listSimpanan)): ?>
          <tr><td colspan="8" style="text-align:center; padding:32px; color:var(--text-subtle);">Belum ada catatan transaksi simpanan.</td></tr>
        <?php else: ?>
          <?php foreach ($listSimpanan as $s): ?>
            <tr>
              <td>#<?= $s['id'] ?></td>
              <td><?= date('d/m/Y', strtotime($s['tanggal'])) ?></td>
              <td><strong><?= htmlspecialchars($s['no_anggota']) ?></strong></td>
              <td><?= htmlspecialchars($s['nama_anggota']) ?></td>
              <td>
                <span class="badge badge-info">
                  Simpanan <?= ucfirst($s['jenis_simpanan']) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $s['tipe_transaksi'] === 'setor' ? 'badge-success' : 'badge-danger' ?>">
                  <?= ucfirst($s['tipe_transaksi']) ?>
                </span>
              </td>
              <td class="table-amount <?= $s['tipe_transaksi'] === 'setor' ? 'text-green' : 'text-red' ?>">
                <?= ($s['tipe_transaksi'] === 'setor' ? '+' : '-') ?> <?= rupiah($s['nominal']) ?>
              </td>
              <td>
                <?php 
                  $ket = trim($s['keterangan'] ?? '');
                  if ($ket === 'Langsung'): ?>
                    <span class="badge badge-success" style="font-size:0.75rem; font-weight:700;">Langsung</span>
                <?php elseif ($ket === 'TF'): ?>
                    <span class="badge badge-info" style="font-size:0.75rem; font-weight:700;">TF</span>
                <?php elseif ($ket === 'Gaji'): ?>
                    <span class="badge" style="font-size:0.75rem; font-weight:700; background-color:#8b5cf6; color:#fff;">Gaji</span>
                <?php elseif ($ket === 'TPP'): ?>
                    <span class="badge badge-warning" style="font-size:0.75rem; font-weight:700;">TPP</span>
                <?php elseif ($ket !== ''): ?>
                    <small style="color:var(--text-muted);"><?= htmlspecialchars($ket) ?></small>
                <?php else: ?>
                    <span style="color:var(--text-subtle);">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Input Transaksi Simpanan -->
<div class="modal-overlay" id="modalTambahSimpanan">
  <div class="modal-card">
    <div class="modal-header">
      <h3>Input Transaksi Simpanan Baru</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalTambahSimpanan')">&times;</button>
    </div>
    <form method="POST" action="simpanan.php">
      <input type="hidden" name="action" value="tambah_simpanan">
      <div class="modal-body">
        
        <!-- Dropdown Pilih Anggota dengan Fitur Pencarian -->
        <div class="form-group" style="position: relative;">
          <label class="form-label">Pilih Anggota Koperasi *</label>
          <input type="hidden" name="anggota_id" id="hidden_anggota_simpanan_id" required>

          <div id="simpananAnggotaTrigger" class="form-control" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; min-height: 42px; padding: 8px 12px; background: #fff; user-select: none;">
            <span id="simpananAnggotaTriggerText" style="color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              -- Cari & Pilih Anggota --
            </span>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); flex-shrink: 0; margin-left: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>

          <div id="simpananAnggotaMenu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); z-index: 1050; overflow: hidden;">
            <div style="padding: 10px; border-bottom: 1px solid var(--border-color); background: #f8fafc;">
              <div style="position: relative; display: flex; align-items: center;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="position: absolute; left: 10px; color: var(--text-muted); pointer-events: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="simpananAnggotaSearch" class="form-control form-control-sm" placeholder="Ketik nama atau nomor anggota..." style="padding-left: 32px; font-size: 0.84rem;" autocomplete="off">
              </div>
            </div>

            <div id="simpananAnggotaList" style="max-height: 220px; overflow-y: auto;">
              <div class="simpanan-anggota-opt" data-id="" data-text="" style="padding: 10px 14px; cursor: pointer; color: var(--text-muted); font-size: 0.85rem; border-bottom: 1px solid #f1f5f9;">
                -- Batalkan Pilihan --
              </div>
              <?php foreach ($daftarAnggota as $ag): 
                $searchKey = strtolower($ag['no_anggota'] . ' ' . $ag['nama']);
              ?>
                <div class="simpanan-anggota-opt" 
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

              <div id="simpananAnggotaEmpty" style="display: none; padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.82rem;">
                Tidak ada anggota yang cocok dengan pencarian.
              </div>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Jenis Simpanan *</label>
            <select name="jenis_simpanan" class="form-select" required>
              <option value="wajib">Simpanan Wajib (Bulanan)</option>
              <option value="sukarela">Simpanan Sukarela</option>
              <option value="pokok">Simpanan Pokok (Awal Masuk)</option>
            </select>
          </div>

          <div class="form-col form-group">
            <label class="form-label">Tipe Transaksi *</label>
            <select name="tipe_transaksi" class="form-select" required>
              <option value="setor">Setor Simpanan (+)</option>
              <option value="tarik">Penarikan Dana (-)</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <!-- Nominal dengan Rp dan Titik Pemisah Ribuan -->
          <div class="form-col form-group">
            <label class="form-label">Nominal (Rp) *</label>
            <input type="text" name="nominal" id="input_nominal_simpanan" class="form-control" placeholder="Contoh: Rp 100.000" oninput="formatRupiahInput(this)" required style="font-weight: 700; color: var(--secondary);">
          </div>

          <!-- Tanggal Format dd/mm/yyyy dengan Pemilih Kalender -->
          <div class="form-col form-group">
            <label class="form-label">Tanggal Transaksi *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required style="padding-right: 40px; font-weight: 600;">
              <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 4px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;" title="Buka Kalender">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>
        </div>

        <!-- Keterangan Dropdown: Langsung, TF, Gaji, TPP -->
        <div class="form-group">
          <label class="form-label">Keterangan</label>
          <select name="keterangan" class="form-select">
            <option value="">-- Pilih Keterangan (Opsional) --</option>
            <option value="Langsung">Langsung</option>
            <option value="TF">TF</option>
            <option value="Gaji">Gaji</option>
            <option value="TPP">TPP</option>
          </select>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambahSimpanan')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Simpan Transaksi
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const trigger = document.getElementById('simpananAnggotaTrigger');
  const menu = document.getElementById('simpananAnggotaMenu');
  const searchInput = document.getElementById('simpananAnggotaSearch');
  const emptyNotice = document.getElementById('simpananAnggotaEmpty');
  const options = document.querySelectorAll('#simpananAnggotaList .simpanan-anggota-opt');
  const hiddenId = document.getElementById('hidden_anggota_simpanan_id');
  const triggerText = document.getElementById('simpananAnggotaTriggerText');

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
          filterAnggotaSimpanan('');
          setTimeout(() => searchInput.focus(), 50);
        }
      }
    });

    if (searchInput) {
      searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
      });

      searchInput.addEventListener('input', function() {
        filterAnggotaSimpanan(this.value.trim().toLowerCase());
      });
    }

    function filterAnggotaSimpanan(keyword) {
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
          triggerText.textContent = '-- Cari & Pilih Anggota --';
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

  <?php if (isset($_GET['action']) && $_GET['action'] === 'tambah'): ?>
    openModal('modalTambahSimpanan');
  <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
