<?php

/**
 * FILE: views/layout/sidebar.php
 * DESKRIPSI: Sidebar dengan fitur Dropdown (Collapse) menggunakan Alpine.js - Edisi Keamanan Diperketat
 */

// 🔒 PENGUATAN KEAMANAN 1: Blokir Akses Langsung ke File Komponen/Layout
if (basename($_SERVER['SCRIPT_FILENAME']) === 'sidebar.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Akses langsung ditolak. File ini merupakan komponen sistem.");
}

// 🔒 PENGUATAN KEAMANAN 2: Sanitasi Ketat URL Page Matcher
$current_page = isset($_GET['page']) ? preg_replace('/[^a-zA-Z0-9-]/', '', $_GET['page']) : 'main';
$user_role    = $_SESSION['role'] ?? '';
$user_nama    = $_SESSION['nama'] ?? 'Pengguna';

/**
 * Fungsi pembantu untuk menentukan class aktif pada menu
 */
function isActive($page, $current_page)
{
    return $page === $current_page
        ? 'bg-white/15 text-white shadow-xs'
        : 'text-blue-100/70 hover:bg-white/5 hover:text-white';
}

/**
 * Merender item menu tunggal
 */
function render_nav_item($page, $icon, $text, $iconColor = '')
{
    global $current_page;
    $class = isActive($page, $current_page);
    // 🔒 PENGUATAN KEAMANAN 3: Proteksi Output Konten Navigasi dengan htmlspecialchars
    $safe_page = htmlspecialchars($page, ENT_QUOTES, 'UTF-8');
    $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    echo "
    <a href='main.php?page=$safe_page' class='w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all $class'>
        <i class='$icon w-5 $iconColor'></i> <span>$safe_text</span>
    </a>";
}

/**
 * Merender menu dropdown (Collapse)
 */
function render_collapse_menu($id, $icon, $text, $items)
{
    global $current_page;

    // Cek apakah salah satu anak menu sedang aktif
    $is_child_active = false;
    foreach ($items as $item) {
        if ($item['page'] === $current_page) {
            $is_child_active = true;
            break;
        }
    }

    // Inisialisasi state Alpine.js (terbuka jika ada anak yang aktif)
    $isOpen = $is_child_active ? 'true' : 'false';
    $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    echo "
    <div x-data='{ open: $isOpen }' class='w-full'>
        <button @click='open = !open' 
            class='w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-semibold transition-all text-blue-100/70 hover:bg-white/5 hover:text-white'>
            <div class='flex items-center gap-3'>
                <i class='$icon w-5 text-indigo-400'></i>
                <span>$safe_text</span>
            </div>
            <i class='fas fa-chevron-down text-[10px] transition-transform duration-300' :class='open ? \"rotate-180\" : \"\"'></i>
        </button>
        
        <div x-show='open' x-cloak x-collapse class='mt-1 space-y-1 ml-4 border-l border-white/10 pl-2'>
    ";

    foreach ($items as $item) {
        render_nav_item($item['page'], 'fas fa-circle text-[6px]', $item['text'], 'text-blue-400/50');
    }

    echo "</div></div>";
}

/**
 * Merender header section
 */
function render_nav_section($title, $marginTop = 'mt-8')
{
    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo "<p class='text-[10px] font-bold text-blue-300/50 uppercase tracking-widest mb-4 px-3 $marginTop'>$safe_title</p>";
}
?>

<?php
render_nav_section('Utama');
render_nav_item('main', 'fas fa-layer-group', 'Dashboard', 'text-blue-300');
?>

<?php if ($user_role === 'admin'): ?>
    <?php
    render_nav_section('Manajemen Data');
    render_nav_item('petugas', 'fas fa-user-tie', 'Petugas Teller', 'text-blue-400');
    render_nav_item('nasabah', 'fas fa-graduation-cap', 'Daftar Nasabah', 'text-violet-400');
    render_nav_item('jurusan', 'fas fa-university', 'Daftar Jurusan', 'text-amber-400');

    render_nav_section('Pelaporan & Verifikasi');
    render_nav_item('laporan-keuangan', 'fas fa-chart-column', 'Laporan Keuangan', 'text-rose-400');
    render_nav_item('approval', 'fas fa-history', 'Riwayat Transaksi Kelas', 'text-emerald-400');
    render_nav_item('loket', 'fas fa-store', 'Daftar Loket', 'text-violet-400');

    render_nav_section('Sistem & Infrastruktur');
    render_nav_item('pengaturan', 'fas fa-user-shield', 'Pengaturan Sistem', 'text-indigo-400');
    render_nav_item('backup', 'fas fa-server', 'Cadangan Data', 'text-cyan-400');
    ?>

<?php elseif ($user_role === 'operator'): ?>
    <?php
    render_nav_section('Operasional Teller');
    render_nav_item('setor-tunai', 'fas fa-arrow-down-long', 'Setor Tunai', 'text-emerald-400');
    render_nav_item('tarik-tunai', 'fas fa-arrow-up-long', 'Tarik Tunai', 'text-amber-400');

    render_nav_section('Data & Pelaporan');
    render_nav_item('nasabah', 'fas fa-graduation-cap', 'Daftar Nasabah', 'text-violet-400');
    render_nav_item('laporan-keuangan', 'fas fa-chart-column', 'Laporan Transaksi', 'text-blue-400');

    render_nav_section('Layanan & Kas');
    render_nav_item('pelayanan-antrian', 'fas fa-headset', 'Layanan Nasabah', 'text-teal-400');
    render_nav_item('kas-teller', 'fas fa-cash-register', 'Jurnal Kas', 'text-indigo-400');
    ?>

<?php elseif ($user_role === 'nasabah'): ?>
    <?php
    render_nav_section('Informasi Pribadi');
    render_nav_item('grafik', 'fas fa-arrow-trend-up', 'Performa Keuangan', 'text-emerald-400');
    render_nav_item('pengaturan', 'fas fa-user-shield', 'Preferensi Akun', 'text-indigo-400');
    ?>
<?php endif; ?>