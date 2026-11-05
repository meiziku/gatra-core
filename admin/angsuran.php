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
    $rawPokok = str_replace(['Rp', '.', ' '], '', $_POST['angsuran_pokok'] ?? '0');
    $rawPokok = str_replace(',', '.', $rawPokok);
    $angsuran_pokok = (float)$rawPokok;

    $rawBunga = str_replace(['Rp', '.', ' '], '', $_POST['angsuran_bunga'] ?? '0');
    $rawBunga = str_replace(',', '.', $rawBunga);
    $angsuran_bunga = (float)$rawBunga;

    $rawBayar = str_replace(['Rp', '.', ' '], '', $_POST['jumlah_bayar'] ?? '0');
    $rawBayar = str_replace(',', '.', $rawBayar);
    $jumlah_bayar = (float)$rawBayar;

    // Jika jumlah_bayar belum terisi, otomatis jumlahkan pokok + bunga
    if ($jumlah_bayar <= 0) {
        $jumlah_bayar = $angsuran_pokok + $angsuran_bunga;
    }

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
    redirect('angsuran.php');
}

// 2. Handle Edit Pembayaran Angsuran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_angsuran') {
    $angsuran_id = (int)($_POST['angsuran_id'] ?? 0);
    $angsuran_ke = (int)($_POST['angsuran_ke'] ?? 1);

    $rawPokok = str_replace(['Rp', '.', ' '], '', $_POST['angsuran_pokok'] ?? '0');
    $rawPokok = str_replace(',', '.', $rawPokok);
    $angsuran_pokok = (float)$rawPokok;

    $rawBunga = str_replace(['Rp', '.', ' '], '', $_POST['angsuran_bunga'] ?? '0');
    $rawBunga = str_replace(',', '.', $rawBunga);
    $angsuran_bunga = (float)$rawBunga;

    $rawBayar = str_replace(['Rp', '.', ' '], '', $_POST['jumlah_bayar'] ?? '0');
    $rawBayar = str_replace(',', '.', $rawBayar);
    $jumlah_bayar = (float)$rawBayar;
    if ($jumlah_bayar <= 0) {
        $jumlah_bayar = $angsuran_pokok + $angsuran_bunga;
    }

    $tanggal_bayar = parse_dmy_to_ymd($_POST['tanggal_bayar'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($angsuran_id > 0 && $jumlah_bayar > 0) {
        $stmtCek = $db->prepare("SELECT pinjaman_id FROM angsuran WHERE id = ?");
        $stmtCek->execute([$angsuran_id]);
        $pinjId = $stmtCek->fetchColumn();

        $stmt = $db->prepare("UPDATE angsuran SET 
            angsuran_ke = ?, 
            jumlah_bayar = ?, 
            angsuran_pokok = ?, 
            angsuran_bunga = ?, 
            tanggal_bayar = ?, 
            keterangan = ? 
            WHERE id = ?");
        $stmt->execute([
            $angsuran_ke,
            $jumlah_bayar,
            $angsuran_pokok,
            $angsuran_bunga,
            $tanggal_bayar,
            $keterangan,
            $angsuran_id
        ]);

        // Cek status lunas / berjalan pinjaman
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

            if ($checkData && $checkData['total_dibayar'] >= $checkData['total_harus_dibayar']) {
                $db->prepare("UPDATE pinjaman SET status = 'lunas' WHERE id = ?")->execute([$pinjId]);
            } else {
                $db->prepare("UPDATE pinjaman SET status = 'berjalan' WHERE id = ?")->execute([$pinjId]);
            }
        }

        set_flash('success', "Data pembayaran angsuran ke-$angsuran_ke berhasil diperbarui.");
    } else {
        set_flash('error', 'Data angsuran tidak valid atau nominal belum diisi.');
    }
    redirect('angsuran.php');
}

// 3. Handle Hapus Pembayaran Angsuran
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
    redirect('angsuran.php');
}

// Ambil daftar pinjaman berjalan untuk form
$pinjamanBerjalan = $db->query("
    SELECT p.*, a.nama as nama_anggota, a.no_anggota,
           COALESCE(SUM(ag.jumlah_bayar), 0) as total_dibayar,
           COALESCE(SUM(ag.angsuran_pokok), 0) as pokok_dibayar,
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
.loan-modal-row-clickable {
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.loan-modal-row-clickable:hover {
  background-color: #f1f5f9 !important;
}
.loan-modal-row-clickable:hover td {
  background-color: #f1f5f9 !important;
}
</style>

<div style="display: grid; grid-template-columns: 400px 1fr; gap: 24px; align-items: flex-start;">
  <!-- Form Bayar Angsuran -->
  <div class="card" style="margin-bottom: 0; overflow: visible !important;">
    <div class="card-header" style="overflow: visible !important;">
      <h3 style="margin: 0; font-size: 1.05rem;">Form Pembayaran Angsuran</h3>
    </div>
    <div class="card-body" style="overflow: visible !important;">
      <form method="POST" action="angsuran.php" id="formBayarAngsuran">
        <input type="hidden" name="action" value="bayar_angsuran">

        <!-- Input Tersembunyi ID Pinjaman untuk Submit -->
        <input type="hidden" name="pinjaman_id" id="hidden_pinjaman_id" value="<?= $selectedPinjamanId ?>">

        <!-- Dropdown Pinjaman Berjalan dengan Fitur Pencarian -->
        <div class="form-group" style="position: relative; z-index: 100;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" style="margin-bottom: 0;">Pilih Pinjaman Berjalan *</label>
            <button type="button" class="btn btn-outline btn-sm" style="font-size: 0.74rem; padding: 2px 8px; color: var(--primary); border-color: var(--primary-border);" onclick="openModal('modalPilihPinjaman')">
              <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              Cari dari Tabel
            </button>
          </div>
          
          <!-- Tombol / Kotak Pemicu Dropdown -->
          <div id="pinjamanDropdownTrigger" class="form-control" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; min-height: 42px; padding: 8px 12px; background: #fff; user-select: none;">
            <span id="pinjamanTriggerText" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; <?= $selectedPinjaman ? 'font-weight: 600; color: var(--secondary);' : 'color: var(--text-muted);' ?>">
              <?php if ($selectedPinjaman): 
                $sisaSelPokok = max(0, (float)$selectedPinjaman['jumlah_pinjaman'] - (float)$selectedPinjaman['pokok_dibayar']);
              ?>
                <?= htmlspecialchars($selectedPinjaman['no_pinjaman']) ?> - <?= htmlspecialchars($selectedPinjaman['nama_anggota']) ?> (Sisa Pokok: <?= rupiah($sisaSelPokok) ?>)
              <?php else: ?>
                -- Klik untuk Memilih Pinjaman --
              <?php endif; ?>
            </span>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); flex-shrink: 0; margin-left: 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>

          <!-- Panel Menu Dropdown dengan Kotak Pencarian (Tidak Terpotong / Overflow Bebas) -->
          <div id="pinjamanDropdownMenu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; width: 440px; max-width: calc(100vw - 32px); background: #fff; border: 1.5px solid var(--border-color); border-radius: var(--radius-sm); box-shadow: 0 14px 35px -5px rgba(0,0,0,0.18), 0 8px 12px -3px rgba(0,0,0,0.1); z-index: 2000; overflow: hidden;">
            <!-- Kolom Pencarian di dalam dropdown -->
            <div style="padding: 10px 12px; border-bottom: 1px solid var(--border-color); background: #f8fafc;">
              <div style="position: relative; display: flex; align-items: center;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="position: absolute; left: 10px; color: var(--text-muted); pointer-events: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="pinjamanSearchInput" class="form-control form-control-sm" placeholder="Ketik nama anggota atau no. pinjaman..." style="padding-left: 32px; font-size: 0.85rem;" autocomplete="off">
              </div>
            </div>

            <!-- Daftar Opsi Pinjaman Lengkap & Nyaman Dilihat -->
            <div id="pinjamanOptionsList" style="max-height: 360px; overflow-y: auto;">
              <div class="pinjaman-option" data-id="" data-text="" style="color: var(--text-muted); font-size: 0.85rem; padding: 10px 14px;">
                &larr; Batalkan Pilihan Pinjaman
              </div>
              <?php foreach ($pinjamanBerjalan as $pb): 
                $sisaPokokItem = max(0, (float)$pb['jumlah_pinjaman'] - (float)$pb['pokok_dibayar']);
                $isSelected = ($selectedPinjamanId === $pb['id']);
                $searchKeyword = strtolower($pb['no_pinjaman'] . ' ' . $pb['nama_anggota'] . ' ' . $pb['no_anggota']);
              ?>
                <div class="pinjaman-option <?= $isSelected ? 'is-selected' : '' ?>" 
                     data-id="<?= $pb['id'] ?>"
                     data-text="<?= htmlspecialchars($searchKeyword) ?>"
                     style="padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer;">
                  <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                    <span style="font-weight: 700; color: var(--secondary); font-size: 0.88rem;">
                      <?= htmlspecialchars($pb['no_pinjaman']) ?> - <?= htmlspecialchars($pb['nama_anggota']) ?>
                    </span>
                    <span class="badge <?= $isSelected ? 'badge-primary' : 'badge-info' ?>" style="font-size: 0.76rem; font-weight: 700; white-space: nowrap;">
                      <?= $pb['kali_angsuran'] + 1 ?>/<?= $pb['lama_bulan'] ?>
                    </span>
                  </div>
                  <div style="display: flex; justify-content: space-between; font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">
                    <span>No. Angg: <strong><?= htmlspecialchars($pb['no_anggota']) ?></strong> &bull; Plafon: <?= rupiah($pb['jumlah_pinjaman']) ?></span>
                    <span>Sisa Pokok: <strong style="color: #dc2626;"><?= rupiah($sisaPokokItem) ?></strong></span>
                  </div>
                  <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--primary); margin-top: 2px;">
                    <span>Cicilan: <strong><?= rupiah($pb['total_angsuran_bulanan']) ?></strong> / bln</span>
                    <span style="color: var(--text-subtle);">Terbayar: <?= $pb['kali_angsuran'] ?>x</span>
                  </div>
                </div>
              <?php endforeach; ?>
              
              <div id="pinjamanEmptyNotice" style="display: none; padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                Tidak ada pinjaman berjalan yang cocok dengan pencarian.
              </div>
            </div>
          </div>
        </div>

        <?php if ($selectedPinjaman): 
          $nextAngsuran = $selectedPinjaman['kali_angsuran'] + 1;
          $sisaPokokSel = max(0, (float)$selectedPinjaman['jumlah_pinjaman'] - (float)$selectedPinjaman['pokok_dibayar']);
          $totalAngsuranBulanan = round($selectedPinjaman['total_angsuran_bulanan']);
          $formattedNominal = number_format($totalAngsuranBulanan, 0, ',', '.');
        ?>
          <div style="background-color: var(--primary-light); border: 1px solid var(--primary-border); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 16px; font-size: 0.85rem;">
            <div style="font-weight: 700; color: var(--primary-dark); margin-bottom: 6px;">Detail Pinjaman Terpilih:</div>
            <div>Plafon: <strong><?= rupiah($selectedPinjaman['jumlah_pinjaman']) ?></strong></div>
            <div>Tenor: <strong><?= $selectedPinjaman['lama_bulan'] ?> Bulan</strong> (Terbayar: <?= $selectedPinjaman['kali_angsuran'] ?>x)</div>
            <div>Sisa Pokok: <strong class="text-red"><?= rupiah($sisaPokokSel) ?></strong></div>
          </div>

          <input type="hidden" name="angsuran_ke" value="<?= $nextAngsuran ?>">

          <div class="form-group">
            <label class="form-label">Angsuran Ke-</label>
            <input type="text" class="form-control" value="Bulan ke-<?= $nextAngsuran ?> dari <?= $selectedPinjaman['lama_bulan'] ?> bulan" readonly style="background-color: #f8fafc; font-weight:700;">
          </div>

          <!-- Pemisahan Input Angsuran Pokok & Bunga -->
          <div class="form-row">
            <div class="form-col form-group">
              <label class="form-label">Angsuran Pokok (Rp) *</label>
              <input type="text" 
                     name="angsuran_pokok" 
                     id="input_angsuran_pokok" 
                     class="form-control" 
                     value="Rp <?= number_format($selectedPinjaman['angsuran_pokok'], 0, ',', '.') ?>" 
                     oninput="hitungTotalAngsuran(this)" 
                     style="font-weight: 700; color: var(--secondary);" 
                     required>
            </div>
            <div class="form-col form-group">
              <label class="form-label">Angsuran Bunga (Rp) *</label>
              <input type="text" 
                     name="angsuran_bunga" 
                     id="input_angsuran_bunga" 
                     class="form-control" 
                     value="Rp <?= number_format($selectedPinjaman['angsuran_bunga'], 0, ',', '.') ?>" 
                     oninput="hitungTotalAngsuran(this)" 
                     style="font-weight: 700; color: var(--secondary);" 
                     required>
            </div>
          </div>

          <!-- Total Pembayaran dengan Kalkulasi Otomatis -->
          <div class="form-group">
            <label class="form-label">Total Pembayaran (Rp) *</label>
            <input type="text" 
                   name="jumlah_bayar" 
                   id="input_jumlah_bayar" 
                   class="form-control" 
                   value="Rp <?= $formattedNominal ?>" 
                   readonly 
                   style="font-weight: 800; font-size: 1.05rem; color: var(--primary-dark); background-color: #ecfdf5; border-color: #a7f3d0;" 
                   required>
            <small style="color: var(--text-muted); font-size: 0.78rem;">Otomatis dihitung dari Angsuran Pokok + Bunga</small>
          </div>

          <div class="form-group">
            <label class="form-label">Tanggal Bayar *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" name="tanggal_bayar" class="form-control date-dmy-input" value="<?= date('d/m/Y') ?>" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required style="padding-right: 40px; font-weight: 600;">
              <button type="button" class="btn-date-picker" tabindex="-1" onclick="triggerDatePicker(this)" style="position: absolute; right: 4px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;" title="Buka Kalender">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" class="native-date-hidden" tabindex="-1" value="<?= date('Y-m-d') ?>" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
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
    <div class="table-freeze-container">
      <table class="table" id="tableAngsuran">
        <thead>
          <tr>
            <th>Tgl Bayar</th>
            <th>No. Pinjaman</th>
            <th>Anggota</th>
            <th style="text-align: center;">Ke</th>
            <th style="text-align: right;">Pokok (Rp)</th>
            <th style="text-align: right;">Jasa / Bunga (Rp)</th>
            <th style="text-align: right;">Total Bayar (Rp)</th>
            <th>Keterangan</th>
            <th style="width: 50px; text-align: center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($listAngsuran)): ?>
            <tr><td colspan="9" style="text-align:center; padding:32px; color:var(--text-subtle);">Belum ada pembayaran angsuran tercatat.</td></tr>
          <?php else: ?>
            <?php foreach ($listAngsuran as $a): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($a['tanggal_bayar'])) ?></td>
                <td><strong><?= htmlspecialchars($a['no_pinjaman']) ?></strong></td>
                <td><?= htmlspecialchars($a['nama_anggota']) ?></td>
                <td style="text-align: center;"><span class="badge badge-info"><?= $a['angsuran_ke'] ?> / <?= $a['lama_bulan'] ?></span></td>
                <td class="table-amount"><?= nominal($a['angsuran_pokok']) ?></td>
                <td class="table-amount"><?= nominal($a['angsuran_bunga']) ?></td>
                <td class="table-amount text-green" style="font-weight: 700;"><?= nominal($a['jumlah_bayar']) ?></td>
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
                <td style="text-align: center; white-space: nowrap;">
                  <div style="display: inline-flex; gap: 4px; align-items: center; justify-content: center;">
                    <button type="button" class="btn btn-outline btn-sm" style="color: var(--primary); border-color: #a7f3d0; padding: 4px 8px;" onclick='openEditAngsuran(<?= json_encode([
                      "id" => $a["id"],
                      "pinjaman_id" => $a["pinjaman_id"],
                      "no_pinjaman" => $a["no_pinjaman"],
                      "nama_anggota" => $a["nama_anggota"],
                      "no_anggota" => $a["no_anggota"],
                      "angsuran_ke" => $a["angsuran_ke"],
                      "lama_bulan" => $a["lama_bulan"],
                      "angsuran_pokok" => $a["angsuran_pokok"],
                      "angsuran_pokok_formatted" => number_format((float)$a["angsuran_pokok"], 0, ',', '.'),
                      "angsuran_bunga" => $a["angsuran_bunga"],
                      "angsuran_bunga_formatted" => number_format((float)$a["angsuran_bunga"], 0, ',', '.'),
                      "jumlah_bayar" => $a["jumlah_bayar"],
                      "jumlah_bayar_formatted" => number_format((float)$a["jumlah_bayar"], 0, ',', '.'),
                      "tanggal_bayar" => date("d/m/Y", strtotime($a["tanggal_bayar"])),
                      "tanggal_bayar_ymd" => $a["tanggal_bayar"],
                      "keterangan" => $a["keterangan"] ?? ""
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Angsuran">
                      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fee2e2; padding: 4px 8px;" onclick="openHapusAngsuran(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['no_pinjaman'])) ?>', <?= $a['angsuran_ke'] ?>, '<?= rupiah($a['jumlah_bayar']) ?>')" title="Hapus Angsuran">
                      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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
</div>

<!-- Modal Pilih Pinjaman Berjalan (Pencarian Tabel Luas & Nyaman) -->
<div class="modal-overlay" id="modalPilihPinjaman">
  <div class="modal-card" style="max-width: 920px; width: 95%;">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #ecfdf5; color: var(--primary);">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </span>
        <div>
          <h3 style="margin: 0; font-size: 1.1rem; color: var(--secondary);">Pilih Pinjaman Berjalan</h3>
          <small style="color: var(--text-muted);">Klik pada baris manapun untuk memilih pinjaman yang akan dibayarkan angsurannya</small>
        </div>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalPilihPinjaman')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 16px 20px;">
      <div style="margin-bottom: 14px;">
        <input type="text" class="form-control" placeholder="Ketik nama anggota, no. pinjaman, atau no. anggota..." data-table-search="tablePilihPinjamanModal" autofocus style="padding: 9px 14px; font-size: 0.9rem;">
      </div>
      <div class="table-freeze-container" style="max-height: 420px; overflow-y: auto; overflow-x: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
        <table class="table" id="tablePilihPinjamanModal" style="width: 100%; margin-bottom: 0;">
          <thead>
            <tr>
              <th style="width: 130px;">No. Pinjaman</th>
              <th>Anggota</th>
              <th style="width: 180px; text-align: right;">Plafon & Tenor (Rp)</th>
              <th style="width: 170px; text-align: right;">Cicilan / Bln (Rp)</th>
              <th style="width: 140px; text-align: right;">Sisa Pokok (Rp)</th>
              <th style="width: 100px; text-align: center;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pinjamanBerjalan)): ?>
              <tr><td colspan="6" style="text-align: center; padding: 28px; color: var(--text-subtle);">Tidak ada pinjaman berjalan yang aktif.</td></tr>
            <?php else: ?>
              <?php foreach ($pinjamanBerjalan as $pb): 
                $sisaPokokPB = max(0, (float)$pb['jumlah_pinjaman'] - (float)$pb['pokok_dibayar']);
                $angsuranKe = (int)$pb['kali_angsuran'] + 1;
              ?>
                <tr class="loan-modal-row-clickable" onclick="window.location.href='angsuran.php?pinjaman_id=<?= $pb['id'] ?>'" title="Klik untuk memilih pinjaman <?= htmlspecialchars($pb['no_pinjaman']) ?> - <?= htmlspecialchars($pb['nama_anggota']) ?>">
                  <td><strong><?= htmlspecialchars($pb['no_pinjaman']) ?></strong></td>
                  <td>
                    <div style="font-weight: 700; color: var(--secondary);"><?= htmlspecialchars($pb['nama_anggota']) ?></div>
                    <small style="color: var(--text-muted);"><?= htmlspecialchars($pb['no_anggota']) ?></small>
                  </td>
                  <td class="table-amount">
                    <div><?= nominal($pb['jumlah_pinjaman']) ?></div>
                    <small style="color: var(--text-muted); display: block; text-align: right;"><?= $pb['lama_bulan'] ?> Bulan (<?= $pb['bunga_persen'] ?>%/bln)</small>
                  </td>
                  <td class="table-amount" style="white-space: nowrap;">
                    <div class="text-blue" style="font-weight: 700;"><?= nominal($pb['total_angsuran_bulanan']) ?></div>
                    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 2px; line-height: 1.35; text-align: right;">
                      Pokok: <strong><?= nominal($pb['angsuran_pokok']) ?></strong><br>
                      Jasa: <strong><?= nominal($pb['angsuran_bunga']) ?></strong>
                    </div>
                  </td>
                  <td class="table-amount text-red" style="font-weight: 700; text-align: right;">
                    <?= nominal($sisaPokokPB) ?>
                  </td>
                  <td style="text-align: center;">
                    <span class="badge badge-info" style="font-size: 0.85rem; font-weight: 700; padding: 4px 8px;">
                      <?= $angsuranKe ?>/<?= $pb['lama_bulan'] ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer" style="padding: 12px 20px;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modalPilihPinjaman')">Tutup</button>
    </div>
  </div>
</div>

<!-- Modal Edit Angsuran -->
<div class="modal-overlay" id="modalEditAngsuran">
  <div class="modal-card" style="max-width: 540px;">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: #ecfdf5; color: var(--primary);">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </span>
        <h3 style="margin: 0;">Edit Pembayaran Angsuran</h3>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalEditAngsuran')">&times;</button>
    </div>
    <form method="POST" action="angsuran.php">
      <input type="hidden" name="action" value="edit_angsuran">
      <input type="hidden" name="angsuran_id" id="edit_angsuran_id">
      <div class="modal-body">
        
        <!-- Info Pinjaman & Anggota (Readonly) -->
        <div class="form-group">
          <label class="form-label">Data Pinjaman</label>
          <div style="background: #f8fafc; border: 1.5px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
            <div>
              <div id="edit_display_nama_anggota" style="font-weight: 700; color: var(--secondary); font-size: 0.95rem;">-</div>
              <div id="edit_display_no_anggota" style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">No. Anggota: -</div>
            </div>
            <div id="edit_display_no_pinjaman" class="badge badge-info" style="font-size: 0.85rem; font-weight: 700;">-</div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Angsuran Ke- *</label>
            <input type="number" id="edit_angsuran_ke" name="angsuran_ke" class="form-control" min="1" max="120" required style="font-weight: 700;">
          </div>
          <div class="form-col form-group">
            <label class="form-label">Tanggal Bayar *</label>
            <div class="input-date-dmy" style="position: relative; display: flex; align-items: center;">
              <input type="text" id="edit_tanggal_bayar" name="tanggal_bayar" class="form-control date-dmy-input" placeholder="dd/mm/yyyy" maxlength="10" oninput="formatDmyInput(this)" required style="padding-right: 40px; font-weight: 600;">
              <button type="button" class="btn-date-picker" tabindex="-1" onclick="triggerDatePicker(this)" style="position: absolute; right: 4px; background: none; border: none; padding: 6px 8px; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;" title="Buka Kalender">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </button>
              <input type="date" id="edit_tanggal_bayar_native" class="native-date-hidden" tabindex="-1" onchange="syncNativeToDmy(this)" style="position: absolute; right: 0; bottom: 0; width: 0; height: 0; opacity: 0; border: none; pointer-events: none;">
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Angsuran Pokok (Rp) *</label>
            <input type="text" id="edit_angsuran_pokok" name="angsuran_pokok" class="form-control" placeholder="Rp 0" oninput="formatRupiahInput(this); hitungTotalEditAngsuran();" required style="font-weight: 700;">
          </div>
          <div class="form-col form-group">
            <label class="form-label">Angsuran Jasa / Bunga (Rp) *</label>
            <input type="text" id="edit_angsuran_bunga" name="angsuran_bunga" class="form-control" placeholder="Rp 0" oninput="formatRupiahInput(this); hitungTotalEditAngsuran();" required style="font-weight: 700;">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Total Pembayaran (Rp) *</label>
          <input type="text" id="edit_jumlah_bayar" name="jumlah_bayar" class="form-control" placeholder="Rp 0" oninput="formatRupiahInput(this)" required readonly style="font-weight: 700; color: var(--primary); background: #f8fafc;">
          <small style="color: var(--text-muted); font-size: 0.78rem;">Otomatis dihitung dari Pokok + Jasa / Bunga</small>
        </div>

        <div class="form-group">
          <label class="form-label">Keterangan / Metode *</label>
          <select name="keterangan" id="edit_keterangan" class="form-control">
            <option value="Tunai">Tunai</option>
            <option value="TF">Transfer Bank (TF)</option>
            <option value="Gaji">Potong Gaji</option>
            <option value="TPP">Potong TPP</option>
            <option value="Lainnya">Lainnya</option>
          </select>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditAngsuran')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Konfirmasi Hapus Angsuran (Rapi & Modern) -->
<div class="modal-overlay" id="modalHapusAngsuran">
  <div class="modal-card" style="max-width: 460px;">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #fee2e2; color: var(--danger);">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </span>
        <h3 style="margin: 0; color: var(--danger); font-size: 1.1rem;">Hapus Pembayaran Angsuran</h3>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalHapusAngsuran')">&times;</button>
    </div>
    <form method="POST" action="angsuran.php">
      <input type="hidden" name="action" value="hapus_angsuran">
      <input type="hidden" name="angsuran_id" id="hapus_angsuran_id">
      <div class="modal-body">
        <p style="font-size: 0.95rem; color: var(--secondary); margin-bottom: 12px; line-height: 1.5;">
          Yakin ingin menghapus pembayaran angsuran ke-<strong id="hapus_angsuran_ke"></strong> sebesar <strong id="hapus_angsuran_nominal" style="color: var(--danger);"></strong> untuk pinjaman <strong id="hapus_angsuran_pinjaman"></strong>?
        </p>
        <div style="background-color: #fff7ed; border: 1px solid #ffedd5; border-radius: var(--radius-sm); padding: 12px; font-size: 0.82rem; color: #9a3412; line-height: 1.5;">
          ℹ️ Saldo sisa kewajiban pinjaman anggota akan otomatis dikembalikan / disesuaikan.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalHapusAngsuran')">Batal</button>
        <button type="submit" class="btn btn-danger">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          Ya, Hapus Angsuran
        </button>
      </div>
    </form>
  </div>
</div>

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

// Hitung Otomatis Total Pembayaran Angsuran = Pokok + Bunga
function hitungTotalAngsuran(el) {
  if (el) formatRupiahInput(el);

  const parseVal = (id) => {
    const input = document.getElementById(id);
    if (!input) return 0;
    const clean = input.value.replace(/[^0-9]/g, '');
    return clean ? parseInt(clean, 10) : 0;
  };

  const pokok = parseVal('input_angsuran_pokok');
  const bunga = parseVal('input_angsuran_bunga');
  const total = pokok + bunga;

  const totalInput = document.getElementById('input_jumlah_bayar');
  if (totalInput) {
    let s = total.toString();
    let sisa = s.length % 3;
    let r = s.substr(0, sisa);
    let ribuan = s.substr(sisa).match(/\d{3}/g);
    if (ribuan) {
      let sep = sisa ? '.' : '';
      r += sep + ribuan.join('.');
    }
    totalInput.value = 'Rp ' + r;
  }
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

    if (typeof initSearchDropdownKeyNav === 'function') {
      initSearchDropdownKeyNav({
        input: searchInput,
        list: document.getElementById('pinjamanOptionsList'),
        itemSelector: '.pinjaman-option',
        onEscape: () => { menu.style.display = 'none'; }
      });
    }
  }

  const modalSearchInput = document.querySelector('input[data-table-search="tablePilihPinjamanModal"]');
  if (modalSearchInput && typeof initSearchDropdownKeyNav === 'function') {
    initSearchDropdownKeyNav({
      input: modalSearchInput,
      list: document.querySelector('#tablePilihPinjamanModal tbody'),
      itemSelector: 'tr.loan-modal-row-clickable',
      onEscape: () => { closeModal('modalPilihPinjaman'); }
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

// Buka Modal Edit Angsuran Modern
function openEditAngsuran(d) {
  document.getElementById('edit_angsuran_id').value = d.id;
  document.getElementById('edit_display_nama_anggota').textContent = d.nama_anggota;
  document.getElementById('edit_display_no_anggota').textContent = 'No. Anggota: ' + (d.no_anggota || '-');
  document.getElementById('edit_display_no_pinjaman').textContent = d.no_pinjaman;
  document.getElementById('edit_angsuran_ke').value = d.angsuran_ke;
  document.getElementById('edit_angsuran_pokok').value = 'Rp ' + d.angsuran_pokok_formatted;
  document.getElementById('edit_angsuran_bunga').value = 'Rp ' + d.angsuran_bunga_formatted;
  document.getElementById('edit_jumlah_bayar').value = 'Rp ' + d.jumlah_bayar_formatted;
  document.getElementById('edit_tanggal_bayar').value = d.tanggal_bayar;
  if (d.tanggal_bayar_ymd) {
    document.getElementById('edit_tanggal_bayar_native').value = d.tanggal_bayar_ymd;
  }
  
  let selKet = document.getElementById('edit_keterangan');
  if (selKet) {
    let matched = false;
    for (let i = 0; i < selKet.options.length; i++) {
      if (selKet.options[i].value === d.keterangan) {
        selKet.selectedIndex = i;
        matched = true;
        break;
      }
    }
    if (!matched && d.keterangan) {
      let opt = new Option(d.keterangan, d.keterangan);
      selKet.add(opt);
      selKet.value = d.keterangan;
    }
  }

  openModal('modalEditAngsuran');
}

// Hitung Otomatis Total Pembayaran pada Modal Edit (Pokok + Bunga)
function hitungTotalEditAngsuran() {
  let elPokok = document.getElementById('edit_angsuran_pokok');
  let elBunga = document.getElementById('edit_angsuran_bunga');
  let elTotal = document.getElementById('edit_jumlah_bayar');
  if (!elPokok || !elBunga || !elTotal) return;

  let pokok = parseFloat(elPokok.value.replace(/[^0-9]/g, '')) || 0;
  let bunga = parseFloat(elBunga.value.replace(/[^0-9]/g, '')) || 0;
  let total = pokok + bunga;

  elTotal.value = 'Rp ' + total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Buka Modal Hapus Angsuran Modern
function openHapusAngsuran(id, noPinj, angsKe, nominal) {
  document.getElementById('hapus_angsuran_id').value = id;
  document.getElementById('hapus_angsuran_pinjaman').textContent = noPinj;
  document.getElementById('hapus_angsuran_ke').textContent = angsKe;
  document.getElementById('hapus_angsuran_nominal').textContent = nominal;
  openModal('modalHapusAngsuran');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
