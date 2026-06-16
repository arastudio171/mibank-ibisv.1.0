<?php

/**
 * Berkas: views/nasabah/edit_profil.php
 * Deskripsi: Halaman melengkapi profil nasabah (Optimasi Kontras Visual + Relokasi Log Sistem)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_nasabah = $_SESSION['id_nasabah'] ?? null;
$error_msg = "";
$success_msg = "";

if (!$id_nasabah) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

// 1. Ambil Daftar Jurusan dari tbl_jurusan untuk opsi Pilihan Relasional
$list_jurusan = [];
try {
    $stmt_jurusan = $pdo->query("SELECT id_jurusan, nama_jurusan FROM tbl_jurusan ORDER BY nama_jurusan ASC");
    $list_jurusan = $stmt_jurusan->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Gagal memuat data jurusan: " . $e->getMessage());
}

// 2. Ambil Data Profil Nasabah Saat Ini
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_nasabah WHERE id_nasabah = ?");
    $stmt->execute([$id_nasabah]);
    $nasabah = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nasabah) {
        throw new Exception("Data nasabah tidak ditemukan.");
    }
} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

// 3. Proses Validasi & Jalankan Query Update Data (POST)
// Proteksi Admin: Kolom NISN, NIK, dan Nama Ibu Kandung sengaja dikunci dari query UPDATE ini
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nama_nasabah       = trim($_POST['nama_nasabah']);
    $tempat_lahir       = !empty(trim($_POST['tempat_lahir'])) ? trim($_POST['tempat_lahir']) : null;
    $tanggal_lahir      = !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null;
    $jenis_kelamin      = !empty($_POST['jenis_kelamin']) ? $_POST['jenis_kelamin'] : null;
    $jenjang_pendidikan = !empty($_POST['jenjang_pendidikan']) ? $_POST['jenjang_pendidikan'] : null;
    $kelas              = $_POST['kelas'];
    $id_jurusan         = !empty($_POST['id_jurusan']) ? intval($_POST['id_jurusan']) : null;
    $telepon            = !empty(trim($_POST['telepon'])) ? trim($_POST['telepon']) : null;
    $email              = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
    $alamat             = !empty(trim($_POST['alamat'])) ? trim($_POST['alamat']) : null;

    if (empty($nama_nasabah) || empty($kelas)) {
        $error_msg = "Nama Lengkap dan Kelas wajib diisi.";
    } else {
        try {
            $sql_update = "UPDATE tbl_nasabah SET 
                nama_nasabah = ?, tempat_lahir = ?, tanggal_lahir = ?, 
                jenis_kelamin = ?, jenjang_pendidikan = ?, kelas = ?, 
                id_jurusan = ?, telepon = ?, email = ?, alamat = ? 
                WHERE id_nasabah = ?";

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                $nama_nasabah,
                $tempat_lahir,
                $tanggal_lahir,
                $jenis_kelamin,
                $jenjang_pendidikan,
                $kelas,
                $id_jurusan,
                $telepon,
                $email,
                $alamat,
                $id_nasabah
            ]);

            $success_msg = "Profil Anda berhasil diperbarui secara aman.";

            // Refresh data terbaru ke variabel view
            $stmt_refresh = $pdo->prepare("SELECT * FROM tbl_nasabah WHERE id_nasabah = ?");
            $stmt_refresh->execute([$id_nasabah]);
            $nasabah = $stmt_refresh->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error update profil nasabah: " . $e->getMessage());
            $error_msg = "Terjadi kesalahan sistem, gagal menyimpan perubahan.";
        }
    }
}
?>

<div id="section-grafik" class="w-full">
    <div class="bg-white p-4 sm:p-8 rounded-[1rem] border border-slate-200/60 shadow-sm transition-all duration-300">

        <div class="bg-[#1566c7] p-6 text-white flex justify-between items-center shadow-md rounded-2xl mb-6">
            <div>
                <h3 class="text-base font-black uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-user-edit text-amber-300"></i> Pengaturan Biodata Mandiri Nasabah
                </h3>
                <p class="text-xs text-slate-200 mt-1 font-medium">Perbarui informasi kontak dan data penunjang instansi secara berkala secara mandiri.</p>
            </div>
            <div class="text-white/40 text-2xl pr-1">
                <i class="fas fa-user-cog"></i>
            </div>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="mb-6 flex items-center gap-3 p-4 bg-rose-50 border border-rose-100 text-rose-700 rounded-xl text-sm font-semibold">
                <i class="fas fa-exclamation-circle text-base"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="mb-6 flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-100 text-emerald-700 rounded-xl text-sm font-semibold">
                <i class="fas fa-check-circle text-base"></i>
                <span><?= htmlspecialchars($success_msg) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

                <div class="bg-slate-50/80 p-5 rounded-2xl border border-slate-200/70 shadow-sm space-y-4 text-sm">
                    <h4 class="text-xs font-black text-slate-800 uppercase tracking-wide border-b border-slate-200 pb-2 mb-2 flex items-center gap-1.5">
                        <i class="fas fa-user text-blue-600"></i> Identitas Diri
                    </h4>

                    <div class="group flex flex-col">
                        <label for="nama_nasabah" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                            Nama Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" id="nama_nasabah" name="nama_nasabah" required autocomplete="off"
                                placeholder="Contoh: Muhammad Ali"
                                value="<?= htmlspecialchars($nasabah['nama_nasabah'] ?? '') ?>"
                                class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                            Masukkan nama lengkap sesuai yang terdaftar pada kartu pelajar resmi sekolah.
                        </small>
                    </div>

                    <div class="group flex flex-col">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                            Nomor Induk Siswa Nasional (NISN) <i class="fas fa-lock text-[10px] ml-0.5 text-slate-400"></i>
                        </label>
                        <div class="relative flex items-center border-2 border-slate-200/80 rounded-xl bg-slate-200/50 transition-all">
                            <div class="pl-4 text-slate-400">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="w-full pl-3 pr-4 py-3.5 text-sm font-mono font-bold text-slate-500 select-all cursor-not-allowed">
                                <?= htmlspecialchars($nasabah['nisn'] ?? '-') ?>
                            </div>
                        </div>
                        <small class="text-amber-600 font-medium text-[11px] mt-1.5 block pl-1">
                            <i class="fas fa-info-circle mr-0.5"></i> Hanya Admin yang dapat memperbarui data NISN ini.
                        </small>
                    </div>

                    <div class="group flex flex-col">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                            Nomor Induk Kependudukan (NIK) <i class="fas fa-lock text-[10px] ml-0.5 text-slate-400"></i>
                        </label>
                        <div class="relative flex items-center border-2 border-slate-200/80 rounded-xl bg-slate-200/50 transition-all">
                            <div class="pl-4 text-slate-400">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="w-full pl-3 pr-4 py-3.5 text-sm font-mono font-bold text-slate-500 select-all cursor-not-allowed">
                                <?= htmlspecialchars($nasabah['nik'] ?? '-') ?>
                            </div>
                        </div>
                        <small class="text-amber-600 font-medium text-[11px] mt-1.5 block pl-1">
                            <i class="fas fa-info-circle mr-0.5"></i> Hanya Admin yang dapat memperbarui data NIK (Kartu Keluarga) ini.
                        </small>
                    </div>

                    <div class="group flex flex-col">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                            Nama Ibu Kandung <i class="fas fa-lock text-[10px] ml-0.5 text-slate-400"></i>
                        </label>
                        <div class="relative flex items-center border-2 border-slate-200/80 rounded-xl bg-slate-200/50 transition-all">
                            <div class="pl-4 text-slate-400">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="w-full pl-3 pr-4 py-3.5 text-sm font-medium text-slate-500 select-all cursor-not-allowed">
                                <?= htmlspecialchars($nasabah['nama_ibu_kandung'] ?? '-') ?>
                            </div>
                        </div>
                        <small class="text-amber-600 font-medium text-[11px] mt-1.5 block pl-1">
                            <i class="fas fa-info-circle mr-0.5"></i> Nama ibu kandung terkunci demi instrumen keamanan finansial nasabah.
                        </small>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="group flex flex-col">
                            <label for="tempat_lahir" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                                Tempat Lahir
                            </label>
                            <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                                <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <input type="text" id="tempat_lahir" name="tempat_lahir"
                                    placeholder="Contoh: Jakarta Selatan"
                                    value="<?= htmlspecialchars($nasabah['tempat_lahir'] ?? '') ?>"
                                    class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                            </div>
                            <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                                Nama kabupaten/kota kelahiran.
                            </small>
                        </div>
                        <div class="group flex flex-col">
                            <label for="tanggal_lahir" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                                Tanggal Lahir
                            </label>
                            <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                                <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <input type="date" id="tanggal_lahir" name="tanggal_lahir"
                                    class="w-full pl-3 pr-4 py-3 bg-transparent outline-none text-sm font-medium text-slate-700"
                                    value="<?= htmlspecialchars($nasabah['tanggal_lahir'] ?? '') ?>">
                            </div>
                            <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                                Tanggal, bulan, dan tahun lahir.
                            </small>
                        </div>
                    </div>

                    <div class="group flex flex-col">
                        <label for="jenis_kelamin" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                            Jenis Kelamin
                        </label>
                        <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-venus-mars"></i>
                            </div>
                            <select id="jenis_kelamin" name="jenis_kelamin"
                                class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-medium text-slate-700 cursor-pointer">
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="L" <?= ($nasabah['jenis_kelamin'] === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($nasabah['jenis_kelamin'] === 'P') ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                            Pilih klasifikasi identitas gender legal Anda.
                        </small>
                    </div>
                </div>

                <div class="flex flex-col gap-6">

                    <div class="bg-slate-50/80 p-5 rounded-2xl border border-slate-200/70 shadow-sm space-y-4 text-sm flex-1">
                        <h4 class="text-xs font-black text-slate-800 uppercase tracking-wide border-b border-slate-200 pb-2 mb-2 flex items-center gap-1.5">
                            <i class="fas fa-graduation-cap text-blue-600"></i> Data Pendidikan
                        </h4>

                        <div class="group flex flex-col">
                            <label for="jenjang_pendidikan" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                                Jenjang Sekolah
                            </label>
                            <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                                <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                    <i class="fas fa-school"></i>
                                </div>
                                <select id="jenjang_pendidikan" name="jenjang_pendidikan"
                                    class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-bold text-slate-700 cursor-pointer">
                                    <option value="">-- Pilih Jenjang --</option>
                                    <?php foreach (['PAUD', 'TK', 'SD', 'SMP', 'SMA', 'SMK'] as $jp): ?>
                                        <option value="<?= $jp ?>" <?= ($nasabah['jenjang_pendidikan'] === $jp) ? 'selected' : '' ?>><?= $jp ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                                Tingkat satuan pendidikan aktif tempat Anda bersekolah.
                            </small>
                        </div>

                        <div class="group flex flex-col">
                            <label for="kelas" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                                Kelas / Ruang <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                                <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                    <i class="fas fa-chalkboard"></i>
                                </div>
                                <select id="kelas" name="kelas" required
                                    class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-bold text-slate-700 cursor-pointer">
                                    <option value="X" <?= ($nasabah['kelas'] === 'X') ? 'selected' : '' ?>>Kelas X</option>
                                    <option value="XI" <?= ($nasabah['kelas'] === 'XI') ? 'selected' : '' ?>>Kelas XI</option>
                                    <option value="XII" <?= ($nasabah['kelas'] === 'XII') ? 'selected' : '' ?>>Kelas XII</option>
                                </select>
                            </div>
                            <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                                Tingkatan rombongan belajar (rombel) yang diduduki sekarang.
                            </small>
                        </div>

                        <div class="group flex flex-col">
                            <label for="id_jurusan" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                                Kompetensi Keahlian (Jurusan)
                            </label>
                            <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                                <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <select id="id_jurusan" name="id_jurusan"
                                    class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-medium text-slate-700 cursor-pointer">
                                    <option value="">-- Pilih Jurusan --</option>
                                    <?php foreach ($list_jurusan as $jurus): ?>
                                        <option value="<?= $jurus['id_jurusan'] ?>" <?= ($nasabah['id_jurusan'] == $jurus['id_jurusan']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($jurus['nama_jurusan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                                Program studi fokus keahlian (Khusus jenjang menengah atas/kejuruan).
                            </small>
                        </div>
                    </div>

                    <div class="bg-slate-50/80 p-5 rounded-2xl border border-slate-200/70 shadow-sm space-y-3.5 text-sm">
                        <h4 class="text-xs font-black text-slate-800 uppercase tracking-wide border-b border-slate-200 pb-2 mb-2 flex items-center gap-1.5">
                            <i class="fas fa-shield-alt text-rose-600"></i> Keamanan & Log Sistem
                        </h4>
                        <div class="flex flex-col bg-white p-2.5 border border-slate-200/60 rounded-xl">
                            <span class="text-xs text-slate-400 font-bold uppercase tracking-wider pl-1 mb-1">Akun Terdaftar Sejak</span>
                            <span class="font-mono text-emerald-600 font-bold text-xs px-1 flex items-center gap-1.5">
                                <i class="fas fa-calendar-check text-emerald-500"></i> <?= htmlspecialchars($nasabah['created_at'] ?? '-') ?>
                            </span>
                        </div>
                        <div class="flex flex-col bg-white p-2.5 border border-slate-200/60 rounded-xl">
                            <span class="text-xs text-slate-400 font-bold uppercase tracking-wider pl-1 mb-1">Sesi Login Terakhir</span>
                            <span class="font-mono text-slate-600 text-xs px-1 flex items-center gap-1.5">
                                <i class="far fa-clock text-slate-400"></i> <?= $nasabah['last_login'] ? htmlspecialchars($nasabah['last_login']) : 'Belum Ada Log Sesi' ?>
                            </span>
                        </div>
                        <div class="flex flex-col bg-white p-2.5 border border-slate-200/60 rounded-xl">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-slate-400 font-bold uppercase tracking-wider pl-1">Gagal Akses PIN</span>
                                <span class="font-bold text-rose-600 font-mono bg-rose-50 px-2 py-0.5 rounded-lg text-xs border border-rose-100"><?= htmlspecialchars($nasabah['pin_failed_attempts'] ?? '0') ?> Kali</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/80 p-5 rounded-2xl border border-slate-200/70 shadow-sm text-sm space-y-4">
                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wide border-b border-slate-200 pb-2 flex items-center gap-1.5">
                    <i class="fas fa-address-book text-blue-600"></i> Informasi Kontak & Alamat Domisili
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="group flex flex-col">
                        <label for="telepon" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                            No. Telepon / WA
                        </label>
                        <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-phone"></i>
                            </div>
                            <input type="text" id="telepon" name="telepon"
                                placeholder="Contoh: 081234567890"
                                value="<?= htmlspecialchars($nasabah['telepon'] ?? '') ?>"
                                class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-bold font-mono text-slate-800 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                            Disarankan nomor WhatsApp aktif untuk menerima laporan notifikasi mutasi tabungan.
                        </small>
                    </div>
                    <div class="group flex flex-col">
                        <label for="email" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                            Email Aktif
                        </label>
                        <div class="relative flex items-center border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                            <div class="pl-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <input type="email" id="email" name="email"
                                placeholder="Contoh: budi.setiawan@gmail.com"
                                value="<?= htmlspecialchars($nasabah['email'] ?? '') ?>"
                                class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-bold font-mono text-slate-800 placeholder:text-slate-400">
                        </div>
                        <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                            Gunakan alamat surat elektronik (email) valid untuk kebutuhan pemulihan PIN / Akun.
                        </small>
                    </div>
                </div>
                <div class="group flex flex-col pt-2">
                    <label for="alamat" class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-2 ml-1">
                        Alamat Rumah Lengkap
                    </label>
                    <div class="relative flex items-start border-2 border-slate-200 rounded-xl bg-white transition-all focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/10">
                        <div class="pl-4 pt-4 text-slate-400 group-focus-within:text-blue-500 transition-colors">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <textarea id="alamat" name="alamat" rows="3"
                            placeholder="Contoh: Jl. Pahlawan No. 45, RT 02/RW 01, Kelurahan Kedaton, Kecamatan Kedaton"
                            class="w-full pl-3 pr-4 py-3.5 bg-transparent outline-none text-sm font-medium text-slate-700 leading-relaxed placeholder:text-slate-400"><?= htmlspecialchars($nasabah['alamat'] ?? '') ?></textarea>
                    </div>
                    <small class="text-slate-400 text-xs mt-1.5 block pl-1">
                        Tuliskan alamat domisili tinggal Anda saat ini secara detail dan rinci.
                    </small>
                </div>
            </div>

            <div class="pt-5 border-t border-slate-200/80 flex items-center justify-end gap-3.5">
                <a href="?page=dashboard"
                    class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-black uppercase tracking-wider rounded-xl transition-all shadow-sm">
                    Batal
                </a>
                <button type="submit" name="update_profil"
                    class="flex items-center justify-center bg-gradient-to-r from-[#1566c7] to-[#1257aa] hover:from-[#1257aa] hover:to-[#0f4994] text-white text-xs font-black py-3 px-7 rounded-xl transition-all shadow-md shadow-blue-500/10 tracking-wider uppercase">
                    <i class="fas fa-save mr-2"></i> Simpan Perubahan Profil
                </button>
            </div>

        </form>
    </div>
</div>