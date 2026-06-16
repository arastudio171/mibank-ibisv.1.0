<?php

/**
 * ==========================================
 * 1. LOGIKA BACKEND: KONSOL OPERATOR REAL-DATABASE (`tbl_antrean`)
 * ==========================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan variabel koneksi database $pdo sudah didefinisikan sebelum kode ini
// $pdo = new PDO(...);

// INI DIUBAH MENJADI ARRAY AGAR BISA MUNCUL DI MASING-MASING CARD LOKET
$msg_loket = [
    'A' => ['status' => null, 'text' => null, 'tipe_aksi' => null],
    'B' => ['status' => null, 'text' => null, 'tipe_aksi' => null]
];

$hari_ini = date('Y-m-d');

// Hubungkan ID Loket fisik operator (Loket A = ID 1, Loket B = ID 2)
$id_loket_a = 1;
$id_loket_b = 2;

// PROSES AKSI OPERATOR: KLIK PANGGIL NEXT ATAU LEWATI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_operator'])) {
    $loket_pilihan = $_POST['loket_pilihan']; // 'A' or 'B'
    $jenis_aksi    = $_POST['jenis_aksi'];    // 'panggil' or 'lewati'
    $id_loket      = ($loket_pilihan === 'A') ? $id_loket_a : $id_loket_b;

    try {
        $pdo->beginTransaction();

        if ($jenis_aksi === 'panggil') {

            // 1. Selesaikan otomatis antrean yang sedang berstatus 'panggil' di loket ini
            $stmt_close = $pdo->prepare("
                UPDATE tbl_antrean 
                SET status_antrean = 'selesai' 
                WHERE id_loket = ? AND tanggal_antrean = ? AND status_antrean = 'panggil'
            ");
            $stmt_close->execute([$id_loket, $hari_ini]);

            // 2. Cari 1 nomor antrean terdepan yang masih 'tunggu'
            $stmt_next = $pdo->prepare("
                SELECT id_antrean, nomor_antrean 
                FROM tbl_antrean 
                WHERE tanggal_antrean = ? 
                  AND status_antrean = 'tunggu' 
                  AND nomor_antrean LIKE ? 
                ORDER BY angka_urutan ASC 
                LIMIT 1 FOR UPDATE
            ");
            $stmt_next->execute([$hari_ini, $loket_pilihan . '%']);
            $antrean_berikutnya = $stmt_next->fetch(PDO::FETCH_ASSOC);

            if ($antrean_berikutnya) {
                $id_antrean_target = $antrean_berikutnya['id_antrean'];
                $no_antrean_text   = $antrean_berikutnya['nomor_antrean'];

                $stmt_update = $pdo->prepare("
                    UPDATE tbl_antrean 
                    SET status_antrean = 'panggil', id_loket = ? 
                    WHERE id_antrean = ?
                ");
                $stmt_update->execute([$id_loket, $id_antrean_target]);

                $pdo->commit();

                // Set notifikasi sukses khusus untuk loket yang memanggil
                $msg_loket[$loket_pilihan] = [
                    'status' => 'success',
                    'text' => "Berhasil memanggil nomor " . $no_antrean_text,
                    'tipe_aksi' => 'panggil'
                ];
            } else {
                $pdo->rollBack();
                $msg_loket[$loket_pilihan] = [
                    'status' => 'error',
                    'text' => "Tidak ada antrean tersisa yang menunggu.",
                    'tipe_aksi' => 'panggil'
                ];
            }
        } else if ($jenis_aksi === 'lewati') {
            // --- LOGIKA TOMBOL LEWATI ---
            $stmt_current = $pdo->prepare("
                SELECT id_antrean, nomor_antrean 
                FROM tbl_antrean 
                WHERE id_loket = ? AND tanggal_antrean = ? AND status_antrean = 'panggil'
                LIMIT 1 FOR UPDATE
            ");
            $stmt_current->execute([$id_loket, $hari_ini]);
            $antrean_aktif = $stmt_current->fetch(PDO::FETCH_ASSOC);

            if ($antrean_aktif) {
                $id_antrean_target = $antrean_aktif['id_antrean'];
                $no_antrean_text   = $antrean_aktif['nomor_antrean'];

                $stmt_skip = $pdo->prepare("
                    UPDATE tbl_antrean 
                    SET status_antrean = 'lewat' 
                    WHERE id_antrean = ?
                ");
                $stmt_skip->execute([$id_antrean_target]);

                $pdo->commit();

                // SEUAI PERMINTAAN: Walau sukses dilewati, set tipe_aksi 'lewati' agar alert berwarna MERAH
                $msg_loket[$loket_pilihan] = [
                    'status' => 'success',
                    'text' => "Nomor " . $no_antrean_text . " telah dilewati (skip).",
                    'tipe_aksi' => 'lewati'
                ];
            } else {
                $pdo->rollBack();
                $msg_loket[$loket_pilihan] = [
                    'status' => 'error',
                    'text' => "Tidak ada nomor aktif untuk dilewati.",
                    'tipe_aksi' => 'lewati'
                ];
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error Operator Antrean: " . $e->getMessage());
        $msg_loket[$loket_pilihan] = [
            'status' => 'error',
            'text' => "Gagal memproses aksi antrean.",
            'tipe_aksi' => $jenis_aksi
        ];
    }
}

/**
 * ==========================================
 * 2. TARIK DATA REALTIME UNTUK TAMPILAN MONITOR OPERATOR
 * ==========================================
 */
try {
    // --- LOKET A (SETOR TUNAI - BIRU) ---
    $stmt_curr_a = $pdo->prepare("SELECT nomor_antrean FROM tbl_antrean WHERE tanggal_antrean = ? AND status_antrean = 'panggil' AND nomor_antrean LIKE 'A%' ORDER BY id_antrean DESC LIMIT 1");
    $stmt_curr_a->execute([$hari_ini]);
    $current_a = $stmt_curr_a->fetchColumn() ?: 'A00';

    $stmt_next_a = $pdo->prepare("SELECT nomor_antrean FROM tbl_antrean WHERE tanggal_antrean = ? AND status_antrean = 'tunggu' AND nomor_antrean LIKE 'A%' ORDER BY angka_urutan ASC LIMIT 1");
    $stmt_next_a->execute([$hari_ini]);
    $next_a = $stmt_next_a->fetchColumn() ?: '-';

    $stmt_sisa_a = $pdo->prepare("SELECT COUNT(*) FROM tbl_antrean WHERE tanggal_antrean = ? AND status_antrean = 'tunggu' AND nomor_antrean LIKE 'A%'");
    $stmt_sisa_a->execute([$hari_ini]);
    $total_sisa_a = $stmt_sisa_a->fetchColumn();


    // --- LOKET B (TARIK TUNAI - HIJAU) ---
    $stmt_curr_b = $pdo->prepare("SELECT nomor_antrean FROM tbl_antrean WHERE tanggal_antrean = ? AND status_antrean = 'panggil' AND nomor_antrean LIKE 'B%' ORDER BY id_antrean DESC LIMIT 1");
    $stmt_curr_b->execute([$hari_ini]);
    $current_b = $stmt_curr_b->fetchColumn() ?: 'B00';

    $stmt_next_b = $pdo->prepare("SELECT nomor_antrean FROM tbl_antrean WHERE tanggal_antrean = ? AND status_antrean = 'tunggu' AND nomor_antrean LIKE 'B%' ORDER BY angka_urutan ASC LIMIT 1");
    $stmt_next_b->execute([$hari_ini]);
    $next_b = $stmt_next_b->fetchColumn() ?: '-';

    $stmt_sisa_b = $pdo->prepare("SELECT COUNT(*) FROM tbl_antrean WHERE tanggal_antrean = ? AND status_antrean = 'tunggu' AND nomor_antrean LIKE 'B%'");
    $stmt_sisa_b->execute([$hari_ini]);
    $total_sisa_b = $stmt_sisa_b->fetchColumn();
} catch (PDOException $e) {
    error_log("Gagal memuat data monitor loket: " . $e->getMessage());
}
?>

<!-- CONTAINER UTAMA -->
<div id="operator-workspace" class="max-w-12xl mx-auto space-y-6">

    <!-- HEADER HALAMAN OPERATOR -->
    <div class="bg-white p-6 rounded-[1rem] border border-slate-100 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-black text-[#506a8a] flex items-center gap-2 text-base">
                    <i class="fas fa-desktop text-blue-500"></i>Konsol Operator Loket Transaksi
                </h3>
                <p class="text-[11px] text-slate-400 font-medium">Sistem manajemen panggilan kasir pintar. Terintegrasi teratur dengan data antrean harian.</p>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto">
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-lg">
                    <span class="h-2 w-2 bg-emerald-500 rounded-full animate-pulse"></span>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                        Mode Live Operator
                    </span>
                </div>
                <button type="button" onclick="toggleFullscreenWorkspace()" class="h-8 w-8 flex items-center justify-center bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-300 text-slate-500 hover:text-slate-700 rounded-lg shadow-2xs transition-all active:scale-95 cursor-pointer" title="Ubah Ukuran Layar">
                    <i id="fullscreen-icon" class="fas fa-expand text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- WORKSPACE UTAMA OPERATOR (GRID 2 KOLOM SEJAJAR) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- ========================================== -->
        <!-- BLOK LOKET A - SETOR TUNAI (WARNA BIRU)    -->
        <!-- ========================================== -->
        <div class="space-y-4">
            <div class="bg-white p-6 rounded-[1rem] border border-slate-100 shadow-sm flex flex-col justify-between relative overflow-hidden">
                <div class="border-b border-slate-50 pb-3 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-xs">
                            <i class="fas fa-piggy-bank"></i>
                        </div>
                        <div>
                            <h4 class="font-black text-xs text-blue-700 tracking-wide uppercase">LOKET A - TELLER (SETOR TUNAI)</h4>
                            <p class="text-[10px] text-slate-400 font-medium">Khusus Transaksi Penambahan Saldo</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold bg-blue-50 text-blue-700 px-2.5 py-1 rounded-md border border-blue-100 shadow-3xs">
                        Sisa: <?= $total_sisa_a ?> Antrean
                    </span>
                </div>

                <!-- DI SINI: TEMPAT ALERT UNTUK LOKET A -->
                <?php if ($msg_loket['A']['text']): ?>
                    <?php if ($msg_loket['A']['tipe_aksi'] === 'lewati' || $msg_loket['A']['status'] === 'error'): ?>
                        <!-- ALERT MERAH JIKA SKIPPED ATAU ERROR -->
                        <div class="mt-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-center gap-2.5 animate-fade-in">
                            <i class="fas fa-forward text-xs text-rose-500"></i>
                            <span class="text-[10px] font-bold"><?= htmlspecialchars($msg_loket['A']['text']) ?></span>
                        </div>
                    <?php else: ?>
                        <!-- ALERT HIJAU KARENA BERHASIL PANGGIL -->
                        <div class="mt-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl flex items-center gap-2.5 animate-fade-in">
                            <i class="fas fa-check-circle text-xs text-emerald-500"></i>
                            <span class="text-[10px] font-bold"><?= htmlspecialchars($msg_loket['A']['text']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- BOX NOMOR ANTRIAN BIRU -->
                <div class="my-6 py-8 px-4 bg-blue-50/40 border border-blue-100 rounded-2xl text-center">
                    <span class="block text-[12px] font-black text-blue-600/80 uppercase tracking-widest mb-1">Sedang Dilayani</span>
                    <span class="block text-9xl font-black text-blue-700 tracking-wider"><?= htmlspecialchars($current_a) ?></span>
                </div>

                <!-- ALERT INFORMATIF LOKET A -->
                <div class="mb-4 p-3 bg-blue-50/80 border border-blue-100 text-blue-900 rounded-xl flex items-start gap-2.5">
                    <i class="fas fa-info-circle text-xs mt-0.5 text-blue-500"></i>
                    <p class="text-[10px] font-semibold leading-relaxed">
                        <strong class="font-bold">Info Operator:</strong> Pastikan menghitung kembali uang fisik di hadapan nasabah sebelum memvalidasi slip transaksi masuk.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <form action="" method="POST">
                        <input type="hidden" name="loket_pilihan" value="A">
                        <input type="hidden" name="jenis_aksi" value="panggil">
                        <input type="hidden" name="aksi_operator" value="1">
                        <button type="submit" class="w-full py-3 bg-gradient-to-br from-blue-500 to-blue-600 hover:opacity-95 text-white font-black text-[10px] uppercase tracking-wider rounded-xl transition-all cursor-pointer active:scale-95 shadow-xs">
                            <i class="fas fa-volume-up mr-1.5"></i> Panggil Next
                        </button>
                    </form>

                    <form action="" method="POST">
                        <input type="hidden" name="loket_pilihan" value="A">
                        <input type="hidden" name="jenis_aksi" value="lewati">
                        <input type="hidden" name="aksi_operator" value="1">
                        <button type="submit" class="w-full py-3 bg-rose-50 text-rose-600 font-black text-[10px] uppercase tracking-wider rounded-xl border border-rose-100 hover:border-rose-200 transition-all cursor-pointer active:scale-95">
                            <i class="fas fa-forward mr-1.5"></i> Lewati (Skip)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Card Informasi Nomor Selanjutnya (Loket A) -->
            <div class="bg-white border border-slate-100 p-4 rounded-xl flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-lg border border-blue-100 flex items-center justify-center text-blue-600 shadow-2xs">
                        <i class="fas fa-angle-double-right text-sm"></i>
                    </div>
                    <div>
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-wider">Antrean Berikutnya</span>
                        <p class="text-[11px] text-slate-500 font-medium">Siap dipanggil oleh sistem loket setor.</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xl font-black text-blue-700 bg-blue-50 shadow-sm px-4 py-1.5 rounded-lg border border-slate-200/60 shadow-2xs">
                        <?= htmlspecialchars($next_a) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- BLOK LOKET B - TARIK TUNAI (WARNA HIJAU)   -->
        <!-- ========================================== -->
        <div class="space-y-4">
            <div class="bg-white p-6 rounded-[1rem] border border-slate-100 shadow-sm flex flex-col justify-between relative overflow-hidden">
                <div class="border-b border-slate-50 pb-3 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-xs">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <h4 class="font-black text-xs text-emerald-700 tracking-wide uppercase">LOKET B - TELLER (TARIK TUNAI)</h4>
                            <p class="text-[10px] text-slate-400 font-medium">Khusus Transaksi Pengambilan Uang</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-md border border-emerald-100 shadow-3xs">
                        Sisa: <?= $total_sisa_b ?> Antrean
                    </span>
                </div>

                <!-- DI SINI: TEMPAT ALERT UNTUK LOKET B -->
                <?php if ($msg_loket['B']['text']): ?>
                    <?php if ($msg_loket['B']['tipe_aksi'] === 'lewati' || $msg_loket['B']['status'] === 'error'): ?>
                        <!-- ALERT MERAH JIKA SKIPPED ATAU ERROR -->
                        <div class="mt-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-center gap-2.5 animate-fade-in">
                            <i class="fas fa-forward text-xs text-rose-500"></i>
                            <span class="text-[10px] font-bold"><?= htmlspecialchars($msg_loket['B']['text']) ?></span>
                        </div>
                    <?php else: ?>
                        <!-- ALERT HIJAU KARENA BERHASIL PANGGIL -->
                        <div class="mt-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl flex items-center gap-2.5 animate-fade-in">
                            <i class="fas fa-check-circle text-xs text-emerald-500"></i>
                            <span class="text-[10px] font-bold"><?= htmlspecialchars($msg_loket['B']['text']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- BOX NOMOR ANTRIAN HIJAU -->
                <div class="my-6 py-8 px-4 bg-emerald-50/40 border border-emerald-100 rounded-2xl text-center">
                    <span class="block text-[12px] font-black text-emerald-600/80 uppercase tracking-widest mb-1">Sedang Dilayani</span>
                    <span class="block text-9xl font-black text-emerald-700 tracking-wider"><?= htmlspecialchars($current_b) ?></span>
                </div>

                <!-- ALERT INFORMATIF LOKET B -->
                <div class="mb-4 p-3 bg-emerald-50/80 border border-emerald-100 text-emerald-900 rounded-xl flex items-start gap-2.5">
                    <i class="fas fa-exclamation-circle text-xs mt-0.5 text-emerald-500"></i>
                    <p class="text-[10px] font-semibold leading-relaxed">
                        <strong class="font-bold">Keamanan:</strong> Harap lakukan verifikasi kartu identitas penarik dan kecocokan tanda tangan sistem sebelum menyerahkan dana tunai.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <form action="" method="POST">
                        <input type="hidden" name="loket_pilihan" value="B">
                        <input type="hidden" name="jenis_aksi" value="panggil">
                        <input type="hidden" name="aksi_operator" value="1">
                        <button type="submit" class="w-full py-3 bg-gradient-to-br from-emerald-500 to-emerald-600 hover:opacity-95 text-white font-black text-[10px] uppercase tracking-wider rounded-xl transition-all cursor-pointer active:scale-95 shadow-xs">
                            <i class="fas fa-volume-up mr-1.5"></i> Panggil Next
                        </button>
                    </form>

                    <form action="" method="POST">
                        <input type="hidden" name="loket_pilihan" value="B">
                        <input type="hidden" name="jenis_aksi" value="lewati">
                        <input type="hidden" name="aksi_operator" value="1">
                        <button type="submit" class="w-full py-3 bg-rose-50 text-rose-600 font-black text-[10px] uppercase tracking-wider rounded-xl border border-rose-100 hover:border-rose-200 transition-all cursor-pointer active:scale-95">
                            <i class="fas fa-forward mr-1.5"></i> Lewati (Skip)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Card Informasi Nomor Selanjutnya (Loket B) -->
            <div class="bg-white border border-slate-100 p-4 rounded-xl flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-lg border border-emerald-100 flex items-center justify-center text-emerald-600 shadow-2xs">
                        <i class="fas fa-angle-double-right text-sm"></i>
                    </div>
                    <div>
                        <span class="block text-[9px] font-black text-slate-400 uppercase tracking-wider">Antrean Berikutnya</span>
                        <p class="text-[11px] text-slate-500 font-medium">Siap dipanggil oleh sistem loket tarik.</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xl font-black text-green-700 bg-green-50 shadow-sm px-4 py-1.5 rounded-lg border border-slate-200/60 shadow-2xs">
                        <?= htmlspecialchars($next_b) ?>
                    </span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- SCRIPT JAVASCRIPT FULLSCREEN -->
<script>
    function toggleFullscreenWorkspace() {
        const workspace = document.getElementById('operator-workspace');
        const icon = document.getElementById('fullscreen-icon');

        if (!document.fullscreenElement) {
            if (workspace.requestFullscreen) {
                workspace.requestFullscreen();
            } else if (workspace.webkitRequestFullscreen) {
                /* Safari */
                workspace.webkitRequestFullscreen();
            } else if (workspace.msRequestFullscreen) {
                /* IE11 */
                workspace.msRequestFullscreen();
            }
            icon.className = "fas fa-compress text-xs";
            workspace.classList.add("p-6", "bg-slate-100");
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
            icon.className = "fas fa-expand text-xs";
            workspace.classList.remove("p-6", "bg-slate-100");
        }
    }

    document.addEventListener('fullscreenchange', () => {
        const icon = document.getElementById('fullscreen-icon');
        const workspace = document.getElementById('operator-workspace');
        if (!document.fullscreenElement) {
            icon.className = "fas fa-expand text-xs";
            workspace.classList.remove("p-6", "bg-slate-100");
        }
    });
</script>