<?php

/**
 * FILE: modules/nasabah/toggle-status.php
 * DESKRIPSI: Logic dinamis untuk mengaktifkan atau menonaktifkan akun nasabah.
 * AKSI: Mengubah status_nasabah menjadi 'aktif' atau 'nonaktif' sesuai parameter URL.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database menggunakan path absolut yang aman
require_once __DIR__ . '/../../auth/database.php';

// 1. VALIDASI AKSES: Hanya mengizinkan Admin dan Superadmin
$allowed_roles = ['admin', 'superadmin'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!in_array($user_role, $allowed_roles)) {
    header("Location: ../../auth/auth-login.php?msg=Akses ditolak! Anda tidak memiliki otoritas (Otorisasi Khusus Admin/Superadmin) untuk mengubah status nasabah.&type=error");
    exit();
}

// 2. VALIDASI PARAMETER: Pastikan ID Nasabah dan Action dikirim melalui URL
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['action']) || empty($_GET['action'])) {
    header("Location: ../../main.php?page=nasabah&msg=Parameter data tidak lengkap.&type=error");
    exit();
}

$id_nasabah = $_GET['id'];
$action     = strtolower($_GET['action']);

// Validasi nilai action agar tidak dimanipulasi lewat URL
if (!in_array($action, ['aktif', 'nonaktif'])) {
    header("Location: ../../main.php?page=nasabah&msg=Aksi tidak valid.&type=error");
    exit();
}

try {
    // 3. UPDATE LOGIK DINAMIS: Mengubah status berdasarkan parameter action
    // Hanya update jika status di DB berbeda dengan status baru (mencegah redundansi rowCount)
    $sql = "UPDATE tbl_nasabah 
            SET status_nasabah = :status_baru 
            WHERE id_nasabah = :id AND status_nasabah != :status_sekarang";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'status_baru'     => $action,
        'id'              => $id_nasabah,
        'status_sekarang' => $action
    ]);

    // Jika ada baris yang berubah, berarti perubahan status berhasil
    if ($stmt->rowCount() > 0) {

        // 4. Catat ke Log sistem dengan keterangan aksi yang jelas
        $status_teks = ($action === 'aktif') ? 'DIAKTIFKAN' : 'DINONAKTIFKAN';
        if (function_exists('writeLog')) {
            writeLog($pdo, "Akun Nasabah ID $id_nasabah telah $status_teks oleh " . $_SESSION['role'] . " (" . $_SESSION['nama'] . ")");
        }

        // Siapkan pesan sukses sesuai aksi yang dilakukan
        if ($action === 'aktif') {
            $pesan_sukses = "Sistem berhasil memverifikasi data. Akun nasabah kini telah AKTIF sepenuhnya dan sudah dapat digunakan untuk transaksi.";
        } else {
            $pesan_sukses = "Akun nasabah berhasil ditiadakan sementara (NONAKTIF). Siswa tidak akan dapat melakukan transaksi hingga diaktifkan kembali.";
        }

        header("Location: ../../main.php?page=nasabah&msg=" . urlencode($pesan_sukses) . "&type=success");
    } else {
        header("Location: ../../main.php?page=nasabah&msg=Gagal memproses. Akun nasabah sudah berada pada status tersebut atau ID tidak terdaftar.&type=error");
    }
    exit();
} catch (PDOException $e) {
    error_log("Toggle Status Error: " . $e->getMessage());
    header("Location: ../../main.php?page=nasabah&msg=Terjadi kesalahan sistem basisdata.&type=error");
    exit();
}
