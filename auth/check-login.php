<?php

/**
 * FILE: auth/check_login.php
 * DESKRIPSI: Verifikasi Login dengan Proteksi Brute Force (Staff & Nasabah), Perlindungan Sesi, dan Validasi Status Akun.
 */

session_start();
require_once 'database.php';

// Helper fungsi untuk mempermudah redirect dengan query string yang aman (urlencoded)
function redirectWithMsg($message, $type = 'error')
{
    $queryString = http_build_query([
        'msg'  => $message,
        'type' => $type
    ]);
    header("Location: auth-login.php?" . $queryString);
    exit();
}

// 1. Mencegah akses langsung dari URL (Harus POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: auth-login.php");
    exit();
}

// 2. Ambil dan Sanitasi Input
$user_input = trim($_POST['username'] ?? '');
$pass_input = $_POST['password'] ?? '';

if (empty($user_input) || empty($pass_input)) {
    redirectWithMsg("Username atau PIN tidak boleh kosong", "error");
}

try {
    // ==========================================
    // A. CEK LOGIN STAFF (Admin / Operator / Supervisor)
    // ==========================================
    $sql_staff = "SELECT u.*, r.nama_role 
                  FROM tbl_users u 
                  JOIN tbl_roles r ON u.id_role = r.id_role 
                  WHERE u.username = :usr LIMIT 1";
    $stmt = $pdo->prepare($sql_staff);
    $stmt->execute(['usr' => $user_input]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika username staff ditemukan di sistem
    if ($staff) {
        // 🔒 PENGUATAN BARU: Cek status pemblokiran brute force akun staff
        if (isset($staff['login_failed_attempts']) && (int)$staff['login_failed_attempts'] >= 5) {
            redirectWithMsg("Akun petugas terkunci sementara karena 5x salah password. Hubungi Superadmin.", "error");
        }

        if (password_verify($pass_input, $staff['password'])) {
            if ($staff['status_akun'] !== 'aktif') {
                redirectWithMsg("Akun petugas dinonaktifkan", "error");
            }

            // PENGUATAN: Reset angka kegagalan dan perbarui waktu login terakhir staff
            $update_staff_login = "UPDATE tbl_users SET login_failed_attempts = 0, last_login = NOW() WHERE id_user = :id";
            $pdo->prepare($update_staff_login)->execute(['id' => $staff['id_user']]);

            // PENGUATAN: Regenerasi Session ID (Anti Session Fixation)
            session_regenerate_id(true);

            // Set Session Staff
            $_SESSION['id_user']    = $staff['id_user'];
            $_SESSION['username']   = $staff['username'];
            $_SESSION['nama']       = $staff['nama_lengkap'];
            $_SESSION['role']       = $staff['nama_role'];
            $_SESSION['nisn']       = null;

            // 🔒 PENINGKATAN STABILITAS: Ambil potongan esensial User Agent saja (Nama OS + Browser Inti)
            // Hal ini mencegah admin ter-logout otomatis saat IP dinamis atau core browser update minor
            $_SESSION['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 150);
            $_SESSION['TERAKHIR_AKTIVITAS'] = time(); // Sinkronisasi dengan fitur auto-logout 10 menit

            if (function_exists('writeLog')) {
                writeLog($pdo, "Login berhasil Staff: {$staff['username']}");
            }

            header("Location: ../main.php?page=main");
            exit();
        } else {
            // 🔒 ANTI BRUTE-FORCE STAFF: Catat dan tambahkan angka kegagalan login petugas
            $staff_attempts = (int)($staff['login_failed_attempts'] ?? 0) + 1;
            $update_fail_staff = "UPDATE tbl_users SET login_failed_attempts = :attempts WHERE id_user = :id";
            $pdo->prepare($update_fail_staff)->execute([
                'attempts' => $staff_attempts,
                'id'       => $staff['id_user']
            ]);

            if ($staff_attempts >= 5) {
                redirectWithMsg("Akun petugas terkunci karena 5x salah password. Hubungi Superadmin.", "error");
            } else {
                redirectWithMsg("Username atau PIN salah", "error");
            }
        }
    }

    // ==========================================
    // B. CEK LOGIN NASABAH (Siswa / Anggota)
    // ==========================================
    $sql_nasabah = "SELECT * FROM tbl_nasabah WHERE nisn = :nisn LIMIT 1";
    $stmt_n = $pdo->prepare($sql_nasabah);
    $stmt_n->execute(['nisn' => $user_input]);
    $nasabah = $stmt_n->fetch(PDO::FETCH_ASSOC);

    if ($nasabah) {
        // PENGUATAN: Cek status pemblokiran akun
        if ((int)$nasabah['is_locked'] === 1) {
            redirectWithMsg("Akun terkunci karena terlalu banyak percobaan login. Hubungi Admin.", "error");
        }

        // Verifikasi PIN Nasabah
        if (password_verify($pass_input, $nasabah['password'])) {

            // Cek Status Validasi ENUM ('aktif' / 'nonaktif')
            if ($nasabah['status_nasabah'] === 'nonaktif') {
                redirectWithMsg("Akun dinonaktifkan sementara, menunggu verifikasi.", "info");
            }

            // PENGUATAN: Reset angka kegagalan dan perbarui last_login nasabah
            $reset_sql = "UPDATE tbl_nasabah SET pin_failed_attempts = 0, last_login = NOW() WHERE id_nasabah = :id";
            $pdo->prepare($reset_sql)->execute(['id' => $nasabah['id_nasabah']]);

            // PENGUATAN: Regenerasi Session ID
            session_regenerate_id(true);

            // Set Session Nasabah
            $_SESSION['id_nasabah'] = $nasabah['id_nasabah'];
            $_SESSION['username']   = $nasabah['nisn'];
            $_SESSION['nama']       = $nasabah['nama_nasabah'];
            $_SESSION['role']       = 'nasabah';
            $_SESSION['nisn']       = $nasabah['nisn'];

            // 🔒 PENINGKATAN STABILITAS: Ambil potongan esensial User Agent saja
            $_SESSION['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 150);
            $_SESSION['TERAKHIR_AKTIVITAS'] = time(); // Sinkronisasi dengan fitur auto-logout 10 menit

            if (function_exists('writeLog')) {
                writeLog($pdo, "Nasabah login (NISN: {$nasabah['nisn']})");
            }

            header("Location: ../main.php?page=main");
            exit();
        } else {
            // Anti Brute Force: Tambah angka kegagalan PIN nasabah
            $attempts = (int)$nasabah['pin_failed_attempts'] + 1;
            $is_locked = ($attempts >= 3) ? 1 : 0; // Kunci permanen pada hitungan ke-3

            $fail_sql = "UPDATE tbl_nasabah SET pin_failed_attempts = :attempts, is_locked = :locked WHERE id_nasabah = :id";
            $pdo->prepare($fail_sql)->execute([
                'attempts' => $attempts,
                'locked'   => $is_locked,
                'id'       => $nasabah['id_nasabah']
            ]);

            if ($is_locked) {
                redirectWithMsg("Akun terkunci karena 3x salah PIN. Hubungi Admin.", "error");
            } else {
                redirectWithMsg("Username atau PIN anda tidak ditemukan", "error");
            }
        }
    }

    // ==========================================
    // C. JIKA TIDAK DITEMUKAN DI KEDUANYA
    // ==========================================
    redirectWithMsg("Username atau PIN anda tidak terdaftar", "error");
} catch (PDOException $e) {
    // Log internal server (Aman, tidak membocorkan error ke client)
    error_log("Login System Critical Error: " . $e->getMessage());
    redirectWithMsg("Terjadi gangguan pada server, coba beberapa saat lagi.", "error");
}
