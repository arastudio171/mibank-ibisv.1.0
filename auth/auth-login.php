<?php

/**
 * FILE: auth-login.php
 * DESKRIPSI: Halaman Login Utama dengan Sidebar & Footer Dinamis Terintegrasi Database.
 */

include_once 'layouts/auth-header.php';

// 1. OTOMATISASI KONEKSI: Jika $pdo belum aktif, panggil database.php
if (!isset($pdo)) {
    if (file_exists('database.php')) {
        require_once 'database.php';
    } elseif (file_exists('../database.php')) {
        require_once '../database.php';
    }
}

$setting = [];

try {
    // 2. VALIDASI AMAN: Pastikan variabel $pdo ada dan siap digunakan sebelum query
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt_setting = $pdo->query("SELECT * FROM tbl_pengaturan LIMIT 1");
        if ($stmt_setting) {
            $setting = $stmt_setting->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    } else {
        throw new Exception("Variabel koneksi database (\$pdo) tidak ditemukan. Pastikan file database.php sudah terhubung.");
    }
} catch (Throwable $e) {
    // Mengamankan sistem jika terjadi error, simpan log internal
    error_log("Gagal memuat pengaturan aplikasi: " . $e->getMessage());
    $setting = []; // Kosongkan agar sistem menggunakan nilai default di bawah
}

// 3. Menentukan nilai default jika data di database kosong (null coalescing)
$nama_aplikasi   = $setting['nama_aplikasi'] ?? 'IBIS';
$subjudul        = $setting['subjudul'] ?? 'Internet Banking Sekolah';
$tagline_1       = $setting['tagline_1'] ?? 'Cerdas Finansial,';
$tagline_2       = $setting['tagline_2'] ?? 'Hebat di Masa Depan.';
$nama_sekolah    = $setting['nama_sekolah'] ?? 'SMK PGRI 4 Bandar Lampung';
$logo_sekolah    = !empty($setting['logo_sekolah']) ? $setting['logo_sekolah'] : null;

// Kolom tambahan baru dari database
$developed_by    = $setting['developed_by'] ?? 'PTIK 4 Bandar Lampung';
$versi_aplikasi  = $setting['versi_aplikasi'] ?? '1.0';
$whatsapp_admin  = $setting['whatsapp_admin'] ?? '0812xxxx1234';
$jam_operasional = $setting['jam_operasional'] ?? 'Senin - Sabtu, 08:00 - 16:00 WIB';
?>

<div class="main-container w-full max-w-6xl bg-white rounded-none md:rounded-[1rem] overflow-hidden flex flex-col md:flex-row card-shadow min-h-[100vh] md:min-h-[700px] mt-0 md:mt-2">
    <!-- SIDEBAR: Informative Section (Biru) -->
    <div class="w-full md:w-5/12 lg:w-1/2 gradient-brand p-8 md:p-12 text-white flex flex-col justify-between relative overflow-hidden">

        <!-- Abstract background decorations -->
        <div class="absolute top-[-10%] right-[-10%] w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-[-5%] left-[-5%] w-48 h-48 bg-blue-400/20 rounded-full blur-2xl"></div>

        <div class="relative z-10">
            <!-- Brand Identity -->
            <div class="flex items-center gap-3 mb-10">
                <div class="w-12 h-12 glass-panel rounded-xl flex items-center justify-center shadow-lg">
                    <?php if ($logo_sekolah): ?>
                        <img src="../assets/img/<?= htmlspecialchars($logo_sekolah) ?>" alt="Logo" class="h-8 w-auto object-contain">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap text-white text-xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-2xl font-800 tracking-tight leading-none">
                        <b class="text-amber-300"><?= htmlspecialchars($nama_aplikasi); ?></b> v<?= htmlspecialchars($versi_aplikasi); ?>
                    </h1>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100/80">
                        <?= htmlspecialchars($subjudul) ?>
                    </p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="space-y-6">
                <h4 class="text-3xl lg:text-4xl font-medium leading-tight">
                    <?= htmlspecialchars($tagline_1); ?>,<br>
                    <span class="text-blue-200">
                        <?= htmlspecialchars($tagline_2); ?>
                    </span>
                </h4>

                <div class="bg-white/10 rounded-xl p-5 border border-white/10">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-blue-200 mb-3">
                        Petunjuk Penggunaan
                    </p>

                    <ul class="space-y-2">
                        <li class="flex items-start gap-3 text-xs">
                            <span
                                class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">
                                1
                            </span>
                            <span>
                                Buka aplikasi <b class="text-amber-300 font-semibold"><?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?></b> melalui halaman web anda.
                            </span>
                        </li>

                        <li class="flex items-start gap-3 text-xs">
                            <span
                                class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">
                                2
                            </span>
                            <span>
                                Masukkan <b class="text-amber-300 font-semibold">Username (NISN/NIP)</b> dan
                                <b class="text-amber-300 font-semibold">PIN 6 digit</b> anda.
                            </span>
                        </li>

                        <li class="flex items-start gap-3 text-xs">
                            <span
                                class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">
                                3
                            </span>
                            <span>
                                Klik tombol <b class="text-amber-300 font-semibold">MASUK SISTEM</b>
                                untuk mengakses aplikasi <b class="text-amber-300 font-semibold"><?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?></b>.
                            </span>
                        </li>

                        <li class="flex items-start gap-3 text-xs">
                            <span
                                class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">
                                4
                            </span>
                            <span>
                                Jika belum memiliki akun, pilih menu
                                <b class="text-amber-300 font-semibold">Buat Akun</b>
                                untuk melakukan registrasi dan mendapatkan akses ke aplikasi
                                <b class="text-amber-300 font-semibold"><?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?></b>.
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Feature Points -->
                <div class="grid grid-cols-2 gap-3 mt-6">
                    <div class="glass-panel p-3 rounded-xl flex items-center gap-3">
                        <i class="fas fa-qrcode text-blue-200"></i>
                        <span class="text-[11px] font-medium">Transaksi QR Cepat</span>
                    </div>

                    <div class="glass-panel p-3 rounded-xl flex items-center gap-3">
                        <i class="fas fa-chart-line text-blue-200"></i>
                        <span class="text-[11px] font-medium">Riwayat Real-time</span>
                    </div>

                    <div class="glass-panel p-3 rounded-xl flex items-center gap-3">
                        <i class="fas fa-piggy-bank text-blue-200"></i>
                        <span class="text-[11px] font-medium">Monitoring Tabungan</span>
                    </div>

                    <div class="glass-panel p-3 rounded-xl flex items-center gap-3">
                        <i class="fas fa-shield-alt text-blue-200"></i>
                        <span class="text-[11px] font-medium">Keamanan Data</span>
                    </div>
                </div>

                <!-- Help Link & Quick Contact -->
                <div class="glass-panel p-4 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-full bg-white text-blue-600 flex items-center justify-center">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div>
                            <p class="text-[11px] font-bold text-amber-300 uppercase tracking-tight">Butuh Bantuan?
                            </p>
                            <p class="text-[10px] text-white-600 font-medium">Hubungi Admin <?= htmlspecialchars($nama_sekolah) ?></p>
                        </div>
                    </div>
                    <a href="https://wa.me/<?= urlencode($whatsapp_admin) ?>" target="_blank"
                        class="w-10 h-10 rounded-lg bg-green-500 shadow-sm flex items-center justify-center text-white hover:bg-green-600 transition-all">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="relative z-10 pt-8 mt-6 border-t border-white/10">
            <div class="flex flex-wrap justify-between items-end gap-4">
                <div class="flex gap-6">
                    <div>
                        <p class="text-[10px] uppercase font-bold text-blue-200 mb-1">Jam Operasional</p>
                        <p class="text-xs font-bold opacity-95"><?= htmlspecialchars($jam_operasional) ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase font-bold text-blue-200 mb-1">Keamanan</p>
                        <p class="text-sm font-bold flex items-center gap-2">
                            <i class="fas fa-user-shield text-amber-300"></i> SSL 256-bit
                        </p>
                        <p class="text-[10px] opacity-70">Data Terenkripsi</p>
                    </div>
                </div>
                <!-- Trust Badges (Fictional for School Context) -->
                <div class="flex items-center gap-2 grayscale opacity-60 contrast-125">
                    <img src="https://placehold.co/40x20/ffffff/1e40af?text=LPS" alt="LPS" class="h-4">
                    <img src="https://placehold.co/40x20/ffffff/1e40af?text=OJK" alt="OJK" class="h-4">
                </div>
            </div>
        </div>
    </div>

    <!-- FORM SECTION: (Putih) -->
    <div class="w-full md:w-7/12 lg:w-1/2 p-8 md:p-16 lg:p-20 flex flex-col justify-center bg-white">
        <div class="max-w-sm mx-auto w-full">
            <div class="mb-8">
                <h3 class="text-3xl font-800 text-blue-800 mb-2">
                    <b class="text-blue-600">Akses</b>
                    <?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?>
                </h3>
                <p class="text-slate-500 font-medium text-sm leading-relaxed">
                    Silakan masuk untuk mengelola tabungan harian Anda.
                </p>

                <!-- Alert Section -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5"></i>
                        <div>
                            <p class="text-xs text-yellow-800 font-medium"><b>Peringatan Keamanan</b></p>
                            <p class="text-xs text-yellow-700 leading-relaxed">Pastikan Anda berada di jaringan
                                sekolah yang aman.</p>
                        </div>
                    </div>
                </div>
                <?php if (isset($_GET['msg'])): ?>
                    <?php
                    $msgType = $_GET['type'] ?? 'error';
                    $bgColor = ($msgType === 'success') ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200';
                    $textColor = ($msgType === 'success') ? 'text-emerald-800' : 'text-red-800';
                    $icon = ($msgType === 'success') ? 'fa-check-circle' : 'fas fa-user-clock';
                    $iconColor = ($msgType === 'success') ? 'text-emerald-600' : 'text-red-600';
                    $title = ($msgType === 'success') ? 'Berhasil' : 'Pemberitahuan';
                    ?>
                    <div class="<?php echo $bgColor; ?> border rounded-lg p-3 mt-4 mb-6">
                        <div class="flex items-start gap-3">
                            <i class="fas <?php echo $icon; ?> <?php echo $iconColor; ?> mt-0.5"></i>
                            <div>
                                <p class="text-xs <?php echo $textColor; ?> font-bold"><?php echo $title; ?></p>
                                <p class="text-xs <?php echo $textColor; ?> leading-relaxed">
                                    <?php echo htmlspecialchars($_GET['msg']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Simplified to single user login, multi-user handled in backend -->
            <form class="space-y-5" method="post" action="check-login.php">
                <div class="space-y-4">
                    <!-- ID Input -->
                    <div class="group">
                        <label id="labelId"
                            class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">NISN</label>
                        <div class="relative flex items-center input-focus-effect border-2 border-slate-100 rounded-xl bg-slate-50/50 transition-all">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" id="username" placeholder="Nomor Induk Siswa Nasional (NISN)" name="username" required class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Masukan NISN yang telah terdaftar.
                        </small>
                    </div>

                    <!-- PIN Input -->
                    <div class="group">
                        <div class="flex justify-between items-center mb-2 ml-1">
                            <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest">PIN
                                Transaksi</label>
                            <button type="button" onclick="openForgotPinModal()"
                                class="text-[10px] font-bold text-blue-600 bg-transparent border-none">Lupa
                                PIN?</button>
                        </div>
                        <div class="relative flex items-center input-focus-effect border-2 border-slate-100 rounded-xl bg-slate-50/50 transition-all">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-key"></i>
                            </div>
                            <input type="password" id="password" placeholder="••••••" name="password" required class="w-full pl-3 pr-12 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                            <button type="button" onclick="togglePass()" class="absolute right-4 text-slate-300 hover:text-slate-500">
                                <i id="eyeIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Masukan PIN transaksi 6 digit anda.
                        </small>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input type="checkbox" id="rememberMe" name="rememberMe"
                            class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded cursor-pointer">
                        <label for="rememberMe" class="ml-2 text-sm text-slate-600 cursor-pointer">Ingat saya di
                            perangkat ini</label>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submitBtn"
                    class="w-full py-4 bg-[#1257aa] text-white font-bold rounded-xl hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-3">
                    <!-- <i class="fas fa-arrow-right-long text-sm"></i> -->
                    <span>MASUK SISTEM</span>
                </button>
                <div class="text-center">
                    <p class="text-sm text-slate-500 font-medium">
                        Belum memiliki Akun?
                        <a href="auth-register.php" class="text-[#1257aa] font-bold decoration-2">
                            Buat Akun <b class="text-emerald-600"><?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?></b>
                        </a>
                    </p>
                </div>

                <!-- Copyright -->
                <div class="text-center mt-8 pt-6 border-t border-slate-100">
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">
                        Copyright &copy; <?= date('Y'); ?> <b><?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?></b><br>
                        Dikembangkan oleh <b class="text-rose-800"><a href="http://www.ptik4bl.smkpgri4bl.web.id" target="_blank"> <?= htmlspecialchars($developed_by); ?> </a></b>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 md:left-auto md:right-6 md:translate-x-0 z-[60] pointer-events-none opacity-0 transition-all duration-500 translate-y-[20px]">
    <div
        class="bg-slate-900 text-white px-6 py-4 rounded-[1.5rem] shadow-2xl flex items-center gap-4 border border-white/10 mx-4">
        <div class="w-10 h-10 rounded-xl bg-red-500 flex items-center justify-center shrink-0">
            <i class="fas fa-exclamation-triangle text-sm"></i>
        </div>
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pesan Keamanan</p>
            <p id="toastMessage" class="text-sm font-semibold"></p>
        </div>
    </div>
</div>

<!-- Forgot PIN Modal -->
<div id="forgotPinModal"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 scale-95 pointer-events-none transition-all duration-300 ease-out">
    <div
        class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto transform transition-all duration-300 ease-out">
        <div class="p-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-800 text-blue-800">Reset PIN <?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?></h3>
                <button onclick="closeForgotPinModal()"
                    class="text-slate-400 hover:text-slate-600 text-2xl transition-colors duration-200">&times;</button>
            </div>
            <div class="space-y-6">
                <div class="text-center">
                    <div
                        class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                        <i class="fas fa-key text-blue-600 text-2xl"></i>
                    </div>
                    <p class="text-slate-600 text-sm leading-relaxed mb-4">
                        Lupa PIN? Jangan khawatir! Proses reset PIN dilakukan secara manual untuk menjaga keamanan
                        data Anda.
                    </p>
                    <p class="text-slate-500 text-xs">
                        Hubungi Admin Lab Akuntansi atau Kepala Sekolah untuk memulai proses verifikasi.
                    </p>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200">
                    <h4 class="text-sm font-bold text-blue-900 mb-3 flex items-center gap-2">
                        <i class="fas fa-info-circle text-blue-600"></i>
                        Langkah-langkah Reset PIN:
                    </h4>
                    <ol class="text-xs text-blue-700 space-y-2 list-decimal list-inside">
                        <li>Kumpulkan informasi identitas Anda (nama, NISN/NIP, kelas/jabatan)</li>
                        <li>Siapkan alasan reset PIN yang jelas</li>
                        <li>Hubungi admin melalui WhatsApp atau datang langsung ke lab</li>
                        <li>Tunggu verifikasi dan konfirmasi dari admin</li>
                        <li>PIN baru akan diberikan setelah verifikasi berhasil</li>
                    </ol>
                </div>

                <div class="bg-yellow-50 p-4 rounded-xl border border-yellow-200">
                    <h4 class="text-sm font-bold text-yellow-900 mb-2 flex items-center gap-2">
                        <i class="fas fa-shield-alt text-yellow-600"></i>
                        Tips Keamanan:
                    </h4>
                    <ul class="text-xs text-yellow-700 space-y-1">
                        <li>• Jangan bagikan PIN Anda kepada siapapun</li>
                        <li>• Gunakan PIN yang mudah diingat tapi sulit ditebak</li>
                        <li>• Segera ubah PIN default setelah registrasi</li>
                        <li>• Laporkan jika ada aktivitas mencurigakan</li>
                    </ul>
                </div>

                <div class="bg-green-50 p-4 rounded-xl border border-green-200">
                    <h4 class="text-sm font-bold text-green-900 mb-2">Informasi Kontak:</h4>
                    <div class="text-xs text-green-700 space-y-1">
                        <p><strong>Admin Lab Akuntansi:</strong> +62 812-3456-7890</p>
                        <p><strong>Kepala Sekolah:</strong> +62 811-2345-6789</p>
                        <p><strong>Lokasi:</strong> Lab Komputer SMK Negeri 1</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button onclick="closeForgotPinModal()"
                        class="flex-1 py-3 bg-slate-200 text-slate-700 font-bold rounded-xl hover:bg-slate-300 transition-all duration-200 transform hover:scale-105 active:scale-95">
                        Tutup
                    </button>
                    <a href="https://wa.me/6281234567890?text=Halo%20Admin,%20saya%20ingin%20reset%20PIN%20e-IBIS.%20Berikut%20data%20saya:%0ANama:%20[NAMA]%0ANISN/NIP:%20[NISN/NIP]%0AKelas/Jabatan:%20[KELAS/JABATAN]%0AAlasan:%20[ALASAN]"
                        target="_blank"
                        class="flex-1 py-3 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition-all duration-200 transform hover:scale-105 active:scale-95 text-center flex items-center justify-center gap-2">
                        <i class="fab fa-whatsapp"></i>
                        <span>Hubungi Admin</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once 'layouts/auth-footer.php'; ?>