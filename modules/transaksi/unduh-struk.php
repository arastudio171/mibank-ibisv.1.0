<?php

/**
 * Aplikasi Mini Bank Sekolah
 * Berkas: modules/transaksi/unduh-struk.php
 * Deskripsi: Engine Generator Unduh PDF Terfinalisasi - Bersih, Rapi, Ultra Modern
 * Penyesuaian: Mengikuti skema cetak-struk (tarik 2500, transfer 1500, petugas, & multi-nasabah)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// HUBUNGKAN KE COMPOSER AUTOLOAD
require_once '../../vendor/autoload.php';

// Hubungkan ke core database PDO aplikasi
require_once '../../auth/database.php';

$id_transaksi = $_GET['id'] ?? null;
$transaksi = null;

if (!$id_transaksi) {
    die("Error: ID Transaksi tidak ditemukan.");
}

// =========================================================================
// STAGE 1 & 2: KONSOLIDASI DATA MANIFEST STRUK (QUERY RELASI MULTI-JOIN)
// =========================================================================
try {
    $query = "SELECT 
                t.id_transaksi, 
                t.kode_transaksi, 
                t.jenis_transaksi, 
                t.jumlah, 
                t.saldo_awal, 
                t.saldo_akhir, 
                t.tanggal_transaksi, 
                t.keterangan,
                t.id_nasabah,
                t.id_nasabah_penerima,
                n_pengirim.nisn AS nisn_pengirim, 
                n_pengirim.nama_nasabah AS nama_pengirim, 
                k_pengirim.nama_kelas AS kelas_pengirim,
                n_penerima.nisn AS nisn_penerima,
                n_penerima.nama_nasabah AS nama_penerima,
                n_penerima.id_nasabah AS id_penerima_murni,
                k_penerima.nama_kelas AS kelas_penerima,
                u.nama_lengkap AS nama_petugas
              FROM tbl_transaksi t
              JOIN tbl_nasabah n_pengirim ON t.id_nasabah = n_pengirim.id_nasabah
              LEFT JOIN tbl_kelas k_pengirim ON n_pengirim.id_kelas = k_pengirim.id_kelas
              LEFT JOIN tbl_nasabah n_penerima ON t.id_nasabah_penerima = n_penerima.id_nasabah
              LEFT JOIN tbl_kelas k_penerima ON n_penerima.id_kelas = k_penerima.id_kelas
              LEFT JOIN tbl_users u ON t.id_petugas = u.id_user
              WHERE t.id_transaksi = ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_transaksi]);
    $transaksi = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $query_safe = "SELECT t.*, n.nisn AS nisn_pengirim, n.nama_nasabah AS nama_pengirim, NULL AS kelas_pengirim, NULL AS nama_penerima, NULL AS nama_petugas 
                       FROM tbl_transaksi t 
                       JOIN tbl_nasabah n ON t.id_nasabah = n.id_nasabah 
                       WHERE t.id_transaksi = ?";
        $stmt_safe = $pdo->prepare($query_safe);
        $stmt_safe->execute([$id_transaksi]);
        $transaksi = $stmt_safe->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        die("Fatal Error Basis Data: " . $ex->getMessage());
    }
}

if (!$transaksi) {
    die("Error: Record data tidak ditemukan.");
}

// =========================================================================
// STAGE 3: LOGIKA BISNIS STRUK ADAPTIF (BIAYA ADMIN & KATEGORI)
// =========================================================================
$id_nasabah_aktif = $_SESSION['id_nasabah'] ?? null;
$jenis = strtolower($transaksi['jenis_transaksi'] ?? '');

// Penentuan Kategori Utama Murni
$kategori_murni = $jenis;
if ($jenis === 'transfer') {
    if (intval($transaksi['id_nasabah_penerima']) === intval($id_nasabah_aktif)) {
        $kategori_murni = 'dana_masuk';
    } else {
        $kategori_murni = 'dana_keluar';
    }
}

// Menghitung Biaya Admin Dinamis Sesuai Aturan Terbaru
$biaya_admin = 0;
if ($kategori_murni === 'tarik') {
    $biaya_admin = 2500;
} elseif ($kategori_murni === 'dana_keluar') {
    $biaya_admin = 1500;
}

// Penentuan Label Teks Nota & Kalkulasi Nominal Bersih Akhir serta Palet Warna
if ($kategori_murni === 'setor') {
    $label_transaksi = "SETORAN TUNAI";
    $label_bersih = "Setoran Bersih";
    $nominal_bersih = $transaksi['jumlah'];

    // Emerald Green
    $r_tema = 16;
    $g_tema = 124;
    $b_tema = 65;
    $r_bg_box = 240;
    $g_bg_box = 253;
    $b_bg_box = 244;
} elseif ($kategori_murni === 'tarik') {
    $label_transaksi = "PENARIKAN TUNAI";
    $label_bersih = "Penarikan Bersih (+Admin)";
    $nominal_bersih = $transaksi['jumlah'] + $biaya_admin;

    // Crimson Rose
    $r_tema = 185;
    $g_tema = 28;
    $b_tema = 28;
    $r_bg_box = 254;
    $g_bg_box = 242;
    $b_bg_box = 242;
} elseif ($kategori_murni === 'dana_masuk') {
    $label_transaksi = "DANA MASUK (TRANSFER)";
    $label_bersih = "Dana Diterima";
    $nominal_bersih = $transaksi['jumlah'];

    // Indigo Blue
    $r_tema = 26;
    $g_tema = 54;
    $b_tema = 130;
    $r_bg_box = 239;
    $g_bg_box = 246;
    $b_bg_box = 255;
} else { // dana_keluar
    $label_transaksi = "DANA KELUAR (TRANSFER)";
    $label_bersih = "Total Debet (+Admin)";
    $nominal_bersih = $transaksi['jumlah'] + $biaya_admin;

    // Royal Purple
    $r_tema = 109;
    $g_tema = 40;
    $b_tema = 217;
    $r_bg_box = 245;
    $g_bg_box = 243;
    $b_bg_box = 255;
}

// Pemisahan Tampilan Informasi: Petugas Loket VS Rekan Transfer
$isLayananLoket = ($kategori_murni === 'setor' || $kategori_murni === 'tarik');
$isLayananTransfer = ($kategori_murni === 'dana_masuk' || $kategori_murni === 'dana_keluar');

// Lokalisasi Hari Kalender Bahasa Indonesia
$hari_list = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$timestamp = strtotime($transaksi['tanggal_transaksi'] ?? date('Y-m-d H:i:s'));
$hari_id = $hari_list[date('l', $timestamp)] ?? date('l', $timestamp);

// =========================================================================
// STAGE 4: ENGINE WATERMARK & GENERATOR CORE PDF
// =========================================================================
class ColoredReceiptPDF extends FPDF
{
    function Header()
    {
        // Cetak Watermark Berputar Pas di Tengah Alur Struk Kertas POS 80mm
        $this->SetFont('Courier', 'B', 22);
        $this->SetTextColor(248, 250, 252); // Sangat samar elegan
        $this->RotatedText(14, 75, 'MINI BANK SEKOLAH', 25);
    }

    function RotatedText($x, $y, $txt, $angle)
    {
        $p = sprintf('matrix(%.2f %.2f %.2f %.2f %.2f %.2f) cm', cos(deg2rad($angle)), sin(deg2rad($angle)), -sin(deg2rad($angle)), cos(deg2rad($angle)), $x * $this->k, ($this->h - $y) * $this->k);
        $this->_out('q');
        $this->_out($p);
        $this->Text(0, 0, $txt);
        $this->_out('Q');
    }
}

// Inisialisasi Kertas Thermal POS Receipt 80mm x 150mm (Panjang dinamis adaptif)
$pdf = new ColoredReceiptPDF('P', 'mm', [80, 150]);
$pdf->SetMargins(5, 6, 5);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 4);

// --- HEADER STRUK ---
$pdf->SetTextColor(30, 58, 138);
$pdf->SetFont('Courier', 'B', 15);
$pdf->Cell(70, 5, 'MINI BANK', 0, 1, 'C');

$pdf->SetTextColor(51, 65, 85);
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(70, 4.5, 'SMK NEGERI 1 INFORMATIKA', 0, 1, 'C');

$pdf->SetTextColor(100, 116, 139);
$pdf->SetFont('Courier', '', 7.5);
$pdf->Cell(70, 3.5, 'Jl. Pendidikan No. 123 | (021) 555-0192', 0, 1, 'C');

// Line Divider 1
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(70, 4, '-----------------------------------------', 0, 1, 'C');

// --- METADATA LOG TRANSAKSI ---
$pdf->SetFont('Courier', '', 8.5);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(30, 4.5, 'No. Referensi', 0, 0, 'L');
$pdf->SetTextColor(30, 58, 138);
$pdf->SetFont('Courier', 'B', 8.5);
$pdf->Cell(40, 4.5, 'YPLP-PGRI-055' . $transaksi['id_transaksi'], 0, 1, 'R');

$pdf->SetFont('Courier', '', 8.5);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(30, 4.5, 'Kode. TRX', 0, 0, 'L');
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFont('Courier', 'B', 8.5);
$pdf->Cell(40, 4.5, $transaksi['kode_transaksi'], 0, 1, 'R');

$pdf->SetFont('Courier', '', 8.5);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(30, 4.5, 'Waktu', 0, 0, 'L');
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(40, 4.5, $hari_id . ' ' . date('d-m-Y H:i:s', $timestamp), 0, 1, 'R');

$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(30, 4.5, 'Status', 0, 0, 'L');
$pdf->SetTextColor(21, 128, 61);
$pdf->SetFont('Courier', 'B', 8.5);
$pdf->Cell(40, 4.5, '[ BERHASIL ]', 0, 1, 'R');

// Kondisional Nama Petugas / Sistem Layanan
$pdf->SetFont('Courier', '', 8.5);
$pdf->SetTextColor(71, 85, 105);
if ($isLayananLoket) {
    $pdf->Cell(30, 4.5, 'Petugas Loket', 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->SetFont('Courier', 'B', 8.5);
    $nama_display_petugas = !empty($transaksi['nama_petugas']) ? $transaksi['nama_petugas'] : 'Kasir Loket';
    $pdf->Cell(40, 4.5, strtoupper($nama_display_petugas), 0, 1, 'R');
} else {
    $pdf->Cell(30, 4.5, 'Sistem Layanan', 0, 0, 'L');
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(40, 4.5, 'TRANSFER ELEKTRONIK', 0, 1, 'R');
}

// Line Divider 2
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(70, 4, '-----------------------------------------', 0, 1, 'C');

// --- MANIFEST REKAMAN IDENTITAS NASABAH ---
if ($isLayananTransfer) {
    // Tampilan Detail Pengirim
    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(25, 4.5, 'NISN Pengirim', 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(40, 4.5, substr($transaksi['nisn_pengirim'] ?? '0000000000', 0, -3), 0, 0, 'R');
    $pdf->SetTextColor(37, 99, 235);
    $pdf->SetFont('Courier', 'B', 8.5);
    $pdf->Cell(5, 4.5, 'xxx', 0, 1, 'R');

    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(25, 4.5, 'Nama Pengirim', 0, 0, 'L');
    $pdf->SetTextColor(30, 58, 138);
    $pdf->SetFont('Courier', 'B', 8.5);
    $pdf->Cell(45, 4.5, strtoupper($transaksi['nama_pengirim']), 0, 1, 'R');

    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(25, 4.5, 'Kelas', 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(45, 4.5, $transaksi['kelas_pengirim'] ?? '-', 0, 1, 'R');

    // Pembatas Halus Internal Dot-Matrix antar nasabah
    $pdf->SetTextColor(226, 232, 240);
    $pdf->Cell(70, 3, '.........................................', 0, 1, 'C');

    // Tampilan Detail Penerima
    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(25, 4.5, 'NISN Penerima', 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(40, 4.5, substr($transaksi['nisn_penerima'] ?? '0000000000', 0, -3), 0, 0, 'R');
    $pdf->SetTextColor(147, 51, 234);
    $pdf->SetFont('Courier', 'B', 8.5);
    $pdf->Cell(5, 4.5, 'xxx', 0, 1, 'R');

    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(25, 4.5, 'Nama Penerima', 0, 0, 'L');
    $pdf->SetTextColor(109, 40, 217);
    $pdf->SetFont('Courier', 'B', 8.5);
    $pdf->Cell(45, 4.5, strtoupper($transaksi['nama_penerima']), 0, 1, 'R');

    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(25, 4.5, 'Kelas', 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(45, 4.5, $transaksi['kelas_penerima'] ?? '-', 0, 1, 'R');
} else {
    // Layout Standar untuk Setor / Tarik Tunai di Loket
    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(20, 4.5, 'NISN', 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(45, 4.5, substr($transaksi['nisn_pengirim'] ?? '0000000000', 0, -3), 0, 0, 'R');
    $pdf->SetTextColor(37, 99, 235);
    $pdf->SetFont('Courier', 'B', 8.5);
    $pdf->Cell(5, 4.5, 'xxx', 0, 1, 'R');

    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(20, 4.5, 'Nasabah', 0, 0, 'L');
    $pdf->SetTextColor(30, 58, 138);
    $pdf->SetFont('Courier', 'B', 8.5);
    $pdf->Cell(50, 4.5, strtoupper($transaksi['nama_pengirim']), 0, 1, 'R');

    $pdf->SetFont('Courier', '', 8.5);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->Cell(20, 4.5, 'Kelas', 0, 0, 'L');
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(50, 4.5, $transaksi['kelas_pengirim'] ?? '-', 0, 1, 'R');
}

// Line Divider 3
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(70, 4, '-----------------------------------------', 0, 1, 'C');

// --- INTEGRASI KOMPONEN KEUANGAN ---
$pdf->SetTextColor($r_tema, $g_tema, $b_tema);
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(35, 5, $label_transaksi, 0, 0, 'L');
$pdf->Cell(35, 5, 'Rp ' . number_format($transaksi['jumlah'], 0, ',', '.'), 0, 1, 'R');

$pdf->SetFont('Courier', '', 8.5);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(35, 4.5, 'Biaya Admin', 0, 0, 'L');
$text_admin = $biaya_admin > 0 ? 'Rp ' . number_format($biaya_admin, 0, ',', '.') : 'Rp 0';
if ($biaya_admin > 0) {
    $pdf->SetTextColor(239, 68, 68);
    $pdf->SetFont('Courier', 'B', 8.5);
}
$pdf->Cell(35, 4.5, $text_admin, 0, 1, 'R');

// Garis Pembatas Halus Internal Dot-Matrix
$pdf->SetTextColor(203, 213, 225);
$pdf->Cell(70, 3, '.........................................', 0, 1, 'C');

$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('Courier', '', 8.5);
$pdf->Cell(35, 4.5, $label_bersih, 0, 0, 'L');
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Courier', 'B', 8.5);
$pdf->Cell(35, 4.5, 'Rp ' . number_format($nominal_bersih, 0, ',', '.'), 0, 1, 'R');

$pdf->Ln(2);

// --- AKUMULASI SALDO BERJALAN NASABAH ---
$pdf->SetFillColor($r_bg_box, $g_bg_box, $b_bg_box);

// Baris Saldo Awal
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('Courier', '', 8.5);
$pdf->Cell(35, 5.5, ' Saldo Awal', 0, 0, 'L', true);
$pdf->Cell(35, 5.5, 'Rp ' . number_format($transaksi['saldo_awal'] ?? 0, 0, ',', '.') . ' ', 0, 1, 'R', true);

// Sinkronisasi logika hitung mutasi saldo berjalan di frontend riwayat
$saldo_akhir_display = (intval($transaksi['id_nasabah']) === intval($id_nasabah_aktif))
    ? $transaksi['saldo_akhir']
    : ($transaksi['saldo_awal'] + $transaksi['jumlah']);

// Baris Saldo Akhir
$pdf->SetTextColor($r_tema, $g_tema, $b_tema);
$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(35, 6, ' SALDO AKHIR', 0, 0, 'L', true);
$pdf->Cell(35, 6, 'Rp ' . number_format($saldo_akhir_display, 0, ',', '.') . ' ', 0, 1, 'R', true);

if (!empty($transaksi['keterangan'])) {
    $pdf->Ln(1);
    $pdf->SetTextColor(148, 163, 184);
    $pdf->SetFont('Courier', 'I', 7.5);
    $pdf->MultiCell(70, 3, 'Catatan: ' . $transaksi['keterangan'], 0, 'L');
}

// Line Divider 4
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(70, 4, '-----------------------------------------', 0, 1, 'C');

// --- FOOTER VALIDASI ---
$pdf->SetFillColor(248, 250, 252);
$pdf->SetTextColor(30, 64, 175);
$pdf->SetFont('Courier', 'B', 8);

$pdf->Cell(70, 4.5, 'PENGESAHAN TRANSAKSI', 0, 1, 'C', true);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('Courier', 'I', 6.5);
$pdf->Cell(70, 3, 'Struk ini adalah bukti transaksi yang sah dan diakui', 0, 1, 'C', true);
$pdf->Cell(70, 3, 'secara administratif oleh Sistem Mini Bank Sekolah.', 0, 1, 'C', true);

$pdf->Ln(3);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetFont('Courier', '', 7.5);
$pdf->Cell(70, 3, '"Budayakan menabung demi masa depan cerah"', 0, 1, 'C');

$pdf->Ln(1);
$pdf->SetTextColor(148, 163, 184);
$pdf->SetFont('Courier', '', 6.5);
$auth_code = "AUTH: MB_" . strtoupper(date('dmy', $timestamp)) . "_VALID";
$pdf->Cell(70, 3, $auth_code, 0, 1, 'C');

// =========================================================================
// STAGE 5: PENAMAAN FILE CUSTOM (nama_nisn_kodetrx_tanggal.pdf)
// =========================================================================
$clean_nama = str_replace(' ', '-', strtolower(trim($transaksi['nama_pengirim'])));
$clean_nisn = $transaksi['nisn_pengirim'] ?? '0000000000';
$clean_kode = $transaksi['kode_transaksi'] ?? '0';
$clean_date = date('dmY', $timestamp);

// Menggabungkan string nama file sesuai format penulisan target
$nama_file_final = "{$clean_nama}_{$clean_nisn}_{$clean_kode}_{$clean_date}.pdf";

// Output file biner PDF memicu instruksi download ('D') otomatis ke browser
$pdf->Output('D', $nama_file_final);
exit;
