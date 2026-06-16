<?php

/**
 * Berkas: modules/transaksi/buka-kas.php
 * Deskripsi: Memproses pembukaan laci kas teller dengan proteksi multi-sesi dan hak akses.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menghubungkan ke konfigurasi database Anda
require_once __DIR__ . '/../../auth/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitasi input dasar dari form modal
    $id_loket = intval($_POST['id']);
    $modal_awal = floatval($_POST['modal']);

    // --- FITUR KEAMANAN: FIREWALL PROTEKSI SESSION ---
    // Jika role yang login adalah operator, paksa id_user menggunakan ID Session miliknya.
    if (isset($_SESSION['nama_role']) && $_SESSION['nama_role'] === 'operator') {
        // Mengabaikan kiriman $_POST['id_user_teller'] yang bisa dimanipulasi hacker/operator lewat F12
        $id_teller_dipilih = intval($_SESSION['id_user']);
    } else {
        // Jika admin/supervisor, baru izinkan mengambil dari pilihan form dropdown
        $id_teller_dipilih = intval($_POST['id_user_teller']);
    }
    // -------------------------------------------------

    // Validasi Keamanan: Gagalkan jika ada data ID kosong atau modal minus akibat manipulasi URL/Form
    if ($id_loket <= 0 || $id_teller_dipilih <= 0 || $modal_awal < 0) {
        header("Location: ../../main.php?page=kas-teller&msg=gagal_input_tidak_valid");
        exit;
    }

    try {
        // VALIDASI UTAMA: Apakah Teller tersebut sudah memiliki jurnal kas yang masih 'open'?
        // Dioptimalkan untuk memeriksa tbl_jurnal_kas langsung agar sinkron dengan status dashboard
        $stmt_check = $pdo->prepare("SELECT l.nama_loket 
                                     FROM tbl_jurnal_kas jk
                                     JOIN tbl_loket l ON jk.id_loket = l.id_loket
                                     WHERE jk.id_user = :id_user AND jk.status_jurnal = 'open' 
                                     LIMIT 1");
        $stmt_check->execute([':id_user' => $id_teller_dipilih]);
        $loket_aktif = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($loket_aktif) {
            // Jika terdeteksi masih aktif di loket lain, batalkan transaksi dan arahkan kembali dengan pesan error
            $nama_loket_lama = $loket_aktif['nama_loket'];
            header("Location: ../../main.php?page=kas-teller&msg=gagal_sudah_buka&loket_aktif=" . urlencode($nama_loket_lama));
            exit;
        }

        // Memulai Database Transaction untuk menjaga konsistensi data antar-tabel
        $pdo->beginTransaction();

        // 1. Masukkan ke jurnal kas (Inisialisasi akumulator ke 0.00 agar dashboard sinkron)
        // PERBAIKAN: Menyamakan nama placeholder antara query string dengan array execute
        $query_jurnal = "INSERT INTO tbl_jurnal_kas 
                         (id_user, id_loket, saldo_awal_laci, saldo_akhir_laci, total_setoran_tunai, total_penarikan, waktu_buka, status_jurnal) 
                         VALUES (:id_user, :id_loket, :saldo_awal, :saldo_akhir, 0.00, 0.00, NOW(), 'open')";

        $stmt_jurnal = $pdo->prepare($query_jurnal);
        $stmt_jurnal->execute([
            ':id_user'     => $id_teller_dipilih,
            ':id_loket'    => $id_loket,
            ':saldo_awal'  => $modal_awal,
            ':saldo_akhir' => $modal_awal
        ]);

        // 2. Update status loket dan set id_petugas ke ID Teller tersebut
        $query_loket = "UPDATE tbl_loket SET 
                            status_loket = 'buka', 
                            id_petugas = :id_user 
                        WHERE id_loket = :id_loket";
        $stmt_loket = $pdo->prepare($query_loket);
        $stmt_loket->execute([
            ':id_user'  => $id_teller_dipilih,
            ':id_loket' => $id_loket
        ]);

        // Komit perubahan jika kedua query di atas sukses tanpa kendala
        $pdo->commit();

        // Alihkan kembali ke halaman utama manajemen kas dengan sinyal sukses
        header("Location: ../../main.php?page=kas-teller&msg=sukses_buka");
        exit;
    } catch (Exception $e) {
        // Jika salah satu query gagal, batalkan semua perubahan data di database (Rollback)
        $pdo->rollBack();
        die("Gagal memproses pembukaan laci kas: " . $e->getMessage());
    }
} else {
    // Jika file diakses langsung via URL tanpa metode POST (Akses Ilegal)
    header("Location: ../../main.php?page=kas-teller");
    exit;
}
