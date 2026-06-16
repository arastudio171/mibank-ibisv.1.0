<?php

/**
 * FILE: index.php
 * DESKRIPSI: Gateway utama dengan lapisan keamanan tinggi (Financial Grade Security)
 * FITUR: Session Hardening, Security Headers, & Anti-Hijacking.
 */

// Mengatur parameter cookie session sebelum session dimulai
ini_set('session.cookie_httponly', 1); // Mencegah akses JavaScript ke cookie session (Anti-XSS)
ini_set('session.use_only_cookies', 1); // Memaksa penggunaan cookie saja
ini_set('session.cookie_samesite', 'Strict'); // Mencegah CSRF antar situs

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1); // Hanya kirim cookie lewat HTTPS
}

session_start();

// Proteksi terhadap Clickjacking, XSS, dan MIME Sniffing
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
header("Referrer-Policy: strict-origin-when-cross-origin");

/**
 * Verifikasi Sidik Jari Browser (Fingerprint)
 * Jika IP atau User Agent berubah mendadak, hancurkan session demi keamanan.
 */
$fingerprint = md5($_SERVER['HTTP_USER_AGENT'] . (ip2long($_SERVER['REMOTE_ADDR']) & ip2long('255.255.255.0')));

if (isset($_SESSION['auth_fingerprint'])) {
    if ($_SESSION['auth_fingerprint'] !== $fingerprint) {
        // Terjadi indikasi pembajakan session
        session_unset();
        session_destroy();
        header("Location: auth/auth-login.php?msg=session_invalid&type=error");
        exit();
    }
} else {
    // Set fingerprint saat login pertama kali terdeteksi nanti
    if (isset($_SESSION['role'])) {
        $_SESSION['auth_fingerprint'] = $fingerprint;
    }
}

// Otomatis logout setelah 30 menit tidak ada aktivitas
$timeout_duration = 1800; // 30 menit
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: auth/auth-login.php?msg=timeout&type=error");
    exit();
}
$_SESSION['last_activity'] = time();

/**
 * Mencegah Session Fixation dengan regenerasi ID secara berkala
 */
if (isset($_SESSION['role'])) {
    // Regenerasi ID setiap kali masuk ke dashboard untuk keamanan ekstra
    if (!isset($_SESSION['needs_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['needs_regeneration'] = false;
    }

    // Redirect ke sistem utama
    header("Location: main.php");
    exit();
} else {
    // Redirect ke halaman login jika belum terautentikasi
    header("Location: auth/auth-login.php");
    exit();
}

// Menutup eksekusi untuk memastikan tidak ada output bocor
exit();
