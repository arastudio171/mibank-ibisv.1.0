<?php

/**
 * FILE: modules/nasabah/aktivasi-nasabah.php
 * DESKRIPSI: Logic untuk mengaktifkan akun nasabah yang baru daftar / nonaktif.
 * AKSI: Mengubah status_nasabah dari 'nonaktif' menjadi 'aktif'.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database menggunakan path absolut yang aman
require_once __DIR__ . '/../../auth/database.php';

// 1. VALIDASI AKSES: Mengizinkan Admin, Supervisor, Auditor, dan Operator
$allowed_roles = ['admin', 'supervisor', 'auditor', 'operator'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!in_array($user_role, $allowed_roles)) {
    header("Location: ../../auth/auth-login.php?msg=Akses ditolak! Anda tidak memiliki otoritas untuk mengaktifkan nasabah.&type=error");
    exit();
}

// 2. Pastikan ada ID Nasabah yang dikirim melalui URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../../main.php?page=nasabah&msg=ID Nasabah tidak ditemukan.&type=error");
    exit();
}

$id_nasabah = $_GET['id'];

try {
    // 3. UPDATE LOGIK BARU: Mengubah dari 'nonaktif' ke 'aktif' (Tanpa setoran_awal)
    $sql = "UPDATE tbl_nasabah 
            SET status_nasabah = 'aktif' 
            WHERE id_nasabah = :id AND status_nasabah = 'nonaktif'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id_nasabah]);

    // Jika ada baris yang berubah, berarti aktivasi berhasil
    if ($stmt->rowCount() > 0) {

        // 4. Catat ke Log sistem
        if (function_exists('writeLog')) {
            writeLog($pdo, "Akun Nasabah ID $id_nasabah diaktifkan oleh " . $_SESSION['role'] . " (" . $_SESSION['nama'] . ")");
        }

        header("Location: ../../main.php?page=nasabah&msg=Sistem berhasil memverifikasi data. Akun nasabah kini telah AKTIF sepenuhnya dan sudah dapat digunakan untuk melakukan transaksi penabungan.&type=success");
    } else {
        header("Location: ../../main.php?page=nasabah&msg=Permintaan ditolak. Akun nasabah gagal diaktifkan karena statusnya sudah aktif atau ID nasabah tidak terdaftar di sistem.&type=error");
    }
    exit();
} catch (PDOException $e) {
    error_log("Aktivasi Error: " . $e->getMessage());
    header("Location: ../../main.php?page=nasabah&msg=Terjadi kesalahan sistem basisdata.&type=error");
    exit();
}
