<?php

/**
 * FILE: views/superadmin/nasabah/edit-nasabah.php
 * DESKRIPSI: Halaman edit biodata nasabah dengan Dropdown Jurusan dinamis dari tabel tbl_jurusan.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hubungkan ke database menggunakan path absolut Anda
require_once __DIR__ . '/../../../auth/database.php';

// 1. VALIDASI AKSES
$allowed_roles = ['admin', 'superadmin'];
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!in_array($user_role, $allowed_roles)) {
    header("Location: ../../../auth/auth-login.php?msg=Akses ditolak! Anda tidak memiliki kewenangan.&type=error");
    exit();
}

// 2. LOGIKA AMBIL DATA LAMA (GET)
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../../../main.php?page=nasabah&msg=ID Nasabah tidak ditemukan.&type=error");
    exit();
}

$id_nasabah = trim($_GET['id']);
$daftar_jurusan = [];

try {
    // Ambil data nasabah aktif
    $sql_fetch = "SELECT * FROM tbl_nasabah WHERE id_nasabah = :id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->execute(['id' => $id_nasabah]);
    $nasabah = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$nasabah) {
        header("Location: ../../../main.php?page=nasabah&msg=Data nasabah tidak ditemukan.&type=error");
        exit();
    }

    // [OTOMATISASI JURUSAN] Ambil master data jurusan untuk keperluan dropdown select
    $sql_jurusan = "SELECT * FROM tbl_jurusan ORDER BY id_jurusan ASC";
    $stmt_jurusan = $pdo->query($sql_jurusan);
    $daftar_jurusan = $stmt_jurusan->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Data Error: " . $e->getMessage());
    header("Location: ../../../main.php?page=nasabah&msg=Terjadi kesalahan internal.&type=error");
    exit();
}

// 3. LOGIKA PROSES SIMPAN PERUBAHAN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_nasabah       = trim($_POST['nama_nasabah']);
    $nisn               = trim($_POST['nisn']);
    $nik                = !empty($_POST['nik']) ? trim($_POST['nik']) : null;
    $nama_ibu_kandung   = trim($_POST['nama_ibu_kandung']);
    $tempat_lahir       = trim($_POST['tempat_lahir']);
    $tanggal_lahir      = !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null;
    $jenis_kelamin      = $_POST['jenis_kelamin'];
    $jenjang_pendidikan = $_POST['jenjang_pendidikan'];
    $kelas              = $_POST['kelas'];
    $id_jurusan         = !empty($_POST['id_jurusan']) ? (int)$_POST['id_jurusan'] : null; // Menerima ID angka dari select box
    $telepon            = trim($_POST['telepon']);
    $email              = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $alamat             = trim($_POST['alamat']);

    if (empty($nama_nasabah) || empty($nisn) || empty($nama_ibu_kandung)) {
        $error_msg = "Nama Nasabah, NISN, dan Nama Ibu Kandung wajib diisi!";
    } else {
        try {
            $sql_update = "UPDATE tbl_nasabah SET 
                            nisn = :nisn,
                            nik = :nik,
                            nama_nasabah = :nama_nasabah,
                            nama_ibu_kandung = :nama_ibu_kandung,
                            tempat_lahir = :tempat_lahir,
                            tanggal_lahir = :tanggal_lahir,
                            jenis_kelamin = :jenis_kelamin,
                            jenjang_pendidikan = :jenjang_pendidikan,
                            kelas = :kelas,
                            id_jurusan = :id_jurusan,
                            telepon = :telepon,
                            email = :email,
                            alamat = :alamat
                          WHERE id_nasabah = :id";

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                'nisn'               => $nisn,
                'nik'                => $nik,
                'nama_nasabah'       => $nama_nasabah,
                'nama_ibu_kandung'   => $nama_ibu_kandung,
                'tempat_lahir'       => $tempat_lahir,
                'tanggal_lahir'      => $tanggal_lahir,
                'jenis_kelamin'      => $jenis_kelamin,
                'jenjang_pendidikan' => $jenjang_pendidikan,
                'kelas'              => $kelas,
                'id_jurusan'         => $id_jurusan,
                'telepon'            => $telepon,
                'email'              => $email,
                'alamat'             => $alamat,
                'id'                 => $id_nasabah
            ]);

            if (function_exists('writeLog')) {
                writeLog($pdo, "📝 UPDATE BIODATA: Data nasabah '$nama_nasabah' (ID: $id_nasabah) berhasil diperbarui.");
            }

            $pesan_sukses = "Biodata nasabah atas nama $nama_nasabah berhasil diperbarui.";
            header("Location: ../../../main.php?page=nasabah&msg=" . urlencode($pesan_sukses) . "&type=success");
            exit();
        } catch (PDOException $e) {
            error_log("Update Nasabah Error: " . $e->getMessage());
            if ($e->getCode() == 23000) {
                $error_msg = "Gagal simpan! NISN atau NIK sudah digunakan oleh nasabah lain.";
            } else {
                $error_msg = "Gagal memperbarui data karena gangguan database internal.";
            }
        }
    }
}
?>

<div class="space-y-6 w-full">
    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 shadow-sm border border-amber-100/50 flex-shrink-0">
                    <i class="fas fa-user-edit text-xs"></i>
                </div>
                <div>
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Form Konfigurasi Biodata Nasabah</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Perbarui informasi profil kependudukan, kontak, dan instansi pendidikan siswa.</p>
                </div>
            </div>
            <a href="?page=nasabah" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-amber-50/70 border border-amber-100 rounded-xl flex items-start gap-2.5 text-amber-800 text-[11px] leading-relaxed">
            <i class="fas fa-shield-alt text-amber-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Manajemen Keamanan Data:</span> Pastikan perubahan data nasabah telah sesuai dengan berkas fisik resmi. Mengubah komponen <span class="bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-bold text-[10px]">NISN MURNI</span> akan otomatis memperbarui kredensial login utama siswa secara *real-time*.
            </div>
        </div>

        <div class="p-6 space-y-6">

            <?php if (isset($error_msg)): ?>
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-semibold flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-sm mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="block font-bold uppercase text-[10px] tracking-wider mb-0.5">Gagal Menyimpan Data</span>
                        <p class="font-medium text-rose-500/90"><?= $error_msg ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-id-badge text-slate-400 text-[10px]"></i> Nama Lengkap Nasabah <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama_nasabah" value="<?= htmlspecialchars($nasabah['nama_nasabah'] ?? '') ?>" required
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Gunakan nama resmi tanpa singkatan sesuai akta kelahiran/ijazah.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-fingerprint text-indigo-500 text-[10px]"></i> NISN Murni <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nisn" value="<?= htmlspecialchars($nasabah['nisn'] ?? '') ?>" required maxlength="20"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-mono font-bold text-slate-800 tracking-wider transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Maksimal 20 digit nomor induk siswa nasional sebagai ID login utama.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-id-card text-slate-400 text-[10px]"></i> NIK Kependudukan
                        </label>
                        <input type="text" name="nik" value="<?= htmlspecialchars($nasabah['nik'] ?? '') ?>" maxlength="20"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-mono text-slate-800 tracking-wider transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Maksimal 20 digit Nomor Induk Kependudukan sesuai KTP / KK.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-female text-rose-400 text-[11px]"></i> Nama Ibu Kandung <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama_ibu_kandung" value="<?= htmlspecialchars($nasabah['nama_ibu_kandung'] ?? '') ?>" required
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-medium text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Wajib diisi sebagai salah satu prasyarat validasi sistem perbankan.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-map-marker-alt text-slate-400 text-[10px]"></i> Tempat Lahir
                        </label>
                        <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($nasabah['tempat_lahir'] ?? '') ?>"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-medium text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Tuliskan nama kota/kabupaten tempat nasabah dilahirkan.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-calendar-alt text-slate-400 text-[10px]"></i> Tanggal Lahir
                        </label>
                        <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($nasabah['tanggal_lahir'] ?? '') ?>"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Atur tanggal lahir resmi nasabah.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-venus-mars text-slate-400 text-[10px]"></i> Jenis Kelamin
                        </label>
                        <select name="jenis_kelamin" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-bold text-slate-800 transition-all">
                            <option value="L" <?= ($nasabah['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki (L)</option>
                            <option value="P" <?= ($nasabah['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan (P)</option>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Data dikirim dalam kode inisial ENUM database ('L' / 'P').</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-school text-amber-500 text-[10px]"></i> Jenjang Pendidikan
                        </label>
                        <select name="jenjang_pendidikan" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-bold text-slate-800 transition-all">
                            <?php foreach (['PAUD', 'TK', 'SD', 'SMP', 'SMA', 'SMK'] as $jng): ?>
                                <option value="<?= $jng ?>" <?= ($nasabah['jenjang_pendidikan'] ?? '') === $jng ? 'selected' : '' ?>><?= $jng ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Pilih salah satu tingkatan lembaga sekolah yang valid.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-chalkboard text-slate-400 text-[10px]"></i> Tingkat Kelas
                        </label>
                        <select name="kelas" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-bold text-slate-800 transition-all">
                            <?php foreach (['X', 'XI', 'XII'] as $kls): ?>
                                <option value="<?= $kls ?>" <?= ($nasabah['kelas'] ?? '') === $kls ? 'selected' : '' ?>><?= $kls ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Tingkat rombongan belajar aktif siswa saat ini.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-graduation-cap text-indigo-500 text-[10px]"></i> Program / Kompetensi Keahlian
                        </label>
                        <select name="id_jurusan" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-bold text-slate-800 transition-all">
                            <option value="">-- Tanpa Jurusan / Umum --</option>
                            <?php if (!empty($daftar_jurusan)): ?>
                                <?php foreach ($daftar_jurusan as $jur): ?>
                                    <option value="<?= $jur['id_jurusan'] ?>" <?= ((int)($nasabah['id_jurusan'] ?? 0) === (int)$jur['id_jurusan']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($jur['nama_jurusan'] ?? $jur['id_jurusan']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Pilih nama kompetensi jurusan resmi dari database sekolah.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fab fa-whatsapp text-emerald-500 text-xs"></i> Nomor Telepon / WA
                        </label>
                        <input type="text" name="telepon" value="<?= htmlspecialchars($nasabah['telepon'] ?? '') ?>" maxlength="15"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-mono text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Gunakan nomor seluler aktif maksimal sepanjang 15 digit angka.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="far fa-envelope text-slate-400 text-[11px]"></i> Alamat Surel / Email
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($nasabah['email'] ?? '') ?>" maxlength="100"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-medium text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Alamat surat elektronik untuk sinkronisasi bukti kuitansi digital.</small>
                    </div>

                    <div class="flex flex-col gap-1.5 md:col-span-2 lg:col-span-3">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-map-marked-alt text-slate-400 text-[10px]"></i> Domisili Alamat Rumah Tinggal
                        </label>
                        <textarea name="alamat" rows="3"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-medium text-slate-800 transition-all"><?= htmlspecialchars($nasabah['alamat'] ?? '') ?></textarea>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Tuliskan alamat domisili pendaftaran saat ini secara lengkap.</small>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="reset" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm">
                        <i class="fas fa-undo mr-1"></i> Reset Kolom
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 text-white hover:bg-amber-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-save text-[11px]"></i> Simpan Seluruh Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>