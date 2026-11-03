<?php
/**
 * Konfigurasi Database Koperasi Gatra Mandiri
 * Mendukung MySQL/MariaDB (Hosting Rumahweb & Laragon) serta Auto-Fallback SQLite
 */

// Mulai sesi jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Aktifkan output buffering agar redirect header selalu aman
if (!ob_get_level()) {
    ob_start();
}

// Pengaturan Konfigurasi MySQL untuk Rumahweb & Laragon
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gatra_core');

// Driver mode: 'auto' (coba MySQL dulu, jika gagal/belum di-setup otomatis pakai SQLite)
// Atau bisa diset 'mysql' atau 'sqlite'
define('DB_DRIVER_MODE', 'auto');

$pdo = null;

function get_db_connection() {
    global $pdo;
    if ($pdo !== null) {
        return $pdo;
    }

    $driver_mode = DB_DRIVER_MODE;
    $connected = false;

    // Coba MySQL jika mode 'auto' atau 'mysql'
    if ($driver_mode === 'auto' || $driver_mode === 'mysql') {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $connected = true;
        } catch (PDOException $e) {
            // Jika mode paksa MySQL, lempar error
            if ($driver_mode === 'mysql') {
                die("Gagal terkoneksi ke Database MySQL Rumahweb/Laragon: " . $e->getMessage() . 
                    "<br><br>Pastikan database '" . DB_NAME . "' sudah dibuat dan kredensial di config/database.php sudah benar.");
            }
            // Jika mode 'auto', fallback ke SQLite agar aplikasi tetap langsung jalan
            $connected = false;
        }
    }

    // Fallback atau mode SQLite
    if (!$connected) {
        try {
            $dataDir = __DIR__ . '/../data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0777, true);
            }
            $sqlitePath = $dataDir . '/koperasi.sqlite';
            $dsn = "sqlite:" . $sqlitePath;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Aktifkan foreign keys di SQLite
            $pdo->exec("PRAGMA foreign_keys = ON;");
        } catch (PDOException $e) {
            die("Gagal menginisialisasi Database SQLite: " . $e->getMessage());
        }
    }

    return $pdo;
}

// Inisialisasi koneksi
$db = get_db_connection();

// Jalankan auto installer / database seeder jika tabel belum ada
require_once __DIR__ . '/init_db.php';
initialize_database($db);
