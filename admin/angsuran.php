<?php
/**
 * Halaman Bayar Angsuran Pinjaman - Admin Koperasi Gatra Teknika
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_admin();

$currentUser = current_user();

// 1. Handle Input Pembayaran Angsuran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bayar_angsuran') {
    $pinjaman_id = (int)$_POST['pinjaman_id'];
    $angsuran_ke = (int)$_POST['angsuran_ke'];

    // Bersihkan format nominal rupiah jika mengandung Rp atau titik ribuan
    $rawBayar = str_replace(['Rp', '.', ' '], '', $_POST['jumlah_bayar'] ?? '0');
    $rawBayar = str_replace(',', '.', $rawBayar);
    $jumlah_bayar = (float)$rawBayar;

    $angsuran_pokok = (float)$_POST['angsuran_pokok'];
    $angsuran_bunga = (float)$_POST['angsuran_bunga'];
    $denda = 0; // Denda ditiadakan sesuai instruksi
    $tanggal_bayar = parse_dmy_to_ymd($_POST['tanggal_bayar'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $petugas = $currentUser['nama_lengkap'];

    if ($pinjaman_id > 0 && $jumlah_bayar > 0) {
        $stmt = $db->prepare("INSERT INTO angsuran (pinjaman_id, angsuran_ke, jumlah_bayar, angsuran_pokok, angsuran_bunga, denda, tanggal_bayar, petugas, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$pinjaman_id, $angsuran_ke, $jumlah_bayar, $angsuran_pokok, $angsuran_bunga, $denda, $tanggal_bayar, $petugas, $keterangan]);

        // Cek apakah pinjaman sudah lunas
        $stmtCheck = $db->prepare("
            SELECT p.total_harus_dibayar, COALESCE(SUM(a.jumlah_bayar), 0) as total_dibayar
            FROM pinjaman p
            LEFT JOIN angsuran a ON p.id = a.pinjaman_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmtCheck->execute([$pinjaman_id]);
        $checkData = $stmtCheck->fetch();

        if ($checkData && $checkData['total_dibayar'] >= $checkData['total_harus_dibayar']) {
            $db->prepare("UPDATE pinjaman SET status = 'lunas' WHERE id = ?")->execute([$pinjaman_id]);
            set_flash('success', "Pembayaran angsuran ke-$angsuran_ke berhasil disimpan. Pinjaman dinyatakan LUNAS!");
        } else {
            set_flash('success', "Pembayaran angsuran ke-$angsuran_ke sebesar " . rupiah($jumlah_bayar) . " berhasil dicatat.");
        }
    } else {
        set_flash('error', 'Silakan pilih pinjaman dan periksa nominal pembayaran.');
    }
    header('Location: angsuran.php');
    exit;
}

// 2. Handle Hapus Pembayaran Angsuran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus_angsuran') {
    $angsuranId = (int)($_POST['angsuran_id'] ?? 0);
    if ($angsuranId > 0) {
        $stmtCek = $db->prepare("SELECT pinjaman_id FROM angsuran WHERE id = ?");
        $stmtCek->execute([$angsuranId]);
        $pinjId = $stmtCek->fetchColumn();

        $db->prepare("DELETE FROM angsuran WHERE id = ?")->execute([$angsuranId]);

        // Cek kembali status pinjaman jika sebelumnya lunas
        if ($pinjId) {
            $stmtCheck = $db->prepare("
                SELECT p.total_harus_dibayar, COALESCE(SUM(a.jumlah_bayar), 0) as total_dibayar
                FROM pinjaman p
                LEFT JOIN angsuran a ON p.id = a.pinjaman_id
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmtCheck->execute([$pinjId]);
            $checkData = $stmtCheck->fetch();
            if ($checkData && $checkData['total_dibayar'] < $checkData['total_harus_dibayar']) {
                $db->prepare("UPDATE pinjaman SET status = 'berjalan' WHERE id = ?")->execute([$pinjId]);
            }
        }
        set_flash('success', 'Catatan pembayaran angsuran berhasil dihapus dan status tagihan telah disesuaikan.');
    } else {
        set_flash('error', 'ID angsuran tidak valid.');
    }
    header('Location: angsuran.php');
    exit;
}

// Ambil daftar pinjaman berjalan untuk form
$pinjamanBerjalan = $db->query("
    SELECT p.*, a.nama as nama_anggota, a.no_anggota,
           COALESCE(SUM(ag.jumlah_bayar), 0) as total_dibayar,
           COUNT(ag.id) as kali_angsuran
    FROM pinjaman p
    JOIN anggota a ON p.anggota_id = a.id
    LEFT JOIN angsuran ag ON p.id = ag.pinjaman_id
    WHERE p.status = 'berjalan'
    GROUP BY p.id
    ORDER BY p.id DESC
")->fetchAll();

// Jika ada parameter GET pinjaman_id
$selectedPinjamanId = (int)($_GET['pinjaman_id'] ?? 0);
$selectedPinjaman = null;
if ($selectedPinjamanId > 0) {
    foreach ($pinjamanBerjalan as $pb) {
        if ($pb['id'] === $selectedPinjamanId) {
            $selectedPinjaman = $pb;
            break;
        }
    }
}

// Riwayat Angsuran Terbaru
$listAngsuran = $db->query("
    SELECT a.*, p.no_pinjaman, ag.nama as nama_anggota, ag.no_anggota, p.lama_bulan
    FROM angsuran a
    JOIN pinjaman p ON a.pinjaman_id = p.id
    JOIN anggota ag ON p.anggota_id = ag.id
    ORDER BY a.tanggal_bayar DESC, a.id DESC
")->fetchAll();

$pageTitle = 'Pembayaran Angsuran';
$pageSubtitle = 'Input pembayaran angsuran pinjaman anggota dan pelunasan';
$activePage = 'angsuran';

require_once __DIR__ . '/header.php';
?>

<style>
.pinjaman-option {
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background-color 0.15s ease;
}
.pinjaman-option:hover {
  background-color: #f1f5f9;
}
.pinjaman-option.is-selected {
  background-color: var(--primary-light);
  border-left: 3px solid var(--primary);
}
</style>

<div style="display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: flex-start;">
  <!-- Form Bayar Angsuran -->
  <div class="card" style="margin-bottom: 0;">
    <div class="card-header">
      <h3 style="margin: 0; font-size: 1.05rem;">Form Pembayaran Angsuran</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="angsuran.php" id="formBayarAngsuran">
        <input type="hidden" name="action" value="bayar_angsuran">

        <!-- Input Tersembunyi ID Pinjaman untuk Submit -->
        <input type="hidden" name="pinjaman_id" id="hidden_pinjaman_id" value="<?= $selectedPinjamanId ?>">

        <!-- Dropdown Pinjaman Berjalan dengan Fitur Pencarian -->
        <div class="form-group" style="position: relative;">
          <label class="form-label">Pilih Pinjaman Berjalan *</label>
          
          <!-- Tombol / Kotak Pemicu Dropdown -->
          <div id="pinjamanDropdownTrigger" class="form-control" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; min-height: 42px; padding: 8px 12px; background: #fff; user-select: none;">
            <span id="pinjamanTriggerText" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; <?= $selectedPinjaman ? 'font-weight: 600; color: var(--secondary);' : 'color: var(--text-muted);' ?>">
              <?php if ($selectedPinjaman): 
                $sisaSel = $selectedPinjaman['total_harus_dibayar'] - $selectedPinjaman['total_dibayar'];
              ?>
                <?= htmlspecialchars($selectedPinjaman['no_pinjaman']) ?> - <?= htmlspecialchars($selectedPinjaman['nama_anggota']) ?> (Sisa: <?= rupiah($sisaSel) ?>)
              <?php else: ?>
                -- Pilih Pinjaman Anggota --
              <?php endif; ?>
            </span>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); flex-shrink: 0; margin-left: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>

          <!-- Panel Menu Dropdown dengan Kotak Pencarian -->
          <div id="pinjamanDropdownMenu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); z-index: 1050; overflow: hidden;">
            <!-- Kolom Pencarian di dalam dropdown -->
            <div style="padding: 10px; border-bottom: 1px solid var(--border-color); background: #f8fafc;">
              <div style="position: relative; display: flex; align-items: center;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="position: absolute; left: 10px; color: var(--text-muted); pointer-events: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="pinjamanSearchInput" class="form-control form-control-sm" placeholder="Ketik nama anggota atau no. pinjaman..." style="padding-left: 32px; font-size: 0.84rem;" autocomplete="off">
              </div>
            </div>

            <!-- Daftar Opsi Pinjaman -->
            <div id="pinjamanOptionsList" style="max-height: 240px; overflow-y: auto;">
              <div class="pinjaman-option" data-id="" data-text="" style="color: var(--text-muted); font-size: 0.85rem;">
                -- Batalkan Pilihan --
              </div>
              <?php foreach ($pinjamanBerjalan as $pb): 
                $sisa = $pb['total_harus_dibayar'] - $pb['total_dibayar'];
                $isSelected = ($selectedPinjamanId === $pb['id']);
                $searchKeyword = strtolower($pb['no_pinjaman'] . ' ' . $pb['nama_anggota'] . ' ' . $pb['no_anggota']);
              ?>
                <div class="pinjaman-option <?= $isSelected ? 'is-selected' : '' ?>" 
                     data-id="<?= $pb['id'] ?>"
                     data-text="<?= htmlspecialchars($searchKeyword) ?>">
                  <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                    <span style="font-weight: 700; color: var(--secondary); font-size: 0.86rem;">
                      <?= htmlspecialchars($pb['no_pinjaman']) ?> - <?= htmlspecialchars($pb['nama_anggota']) ?>
                    </span>
                    <span class="badge <?= $isSelected ? 'badge-primary' : 'badge-info' ?>" style="font-size: 0.7rem; white-space: nowrap;">
                      Ke-<?= $pb['kali_angsuran'] + 1 ?>
                    </span>
                  </div>
                  <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); margin-top: 3px;">
                    <span>No. Anggota: <?= htmlspecialchars($pb['no_anggota']) ?></span>
                    <span>Sisa: <strong style="color: #dc2626;"><?= rupiah($sisa) ?></strong></span>
                  </div>
                </div>
              <?php endforeach; ?>
              
              <div id="pinjamanEmptyNotice" style="display: none; padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.82rem;">
                Tidak ada pinjaman yang cocok.
              </div>
            </div>
          </div>
        </div>

        <?php if ($selectedPinjaman): 
          $nextAngsuran = $selectedPinjaman['kali_angsuran'] + 1;
          $sisa = $selectedPinjaman['total_harus_dibayar'] - $selectedPinjaman['total_dibayar'];
          $totalAngsuranBulanan = round($selectedPinjaman['total_angsuran_bulanan']);
          $formattedNominal = number_format($totalAngsuranBulanan, 0, ',', '.');
        ?>
          <div style="background-color: var(--primary-light); border: 1px solid var(--primary-border); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 16px; font-size: 0.85rem;">
            <div style="font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">Detail Pinjaman Terpilih:</div>
            <div>Plafon: <strong><?= rupiah($selectedPinjaman['jumlah_pinjaman']) ?></strong></div>
            <div>Tenor: <strong><?= $selectedPinjaman['lama_bulan'] ?> Bulan</strong> (Terbayar: <?= $selectedPinjaman['kali_angsuran'] ?>x)</div>
            <div>Sisa Tagihan: <strong class="text-red"><?= rupiah($sisa) ?></strong></div>
          </div>

          <input type="hidden" name="angsuran_ke" value="<?= $nextAngsuran ?>">
          <input type="hidden" name="angsuran_pokok" value="<?= $selectedPinjaman['angsuran_pokok'] ?>">
          <input type="hidden" name="angsuran_bunga" value="<?= $selectedPinjaman['angsuran_bunga'] ?>">

          <div class="form-group">
            <label class="form-label">Angsuran Ke-</label>
            <input type="text" class="form-control" value="Bulan ke-<?= $nextAngsuran ?> dari <?= $selectedPinjaman['lama_bulan'] ?> bulan" readonly style="background-color: #f8fafc; font-weight:700;">
          </div>

          <!-- Jumlah Pembayaran dengan Rp dan Titik Pemisah Ribuan Otomatis -->
          <div class="form-group">
            <label class="form-label">Jumlah Pembayaran (Rp) *</label>
            <input type="text" 
                   name="jumlah_bayar" 
                   id="input_jumlah_bayar" 
                   class="form-control" 
                   value="Rp <?= $formattedNominal ?>" 
                   oninput="formatRupiahInput(this)" 
                   style="font-weight: 700; color: var(--secondary);" 
                   required>
            <div class="form-text">Pokok: <?= rupiah($selectedPinjaman['angsuran_pokok']) ?> + Bunga: <?= rupiah($selectedPinjaman['angsuran_bunga']) ?></div>
          </div>

          <div class="form-group">
            <label class="form-label">Tanggal Bayar *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal_bayar" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required style="padding-right: 40px; font-weight: 600;">
              <button type="button" class="btn-date-picker" onclick="triggerDatePicker(this)" style="position: absolute; right: 4px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;" title="Buka Kalender">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>

          <!-- Form Keterangan (Dropdown Tunai, TF, Gaji, TPP - Boleh diisi / opsional) -->
          <div class="form-group">
            <label class="form-label">Keterangan</label>
            <select name="keterangan" class="form-select">
              <option value="">-- Pilih Keterangan (Opsional) --</option>
              <option value="Tunai">Tunai</option>
              <option value="TF">TF</option>
              <option value="Gaji">Gaji</option>
              <option value="TPP">TPP</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Proses Pembayaran Angsuran
          </button>
        <?php else: ?>
          <div style="background-color: #f8fafc; padding: 20px; text-align: center; border-radius: var(--radius-sm); color: var(--text-muted); font-size: 0.85rem;">
            Pilih atau cari salah satu pinjaman di atas untuk memuat rincian tagihan dan memproses pembayaran angsuran.
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Riwayat Pembayaran Angsuran -->
  <div class="card" style="margin-bottom: 0;">
    <div class="card-header">
      <h3 style="margin: 0; font-size: 1.05rem;">Riwayat Pembayaran Angsuran Masuk</h3>
      <input type="text" class="form-control" style="width: 240px; padding: 6px 10px; font-size: 0.82rem;" placeholder="Cari angsuran / keterangan..." data-table-search="tableAngsuran">
    </div>
    <div class="table-responsive">
      <table class="table" id="tableAngsuran">
        <thead>
          <tr>
            <th>Tgl Bayar</th>
            <th>No. Pinjaman</th>
            <th>Anggota</th>
            <th>Ke</th>
            <th>Total Bayar</th>
            <th>Pokok / Bunga</th>
            <th>Keterangan</th>
            <th style="width: 50px; text-align: center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($listAngsuran)): ?>
            <tr><td colspan="8" style="text-align:center; padding:32px; color:var(--text-subtle);">Belum ada pembayaran angsuran tercatat.</td></tr>
          <?php else: ?>
            <?php foreach ($listAngsuran as $a): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($a['tanggal_bayar'])) ?></td>
                <td><strong><?= htmlspecialchars($a['no_pinjaman']) ?></strong></td>
                <td><?= htmlspecialchars($a['nama_anggota']) ?></td>
                <td><span class="badge badge-info"><?= $a['angsuran_ke'] ?> / <?= $a['lama_bulan'] ?></span></td>
                <td class="table-amount text-green"><?= rupiah($a['jumlah_bayar']) ?></td>
                <td>
                  <small style="color:var(--text-muted);">
                    P: <?= rupiah($a['angsuran_pokok']) ?><br>
                    B: <?= rupiah($a['angsuran_bunga']) ?>
                  </small>
                </td>
                <!-- Kolom Keterangan Menggantikan Kolom Petugas -->
                <td>
                  <?php 
                    $ket = trim($a['keterangan'] ?? '');
                    if ($ket === 'Tunai'): ?>
                      <span class="badge badge-success" style="font-size:0.75rem; font-weight:700;">Tunai</span>
                  <?php elseif ($ket === 'TF'): ?>
                      <span class="badge badge-info" style="font-size:0.75rem; font-weight:700;">TF</span>
                  <?php elseif ($ket === 'Gaji'): ?>
                      <span class="badge" style="font-size:0.75rem; font-weight:700; background-color:#8b5cf6; color:#fff;">Gaji</span>
                  <?php elseif ($ket === 'TPP'): ?>
                      <span class="badge badge-warning" style="font-size:0.75rem; font-weight:700;">TPP</span>
                  <?php elseif ($ket !== ''): ?>
                      <span style="font-size:0.8rem; color:var(--secondary); font-weight:500;"><?= htmlspecialchars($ket) ?></span>
                  <?php else: ?>
                      <span style="color:var(--text-subtle);">-</span>
                  <?php endif; ?>
                </td>
                <td style="text-align: center;">
                  <button type="button" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fee2e2; padding: 4px 8px;" onclick="konfirmasiHapusAngsuran(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['no_pinjaman'])) ?>', <?= $a['angsuran_ke'] ?>, '<?= rupiah($a['jumlah_bayar']) ?>')" title="Hapus Angsuran">
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
</div>

<!-- Form Hapus Angsuran Tersembunyi -->
<form id="formHapusAngsuran" method="POST" action="angsuran.php" style="display:none;">
  <input type="hidden" name="action" value="hapus_angsuran">
  <input type="hidden" name="angsuran_id" id="hapus_angsuran_id">
</form>

<script>
// Format Otomatis Input Rupiah dengan Rp dan Titik Pemisah Ribuan
function formatRupiahInput(input) {
  let val = input.value.replace(/[^0-9]/g, '');
  if (val === '') {
    input.value = '';
    return;
  }
  let numberString = val.toString();
  let sisa = numberString.length % 3;
  let rupiah = numberString.substr(0, sisa);
  let ribuan = numberString.substr(sisa).match(/\d{3}/g);

  if (ribuan) {
    let separator = sisa ? '.' : '';
    rupiah += separator + ribuan.join('.');
  }
  input.value = 'Rp ' + rupiah;
}

// Interaktivitas Dropdown Pinjaman Berjalan dengan Fitur Pencarian Realtime
document.addEventListener('DOMContentLoaded', function() {
  const trigger = document.getElementById('pinjamanDropdownTrigger');
  const menu = document.getElementById('pinjamanDropdownMenu');
  const searchInput = document.getElementById('pinjamanSearchInput');
  const optionsList = document.getElementById('pinjamanOptionsList');
  const emptyNotice = document.getElementById('pinjamanEmptyNotice');
  const options = document.querySelectorAll('.pinjaman-option');

  if (!trigger || !menu) return;

  // Buka / Tutup Dropdown
  trigger.addEventListener('click', function(e) {
    e.stopPropagation();
    const isVisible = menu.style.display === 'block';
    if (isVisible) {
      menu.style.display = 'none';
    } else {
      menu.style.display = 'block';
      if (searchInput) {
        searchInput.value = '';
        filterOptions('');
        setTimeout(() => searchInput.focus(), 50);
      }
    }
  });

  // Filter Opsi Berdasarkan Pencarian
  if (searchInput) {
    searchInput.addEventListener('click', function(e) {
      e.stopPropagation();
    });

    searchInput.addEventListener('input', function() {
      filterOptions(this.value.trim().toLowerCase());
    });
  }

  function filterOptions(keyword) {
    let visibleCount = 0;
    options.forEach(opt => {
      const text = opt.getAttribute('data-text') || '';
      const id = opt.getAttribute('data-id');
      
      // Opsi batalkan pilihan selalu tampil jika keyword kosong
      if (id === '') {
        opt.style.display = keyword === '' ? 'block' : 'none';
        return;
      }

      if (keyword === '' || text.includes(keyword)) {
        opt.style.display = 'block';
        visibleCount++;
      } else {
        opt.style.display = 'none';
      }
    });

    if (emptyNotice) {
      emptyNotice.style.display = (visibleCount === 0 && keyword !== '') ? 'block' : 'none';
    }
  }

  // Pilih Opsi
  options.forEach(opt => {
    opt.addEventListener('click', function() {
      const pinjamanId = this.getAttribute('data-id');
      if (pinjamanId) {
        window.location.href = 'angsuran.php?pinjaman_id=' + pinjamanId;
      } else {
        window.location.href = 'angsuran.php';
      }
    });
  });

  // Tutup dropdown jika klik di luar
  document.addEventListener('click', function(e) {
    if (!trigger.contains(e.target) && !menu.contains(e.target)) {
      menu.style.display = 'none';
    }
  });
});

function konfirmasiHapusAngsuran(id, noPinj, angsKe, nominal) {
  if (confirm('Yakin ingin menghapus pembayaran angsuran ke-' + angsKe + ' sebesar ' + nominal + ' untuk pinjaman ' + noPinj + '? Saldo kewajiban pinjaman akan dikembalikan.')) {
    document.getElementById('hapus_angsuran_id').value = id;
    document.getElementById('formHapusAngsuran').submit();
  }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
