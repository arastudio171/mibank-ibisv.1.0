<?php

/**
 * FILE: modules/nasabah/reset-password.php
 * DESKRIPSI: Logic untuk mereset password/PIN nasabah ke standar bawaan sistem.
 * AKSI: Mengubah password ke default hash, reset salah login menjadi 0, dan membuka status lock.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database menggunakan path absolut yang aman
require_once __DIR__ . '/../../auth/database.php';

// 1. VALIDASI AKSES: Hanya Admin dan Superadmin yang berhak mereset password
$allowed_roles = ['admin', 'superadmin'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!in_array($user_role, $allowed_roles)) {
    header("Location: ../../auth/auth-login.php?msg=Akses ditolak! Anda tidak memiliki kewenangan bypass kredensial nasabah.&type=error");
    exit();
}

// 2. Pastikan ada ID Nasabah yang dikirim melalui URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../../main.php?page=nasabah&msg=ID Nasabah tidak valid.&type=error");
    exit();
}

// Amankan ID dengan memastikannya berupa int/string bersih
$id_nasabah = trim($_GET['id']);

// Tetapkan password bawaan sistem (Default Password)
$password_default = "123456";
$password_hashed  = password_hash($password_default, PASSWORD_BCRYPT);

try {
    // 3. VALIDASI KEBERADAAN DATA (Ambil nama nasabah terlebih dahulu)
    $sql_info = "SELECT nama_nasabah FROM tbl_nasabah WHERE id_nasabah = :id";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute(['id' => $id_nasabah]);
    $nasabah = $stmt_info->fetch(PDO::FETCH_ASSOC);

    // JIKA NASABAH TIDAK DITEMUKAN, LANGSUNG TENDANG KELUAR
    if (!$nasabah) {
        header("Location: ../../main.php?page=nasabah&msg=Gagal mereset akun. Data nasabah tidak ditemukan di sistem.&type=error");
        exit();
    }

    $nama_nasabah = $nasabah['nama_nasabah'];

    // 4. UPDATE QUERY: Reset total (Password baru + Nol-kan percobaan gagal + Buka Gembok)
    $sql = "UPDATE tbl_nasabah 
            SET password = :password_baru, 
                pin_failed_attempts = 0, 
                is_locked = 0 
            WHERE id_nasabah = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'password_baru' => $password_hashed,
        'id'            => $id_nasabah
    ]);

    // 5. Catat tindakan pengubahan kredensial ini ke Log sistem
    if (function_exists('writeLog')) {
        writeLog($pdo, "🔑 RESET TOTAL AKUN: Akun nasabah '$nama_nasabah' telah di-unlock & password di-reset ke default oleh " . $_SESSION['role'] . " (" . $_SESSION['nama'] . ")");
    }

    // Redirect sukses
    $pesan_sukses = "Kredensial berhasil dikonfigurasi ulang. Akun nasabah atas nama $nama_nasabah telah aktif kembali, batasan blokir dibersihkan, dan password disetel ke '" . $password_default . "'.";
    header("Location: ../../main.php?page=nasabah&msg=" . urlencode($pesan_sukses) . "&type=success");
    exit();
} catch (PDOException $e) {
    // Catat log error internal untuk kebutuhan debugging developer
    error_log("Reset Password Error: " . $e->getMessage());
    header("Location: ../../main.php?page=nasabah&msg=Terjadi kesalahan internal sistem basisdata saat memperbarui sandi.&type=error");
    exit();
}
