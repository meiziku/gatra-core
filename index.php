<?php
/**
 * Gateway Redirect Index
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helper.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user['role'] === 'admin') {
        header('Location: ' . base_url('admin/index.php'));
        exit;
    } else {
        header('Location: ' . base_url('anggota/index.php'));
        exit;
    }
} else {
    header('Location: ' . base_url('login.php'));
    exit;
}
