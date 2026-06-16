<?php

/**
 * FILE: views/superadmin/operator/edit-operator.php
 * DESKRIPSI: Form Edit Petugas Teller dengan Aksen Warna Amber (Kuning)
 * Logika: Diperkuat, Sinkron dengan Variabel Role ID = 2, Validasi Server-side.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔑 SINKRONISASI ROUTING: Parameter page untuk kembali
$redirect_page = 'petugas';

// 🔒 PENGUATAN KEAMANAN 1: Validasi Hak Akses Admin & Parameter ID
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin' || !isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href = '?page=$redirect_page';</script>";
    exit();
}

$id_user = (int)$_GET['id'];
$id_role_operator = 2; // Menyelaraskan ID Role Operator sesuai master data Tambah
$error_msg = '';
$operator = null;

// 1. AMBIL DATA DARI DATABASE (GET)
try {
    // 🔒 PENGUATAN KEAMANAN 2: Pastikan ID yang dicari benar-benar ber-role Operator (ID 2)
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE id_user = :id AND id_role = :role");
    $stmt->execute(['id' => $id_user, 'role' => $id_role_operator]);
    $operator = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$operator) {
        // Redirect jika hacker mencoba menebak ID Admin lain via URL
        echo "<script>window.location.href = '?page=$redirect_page&msg=Data petugas tidak ditemukan atau akses ditolak!&type=error';</script>";
        exit();
    }
} catch (PDOException $e) {
    $error_msg = "Gagal memuat data: " . $e->getMessage();
}

// 2. PROSES SUBMIT PERUBAHAN DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $username     = strtolower(trim($_POST['username'])); // Sanitasi: Paksa huruf kecil
    $password     = $_POST['password'];

    // 🔒 PENGUATAN KEAMANAN 3: Validasi Server-side Ketat
    if (empty($nama_lengkap) || empty($username)) {
        $error_msg = "Nama lengkap dan username wajib diisi.";
    } elseif (preg_match('/[^a-z0-9_]/', $username)) {
        $error_msg = "Username hanya boleh huruf kecil, angka, dan underscore (_). Tanpa spasi!";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error_msg = "Password baru terlalu pendek! Minimal 6 karakter.";
    } else {
        try {
            // Cek duplikasi username (Kecuali milik user ini sendiri)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM tbl_users WHERE username = :user AND id_user != :id");
            $stmt_check->execute(['user' => $username, 'id' => $id_user]);

            if ($stmt_check->fetchColumn() > 0) {
                $error_msg = "Username '@" . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "' sudah digunakan petugas lain!";
            } else {
                // Eksekusi Update
                if (!empty($password)) {
                    // Jika ganti password
                    $pass_hashed = password_hash($password, PASSWORD_BCRYPT);
                    $sql_update = "UPDATE tbl_users 
                                   SET nama_lengkap = :nama, username = :user, password = :pass 
                                   WHERE id_user = :id AND id_role = :role";
                    $params = [
                        'nama' => $nama_lengkap,
                        'user' => $username,
                        'pass' => $pass_hashed,
                        'id' => $id_user,
                        'role' => $id_role_operator
                    ];
                } else {
                    // Jika password kosong (tidak diganti)
                    $sql_update = "UPDATE tbl_users 
                                   SET nama_lengkap = :nama, username = :user 
                                   WHERE id_user = :id AND id_role = :role";
                    $params = [
                        'nama' => $nama_lengkap,
                        'user' => $username,
                        'id' => $id_user,
                        'role' => $id_role_operator
                    ];
                }

                $pdo->prepare($sql_update)->execute($params);
                echo "<script>window.location.href = '?page=$redirect_page&msg=Berkas identitas petugas diperbarui!&type=success';</script>";
                exit();
            }
        } catch (PDOException $e) {
            $error_msg = "Gagal memperbarui database: " . $e->getMessage();
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
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Form Edit Otoritas Petugas</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Modifikasi identitas profil atau lakukan reset password berkala.</p>
                </div>
            </div>
            <a href="?page=<?= $redirect_page ?>" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-amber-50/70 border border-amber-100 rounded-xl flex items-start gap-2.5 text-amber-900 text-[11px] leading-relaxed">
            <i class="fas fa-info-circle text-amber-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Pemeliharaan Kredensial:</span> Perubahan username memengaruhi ID log masuk petugas. Jika petugas <span class="bg-amber-100 text-amber-900 px-1.5 py-0.5 rounded font-bold text-[10px]">TIDAK INGIN</span> mengganti kata sandi, biarkan kolom input password <span class="font-bold">KOSONG</span>.
            </div>
        </div>

        <div class="p-6 space-y-6">

            <?php if (isset($error_msg) && $error_msg): ?>
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-semibold flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-sm mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="block font-bold uppercase text-[10px] tracking-wider mb-0.5">Perubahan Gagal</span>
                        <p class="font-medium text-rose-500/90"><?= $error_msg ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">

                    <div class="flex flex-col gap-1.5 md:col-span-2">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-user text-amber-500 text-[10px]"></i> Nama Lengkap Petugas <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($operator['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Gunakan nama lengkap personil untuk pelaporan log audit transaksi yang valid.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-at text-amber-500 text-[10px]"></i> Username Otoritas <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3 font-mono text-amber-500 font-bold text-[11px] select-none">@</span>
                            <input type="text" name="username" value="<?= htmlspecialchars($operator['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                                class="w-full pl-7 pr-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-mono font-bold text-slate-800 transition-all">
                        </div>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Hanya huruf kecil, angka, dan (_) tanpa spasi. Bersifat unik.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-lock text-slate-400 text-[10px]"></i> Kata Sandi Baru <span class="text-slate-400 font-normal">(Opsional)</span>
                        </label>
                        <input type="password" name="password" placeholder="Isi hanya jika ingin ganti sandi..." minlength="6"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Kosongkan jika petugas menggunakan kata sandi lama.</small>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <a href="?page=<?= $redirect_page ?>" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1">
                        Batal
                    </a>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 text-white hover:bg-amber-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-check-circle text-[10px]"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>