<?php
/**
 * Admin Layout Header
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_admin();

$currentUser = current_user();
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Panel Admin') ?> - Gatra Teknika</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
  <link rel="icon" type="image/png" href="../assets/img/logo.png">
  <style>
    /* Styling Submenu Accordion Sidebar */
    .nav-dropdown {
      position: relative;
    }
    .nav-dropdown.open .dropdown-chevron {
      transform: rotate(180deg);
    }
    .nav-submenu {
      display: none;
      padding-left: 18px;
      margin: 4px 0 6px 0;
      flex-direction: column;
      gap: 2px;
    }
    .nav-dropdown.open .nav-submenu {
      display: flex;
    }
    .nav-sublink {
      display: flex;
      align-items: center;
      padding: 7px 12px;
      font-size: 0.82rem;
      color: var(--secondary-muted, #64748b);
      border-radius: var(--radius-sm, 6px);
      text-decoration: none;
      font-weight: 500;
      transition: all 0.15s ease;
    }
    .nav-sublink:hover {
      background-color: var(--bg-subtle, #f1f5f9);
      color: var(--primary, #059669);
    }
    .nav-sublink.active {
      background-color: var(--primary-light, #ecfdf5);
      color: var(--primary, #059669);
      font-weight: 700;
      border-left: 3px solid var(--primary, #059669);
    }
  </style>
  <script>
    function toggleNavDropdown(id) {
      const el = document.getElementById(id);
      if (el) el.classList.toggle('open');
    }
  </script>
</head>
<body>

<div class="app-container">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-icon" style="background: transparent; box-shadow: none; width: 38px; height: 38px;">
        <img src="../assets/img/logo.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
      </div>
      <div class="brand-info">
        <h1>GATRA TEKNIKA</h1>
        <span>Portal Pengurus</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu Utama</div>
      
      <a href="index.php" class="nav-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Dashboard</span>
      </a>

      <a href="anggota.php" class="nav-link <?= ($activePage === 'anggota') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span>Data Anggota</span>
      </a>

      <div class="nav-section-title">Transaksi Keuangan SP</div>

      <a href="simpanan.php" class="nav-link <?= ($activePage === 'simpanan') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span>Simpanan Anggota</span>
      </a>

      <a href="simpanan_pendidikan.php" class="nav-link <?= ($activePage === 'simpanan_pendidikan' || $activePage === 'simpanan_pendidikan_tabel') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/></svg>
        <span>Simpanan Pendidikan</span>
      </a>

      <a href="pinjaman.php" class="nav-link <?= ($activePage === 'pinjaman') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        <span>Pinjaman Anggota</span>
      </a>

      <a href="angsuran.php" class="nav-link <?= ($activePage === 'angsuran') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        <span>Bayar Angsuran</span>
      </a>

      <a href="laporan_kas_sp.php" class="nav-link <?= ($activePage === 'laporan_kas_sp') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Laporan Kas SP</span>
      </a>

      <div class="nav-section-title">Buku Kas & Bank</div>

      <a href="kas_umum.php" class="nav-link <?= ($activePage === 'kas_umum') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span>Kas Umum</span>
      </a>

      <a href="kas_bank.php" class="nav-link <?= ($activePage === 'kas_bank') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v4M12 14v4M16 14v4"/></svg>
        <span>Kas Bank</span>
      </a>

      <a href="kas_toko.php" class="nav-link <?= ($activePage === 'kas_toko') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <span>Kas Toko</span>
      </a>

      <div class="nav-section-title">Laporan & Analisis</div>

      <!-- Accordion Expand / Collapse: Laporan Koperasi -->
      <div class="nav-dropdown <?= ($activePage === 'laporan') ? 'open' : '' ?>" id="laporanDropdown">
        <a href="javascript:void(0)" class="nav-link nav-dropdown-toggle <?= ($activePage === 'laporan') ? 'active' : '' ?>" onclick="toggleNavDropdown('laporanDropdown')">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span style="flex-grow: 1;">Laporan Koperasi</span>
          <svg class="dropdown-chevron" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transition: transform 0.25s ease;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </a>
        <div class="nav-submenu">
          <a href="laporan.php?tab=simpanan_wajib" class="nav-sublink <?= ($activePage === 'laporan' && ($activeTab ?? ($tab ?? '')) === 'simpanan_wajib') ? 'active' : '' ?>">
            <span>📄 Simpanan Wajib</span>
          </a>
          <a href="laporan.php?tab=simpanan_manasuka" class="nav-sublink <?= ($activePage === 'laporan' && ($activeTab ?? ($tab ?? '')) === 'simpanan_manasuka') ? 'active' : '' ?>">
            <span>📑 Simpanan Manasuka</span>
          </a>
          <a href="laporan.php?tab=laporan_hasil_usaha" class="nav-sublink <?= ($activePage === 'laporan' && ($activeTab ?? ($tab ?? '')) === 'laporan_hasil_usaha') ? 'active' : '' ?>">
            <span>📈 Laporan Hasil Usaha</span>
          </a>
          <a href="laporan.php?tab=neraca" class="nav-sublink <?= ($activePage === 'laporan' && ($activeTab ?? ($tab ?? '')) === 'neraca') ? 'active' : '' ?>">
            <span>⚖️ Neraca</span>
          </a>
          <a href="laporan.php?tab=rekap_shu" class="nav-sublink <?= ($activePage === 'laporan' && ($activeTab ?? ($tab ?? '')) === 'rekap_shu') ? 'active' : '' ?>">
            <span>📊 Rekap SHU</span>
          </a>
          <a href="laporan.php?tab=pembagian_shu" class="nav-sublink <?= ($activePage === 'laporan' && ($activeTab ?? ($tab ?? '')) === 'pembagian_shu') ? 'active' : '' ?>">
            <span>🍰 Pembagian SHU</span>
          </a>
        </div>
      </div>

      <div class="nav-section-title">Konfigurasi</div>

      <a href="pengaturan.php" class="nav-link <?= ($activePage === 'pengaturan') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        <span>Pengaturan</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-snippet">
        <div class="user-avatar">
          <?= strtoupper(substr($currentUser['nama_lengkap'] ?? 'A', 0, 1)) ?>
        </div>
        <div class="user-details">
          <div class="user-name"><?= htmlspecialchars($currentUser['nama_lengkap']) ?></div>
          <span class="user-role-badge">Pengurus (Admin)</span>
        </div>
        <a href="../logout.php" class="btn-logout" title="Keluar">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content Area -->
  <div class="main-wrapper">
    <header class="top-navbar">
      <div class="navbar-left">
        <button class="mobile-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
          <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="page-title-box">
          <h2><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
          <p><?= htmlspecialchars($pageSubtitle ?? 'Koperasi Simpan Pinjam Gatra Teknika') ?></p>
        </div>
      </div>

      <div class="navbar-right">
        <div class="date-pill">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <span><?= tgl_indo(date('Y-m-d'), true) ?></span>
        </div>
      </div>
    </header>

    <main class="content-body">
      <?= render_flash() ?>
