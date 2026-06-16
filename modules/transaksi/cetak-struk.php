<?php

/**
 * Aplikasi Mini Bank Sekolah
 * Berkas: modules/transaksi/cetak-struk.php
 * Deskripsi: Modul cetak struk dinamis adaptif untuk printer thermal 80mm
 * Penyesuaian: Mengikuti skema tabel tbl_users (nama_lengkap) & relasi transfer/petugas murni.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke core database PDO aplikasi
require_once '../../auth/database.php';

$id_transaksi = $_GET['id'] ?? null;
$transaksi = null;

if (!$id_transaksi) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px; color:#dc2626;'><strong>Error:</strong> ID Transaksi tidak ditemukan atau parameter URL tidak valid.</div>");
}

// =========================================================================
// STAGE 1 & 2: KONSOLIDASI DATA MANIFEST STRUK (QUERY RELASI MULTI-JOIN)
// =========================================================================
try {
    // Menggunakan JOIN berganda untuk mendapatkan data Pengirim, Penerima, dan Nama Lengkap Petugas
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
    // Emergency Mode jika terjadi kegagalan struktur query relasi kompleks
    try {
        $query_safe = "SELECT t.*, n.nisn AS nisn_pengirim, n.nama_nasabah AS nama_pengirim, NULL AS kelas_pengirim, NULL AS nama_penerima, NULL AS nama_petugas 
                       FROM tbl_transaksi t 
                       JOIN tbl_nasabah n ON t.id_nasabah = n.id_nasabah 
                       WHERE t.id_transaksi = ?";
        $stmt_safe = $pdo->prepare($query_safe);
        $stmt_safe->execute([$id_transaksi]);
        $transaksi = $stmt_safe->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        die("<div style='font-family:sans-serif; padding:20px; color:#dc2626;'><strong>Fatal Error Basis Data:</strong> " . htmlspecialchars($ex->getMessage()) . "</div>");
    }
}

if (!$transaksi) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px; color:#dc2626;'><strong>Error:</strong> Record data transaksi tidak ditemukan dalam sistem pembukuan.</div>");
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

// Penentuan Label Teks Nota & Kalkulasi Nominal Bersih Akhir
if ($kategori_murni === 'setor') {
    $label_transaksi = "SETORAN TUNAI";
    $label_bersih = "Setoran Bersih";
    $nominal_bersih = $transaksi['jumlah'];
} elseif ($kategori_murni === 'tarik') {
    $label_transaksi = "PENARIKAN TUNAI";
    $label_bersih = "Penarikan Bersih (+Admin)";
    $nominal_bersih = $transaksi['jumlah'] + $biaya_admin;
} elseif ($kategori_murni === 'dana_masuk') {
    $label_transaksi = "DANA MASUK (TRANSFER)";
    $label_bersih = "Dana Diterima";
    $nominal_bersih = $transaksi['jumlah'];
} else { // dana_keluar
    $label_transaksi = "DANA KELUAR (TRANSFER)";
    $label_bersih = "Total Debet (+Admin)";
    $nominal_bersih = $transaksi['jumlah'] + $biaya_admin;
}

// Pemisahan Tampilan Informasi: Petugas Loket VS Rekan Transfer
$isLayananLoket = ($kategori_murni === 'setor' || $kategori_murni === 'tarik');
$isLayananTransfer = ($kategori_murni === 'dana_masuk' || $kategori_murni === 'dana_keluar');

// Lokalisasi Hari Kalender Bahasa Indonesia
$hari_list = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$timestamp = strtotime($transaksi['tanggal_transaksi'] ?? date('Y-m-d H:i:s'));
$hari_id = $hari_list[date('l', $timestamp)] ?? date('l', $timestamp);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk_<?= htmlspecialchars($transaksi['kode_transaksi'] ?? '0') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap');

        @media print {
            @page {
                margin: 0;
                size: 80mm auto;
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .no-print {
                display: none !important;
            }

            .receipt-container {
                box-shadow: none !important;
                border: none !important;
                width: 72mm;
                margin: 0 auto;
                padding: 2mm 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        body {
            font-family: 'Courier Prime', monospace;
            background-color: #c4cfd9;
        }

        .receipt-container {
            width: 80mm;
            min-height: 120mm;
            background-color: white;
            padding: 5mm;
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 12px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 3rem;
            color: rgba(71, 85, 105, 0.04);
            white-space: nowrap;
            pointer-events: none;
            text-transform: uppercase;
            font-weight: 900;
            z-index: 0;
            letter-spacing: 2px;
        }

        .dashed-line {
            border-top: 1px dashed #64748b;
            margin: 3mm 0;
        }
    </style>
</head>

<body class="flex flex-col items-center py-10">
    <br>
    <!-- Toolbar Non-Printable -->
    <div class="no-print flex gap-3 mt-4 relative z-20">
        <button onclick="window.print()" class="sm:bg-gradient-to-br sm:from-[#2978d7] sm:via-[#1566c7] sm:to-[#1257aa] hover:opacity-95 text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow-lg transition-all flex items-center gap-2 cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            CETAK STRUK (80mm)
        </button>
    </div>
    <br>

    <!-- Wadah Fisik Struk Thermal -->
    <div class="receipt-container text-gray-800 text-[11px] leading-tight">
        <div class="watermark">MINI BANK</div>

        <!-- Header Sekolah -->
        <div class="relative z-10 text-center mb-2">
            <h1 class="font-bold text-[16px] uppercase text-blue-900 leading-none">Mini Bank</h1>
            <h2 class="font-bold text-[12px] text-gray-700 mt-1">SMK NEGERI 1 INFORMATIKA</h2>
            <div class="text-[9px] text-gray-500 mt-1">
                <p>Jl. Pendidikan No. 123 | (021) 555-0192</p>
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Log Metadata Logistik -->
        <div class="relative z-10 space-y-1 mb-2">
            <div class="flex justify-between"><span>No. Referensi</span><span class="font-bold text-blue-900">YPLP-PGRI-055<?= htmlspecialchars($transaksi['id_transaksi'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span>Kode. TRX</span><span class="font-bold font-mono"><?= htmlspecialchars($transaksi['kode_transaksi'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span>Waktu</span><span><?= $hari_id . ' ' . date('d-m-Y H:i:s', $timestamp) ?></span></div>
            <div class="flex justify-between items-center py-1"><span>Status</span><span class="bg-green-100 text-green-700 px-2 py-0.5 text-[9px] rounded font-bold border border-green-200 uppercase">BERHASIL</span></div>

            <!-- ATURAN 1: Tampilkan nama petugas hanya saat Setor atau Tarik Tunai -->
            <?php if ($isLayananLoket): ?>
                <div class="flex justify-between border-t border-gray-100 pt-1"><span>Petugas Loket</span><span class="uppercase font-bold text-slate-900"><?= htmlspecialchars($transaksi['nama_petugas'] ?? 'Kasir Loket') ?></span></div>
            <?php else: ?>
                <div class="flex justify-between border-t border-gray-100 pt-1"><span>Sistem Layanan</span><span class="uppercase text-gray-500">Transfer Elektronik</span></div>
            <?php endif; ?>
        </div>

        <div class="dashed-line"></div>

        <!-- Manifes Rekaman Identitas Nasabah Terlibat -->
        <div class="relative z-10 space-y-1">
            <?php if ($isLayananTransfer): ?>
                <!-- ATURAN 2: Tampilkan Detail Pengirim dan Penerima Khusus Transaksi Transfer -->
                <div class="flex justify-between"><span>NISN Pengirim</span><span><?= htmlspecialchars(substr($transaksi['nisn_pengirim'] ?? '0000000000', 0, -3)) ?><span class="text-blue-600 font-bold">xxx</span></span></div>
                <div class="flex justify-between"><span>Nama Pengirim</span><span class="font-bold uppercase text-blue-900"><?= htmlspecialchars($transaksi['nama_pengirim'] ?? '-') ?></span></div>
                <div class="flex justify-between border-b border-gray-100 pb-1"><span>Kelas</span><span><?= htmlspecialchars($transaksi['kelas_pengirim'] ?? '-') ?></span></div>

                <div class="flex justify-between pt-1"><span>NISN Penerima</span><span><?= htmlspecialchars(substr($transaksi['nisn_penerima'] ?? '0000000000', 0, -3)) ?><span class="text-purple-600 font-bold">xxx</span></span></div>
                <div class="flex justify-between"><span>Nama Penerima</span><span class="font-bold uppercase text-purple-900"><?= htmlspecialchars($transaksi['nama_penerima'] ?? '-') ?></span></div>
                <div class="flex justify-between"><span>Kelas</span><span><?= htmlspecialchars($transaksi['kelas_penerima'] ?? '-') ?></span></div>
            <?php else: ?>
                <!-- Layout Standar untuk Setor / Tarik Tunai di Loket -->
                <div class="flex justify-between"><span>NISN</span><span><?= htmlspecialchars(substr($transaksi['nisn_pengirim'] ?? '0000000000', 0, -3)) ?><span class="text-blue-600 font-bold">xxx</span></span></div>
                <div class="flex justify-between"><span>Nasabah</span><span class="font-bold uppercase text-blue-900"><?= htmlspecialchars($transaksi['nama_pengirim'] ?? '-') ?></span></div>
                <div class="flex justify-between"><span>Kelas</span><span><?= htmlspecialchars($transaksi['kelas_pengirim'] ?? '-') ?></span></div>
            <?php endif; ?>
        </div>

        <div class="dashed-line"></div>

        <!-- Komparasi Komponen Nilai Pembukuan -->
        <div class="relative z-10 py-1">
            <div class="flex justify-between font-bold text-[12px] text-blue-900 mb-1"><span class="uppercase"><?= $label_transaksi ?></span><span>Rp <?= number_format($transaksi['jumlah'] ?? 0, 0, ',', '.') ?></span></div>
            <div class="space-y-1 text-[11px] text-gray-600">
                <div class="flex justify-between"><span>Biaya Admin</span><span class="<?= $biaya_admin > 0 ? 'text-red-500 font-bold' : '' ?>"><?= $biaya_admin > 0 ? 'Rp ' . number_format($biaya_admin, 0, ',', '.') : 'Rp 0' ?></span></div>
                <div class="flex justify-between pt-1 border-t border-gray-50 mt-1"><span><?= $label_bersih ?></span><span class="font-bold text-gray-800">Rp <?= number_format($nominal_bersih, 0, ',', '.') ?></span></div>
            </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Akumulasi Saldo Berjalan Nasabah Aktif -->
        <div class="relative z-10 space-y-1">
            <div class="flex justify-between text-[11px]"><span>Saldo Awal</span><span class="font-medium text-gray-700">Rp <?= number_format($transaksi['saldo_awal'] ?? 0, 0, ',', '.') ?></span></div>
            <?php
            // Sinkronisasi logika hitung mutasi saldo berjalan di frontend riwayat
            $saldo_akhir_display = (intval($transaksi['id_nasabah']) === intval($id_nasabah_aktif))
                ? $transaksi['saldo_akhir']
                : ($transaksi['saldo_awal'] + $transaksi['jumlah']);
            ?>
            <div class="flex justify-between font-bold text-blue-900 text-[12px]"><span class="uppercase">Saldo Akhir</span><span>Rp <?= number_format($saldo_akhir_display, 0, ',', '.') ?></span></div>
        </div>

        <!-- Catatan Tambahan -->
        <?php if (!empty($transaksi['keterangan'])): ?>
            <div class="text-[9px] text-gray-400 italic my-2">Catatan: <?= htmlspecialchars($transaksi['keterangan']) ?></div>
        <?php endif; ?>

        <div class="dashed-line"></div>

        <!-- Catatan Kaki Legalitas Administrasi -->
        <div class="relative z-10 text-center space-y-2 mt-4">
            <div class="bg-gray-50 p-2 rounded border border-gray-200">
                <p class="font-bold text-blue-800 text-[9px] uppercase mb-1">Pengesahan Transaksi</p>
                <p class="text-[8px] leading-tight text-gray-600 italic">Struk ini adalah bukti transaksi yang sah dan diakui secara administratif oleh Sistem Mini Bank Sekolah.</p>
            </div>
            <div class="text-[8px] text-gray-400 mt-4">
                <p>"Budayakan menabung demi masa depan cerah"</p>
                <p class="mt-2 font-mono tracking-widest opacity-50 uppercase text-[7px]">AUTH: MB_<?= strtoupper(date('dmy', $timestamp)) ?>_VALID</p>
            </div>
        </div>
    </div>

    <!-- Tips Alat Cetak -->
    <div class="no-print mt-6 max-w-[80mm] text-[12px] text-gray-400 text-center italic px-4">
        Tip: Gunakan printer thermal 80mm. Pastikan margin printer diatur ke 'None' pada saat mencetak.
    </div>

    <!-- Perintah Lifecycle Print -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 400);
        });
    </script>
</body>

</html>