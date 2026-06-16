<?php

/**
 * FILE: modules/operator/delete-operator.php
 * DESKRIPSI: Sistem Penghapusan Pintar (Hard Delete / Soft Delete otomatis)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat koneksi database pdo Anda
require_once __DIR__ . '/../../auth/database.php';

// 🔒 VALIDASI AKSES BACKEND: Menggunakan JS Redirect menghindari error "Headers Already Sent"
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin' || !isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href = '../../main.php?page=petugas&msg=Akses ditolak! Hak penghapusan dikunci.&type=error';</script>";
    exit();
}

$id_user = (int)$_GET['id'];
$id_role_operator = 2; // Memastikan hanya mengeksekusi user dengan role Operator

try {
    // 🚀 LANGKAH 1: Coba hapus secara permanen (Hard Delete)
    // Klausa id_role = 2 mengunci agar tidak salah menghapus akun Admin/Nasabah
    $stmt_del = $pdo->prepare("DELETE FROM tbl_users WHERE id_user = :id AND id_role = :role");
    $stmt_del->execute(['id' => $id_user, 'role' => $id_role_operator]);

    if ($stmt_del->rowCount() > 0) {
        // Berhasil jika akun bersih belum memiliki riwayat transaksi
        echo "<script>window.location.href = '../../main.php?page=petugas&msg=Data kredensial operator telah dihapus permanen dari sistem.&type=success';</script>";
        exit();
    } else {
        echo "<script>window.location.href = '../../main.php?page=petugas&msg=Data operator tidak ditemukan.&type=error';</script>";
        exit();
    }
} catch (PDOException $e) {
    // 💡 LANGKAH 2: Tangkap error Foreign Key Constraint (SQLSTATE 23000)
    if ($e->getCode() === '23000') {
        try {
            // Otomatis ubah status menjadi nonaktif (Soft Delete) berdasarkan kolom di database Anda
            $stmt_soft = $pdo->prepare("UPDATE tbl_users SET status_akun = 'nonaktif' WHERE id_user = :id AND id_role = :role");
            $stmt_soft->execute(['id' => $id_user, 'role' => $id_role_operator]);

            echo "<script>window.location.href = '../../main.php?page=petugas&msg=Petugas memiliki riwayat keuangan. Hak akses masuk berhasil dinonaktifkan (Soft Delete) demi menjaga integritas jurnal kas.&type=success';</script>";
            exit();
        } catch (PDOException $ex) {
            echo "<script>window.location.href = '../../main.php?page=petugas&msg=Gagal mengubah status otoritas: " . urlencode($ex->getMessage()) . "&type=error';</script>";
            exit();
        }
    } else {
        // Jika ada kesalahan query database lainnya
        echo "<script>window.location.href = '../../main.php?page=petugas&msg=Kegagalan Sistem: " . urlencode($e->getMessage()) . "&type=error';</script>";
        exit();
    }
}
