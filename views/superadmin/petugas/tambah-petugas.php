<?php

/**
 * FILE: views/superadmin/petugas/tambah-petugas.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// VALIDASI AKSES: Samakan dengan halaman utama (Mencari 'admin')
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>window.location.href = 'auth/auth-login.php?msg=Akses ditolak! Khusus Admin.&type=error';</script>";
    exit();
}

$error_msg = '';
$id_role_operator = 2; // ID Role untuk Operator/Petugas Anda

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];

    if (empty($nama_lengkap) || empty($username) || empty($password)) {
        $error_msg = "Seluruh formulir wajib diisi tanpa terkecuali!";
    } else {
        try {
            // Cek duplikasi username di tbl_users
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE username = :user");
            $stmt_check->execute(['user' => $username]);

            if ($stmt_check->fetchColumn() > 0) {
                $error_msg = "Username '@" . htmlspecialchars($username) . "' sudah terdaftar di sistem!";
            } else {
                $password_hashed = password_hash($password, PASSWORD_BCRYPT);

                // Query Insert sesuai struktur: tbl_users & status_akun enum ('aktif')
                $sql_insert = "INSERT INTO tbl_users (username, password, nama_lengkap, id_role, status_akun) 
                               VALUES (:user, :pass, :nama, :role, 'aktif')";
                $pdo->prepare($sql_insert)->execute([
                    'user' => $username,
                    'pass' => $password_hashed,
                    'nama' => $nama_lengkap,
                    'role' => $id_role_operator
                ]);

                echo "<script>window.location.href = '?page=petugas&msg=Akun petugas baru berhasil didaftarkan!&type=success';</script>";
                exit();
            }
        } catch (PDOException $e) {
            $error_msg = "Kegagalan Sistem Database: " . $e->getMessage();
        }
    }
}
?>

<div class="space-y-6 w-full">
    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100/50 flex-shrink-0">
                    <i class="fas fa-user-plus text-xs"></i>
                </div>
                <div>
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Form Registrasi Petugas Teller Baru</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Daftarkan hak akses berkas kredensial petugas baru ke dalam database.</p>
                </div>
            </div>
            <a href="?page=petugas" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-800 text-[11px] leading-relaxed">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Otoritas Kredensial:</span> Pembuatan akun petugas wajib menggunakan <span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-bold text-[10px]">USERNAME UNIK</span>. Sistem akan otomatis menolak jika username telah digunakan oleh petugas lain demi keamanan data log transaksi.
            </div>
        </div>

        <div class="p-6 space-y-6">

            <?php if (isset($error_msg) && $error_msg): ?>
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-semibold flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-sm mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="block font-bold uppercase text-[10px] tracking-wider mb-0.5">Registrasi Gagal</span>
                        <p class="font-medium text-rose-500/90"><?= $error_msg ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">

                    <div class="flex flex-col gap-1.5 md:col-span-2">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-user text-slate-400 text-[10px]"></i> Nama Lengkap Petugas <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama_lengkap" placeholder="Masukkan nama lengkap beserta gelar..." required
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Masukkan nama utuh personil untuk pelaporan cetak struk dan jurnal kas.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-at text-blue-500 text-[10px]"></i> Username Otoritas <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 font-mono text-slate-400 font-bold text-[11px] select-none">@</span>
                            <input type="text" name="username" placeholder="contoh: petugas_spp" required
                                class="w-full pl-7 pr-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-mono font-bold text-slate-800 transition-all">
                        </div>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Gunakan huruf kecil tanpa spasi. Bersifat unik untuk proses autentikasi masuk.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-lock text-slate-400 text-[10px]"></i> Kata Sandi (Password) <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" name="password" placeholder="Minimal 6 karakter kombinasi..." required
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Gunakan kombinasi alphanumeric (huruf dan angka) untuk keamanan optimal.</small>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="reset" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-500 text-white hover:bg-blue-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-plus text-[10px]"></i> Simpan Data Otoritas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>