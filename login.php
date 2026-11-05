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

        if ($user && password_verify($password, $user['password'])) {
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
        <div style="position: relative; display: flex; align-items: center;">
          <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan kata sandi" required style="padding-right: 42px;">
          <button type="button" id="togglePasswordBtn" onclick="togglePasswordVisibility()" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; justify-content: center; padding: 4px;" title="Lihat/Sembunyikan Kata Sandi">
            <!-- Icon Eye Open -->
            <svg id="eyeOpenIcon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <!-- Icon Eye Slashed / Closed -->
            <svg id="eyeClosedIcon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem; margin-top: 14px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
        Masuk
      </button>
    </form>
  </div>

  <script src="assets/js/main.js"></script>
  <script>
  function togglePasswordVisibility() {
    const pwdInput = document.getElementById('password');
    const eyeOpen = document.getElementById('eyeOpenIcon');
    const eyeClosed = document.getElementById('eyeClosedIcon');
    if (!pwdInput) return;
    if (pwdInput.type === 'password') {
      pwdInput.type = 'text';
      if (eyeOpen) eyeOpen.style.display = 'none';
      if (eyeClosed) eyeClosed.style.display = 'block';
    } else {
      pwdInput.type = 'password';
      if (eyeOpen) eyeOpen.style.display = 'block';
      if (eyeClosed) eyeClosed.style.display = 'none';
    }
  }
  </script>
</body>
</html>
