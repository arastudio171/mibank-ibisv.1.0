<?php

/**
 * FILE: main.php
 * DESKRIPSI: Router utama dengan sistem White-listing - Edisi Keamanan Diperketat
 */

// 🔒 PENGUATAN KEAMANAN 1: Proteksi Session & Cookie Secure
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();
require_once 'auth/database.php';

// 🔒 PENGUATAN KEAMANAN 2: Injeksi HTTP Security Headers 
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: upgrade-insecure-requests;");

// 🔒 PENGUATAN KEAMANAN 3: Rotasi Session ID untuk Mencegah Session Hijacking
if (!isset($_SESSION['CREATED_AT'])) {
    $_SESSION['CREATED_AT'] = time();
} else if (time() - $_SESSION['CREATED_AT'] > 1800) { // Rotasi setiap 30 menit
    session_regenerate_id(true);
    $_SESSION['CREATED_AT'] = time();
}

// 1. Proteksi Sesi Utama
if (!isset($_SESSION['role'])) {
    header("Location: auth/auth-login.php?msg=Akses ditolak.");
    exit();
}

// 🔒 PENGUATAN KEAMANAN 4: Sanitasi Ketat Terhadap URL Input (Anti LFI / Directory Traversal)
$page = isset($_GET['page']) ? preg_replace('/[^a-zA-Z0-9-]/', '', $_GET['page']) : 'main';
$role = $_SESSION['role']; // admin, operator, nasabah
$nama = $_SESSION['nama'] ?? 'Pengguna';

// 2. Definisi Rute (White-listing) - BERSIH DARI SUPERVISOR / AUDITOR
$routes = [
    'admin' => [
        'main'                  => 'views/main/superadmin.php',
        'log-aktivitas'         => 'views/superadmin/log-aktivitas.php',
        'laporan-keuangan'      => 'views/superadmin/transaksi-nasabah.php',
        'approval'              => 'views/superadmin/approval-transaksi.php',
        'nasabah'               => 'views/superadmin/daftar-nasabah.php',
        'edit-nasabah'          => 'views/superadmin/nasabah/edit-nasabah.php',
        'tambah-nasabah'        => 'views/superadmin/nasabah/tambah-nasabah.php',
        'petugas'               => 'views/superadmin/daftar-petugas.php',
        'edit-petugas'          => 'views/superadmin/petugas/edit-petugas.php',
        'tambah-petugas'        => 'views/superadmin/petugas/tambah-petugas.php',
        'jurusan'               => 'views/superadmin/daftar-jurusan.php',
        'tambah-jurusan'        => 'views/superadmin/jurusan/tambah-jurusan.php',
        'edit-jurusan'          => 'views/superadmin/jurusan/edit-jurusan.php',
        'pengaturan'            => 'views/superadmin/pengaturan-sistem.php',
        'backup'                => 'views/superadmin/backup-database.php',
    ],
    'operator' => [
        'main'                  => 'views/main/operator.php',
        'setor-tunai'           => 'views/operator/setor-tunai.php',
        'tarik-tunai'           => 'views/operator/tarik-tunai.php',
        'pelayanan-antrian'     => 'views/operator/pelayanan-antrian.php',
        'laporan-keuangan'      => 'views/operator/transaksi-nasabah.php',
        'penyetoran-dana'       => 'views/operator/penyetoran-dana.php',
        'penarikan-dana'        => 'views/operator/penarikan-dana.php',
        'infaq-nasabah'         => 'views/operator/infaq-nasabah.php',
        'nasabah'               => 'views/operator/daftar-nasabah.php',
        'kas-teller'            => 'views/operator/kas-teller.php',
        'pendapatan'            => 'views/operator/riwayat-pendapatan.php'
    ],
    'nasabah' => [
        'main'                  => 'views/main/nasabah.php',
        'riwayat'               => 'views/nasabah/riwayat.php',
        'grafik'                => 'views/nasabah/grafik.php',
        'pengaturan'            => 'views/nasabah/pengaturan.php',
        'ambil-antrean'         => 'views/nasabah/ambil-antrean.php',
        'transfer-dana'         => 'views/nasabah/transfer-dana.php',
        'qr-payment'            => 'views/nasabah/qr-payment.php',
        'infaq-dana'            => 'views/nasabah/infaq-dana.php',
        'infaq'                 => 'views/nasabah/riwayat-infaq.php',
        'edit-profil'           => 'views/nasabah/profile.php',
        'transaksi-nasabah'     => 'views/nasabah/transaksi-nasabah.php'
    ]
];

// 3. Penentuan Title Halaman
$page_titles = [
    'main' => 'Dashboard'
];
$title = "Mini Bank - " . ($page_titles[$page] ?? 'Sistem');

// =========================================================================
// 3. LOGIKA BACKEND A: AMBIL NOTIFIKASI TRANSAKSI TERBARU (KHUSUS NASABAH)
// =========================================================================
$id_nasabah_aktif = $_SESSION['id_nasabah'] ?? null;
$notifikasi_list = [];
$jumlah_unread = 0;

if ($id_nasabah_aktif && $role === 'nasabah') {
    try {
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tbl_notifikasi WHERE id_nasabah = ? AND is_read = 0");
        $stmt_count->execute([$id_nasabah_aktif]);
        $jumlah_unread = (int) $stmt_count->fetchColumn();

        $stmt_list = $pdo->prepare("SELECT id_notifikasi, judul, pesan, is_read, created_at FROM tbl_notifikasi WHERE id_nasabah = ? ORDER BY created_at DESC LIMIT 5");
        $stmt_list->execute([$id_nasabah_aktif]);
        $notifikasi_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $jumlah_unread = 0;
        $notifikasi_list = [];
    }
}

// =========================================================================
// 4. LOGIKA BACKEND B: AMBIL DATA CONFIG/PENGATURAN KEUANGAN & GLOBAL DB
// =========================================================================
$app_config = [
    'nama_aplikasi'                => 'IBIS',
    'subjudul'                     => 'Internet Banking Sekolah',
    'tagline_1'                    => 'Cerdas Finansial,',
    'tagline_2'                    => 'Hebat di Masa Depan.',
    'versi_aplikasi'               => '3.0',
    'developed_by'                 => 'PTIK SMK PGRI 4 Bandar Lampung',
    'nama_sekolah'                 => 'SMK PGRI 4 Bandar Lampung',
    'minimal_penarikan'            => 10000.00,
    'minimal_saldo_mengendap'      => 15000.00,
    'biaya_admin_default'          => 2500.00,
    'biaya_transfer_default'       => 1500.00,
    'format_nomor_transaksi'       => 'TRX/[YYYY]/[MM]/[ID]',
    'jam_operasional'              => 'Senin - Jumat, 07:30 - 15:00 WIB',
    'limit_transfer_harian'        => 50000.00,
    'logo_sekolah'                 => null
];

try {
    $stmt_config = $pdo->query("SELECT * FROM tbl_pengaturan LIMIT 1");
    $db_config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    if ($db_config) {
        $app_config = array_merge($app_config, $db_config);
    }
} catch (PDOException $e) {
    error_log("Gagal memuat pengaturan aplikasi: " . $e->getMessage());
}

// =========================================================================
// ⚡ INJEKSI KEAMANAN: BLOKIR KETIK MANUAL DI URL BAR (ANTI-URL TAMPERING) ⚡
// =========================================================================
$metode_akses = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin';

if ($page !== 'main' && $metode_akses === 'none') {
    echo "<script>
        alert('Akses ditolak. Silakan gunakan menu navigasi yang tersedia untuk mengakses halaman ini.');
        window.location.href = 'main.php?page=main';
        </script>";
    exit();
}

include 'views/layout/header.php';
?>

<div id="sidebar-overlay" onclick="toggleMobileMenu()" class="fixed inset-0 bg-black/50 z-40 backdrop-blur-sm hidden transition-opacity lg:hidden"></div>

<div class="flex h-screen overflow-hidden">
    <aside id="sidebar" class="no-print w-72 sidebar-gradient text-slate-300 flex flex-col shrink-0 shadow-xl relative">

        <button onclick="toggleSidebar()"
            class="hidden lg:flex absolute -right-3 top-10 bg-amber-500 text-white w-6 h-6 rounded-full items-center justify-center shadow-lg transition-all z-20">
            <i id="toggle-icon" class="fas fa-chevron-left text-[10px]"></i>
        </button>

        <div class="p-6 flex-1 overflow-y-auto custom-scrollbar">
            <div class="header-container flex items-center gap-3 mb-10 overflow-hidden transition-all">
                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center shadow-lg shrink-0">
                    <i class="fas fa-user text-yellow-600 text-lg"></i>
                </div>
                <div class="sidebar-header-text whitespace-nowrap">
                    <h1 class="text-xl font-800 tracking-tight leading-none text-white">
                        <?php
                        $nama_app = (!empty($app_config['nama_aplikasi'])) ? trim($app_config['nama_aplikasi']) : 'IBIS';
                        $versi_app = (!empty($app_config['versi_aplikasi'])) ? trim($app_config['versi_aplikasi']) : '3.0';
                        ?>

                        <b><?= htmlspecialchars($nama_app) ?></b>

                        <span class="text-xs font-normal text-blue-200/70 ml-1">
                            v<?= htmlspecialchars($versi_app) ?>
                        </span>
                    </h1>
                    <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-blue-200/60">
                        Level Akses: <?= htmlspecialchars($role) ?>
                    </p>
                </div>
            </div>

            <?php include_once 'views/layout/sidebar.php'; ?>
        </div>

        <div class="mt-auto p-6 border-t border-white/5">
            <div class="user-card-container flex items-center gap-3 mb-6 p-2 bg-black/20 rounded-xl overflow-hidden transition-all">
                <?php
                $avatar_bg = 'bg-blue-500/20 text-blue-400 border-blue-500/30';
                if ($role == 'admin') $avatar_bg = 'bg-amber-500/20 text-amber-400 border-amber-500/30';
                if ($role == 'nasabah') $avatar_bg = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
                ?>
                <div class="w-10 h-10 rounded-xl bg-yellow-500 <?= $avatar_bg ?> shrink-0 flex items-center justify-center font-bold text-white shadow-inner">
                    <?= strtoupper(substr($nama, 0, 1)) ?>
                </div>
                <div class="user-info-text truncate">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($nama) ?></p>
                    <p class="text-[10px] text-blue-300/70 italic"><?= htmlspecialchars($role) ?></p>
                </div>
            </div>
            <a href="auth/auth-logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');"
                class="nav-item w-full flex items-center justify-center gap-2 py-3 rounded-xl text-xs font-extrabold bg-amber-500 text-white hover:opacity-95 transition-all">
                <i class="fas fa-power-off"></i> <span class="logout-text">KELUAR SISTEM</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-8 shrink-0">
            <div class="flex items-center gap-4">
                <button onclick="toggleMobileMenu()"
                    class="lg:hidden w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h2 id="section-title" class="text-xl font-800 text-slate-800 capitalize tracking-tight">
                        <?= $page_titles[$page] ?? 'Pusat Kendali' ?>
                    </h2>
                    <p class="text-[10px] lg:text-xs text-slate-400 font-medium">
                        Kontrol penuh <?= htmlspecialchars($app_config['subjudul']) ?>.
                        <b class="text-emerald-500">
                            <?= htmlspecialchars($app_config['tagline_1']) ?> <?= htmlspecialchars($app_config['tagline_2']) ?>
                        </b>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 lg:gap-4">
                <div class="hidden md:flex items-center gap-2.5 px-3.5 py-2 bg-blue-50/70 border border-blue-100 text-blue-600 rounded-xl select-none">
                    <i class="fas fa-calendar-alt text-xs opacity-80"></i>
                    <span id="current-date-time" class="text-xs font-bold normal-case tracking-wider tabular-nums">Memuat Waktu...</span>
                </div>

                <div class="relative inline-block text-left">
                    <button id="btn-notif" type="button" aria-label="Lihat Notifikasi" class="w-10 h-10 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-center text-blue-500 cursor-pointer hover:bg-blue-100 focus:outline-none transition-all">
                        <i class="fas fa-bell text-[#1566c7]"></i>

                        <?php if ($jumlah_unread > 0): ?>
                            <span class="absolute top-0 right-0 flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500 border-2 border-white"></span>
                            </span>
                        <?php endif; ?>
                    </button>

                    <div id="dropdown-notif" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden transform origin-top-right transition-all">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                            <span class="font-bold text-slate-700 text-xs uppercase tracking-wider">Notifikasi Transaksi</span>
                            <?php if ($jumlah_unread > 0): ?>
                                <span class="bg-rose-100 text-rose-600 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $jumlah_unread ?> Baru</span>
                            <?php endif; ?>
                        </div>

                        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                            <?php if (!empty($notifikasi_list)): ?>
                                <?php foreach ($notifikasi_list as $notif):
                                    if (!function_exists('formatWaktuNotif')) {
                                        function formatWaktuNotif($timestamp)
                                        {
                                            $waktu_buat = strtotime($timestamp);
                                            $selisih = time() - $waktu_buat;
                                            if ($selisih < 60) return 'Baru saja';
                                            $hitung_menit = round($selisih / 60);
                                            if ($hitung_menit < 60) return $hitung_menit . ' menit yang lalu';
                                            $hitung_jam = round($selisih / 3600);
                                            if ($hitung_jam < 24) return $hitung_jam . ' jam yang lalu';
                                            $hitung_hari = round($selisih / 86400);
                                            if ($hitung_hari == 1) return 'Kemarin';
                                            elseif ($hitung_hari < 7) return $hitung_hari . ' hari yang lalu';
                                            return date('d M', $waktu_buat);
                                        }
                                    }

                                    $judul_lower = strtolower($notif['judul']);
                                    $icon_class = 'fas fa-bell';
                                    $icon_bg = 'bg-slate-100 text-slate-500';

                                    if (strpos($judul_lower, 'transfer') !== false || strpos($judul_lower, 'kirim') !== false) {
                                        $icon_class = 'fas fa-paper-plane';
                                        $icon_bg = 'bg-blue-50 text-blue-600 border border-blue-100/50';
                                    } elseif (strpos($judul_lower, 'terima') !== false || strpos($judul_lower, 'masuk') !== false || strpos($judul_lower, 'topup') !== false) {
                                        $icon_class = 'fas fa-wallet';
                                        $icon_bg = 'bg-emerald-50 text-emerald-600 border border-emerald-100/50';
                                    } elseif (strpos($judul_lower, 'gagal') !== false || strpos($judul_lower, 'batal') !== false) {
                                        $icon_class = 'fas fa-exclamation-circle';
                                        $icon_bg = 'bg-rose-50 text-rose-600 border border-rose-100/50';
                                    } elseif ($notif['is_read'] == 0) {
                                        $icon_bg = 'bg-indigo-50 text-indigo-600 border border-indigo-100/50';
                                    }
                                ?>
                                    <div class="flex items-start gap-3 p-3.5 hover:bg-slate-50/80 transition-all duration-200 relative group <?= $notif['is_read'] == 0 ? 'bg-blue-50/20' : '' ?>">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-xs <?= $icon_bg ?>">
                                            <i class="<?= $icon_class ?> text-xs"></i>
                                        </div>
                                        <div class="flex-1 min-w-0 space-y-0.5">
                                            <div class="flex justify-between items-baseline gap-2">
                                                <h4 class="font-bold text-slate-800 text-[11px] sm:text-[12.5px] tracking-wide truncate group-hover:text-[#1566c7] transition-colors leading-tight">
                                                    <?= htmlspecialchars($notif['judul']) ?>
                                                </h4>
                                            </div>
                                            <p class="text-slate-500 text-[11px] leading-relaxed font-sans pr-1 break-words">
                                                <?= htmlspecialchars($notif['pesan']) ?>
                                            </p>
                                            <p class="text-slate-400 text-[11px] leading-relaxed font-sans pr-1 break-words">
                                                <i class="fas fa-clock mr-1"></i> <?= formatWaktuNotif($notif['created_at']) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-slate-400">
                                    <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-2 border border-slate-100/80">
                                        <i class="fas fa-bell-slash text-slate-300 text-base"></i>
                                    </div>
                                    <p class="text-[11px] font-semibold text-slate-400 tracking-wide">Belum ada riwayat notifikasi transaksi.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="bg-slate-50 border-t border-slate-100 text-center">
                            <a href="main.php?page=transaksi-nasabah" class="block py-2 text-[11px] text-blue-600 font-bold hover:text-blue-700 hover:bg-slate-100 transition-colors">
                                Lihat Semua Notifikasi
                            </a>
                        </div>
                    </div>
                </div>

                <button id="btn-fullscreen" type="button" aria-label="Layar Penuh" class="w-10 h-10 bg-emerald-50 border border-slate-100 rounded-xl flex items-center justify-center text-emerald-600 cursor-pointer hover:bg-slate-100 focus:outline-none transition-all">
                    <i id="fullscreen-icon" class="fas fa-expand text-emerald-600"></i>
                </button>

                <a href="auth/auth-logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');"
                    aria-label="Keluar Sistem" class="w-10 h-10 bg-amber-50 border border-amber-100 rounded-xl flex items-center justify-center text-amber-500 cursor-pointer hover:bg-amber-100 focus:outline-none transition-all">
                    <i class="fas fa-power-off text-amber-600"></i>
                </a>

            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar bg-[#d0ddeb]">
            <?php
            if (isset($routes[$role][$page])) {
                $file_to_include = $routes[$role][$page];
                if (file_exists($file_to_include)) {
                    include $file_to_include;
                } else {
                    include 'views/errors/404.html';
                }
            } else {
                include 'views/errors/403.html';
            }
            ?>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. ENGINE CLOCK JALAN (Format Hari, Tanggal + H:i:s)
        function jalankanWaktuRealtime() {
            const elemenWaktu = document.getElementById('current-date-time');
            if (!elemenWaktu) return;

            const sekarang = new Date();
            const opsiTanggal = {
                weekday: 'long',
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            };
            const tanggalString = sekarang.toLocaleDateString('id-ID', opsiTanggal);

            const jam = String(sekarang.getHours()).padStart(2, '0');
            const menit = String(sekarang.getMinutes()).padStart(2, '0');
            const detik = String(sekarang.getSeconds()).padStart(2, '0');

            elemenWaktu.textContent = `${tanggalString} • ${jam}:${menit}:${detik}`;
        }
        jalankanWaktuRealtime();
        setInterval(jalankanWaktuRealtime, 1000);

        // 2. INTERAKSI TOGGLE DROPDOWN NOTIFIKASI
        const btnNotif = document.getElementById('btn-notif');
        const dropdownNotif = document.getElementById('dropdown-notif');

        if (btnNotif && dropdownNotif) {
            btnNotif.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownNotif.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!dropdownNotif.contains(e.target) && !btnNotif.contains(e.target)) {
                    dropdownNotif.classList.add('hidden');
                }
            });
        }

        // 3. LOGIKA JAVASCRIPT: TOGGLE FULLSCREEN MODERN
        const btnFullscreen = document.getElementById('btn-fullscreen');
        const fsIcon = document.getElementById('fullscreen-icon');

        if (btnFullscreen && fsIcon) {
            btnFullscreen.addEventListener('click', () => {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen()
                        .then(() => {
                            fsIcon.classList.remove('fa-expand');
                            fsIcon.classList.add('fa-compress');
                        })
                        .catch(err => {
                            console.error(`Gagal mengaktifkan Fullscreen: ${err.message}`);
                        });
                } else {
                    document.exitFullscreen()
                        .then(() => {
                            fsIcon.classList.remove('fa-compress');
                            fsIcon.classList.add('fa-expand');
                        });
                }
            });
        }
    });
</script>

<?php include 'views/layout/footer.php'; ?>