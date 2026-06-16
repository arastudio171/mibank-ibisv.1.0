<?php

/**
 * FILE: views/superadmin/tahun_ajaran/edit-tahun.php
 * DESKRIPSI: Halaman ubah konfigurasi master data tahun ajaran lama (Tema: Amber).
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
    header("Location: ../../../main.php?page=tahun-ajaran&msg=ID Tahun Ajaran tidak ditemukan.&type=error");
    exit();
}

$id_tahun_ajaran = (int)$_GET['id'];

try {
    $sql_fetch = "SELECT * FROM tbl_tahun_ajaran WHERE id_tahun_ajaran = :id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->execute(['id' => $id_tahun_ajaran]);
    $tahun_data = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$tahun_data) {
        header("Location: ../../../main.php?page=tahun-ajaran&msg=Data tahun ajaran tidak ditemukan.&type=error");
        exit();
    }
} catch (PDOException $e) {
    error_log("Fetch Tahun Ajaran Error: " . $e->getMessage());
    header("Location: ../../../main.php?page=tahun-ajaran&msg=Terjadi kesalahan internal.&type=error");
    exit();
}

// 3. LOGIKA PROSES SIMPAN PERUBAHAN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tahun_ajaran = trim($_POST['tahun_ajaran']);
    $status_aktif = isset($_POST['status_aktif']) ? $_POST['status_aktif'] : 'nonaktif';

    if (empty($tahun_ajaran)) {
        $error_msg = "Rentang Tahun Ajaran wajib diisi!";
    } elseif (!preg_match('/^[0-9]{4}\/[0-9]{4}$/', $tahun_ajaran)) {
        $error_msg = "Format penulisan tidak valid! Gunakan format baku seperti 2025/2026.";
    } else {
        try {
            $pdo->beginTransaction();

            // Jika status diubah menjadi 'aktif', nonaktifkan dulu tahun ajaran lainnya
            if ($status_aktif === 'aktif') {
                $sql_reset = "UPDATE tbl_tahun_ajaran SET status_aktif = 'nonaktif' WHERE status_aktif = 'aktif'";
                $pdo->exec($sql_reset);
            }

            // Update data tbl_tahun_ajaran terkait
            $sql_update = "UPDATE tbl_tahun_ajaran SET 
                            tahun_ajaran = :tahun,
                            status_aktif = :status
                          WHERE id_tahun_ajaran = :id";

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                'tahun'  => $tahun_ajaran,
                'status' => $status_aktif,
                'id'     => $id_tahun_ajaran
            ]);

            if (function_exists('writeLog')) {
                writeLog($pdo, "📝 UPDATE TAHUN AJARAN: Mengubah informasi periode ID $id_tahun_ajaran menjadi [$tahun_ajaran] dengan status [$status_aktif].");
            }

            $pdo->commit();

            echo "<script>window.location.href = '?page=tahun-ajaran&msg=Data+tahun+ajaran+berhasil+diperbarui&type=success';</script>";
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update Tahun Ajaran Error: " . $e->getMessage());

            if ($e->getCode() == 23000) {
                $error_msg = "Gagal simpan! Rentang Tahun Ajaran '$tahun_ajaran' sudah digunakan oleh periode lain.";
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
                    <i class="fas fa-edit text-xs"></i>
                </div>
                <div>
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Form Edit Data Tahun Ajaran</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Perbarui rentang periode atau alihkan status keaktifan tahun akademik.</p>
                </div>
            </div>
            <a href="?page=tahun-ajaran" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-amber-50/70 border border-amber-100 rounded-xl flex items-start gap-2.5 text-amber-800 text-[11px] leading-relaxed">
            <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Dampak Relasi Sistem:</span> Mengubah komponen <span class="bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-bold text-[10px]">TAHUN AJARAN</span> akan memengaruhi pengelompokan riwayat transaksi simpanan nasabah. Jika Anda mengubah status menjadi <span class="font-bold text-emerald-600">Aktif</span>, periode sebelumnya akan otomatis dinonaktifkan oleh sistem.
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-calendar-alt text-amber-500 text-[10px]"></i> Rentang Tahun Ajaran <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($tahun_data['tahun_ajaran'] ?? '') ?>" required maxlength="9" pattern="^[0-9]{4}\/[0-9]{4}$"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-mono font-bold text-slate-800 tracking-wider transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Perubahan pada format penulisan wajib tetap menggunakan pola baku (Contoh: 2025/2026).</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-toggle-on text-slate-400 text-[10px]"></i> Status Operasional Sistem <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative w-full">
                            <select name="status_aktif" required
                                class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 font-bold text-slate-700 transition-all appearance-none cursor-pointer">
                                <option value="nonaktif" <?= ($tahun_data['status_aktif'] === 'nonaktif') ? 'selected' : '' ?> class="font-semibold text-slate-600">NONAKTIF (Arsip Master Data)</option>
                                <option value="aktif" <?= ($tahun_data['status_aktif'] === 'aktif') ? 'selected' : '' ?> class="font-bold text-emerald-600">AKTIF (Gunakan Sebagai Periode Berjalan)</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400 text-[10px]">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </div>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Menyetel status ke AKTIF akan menonaktifkan tahun ajaran aktif lainnya di database.</small>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="reset" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-amber-500 text-white hover:bg-amber-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-save text-[11px]"></i> Simpan Perubahan Periode
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>