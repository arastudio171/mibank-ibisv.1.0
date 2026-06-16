<?php

/**
 * FILE: modules/nasabah/update-password.php
 * DESKRIPSI: Logic mandiri bagi nasabah untuk mengubah password akun mereka sendiri.
 * AKSI: Validasi password lama, verifikasi kesesuaian password baru, dan enkripsi hash BCRYPT.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database menggunakan path absolut yang aman
require_once __DIR__ . '/../../auth/database.php';

// 1. VALIDASI AKSES: Pastikan yang mengakses adalah user/nasabah yang sudah login
// *Catatan: Sesuaikan key session login nasabah Anda (misal: id_nasabah atau user_id)*
if (!isset($_SESSION['id_nasabah'])) {
    header("Location: ../../auth/auth-login.php?msg=Silakan login terlebih dahulu.&type=error");
    exit();
}

$id_nasabah = $_SESSION['id_nasabah'];

// 2. PASTIKAN DATA DIKIRIM LEWAT METHOD POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil data dari form dan bersihkan spasi (trim)
    $pw_lama = isset($_POST['pw_lama']) ? trim($_POST['pw_lama']) : '';
    $pw_baru = isset($_POST['pw_baru']) ? trim($_POST['pw_baru']) : '';
    $pw_konf = isset($_POST['pw_konf']) ? trim($_POST['pw_konf']) : '';

    // --- VALIDASI INPUT ---

    // A. Pastikan semua field terisi
    if (empty($pw_lama) || empty($pw_baru) || empty($pw_konf)) {
        $_SESSION['error_msg'] = "Semua kolom password wajib diisi!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // B. Validasi panjang minimal password baru (sesuai hint HTML: minimal 8 karakter)
    if (strlen($pw_baru) < 8) {
        $_SESSION['error_msg'] = "Password baru terlalu pendek! Gunakan minimal 8 karakter.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // C. Pastikan password baru dan konfirmasi cocok
    if ($pw_baru !== $pw_konf) {
        $_SESSION['error_msg'] = "Verifikasi ulang password tidak cocok dengan password baru Anda.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    try {
        // 3. VERIFIKASI PASSWORD LAMA KEDATABASE
        $sql = "SELECT password, nama_nasabah FROM tbl_nasabah WHERE id_nasabah = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id_nasabah]);
        $nasabah = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nasabah) {
            $_SESSION['error_msg'] = "Data akun Anda tidak ditemukan di sistem.";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }

        // Cek apakah password lama yang diinput cocok dengan hash di database
        if (!password_verify($pw_lama, $nasabah['password'])) {
            $_SESSION['error_msg'] = "Password lama yang Anda masukkan salah!";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }

        // --- PROSES UPDATE ---

        // Hash password baru dengan algoritma BCRYPT yang aman
        $password_baru_hashed = password_hash($pw_baru, PASSWORD_BCRYPT);

        $sql_update = "UPDATE tbl_nasabah SET password = :password_baru WHERE id_nasabah = :id";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            'password_baru' => $password_baru_hashed,
            'id'            => $id_nasabah
        ]);

        // 4. Catat aktivitas perubahan keamanan ini ke Log sistem
        if (function_exists('writeLog')) {
            writeLog($pdo, "🔐 SELF UPDATE: Nasabah bernama '" . $nasabah['nama_nasabah'] . "' (ID: $id_nasabah) berhasil memperbarui password keamanannya.");
        }

        // Set pesan sukses ke session
        $_SESSION['success_msg'] = "Berhasil! Kata sandi akun Anda telah diperbarui.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    } catch (PDOException $e) {
        error_log("Update Password Self Error: " . $e->getMessage());
        $_SESSION['error_msg'] = "Terjadi kesalahan sistem internal saat memproses data.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
} else {
    // Jika mencoba akses langsung file ini tanpa POST method
    header("Location: ../../main.php");
    exit();
}
