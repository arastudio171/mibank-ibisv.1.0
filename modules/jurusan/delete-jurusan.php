<?php

/**
 * FILE: modules/jurusan/delete-jurusan.php
 * DESKRIPSI: Logika backend proses hapus master data jurusan dengan proteksi relasi DB.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database (naik 2 tingkat dari folder modules/jurusan/ ke root)
require_once __DIR__ . '/../../auth/database.php';

// 1. VALIDASI AKSES OTORITAS
$allowed_roles = ['admin', 'superadmin'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!in_array($user_role, $allowed_roles)) {
    $msg = urlencode("Akses ditolak! Anda tidak memiliki kewenangan menghapus data master.");
    header("Location: ../../auth/auth-login.php?msg=$msg&type=error");
    exit();
}

// 2. VALIDASI PARAMETER ID
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    $msg = urlencode("Parameter ID tidak valid atau tidak ditemukan.");
    header("Location: ../../main.php?page=jurusan&msg=$msg&type=error");
    exit();
}

$id_jurusan = (int)$_GET['id'];

try {
    // 3. DOUBLE PROTEKSI: Cek apakah jurusan ini masih mengikat nasabah aktif di database
    $sql_check = "SELECT COUNT(*) FROM tbl_nasabah WHERE id_jurusan = :id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['id' => $id_jurusan]);
    $nasabah_count = (int)$stmt_check->fetchColumn();

    if ($nasabah_count > 0) {
        // Gagalkan proses jika tombol di-bypass paksa oleh user melalui URL injection
        $msg = urlencode("Gagal menghapus! Master jurusan terikat aman dengan $nasabah_count nasabah aktif.");
        header("Location: ../../main.php?page=jurusan&msg=$msg&type=error");
        exit();
    }

    // 4. AMBIL INFORMASI KODE JURUSAN UNTUK KEPERLUAN PENCATATAN LOG
    $sql_info = "SELECT kode_jurusan, nama_jurusan FROM tbl_jurusan WHERE id_jurusan = :id";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute(['id' => $id_jurusan]);
    $data_jurusan = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$data_jurusan) {
        $msg = urlencode("Data master jurusan tidak ditemukan atau sudah dihapus sebelumnya.");
        header("Location: ../../main.php?page=jurusan&msg=$msg&type=error");
        exit();
    }

    $kode_lama = $data_jurusan['kode_jurusan'];
    $nama_lama = $data_jurusan['nama_jurusan'];

    // 5. EKSEKUSI PENGHAPUSAN PERMANEN
    $sql_delete = "DELETE FROM tbl_jurusan WHERE id_jurusan = :id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute(['id' => $id_jurusan]);

    // 6. TRACE LOG KE SYSTEM LOG mibank
    if (function_exists('writeLog')) {
        writeLog($pdo, "🗑️ HAPUS JURUSAN: Berhasil menghapus master kompetensi [$kode_lama] $nama_lama secara permanen.");
    }

    // Redirect kembali ke tabel utama dengan status sukses
    $pesan_sukses = "Master data jurusan ($kode_lama) berhasil dihapus dari sistem.";
    header("Location: ../../main.php?page=jurusan&msg=" . urlencode($pesan_sukses) . "&type=success");
    exit();
} catch (PDOException $e) {
    // Catat log error internal server jika query bermasalah
    error_log("Error Delete Master Jurusan ID {$id_jurusan}: " . $e->getMessage());

    $msg = urlencode("Gagal menghapus data akibat kendala relasi database internal server.");
    header("Location: ../../main.php?page=jurusan&msg=$msg&type=error");
    exit();
}
