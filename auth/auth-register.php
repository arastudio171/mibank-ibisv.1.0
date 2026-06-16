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

// TAMBAHAN LOGIKA SALDO UNTUK PETUNJUK PENDAFTARAN
$saldo_raw       = $setting['saldo'] ?? 15000;
$saldo_format    = "Rp " . number_format($saldo_raw, 0, ',', '.');

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
                        Petunjuk Pendaftaran
                    </p>

                    <ul class="space-y-2">
                        <li class="flex items-start gap-3 text-xs">
                            <span class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">1</span>
                            <span>
                                Lengkapi data diri dengan benar sesuai identitas yang dimiliki.
                            </span>
                        </li>

                        <li class="flex items-start gap-3 text-xs">
                            <span class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">2</span>
                            <span>
                                Gunakan <b class="text-amber-300 font-semibold">NISN</b> sebagai
                                <b class="text-amber-300 font-semibold">Username</b> akun Anda.
                            </span>
                        </li>

                        <li class="flex items-start gap-3 text-xs">
                            <span class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">3</span>
                            <span>
                                Buat <b class="text-amber-300 font-semibold">PIN 6 digit</b>
                                yang mudah diingat namun tidak mudah ditebak.
                            </span>
                        </li>

                        <li class="flex items-start gap-3 text-xs">
                            <span class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">4</span>
                            <span>
                                Pilih <b class="text-amber-300 font-semibold">Kelas</b> dan
                                <b class="text-amber-300 font-semibold">Jurusan</b> sesuai data akademik Anda.
                            </span>
                        </li>

                        <li class="flex items-start gap-3 text-xs">
                            <span class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center font-bold text-[9px] shrink-0">
                                5
                            </span>

                            <div class="space-y-2">
                                <p class="text-justify">
                                    Setelah pendaftaran berhasil, temui
                                    <b class="text-amber-300 font-semibold">Operator Tabungan</b>
                                    untuk proses verifikasi dan aktivasi akun dengan membawa:
                                </p>

                                <ul class="space-y-1 pl-2">
                                    <li>• <b class="text-amber-300 font-semibold">Kartu Pelajar</b></li>
                                    <li>• <b class="text-amber-300 font-semibold">Surat Permohonan Pembukaan Tabungan</b></li>
                                    <li>• <b class="text-amber-300 font-semibold">Setoran Awal Rp <?= htmlspecialchars($saldo_format); ?></b></li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-4 pt-3 border-t border-white/10">
                        <div class="flex items-start gap-2">
                            <i class="fas fa-circle-info text-amber-300 mt-0.5 shrink-0"></i>
                            <p class="text-[11px] text-blue-100 leading-relaxed text-justify">
                                Proses verifikasi dan aktivasi hanya dilakukan pada jam operasional.
                            </p>
                        </div>
                    </div>
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
    <div class="w-full md:w-8/12 lg:w-7/12 p-8 md:p-12 lg:p-16 flex flex-col justify-center bg-white">
        <div class="max-w-5xl mx-auto w-full">
            <div class="mb-8">
                <h3 class="text-3xl font-800 text-blue-800 mb-2">
                    <b class="text-blue-600">Daftar</b>
                    <?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?>
                </h3>
                <p class="text-slate-500 font-medium text-sm leading-relaxed">
                    Silakan lakukan pendaftaran untuk mengelola tabungan harian Anda.
                </p>

                <!-- Alert Section -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5"></i>
                        <div>
                            <p class="text-xs text-yellow-800 font-medium"><b>Peringatan Keamanan</b></p>
                            <p class="text-xs text-yellow-700 leading-relaxed">
                                Pendaftaran hanya untuk Nasabah Siswa/Anggota.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <form class="space-y-5" method="post" action="check-register.php">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <!-- Full Name -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">
                            Nama Lengkap
                        </label>
                        <div class="relative flex items-center input-focus-effect border-2 border-slate-100 rounded-xl bg-slate-50/50 transition-all">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <input type="text" id="fullName" placeholder="Nama Nasabah" name="nama_siswa" required class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Masukkan nama lengkap sesuai identitas.
                        </small>
                    </div>

                    <!-- Nama Ibu Kandung -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">
                            Nama Ibu Kandung
                        </label>
                        <div class="relative flex items-center input-focus-effect border-2 border-slate-100 rounded-xl bg-slate-50/50 transition-all">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <input type="text" name="nama_ibu_kandung" placeholder="Nama Ibu Kandung" required
                                class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Masukkan nama ibu kandung.
                        </small>
                    </div>

                    <!-- NIK -->
                    <div class="group md:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">
                            NIK <span class="text-slate-300">(Opsional)</span>
                        </label>
                        <div class="relative flex items-center input-focus-effect border-2 border-slate-100 rounded-xl bg-slate-50/50 transition-all">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-address-card"></i>
                            </div>
                            <input type="text" name="nik" maxlength="16"
                                placeholder="Nomor Identitas KTP (Opsional)"
                                class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Isi jika memiliki NIK, boleh dikosongkan apabila belum tersedia.
                        </small>
                    </div>

                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">
                            NISN
                        </label>
                        <div class="relative flex items-center input-focus-effect border-2 border-slate-100 rounded-xl bg-slate-50/50 transition-all">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" id="username" placeholder="NISN" name="nisn" required class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Masukan NISN sebagai username.
                        </small>
                    </div>

                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">
                            PIN Transaksi
                        </label>
                        <div class="relative flex items-center input-focus-effect border-2 border-slate-100 rounded-xl bg-slate-50/50 transition-all">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-key"></i>
                            </div>
                            <input type="password" id="password" placeholder="••••••••" name="password" required minlength="8" class="w-full pl-3 pr-12 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                            <button type="button" onclick="togglePass()" class="absolute right-4 text-slate-300 hover:text-slate-500">
                                <i id="eyeIcon" class="fas fa-eye"></i>
                            </button>
                        </div>

                        <div id="strength-meter-container" class="hidden mt-2.5 transition-all duration-300">
                            <div class="flex justify-between items-center mb-1 text-[11px] font-semibold text-slate-500">
                                <span>Kekuatan Keamanan:</span>
                                <span id="strength-text" class="transition-colors duration-300">Belum diisi</span>
                            </div>
                            <div class="grid grid-cols-4 gap-1.5 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div id="bar-1" class="h-full rounded-full bg-slate-200 transition-all duration-300"></div>
                                <div id="bar-2" class="h-full rounded-full bg-slate-200 transition-all duration-300"></div>
                                <div id="bar-3" class="h-full rounded-full bg-slate-200 transition-all duration-300"></div>
                                <div id="bar-4" class="h-full rounded-full bg-slate-200 transition-all duration-300"></div>
                            </div>
                        </div>

                        <small class="text-slate-400 text-xs mt-1 block">
                            Buat PIN Transaksi minimal 8 karakter.
                        </small>
                    </div>

                    <!-- Kelas -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">
                            Kelas
                        </label>
                        <div class="relative flex items-center border-2 border-slate-100 rounded-xl bg-slate-50/50">
                            <div class="pl-4 text-slate-400">
                                <i class="fas fa-school"></i>
                            </div>
                            <select name="kelas" required class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700">
                                <option disabled selected class="text-slate-400 font-normal">-- Pilih Kelas --</option>
                                <option value="X">X</option>
                                <option value="XI">XI</option>
                                <option value="XII">XII</option>
                            </select>
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Pilih tingkat kelas saat ini.
                        </small>
                    </div>

                    <!-- Jurusan -->
                    <div class="group">
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">
                            Jurusan
                        </label>
                        <div class="relative flex items-center border-2 border-slate-100 rounded-xl bg-slate-50/50">
                            <div class="pl-4 text-slate-400">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <select name="id_jurusan" required class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700">
                                <option disabled selected class="text-slate-400 font-normal">-- Pilih Jurusan --</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT id_jurusan, nama_jurusan FROM tbl_jurusan ORDER BY nama_jurusan ASC");
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='{$row['id_jurusan']}'>" . htmlspecialchars($row['nama_jurusan']) . "</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option disabled>Error: " . $e->getMessage() . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <small class="text-slate-400 text-xs mt-1 block">
                            Pilih jurusan sesuai program keahlian.
                        </small>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full py-4 bg-[#1257aa] text-white font-bold rounded-xl hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-3">
                    <span>DAFTAR</span>
                </button>

                <div class="text-center">
                    <p class="text-sm text-slate-500 font-medium mb-2">
                        Sudah memiliki Akun?
                        <a href="auth-login.php" class="text-[#1257aa] font-bold decoration-2">
                            Masuk <b class="text-emerald-600"><?= htmlspecialchars($nama_aplikasi); ?> v<?= htmlspecialchars($versi_aplikasi); ?></b>
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

<div id="toast"
    class="fixed bottom-6 left-1/2 -translate-x-1/2 md:left-auto md:right-6 md:translate-x-0 z-[60] pointer-events-none opacity-0 transition-all duration-500 translate-y-[20px]">
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
<?php include_once 'layouts/auth-footer.php'; ?>