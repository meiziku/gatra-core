<?php
/**
 * Helper Functions untuk Koperasi Gatra Mandiri
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!ob_get_level()) {
    ob_start();
}

/**
 * Format angka ke format Rupiah (Menjamin 1 baris tidak pernah terputus 2 baris)
 */
function rupiah($angka) {
    if ($angka === null || $angka === '') return "Rp\xc2\xa00";
    return "Rp\xc2\xa0" . number_format((float)$angka, 0, ',', '.');
}

/**
 * Alias format_rupiah untuk kompatibilitas
 */
function format_rupiah($angka) {
    return rupiah($angka);
}

/**
 * Konversi tanggal format DD/MM/YYYY atau YYYY-MM-DD ke YYYY-MM-DD standar database
 */
function parse_dmy_to_ymd($dateStr) {
    if (!$dateStr) return date('Y-m-d');
    $clean = trim($dateStr);
    // Jika format DD/MM/YYYY atau DD-MM-YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $clean, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // Jika format YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
        return $clean;
    }
    return date('Y-m-d');
}

/**
 * Format tanggal standar database ke format DD/MM/YYYY
 */
function tgl_dmy($tanggal) {
    if (!$tanggal || $tanggal == '0000-00-00') return '';
    return date('d/m/Y', strtotime($tanggal));
}

/**
 * Data Matriks Resmi Simpanan Pendidikan Koperasi Gatra Teknika
 * Target Dana vs Tenor (12, 24, 36, 48, 60, 72 Bulan)
 */
function get_matriks_simpanan_pendidikan() {
    return [
        1000000  => [72 => 11000,  60 => 13800,  48 => 18000,  36 => 24900,  24 => 38800,   12 => 80500],
        2000000  => [72 => 22000,  60 => 27500,  48 => 35900,  36 => 49800,  24 => 77500,   12 => 160900],
        3000000  => [72 => 33000,  60 => 41300,  48 => 53800,  36 => 74600,  24 => 116300,  12 => 241300],
        4000000  => [72 => 43900,  60 => 55000,  48 => 71700,  36 => 99500,  24 => 155000,  12 => 321700],
        5000000  => [72 => 54900,  60 => 68800,  48 => 89600,  36 => 124400, 24 => 193800,  12 => 402100],
        6000000  => [72 => 65900,  60 => 82500,  48 => 107500, 36 => 149200, 24 => 232500,  12 => 482500],
        7000000  => [72 => 76900,  60 => 96300,  48 => 125500, 36 => 174100, 24 => 271300,  12 => 563000],
        8000000  => [72 => 87800,  60 => 110000, 48 => 143400, 36 => 198900, 24 => 310000,  12 => 643400],
        9000000  => [72 => 98800,  60 => 123800, 48 => 161300, 36 => 223800, 24 => 348800,  12 => 723800],
        10000000 => [72 => 109800, 60 => 137500, 48 => 179200, 36 => 248700, 24 => 387500,  12 => 804200],
        15000000 => [72 => 164600, 60 => 206300, 48 => 268800, 36 => 373000, 24 => 581300,  12 => 1206300],
        20000000 => [72 => 219500, 60 => 275000, 48 => 358400, 36 => 497300, 24 => 775000,  12 => 1608400],
        25000000 => [72 => 274400, 60 => 343800, 48 => 448000, 36 => 621600, 24 => 968800,  12 => 2010500],
        30000000 => [72 => 329200, 60 => 412500, 48 => 537500, 36 => 745900, 24 => 1162500, 12 => 2412500],
        40000000 => [72 => 438900, 60 => 550000, 48 => 716700, 36 => 994500, 24 => 1550000, 12 => 3216700],
        50000000 => [72 => 548700, 60 => 687500, 48 => 895900, 36 => 1243100, 24 => 1937500, 12 => 4020900],
    ];
}

/**
 * Mengambil nominal setoran bulanan simpanan pendidikan berdasarkan target dana dan tenor bulan
 */
function get_setoran_simpanan_pendidikan($targetDana, $tenorBulan) {
    $matriks = get_matriks_simpanan_pendidikan();
    $target = (int)$targetDana;
    $tenor = (int)$tenorBulan;
    if (isset($matriks[$target][$tenor])) {
        return $matriks[$target][$tenor];
    }
    return 0;
}

/**
 * Format tanggal ke format Indonesia (contoh: 28 Oktober 2026)
 */
function tgl_indo($tanggal, $cetak_hari = false) {
    if (!$tanggal || $tanggal == '0000-00-00') return '-';
    
    $hari = array ( 1 => 'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu',
        'Minggu'
    );
    
    $bulan = array (1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    );
    
    $split    = explode('-', date('Y-m-d', strtotime($tanggal)));
    $tgl_indo = (int)$split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
    
    if ($cetak_hari) {
        $num = date('N', strtotime($tanggal));
        return $hari[$num] . ', ' . $tgl_indo;
    }
    return $tgl_indo;
}

/**
 * Mendapatkan base URL otomatis
 */
function base_url($path = '') {
    // Deteksi HTTPS
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $protocol = $is_https ? "https://" : "http://";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Hitung direktori dasar relatif terhadap script
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    // Normalize slashes
    $script_dir = str_replace('\\', '/', $script_dir);
    
    // Jika berada di dalam folder admin atau anggota, naik ke root
    if (strpos($script_dir, '/admin') !== false) {
        $root_dir = preg_replace('/\/admin.*$/', '', $script_dir);
    } elseif (strpos($script_dir, '/anggota') !== false) {
        $root_dir = preg_replace('/\/anggota.*$/', '', $script_dir);
    } else {
        $root_dir = rtrim($script_dir, '/');
    }
    
    $base = $protocol . $host . ($root_dir === '/' || $root_dir === '' ? '' : $root_dir);
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect aman dengan deteksi headers_sent dan fallback HTML/JS
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script type="text/javascript">window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
    exit;
}

/**
 * Cek status login
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Ambil data user yang sedang login
 */
function current_user() {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nama_lengkap' => $_SESSION['nama_lengkap'],
        'role' => $_SESSION['role'],
        'anggota_id' => $_SESSION['anggota_id'] ?? null,
        'no_anggota' => $_SESSION['no_anggota'] ?? null
    ];
}

/**
 * Batasi akses hanya untuk user yang sudah login
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        redirect(base_url('login.php'));
    }
}

/**
 * Batasi akses khusus Admin
 */
function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        set_flash('error', 'Akses ditolak. Halaman ini khusus untuk Administrator Koperasi.');
        redirect(base_url('anggota/index.php'));
    }
}

/**
 * Batasi akses khusus Anggota
 */
function require_anggota() {
    require_login();
    if ($_SESSION['role'] !== 'anggota') {
        // Admin boleh melihat atau diarahkan ke dashboard admin
        redirect(base_url('admin/index.php'));
    }
}

/**
 * Set flash message (notifikasi pesan sukses/error)
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Ambil dan bersihkan flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render notifikasi flash jika ada
 */
function render_flash() {
    $flash = get_flash();
    if (!$flash) return '';
    
    $type_class = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        'info'    => 'alert-info'
    ][$flash['type']] ?? 'alert-info';
    
    $icon = [
        'success' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
        'error'   => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
        'warning' => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'info'    => '<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
    ][$flash['type']] ?? '';
    
    return '<div class="alert ' . $type_class . '">
        <span class="alert-icon">' . $icon . '</span>
        <div class="alert-content">' . htmlspecialchars($flash['message']) . '</div>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>';
}

/**
 * Mengambil nilai pengaturan sistem dari database
 */
function get_setting($key, $default = null) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT nilai FROM pengaturan WHERE kunci = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Menyimpan / memperbarui nilai pengaturan sistem
 */
function set_setting($key, $value) {
    global $db;
    try {
        $stmt = $db->prepare("REPLACE INTO pengaturan (kunci, nilai) VALUES (?, ?)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mengambil daftar opsi kategori rekening terstruktur dari Neraca & Laba/Rugi
 */
function get_kategori_kas_grouped() {
    $rawNeraca = get_setting('neraca_saldo');
    $neraca = $rawNeraca ? json_decode($rawNeraca, true) : [];

    $rawLr = get_setting('akun_labarugi');
    $lr = $rawLr ? json_decode($rawLr, true) : [];

    $grouped = [];

    // 1. Group Laba/Rugi: Pendapatan
    $pendapatan = $lr['pendapatan'] ?? [];
    if (!empty($pendapatan)) {
        $grouped['Laba/Rugi - Pendapatan'] = [];
        foreach ($pendapatan as $p) {
            $kode = trim($p['kode'] ?? '');
            $nama = trim($p['nama'] ?? '');
            if ($nama === '') continue;
            $val = ($kode !== '' ? "[$kode] " : '') . $nama;
            $grouped['Laba/Rugi - Pendapatan'][] = [
                'val'  => $val,
                'kode' => $kode,
                'nama' => $nama,
                'arus' => 'masuk'
            ];
        }
    }

    // 2. Group Laba/Rugi: Beban & Pengeluaran
    $pengeluaran = $lr['pengeluaran'] ?? [];
    if (!empty($pengeluaran)) {
        $grouped['Laba/Rugi - Beban & Pengeluaran'] = [];
        foreach ($pengeluaran as $p) {
            $kode = trim($p['kode'] ?? '');
            $nama = trim($p['nama'] ?? '');
            if ($nama === '') continue;
            $val = ($kode !== '' ? "[$kode] " : '') . $nama;
            $grouped['Laba/Rugi - Beban & Pengeluaran'][] = [
                'val'  => $val,
                'kode' => $kode,
                'nama' => $nama,
                'arus' => 'keluar'
            ];
        }
    }

    // 3. Group Neraca: Aset, Kewajiban, Dana, Ekuitas, SHU
    if (!empty($neraca) && is_array($neraca)) {
        foreach ($neraca as $catKey => $cat) {
            $catTitle = 'Neraca - ' . ($cat['nama_kategori'] ?? $catKey);
            $grouped[$catTitle] = [];
            foreach ($cat['items'] ?? [] as $it) {
                $kode = trim($it['kode'] ?? '');
                $nama = trim($it['nama'] ?? '');
                if ($nama === '') continue;
                $val = ($kode !== '' ? "[$kode] " : '') . $nama;
                $grouped[$catTitle][] = [
                    'val'  => $val,
                    'kode' => $kode,
                    'nama' => $nama,
                    'arus' => 'fleksibel'
                ];
            }
        }
    }

    return $grouped;
}

/**
 * Mengambil Saldo Awal Neraca untuk Akun Kas (Kas Umum, Kas Bank, Kas Toko)
 */
function get_saldo_awal_neraca_kas($tipeKas) {
    $rawNeraca = get_setting('neraca_saldo');
    if (!$rawNeraca) return 0;
    $neraca = json_decode($rawNeraca, true);
    if (!is_array($neraca)) return 0;

    $items = $neraca['A1']['items'] ?? [];
    foreach ($items as $it) {
        $kode = trim($it['kode'] ?? '');
        $nama = strtolower(trim($it['nama'] ?? ''));
        if (($tipeKas === 'kas_sp' || $tipeKas === 'sp') && ($kode === 'A.1.1' || strpos($nama, 'simpan pinjam') !== false || strpos($nama, 'kas sp') !== false)) {
            return (float)($it['saldo'] ?? 0);
        }
        if ($tipeKas === 'kas_umum' && ($kode === 'A.1.2' || strpos($nama, 'pusat') !== false || strpos($nama, 'umum') !== false)) {
            return (float)($it['saldo'] ?? 0);
        }
        if ($tipeKas === 'kas_bank' && ($kode === 'A.1.3' || strpos($nama, 'bank') !== false)) {
            return (float)($it['saldo'] ?? 0);
        }
        if ($tipeKas === 'kas_toko' && ($kode === 'A.1.4' || strpos($nama, 'toko') !== false)) {
            return (float)($it['saldo'] ?? 0);
        }
    }
    return 0;
}

