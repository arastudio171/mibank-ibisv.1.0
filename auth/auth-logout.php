<?php

/**
 * FILE: auth/logout.php
 * DESKRIPSI: Proses logout aman, pembersihan token database, pencatatan audit log, dan penghancuran sesi.
 */

session_start();
require_once 'database.php'; // Diperlukan untuk memproses update token dan log aktivitas

try {
    // 1. AUDIT TRAIL & CLEANUP DATABASE (Dilakukan SEBELUM sesi dihancurkan)
    if (isset($_SESSION['role'])) {
        $username = $_SESSION['username'] ?? 'Unknown';
        $role     = $_SESSION['role'];

        // A. Catat aktivitas logout jika fungsi writeLog tersedia
        if (function_exists('writeLog')) {
            writeLog($pdo, "User {$username} ({$role}) telah melakukan logout");
        }

        // B. Hapus remember_token di database agar fitur 'Remember Me' otomatis mati
        if ($role === 'nasabah' && isset($_SESSION['id_nasabah'])) {
            $stmt = $pdo->prepare("UPDATE tbl_nasabah SET remember_token = NULL WHERE id_nasabah = ?");
            $stmt->execute([$_SESSION['id_nasabah']]);
        } elseif (isset($_SESSION['id_user'])) {
            // Untuk Staff (Admin / Operator)
            $stmt = $pdo->prepare("UPDATE tbl_users SET remember_token = NULL WHERE id_user = ?");
            $stmt->execute([$_SESSION['id_user']]);
        }
    }
} catch (PDOException $e) {
    // Jika database error, simpan log internal namun proses logout di server harus tetap berjalan
    error_log("Gagal membersihkan token saat logout: " . $e->getMessage());
}

// 2. Hapus Cookie "Remember Me" dari browser client (jika aplikasi Anda menggunakannya)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// 3. Hapus semua variabel data di dalam array $_SESSION
$_SESSION = [];

// 4. Hapus Cookie Sesi (PHPSESSID) murni dari browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 5. Hancurkan Sesi secara total di sisi Server
session_destroy();

// 6. Redirect ke halaman login dengan mengirimkan pesan sukses yang aman
$successQuery = http_build_query([
    'msg'  => 'Anda telah berhasil logout dari sistem.',
    'type' => 'success'
]);

header("Location: auth-login.php?" . $successQuery);
exit();
