<?php

/**
 * ==========================================
 * LOGIKA PHP - PROSES TRANSAKSI INFAQ
 * ==========================================
 * Disesuaikan dengan tabel: tbl_transaksi_infaq
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_nasabah = $_SESSION['id_nasabah'] ?? null;
$id_petugas = $_SESSION['id_user'] ?? null; // Mengambil ID petugas/amil yang sedang login jika ada
$saldo_riil  = 0; // Penampung saldo utama siswa

if ($id_nasabah) {
    try {
        // 1. Ambil Saldo Utama Terbaru untuk validasi kecukupan saldo
        $stmt_saldo = $pdo->prepare("SELECT saldo FROM tbl_nasabah WHERE id_nasabah = ?");
        $stmt_saldo->execute([$id_nasabah]);
        $saldo_riil = $stmt_saldo->fetch(PDO::FETCH_ASSOC)['saldo'] ?? 0;

        // 2. Ambil Riwayat Khusus Infaq Siswa ini dari tabel baru (tbl_transaksi_infaq)
        $stmt_riwayat = $pdo->prepare("
            SELECT id_infaq AS id_transaksi, nominal_infaq AS jumlah, keterangan, tanggal_infaq AS tracking_date, tanggal_infaq AS tanggal_transaksi 
            FROM tbl_transaksi_infaq 
            WHERE id_nasabah = ? 
            ORDER BY tanggal_infaq DESC
        ");
        $stmt_riwayat->execute([$id_nasabah]);
        $riwayat_infaq = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);

        // 3. Hitung Total Infaq yang telah dikeluarkan oleh siswa ini dari tbl_transaksi_infaq
        $stmt_total = $pdo->prepare("
            SELECT SUM(nominal_infaq) as total 
            FROM tbl_transaksi_infaq 
            WHERE id_nasabah = ?
        ");
        $stmt_total->execute([$id_nasabah]);
        $total_infaq_user = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Gagal memuat data infaq: " . $e->getMessage());
    }
}

// 4. Proses Eksekusi Form Potong Saldo Infaq
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_infaq']) && $id_nasabah) {
    $nominal_infaq = floatval($_POST['nominal_infaq']);
    $catatan       = trim($_POST['catatan_infaq']) ?: 'Infaq Sukarela Siswa';

    // Set default jenis_infaq ke 'umum', atau Anda bisa menyesuaikannya nanti melalui input form select jika diperlukan
    $jenis_infaq   = 'umum';

    if ($nominal_infaq < 1000) {
        echo "<script>alert('Minimal berinfaq adalah Rp 1.000');</script>";
    } elseif ($nominal_infaq > $saldo_riil) {
        echo "<script>alert('Saldo tabungan Anda tidak mencukupi untuk melakukan infaq ini.');</script>";
    } else {
        try {
            $pdo->beginTransaction();

            // A. Potong Saldo Utama di tabel Nasabah
            $update_saldo = $pdo->prepare("UPDATE tbl_nasabah SET saldo = saldo - ? WHERE id_nasabah = ?");
            $update_saldo->execute([$nominal_infaq, $id_nasabah]);

            // B. Generator otomatis untuk `kode_infaq` (Format: INF/YYYYMMDD/KODE_ACAK)
            $kode_infaq = "INF/" . date('Ymd') . "/" . strtoupper(bin2hex(random_bytes(3)));

            // C. Masukkan data ke tabel baru `tbl_transaksi_infaq`
            $insert_infaq = $pdo->prepare("
                INSERT INTO tbl_transaksi_infaq (kode_infaq, id_nasabah, jenis_infaq, nominal_infaq, keterangan, tanggal_infaq, id_petugas) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $insert_infaq->execute([$kode_infaq, $id_nasabah, $jenis_infaq, $nominal_infaq, $catatan, $id_petugas]);

            $pdo->commit();
            echo "<script>alert('Alhamdulillah, infaq Anda berhasil disalurkan. Terima kasih atas kebaikan Anda!'); window.location.href='';</script>";
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Gagal memproses transaksi infaq: " . $e->getMessage());
            echo "<script>alert('Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.');</script>";
        }
    }
}
?>

<div class="space-y-8">

    <!-- HEADER NAVIGASI BALIK -->
    <!-- <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                <i class="fas fa-heart text-green-600"></i> Infaq & Sedekah Digital
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Salurkan kebaikan secara mudah dan transparan dari saldo tabungan Anda.</p>
        </div>
        <a href="?page=dashboard" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all cursor-pointer">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div> -->

    <!-- ROW UTAMA: FORM & SUMMARY -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start" x-data="{ nominal: '' }">

        <!-- KOLOM FORM BAYAR (KIRI - LEBAR 2) -->
        <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm p-5 sm:p-6 lg:col-span-2 space-y-6">
            <!-- <div class="border-b border-slate-100 pb-4">
                <h3 class="font-bold text-slate-800 text-sm sm:text-base">Formulir Penyaluran Infaq</h3>
                <p class="text-xs text-slate-400 mt-0.5">Silakan tentukan nominal yang ingin Anda sedekahkan.</p>
            </div> -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
                <div>
                    <!-- <h3 class="font-black text-blue-800 flex items-center gap-2">
                        <i class="fas fa-hand-holding-usd text-amber-500"></i>Penarikan Tunai
                    </h3> -->
                    <h3 class="font-black text-[#506a8a] flex items-center gap-2">
                        <i class="fas fa-hands-helping text-amber-500"></i>Penyaluran Infaq
                    </h3>
                    <p class="text-[11px] text-slate-400 font-medium">Silakan tentukan nominal yang ingin Anda sedekahkan.</p>
                </div>
                <a href="?page=main" class="text-[10px] font-black uppercase tracking-wider text-slate-400 hover:text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
            </div>

            <form action="" method="POST" class="space-y-5">
                <!-- TAMPILAN SALDO UTAMA SAAT INI -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex justify-between items-center">
                    <div>
                        <p class="text-[10px] font-black tracking-wider text-slate-400 uppercase">Saldo Utama Anda</p>
                        <p class="text-xl font-black text-slate-700 mt-0.5">Rp <?= number_format($saldo_riil, 0, ',', '.') ?></p>
                    </div>
                    <span class="px-2.5 py-1 bg-blue-50 text-blue-700 text-[10px] font-bold border border-blue-200 rounded-lg">
                        <i class="fas fa-wallet mr-1"></i> Sumber Dana
                    </span>
                </div>

                <!-- INPUT NOMINAL MANUAL -->
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-black text-slate-400 capitalize tracking-wider flex items-center gap-1">
                        <i class="fas fa-money-bill-wave text-green-600"></i> Masukkan Nominal Infaq (Rp)
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <span class="text-slate-400 font-bold text-sm">Rp</span>
                        </div>
                        <input type="number" name="nominal_infaq" x-model="nominal" min="1000" placeholder="Contoh: 1000" required
                            class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 font-black text-slate-700 bg-slate-50/50 text-lg transition-all">
                    </div>
                    <small class="text-[10px] text-slate-400">Minimal penyaluran infaq adalah Rp 1.000</small>
                </div>

                <!-- QUICK SELECT NOMINAL INSTAN -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-slate-400 capitalize tracking-wider">
                        Atau Pilih Nominal Instan:
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <button type="button" @click="nominal = 1000" class="p-2.5 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-blue-50 hover:border-blue-300 active:scale-95 transition-all cursor-pointer">
                            Rp 1.000
                        </button>
                        <button type="button" @click="nominal = 2000" class="p-2.5 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-blue-50 hover:border-blue-300 active:scale-95 transition-all cursor-pointer">
                            Rp 2.000
                        </button>
                        <button type="button" @click="nominal = 3000" class="p-2.5 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-blue-50 hover:border-blue-300 active:scale-95 transition-all cursor-pointer">
                            Rp 3.000
                        </button>
                        <button type="button" @click="nominal = 4000" class="p-2.5 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-blue-50 hover:border-blue-300 active:scale-95 transition-all cursor-pointer">
                            Rp 4.000
                        </button>
                        <button type="button" @click="nominal = 5000" class="p-2.5 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-blue-50 hover:border-blue-300 active:scale-95 transition-all cursor-pointer">
                            Rp 5.000
                        </button>
                    </div>
                </div>

                <!-- CATATAN / DOA SAKLAR -->
                <div class="space-y-1.5">
                    <label class="block text-[10px] font-black text-slate-400 capitalize tracking-wider flex items-center gap-1">
                        <i class="far fa-comment-dots text-slate-400"></i> Catatan Khusus / Doa Anda (Opsional)
                    </label>
                    <input type="text" name="catatan_infaq" placeholder="Contoh: Hamba Allah, Infaq Jumat Berkah, Semoga berkah"
                        class="w-full px-4 py-2.5 text-xs border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 font-medium text-slate-700 bg-slate-50/50 transition-all">
                </div>
                <small class="text-[10px] text-slate-400">Catatan ini akan tercantum di laporan transparansi penyaluran infaq.</small>

                <!-- WARNING TENTANG KESEKUTUAN DATA -->
                <div class="bg-amber-50 border border-amber-200 p-3 rounded-xl flex gap-3 text-[11px] text-amber-800 leading-relaxed">
                    <i class="fas fa-shield-alt text-amber-500 mt-0.5 shrink-0 text-xs"></i>
                    <p>Setelah menekan tombol konfirmasi, saldo tabungan Anda akan langsung terpotong secara permanen untuk dialokasikan ke rekening infaq sosial sekolah.</p>
                </div>

                <!-- BUTTON SUBMIT -->
                <button type="submit" name="proses_infaq" class="w-full py-3.5 bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] hover:opacity-90 text-white font-black text-xs tracking-wider rounded-xl transition-all duration-300 active:scale-95 shadow-lg cursor-pointer text-center flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i> KONFIRMASI & SALURKAN INFAQ
                </button>
            </form>
        </div>

        <!-- KOLOM STATISTIK KONTRIBUSI (KANAN - LEBAR 1) -->
        <div class="space-y-4">
            <!-- TOTAL INFAQ TERKUMPUL SECARA PRIBADI -->
            <div class="bg-gradient-to-r from-green-600 to-green-700 p-5 rounded-[1rem] text-white shadow-md relative overflow-hidden group">
                <div class="absolute -right-12 -bottom-12 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                <p class="text-[9px] font-black tracking-[0.2em] text-green-100/70 uppercase"><i class="fas fa-trophy text-yellow-400 mr-1"></i> Amal Jariyah Anda</p>
                <h3 class="text-3xl font-black mt-1.5 tracking-tight">Rp <?= number_format($total_infaq_user, 0, ',', '.') ?></h3>
                <p class="text-[11px] text-green-100/80 mt-2 border-t border-white/10 pt-2 leading-relaxed mb-4">
                    Total kontribusi infaq Anda salurkan melalui sistem tabungan digital ini. Semoga menjadi berkah.
                </p>

                <!-- TOMBOL MENUJU RIWAYAT INFAQ -->
                <a href="?page=infaq" class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 bg-white/10 hover:bg-white/20 active:scale-95 border border-white/20 text-[11px] font-bold tracking-wide rounded-xl transition-all cursor-pointer text-white">
                    <i class="fas fa-history text-xs text-green-200"></i> Lihat Riwayat Infaq Anda <i class="fas fa-arrow-right ml-1 text-[9px] opacity-70"></i>
                </a>
            </div>

            <!-- CARD MOTIVASI ISLAMI -->
            <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm p-4 text-center space-y-2">
                <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto text-sm">
                    <i class="fas fa-quote-left"></i>
                </div>
                <p class="text-xs text-slate-500 font-medium leading-relaxed">
                    "Sedekah itu tidak akan mengurangi harta. Tidak ada orang yang memberi maaf kepada orang lain, melainkan Allah akan menambah kemuliaannya."
                </p>
                <p class="text-[9px] font-black text-slate-400 tracking-wider uppercase">— HR. Muslim</p>
            </div>
        </div>

    </div>

    <!-- RIWAYAT TRANSAKSI INFAQ PRIBADI (BAGIAN BAWAH) -->
    <!-- <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 bg-slate-50/60">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm sm:text-base">
                <i class="fas fa-history text-green-600"></i> Catatan Kebaikan Anda (Riwayat Infaq)
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Berikut adalah log transparansi penyaluran dana sosial khusus dari akun Anda.</p>
        </div>

        <div class="divide-y divide-slate-100">
            <?php if (empty($riwayat_infaq)): ?>
                <div class="p-8 text-center text-slate-400 space-y-2">
                    <i class="fas fa-heart-broken text-3xl text-slate-200"></i>
                    <p class="text-xs">Anda belum memulai riwayat infaq. Salurkan kebaikan pertama Anda di atas!</p>
                </div>
            <?php else: ?>
                <?php foreach ($riwayat_infaq as $infaq): ?>
                    <div class="p-4 flex items-center justify-between hover:bg-slate-50/50 transition-colors gap-3">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 bg-green-50 text-green-600 rounded-xl flex items-center justify-center border border-green-100 text-xs shrink-0">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs sm:text-sm font-bold text-slate-800 truncate">
                                    <?= htmlspecialchars($infaq['keterangan']) ?>
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    <?= date('d M Y, H:i', strtotime($infaq['tanggal_transaksi'])) ?> WIB
                                </p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs sm:text-sm font-black text-green-600">
                                - Rp<?= number_format($infaq['jumlah'], 0, ',', '.') ?>
                            </p>
                            <span class="text-[8px] uppercase tracking-widest font-extrabold px-1.5 py-0.5 bg-green-100 text-green-800 rounded">
                                Sukses
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div> -->

</div>