<?php

/**
 * ===================================================
 * LOGIKA PHP - MEMUAT DATA RIWAYAT INFAQ
 * ===================================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan nasabah/siswa sudah login
$id_nasabah = $_SESSION['id_nasabah'] ?? null;

// Variabel penampung data awal
$total_infaq_user = 0;
$frekuensi_infaq  = 0;
$infaq_bulan_ini  = 0;
$riwayat_infaq    = [];

// Array untuk lokalisasi nama hari ke Bahasa Indonesia
$nama_hari = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

if ($id_nasabah) {
    try {
        // 1. Hitung Total Akumulasi Infaq Siswa
        $stmt_total = $pdo->prepare("
            SELECT SUM(nominal_infaq) as total 
            FROM tbl_transaksi_infaq 
            WHERE id_nasabah = ?
        ");
        $stmt_total->execute([$id_nasabah]);
        $total_infaq_user = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // 2. Hitung Frekuensi (Berapa Kali) Siswa Berinfaq
        $stmt_count = $pdo->prepare("
            SELECT COUNT(id_infaq) as jumlah_kali 
            FROM tbl_transaksi_infaq 
            WHERE id_nasabah = ?
        ");
        $stmt_count->execute([$id_nasabah]);
        $frekuensi_infaq = $stmt_count->fetch(PDO::FETCH_ASSOC)['jumlah_kali'] ?? 0;

        // 3. Hitung Total Infaq Khusus Bulan Ini
        $stmt_bulan = $pdo->prepare("
            SELECT SUM(nominal_infaq) as total_bulan 
            FROM tbl_transaksi_infaq 
            WHERE id_nasabah = ? AND MONTH(tanggal_infaq) = MONTH(CURRENT_DATE()) AND YEAR(tanggal_infaq) = YEAR(CURRENT_DATE())
        ");
        $stmt_bulan->execute([$id_nasabah]);
        $infaq_bulan_ini = $stmt_bulan->fetch(PDO::FETCH_ASSOC)['total_bulan'] ?? 0;

        // 4. Ambil Semua Daftar Riwayat Transaksi Infaq
        $stmt_riwayat = $pdo->prepare("
            SELECT kode_infaq, jenis_infaq, nominal_infaq, keterangan, tanggal_infaq 
            FROM tbl_transaksi_infaq 
            WHERE id_nasabah = ? 
            ORDER BY tanggal_infaq DESC
        ");
        $stmt_riwayat->execute([$id_nasabah]);
        $riwayat_infaq = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Gagal memuat halaman riwayat infaq: " . $e->getMessage());
    }
}
?>

<!-- ===================================================
     TAMPILAN INTERFACE (HTML & TAILWIND CSS)
     =================================================== -->
<div class="space-y-6">

    <!-- 1. HEADER HALAMAN -->
    <!-- <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pb-2">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                <i class="fas fa-history text-green-600"></i> Catatan Kebaikan Anda
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Transparansi dan rekam jejak penyaluran dana infaq digital Anda.</p>
        </div>
        <a href="?page=infaq" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 rounded-xl text-xs font-bold transition-all cursor-pointer border border-slate-200/50">
            <i class="fas fa-heart text-green-600"></i> Berinfaq Lagi
        </a>
    </div> -->

    <!-- 2. BARIS RINGKASAN STATISTIK (KPI CARDS) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        <!-- Total Amal Jariyah (Menggunakan warna gradasi request Anda) -->
        <div class="bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] p-4 rounded-2xl text-white shadow-sm relative overflow-hidden">
            <i class="fas fa-donate absolute -right-3 -bottom-3 text-white/10 text-5xl pointer-events-none"></i>
            <p class="text-[9px] font-black tracking-wider text-green-100/80 uppercase">Total Amal Jariyah</p>
            <h3 class="text-2xl font-black mt-1 font-mono">Rp <?= number_format($total_infaq_user, 0, ',', '.') ?></h3>
            <p class="text-[10px] text-green-100/70 mt-1">Akumulasi seluruh saldo yang diinfaqkan</p>
        </div>

        <!-- Total Frekuensi Transaksi -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <i class="fas fa-file-invoice-dollar absolute -right-3 -bottom-3 text-slate-100 text-5xl pointer-events-none"></i>
            <div>
                <p class="text-[9px] font-black tracking-wider text-slate-400 uppercase">Frekuensi Infaq</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 font-mono"><?= number_format($frekuensi_infaq, 0, ',', '.') ?> <span class="text-xs text-slate-400 font-sans font-medium">Kali</span></h3>
            </div>
            <p class="text-[10px] text-slate-400 mt-1">Konsistensi berbagi kebaikan</p>
        </div>

        <!-- Akumulasi Bulan Ini -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <i class="fas fa-calendar-check absolute -right-3 -bottom-3 text-slate-100 text-5xl pointer-events-none"></i>
            <div>
                <p class="text-[9px] font-black tracking-wider text-slate-400 uppercase">Kontribusi Bulan Ini</p>
                <h3 class="text-2xl font-black text-slate-800 mt-1 font-mono">Rp <?= number_format($infaq_bulan_ini, 0, ',', '.') ?></h3>
            </div>
            <p class="text-[10px] text-green-600 font-bold mt-1 flex items-center gap-1">
                <i class="fas fa-arrow-up text-[8px]"></i> Istiqomah beramal
            </p>
        </div>

    </div>

    <!-- 3. AREA UTAMA: TABEL RIWAYAT TRANSAKSI -->
    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-4 sm:p-5 border-b border-slate-100 bg-slate-50/60 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm sm:text-base">
                    <i class="fas fa-list-ul text-slate-400 text-xs"></i> Log Mutasi Infaq Anda
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Menampilkan seluruh dana sosial terdebet dari rekening Anda.</p>
            </div>
            <span class="text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 px-2.5 py-1 rounded-lg flex items-center gap-1.5">
                <i class="fas fa-shield-alt text-xs"></i> Data Terverifikasi Sistem
            </span>
        </div>

        <!-- Struktur Tabel Data -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/30 text-[10px] font-black uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4 text-center w-[50px]">No</th>
                        <th class="py-3 px-4 w-[240px]">Hari, Tanggal & Waktu</th>
                        <th class="py-3 px-4 w-[180px]">Kode Referensi</th>
                        <th class="py-3 px-4 w-[120px]">Kategori</th>
                        <th class="py-3 px-4">Keterangan / Doa</th>
                        <th class="py-3 px-4 text-right w-[150px]">Nominal</th>
                        <th class="py-3 px-4 text-center w-[100px]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">

                    <?php if (empty($riwayat_infaq)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 space-y-2">
                                <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-2 text-slate-300 border border-slate-100">
                                    <i class="fas fa-heart-broken text-lg"></i>
                                </div>
                                <p class="text-xs font-medium">Anda belum memiliki riwayat transaksi infaq.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $no = 1;
                        foreach ($riwayat_infaq as $infaq):
                            // Konversi nama hari ke bahasa indonesia
                            $day_name = date('l', strtotime($infaq['tanggal_infaq']));
                            $hari_indo = $nama_hari[$day_name] ?? $day_name;

                            // Format Tanggal dan Jam lengkap (H:i:s)
                            $waktu_lengkap = $hari_indo . ', ' . date('d M Y - H:i:s', strtotime($infaq['tanggal_infaq'])) . ' WIB';
                        ?>
                            <tr class="hover:bg-slate-50/40 transition-colors">
                                <!-- Kolom Nomor -->
                                <td class="py-3.5 px-4 text-center font-bold text-slate-400 font-mono">
                                    <?= $no++; ?>
                                </td>
                                <!-- Kolom Hari, Tanggal, Jam (H:i:s) -->
                                <td class="py-3.5 px-4 font-medium text-slate-600">
                                    <?= $waktu_lengkap ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-700">
                                    <?= htmlspecialchars($infaq['kode_infaq']) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <?php if ($infaq['jenis_infaq'] === 'umum'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100">
                                            <i class="fas fa-mosque text-[9px]"></i> Umum
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-100">
                                            <i class="fas fa-child text-[9px]"></i> Khusus
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 font-medium max-w-[230px] truncate" title="<?= htmlspecialchars($infaq['keterangan']) ?>">
                                    <?= htmlspecialchars($infaq['keterangan']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-black text-green-600 font-mono">
                                    - Rp <?= number_format($infaq['nominal_infaq'], 0, ',', '.') ?>
                                </td>
                                <!-- Kolom Status Berhasil -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-block text-[9px] uppercase tracking-wider font-extrabold px-2 py-0.5 bg-green-100 text-green-800 rounded-md">
                                        Berhasil
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <div class="p-4 bg-slate-50/50 border-t border-slate-100 text-center">
            <p class="text-[10px] text-slate-400 font-medium">Menampilkan <?= count($riwayat_infaq) ?> keseluruhan transaksi infaq digital</p>
        </div>

    </div>

</div>