<?php
require_once '../config/database.php';
require_once '../config/helper.php';

require_admin();

$pageTitle = 'Tabel Matriks Simpanan Pendidikan';
$pageSubtitle = 'Daftar resmi paket target dana, tenor, dan setoran bulanan Koperasi Gatra Teknika';
$activePage = 'simpanan_pendidikan_tabel';

$matriks = get_matriks_simpanan_pendidikan();
$kolomTenor = [
    ['tahun' => 6, 'bulan' => 72],
    ['tahun' => 5, 'bulan' => 60],
    ['tahun' => 4, 'bulan' => 48],
    ['tahun' => 3, 'bulan' => 36],
    ['tahun' => 2, 'bulan' => 24],
    ['tahun' => 1, 'bulan' => 12]
];

include 'header.php';
?>

<div class="row" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
  <div>
    <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--secondary); margin-bottom: 4px;">Tabel Resmi Simpanan Pendidikan</h2>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Matriks acuan setoran bulanan dan target dana yang diterima anggota saat jatuh tempo.</p>
  </div>
  <div style="display: flex; gap: 10px;">
    <a href="simpanan_pendidikan.php" class="btn btn-secondary">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      Kelola Anggota Terdaftar
    </a>
    <button type="button" class="btn btn-primary" onclick="window.print()">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Cetak Matriks
    </button>
  </div>
</div>

<!-- Interactive Calculator Card -->
<div class="card" style="margin-bottom: 24px; border: 1px solid #bfdbfe; background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%);">
  <div class="card-header" style="border-bottom: 1px solid #dbeafe; background: transparent;">
    <div>
      <h3 style="color: #1e40af; font-weight: 800; display: flex; align-items: center; gap: 8px;">
        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        Kalkulator & Simulasi Cepat Simpanan Pendidikan
      </h3>
      <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: #475569;">Pilih target dana dan jangka waktu untuk melihat simulasi setoran bulanan dan keuntungan anggota.</p>
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
          <option value="50" style="display:none;">-</option>
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
        <label class="form-label" style="font-weight: 700; color: #1e3a8a;">Keuntungan / Bunga Anggota:</label>
        <div id="simHasilKeuntungan" style="font-size: 1.1rem; font-weight: 800; color: #1d4ed8; background: #ffffff; padding: 9px 14px; border-radius: 8px; border: 1px solid #bfdbfe;">
          Rp 0
        </div>
      </div>
    </div>

    <div style="margin-top: 16px; padding: 12px 16px; background: rgba(255,255,255,0.7); border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 0.88rem; color: #334155;">
      <div>
        Total yang disetor selama tenor: <strong id="simTotalDisetor" style="color: #0f172a;">Rp 0</strong>. 
        Anggota akan menerima dana tunai penuh saat jatuh tempo sebesar <strong id="simTargetDiterima" style="color: #047857;">Rp 0</strong>.
      </div>
      <div>
        <a id="btnDaftarCepat" href="simpanan_pendidikan.php?action=baru" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.85rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Daftarkan Anggota Ini
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Official Table Replica Card -->
<div class="card" style="box-shadow: var(--shadow-md); border-radius: 12px; overflow: hidden;">
  <div style="background: #ffffff; padding: 24px 24px 16px; text-align: center; border-bottom: 2px solid #38bdf8;">
    <h1 style="color: #38bdf8; font-weight: 900; font-size: 2rem; letter-spacing: 1.5px; margin: 0; text-transform: uppercase;">
      SIMPANAN PENDIDIKAN
    </h1>
    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 6px 0 0 0;">
      Koperasi Simpan Pinjam Gatra Teknika &bull; Program Tabungan Masa Depan Pendidikan Terencana
    </p>
  </div>

  <div class="table-freeze-container" style="max-height: calc(100vh - 200px); padding: 4px;">
    <table class="matrix-table" id="matrixSimpananPendidikan" style="width: 100%; border-collapse: separate; border-spacing: 0; font-family: 'Plus Jakarta Sans', sans-serif;">
      <thead>
        <tr style="background-color: #ffffff;">
          <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1rem; font-weight: 800; text-align: left; width: 170px; position: sticky; top: 0; z-index: 25; background-color: #ffffff;">
            Tahun
          </th>
          <?php foreach ($kolomTenor as $col): ?>
            <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1.05rem; font-weight: 800; text-align: right; position: sticky; top: 0; z-index: 25; background-color: #ffffff;">
              <?= $col['tahun'] ?>
            </th>
          <?php endforeach; ?>
        </tr>
        <tr style="background-color: #f8fafc;">
          <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1rem; font-weight: 800; text-align: left; background-color: #f8fafc; position: sticky; top: 46px; z-index: 25;">
            Bulan
          </th>
          <?php foreach ($kolomTenor as $col): ?>
            <th style="border: 2px solid #0f172a; padding: 10px 14px; font-size: 1.05rem; font-weight: 800; text-align: right; background-color: #f8fafc; position: sticky; top: 46px; z-index: 25;">
              <?= $col['bulan'] ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matriks as $target => $barisTenor): ?>
          <tr class="matrix-row" style="border: 1.5px solid #0f172a; transition: background-color 0.15s ease;">
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
                  data-setoran="<?= $nominal ?>"
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

  <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid var(--border-color); font-size: 0.85rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
      <span style="display: inline-block; width: 12px; height: 12px; background: #dbeafe; border: 1px solid #3b82f6; border-radius: 3px; margin-right: 6px; vertical-align: middle;"></span>
      <strong>Petunjuk:</strong> Klik salah satu angka nominal di dalam tabel untuk langsung menguji simulasinya pada kalkulator di atas.
    </div>
    <div>
      <em>* Nominal per bulan dapat dibayarkan melalui Tunai Langsung, Transfer Bank, Potong Gaji, atau Potong TPP.</em>
    </div>
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
@media print {
  .top-navbar, .sidebar, .card-header, #simTarget, #simTenor, #btnDaftarCepat, button, .btn {
    display: none !important;
  }
  .main-wrapper {
    margin: 0 !important;
    padding: 0 !important;
  }
  .card {
    box-shadow: none !important;
    border: none !important;
  }
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

  // Update link pendaftaran
  const btnDaftar = document.getElementById('btnDaftarCepat');
  if (btnDaftar) {
    btnDaftar.href = 'simpanan_pendidikan.php?daftar_target=' + target + '&daftar_tenor=' + tenor;
  }

  // Highlight active cell in matrix
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
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
  hitungSimulasi();
});
</script>

<?php include 'footer.php'; ?>

