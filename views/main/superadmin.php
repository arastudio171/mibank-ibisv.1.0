<?php
// =========================================================================
// 0. INISIALISASI VARIABEL UTAMA
// =========================================================================
$nama  = $_SESSION['nama'] ?? 'Pengguna';
$today = date('Y-m-d');

// =========================================================================
// 1. METRIK UTAMA HARIAN & BULANAN (REAL-TIME KAS, TABUNGAN & OPERASIONAL)
// =========================================================================

// A. Total Setoran Hari Ini (Menambah Kas Likuid)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.jumlah), 0) 
    FROM tbl_transaksi t 
    JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi 
    WHERE DATE(t.tanggal_transaksi) = ? 
      AND jt.kode_jenis = 'setor' 
      AND t.status_approval = 'approved'
");
$stmt->execute([$today]);
$setoran_hari_ini = (float)$stmt->fetchColumn();

// B. Total Penarikan Hari Ini (Mengurangi Kas Likuid)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.jumlah), 0) 
    FROM tbl_transaksi t 
    JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi 
    WHERE DATE(t.tanggal_transaksi) = ? 
      AND jt.kode_jenis = 'tarik' 
      AND t.status_approval = 'approved'
");
$stmt->execute([$today]);
$penarikan_hari_ini = (float)$stmt->fetchColumn();

// C. Total Penerimaan Infaq Hari Ini 
// (Diperbaiki: Mengarah langsung ke tabel khusus tbl_transaksi_infaq)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(nominal_infaq), 0) 
    FROM tbl_transaksi_infaq 
    WHERE DATE(tanggal_infaq) = ?
");
$stmt->execute([$today]);
$infaq_hari_ini = (float)$stmt->fetchColumn();

// =========================================================================
// D. FIX LOGIKA BARU: PENDAPATAN OPERASIONAL (HARIAN vs BULANAN) - 100% AMAN
// =========================================================================

// D1. Pendapatan Admin Harian (Murni dari biaya_admin Tarik Tunai & Transfer Hari Ini)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.biaya_admin), 0) 
    FROM tbl_transaksi t
    JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi
    WHERE DATE(t.tanggal_transaksi) = ? 
      AND t.status_approval = 'approved'
      AND jt.kode_jenis IN ('tarik', 'transfer')
");
$stmt->execute([$today]);
$admin_hari_ini = (float)$stmt->fetchColumn();

// D2. Total Pendapatan Bulanan (Dari akumulasi Autodebet Potongan Massal setiap Bulan Berjalan)
$bulan_sekarang = date('Y-m');
$stmt_bulanan = $pdo->prepare("
    SELECT COALESCE(SUM(jumlah), 0) 
    FROM tbl_transaksi 
    WHERE id_jenis_transaksi = 5 
      AND id_metode_transaksi = 4 
      AND DATE_FORMAT(tanggal_transaksi, '%Y-%m') = ?
      AND status_approval = 'approved'
");
$stmt_bulanan->execute([$bulan_sekarang]);
$total_pendapatan_bulanan = (float)$stmt_bulanan->fetchColumn();

// =========================================================================

// E. Total Tabungan Keseluruhan (Posisi Saldo Riil Seluruh Nasabah Aktif)
$stmt_total_saldo = $pdo->query("
    SELECT COALESCE(SUM(saldo), 0) 
    FROM tbl_nasabah 
    WHERE status_nasabah = 'aktif'
");
$total_tabungan_keseluruhan = (float)$stmt_total_saldo->fetchColumn();


// =========================================================================
// 2. ANALISIS OPERASIONAL & PERSENTASE STATUS TRANSAKSI
// =========================================================================

// A. Hitung Arus Kas Bersih Harian (Net Cashflow dari Aktivitas Setor vs Tarik)
$arus_kas_summary = $setoran_hari_ini - $penarikan_hari_ini;

// B. Statistik Komposisi Status Transaksi Hari Ini (Untuk Keperluan Progress Bar UI)
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status_approval = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status_approval = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status_approval = 'rejected' THEN 1 ELSE 0 END) as rejected,
        COUNT(*) as total
    FROM tbl_transaksi 
    WHERE DATE(tanggal_transaksi) = ?
");
$stmt->execute([$today]);
$status_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_tx     = $status_stats['total'] ?? 0;
$pct_approved = $total_tx > 0 ? round(($status_stats['approved'] / $total_tx) * 100) : 0;
$pct_pending  = $total_tx > 0 ? round(($status_stats['pending'] / $total_tx) * 100) : 0;


// =========================================================================
// 3. LOGIKA DATA GRAFIK (TREN BERJALAN / ENGINE MONITOR DETAK JANTUNG 7 HARI)
// =========================================================================
$chart_labels = [];
$chart_setor  = [];
$chart_tarik  = [];
$chart_saldo  = [];

// Tentukan batas awal tanggal (7 hari yang lalu termasuk hari ini)
$start_date = date('Y-m-d', strtotime('-6 days'));

// A. Tarik akumulasi SETORAN harian selama 7 hari terakhir
$stmt_setor = $pdo->prepare("
    SELECT DATE(t.tanggal_transaksi) as tgl, SUM(t.jumlah) as total 
    FROM tbl_transaksi t 
    JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi 
    WHERE DATE(t.tanggal_transaksi) >= ? 
      AND jt.kode_jenis = 'setor' 
      AND t.status_approval = 'approved'
    GROUP BY DATE(t.tanggal_transaksi)
");
$stmt_setor->execute([$start_date]);
$raw_setor = $stmt_setor->fetchAll(PDO::FETCH_KEY_PAIR);

// B. Tarik akumulasi PENARIKAN harian selama 7 hari terakhir
$stmt_tarik = $pdo->prepare("
    SELECT DATE(t.tanggal_transaksi) as tgl, SUM(t.jumlah) as total 
    FROM tbl_transaksi t 
    JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi 
    WHERE DATE(t.tanggal_transaksi) >= ? 
      AND jt.kode_jenis = 'tarik' 
      AND t.status_approval = 'approved'
    GROUP BY DATE(t.tanggal_transaksi)
");
$stmt_tarik->execute([$start_date]);
$raw_tarik = $stmt_tarik->fetchAll(PDO::FETCH_KEY_PAIR);

// C. Hitung base Saldo Awal bank SEBELUM rentang periode 7 hari ini dimulai
$stmt_base_saldo = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(jumlah), 0) FROM tbl_transaksi t JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi WHERE DATE(tanggal_transaksi) < ? AND jt.kode_jenis = 'setor' AND t.status_approval = 'approved') - 
        (SELECT COALESCE(SUM(jumlah + biaya_admin), 0) FROM tbl_transaksi t JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi WHERE DATE(tanggal_transaksi) < ? AND jt.kode_jenis = 'tarik' AND t.status_approval = 'approved') 
    AS saldo_awal
");
$stmt_base_saldo->execute([$start_date, $start_date]);
$running_balance = (float)$stmt_base_saldo->fetchColumn();

// D. Looping terbalik untuk menyusun urutan kronologis grafik (Hari ke-1 hingga Hari ke-7)
for ($i = 6; $i >= 0; $i--) {
    $date_target = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date_target));

    $setor_hari_ini_chart = isset($raw_setor[$date_target]) ? (float)$raw_setor[$date_target] : 0.0;
    $tarik_hari_ini_chart = isset($raw_tarik[$date_target]) ? (float)$raw_tarik[$date_target] : 0.0;

    // Kalkulasi saldo berjalan secara kumulatif untuk sumbu grafik utama
    $running_balance += ($setor_hari_ini_chart - $tarik_hari_ini_chart);

    $chart_setor[] = $setor_hari_ini_chart;
    $chart_tarik[] = $tarik_hari_ini_chart;
    $chart_saldo[] = $running_balance;
}


// =========================================================================
// 4. JURNAL AKTIVITAS TRANSAKSI TERBARU (WIDGET MUTASI MULTI-IKON)
// =========================================================================
$stmt = $pdo->query("
    SELECT t.*, jt.kode_jenis AS jenis_transaksi, jt.nama_jenis, n.nama_nasabah, n.kelas, j.nama_jurusan 
    FROM tbl_transaksi t
    JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi
    JOIN tbl_nasabah n ON t.id_nasabah = n.id_nasabah
    LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
    ORDER BY t.tanggal_transaksi DESC LIMIT 6
");
$recent_logs = $stmt->fetchAll();


// =========================================================================
// 5. FUNGSI PEMBANTU (HELPER FUNCTIONS)
// =========================================================================

if (!function_exists('timeAgo')) {
    function timeAgo($timestamp)
    {
        $time = time() - strtotime($timestamp);
        if ($time < 60) return 'Baru saja';

        $m = round($time / 60);
        if ($m < 60) return $m . ' mnt lalu';

        $h = round($m / 60);
        if ($h < 24) return $h . ' jam lalu';

        return date('d/m', strtotime($timestamp));
    }
}

if (!function_exists('formatShortAmount')) {
    function formatShortAmount($amount)
    {
        if ($amount >= 1000000) {
            return round($amount / 1000000, 1) . 'jt';
        } elseif ($amount >= 1000) {
            return round($amount / 1000, 1) . 'rb';
        }
        return number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('timeAgoPrecision')) {
    function timeAgoPrecision($timestamp)
    {
        $time = time() - strtotime($timestamp);

        if ($time < 1) return '1 detik lalu';
        if ($time < 60) return $time . ' detik lalu';

        $m = floor($time / 60);
        if ($m < 60) {
            $s = $time % 60;
            return $m . ' menit' . ($s > 0 ? ' ' . $s . ' detik' : '') . ' lalu';
        }

        $h = floor($m / 60);
        if ($h < 24) {
            $m_rem = $m % 60;
            return $h . ' jam' . ($m_rem > 0 ? ' ' . $m_rem . ' menit' : '') . ' lalu';
        }

        return date('d M Y • H:i:s', strtotime($timestamp));
    }
}

// Ambil 3 log aktivitas terbaru
$stmt_log = $pdo->query("
    SELECT 
        l.*, 
        u.username AS nama_operator,
        n.nama_nasabah,
        n.nisn,      
        n.kelas,     
        j.nama_jurusan
    FROM log_activity l
    LEFT JOIN tbl_users u ON l.id_user = u.id_user
    LEFT JOIN tbl_nasabah n ON l.id_nasabah = n.id_nasabah
    LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
    ORDER BY l.timestamp DESC 
    LIMIT 3
");
$activity_logs = $stmt_log->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('parseAgent')) {
    function parseAgent($ua)
    {
        if (empty($ua)) return 'Unknown Device';
        if (preg_match('/android/i', $ua)) return 'Android';
        if (preg_match('/iphone|ipad/i', $ua)) return 'iOS Device';
        if (preg_match('/windows/i', $ua)) return 'Windows PC';
        if (preg_match('/macintosh|mac os x/i', $ua)) return 'MacBook/Mac';
        if (preg_match('/linux/i', $ua)) return 'Linux PC';
        return 'Generic Device';
    }
}
?>

<div id="section-dashboard" class="space-y-6">

    <!-- Tambahkan x-data="{ alertOpen: true }" agar Alpine.js tahu status awal alert ini aktif -->
    <div x-data="{ alertOpen: true }"
        x-show="alertOpen"
        x-transition:leave="transition ease-in duration-300 transform opacity-0 -translate-y-2"
        class="flex items-start justify-between p-3.5 sm:p-4 bg-blue-50 border border-blue-200 rounded-[1rem] text-blue-800 shadow-sm">

        <div class="flex gap-2.5 sm:gap-3">
            <!-- Indikator Ikon -->
            <div class="shrink-0 mt-0.5">
                <i class="fas fa-info-circle text-yellow-600 text-base sm:text-lg animate-pulse"></i>
            </div>

            <!-- Konten Teks Operasional Bank -->
            <div class="space-y-0.5 sm:space-y-1">
                <h5 class="text-xs sm:text-sm font-bold tracking-tight">Otentikasi & Sesi Aman Terminal</h5>
                <p class="text-[11px] sm:text-xs text-slate-700/90 leading-relaxed">
                    Selamat bertugas, <span class="font-bold text-rose-600"><?= htmlspecialchars($nama) ?></span>. Terminal kas Anda telah diaktifkan. Seluruh pencatatan mutasi laci kas <b>*cash drawer*</b>, log jurnal transaksi, dan rekonsiliasi sistem disinkronkan langsung ke <b>*core banking*</b> secara real-time. Sesi aman operasional berlaku hingga <span class="font-bold"><?= date('H:i:s', strtotime('+15 minutes')) ?> WIB</span>.
                </p>
            </div>
        </div>

        <!-- Tombol Tutup (Dismiss Button) -->
        <button @click="alertOpen = false" class="shrink-0 p-1 text-blue-500 hover:text-blue-800 hover:bg-blue-100/50 rounded-lg transition-colors cursor-pointer ml-1">
            <i class="fas fa-times text-xs sm:text-sm"></i>
        </button>
    </div>

    <!-- DASHBOARD CARD -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- ========================================================================= -->
        <!-- MAKRO VIEW: CARD 1 - TOTAL TABUNGAN KESELURUHAN (xl:col-span-2 / LEBIH LEBAR) -->
        <!-- ========================================================================= -->
        <div x-data="{ 
        showBalance: false, 
        isPromptOpen: false, 
        inputPin: '', 
        correctPin: '334-10807055', 
        errorMessage: '' 
     }"
            class="xl:col-span-2 relative overflow-hidden bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] rounded-[1rem] text-white p-6 flex flex-col justify-between group min-h-[230px]">

            <div class="absolute -top-12 -right-12 w-36 h-36 bg-white/10 rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700"></div>
            <div class="absolute -bottom-6 -left-6 w-24 h-24 bg-blue-500/20 rounded-full blur-xl"></div>

            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <span class="px-2.5 py-1 bg-white/15 text-blue-200 text-[9px] font-extrabold uppercase tracking-widest rounded-md border border-white/10">
                        <i class="fas fa-vault mr-1"></i> Global Asset Analytics
                    </span>
                    <h5 class="text-xs font-black text-blue-200/90 uppercase tracking-widest mt-4">Total Liquiditas Tabungan Nasabah 1 Tahun</h5>
                </div>
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/15">
                    <i class="fas fa-university text-sm text-blue-300"></i>
                </div>
            </div>

            <div class="relative z-10 my-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl md:text-3xl font-black tracking-tight text-white flex items-baseline gap-1">
                        <span x-show="showBalance">Rp <?= number_format($total_tabungan_keseluruhan, 0, ',', '.') ?></span>
                        <span x-show="!showBalance" class="tracking-widest text-blue-200/80">Rp •••••••••</span>
                    </h2>

                    <button @click="if(showBalance) { showBalance = false; } else { isPromptOpen = !isPromptOpen; errorMessage = ''; inputPin = ''; }"
                        class="p-1.5 bg-white/10 hover:bg-white/20 rounded-lg border border-white/10 transition-colors cursor-pointer text-xs"
                        title="Otorisasi Akses Saldo">
                        <i class="fas" :class="showBalance ? 'fa-eye-slash text-rose-300' : 'fa-eye text-emerald-300'"></i>
                    </button>
                </div>

                <div x-show="isPromptOpen" x-transition class="mt-3 p-2.5 bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg max-w-[320px]">
                    <p class="text-[9px] uppercase tracking-wider font-black text-blue-100 mb-1">Otorisasi Hak Akses Khusus</p>
                    <div class="flex gap-2">
                        <input type="password" maxlength="12" placeholder="PIN" x-model="inputPin" @keyup.enter="if(inputPin === correctPin) { showBalance = true; isPromptOpen = false; } else { errorMessage = 'PIN Salah / Akses Ditolak'; }"
                            class="w-36 bg-white/15 border border-white/20 rounded px-2 py-1 text-xs text-center tracking-widest text-white placeholder-white/40 focus:outline-none focus:border-emerald-300">
                        <button @click="if(inputPin === correctPin) { showBalance = true; isPromptOpen = false; } else { errorMessage = 'PIN Salah / Akses Ditolak'; }"
                            class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded transition-colors cursor-pointer shrink-0">
                            Verify
                        </button>
                        <button @click="isPromptOpen = false" class="px-2 py-1 bg-white/10 hover:bg-white/20 text-blue-100 hover:text-white text-xs rounded transition-colors cursor-pointer shrink-0">
                            Batal
                        </button>
                    </div>
                    <p x-show="errorMessage" x-text="errorMessage" class="text-[9px] text-rose-300 font-bold mt-1 animate-pulse"></p>
                </div>

                <p class="text-[10px] text-blue-100/70 font-medium mt-1 leading-tight">
                    Akumulasi real-time seluruh saldo berjalan yang dimiliki oleh nasabah dengan status <span class="text-amber-300 font-bold">Aktif</span> di dalam database sistem.
                </p>
            </div>

            <div class="relative z-10 pt-2 border-t border-white/10 flex justify-between items-center text-[10px] font-bold text-blue-200/80">
                <div class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full" :class="showBalance ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                    Status Core: <span x-text="showBalance ? 'Terbuka' : 'Terproteksi'"></span>
                </div>
                <div class="flex items-center gap-1 border-l border-white/10 pl-3">
                    <i class="fas fa-database mr-1"></i> Source: tbl_nasabah
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MIKRO VIEW: CARD 2 - NET TRANSAKSI HARIAN (xl:col-span-1 / LEBIH KECIL) -->
        <!-- ========================================================================= -->
        <div class="xl:col-span-1 relative overflow-hidden bg-gradient-to-br from-[#0ea5e9] via-[#0284c7] to-[#0369a1] rounded-[1rem] text-white p-6 flex flex-col justify-between group min-h-[220px]">
            <div class="absolute -top-12 -right-12 w-36 h-36 bg-white/10 rounded-full blur-2xl group-hover:scale-125 transition-transform duration-700"></div>
            <div class="absolute -bottom-6 -left-6 w-24 h-24 bg-sky-400/20 rounded-full blur-xl"></div>

            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <span class="px-2.5 py-1 bg-white/15 text-sky-100 text-[9px] font-extrabold uppercase tracking-widest rounded-md border border-white/10">
                        <i class="fas fa-chart-line mr-1"></i> Daily Flow
                    </span>
                    <h5 class="text-xs font-black text-sky-200/90 uppercase tracking-widest mt-4">Arus Kas Masuk Bersih</h5>
                </div>
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/15 shadow-inner">
                    <i class="fas fa-cash-register text-sm text-sky-200"></i>
                </div>
            </div>

            <div class="relative z-10 my-4">
                <h2 class="text-2xl md:text-3xl font-black tracking-tight text-white flex items-baseline gap-1">
                    Rp <?= number_format($arus_kas_summary, 0, ',', '.') ?>
                </h2>
                <p class="text-[10px] text-sky-100/80 font-medium mt-1 leading-tight">
                    Total perputaran uang masuk hari ini (Setoran bersih setelah dikurangi penarikan tunai operasional).
                </p>
            </div>

            <div class="relative z-10 pt-2 border-t border-white/10 flex justify-between items-center text-[10px] font-bold text-sky-200/80">
                <div class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Buku Kas: Terkunci
                </div>
                <div class="flex items-center gap-1 border-l border-white/10 pl-3">
                    <i class="fas fa-exchange-alt mr-1"></i> Source: tbl_transaksi
                </div>
            </div>
        </div>

    </div>

    <!-- TOTAL SETORAN, PENARIKAN, DAN PENDAPATAN -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="?page=penyetoran-dana" class="bg-white p-5 rounded-[1rem] border border-slate-100 shadow-sm flex items-center justify-between group transition-all">
            <div>
                <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest mb-1">Total Setoran Hari Ini</p>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight">Rp <?= number_format($setoran_hari_ini, 0, ',', '.') ?></h3>
                <p class="text-[10px] text-slate-500 font-medium mt-1">
                    <i class="fas fa-coins text-yellow-500 mr-1"></i> Akumulasi penyetoran transaksi hari ini
                    <span class="inline-block transition-transform group-hover:translate-x-1 ml-1">→</span>
                </p>
            </div>
            <div class="w-10 h-10 bg-emerald-500 text-white rounded-xl flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-arrow-down-long"></i>
            </div>
        </a>

        <a href="?page=penarikan-dana" class="bg-white p-5 rounded-[1rem] border border-slate-100 shadow-sm flex items-center justify-between group transition-all">
            <div>
                <p class="text-[10px] font-bold text-amber-600 uppercase tracking-widest mb-1">Total Penarikan Hari Ini</p>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight">Rp <?= number_format($penarikan_hari_ini, 0, ',', '.') ?></h3>
                <p class="text-[10px] text-slate-500 font-medium mt-1">
                    <i class="fas fa-coins text-yellow-500 mr-1"></i> Akumulasi penarikan transaksi hari ini
                    <span class="inline-block transition-transform group-hover:translate-x-1 ml-1">→</span>
                </p>
            </div>
            <div class="w-10 h-10 bg-amber-500 text-white rounded-xl flex items-center justify-center text-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-arrow-up-long"></i>
            </div>
        </a>

        <a href="?page=pendapatan" class="bg-gradient-to-br from-[#6366f1] via-[#4f46e5] to-[#4338ca] p-5 rounded-[1rem] shadow-sm flex items-center justify-between group transition-all block">
            <div>
                <p class="text-[10px] font-bold text-white uppercase tracking-widest mb-1">Total Pendapatan Harian</p>
                <h3 class="text-2xl font-black text-yellow-200 tracking-tight">Rp <?= number_format($admin_hari_ini, 0, ',', '.') ?></h3>
                <p class="text-[10px] text-white font-medium mt-1">
                    <i class="fas fa-coins mr-1"></i> Akumulasi pendapatan transaksi hari ini
                    <span class="inline-block transition-transform group-hover:translate-x-1 ml-1">→</span>
                </p>
            </div>
            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/15 text-lg group-hover:scale-110 transition-transform">
                <i class="fas fa-wallet text-white"></i>
            </div>
        </a>
    </div>

    <!-- ANALYSIS CARDS -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 bg-white p-5 md:p-6 rounded-[1rem] border border-slate-100 shadow-sm flex flex-col justify-between gap-4">

            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                <div class="flex flex-col gap-1">
                    <h5 class="text-base font-black text-slate-800 tracking-tight flex items-center gap-2">
                        <i class="fas fa-chart-line text-emerald-500"></i>
                        Analisis Fluktuasi & Tren Kas
                    </h5>
                    <p class="text-xs text-slate-400 flex items-start sm:items-center gap-1.5 leading-relaxed">
                        <i class="fas fa-info-circle text-slate-400/90 mt-0.5 sm:mt-0 text-[11px]"></i>
                        Visualisasi real-time arus kas masuk, kas keluar, dan akumulasi saldo mengendap 7 hari terakhir.
                    </p>
                </div>

                <div class="flex items-center gap-2.5 p-2 px-3 bg-emerald-50/80 border border-emerald-100 rounded-xl text-emerald-800 w-full sm:w-auto sm:max-w-[240px] self-start sm:self-center transition-all duration-300 hover:shadow-sm">
                    <div class="flex-shrink-0 w-6 h-6 rounded-md bg-emerald-500/10 flex items-center justify-center">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black tracking-tight text-emerald-900 leading-tight">Live Auto-Sync</span>
                        <span class="text-[9px] font-medium text-emerald-600/90 leading-tight">Grafik diperbarui instan</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 my-1">

                <div class="p-3 bg-emerald-50/40 border border-emerald-100 rounded-xl flex items-start gap-2 text-emerald-800 text-[11px] leading-snug">
                    <i class="fas fa-arrow-alt-circle-down text-emerald-500 mt-0.5 text-sm flex-shrink-0"></i>
                    <div>
                        <span class="font-bold block text-emerald-900 mb-0.5">Kas Masuk (Setoran):</span>
                        Total dana tervalidasi <span class="bg-emerald-100 text-emerald-800 px-1 py-0.5 rounded font-black text-[9px]">APPROVED</span> yang menambah likuiditas.
                    </div>
                </div>

                <div class="p-3 bg-amber-50/40 border border-amber-100 rounded-xl flex items-start gap-2 text-amber-800 text-[11px] leading-snug">
                    <i class="fas fa-arrow-alt-circle-up text-amber-500 mt-0.5 text-sm flex-shrink-0"></i>
                    <div>
                        <span class="font-bold block text-amber-900 mb-0.5">Kas Keluar (Penarikan):</span>
                        Total penarikan tunai <span class="bg-amber-100 text-amber-800 px-1 py-0.5 rounded font-black text-[9px]">APPROVED</span> termasuk biaya admin loket.
                    </div>
                </div>

                <div class="p-3 bg-blue-50/50 border border-blue-100 rounded-xl flex items-start gap-2 text-blue-800 text-[11px] leading-snug">
                    <i class="fas fa-wallet text-blue-500 mt-0.5 text-sm flex-shrink-0"></i>
                    <div>
                        <span class="font-bold block text-blue-900 mb-0.5">Saldo Mengendap:</span>
                        Akumulasi riil posisi kas aman <span class="bg-blue-100 text-blue-800 px-1 py-0.5 rounded font-black text-[9px]">NET BALANCE</span> seluruh tabungan.
                    </div>
                </div>

            </div>

            <div class="relative w-full h-[290px] mt-1">
                <canvas id="cashflowChart"></canvas>
            </div>
        </div>

        <div class="space-y-6">

            <a href="main.php?page=pendapatan-bulanan" class="bg-gradient-to-br from-[#a855f7] via-[#8b5cf6] to-[#6d28d9] 0 p-5 rounded-[1rem] shadow-sm flex items-center justify-between group hover:shadow-md cursor-pointer transition-all block relative overflow-hidden">

                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-white uppercase tracking-widest mb-1">Total Pendapatan Bulanan</p>
                    <h3 class="text-2xl font-black text-white tracking-tight mt-1">
                        Rp <?= number_format($total_pendapatan_bulanan ?? 0, 0, ',', '.') ?>
                    </h3>
                    <p class="text-[10px] text-indigo-100/90 font-medium mt-1">
                        <i class="fas fa-chart-line mr-1 text-amber-300"></i> Akumulasi laba pengelolaan kas bulan ini
                        <span class="inline-block transition-transform group-hover:translate-x-1 ml-1">→</span>
                    </p>
                </div>

                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/15 text-lg group-hover:scale-110 group-hover:bg-white/20 transition-all shrink-0 relative z-10">
                    <i class="fas fa-wallet text-white"></i>
                </div>
            </a>

            <a href="main.php?page=infaq-nasabah" class="bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-700 p-5 rounded-[1rem] shadow-sm flex items-center justify-between group hover:shadow-md cursor-pointer transition-all block relative overflow-hidden">

                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-white uppercase tracking-widest mb-1">Total Penerimaan Infaq</p>
                    <h3 class="text-2xl font-black text-white tracking-tight mt-1">
                        Rp <?= number_format($infaq_hari_ini, 0, ',', '.') ?>
                    </h3>
                    <p class="text-[10px] text-emerald-100/90 font-medium mt-1">
                        <i class="fas fa-calculator mr-1 text-amber-300"></i> Akumulasi penerimaan infaq per hari ini
                        <span class="inline-block transition-transform group-hover:translate-x-1 ml-1">→</span>
                    </p>
                </div>

                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/15 text-lg group-hover:scale-110 group-hover:bg-white/20 transition-all shrink-0 relative z-10">
                    <i class="fas fa-hand-holding-heart text-white"></i>
                </div>
            </a>

            <div class="bg-white p-5 rounded-[1rem] border border-slate-100 shadow-sm flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h5 class="text-sm font-black text-slate-800 tracking-tight flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            Aktivitas Transaksi Terkini
                        </h5>
                        <p class="text-[11px] text-slate-400">Aktivitas mutasi live dari seluruh nasabah</p>
                    </div>
                    <span class="text-[9px] font-bold bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">
                        <?= count($recent_logs) ?> Log
                    </span>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto max-h-[240px] pr-1">
                    <?php foreach ($recent_logs as $log):
                        // Ambil waktu jam:menit:detik asli untuk tooltip HTML (saat kursor diarahkan)
                        $exact_time = date('H:i:s', strtotime($log['tanggal_transaksi']));

                        // LOGIKA SWITCH-CASE WARNA DAN IKON BERDASARKAN JENIS TRANSAKSI
                        switch ($log['jenis_transaksi']) {
                            case 'setor':
                                $icon_class   = 'fa-arrow-down';
                                $bg_badge     = 'bg-emerald-50 text-emerald-600 border-emerald-100/60';
                                $text_amount  = 'text-emerald-600';
                                $prefix       = '+';
                                break;
                            case 'tarik':
                                $icon_class   = 'fa-arrow-up';
                                $bg_badge     = 'bg-rose-50 text-rose-600 border-rose-100/60';
                                $text_amount  = 'text-rose-600';
                                $prefix       = '-';
                                break;
                            case 'infaq':
                                $icon_class   = 'fa-hand-holding-heart';
                                $bg_badge     = 'bg-purple-50 text-purple-600 border-purple-100/60';
                                $text_amount  = 'text-purple-600';
                                $prefix       = '-';
                                break;
                            case 'transfer':
                                $icon_class   = 'fa-exchange-alt';
                                $bg_badge     = 'bg-blue-50 text-blue-600 border-blue-100/60';
                                $text_amount  = 'text-blue-600';
                                $prefix       = '-';
                                break;
                            default:
                                $icon_class   = 'fa-receipt';
                                $bg_badge     = 'bg-slate-50 text-slate-600 border-slate-200/60';
                                $text_amount  = 'text-slate-700';
                                $prefix       = '• ';
                                break;
                        }
                    ?>
                        <div class="flex items-center gap-3 border-b border-slate-50 pb-2.5 last:border-none last:pb-0 hover:bg-slate-50/40 p-1 rounded-lg transition-all">

                            <div class="w-8 h-8 rounded-xl border <?= $bg_badge ?> flex items-center justify-center shrink-0 text-xs shadow-sm">
                                <i class="fas <?= $icon_class ?>"></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-slate-700 truncate">
                                    <?= htmlspecialchars($log['nama_nasabah']) ?>
                                </p>

                                <div class="flex flex-wrap items-center gap-1 mt-0.5 text-[10px]">
                                    <span class="px-1.5 py-0.5 bg-slate-100 text-slate-600 font-bold rounded text-[9px]">
                                        <?= !empty($log['kelas']) ? htmlspecialchars($log['kelas'] . ' ' . $log['nama_jurusan']) : 'Umum' ?>
                                    </span>

                                    <span class="text-slate-300">•</span>

                                    <span class="text-slate-400 font-medium flex items-center gap-0.5" title="Jam Transaksi: <?= $exact_time ?>">
                                        <i class="far fa-clock text-[9px] text-slate-400"></i>
                                        <?= timeAgoPrecision($log['tanggal_transaksi']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-xs font-black tracking-tight <?= $text_amount ?>">
                                    <?= $prefix ?>Rp<?= number_format($log['jumlah'], 0, ',', '.') ?>
                                </span>

                                <span class="block text-[8px] font-black uppercase tracking-wider mt-0.5 <?= $log['status_approval'] == 'approved' ? 'text-emerald-500' : ($log['status_approval'] == 'pending' ? 'text-amber-500' : 'text-rose-500') ?>">
                                    <i class="fas <?= $log['status_approval'] == 'approved' ? 'fa-check-circle' : ($log['status_approval'] == 'pending' ? 'fa-clock' : 'fa-times-circle') ?> text-[7px]"></i>
                                    <?= htmlspecialchars($log['status_approval']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($recent_logs)): ?>
                        <div class="text-center py-8 flex flex-col items-center justify-center gap-1.5">
                            <i class="fas fa-history text-slate-300 text-lg animate-pulse"></i>
                            <p class="text-xs text-slate-400 font-medium">Belum ada aktivitas mutasi live hari ini.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <a href="?page=laporan-keuangan" class="mt-4 w-full py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200/60 text-slate-500 hover:text-slate-700 text-center text-[10px] font-black rounded-lg transition-all uppercase tracking-wider flex items-center justify-center gap-1 shadow-sm">
                    <i class="fas fa-file-alt text-[11px]"></i>
                    Lihat Laporan Keuangan
                </a>
            </div>

        </div>
    </div>

    <!-- LOG AKTIVITAS -->
    <div class="lg:col-span-3 bg-white p-5 rounded-[1rem] border border-slate-100 shadow-sm flex flex-col">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
            <div>
                <h5 class="text-sm font-black text-slate-800 tracking-tight flex items-center gap-2">
                    <i class="fas fa-history text-rose-500"></i>
                    Log Aktivitas Terbaru
                </h5>
                <p class="text-[11px] text-slate-400">Memantau 3 tindakan sistem terakhir dengan rincian parameter jaringan, log aktivitas, dan data user agent asli</p>
            </div>
            <span class="w-fit text-[9px] font-black bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full uppercase tracking-wider shrink-0">
                Live Monitor
            </span>
        </div>

        <div class="overflow-x-auto -mx-5 sm:mx-0">
            <div class="inline-block min-w-full align-middle px-5 sm:px-0">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead>
                        <tr class="bg-slate-50/70 text-[10px] font-black text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-3 w-10 text-center rounded-l-lg">No.</th>
                            <th class="py-3 px-4">Informasi Pelaku</th>
                            <th class="py-3 px-4">Aktivitas / Tindakan</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4">User Agent (Asli)</th>
                            <th class="py-3 px-4 rounded-r-lg text-right">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-xs">
                        <?php
                        $no = 1;
                        foreach ($activity_logs as $log):
                            // 1. LOGIKA STRUKTUR NAMA DAN ROLE PELAKU
                            $is_nasabah = (strtolower($log['role_pelaku']) == 'nasabah');
                            $nama_pelaku = $is_nasabah ? $log['nama_nasabah'] : $log['nama_operator'];
                            $nama_pelaku = $nama_pelaku ?? 'Sistem / Anonim';

                            // 2. ENGINE DETEKSI OTOMATIS AKTIVITAS MENCURIGAKAN
                            $is_suspicious = false;
                            $suspicious_keywords = ['gagal', 'delete', 'hapus', 'salah', 'unauthorized', 'banned', 'suspend', 'paksa', 'error'];
                            foreach ($suspicious_keywords as $keyword) {
                                if (stripos($log['aktivitas'], $keyword) !== false) {
                                    $is_suspicious = true;
                                    break;
                                }
                            }

                            // Set background baris berdasarkan tingkat keamanan log
                            $row_bg = $is_suspicious ? 'bg-rose-50/40 hover:bg-rose-50/70 transition-colors' : 'hover:bg-slate-50/50 transition-colors';
                        ?>
                            <tr class="<?= $row_bg ?>">
                                <td class="py-4 px-3 text-center font-bold text-slate-400 font-mono">
                                    <?= $no++; ?>.
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-800">
                                        <?= htmlspecialchars($nama_pelaku) ?>
                                        <?php if ($is_suspicious): ?>
                                            <span class="ml-1 px-1.5 py-0.2 bg-rose-500 text-white text-[8px] font-black rounded uppercase tracking-wider animate-pulse inline-block align-middle">
                                                <i class="fas fa-exclamation-triangle text-[7px]"></i> Alert
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-[10px] text-slate-500 mt-0.5 flex items-center flex-wrap gap-x-2 gap-y-0.5">
                                        <?php if ($is_nasabah): ?>
                                            <span><i class="fas fa-graduation-cap text-[10px] text-amber-500"></i></span>
                                            <span>NISN: <b><?= htmlspecialchars($log['nisn'] ?? '-') ?></b></span>
                                            <span class="text-slate-300">|</span>
                                            <span>Kelas: <b><?= htmlspecialchars($log['kelas'] ?? '-') ?></b></span>
                                            <span class="text-slate-300">|</span>
                                            <span>Jurusan: <b class="text-[#1258ab]"><?= htmlspecialchars($log['nama_jurusan'] ?? 'Umum') ?></b></span>
                                        <?php else: ?>
                                            <span><i class="fas fa-user-shield text-[10px] text-blue-500"></i></span>
                                            <span>Hak Akses: <b class="uppercase text-blue-600"><?= htmlspecialchars($log['role_pelaku'] ?? 'Sistem') ?></b></span>
                                            <span class="text-slate-300">|</span>
                                            <span>ID Log: <b>#<?= $log['id_log'] ?></b></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="py-4 px-4">
                                    <p class="<?= $is_suspicious ? 'text-rose-600 font-semibold' : 'text-slate-600 font-medium' ?> min-w-[180px] max-w-[280px] break-words line-clamp-2" title="<?= htmlspecialchars($log['aktivitas']) ?>">
                                        <?= htmlspecialchars($log['aktivitas']) ?>
                                    </p>
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="font-mono font-bold text-slate-600 bg-slate-50 border border-slate-100 px-2 py-1 rounded text-[11px] flex items-center gap-1 w-fit">
                                        <i class="fas fa-network-wired text-[9px] text-slate-400"></i>
                                        <?= htmlspecialchars($log['ip_address'] ?? '0.0.0.0') ?>
                                    </span>
                                </td>

                                <td class="py-4 px-4">
                                    <p class="text-slate-500 font-mono text-[10px] max-w-[250px] break-all line-clamp-2 leading-relaxed" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                        <?= htmlspecialchars($log['user_agent'] ?? 'Tidak terekam') ?>
                                    </p>
                                </td>

                                <td class="py-4 px-4 text-right whitespace-nowrap font-mono text-slate-600 text-[11px]">
                                    <div class="font-bold">
                                        <?= date('Y-m-d', strtotime($log['timestamp'])) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        <?= date('H:i:s', strtotime($log['timestamp'])) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($activity_logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-8">
                                    <p class="text-xs text-slate-400">Belum ada aktivitas yang tercatat saat ini.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <a href="?page=log-aktivitas" class="mt-4 w-full py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200/60 text-slate-500 hover:text-slate-700 text-center text-[10px] font-black rounded-lg transition-all uppercase tracking-wider flex items-center justify-center gap-1 shadow-sm shrink-0">
            <i class="fas fa-file-alt text-[11px]"></i>
            Lihat Log Aktivitas
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const canvas = document.getElementById('cashflowChart');
        const ctx = canvas.getContext('2d');

        // Suntik data PHP ke Javascript JSON
        const labelsData = <?= json_encode($chart_labels) ?>;
        const dataSetor = <?= json_encode($chart_setor) ?>;
        const dataTarik = <?= json_encode($chart_tarik) ?>;
        const dataSaldo = <?= json_encode($chart_saldo) ?>;

        // Efek Gradien Transparan Memudar di Bawah Garis
        // Gradien Setoran (Hijau)
        const gradientSetor = ctx.createLinearGradient(0, 0, 0, 280);
        gradientSetor.addColorStop(0, 'rgba(16, 185, 129, 0.28)');
        gradientSetor.addColorStop(1, 'rgba(16, 185, 129, 0.00)');

        // Gradien Penarikan (Amber)
        const gradientTarik = ctx.createLinearGradient(0, 0, 0, 280);
        gradientTarik.addColorStop(0, 'rgba(245, 158, 11, 0.25)');
        gradientTarik.addColorStop(1, 'rgba(245, 158, 11, 0.00)');

        // Gradien Akumulasi Saldo (Biru)
        const gradientSaldo = ctx.createLinearGradient(0, 0, 0, 280);
        gradientSaldo.addColorStop(0, 'rgba(18, 87, 170, 0.30)');
        gradientSaldo.addColorStop(1, 'rgba(18, 87, 170, 0.00)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labelsData,
                datasets: [{
                        label: 'Kas Masuk / Setoran (Rp)',
                        data: dataSetor,
                        borderColor: '#10b981', // HIJAU EMERALD SOLID
                        backgroundColor: gradientSetor,
                        borderWidth: 3,
                        tension: 0.38,
                        fill: true,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#10b981',
                        pointHoverBorderColor: '#ffffff',
                    },
                    {
                        label: 'Kas Keluar / Penarikan (Rp)',
                        data: dataTarik,
                        borderColor: '#f59e0b', // AMBER SOLID
                        backgroundColor: gradientTarik,
                        borderWidth: 3,
                        tension: 0.38,
                        fill: true,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#f59e0b',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#f59e0b',
                        pointHoverBorderColor: '#ffffff',
                    },
                    {
                        label: 'Total Saldo Mengendap (Rp)',
                        data: dataSaldo,
                        borderColor: '#1257aa', // BIRU REQUEST SOLID
                        backgroundColor: gradientSaldo,
                        borderWidth: 4, // Paling tebal sebagai indikator akumulasi utama
                        tension: 0.38,
                        fill: true,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#1257aa',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: '#1257aa',
                        pointHoverBorderColor: '#ffffff',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                // Hover Mode Index: Menyorot ketiga garis sekaligus secara vertikal
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12,
                            usePointStyle: true, // Bentuk legenda lingkaran cantik
                            pointStyle: 'circle',
                            font: {
                                size: 11,
                                weight: 'bold'
                            },
                            color: '#475569'
                        }
                    },
                    tooltip: {
                        padding: 12,
                        backgroundColor: 'rgba(15, 23, 42, 0.95)', // Pop-up hitam elegan transparan
                        titleFont: {
                            size: 12,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        },
                        cornerRadius: 8,
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                let label = ' ' + context.dataset.label.split(' (')[0] + ': ';
                                return label + new Intl.NumberFormat('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    maximumFractionDigits: 0
                                }).format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 11,
                                weight: 'bold'
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: '#f1f5f9'
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 10,
                                weight: '500'
                            },
                            padding: 8
                        },
                        callback: function(value) {
                            if (value >= 1000000) return 'Rp ' + (value / 1000000) + ' Jt';
                            if (value >= 1000) return 'Rp ' + (value / 1000) + ' Rb';
                            return 'Rp ' + value;
                        }
                    }
                }
            }
        });
    });
</script>