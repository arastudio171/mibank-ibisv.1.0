<?php

/**
 * Berkas: modules/transaksi/tutup-kas.php
 * Deskripsi: Memproses penutupan laci kas teller secara aman dengan proteksi kepemilikan sesi.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. KONEKSI DATABASE
require_once __DIR__ . '/../../auth/database.php';

// Mendukung POST (sangat direkomendasikan) atau GET dari frontend Anda saat ini
$method = $_SERVER['REQUEST_METHOD'];
$id_jurnal = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

if (($method === 'GET' || $method === 'POST') && $id_jurnal > 0) {

    $user_login_id   = $_SESSION['id_user'] ?? 0;
    $user_login_role = $_SESSION['nama_role'] ?? 'operator';

    try {
        $pdo->beginTransaction();

        // FIX BUG 1: Cari tahu loket terkait, TAPI WAJIB yang status jurnalnya masih 'open'
        // Ini mengunci agar jurnal yang sudah closed tidak bisa memanipulasi loket aktif saat ini
        $stmt_find = $pdo->prepare("SELECT id_loket, id_user FROM tbl_jurnal_kas WHERE id_jurnal = :id_jurnal AND status_jurnal = 'open'");
        $stmt_find->execute([':id_jurnal' => $id_jurnal]);
        $jurnal = $stmt_find->fetch(PDO::FETCH_ASSOC);

        if (!$jurnal) {
            // Jika jurnal tidak ditemukan atau statusnya memang sudah 'closed'
            throw new Exception("Laci kas sudah ditutup sebelumnya atau ID tidak valid.");
        }

        // FIX BUG 2: VALIDASI FIREWALL OTORISASI
        // Jika yang login adalah OPERATOR, pastikan ID User di jurnal adalah milik dirinya sendiri
        if ($user_login_role === 'operator' && intval($jurnal['id_user']) !== intval($user_login_id)) {
            throw new Exception("Anda tidak memiliki hak akses untuk menutup laci kas teller lain!");
        }

        $id_loket_terkait = $jurnal['id_loket'];

        // 2. Tutup jurnal kas & catat waktu closing secara real-time
        $query_close = "UPDATE tbl_jurnal_kas SET 
                            status_jurnal = 'closed', 
                            waktu_tutup = NOW() 
                        WHERE id_jurnal = :id_jurnal";
        $stmt_close = $pdo->prepare($query_close);
        $stmt_close->execute([':id_jurnal' => $id_jurnal]);

        // 3. Ubah status loket menjadi tutup dan bebaskan loket (set petugas menjadi NULL)
        $query_reset_loket = "UPDATE tbl_loket SET 
                                status_loket = 'tutup', 
                                id_petugas = NULL 
                              WHERE id_loket = :id_loket";
        $stmt_reset = $pdo->prepare($query_reset_loket);
        $stmt_reset->execute([':id_loket' => $id_loket_terkait]);

        $pdo->commit();

        // Alihkan kembali ke halaman manajemen kas dengan pesan sukses
        header("Location: ../../main.php?page=kas-teller&msg=sukses_tutup");
        exit;
    } catch (Exception $e) {
        // Batalkan semua perubahan jika terjadi error di tengah jalan
        $pdo->rollBack();

        // Alihkan kembali dengan membawa pesan galat/error demi kemudahan debug
        header("Location: ../../main.php?page=kas-teller&msg=gagal_tutup&reason=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('HTTP/1.1 403 Forbidden');
    echo "Akses dilarang atau parameter tidak lengkap.";
    exit;
}
