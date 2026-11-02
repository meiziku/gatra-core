<?php
/**
 * Simpanan Pendidikan Anggota - Koperasi Gatra Mandiri
 */
$pageTitle = 'Simpanan Pendidikan';
$pageSubtitle = 'Program tabungan berjangka pendidikan anggota terdaftar';
$activePage = 'simpanan_pendidikan';

require_once __DIR__ . '/header.php';

$anggotaId = $memberProfile['id'];
$matriks = get_matriks_simpanan_pendidikan();
$kolomTenor = [
    ['tahun' => 6, 'bulan' => 72],
    ['tahun' => 5, 'bulan' => 60],
    ['tahun' => 4, 'bulan' => 48],
    ['tahun' => 3, 'bulan' => 36],
    ['tahun' => 2, 'bulan' => 24],
    ['tahun' => 1, 'bulan' => 12]
];

// Cari rekening simpanan pendidikan milik anggota ini
$stmtSp = $db->prepare("
    SELECT sp.*, 
           COUNT(sps.id) as total_setor_kali,
           COALESCE(SUM(sps.nominal), 0) as total_nominal_terkumpul
    FROM simpanan_pendidikan sp
    LEFT JOIN simpanan_pendidikan_setoran sps ON sp.id = sps.simpanan_pendidikan_id
    WHERE sp.anggota_id = ?
    GROUP BY sp.id
    ORDER BY sp.id DESC
");
$stmtSp->execute([$anggotaId]);
$rekeningSpList = $stmtSp->fetchAll(PDO::FETCH_ASSOC);

// Ambil riwayat setoran jika anggota memiliki rekening
$riwayatSetoran = [];
if (!empty($rekeningSpList)) {
    $spIds = array_column($rekeningSpList, 'id');
    $placeholders = implode(',', array_fill(0, count($spIds), '?'));
    $stmtSetoran = $db->prepare("
        SELECT sps.*, sp.no_rekening 
        FROM simpanan_pendidikan_setoran sps
        JOIN simpanan_pendidikan sp ON sps.simpanan_pendidikan_id = sp.id
        WHERE sps.simpanan_pendidikan_id IN ($placeholders)
        ORDER BY sps.tanggal_bayar DESC, sps.id DESC
    ");
    $stmtSetoran->execute($spIds);
    $riwayatSetoran = $stmtSetoran->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Header Banner / Status Pendaftaran -->
<?php if (empty($rekeningSpList)): ?>
  <div class="card" style="margin-bottom: 24px; border-left: 4px solid #3b82f6; background: #eff6ff;">
    <div style="padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
      <div style="display: flex; gap: 14px; align-items: center;">
        <div style="background: #dbeafe; color: #1e40af; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
          <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
          <h3 style="margin: 0; color: #1e3a8a; font-size: 1.1rem; font-weight: 700;">Anda Belum Terdaftar di Program Simpanan Pendidikan</h3>
          <p style="margin: 4px 0 0 0; color: #475569; font-size: 0.88rem;">
            Simpanan Pendidikan diperuntukkan khusus bagi anggota terdaftar. Silakan pelajari tabel simulasi di bawah dan hubungi pengurus koperasi untuk melakukan pendaftaran.
          </p>
        </div>
      </div>
      <div>
        <a href="#tabelMatriks" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.88rem;">
          Lihat Simulasi Paket
        </a>
      </div>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($rekeningSpList as $sp): 
    $persen = $sp['tenor_bulan'] > 0 ? min(100, round(($sp['total_setor_kali'] / $sp['tenor_bulan']) * 100)) : 0;
    $sisaBulan = max(0, $sp['tenor_bulan'] - $sp['total_setor_kali']);
    $totalRencana = $sp['setoran_bulanan'] * $sp['tenor_bulan'];
    $keuntungan = $sp['target_dana'] - $totalRencana;
  ?>
    <div class="card" style="margin-bottom: 24px; border: 1px solid #a7f3d0; overflow: hidden;">
      <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #fff; padding: 22px 24px;">
        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 14px;">
          <div>
            <span style="display: inline-block; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">
              Rekening Simpanan Pendidikan
            </span>
            <h2 style="margin: 0; font-size: 1.5rem; font-weight: 800;"><?= htmlspecialchars($sp['no_rekening']) ?></h2>
            <p style="margin: 4px 0 0 0; opacity: 0.9; font-size: 0.85rem;">Mulai: <?= tgl_dmy($sp['tanggal_mulai']) ?> &bull; Jatuh Tempo: <?= tgl_dmy($sp['tanggal_jatuh_tempo']) ?></p>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 0.8rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px;">Target Dana Diterima</div>
            <div style="font-size: 1.7rem; font-weight: 900; color: #a7f3d0;"><?= rupiah($sp['target_dana']) ?></div>
            <span class="badge" style="background: #ffffff; color: #047857; font-weight: 700; font-size: 0.75rem; text-transform: uppercase;">
              Status: <?= htmlspecialchars($sp['status']) ?>
            </span>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 0.88rem;">
          <div>
            <div style="opacity: 0.8; font-size: 0.75rem;">Setoran Wajib Bulanan:</div>
            <strong style="font-size: 1.05rem;"><?= rupiah($sp['setoran_bulanan']) ?> / bln</strong>
          </div>
          <div>
            <div style="opacity: 0.8; font-size: 0.75rem;">Jangka Waktu (Tenor):</div>
            <strong><?= $sp['tenor_bulan'] ?> Bulan (<?= round($sp['tenor_bulan'] / 12) ?> Thn)</strong>
          </div>
          <div>
            <div style="opacity: 0.8; font-size: 0.75rem;">Total Sudah Disetor:</div>
            <strong style="font-size: 1.05rem; color: #a7f3d0;"><?= rupiah($sp['total_nominal_terkumpul']) ?></strong>
          </div>
          <div>
            <div style="opacity: 0.8; font-size: 0.75rem;">Keuntungan dari Koperasi:</div>
            <strong style="color: #fef08a;">+<?= rupiah($keuntungan) ?></strong>
          </div>
        </div>
      </div>

      <!-- Progres Bar -->
      <div style="padding: 18px 24px; background: #ffffff;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 0.88rem;">
          <span style="font-weight: 700; color: var(--secondary);">Progres Simpanan:</span>
          <span style="font-weight: 800; color: #047857;"><?= $persen ?>% (Bulan ke-<?= $sp['total_setor_kali'] ?> dari <?= $sp['tenor_bulan'] ?> Bulan)</span>
        </div>
        <div style="background: #e2e8f0; height: 10px; border-radius: 6px; overflow: hidden; margin-bottom: 10px;">
          <div style="background: <?= $persen >= 100 ? '#10b981' : '#3b82f6' ?>; width: <?= $persen ?>%; height: 100%; transition: width 0.3s ease;"></div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-muted);">
          <span>Tersisa <strong><?= $sisaBulan ?> bulan setoran</strong> lagi</span>
          <span>Setoran melalui: Tunai, Transfer Bank, Potong Gaji, atau Potong TPP</span>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Riwayat Pembayaran Setoran Masuk -->
  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
      <h3 style="margin: 0; font-weight: 800;">Riwayat Pembayaran Setoran Pendidikan Anda</h3>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Tanggal Bayar</th>
            <th>No. Rekening</th>
            <th>Bulan Ke</th>
            <th>Nominal</th>
            <th>Metode Pembayaran</th>
            <th>Petugas</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($riwayatSetoran)): ?>
            <tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--text-subtle);">Belum ada catatan setoran masuk.</td></tr>
          <?php else: ?>
            <?php foreach ($riwayatSetoran as $r): ?>
              <tr>
                <td><?= tgl_dmy($r['tanggal_bayar']) ?></td>
                <td><strong><?= htmlspecialchars($r['no_rekening']) ?></strong></td>
                <td><span class="badge badge-info">Bulan ke-<?= $r['setoran_ke'] ?></span></td>
                <td style="font-weight: 800; color: #047857;"><?= rupiah($r['nominal']) ?></td>
                <td>
                  <?php 
                    $ket = trim($r['keterangan'] ?? '');
                    if ($ket === 'Langsung'): ?>
                      <span class="badge badge-success">Langsung</span>
                  <?php elseif ($ket === 'TF'): ?>
                      <span class="badge badge-info">TF</span>
                  <?php elseif ($ket === 'Gaji'): ?>
                      <span class="badge badge-warning">Gaji</span>
                  <?php elseif ($ket === 'TPP'): ?>
                      <span class="badge badge-purple" style="background:#f3e8ff; color:#7e22ce;">TPP</span>
                  <?php else: ?>
                      <span><?= htmlspecialchars($ket ?: '-') ?></span>
                  <?php endif; ?>
                </td>
                <td><small><?= htmlspecialchars($r['petugas'] ?? 'Kasir') ?></small></td>
                <td><small><?= htmlspecialchars($r['catatan'] ?: '-') ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Kalkulator Simulasi Interaktif & Tabel Matriks Resmi -->
<div id="tabelMatriks" class="card" style="margin-bottom: 24px; border: 1px solid #bfdbfe; background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%);">
  <div class="card-header" style="background: transparent; border-bottom: 1px solid #dbeafe;">
    <div>
      <h3 style="color: #1e40af; font-weight: 800; display: flex; align-items: center; gap: 8px;">
        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        Kalkulator & Simulasi Simpanan Pendidikan
      </h3>
      <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: #475569;">
        Hitung estimasi setoran bulanan dan keuntungan yang didapat sesuai target kebutuhan pendidikan putra-putri Anda.
      </p>
    </div>
  </div>

  <div class="card-body">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; align-items: end;">
      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-weight: 700; color: #1e3a8a;">Pilih Target Dana (Diterima):</label>
        <select id="simTarget" class="form-select" onchange="hitungSimulasi()" style="font-weight: 600; border-color: #93c5fd;">
          <?php foreach ($matriks as $target => $v): ?>
            <option value="<?= $target ?>" <?= $target == 10000000 ? 'selected' : '' ?>><?= rupiah($target) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-weight: 700; color: #1e3a8a;">Pilih Jangka Waktu (Tenor):</label>
        <select id="simTenor" class="form-select" onchange="hitungSimulasi()" style="font-weight: 600; border-color: #93c5fd;">
          <option value="12">1 Tahun (12 Bulan)</option>
          <option value="24">2 Tahun (24 Bulan)</option>
          <option value="36" selected>3 Tahun (36 Bulan)</option>
          <option value="48">4 Tahun (48 Bulan)</option>
          <option value="60">5 Tahun (60 Bulan)</option>
          <option value="72">6 Tahun (72 Bulan)</option>
        </select>
      </div>

      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-weight: 700; color: #1e3a8a;">Setoran Wajib Bulanan:</label>
        <div id="simHasilSetoran" style="font-size: 1.25rem; font-weight: 800; color: #047857; background: #ffffff; padding: 8px 14px; border-radius: 8px; border: 1px solid #a7f3d0;">
          Rp 0
        </div>
      </div>

      <div class="form-group" style="margin: 0;">
        <label class="form-label" style="font-weight: 700; color: #1e3a8a;">Keuntungan / Bunga Diterima:</label>
        <div id="simHasilKeuntungan" style="font-size: 1.1rem; font-weight: 800; color: #1d4ed8; background: #ffffff; padding: 9px 14px; border-radius: 8px; border: 1px solid #bfdbfe;">
          Rp 0
        </div>
      </div>
    </div>

    <div style="margin-top: 16px; padding: 12px 16px; background: rgba(255,255,255,0.7); border-radius: 8px; font-size: 0.88rem; color: #334155;">
      Total yang disetor selama tenor: <strong id="simTotalDisetor" style="color: #0f172a;">Rp 0</strong>. 
      Anda akan menerima dana tunai penuh saat jatuh tempo sebesar <strong id="simTargetDiterima" style="color: #047857;">Rp 0</strong>.
    </div>
  </div>
</div>

<!-- Tabel Matriks Lengkap -->
<div class="card" style="box-shadow: var(--shadow-md); border-radius: 12px; overflow: hidden; margin-bottom: 24px;">
  <div style="background: #ffffff; padding: 20px 24px 14px; text-align: center; border-bottom: 2px solid #38bdf8;">
    <h2 style="color: #38bdf8; font-weight: 900; font-size: 1.8rem; letter-spacing: 1.5px; margin: 0; text-transform: uppercase;">
      SIMPANAN PENDIDIKAN
    </h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin: 4px 0 0 0;">
      Tabel Matriks Resmi Setoran Bulanan Koperasi Simpan Pinjam Gatra Teknika
    </p>
  </div>

  <div class="table-responsive" style="padding: 12px;">
    <table class="matrix-table" id="matrixSimpananPendidikan" style="width: 100%; border-collapse: collapse; font-family: 'Plus Jakarta Sans', sans-serif;">
      <thead>
        <tr style="background-color: #ffffff; border: 2px solid #0f172a;">
          <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1rem; font-weight: 800; text-align: left; width: 170px;">
            Tahun
          </th>
          <?php foreach ($kolomTenor as $col): ?>
            <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1.05rem; font-weight: 800; text-align: right;">
              <?= $col['tahun'] ?>
            </th>
          <?php endforeach; ?>
        </tr>
        <tr style="background-color: #ffffff; border: 2px solid #0f172a;">
          <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1rem; font-weight: 800; text-align: left; background-color: #f8fafc;">
            Bulan
          </th>
          <?php foreach ($kolomTenor as $col): ?>
            <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1.05rem; font-weight: 800; text-align: right; background-color: #f8fafc;">
              <?= $col['bulan'] ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matriks as $target => $barisTenor): ?>
          <tr class="matrix-row" style="border: 1.5px solid #0f172a;">
            <td style="border: 1.5px solid #0f172a; padding: 9px 14px; font-weight: 800; font-size: 0.98rem; text-align: right; background-color: #ffffff; color: #0f172a;">
              <?= number_format($target, 0, ',', ',') ?>
            </td>
            <?php foreach ($kolomTenor as $col): 
              $tenorBulan = $col['bulan'];
              $nominal = $barisTenor[$tenorBulan] ?? 0;
            ?>
              <td class="matrix-cell" 
                  data-target="<?= $target ?>" 
                  data-tenor="<?= $tenorBulan ?>" 
                  onclick="selectCell(<?= $target ?>, <?= $tenorBulan ?>)"
                  style="border: 1.5px solid #0f172a; padding: 9px 14px; font-size: 0.95rem; text-align: right; font-weight: 600; cursor: pointer;">
                <?= number_format($nominal, 0, ',', ',') ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.matrix-table tbody tr:hover {
  background-color: #f1f5f9;
}
.matrix-cell:hover {
  background-color: #dbeafe !important;
  color: #1e40af !important;
  font-weight: 800 !important;
}
.matrix-cell.active-cell {
  background-color: #bfdbfe !important;
  color: #1d4ed8 !important;
  font-weight: 800 !important;
  outline: 2px solid #2563eb;
}
</style>

<script>
const matriksData = <?= json_encode($matriks) ?>;

function formatRpJs(num) {
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function hitungSimulasi() {
  const target = parseInt(document.getElementById('simTarget').value) || 0;
  const tenor = parseInt(document.getElementById('simTenor').value) || 0;

  let setoran = 0;
  if (matriksData[target] && matriksData[target][tenor]) {
    setoran = matriksData[target][tenor];
  }

  const totalDisetor = setoran * tenor;
  const keuntungan = target - totalDisetor;

  document.getElementById('simHasilSetoran').innerText = formatRpJs(setoran) + ' / bln';
  document.getElementById('simTotalDisetor').innerText = formatRpJs(totalDisetor);
  document.getElementById('simTargetDiterima').innerText = formatRpJs(target);
  
  if (keuntungan > 0) {
    const persen = ((keuntungan / totalDisetor) * 100).toFixed(2);
    document.getElementById('simHasilKeuntungan').innerText = '+' + formatRpJs(keuntungan) + ' (' + persen + '%)';
    document.getElementById('simHasilKeuntungan').style.color = '#1d4ed8';
  } else {
    document.getElementById('simHasilKeuntungan').innerText = formatRpJs(0);
  }

  document.querySelectorAll('.matrix-cell').forEach(cell => {
    if (parseInt(cell.dataset.target) === target && parseInt(cell.dataset.tenor) === tenor) {
      cell.classList.add('active-cell');
    } else {
      cell.classList.remove('active-cell');
    }
  });
}

function selectCell(target, tenor) {
  document.getElementById('simTarget').value = target;
  document.getElementById('simTenor').value = tenor;
  hitungSimulasi();
  document.getElementById('tabelMatriks').scrollIntoView({ behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
  hitungSimulasi();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

