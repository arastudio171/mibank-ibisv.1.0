<?php

/**
 * FILE: modules/nasabah/hapus-nasabah.php
 * DESKRIPSI: Logic untuk menghapus data nasabah secara permanen.
 * AKSI: Menghapus data nasabah di tbl_nasabah serta data terkait (mutasi/target) dalam satu transaksi aman.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database menggunakan path absolut yang aman
require_once __DIR__ . '/../../auth/database.php';

// 1. VALIDASI AKSES SKALA TINGGI: Hanya Admin dan Superadmin yang boleh menghapus secara permanen
$allowed_roles = ['admin', 'superadmin'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!in_array($user_role, $allowed_roles)) {
    header("Location: ../../auth/auth-login.php?msg=Akses ditolak! Tindakan destruktif ini memerlukan hak akses tingkat tinggi (Admin/Superadmin).&type=error");
    exit();
}

// 2. Pastikan ada ID Nasabah yang dikirim melalui URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../../main.php?page=nasabah&msg=ID Nasabah tidak valid atau tidak disertakan.&type=error");
    exit();
}

$id_nasabah = $_GET['id'];

try {
    // 3. MULAI TRANSAKSI BASISDATA (Mencegah data corrupt jika salah satu query gagal)
    $pdo->beginTransaction();

    // A. Ambil nama nasabah terlebih dahulu untuk keperluan pencatatan LOG system sebelum datanya hilang
    $sql_info = "SELECT nama_nasabah FROM tbl_nasabah WHERE id_nasabah = :id";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute(['id' => $id_nasabah]);
    $nasabah = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$nasabah) {
        // Jika data nasabah tidak ditemukan di sistem
        $pdo->rollBack();
        header("Location: ../../main.php?page=nasabah&msg=Gagal menghapus. Data nasabah tidak ditemukan atau sudah dihapus sebelumnya.&type=error");
        exit();
    }

    $nama_terhapus = $nasabah['nama_nama_nasabah'] ?? $nasabah['nama_nasabah'];

    // B. Hapus riwayat mutasi tabungan terlebih dahulu (Menghindari Foreign Key Constraint Error)
    // *Catatan: Sesuaikan nama tabel riwayat transaksi Anda jika berbeda (misal: tbl_transaksi / tbl_mutasi)*
    $sql_mutasi = "DELETE FROM tbl_mutasi WHERE id_nasabah = :id";
    $stmt_mutasi = $pdo->prepare($sql_mutasi);
    $stmt_mutasi->execute(['id' => $id_nasabah]);

    // C. Hapus data utama nasabah dari tabel biodata/nasabah
    $sql_nasabah = "DELETE FROM tbl_nasabah WHERE id_nasabah = :id";
    $stmt_nasabah = $pdo->prepare($sql_nasabah);
    $stmt_nasabah->execute(['id' => $id_nasabah]);

    // Jika proses penghapusan di tabel utama berhasil
    if ($stmt_nasabah->rowCount() > 0) {

        // KUNCI DAN JALANKAN TRANSAKSI
        $pdo->commit();

        // 4. Catat tindakan krusial ini ke Log sistem
        if (function_exists('writeLog')) {
            writeLog($pdo, "⚠️ PERMANENT DELETE: Akun & Riwayat Mutasi Nasabah bernama '$nama_terhapus' (ID: $id_nasabah) TELAH DIHAPUS oleh " . $_SESSION['role'] . " (" . $_SESSION['nama'] . ")");
        }

        $pesan_sukses = "Data nasabah atas nama " . $nama_terhapus . " beserta seluruh riwayat saldo dan mutasi tabungannya telah berhasil dihapus secara permanen dari database.";
        header("Location: ../../main.php?page=nasabah&msg=" . urlencode($pesan_sukses) . "&type=success");
    } else {
        // Jika query lolos tapi tidak ada baris yang terhapus
        $pdo->rollBack();
        header("Location: ../../main.php?page=nasabah&msg=Gagal mengeksekusi penghapusan data utama.&type=error");
    }
    exit();
} catch (PDOException $e) {
    // Batalkan semua perubahan jika di tengah jalan ada error SQL
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Penghapusan Nasabah Error: " . $e->getMessage());
    header("Location: ../../main.php?page=nasabah&msg=Terjadi kesalahan internal pada sistem basisdata saat menghapus data.&type=error");
    exit();
}
