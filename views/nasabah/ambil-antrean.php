<?php

/**
 * Aplikasi Mini Bank Sekolah
 * Berkas: views/nasabah/ambil_antrean.php (VERSI DYNAMIC THEME COLOR)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan variabel koneksi database $pdo sudah didefinisikan sebelum kode ini
$id_nasabah      = $_SESSION['id_user'] ?? $_SESSION['id_nasabah'] ?? null;
$error_msg       = null;
$success_msg     = null;
$hari_ini        = date('Y-m-d');

$tiket_saya      = null;
$nomor_sekarang  = '-';
$nama_loket_now  = '-';
$sisa_antrean    = 0;

// ==========================================
// HANDLE REQUEST AJAX ENDPOINT (UNTUK LIVE MONITOR)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'get_live_monitor') {
    header('Content-Type: application/json');
    $live_nomor = '-';
    $live_loket = '-';
    try {
        $stmt_current = $pdo->query("
            SELECT a.nomor_antrean, l.nama_loket 
            FROM tbl_antrean a
            JOIN tbl_loket l ON a.id_loket = l.id_loket
            WHERE a.tanggal_antrean = '$hari_ini' AND a.status_antrean = 'panggil' 
            ORDER BY a.id_antrean DESC LIMIT 1
        ");
        $current_call = $stmt_current->fetch(PDO::FETCH_ASSOC);
        if ($current_call) {
            $live_nomor = $current_call['nomor_antrean'];
            $live_loket = $current_call['nama_loket'];
        }
    } catch (PDOException $e) {
        // Silently log
    }
    echo json_encode(['nomor' => $live_nomor, 'loket' => $live_loket]);
    exit;
}

// ==========================================
// LOGIKA BACKEND: SISTEM MANAJEMEN ANTREAN DIGITAL
// ==========================================
if ($id_nasabah) {
    try {
        // 1. Ambil info antrean nasabah hari ini yang berstatus 'tunggu' atau 'panggil'
        $stmt_my_ticket = $pdo->prepare("
            SELECT a.*, l.nama_loket 
            FROM tbl_antrean a 
            LEFT JOIN tbl_loket l ON a.id_loket = l.id_loket 
            WHERE a.id_nasabah = ? AND a.tanggal_antrean = ? AND a.status_antrean IN ('tunggu', 'panggil')
            LIMIT 1
        ");
        $stmt_my_ticket->execute([$id_nasabah, $hari_ini]);
        $tiket_saya = $stmt_my_ticket->fetch(PDO::FETCH_ASSOC);

        // 2. Ambil nomor antrean yang SEDANG DIPANGGIL saat ini di bank (global)
        $stmt_current = $pdo->query("
            SELECT a.nomor_antrean, l.nama_loket 
            FROM tbl_antrean a
            JOIN tbl_loket l ON a.id_loket = l.id_loket
            WHERE a.tanggal_antrean = '$hari_ini' AND a.status_antrean = 'panggil' 
            ORDER BY a.id_antrean DESC LIMIT 1
        ");
        $current_call = $stmt_current->fetch(PDO::FETCH_ASSOC);
        if ($current_call) {
            $nomor_sekarang = $current_call['nomor_antrean'];
            $nama_loket_now = $current_call['nama_loket'];
        }

        // 3. Hitung sisa jumlah antrean yang belum dilayani sebelum nomor nasabah
        if ($tiket_saya && $tiket_saya['status_antrean'] === 'tunggu') {
            $jenis_prefix = substr($tiket_saya['nomor_antrean'], 0, 1);

            $stmt_sisa = $pdo->prepare("
                SELECT COUNT(*) as sisa 
                FROM tbl_antrean 
                WHERE tanggal_antrean = ? 
                  AND status_antrean = 'tunggu' 
                  AND nomor_antrean LIKE ?
                  AND angka_urutan < ?
            ");
            $stmt_sisa->execute([$hari_ini, $jenis_prefix . '%', $tiket_saya['angka_urutan']]);
            $res_sisa = $stmt_sisa->fetch(PDO::FETCH_ASSOC);
            $sisa_antrean = $res_sisa['sisa'] ?? 0;
        }
    } catch (PDOException $e) {
        error_log("Gagal mengambil data antrean harian: " . $e->getMessage());
    }
}

// 4. PROSES SUBMIT: AMBIL ANTREAN BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ambil_antrean']) && $id_nasabah) {
    $jenis_layanan = $_POST['jenis_layanan'] ?? 'setor';

    if (!in_array($jenis_layanan, ['setor', 'tarik'])) {
        $error_msg = "Jenis layanan yang Anda pilih tidak valid.";
    } else {
        try {
            $stmt_cek = $pdo->prepare("SELECT id_antrean FROM tbl_antrean WHERE id_nasabah = ? AND tanggal_antrean = ? AND status_antrean IN ('tunggu', 'panggil')");
            $stmt_cek->execute([$id_nasabah, $hari_ini]);

            if ($stmt_cek->fetch()) {
                $error_msg = "Anda sudah memiliki nomor antrean yang aktif untuk hari ini.";
            } else {
                $pdo->beginTransaction();

                $prefix = ($jenis_layanan === 'setor') ? "A" : "B";

                $stmt_max = $pdo->prepare("
                    SELECT MAX(angka_urutan) as terakhir 
                    FROM tbl_antrean 
                    WHERE tanggal_antrean = ? AND nomor_antrean LIKE ? FOR UPDATE
                ");
                $stmt_max->execute([$hari_ini, $prefix . '%']);
                $res_max = $stmt_max->fetch(PDO::FETCH_ASSOC);

                $angka_selanjutnya = intval($res_max['terakhir'] ?? 0) + 1;
                $nomor_antrean_final = $prefix . str_pad($angka_selanjutnya, 2, "0", STR_PAD_LEFT);

                $stmt_insert = $pdo->prepare("
                    INSERT INTO tbl_antrean (nomor_antrean, angka_urutan, id_nasabah, id_loket, tanggal_antrean, status_antrean, created_at) 
                    VALUES (?, ?, ?, NULL, ?, 'tunggu', NOW())
                ");
                $stmt_insert->execute([$nomor_antrean_final, $angka_selanjutnya, $id_nasabah, $hari_ini]);

                $pdo->commit();

                echo "<script>alert('Berhasil mengambil nomor antrean! Nomor Anda: " . $nomor_antrean_final . "'); window.location.href='?page=ambil-antrean';</script>";
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Gagal memproses pengambilan tiket antrean: " . $e->getMessage());
            $error_msg = "Sistem gagal mengeluarkan nomor tiket. Silakan coba sesaat lagi.";
        }
    }
}
?>

<!-- CONTAINER UTAMA -->
<div class="flex flex-col-reverse lg:grid lg:grid-cols-3 gap-4 md:gap-6 max-w-12xl mx-auto items-start">

    <!-- KOLOM UTAMA -->
    <div class="w-full lg:col-span-2 space-y-4 md:space-y-6">

        <!-- Notifikasi -->
        <?php if ($success_msg): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl flex items-center gap-3 text-xs font-bold animate-fade-in">
                <i class="fas fa-check-circle text-lg text-emerald-500 shrink-0"></i>
                <span><?= htmlspecialchars($success_msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-center gap-3 text-xs font-bold animate-fade-in">
                <i class="fas fa-exclamation-circle text-lg text-rose-500 shrink-0"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- KARTU UTAMA MODUL ANTREAN (Diberikan ID untuk manipulasi warna via JS) -->
        <div id="main_card_container" class="bg-white p-4 sm:p-6 md:p-8 rounded-2xl md:rounded-[1rem] border border-slate-100 shadow-sm relative overflow-hidden transition-colors duration-300">

            <!-- Header Card -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50 gap-2">
                <div class="min-w-0">
                    <!-- Text Header diberi ID agar warnanya ikut berubah dinamis -->
                    <!-- <h3 id="main_card_title" class="font-black text-blue-800 flex items-center gap-2 text-sm sm:text-base transition-colors duration-300">
                        <i class="fas fa-ticket-alt text-amber-500 shrink-0"></i> Ambil Antrean Layanan
                    </h3> -->
                    <h3 id="main_card_title" class="font-black text-[#506a8a] flex items-center gap-2">
                        <i class="fas fa-ticket-alt text-amber-500"></i>Ambil Antrean Layanan
                    </h3>
                    <p class="text-[10px] sm:text-[11px] text-slate-400 font-medium whitespace-normal md:truncate">Silakan pilih jenis keperluan transaksi sebelum mencetak nomor urut digital Anda.</p>
                </div>
                <a href="?page=main" class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 hover:text-slate-600 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100 shrink-0 transition-colors">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
            </div>

            <?php if (!$tiket_saya): ?>
                <!-- FORM AMBIL ANTREAN -->
                <form action="" method="POST" class="space-y-6">

                    <!-- KOMPONEN PILIHAN LAYANAN -->
                    <div class="space-y-2">
                        <label class="block text-[10px] sm:text-[11px] font-black text-slate-400 uppercase tracking-wider">Pilih Jenis Layanan Transaksi:</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <!-- Opsi A: Setor Tunai -->
                            <label class="relative flex p-4 bg-slate-50 hover:bg-slate-100/70 border border-slate-200 rounded-xl sm:rounded-2xl cursor-pointer transition-all has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50/40 group select-none">
                                <input type="radio" name="jenis_layanan" value="setor" checked onclick="switchTheme('setor')" class="sr-only">
                                <div class="flex items-center gap-3.5 w-full">
                                    <div class="w-10 h-10 rounded-xl bg-blue-100/80 text-blue-600 flex items-center justify-center text-base shrink-0 group-hover:scale-105 transition-transform">
                                        <i class="fas fa-arrow-alt-circle-down"></i>
                                    </div>
                                    <div class="min-w-0 flex-1 text-left">
                                        <p class="text-xs font-black text-slate-700 group-has-[:checked]:text-blue-950">Setor Dana Tunai</p>
                                        <p class="text-[10px] text-slate-400 group-has-[:checked]:text-blue-700/80 font-medium mt-0.5">Format Antrean: <span class="font-bold">A01, A02, ...</span></p>
                                    </div>
                                    <div class="w-5 h-5 rounded-full border-2 border-slate-300 group-has-[:checked]:border-blue-500 group-has-[:checked]:bg-blue-500 flex items-center justify-center shrink-0 transition-colors">
                                        <div class="w-2 h-2 rounded-full bg-white opacity-0 group-has-[:checked]:opacity-100"></div>
                                    </div>
                                </div>
                            </label>

                            <!-- Opsi B: Tarik Tunai -->
                            <label class="relative flex p-4 bg-slate-50 hover:bg-slate-100/70 border border-slate-200 rounded-xl sm:rounded-2xl cursor-pointer transition-all has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/40 group select-none">
                                <input type="radio" name="jenis_layanan" value="tarik" onclick="switchTheme('tarik')" class="sr-only">
                                <div class="flex items-center gap-3.5 w-full">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-100/80 text-emerald-600 flex items-center justify-center text-base shrink-0 group-hover:scale-105 transition-transform">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="min-w-0 flex-1 text-left">
                                        <p class="text-xs font-black text-slate-700 group-has-[:checked]:text-emerald-950">Penarikan Tunai</p>
                                        <p class="text-[10px] text-slate-400 group-has-[:checked]:text-emerald-700/80 font-medium mt-0.5">Format Antrean: <span class="font-bold">B01, B02, ...</span></p>
                                    </div>
                                    <div class="w-5 h-5 rounded-full border-2 border-slate-300 group-has-[:checked]:border-emerald-500 group-has-[:checked]:bg-emerald-500 flex items-center justify-center shrink-0 transition-colors">
                                        <div class="w-2 h-2 rounded-full bg-white opacity-0 group-has-[:checked]:opacity-100"></div>
                                    </div>
                                </div>
                            </label>

                        </div>
                    </div>

                    <!-- Warning Pengingat Operasional (Diberi ID agar warna background kotak info ikut berubah) -->
                    <div id="info_box_container" class="bg-blue-50 p-4 rounded-xl flex gap-3 border border-blue-100 text-left transition-all duration-300">
                        <i id="info_box_icon" class="fas fa-info-circle text-blue-500 text-sm mt-0.5 shrink-0 transition-colors duration-300"></i>
                        <div class="space-y-1">
                            <p id="info_box_title" class="text-[11px] text-blue-800 font-bold leading-relaxed transition-colors duration-300">Ketentuan Sistem Sistem Antrean:</p>
                            <p class="text-[10px] text-slate-600 font-medium leading-relaxed">
                                Setiap akun siswa dibatasi hanya diperbolehkan mengamankan 1 tiket antrean dengan status aktif per hari. Pastikan Anda berada di sekitar wilayah ruang bank sekolah apabila nomor Anda sudah mendekati antrean panggilan.
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Ambil Antrean (Diberi ID agar warna gradien tombol berubah sesuai tema) -->
                    <div class="space-y-2 text-center">
                        <button type="submit" name="ambil_antrean" id="submit_btn_antrean"
                            class="w-full py-4 hover:opacity-95 sm:bg-gradient-to-br sm:from-[#2978d7] sm:via-[#1566c7] sm:to-[#1257aa] text-white font-black text-xs uppercase tracking-widest rounded-xl hover:opacity-95 transition-all shadow-lg shadow-blue-500/10 cursor-pointer active:scale-[0.99] flex items-center justify-center gap-2">
                            <i class="fas fa-user-plus text-sm"></i> Antri Sekarang
                        </button>
                        <small class="block text-[10px] text-slate-400 font-medium">
                            <i class="fas fa-clock"></i> Tiket otomatis ter-stempel waktu server Mini Bank saat tombol ditekan.
                        </small>
                    </div>
                </form>
            <?php else: ?>
                <!-- TAMPILAN JIKA SUDAH MEMILIKI TIKET AKTIF -->
                <div class="max-w-md mx-auto bg-slate-50 border border-slate-200 rounded-2xl md:rounded-3xl p-5 md:p-6 text-center space-y-4 relative overflow-hidden my-4 shadow-sm">
                    <div class="hidden xs:block absolute -left-4 top-1/2 -translate-y-1/2 w-8 h-8 bg-white border-r border-slate-200 rounded-full"></div>
                    <div class="hidden xs:block absolute -right-4 top-1/2 -translate-y-1/2 w-8 h-8 bg-white border-l border-slate-200 rounded-full"></div>

                    <div>
                        <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest block mb-1">Nomor Antrean Anda</span>

                        <!-- Pewarnaan dinamis tiket berdasarkan string prefix database -->
                        <?php if (strpos($tiket_saya['nomor_antrean'], 'A') === 0): ?>
                            <h1 class="text-5xl sm:text-6xl font-black text-blue-600 tracking-tight my-2"><?= htmlspecialchars($tiket_saya['nomor_antrean']) ?></h1>
                            <div class="mb-3">
                                <span class="px-2.5 py-1 bg-blue-100 text-blue-700 font-bold rounded-lg text-[10px] uppercase tracking-wide">Layanan: Setor Tunai</span>
                            </div>
                        <?php else: ?>
                            <h1 class="text-5xl sm:text-6xl font-black text-emerald-600 tracking-tight my-2"><?= htmlspecialchars($tiket_saya['nomor_antrean']) ?></h1>
                            <div class="mb-3">
                                <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 font-bold rounded-lg text-[10px] uppercase tracking-wide">Layanan: Penarikan Tunai</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($tiket_saya['status_antrean'] === 'panggil'): ?>
                            <div class="inline-block px-4 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 font-black rounded-full text-xs shadow-sm animate-bounce">
                                <i class="fas fa-bullhorn mr-1.5"></i> Menuju: <?= htmlspecialchars($tiket_saya['nama_loket'] ?? 'Meja Teller') ?>
                            </div>
                        <?php else: ?>
                            <div class="inline-block px-4 py-1.5 bg-white border border-slate-200 text-slate-600 font-black rounded-full text-xs shadow-sm">
                                <i class="fas fa-hourglass-half text-amber-500 mr-1.5"></i> Status: Menunggu Giliran
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-dashed border-slate-200 pt-4 px-2 space-y-2">
                        <?php if ($tiket_saya['status_antrean'] === 'tunggu'): ?>
                            <p class="text-xs font-bold text-slate-600">
                                Ada <span class="<?= (strpos($tiket_saya['nomor_antrean'], 'A') === 0) ? 'text-blue-600 bg-blue-50 border-blue-100' : 'text-emerald-600 bg-emerald-50 border-emerald-100' ?> font-black px-1.5 py-0.5 rounded border"><?= $sisa_antrean ?> orang</span> lagi dengan kebutuhan layanan serupa sebelum giliran Anda.
                            </p>
                            <small class="block text-[9px] text-slate-400 font-semibold leading-normal text-center">
                                *Harap tunggu dengan tenang di bangku tunggu. Sisa orang di atas akan berkurang otomatis seiring teller memproses transaksi.
                            </small>
                        <?php else: ?>
                            <p class="text-xs font-black text-emerald-600 uppercase tracking-wide bg-emerald-50/50 p-2 rounded-xl border border-emerald-100 animate-pulse">
                                Giliran Anda Telah Tiba! Silakan Merapat ke Loket.
                            </p>
                            <small class="block text-[9px] text-emerald-600 font-semibold leading-normal text-center">
                                *Bawa buku tabungan, kartu pelajar, beserta dokumen transaksi Anda langsung ke meja teller loket yang memanggil.
                            </small>
                        <?php endif; ?>

                        <div class="flex flex-col sm:flex-row justify-between items-center text-[10px] font-bold text-slate-400 pt-2 gap-1">
                            <span>Waktu Cetak: <?= date('d/m/Y H:i', strtotime($tiket_saya['created_at'])) ?> WIB</span>
                            <span class="uppercase tracking-widest text-slate-500">Mini Bank</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- KOLOM MONITOR LIVE ANTREAN GLOBAL -->
    <div class="w-full lg:col-span-1">
        <div id="section-grafik" class="space-y-8">
            <div class="bg-white p-4 sm:p-6 rounded-2xl md:rounded-[1rem] border border-slate-100 shadow-sm">
                <div class="flex justify-between items-center mb-6 pb-3 border-b border-slate-50">
                    <div>
                        <h5 class="text-sm font-black text-slate-800">
                            <i class="fas fa-tv text-blue-500"></i>&ensp;Monitor Antrean Live
                        </h5>
                    </div>
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                </div>

                <!-- DISPLAY MONITOR UTAMA -->
                <div class="bg-gradient-to-br from-slate-900 to-blue-950 p-6 rounded-xl md:rounded-2xl text-center text-white relative overflow-hidden shadow-md">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">Sedang Dilayani</span>
                    <h1 id="live_nomor_antrean" class="text-4xl sm:text-5xl font-black text-amber-400 tracking-tight my-1 font-mono"><?= $nomor_sekarang ?></h1>

                    <div class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 rounded-full text-[10px] sm:text-[11px] font-bold max-w-full truncate">
                        <i class="fas fa-desktop text-blue-300 shrink-0"></i> Panggilan di: &nbsp;<span id="live_nama_loket" class="text-emerald-300 font-black truncate"><?= htmlspecialchars($nama_loket_now) ?></span>
                    </div>
                    <i class="fas fa-volume-up absolute -right-6 -bottom-6 text-white/5 text-7xl pointer-events-none"></i>
                </div>

                <div class="mt-6 space-y-3 border-t border-slate-50 pt-4">
                    <div class="flex items-center gap-3 p-3 bg-blue-50/50 rounded-xl border border-blue-100/50">
                        <div class="w-8 h-8 shrink-0 bg-white rounded-lg flex items-center justify-center text-blue-500 shadow-sm">
                            <i class="fas fa-sync-alt text-xs animate-spin" style="animation-duration: 4s;"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[9px] font-bold text-blue-600 uppercase tracking-wide">Pembaruan Otomatis (Realtime)</p>
                            <p class="text-[10px] text-slate-500 font-medium leading-tight mt-0.5">Data di monitor ini akan otomatis ter-update setiap 5 detik tanpa perlu me-refresh browser.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
JAVASCRIPT REALTIME FETCH & THEME CONTROL
========================================== -->
<script>
    // Fungsi untuk mengubah skema warna form secara dinamis (Realtime Theme Switcher)
    function switchTheme(type) {
        // Element target manipulasi warna
        const mainCard = document.getElementById('main_card_container');
        const mainTitle = document.getElementById('main_card_title');
        const infoBox = document.getElementById('info_box_container');
        const infoIcon = document.getElementById('info_box_icon');
        const infoTitle = document.getElementById('info_box_title');
        const submitBtn = document.getElementById('submit_btn_antrean');

        if (!mainCard) return; // Proteksi jika user sudah punya tiket (form tersembunyi)

        if (type === 'tarik') {
            // == TEMA HIJAU (PENARIKAN TUNAI) ==
            mainCard.style.borderColor = '#bbf7d0'; // emerald-200

            mainTitle.classList.remove('text-[#506a8a]');
            mainTitle.classList.add('text-[#506a8a]');

            infoBox.className = "p-4 bg-emerald-50 rounded-xl flex gap-3 border border-emerald-100 text-left transition-all duration-300";
            infoIcon.className = "fas fa-info-circle text-emerald-500 text-sm mt-0.5 shrink-0 transition-colors duration-300";
            infoTitle.className = "text-[11px] text-emerald-800 font-bold leading-relaxed transition-colors duration-300";

            submitBtn.className = "w-full py-4 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black text-xs uppercase tracking-widest rounded-xl hover:opacity-95 transition-all shadow-lg shadow-emerald-500/10 cursor-pointer active:scale-[0.99] flex items-center justify-center gap-2";
        } else {
            // == TEMA BIRU (SETOR TUNAI) ==
            mainCard.style.borderColor = '#e2e8f0'; // slate-100

            mainTitle.classList.remove('text-emerald-800');
            mainTitle.classList.add('text-blue-800');

            infoBox.className = "p-4 bg-blue-50 rounded-xl flex gap-3 border border-blue-100 text-left transition-all duration-300";
            infoIcon.className = "fas fa-info-circle text-blue-500 text-sm mt-0.5 shrink-0 transition-colors duration-300";
            infoTitle.className = "text-[11px] text-blue-800 font-bold leading-relaxed transition-colors duration-300";

            submitBtn.className = "w-full py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black text-xs uppercase tracking-widest rounded-xl hover:opacity-95 transition-all shadow-lg shadow-blue-500/10 cursor-pointer active:scale-[0.99] flex items-center justify-center gap-2";
        }
    }

    // Fungsi AJAX Monitor Live
    function refreshLiveMonitor() {
        const currentUrl = window.location.href.split('&')[0].split('?')[0];
        const fetchUrl = currentUrl + "?page=ambil-antrean&action=get_live_monitor";

        fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                document.getElementById('live_nomor_antrean').innerText = data.nomor;
                document.getElementById('live_nama_loket').innerText = data.loket;
            })
            .catch(error => console.error("Gagal memperbarui monitor antrean:", error));
    }

    setInterval(refreshLiveMonitor, 5000);
</script>