<?php

/**
 * FILE: auth/database.php
 * DESKRIPSI: Koneksi database menggunakan PDO dengan proteksi SQL Injection & XSS Sanitasi.
 * FITUR: Audit Trail (Logging Aktivitas), Error Handling Modern, & Timezone Sync.
 */

$timezone = "Asia/Jakarta";
$host     = "localhost";
$username = "root";
$password = ""; // Sesuaikan dengan password database Anda
$db_name  = "mibank_db";

try {
    date_default_timezone_set($timezone);

    $pdo = new PDO("mysql:host={$host};dbname={$db_name};charset=utf8mb4", $username, $password);

    // Set konfigurasi keamanan PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Mengaktifkan mode error sebagai exception
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Data dikembalikan sebagai array asosiatif
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Memaksa penggunaan native prepared statements

} catch (PDOException $e) {
    // Jangan tampilkan detail error database ke user publik (Gunakan error_log)
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Maaf, sistem sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.");
}

/**
 * 🔒 AUDIT TRAIL ENGINE (writeLog)
 * Dipindahkan ke luar blok try-catch utama untuk mencegah crash redundansi fungsi.
 * Dilengkapi dengan sanitasi anti XSS injection pada komponen server global.
 */
if (!function_exists('writeLog')) {
    function writeLog($pdo, $aktivitas)
    {
        try {
            // 1. Mengambil ID pelakunya (bisa staff atau nasabah)
            $id_user    = $_SESSION['id_user'] ?? null;
            $id_nasabah = $_SESSION['id_nasabah'] ?? null;
            $role       = $_SESSION['role'] ?? 'guest';

            // 2. Ambil IP Address (Gunakan fallback aman jika dibaca via proxy/cloudflare)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';

            // 3. 🔒 PROTEKSI ANTI-XSS: Sanitasi User Agent sebelum masuk ke database log
            $raw_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User Agent';
            $user_agent = htmlspecialchars(strip_tags($raw_user_agent), ENT_QUOTES, 'UTF-8');

            // 4. Eksekusi Log via Prepared Statements
            $sql = "INSERT INTO log_activity (id_user, id_nasabah, role_pelaku, aktivitas, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_user, $id_nasabah, $role, $aktivitas, $ip, $user_agent]);
        } catch (PDOException $e) {
            // Log error ke file sistem jika database log gagal (tidak menghentikan aplikasi)
            error_log("Gagal menulis log: " . $e->getMessage());
        }
    }
}
