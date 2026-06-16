<?php

/**
 * FILE: modules/operator/reset-password.php
 * DESKRIPSI: Logic untuk mereset password petugas/operator ke standar bawaan sistem (Bypass Instan).
 * AKSI: Mengubah password ke default hash (123456) dan mengembalikan ke halaman utama.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database menggunakan path absolut yang aman
require_once __DIR__ . '/../../auth/database.php';

// 1. VALIDASI AKSES: Hanya Admin yang berhak mereset password petugas
$allowed_roles = ['admin'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!in_array($user_role, $allowed_roles)) {
    echo "<script>window.location.href = '../../auth/auth-login.php?msg=Akses ditolak! Anda tidak memiliki kewenangan bypass kredensial petugas.&type=error';</script>";
    exit();
}

// 2. Pastikan ada ID Petugas yang dikirim melalui URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href = '../../main.php?page=operator&msg=ID Petugas tidak valid.&type=error';</script>";
    exit();
}

// Amankan ID dengan memastikannya berupa integer bersih
$id_user = (int)$_GET['id'];
$id_role_operator = 2; // Mengunci target hanya untuk role Operator

// Tetapkan password bawaan sistem (Default Password)
$password_default = "123456";
$password_hashed  = password_hash($password_default, PASSWORD_BCRYPT);

try {
    // 3. VALIDASI KEBERADAAN DATA (Ambil data petugas terlebih dahulu)
    $sql_info = "SELECT nama_lengkap, username FROM tbl_users WHERE id_user = :id AND id_role = :role";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute(['id' => $id_user, 'role' => $id_role_operator]);
    $operator = $stmt_info->fetch(PDO::FETCH_ASSOC);

    // JIKA PETUGAS TIDAK DITEMUKAN, LANGSUNG TENDANG KELUAR
    if (!$operator) {
        echo "<script>window.location.href = '../../main.php?page=operator&msg=Gagal mereset akun. Data petugas tidak ditemukan atau ilegal.&type=error';</script>";
        exit();
    }

    $nama_lengkap = $operator['nama_lengkap'];
    $username     = $operator['username'];

    // 4. UPDATE QUERY: Reset Password ke Default untuk user ini
    $sql = "UPDATE tbl_users 
            SET password = :password_baru 
            WHERE id_user = :id AND id_role = :role";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'password_baru' => $password_hashed,
        'id'            => $id_user,
        'role'          => $id_role_operator
    ]);

    // 5. Catat tindakan pengubahan kredensial ini ke Log sistem jika fungsi tersedia
    if (function_exists('writeLog')) {
        writeLog($pdo, "🔑 RESET PASSWORD PETUGAS: Akun @$username ($nama_lengkap) telah di-reset ke default oleh " . $_SESSION['role']);
    }

    // Redirect sukses menggunakan JavaScript agar terhindar dari error 'Headers Already Sent'
    $pesan_sukses = "Kredensial petugas atas nama $nama_lengkap (@$username) berhasil dikonfigurasi ulang. Kata sandi disetel kembali ke standar: '" . $password_default . "'.";
    echo "<script>window.location.href = '../../main.php?page=operator&msg=" . urlencode($pesan_sukses) . "&type=success';</script>";
    exit();
} catch (PDOException $e) {
    // Catat log error internal untuk kebutuhan debugging developer
    error_log("Reset Password Operator Error: " . $e->getMessage());
    echo "<script>window.location.href = '../../main.php?page=operator&msg=Terjadi kesalahan internal sistem basisdata saat memperbarui sandi petugas.&type=error';</script>";
    exit();
}
