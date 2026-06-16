<?php

/**
 * FILE: auth/check-register.php
 * DESKRIPSI: Logika backend registrasi nasabah dengan Penguatan Validasi Data, Anti-Bypass, dan Saldo Awal Dinamis dari Database.
 */

session_start();
require_once 'database.php';

// Helper fungsi untuk mempersingkat redirect dengan query string yang aman
function redirectWithError($message)
{
    $queryString = http_build_query([
        'msg'  => $message,
        'type' => 'error'
    ]);
    header("Location: auth-register.php?" . $queryString);
    exit();
}

// 1. Pastikan request dikirim melalui metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: auth-register.php");
    exit();
}

// 2. Ambil dan sanitasi input (Gunakan strip_tags untuk membersihkan potensi tag HTML)
$nama_nasabah     = trim(strip_tags($_POST['nama_siswa'] ?? ''));
$nama_ibu_kandung = trim(strip_tags($_POST['nama_ibu_kandung'] ?? ''));
$nisn             = trim($_POST['nisn'] ?? '');
$password         = $_POST['password'] ?? '';
$nik              = trim($_POST['nik'] ?? '');
$kelas            = trim($_POST['kelas'] ?? '');
$id_jurusan       = $_POST['id_jurusan'] ?? '';

// 3. Validasi Dasar (Kolom Wajib)
if (empty($nama_nasabah) || empty($nama_ibu_kandung) || empty($nisn) || empty($password) || empty($kelas) || empty($id_jurusan)) {
    redirectWithError("Semua kolom wajib diisi!");
}

// 🔒 PENGUATAN VALIDASI: NISN Wajib Angka Murni (Umumnya 10 Digit)
if (!preg_match("/^[0-9]+$/", $nisn)) {
    redirectWithError("Format NISN tidak valid! Harus berupa angka murni.");
}

// 🔒 PENGUATAN VALIDASI: NIK Wajib Angka Murni jika diisi (Umumnya 16 Digit)
if (!empty($nik) && !preg_match("/^[0-9]+$/", $nik)) {
    redirectWithError("Format NIK tidak valid! Harus berupa angka murni.");
}

// Validasi kesesuaian ENUM Kelas ('X', 'XI', 'XII')
if (!in_array($kelas, ['X', 'XI', 'XII'])) {
    redirectWithError("Tingkatan kelas tidak valid!");
}

// Validasi format ID Jurusan wajib angka
if (!is_numeric($id_jurusan) || (int)$id_jurusan <= 0) {
    redirectWithError("Jurusan yang dipilih tidak valid!");
}

// ✅ VALIDASI PASSWORD: Mendukung Alphanumeric & Simbol (Minimal 8 Karakter)
if (strlen($password) < 8) {
    redirectWithError("Password minimal harus memiliki 8 karakter!");
}

try {
    // 🔒 1. AMBIL SALDO AWAL DEFAULT SECARA DINAMIS DARI DATABASE
    $stmt_config = $pdo->query("SELECT saldo_awal_default FROM tbl_pengaturan LIMIT 1");
    $config = $stmt_config->fetch(PDO::FETCH_ASSOC);

    // Jika karena suatu hal konfigurasi di database kosong, gunakan cadangan (fallback) 25000.00
    $saldo_awal_final = isset($config['saldo_awal_default']) ? $config['saldo_awal_default'] : '25000.00';

    // 🔒 PENGUATAN INTEGRITAS: Cek apakah ID Jurusan benar-benar ada di master data sekolah
    $check_jurusan = $pdo->prepare("SELECT 1 FROM tbl_jurusan WHERE id_jurusan = :id LIMIT 1");
    $check_jurusan->execute(['id' => (int)$id_jurusan]);
    if (!$check_jurusan->fetch()) {
        redirectWithError("Jurusan yang Anda pilih tidak terdaftar di sistem!");
    }

    // 4. Cek apakah NISN sudah terdaftar (Karena UNIQUE KEY)
    $check_nisn_stmt = $pdo->prepare("SELECT 1 FROM tbl_nasabah WHERE nisn = :nisn LIMIT 1");
    $check_nisn_stmt->execute(['nisn' => $nisn]);

    if ($check_nisn_stmt->fetch()) {
        redirectWithError("NISN sudah terdaftar di sistem!");
    }

    // 5. Cek apakah NIK sudah terdaftar (Hanya jika NIK diisi, karena NIK bersifat UNIQUE KEY)
    if (!empty($nik)) {
        $check_nik_stmt = $pdo->prepare("SELECT 1 FROM tbl_nasabah WHERE nik = :nik LIMIT 1");
        $check_nik_stmt->execute(['nik' => $nik]);

        if ($check_nik_stmt->fetch()) {
            redirectWithError("NIK sudah digunakan oleh nasabah lain!");
        }
    }

    // 6. Hash Password 
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 7. Simpan ke Database
    $sql = "INSERT INTO tbl_nasabah 
            (nama_nasabah, nama_ibu_kandung, nisn, password, nik, kelas, id_jurusan, status_nasabah, saldo) 
            VALUES (:nama, :ibu, :nisn, :pass, :nik, :kelas, :jurusan, :status_nasabah, :saldo)";

    $stmt = $pdo->prepare($sql);

    // Bind parameter dengan tipe data yang tepat
    $stmt->bindValue(':nama', $nama_nasabah, PDO::PARAM_STR);
    $stmt->bindValue(':ibu', $nama_ibu_kandung, PDO::PARAM_STR);
    $stmt->bindValue(':nisn', $nisn, PDO::PARAM_STR);
    $stmt->bindValue(':pass', $hashed_password, PDO::PARAM_STR);
    $stmt->bindValue(':nik', !empty($nik) ? $nik : null, !empty($nik) ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':kelas', $kelas, PDO::PARAM_STR);
    $stmt->bindValue(':jurusan', (int)$id_jurusan, PDO::PARAM_INT);
    $stmt->bindValue(':status_nasabah', 'nonaktif', PDO::PARAM_STR); // Menunggu aktivasi admin/petugas
    $stmt->bindValue(':saldo', $saldo_awal_final, PDO::PARAM_STR); // 🌟 SEKARANG DIAMBIL DARI VARIABEL DINAMIS

    if ($stmt->execute()) {
        // Catat ke log audit trail jika fungsi global tersedia
        if (function_exists('writeLog')) {
            writeLog($pdo, "Siswa mendaftar mandiri (NISN: {$nisn}, Nama: {$nama_nasabah}, Saldo Awal: Rp {$saldo_awal_final})");
        }

        $successQuery = http_build_query([
            'msg'  => 'Registrasi Berhasil! Akun Anda menunggu aktivasi Petugas.',
            'type' => 'success'
        ]);
        header("Location: auth-login.php?" . $successQuery);
        exit();
    }
} catch (PDOException $e) {
    // Log error terperinci ke server log internal demi keamanan sistem
    error_log("Database Registration Error: " . $e->getMessage());

    // Berikan pesan general yang aman ke sisi client/user
    redirectWithError("Gagal melakukan registrasi karena gangguan server. Silakan coba beberapa saat lagi.");
}
