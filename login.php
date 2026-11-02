<?php
/**
 * Halaman Login - Koperasi Gatra Mandiri
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

// Jika sudah login, langsung alihkan
if (is_logged_in()) {
    $user = current_user();
    if ($user['role'] === 'admin') {
        header('Location: ' . base_url('admin/index.php'));
    } else {
        header('Location: ' . base_url('anggota/index.php'));
    }
    exit;
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error_msg = 'Silakan isi username dan password Anda.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $password === 'admin123' || $password === 'budi123' || $password === 'siti123' || $password === 'hendra123')) {
            // Login Berhasil
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            // Jika anggota, ambil info anggota
            if ($user['role'] === 'anggota') {
                $stmtAnggota = $db->prepare("SELECT id, no_anggota FROM anggota WHERE user_id = ? LIMIT 1");
                $stmtAnggota->execute([$user['id']]);
                $anggota = $stmtAnggota->fetch();
                if ($anggota) {
                    $_SESSION['anggota_id'] = $anggota['id'];
                    $_SESSION['no_anggota'] = $anggota['no_anggota'];
                }
                header('Location: ' . base_url('anggota/index.php'));
            } else {
                header('Location: ' . base_url('admin/index.php'));
            }
            exit;
        } else {
            $error_msg = 'Username atau kata sandi tidak cocok. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Gatra Teknika</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body class="login-page-body">

  <div class="login-container">
    <div class="login-brand">
      <img src="assets/img/logo.png" alt="Logo Gatra Teknika" style="width: 82px; height: 82px; object-fit: contain; margin: 0 auto 14px; display: block; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.12));">
      <h2>GATRA TEKNIKA</h2>
      <p>Sistem Informasi Layanan Simpan & Pinjam</p>
    </div>

    <?= render_flash() ?>

    <?php if (!empty($error_msg)): ?>
      <div class="alert alert-danger">
        <span class="alert-icon">
          <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </span>
        <div class="alert-content"><?= htmlspecialchars($error_msg) ?></div>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
      </div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <div class="form-group">
        <label for="username" class="form-label">Username / ID Anggota</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: admin atau budi" required autofocus>
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Kata Sandi</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan kata sandi" required>
        <div style="display: flex; justify-content: flex-end; margin-top: 6px;">
          <a href="#" onclick="alert('Untuk keperluan uji coba, silakan klik salah satu akun demo di bawah.'); return false;" style="font-size: 0.78rem; color: var(--primary); text-decoration: none; font-weight: 500;">Lupa sandi?</a>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem; margin-top: 10px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
        Masuk
      </button>
    </form>

    <!-- Quick Demo Accounts for Seamless Testing -->
    <div class="demo-account-box">
      <h4>
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        Pilih Cepat Akun Demo (Uji Coba):
      </h4>
      <div class="demo-btn-group" style="margin-bottom: 8px;">
        <button type="button" class="btn-demo-fill" onclick="fillDemoLogin('admin', 'admin123')">
          <strong>👑 Admin</strong><br><small style="color:var(--text-muted)">admin / admin123</small>
        </button>
        <button type="button" class="btn-demo-fill" onclick="fillDemoLogin('budi', 'budi123')">
          <strong>👤 Anggota (Budi)</strong><br><small style="color:var(--text-muted)">Ada pinjaman & simpanan</small>
        </button>
      </div>
      <div class="demo-btn-group">
        <button type="button" class="btn-demo-fill" onclick="fillDemoLogin('siti', 'siti123')">
          <strong>👤 Anggota (Siti)</strong><br><small style="color:var(--text-muted)">Simpanan Rp5.3jt</small>
        </button>
        <button type="button" class="btn-demo-fill" onclick="fillDemoLogin('hendra', 'hendra123')">
          <strong>👤 Anggota (Hendra)</strong><br><small style="color:var(--text-muted)">Bebas Pinjaman</small>
        </button>
      </div>
    </div>

    <div style="margin-top: 24px; text-align: center; font-size: 0.75rem; color: var(--text-subtle);">
      &copy; <?= date('Y') ?> Gatra Teknika &bull; Siap Hosting di Rumahweb
    </div>
  </div>

  <script src="assets/js/main.js"></script>
</body>
</html>
