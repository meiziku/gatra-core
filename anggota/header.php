<?php
/**
 * Header Layout Portal Anggota - Koperasi Gatra Mandiri
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_login();

$currentUser = current_user();
$activePage = $activePage ?? 'dashboard';

// Ambil info lengkap anggota berdasarkan user_id atau anggota_id
$stmtAg = $db->prepare("SELECT * FROM anggota WHERE user_id = ? OR id = ? LIMIT 1");
$stmtAg->execute([$currentUser['id'], $currentUser['anggota_id'] ?? 0]);
$memberProfile = $stmtAg->fetch();

if (!$memberProfile) {
    die("Data profil anggota tidak ditemukan. Hubungi pengurus koperasi.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Portal Anggota') ?> - Gatra Teknika</title>
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
  <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body>

<div class="app-container">
  <!-- Sidebar Anggota -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-icon" style="background: transparent; box-shadow: none; width: 38px; height: 38px;">
        <img src="../assets/img/logo.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
      </div>
      <div class="brand-info">
        <h1>GATRA TEKNIKA</h1>
        <span>Portal Anggota</span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu Anggota</div>
      
      <a href="index.php" class="nav-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Dashboard & Kartu</span>
      </a>

      <a href="simpanan.php" class="nav-link <?= ($activePage === 'simpanan') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        <span>Sisa Simpanan</span>
      </a>

      <a href="simpanan_pendidikan.php" class="nav-link <?= ($activePage === 'simpanan_pendidikan') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/></svg>
        <span>Simpanan Pendidikan</span>
      </a>

      <a href="pinjaman.php" class="nav-link <?= ($activePage === 'pinjaman') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        <span>Sisa Pinjaman</span>
      </a>

      <a href="pengajuan.php" class="nav-link <?= ($activePage === 'simulasi') ? 'active' : '' ?>">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
        <span>Simulasi Pinjaman</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-snippet">
        <div class="user-avatar" style="background: linear-gradient(135deg, #059669, #047857);">
          <?= strtoupper(substr($memberProfile['nama'], 0, 1)) ?>
        </div>
        <div class="user-details">
          <div class="user-name"><?= htmlspecialchars($memberProfile['nama']) ?></div>
          <span class="user-role-badge"><?= htmlspecialchars($memberProfile['no_anggota']) ?></span>
        </div>
        <a href="../logout.php" class="btn-logout" title="Keluar">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- Main Content Wrapper -->
  <div class="main-wrapper">
    <header class="top-navbar">
      <div class="navbar-left">
        <button class="mobile-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
          <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="page-title-box">
          <h2><?= htmlspecialchars($pageTitle ?? 'Portal Anggota') ?></h2>
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
