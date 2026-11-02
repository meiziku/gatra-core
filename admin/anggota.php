<?php
/**
 * Manajemen Data Anggota - Admin Koperasi Gatra Teknika
 */
$pageTitle = 'Data Anggota';
$pageSubtitle = 'Kelola data identitas anggota, status keanggotaan, dan akun akses';
$activePage = 'anggota';

require_once __DIR__ . '/header.php';

// 1. Handle Tambah Anggota Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_anggota') {
    $nama = trim($_POST['nama'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $pekerjaan = in_array($_POST['pekerjaan'] ?? '', ['ASN', 'NON ASN']) ? $_POST['pekerjaan'] : 'NON ASN';
    $tanggal_bergabung = !empty($_POST['tanggal_bergabung']) ? $_POST['tanggal_bergabung'] : date('Y-m-d');
    
    // Akun Login Default
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $nama)[0])) . rand(10, 99);
    $password_raw = 'anggota123';
    $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);

    if (!empty($nama)) {
        // Generate No. Anggota baru
        $count = $db->query("SELECT COUNT(*) FROM anggota")->fetchColumn() + 1;
        $no_anggota = 'KOP-' . date('Y') . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

        // 1. Buat User Login
        $stmtUser = $db->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'anggota', ?)");
        $stmtUser->execute([$username, $password_hash, $nama]);
        $userId = $db->lastInsertId();

        // 2. Buat Record Anggota (Simpanan pokok awal ditiadakan sesuai ketentuan baru)
        $stmtAnggota = $db->prepare("INSERT INTO anggota (user_id, no_anggota, nama, nik, alamat, no_telp, pekerjaan, tanggal_bergabung, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')");
        $stmtAnggota->execute([$userId, $no_anggota, $nama, $nik, $alamat, $no_telp, $pekerjaan, $tanggal_bergabung]);

        set_flash('success', "Anggota baru <strong>$nama ($no_anggota)</strong> berhasil didaftarkan! Pekerjaan: <strong>$pekerjaan</strong>, Username: <strong>$username</strong>, Password Default: <strong>$password_raw</strong>");
        header('Location: anggota.php');
        exit;
    } else {
        set_flash('error', 'Nama anggota wajib diisi.');
    }
}

// 2. Handle Edit Anggota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_anggota') {
    $anggotaId = (int)($_POST['anggota_id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $no_telp = trim($_POST['no_telp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $pekerjaan = in_array($_POST['pekerjaan'] ?? '', ['ASN', 'NON ASN']) ? $_POST['pekerjaan'] : 'NON ASN';
    $tanggal_bergabung = !empty($_POST['tanggal_bergabung']) ? $_POST['tanggal_bergabung'] : date('Y-m-d');
    $status = ($_POST['status'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $tanggal_keluar = ($status === 'nonaktif') ? (!empty($_POST['tanggal_keluar']) ? $_POST['tanggal_keluar'] : date('Y-m-d')) : null;

    if ($anggotaId > 0 && !empty($nama)) {
        $stmt = $db->prepare("UPDATE anggota SET nama = ?, nik = ?, no_telp = ?, alamat = ?, pekerjaan = ?, tanggal_bergabung = ?, status = ?, tanggal_keluar = ? WHERE id = ?");
        $stmt->execute([$nama, $nik, $no_telp, $alamat, $pekerjaan, $tanggal_bergabung, $status, $tanggal_keluar, $anggotaId]);

        // Sinkronkan nama di tabel users
        $stmtUser = $db->prepare("SELECT user_id FROM anggota WHERE id = ?");
        $stmtUser->execute([$anggotaId]);
        $uId = $stmtUser->fetchColumn();
        if ($uId) {
            $db->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?")->execute([$nama, $uId]);
        }

        set_flash('success', "Data anggota <strong>$nama</strong> berhasil diperbarui.");
        header('Location: anggota.php');
        exit;
    } else {
        set_flash('error', 'Data tidak valid atau nama anggota kosong.');
    }
}

// 3. Handle Reset Password ke Default (anggota123)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $anggotaId = (int)($_POST['anggota_id'] ?? 0);
    
    $stmt = $db->prepare("SELECT a.id, a.nama, a.user_id, u.username FROM anggota a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$anggotaId]);
    $ag = $stmt->fetch();

    if ($ag) {
        $defaultPass = 'anggota123';
        $hashed = password_hash($defaultPass, PASSWORD_DEFAULT);

        if (!empty($ag['user_id'])) {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $ag['user_id']]);
        } else {
            // Jika belum punya akun user, buatkan akun baru
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $ag['nama'])[0])) . rand(10, 99);
            $stmtNewUser = $db->prepare("INSERT INTO users (username, password, role, nama_lengkap) VALUES (?, ?, 'anggota', ?)");
            $stmtNewUser->execute([$username, $hashed, $ag['nama']]);
            $newUid = $db->lastInsertId();
            $db->prepare("UPDATE anggota SET user_id = ? WHERE id = ?")->execute([$newUid, $anggotaId]);
            $ag['username'] = $username;
        }

        set_flash('success', "Kata sandi untuk <strong>" . htmlspecialchars($ag['nama']) . "</strong> (Username: <strong>" . htmlspecialchars($ag['username']) . "</strong>) berhasil direset ke default: <code>$defaultPass</code>");
        header('Location: anggota.php');
        exit;
    } else {
        set_flash('error', 'Anggota tidak ditemukan.');
    }
}

// 4. Handle Ubah Status Aktif / Tidak Aktif (Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $anggotaId = (int)($_POST['anggota_id'] ?? 0);
    $targetStatus = ($_POST['target_status'] ?? '') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $tanggalKeluar = ($targetStatus === 'nonaktif') ? (!empty($_POST['tanggal_keluar']) ? $_POST['tanggal_keluar'] : date('Y-m-d')) : null;

    $stmt = $db->prepare("SELECT nama FROM anggota WHERE id = ?");
    $stmt->execute([$anggotaId]);
    $nama = $stmt->fetchColumn();

    if ($nama) {
        $db->prepare("UPDATE anggota SET status = ?, tanggal_keluar = ? WHERE id = ?")->execute([$targetStatus, $tanggalKeluar, $anggotaId]);
        $statusText = $targetStatus === 'aktif' ? '<span class="text-green">Aktif</span>' : '<span class="text-red">Tidak Aktif</span>';
        set_flash('success', "Status anggota <strong>" . htmlspecialchars($nama) . "</strong> berhasil diubah menjadi: $statusText");
        header('Location: anggota.php');
        exit;
    } else {
        set_flash('error', 'Anggota tidak ditemukan.');
    }
}

// 5. Handle Hapus Anggota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus_anggota') {
    $anggotaId = (int)($_POST['anggota_id'] ?? 0);

    // Cek apakah ada pinjaman berjalan
    $stmtCek = $db->prepare("SELECT COUNT(*) FROM pinjaman WHERE anggota_id = ? AND status = 'berjalan'");
    $stmtCek->execute([$anggotaId]);
    $hasActiveLoan = $stmtCek->fetchColumn() > 0;

    if ($hasActiveLoan) {
        set_flash('error', 'Anggota tidak dapat dihapus karena masih memiliki pinjaman yang sedang berjalan. Lunaskan pinjaman terlebih dahulu.');
    } else {
        $stmtAg = $db->prepare("SELECT user_id, nama FROM anggota WHERE id = ?");
        $stmtAg->execute([$anggotaId]);
        $ag = $stmtAg->fetch();

        if ($ag) {
            $db->prepare("DELETE FROM anggota WHERE id = ?")->execute([$anggotaId]);
            if (!empty($ag['user_id'])) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$ag['user_id']]);
            }
            set_flash('success', "Data anggota <strong>" . htmlspecialchars($ag['nama']) . "</strong> berhasil dihapus dari sistem.");
        }
    }
    header('Location: anggota.php');
    exit;
}

// Sorting Parameter
$allowedSort = [
    'no_anggota'        => 'a.no_anggota',
    'nama'              => 'a.nama',
    'tanggal_bergabung' => 'a.tanggal_bergabung',
    'id'                => 'a.id'
];
$sort = $_GET['sort'] ?? 'id';
if (!array_key_exists($sort, $allowedSort)) {
    $sort = 'id';
}
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$orderBy = $allowedSort[$sort] . ' ' . $order;

// Ambil daftar seluruh anggota dengan sorting
$listAnggota = $db->query("
    SELECT a.*, u.username
    FROM anggota a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY $orderBy
")->fetchAll();
?>

<div class="card">
  <div class="card-header" style="flex-wrap: wrap; gap: 14px;">
    <div>
      <h3 style="margin-bottom: 2px;">Direktori Anggota Koperasi</h3>
      <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Total terdaftar: <strong><?= count($listAnggota) ?> Anggota</strong> (Semua Data dalam 1 Halaman)</p>
    </div>
    
    <div class="card-header-actions" style="flex-wrap: wrap; gap: 10px;">
      <!-- Dropdown Urutkan Cepat -->
      <div style="display: flex; align-items: center; gap: 6px;">
        <label for="sortSelector" style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600; white-space: nowrap;">Urutkan:</label>
        <select id="sortSelector" class="form-control" style="padding: 6px 10px; font-size: 0.82rem; width: auto;" onchange="window.location.href=this.value">
          <option value="anggota.php?sort=id&order=desc" <?= ($sort === 'id') ? 'selected' : '' ?>>Terbaru Ditambahkan</option>
          <option value="anggota.php?sort=no_anggota&order=asc" <?= ($sort === 'no_anggota' && $order === 'ASC') ? 'selected' : '' ?>>No. Anggota (A &rarr; Z)</option>
          <option value="anggota.php?sort=no_anggota&order=desc" <?= ($sort === 'no_anggota' && $order === 'DESC') ? 'selected' : '' ?>>No. Anggota (Z &rarr; A)</option>
          <option value="anggota.php?sort=nama&order=asc" <?= ($sort === 'nama' && $order === 'ASC') ? 'selected' : '' ?>>Nama Anggota (A &rarr; Z)</option>
          <option value="anggota.php?sort=nama&order=desc" <?= ($sort === 'nama' && $order === 'DESC') ? 'selected' : '' ?>>Nama Anggota (Z &rarr; A)</option>
          <option value="anggota.php?sort=tanggal_bergabung&order=desc" <?= ($sort === 'tanggal_bergabung' && $order === 'DESC') ? 'selected' : '' ?>>Tgl Bergabung (Terbaru)</option>
          <option value="anggota.php?sort=tanggal_bergabung&order=asc" <?= ($sort === 'tanggal_bergabung' && $order === 'ASC') ? 'selected' : '' ?>>Tgl Bergabung (Terlama)</option>
        </select>
      </div>

      <!-- Live Search Filter -->
      <input type="text" class="form-control" style="width: 220px; padding: 6px 12px; font-size: 0.85rem;" placeholder="Cari nama, NIK, No. Anggota..." data-table-search="tableAnggota">
      
      <!-- Tombol Tambah Anggota -->
      <button type="button" class="btn btn-primary" onclick="openModal('modalTambahAnggota')">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        Tambah Anggota Baru
      </button>
    </div>
  </div>

  <!-- Container Freeze Header saat Scroll ke Bawah -->
  <div class="table-freeze-container">
    <table class="table" id="tableAnggota">
      <thead>
        <tr>
          <!-- Kolom No. Anggota dengan Klik Sort -->
          <th style="cursor: pointer; user-select: none;" onclick="window.location.href='anggota.php?sort=no_anggota&order=<?= ($sort === 'no_anggota' && $order === 'ASC') ? 'desc' : 'asc' ?>'" title="Klik untuk mengurutkan berdasarkan No. Anggota">
            <div style="display:flex; align-items:center; gap:4px;">
              No. Anggota
              <span style="font-size:0.75rem; color:<?= $sort === 'no_anggota' ? 'var(--primary)' : 'var(--text-subtle)' ?>;">
                <?= $sort === 'no_anggota' ? ($order === 'ASC' ? '▲' : '▼') : '↕' ?>
              </span>
            </div>
          </th>

          <!-- Kolom Nama & NIK dengan Klik Sort -->
          <th style="cursor: pointer; user-select: none;" onclick="window.location.href='anggota.php?sort=nama&order=<?= ($sort === 'nama' && $order === 'ASC') ? 'desc' : 'asc' ?>'" title="Klik untuk mengurutkan berdasarkan Nama">
            <div style="display:flex; align-items:center; gap:4px;">
              Nama & NIK
              <span style="font-size:0.75rem; color:<?= $sort === 'nama' ? 'var(--primary)' : 'var(--text-subtle)' ?>;">
                <?= $sort === 'nama' ? ($order === 'ASC' ? '▲' : '▼') : '↕' ?>
              </span>
            </div>
          </th>

          <th>Pekerjaan</th>
          <th>Kontak & Alamat</th>

          <!-- Kolom Tanggal Bergabung dengan Klik Sort -->
          <th style="cursor: pointer; user-select: none;" onclick="window.location.href='anggota.php?sort=tanggal_bergabung&order=<?= ($sort === 'tanggal_bergabung' && $order === 'ASC') ? 'desc' : 'asc' ?>'" title="Klik untuk mengurutkan berdasarkan Tanggal Bergabung">
            <div style="display:flex; align-items:center; gap:4px;">
              Tgl Bergabung
              <span style="font-size:0.75rem; color:<?= $sort === 'tanggal_bergabung' ? 'var(--primary)' : 'var(--text-subtle)' ?>;">
                <?= $sort === 'tanggal_bergabung' ? ($order === 'ASC' ? '▲' : '▼') : '↕' ?>
              </span>
            </div>
          </th>

          <th>Username</th>
          <th style="min-width: 190px;">Status & Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listAnggota)): ?>
          <tr><td colspan="7" style="text-align:center; padding:32px; color:var(--text-subtle);">Belum ada data anggota.</td></tr>
        <?php else: ?>
          <?php foreach ($listAnggota as $ag): ?>
            <tr>
              <td>
                <span class="badge badge-info" style="font-size:0.8rem;"><?= htmlspecialchars($ag['no_anggota']) ?></span>
              </td>
              <td>
                <div style="font-weight: 700; color: var(--secondary);"><?= htmlspecialchars($ag['nama']) ?></div>
                <small style="color:var(--text-subtle);">NIK: <?= htmlspecialchars($ag['nik'] ?: '-') ?></small>
              </td>
              <td>
                <span class="badge <?= ($ag['pekerjaan'] === 'ASN') ? 'badge-primary' : 'badge-neutral' ?>" style="font-size:0.75rem; <?= ($ag['pekerjaan'] === 'ASN') ? 'background: #dbeafe; color: #1e40af;' : '' ?>">
                  <?= htmlspecialchars($ag['pekerjaan'] ?: 'NON ASN') ?>
                </span>
              </td>
              <td>
                <div><?= htmlspecialchars($ag['no_telp'] ?: '-') ?></div>
                <small style="color:var(--text-muted);"><?= htmlspecialchars($ag['alamat'] ?: '-') ?></small>
              </td>
              <td><?= date('d/m/Y', strtotime($ag['tanggal_bergabung'])) ?></td>
              <td>
                <span style="font-family: monospace; font-size:0.85rem; background:#f1f5f9; padding:2px 6px; border-radius:4px;">
                  <?= htmlspecialchars($ag['username'] ?: '-') ?>
                </span>
              </td>
              
              <!-- Kolom Status & Aksi Gabungan (Icon Only) -->
              <td>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                  
                  <!-- Tampilan Status & Informasi Tanggal Keluar -->
                  <div>
                    <?php if ($ag['status'] === 'aktif'): ?>
                      <span class="badge badge-success" style="font-size: 0.76rem;">
                        <svg width="8" height="8" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                        Aktif
                      </span>
                    <?php else: ?>
                      <span class="badge badge-danger" style="font-size: 0.76rem;">
                        <svg width="8" height="8" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                        Tidak Aktif
                      </span>
                      <!-- Tulisan warna merah sebagai informasi tanggal keluarnya -->
                      <div style="color: #dc2626; font-size: 0.75rem; font-weight: 700; margin-top: 4px; display: flex; align-items: center; gap: 3px;">
                        <span>🚪 Keluar:</span>
                        <span><?= !empty($ag['tanggal_keluar']) ? tgl_indo($ag['tanggal_keluar']) : '-' ?></span>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Tombol Aksi Icon Only (Edit, Reset Password, Nonaktif/Aktif, Hapus) -->
                  <div style="display: flex; gap: 6px; align-items: center;">
                    
                    <!-- 1. Tombol Edit (Icon Only) -->
                    <button type="button" class="btn btn-secondary btn-icon" onclick='openEditModal(<?= htmlspecialchars(json_encode($ag), ENT_QUOTES, 'UTF-8') ?>)' title="Edit Profil Anggota">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>

                    <!-- 2. Tombol Reset Password (Modal Putih di Tengah) -->
                    <button type="button" class="btn btn-secondary btn-icon" onclick="openResetModal(<?= $ag['id'] ?>, '<?= htmlspecialchars(addslashes($ag['nama'])) ?>')" title="Reset Kata Sandi ke Default (anggota123)">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    </button>

                    <!-- 3. Tombol Status Nonaktif / Aktif (Modal Putih di Tengah) -->
                    <?php if ($ag['status'] === 'aktif'): ?>
                      <button type="button" class="btn btn-warning btn-icon" onclick="openNonaktifModal(<?= $ag['id'] ?>, '<?= htmlspecialchars(addslashes($ag['nama'])) ?>')" title="Ubah Status Menjadi Tidak Aktif">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-outline-primary btn-icon" onclick="openAktifkanModal(<?= $ag['id'] ?>, '<?= htmlspecialchars(addslashes($ag['nama'])) ?>')" title="Aktifkan Kembali Keanggotaan">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      </button>
                    <?php endif; ?>

                    <!-- 4. Tombol Hapus (Modal Putih di Tengah) -->
                    <button type="button" class="btn btn-danger btn-icon" onclick="openHapusModal(<?= $ag['id'] ?>, '<?= htmlspecialchars(addslashes($ag['nama'])) ?>')" title="Hapus Data Anggota">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>

                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah Anggota Baru -->
<div class="modal-overlay" id="modalTambahAnggota">
  <div class="modal-card" style="max-width: 580px;">
    <div class="modal-header">
      <h3>Pendaftaran Anggota Koperasi Baru</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalTambahAnggota')">&times;</button>
    </div>
    <form method="POST" action="anggota.php">
      <input type="hidden" name="action" value="tambah_anggota">
      <div class="modal-body">
        
        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="nama" class="form-control" placeholder="Contoh: Ahmad Fauzi" required>
          </div>
          <div class="form-col form-group">
            <label class="form-label">NIK (Nomor Induk Kependudukan)</label>
            <input type="text" name="nik" class="form-control" placeholder="16 digit NIK">
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">No. Telepon / WhatsApp</label>
            <input type="text" name="no_telp" class="form-control" placeholder="Contoh: 08123456789">
          </div>
          <div class="form-col form-group">
            <!-- Kolom pekerjaan berbentuk dropdown (ASN atau NON ASN) -->
            <label class="form-label">Pekerjaan *</label>
            <select name="pekerjaan" class="form-control" required>
              <option value="ASN">ASN</option>
              <option value="NON ASN" selected>NON ASN</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Lengkap</label>
          <textarea name="alamat" class="form-control" rows="2" placeholder="Nama jalan, RT/RW, kelurahan, kota"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Tanggal Bergabung *</label>
          <input type="date" name="tanggal_bergabung" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div style="background-color: var(--bg-subtle); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 12px; font-size: 0.8rem; color: var(--text-muted);">
          ℹ️ Sistem akan membuatkan akun login anggota secara otomatis dengan kata sandi default: <code>anggota123</code>.
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambahAnggota')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Daftarkan Anggota
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Anggota -->
<div class="modal-overlay" id="modalEditAnggota">
  <div class="modal-card" style="max-width: 580px;">
    <div class="modal-header">
      <h3>Edit Data Anggota</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalEditAnggota')">&times;</button>
    </div>
    <form method="POST" action="anggota.php">
      <input type="hidden" name="action" value="edit_anggota">
      <input type="hidden" name="anggota_id" id="edit_anggota_id">
      <div class="modal-body">
        
        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">No. Anggota</label>
            <input type="text" id="edit_no_anggota" class="form-control" readonly style="background:#f1f5f9; cursor:not-allowed;">
          </div>
          <div class="form-col form-group">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="nama" id="edit_nama" class="form-control" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">NIK</label>
            <input type="text" name="nik" id="edit_nik" class="form-control" placeholder="16 digit NIK">
          </div>
          <div class="form-col form-group">
            <label class="form-label">Pekerjaan *</label>
            <select name="pekerjaan" id="edit_pekerjaan" class="form-control" required>
              <option value="ASN">ASN</option>
              <option value="NON ASN">NON ASN</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col form-group">
            <label class="form-label">No. Telepon / WhatsApp</label>
            <input type="text" name="no_telp" id="edit_no_telp" class="form-control">
          </div>
          <div class="form-col form-group">
            <label class="form-label">Tanggal Bergabung *</label>
            <input type="date" name="tanggal_bergabung" id="edit_tanggal_bergabung" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Lengkap</label>
          <textarea name="alamat" id="edit_alamat" class="form-control" rows="2"></textarea>
        </div>

        <div class="form-row" style="background:var(--bg-subtle); padding:14px; border-radius:var(--radius-sm); border:1px solid var(--border-color);">
          <div class="form-col form-group" style="margin-bottom:0;">
            <label class="form-label">Status Keanggotaan *</label>
            <select name="status" id="edit_status" class="form-control" onchange="toggleTanggalKeluar(this.value)">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Tidak Aktif</option>
            </select>
          </div>
          <div class="form-col form-group" id="group_tanggal_keluar" style="margin-bottom:0; display:none;">
            <label class="form-label" style="color:#dc2626; font-weight:700;">Tanggal Keluar *</label>
            <input type="date" name="tanggal_keluar" id="edit_tanggal_keluar" class="form-control">
            <div class="form-text" style="color:#dc2626;">Ditampilkan teks warna merah pada direktori.</div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditAnggota')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Nonaktifkan Anggota -->
<div class="modal-overlay" id="modalNonaktifAnggota">
  <div class="modal-card" style="max-width: 440px;">
    <div class="modal-header">
      <h3>Nonaktifkan Anggota</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalNonaktifAnggota')">&times;</button>
    </div>
    <form method="POST" action="anggota.php">
      <input type="hidden" name="action" value="toggle_status">
      <input type="hidden" name="target_status" value="nonaktif">
      <input type="hidden" name="anggota_id" id="nonaktif_anggota_id">
      <div class="modal-body">
        <p style="font-size: 0.9rem; color: var(--secondary); margin-bottom: 12px;">
          Anda akan mengubah status keanggotaan <strong id="nonaktif_nama_anggota"></strong> menjadi <span class="badge badge-danger">Tidak Aktif</span>.
        </p>
        <div class="form-group" style="margin-top: 14px;">
          <label class="form-label" style="color:#dc2626; font-weight:700;">Tanggal Keluar *</label>
          <input type="date" name="tanggal_keluar" id="nonaktif_tanggal_keluar" class="form-control" value="<?= date('Y-m-d') ?>" required>
          <div class="form-text" style="color:#dc2626;">Informasi tanggal keluar ini akan ditampilkan dengan tulisan warna merah di direktori anggota.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalNonaktifAnggota')">Batal</button>
        <button type="submit" class="btn btn-danger">
          Konfirmasi Nonaktifkan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Reset Password (Putih di Tengah) -->
<div class="modal-overlay" id="modalResetPassword">
  <div class="modal-card" style="max-width: 460px;">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #eff6ff; color: var(--accent);">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        </span>
        <h3 style="margin: 0;">Reset Kata Sandi</h3>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalResetPassword')">&times;</button>
    </div>
    <form method="POST" action="anggota.php">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="anggota_id" id="reset_anggota_id">
      <div class="modal-body">
        <p style="font-size: 0.95rem; color: var(--secondary); margin-bottom: 14px; line-height: 1.5;">
          Kembalikan kata sandi akun <strong id="reset_nama_anggota"></strong> menjadi default?
        </p>
        <div style="background-color: var(--bg-subtle); border: 1px dashed var(--border-color); border-radius: var(--radius-sm); padding: 14px; font-size: 0.85rem; color: var(--text-muted);">
          <div style="margin-bottom: 6px;">Kata sandi baru akan diatur ke default:</div>
          <code style="font-size: 1rem; color: var(--primary); font-weight: 700; background: #fff; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--border-color); display: inline-block;">anggota123</code>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalResetPassword')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Konfirmasi Reset
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Hapus Anggota (Putih di Tengah) -->
<div class="modal-overlay" id="modalHapusAnggota">
  <div class="modal-card" style="max-width: 460px;">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #fee2e2; color: var(--danger);">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </span>
        <h3 style="margin: 0; color: var(--danger);">Hapus Data Anggota</h3>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalHapusAnggota')">&times;</button>
    </div>
    <form method="POST" action="anggota.php">
      <input type="hidden" name="action" value="hapus_anggota">
      <input type="hidden" name="anggota_id" id="hapus_anggota_id">
      <div class="modal-body">
        <p style="font-size: 0.95rem; color: var(--secondary); margin-bottom: 12px; line-height: 1.5;">
          Apakah Anda yakin ingin menghapus data anggota <strong id="hapus_nama_anggota"></strong>?
        </p>
        <div style="background-color: #fee2e2; border: 1px solid #fecaca; border-radius: var(--radius-sm); padding: 12px; font-size: 0.82rem; color: #991b1b; line-height: 1.5;">
          ⚠️ <strong>Peringatan:</strong> Tindakan ini permanen. Akun login dan data keanggotaan terkait akan dihapus dari sistem.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalHapusAnggota')">Batal</button>
        <button type="submit" class="btn btn-danger">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          Ya, Hapus Anggota
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Aktifkan Kembali Anggota (Putih di Tengah) -->
<div class="modal-overlay" id="modalAktifkanAnggota">
  <div class="modal-card" style="max-width: 440px;">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #ecfdf5; color: var(--primary);">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <h3 style="margin: 0;">Aktifkan Keanggotaan</h3>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeModal('modalAktifkanAnggota')">&times;</button>
    </div>
    <form method="POST" action="anggota.php">
      <input type="hidden" name="action" value="toggle_status">
      <input type="hidden" name="target_status" value="aktif">
      <input type="hidden" name="anggota_id" id="aktifkan_anggota_id">
      <div class="modal-body">
        <p style="font-size: 0.95rem; color: var(--secondary); margin-bottom: 8px;">
          Aktifkan kembali status keanggotaan <strong id="aktifkan_nama_anggota"></strong>?
        </p>
        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">
          Status akan diubah kembali menjadi <span class="badge badge-success">Aktif</span> dan catatan tanggal keluar akan dibersihkan.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAktifkanAnggota')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Aktifkan Keanggotaan
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(ag) {
  document.getElementById('edit_anggota_id').value = ag.id;
  document.getElementById('edit_no_anggota').value = ag.no_anggota;
  document.getElementById('edit_nama').value = ag.nama;
  document.getElementById('edit_nik').value = ag.nik || '';
  document.getElementById('edit_no_telp').value = ag.no_telp || '';
  
  // Pekerjaan ASN / NON ASN
  const selPekerjaan = document.getElementById('edit_pekerjaan');
  if (ag.pekerjaan === 'ASN') {
    selPekerjaan.value = 'ASN';
  } else {
    selPekerjaan.value = 'NON ASN';
  }
  
  document.getElementById('edit_alamat').value = ag.alamat || '';
  document.getElementById('edit_tanggal_bergabung').value = ag.tanggal_bergabung;
  
  const statusSel = document.getElementById('edit_status');
  statusSel.value = ag.status || 'aktif';
  
  const tglKeluarInput = document.getElementById('edit_tanggal_keluar');
  tglKeluarInput.value = ag.tanggal_keluar || '<?= date('Y-m-d') ?>';
  toggleTanggalKeluar(statusSel.value);
  
  openModal('modalEditAnggota');
}

function toggleTanggalKeluar(status) {
  const grp = document.getElementById('group_tanggal_keluar');
  const inp = document.getElementById('edit_tanggal_keluar');
  if (status === 'nonaktif') {
    grp.style.display = 'block';
    inp.required = true;
  } else {
    grp.style.display = 'none';
    inp.required = false;
  }
}

function openNonaktifModal(id, nama) {
  document.getElementById('nonaktif_anggota_id').value = id;
  document.getElementById('nonaktif_nama_anggota').textContent = nama;
  document.getElementById('nonaktif_tanggal_keluar').value = '<?= date('Y-m-d') ?>';
  openModal('modalNonaktifAnggota');
}

function openResetModal(id, nama) {
  document.getElementById('reset_anggota_id').value = id;
  document.getElementById('reset_nama_anggota').textContent = nama;
  openModal('modalResetPassword');
}

function openHapusModal(id, nama) {
  document.getElementById('hapus_anggota_id').value = id;
  document.getElementById('hapus_nama_anggota').textContent = nama;
  openModal('modalHapusAnggota');
}

function openAktifkanModal(id, nama) {
  document.getElementById('aktifkan_anggota_id').value = id;
  document.getElementById('aktifkan_nama_anggota').textContent = nama;
  openModal('modalAktifkanAnggota');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
