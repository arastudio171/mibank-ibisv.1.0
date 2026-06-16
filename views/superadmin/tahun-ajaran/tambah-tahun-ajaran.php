<?php

/**
 * FILE: views/superadmin/tahun_ajaran/tambah-tahun.php
 * DESKRIPSI: Halaman tambah master data tahun ajaran baru (Tema: Blue).
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

// 2. LOGIKA PROSES SIMPAN DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tahun_ajaran = trim($_POST['tahun_ajaran']);
    $status_aktif = isset($_POST['status_aktif']) ? $_POST['status_aktif'] : 'nonaktif';

    // Validasi input wajib isi dan format penulisan
    if (empty($tahun_ajaran)) {
        $error_msg = "Rentang Tahun Ajaran wajib diisi!";
    } elseif (!preg_match('/^[0-9]{4}\/[0-9]{4}$/', $tahun_ajaran)) {
        $error_msg = "Format penulisan tidak valid! Gunakan format baku seperti 2025/2026.";
    } else {
        try {
            $pdo->beginTransaction();

            // Jika status yang dipilih adalah 'aktif', nonaktifkan dulu tahun ajaran lain yang saat ini sedang aktif
            if ($status_aktif === 'aktif') {
                $sql_reset = "UPDATE tbl_tahun_ajaran SET status_aktif = 'nonaktif' WHERE status_aktif = 'aktif'";
                $pdo->exec($sql_reset);
            }

            // Insert data baru ke tbl_tahun_ajaran
            $sql_insert = "INSERT INTO tbl_tahun_ajaran (tahun_ajaran, status_aktif) VALUES (:tahun, :status)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                'tahun'  => $tahun_ajaran,
                'status' => $status_aktif
            ]);

            if (function_exists('writeLog')) {
                writeLog($pdo, "➕ TAMBAH TAHUN AJARAN: Berhasil menambahkan periode baru [$tahun_ajaran] dengan status [$status_aktif].");
            }

            $pdo->commit();

            echo "<script>window.location.href = '?page=tahun-ajaran&msg=Data+tahun+ajaran+berhasil+ditambahkan&type=success';</script>";
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Insert Tahun Ajaran Error: " . $e->getMessage());

            // Deteksi duplikasi UNIQUE KEY tahun_ajaran
            if ($e->getCode() == 23000) {
                $error_msg = "Gagal simpan! Rentang Tahun Ajaran '$tahun_ajaran' sudah pernah terdaftar di sistem.";
            } else {
                $error_msg = "Gagal menambahkan data karena kendala database internal.";
            }
        }
    }
}
?>

<div class="space-y-6 w-full">
    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100/50 flex-shrink-0">
                    <i class="fas fa-calendar-plus text-xs"></i>
                </div>
                <div>
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Form Tambah Master Tahun Ajaran</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Buat rentang tahun akademik baru dan tentukan status operasional pencatatan kas.</p>
                </div>
            </div>
            <a href="?page=tahun-ajaran" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-800 text-[11px] leading-relaxed">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Integritas Master Data:</span> Penulisan tahun ajaran wajib menggunakan <span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-bold text-[10px]">FORMAT BAKU</span> sepanjang 9 karakter (Contoh: 2025/2026). Jika Anda memilih status <span class="font-bold text-emerald-600">Aktif Berjalan</span>, maka sistem otomatis mengubah status tahun ajaran lainnya menjadi nonaktif.
            </div>
        </div>

        <div class="p-6 space-y-6">

            <?php if (isset($error_msg)): ?>
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-semibold flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-sm mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="block font-bold uppercase text-[10px] tracking-wider mb-0.5">Pendaftaran Gagal</span>
                        <p class="font-medium text-rose-500/90"><?= $error_msg ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-calendar-alt text-blue-500 text-[10px]"></i> Rentang Tahun Ajaran <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="tahun_ajaran" placeholder="Contoh: 2025/2026" required maxlength="9" pattern="^[0-9]{4}\/[0-9]{4}$"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-mono font-bold text-slate-800 tracking-wider transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Gunakan format 4 digit tahun awal, garis miring, diikuti 4 digit tahun akhir (Maks. 9 karakter).</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-toggle-on text-slate-400 text-[10px]"></i> Status Operasional Sistem <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative w-full">
                            <select name="status_aktif" required
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-bold text-slate-700 transition-all appearance-none cursor-pointer">
                                <option value="nonaktif" selected class="font-semibold text-slate-600">NONAKTIF (Arsip Master Data)</option>
                                <option value="aktif" class="font-bold text-emerald-600">AKTIF (Gunakan Sebagai Periode Berjalan)</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400 text-[10px]">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </div>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Hanya boleh ada satu tahun ajaran yang aktif untuk merekam transaksi buku tabungan.</small>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="reset" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-500 text-white hover:bg-blue-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-plus text-[10px]"></i> Tambah Tahun Ajaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>