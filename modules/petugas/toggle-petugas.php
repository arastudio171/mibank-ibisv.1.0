<?php

/**
 * FILE: modules/operator/toggle-petugas.php
 * DESKRIPSI: Toggle Aktif/Nonaktif Petugas (Sinkron 100% dengan tbl_users)
 */

// 🛠️ PANDUAN DEBUGGING: Jika masih blank, hapus tanda komentar (//) di 2 baris bawah ini untuk melihat erornya:
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔍 KONEKSI DATABASE: Pastikan file database.php Anda ada di folder 'auth'
$database_path = __DIR__ . '/../../auth/database.php';
if (!file_exists($database_path)) {
    die("<b>Eror Jalur File:</b> File database tidak ditemukan di: <code>" . htmlspecialchars($database_path) . "</code>");
}
require_once $database_path;

// 🔑 SINKRONISASI HALAMAN: Mengikuti parameter ?page=operator milik Anda
$redirect_page = 'petugas';

// 🔒 VALIDASI AKSES: Hanya Admin yang boleh mengubah status akun petugas
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin' || !isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href = '../../main.php?page=$redirect_page&msg=Akses ditolak! Sesi tidak valid.&type=error';</script>";
    exit();
}

$id_user = (int)$_GET['id'];
$id_role_operator = 2; // Sesuai dengan id_role petugas di sistem Anda

try {
    // 1. Ambil status_akun saat ini dari tbl_users
    // Catatan: Jika di file database.php Anda menggunakan nama variabel '$conn', ganti '$pdo' di bawah menjadi '$conn'
    $stmt = $pdo->prepare("SELECT status_akun FROM tbl_users WHERE id_user = :id AND id_role = :role");
    $stmt->execute(['id' => $id_user, 'role' => $id_role_operator]);
    $current_status = $stmt->fetchColumn();

    if ($current_status !== false) {
        // 2. Balikkan nilai ENUM ('aktif' <=> 'nonaktif')
        $status_baru = ($current_status === 'aktif') ? 'nonaktif' : 'aktif';

        // 3. Update status baru ke tbl_users
        $stmt_update = $pdo->prepare("UPDATE tbl_users SET status_akun = :status WHERE id_user = :id AND id_role = :role");
        $stmt_update->execute(['status' => $status_baru, 'id' => $id_user, 'role' => $id_role_operator]);

        // 4. Redirect kembali ke halaman tabel operator dengan aman
        $alert_type = ($status_baru === 'aktif') ? 'success' : 'warning';
        echo "<script>window.location.href = '../../main.php?page=$redirect_page&msg=Status akun petugas berhasil diubah menjadi " . strtoupper($status_baru) . "!&type=$alert_type';</script>";
        exit();
    } else {
        // Jika ID User ditemukan tapi id_role-nya BUKAN 2 (Bukan Operator, misal ID Admin)
        echo "<script>window.location.href = '../../main.php?page=$redirect_page&msg=Data petugas tidak ditemukan atau Anda mencoba mengubah akun sesama Admin!&type=error';</script>";
        exit();
    }
} catch (PDOException $e) {
    die("<b>Kegagalan Database:</b> " . $e->getMessage() . "<br><i>Tips: Periksa apakah nama variabel di database.php Anda adalah \$pdo atau \$conn!</i>");
}
