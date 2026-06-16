<?php

/**
 * FILE: views/superadmin/jurusan/tambah-jurusan.php
 * DESKRIPSI: Halaman tambah master data jurusan baru (Tema: Blue).
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
    $kode_jurusan = strtoupper(trim($_POST['kode_jurusan'])); // Otomatis jadikan huruf kapital
    $nama_jurusan = trim($_POST['nama_jurusan']);

    // Validasi input wajib isi
    if (empty($kode_jurusan) || empty($nama_jurusan)) {
        $error_msg = "Kode Jurusan dan Nama Jurusan wajib diisi!";
    } else {
        try {
            $sql_insert = "INSERT INTO tbl_jurusan (kode_jurusan, nama_jurusan) VALUES (:kode, :nama)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                'kode' => $kode_jurusan,
                'nama' => $nama_jurusan
            ]);

            if (function_exists('writeLog')) {
                writeLog($pdo, "➕ TAMBAH JURUSAN: Berhasil menambahkan kompetensi jurusan baru [$kode_jurusan] $nama_jurusan.");
            }

            $pesan_sukses = "Jurusan baru ($nama_jurusan) berhasil ditambahkan.";
            echo "<script>window.location.href = '?page=jurusan&msg=Data+jurusan+berhasil+ditambahkan&type=success';</script>";
            exit;
        } catch (PDOException $e) {
            error_log("Insert Jurusan Error: " . $e->getMessage());
            // Deteksi duplikasi UNIQUE KEY kode_jurusan
            if ($e->getCode() == 23000) {
                $error_msg = "Gagal simpan! Kode Jurusan '$kode_jurusan' sudah digunakan oleh jurusan lain.";
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
                    <i class="fas fa-folder-plus text-xs"></i>
                </div>
                <div>
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Form Tambah Master Jurusan</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Buat kompetensi keahlian/program studi baru untuk relasi data siswa.</p>
                </div>
            </div>
            <a href="?page=jurusan" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-800 text-[11px] leading-relaxed">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Integritas Master Data:</span> Pembuatan kompetensi keahlian baru wajib menggunakan <span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-bold text-[10px]">KODE UNIK</span> (Maks. 10 Karakter). Sistem akan otomatis menolak jika singkatan jurusan tersebut sudah pernah terdaftar di database.
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
                            <i class="fas fa-key text-blue-500 text-[10px]"></i> Kode Singkatan Jurusan <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="kode_jurusan" placeholder="Contoh: RPL, TKJ, AKL" required maxlength="10"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-mono font-bold text-slate-800 tracking-wider uppercase transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Maksimal 10 karakter. Bersifat unik dan tidak boleh sama antar jurusan.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-graduation-cap text-slate-400 text-[10px]"></i> Nama Lengkap Jurusan <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama_jurusan" placeholder="Contoh: Rekayasa Perangkat Lunak" required maxlength="50"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Maksimal 50 karakter. Masukkan nama utuh kompetensi keahlian.</small>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="reset" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-500 text-white hover:bg-blue-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-plus text-[10px]"></i> Tambah Jurusan Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>