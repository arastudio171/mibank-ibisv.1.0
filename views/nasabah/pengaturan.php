<?php

/**
 * LOGIKA BACKEND: Pengaturan Akun & Keamanan
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_nasabah = $_SESSION['id_nasabah'] ?? null;

$error_msg   = $_SESSION['error_msg'] ?? "";
$success_msg = $_SESSION['success_msg'] ?? "";

unset($_SESSION['error_msg'], $_SESSION['success_msg']);

// 1. Ambil Data Profil Nasabah Secara Dinamis (DIUPGRADE DENGAN LEFT JOIN)
if ($id_nasabah) {
    $stmt = $pdo->prepare("
        SELECT 
            n.nisn,
            n.nama_nasabah, 
            n.status_nasabah, 
            n.kelas, 
            n.tempat_lahir, 
            n.tanggal_lahir, 
            n.telepon,
            j.kode_jurusan,
            j.nama_jurusan
        FROM tbl_nasabah n
        LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
        WHERE n.id_nasabah = ?
    ");
    $stmt->execute([$id_nasabah]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Handle Perubahan Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $pw_lama = isset($_POST['pw_lama']) ? trim($_POST['pw_lama']) : '';
    $pw_baru = isset($_POST['pw_baru']) ? trim($_POST['pw_baru']) : '';
    $pw_konf = isset($_POST['pw_konf']) ? trim($_POST['pw_konf']) : '';

    if (empty($pw_lama) || empty($pw_baru) || empty($pw_konf)) {
        $_SESSION['error_msg'] = "Semua kolom password wajib diisi.";
    } elseif ($pw_baru !== $pw_konf) {
        $_SESSION['error_msg'] = "Konfirmasi password baru tidak cocok.";
    } elseif (strlen($pw_baru) < 8) {
        $_SESSION['error_msg'] = "Password baru minimal harus 8 karakter.";
    } else {
        $stmt_cek = $pdo->prepare("SELECT password FROM tbl_nasabah WHERE id_nasabah = ?");
        $stmt_cek->execute([$id_nasabah]);
        $data_user = $stmt_cek->fetch();

        if ($data_user && password_verify($pw_lama, $data_user['password'])) {
            $hash_baru = password_hash($pw_baru, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE tbl_nasabah SET password = ? WHERE id_nasabah = ?");

            if ($update->execute([$hash_baru, $id_nasabah])) {
                $_SESSION['success_msg'] = "Kata sandi berhasil diperbarui demi keamanan akun Anda.";
            } else {
                $_SESSION['error_msg'] = "Terjadi kesalahan sistem saat memperbarui data.";
            }
        } else {
            $_SESSION['error_msg'] = "Password lama yang Anda masukkan salah.";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!-- SECTION: RIWAYAT -->
<div id="section-riwayat" class="space-y-8">
    <!-- <div class="bg-white rounded-[1rem] border border-slate-100 overflow-hidden shadow-sm"> -->
    <div class="overflow-x-auto">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- LEFT: Profile Sidebar -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-[1.25rem] p-10 shadow-xl shadow-slate-200/50 text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-32 sm:bg-gradient-to-br sm:from-[#2978d7] sm:via-[#1566c7] sm:to-[#1257aa]"></div>

                    <div class="relative pt-6 text-center">
                        <div class="relative w-32 h-32 mx-auto mb-4">
                            <div class="w-full h-full bg-white rounded-[1rem] ring-8 ring-white shadow-xl flex items-center justify-center">
                                <i class="fas fa-user text-amber-600 text-5xl"></i>
                            </div>
                        </div>

                        <div class="flex flex-col items-center gap-2 mt-1 mb-5">
                            <!-- <p class="text-slate-400 font-bold text-xs uppercase tracking-widest">
                                Nasabah <?= htmlspecialchars($user['status_nasabah'] ?? 'Reguler') ?>
                            </p> -->
                            <h3 class="text-xl font-black text-slate-800 uppercase tracking-wide">
                                <?= htmlspecialchars($user['nama_nasabah'] ?? 'Nama Tidak Tersedia') ?>
                            </h3>
                            <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-emerald-50 text-emerald-600 rounded-xl text-[10px] font-extrabold uppercase tracking-widest border border-emerald-100">
                                <span class="w-1 h-1 rounded-xl bg-emerald-500 animate-pulse"></span>
                                Verified Account
                            </div>
                        </div>

                        <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-4 text-left space-y-3 mb-5">
                            <div class="flex items-center justify-between text-xs border-b border-slate-100 pb-2">
                                <span class="text-slate-400 font-medium flex items-center gap-2">
                                    <i class="far fa-id-card text-teal-500 w-4"></i> NISN
                                </span>
                                <span class="text-slate-700 font-bold tracking-wider">
                                    <?= htmlspecialchars($user['nisn'] ?? '-') ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs border-b border-slate-100 pb-2">
                                <span class="text-slate-400 font-medium flex items-center gap-2">
                                    <i class="fas fa-layer-group text-indigo-500 w-4"></i> Kelas / Jurusan
                                </span>
                                <span class="text-slate-700 font-bold uppercase">
                                    <?= htmlspecialchars(($user['kelas'] ?? '-') . ' ' . ($user['kode_jurusan'] ?? '')) ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs border-b border-slate-100 pb-2">
                                <span class="text-slate-400 font-medium flex items-center gap-2">
                                    <i class="fas fa-graduation-cap text-emerald-500 w-4"></i> Program Keahlian
                                </span>
                                <span class="text-slate-700 font-semibold truncate max-w-[180px]" title="<?= htmlspecialchars($user['nama_jurusan'] ?? '-') ?>">
                                    <?= htmlspecialchars($user['nama_jurusan'] ?? '-') ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs border-b border-slate-100 pb-2">
                                <span class="text-slate-400 font-medium flex items-center gap-2">
                                    <i class="far fa-calendar-alt text-sky-500 w-4"></i> Tempat & Tanggal Lahir
                                </span>
                                <span class="text-slate-700 font-medium">
                                    <?= htmlspecialchars($user['tempat_lahir'] ?? '-') ?>,
                                    <?= !empty($user['tanggal_lahir']) ? date('d M Y', strtotime($user['tanggal_lahir'])) : '-' ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-400 font-medium flex items-center gap-2">
                                    <i class="fab fa-whatsapp text-green-500 font-bold text-sm w-4"></i> WhatsApp
                                </span>
                                <span class="text-slate-700 font-bold">
                                    <?= htmlspecialchars($user['telepon'] ?? '-') ?>
                                </span>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-400 italic leading-relaxed px-4 mb-4">
                            "Menjaga kerahasiaan data pribadi adalah langkah awal menuju kebebasan finansial."
                        </p>

                        <div class="pt-2 border-t border-dashed border-slate-100">
                            <a href="?page=edit-profil" class="flex items-center justify-center gap-2 w-full text-center bg-[#1257aa] text-white font-bold text-xs py-3 px-4 rounded-xl shadow-md shadow-blue-500/10 transition-all duration-200 group">
                                <i class="fas fa-user-cog text-[11px] text-white transition-colors"></i>
                                Lengkapi Profil Anda
                            </a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT: Settings Integrated -->
            <div class="lg:col-span-8">
                <form method="POST" action="" class="bg-white rounded-[1rem] border border-slate-200/70 shadow-xl shadow-slate-200/40 overflow-hidden">

                    <div class="px-6 py-6 sm:px-8 sm:py-8 bg-slate-50 border-b border-slate-100">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] rounded-xl flex items-center justify-center text-white shadow-md shadow-blue-100 shrink-0">
                                <i class="fas fa-key text-lg"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-black text-slate-800 tracking-tight">Keamanan Akun</h4>
                                <p class="text-xs sm:text-sm text-slate-500 font-medium">Ubah kata sandi secara berkala untuk menjaga akun tetap aman.</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 sm:p-8 space-y-6">

                        <?php if ($error_msg): ?>
                            <div class="bg-rose-50 border border-rose-100 text-rose-600 px-5 py-3.5 rounded-xl text-xs font-bold flex items-center gap-3">
                                <i class="fas fa-circle-exclamation text-base"></i> <?= $error_msg ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_msg): ?>
                            <div class="bg-emerald-50 border border-emerald-100 text-emerald-600 px-5 py-3.5 rounded-xl text-xs font-bold flex items-center gap-3">
                                <i class="fas fa-circle-check text-base"></i> <?= $success_msg ?>
                            </div>
                        <?php endif; ?>

                        <div class="bg-blue-50 border border-blue-100 text-blue-700 px-5 py-3.5 rounded-xl text-xs font-medium flex items-start gap-3 leading-relaxed">
                            <i class="fas fa-shield-halved text-base text-blue-500 mt-0.5 shrink-0"></i>
                            <div>
                                <span class="font-bold block mb-0.5">Pemberitahuan Sistem Keamanan:</span>
                                Pastikan Anda mengingat kata sandi saat ini sebelum melakukan perubahan. Setelah disimpan, sesi login akan disinkronkan ulang secara otomatis demi perlindungan privasi.
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <div class="space-y-2 md:col-span-2">
                                <label class="text-xs font-extrabold uppercase tracking-wider text-slate-500 ml-0.5">Password Lama</label>
                                <div class="relative flex items-center border border-slate-200 bg-slate-50/50 rounded-xl focus-within:border-blue-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-blue-500/5 transition-all group px-4">
                                    <i class="fas fa-lock text-slate-300 group-focus-within:text-blue-500 transition-colors text-sm w-5 text-center shrink-0"></i>
                                    <input type="password" name="pw_lama" placeholder="••••••••••••" required
                                        class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                                </div>
                                <small class="text-slate-400 font-medium block mt-1 ml-0.5">Pastikan password lama sesuai dengan data saat ini.</small>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-extrabold uppercase tracking-wider text-slate-500 ml-0.5">Password Baru</label>
                                <div class="relative flex items-center border border-slate-200 bg-slate-50/50 rounded-xl focus-within:border-blue-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-blue-500/5 transition-all group px-4">
                                    <i class="fas fa-shield text-slate-300 group-focus-within:text-blue-500 transition-colors text-sm w-5 text-center shrink-0"></i>
                                    <input type="password" name="pw_baru" placeholder="Sandi baru" required
                                        class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                                </div>
                                <small class="text-slate-400 font-medium block mt-1 ml-0.5">Gunakan minimal 8 karakter kombinasi.</small>
                            </div>

                            <div class="space-y-2">
                                <label class="text-xs font-extrabold uppercase tracking-wider text-slate-500 ml-0.5">Verifikasi Ulang Password</label>
                                <div class="relative flex items-center border border-slate-200 bg-slate-50/50 rounded-xl focus-within:border-blue-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-blue-500/5 transition-all group px-4">
                                    <i class="fas fa-check-double text-slate-300 group-focus-within:text-blue-500 transition-colors text-sm w-5 text-center shrink-0"></i>
                                    <input type="password" name="pw_konf" placeholder="Ulangi sandi baru" required
                                        class="w-full pl-3 pr-4 py-4 bg-transparent outline-none text-sm font-medium text-slate-700 placeholder:text-slate-400">
                                </div>
                                <small class="text-slate-400 font-medium block mt-1 ml-0.5">Ulangi password baru untuk verifikasi.</small>
                            </div>

                        </div>

                        <div class="pt-2">
                            <input type="hidden" name="update_password" value="1">
                            <button type="submit"
                                class="w-full py-3.5 bg-[#1257aa] hover:bg-[#0e488f] text-white font-black text-xs uppercase tracking-widest rounded-xl transition-all shadow-md shadow-blue-500/10 hover:shadow-lg hover:shadow-blue-500/20 cursor-pointer active:scale-[0.99]">
                                <i class="fas fa-paper-plane mr-2"></i> Konfirmasi & Simpan Perubahan Keamanan
                            </button>
                        </div>
                    </div>

                </form>
            </div>

        </div>
    </div>
    <!-- </div> -->
</div>