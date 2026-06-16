<?php

/**
 * FILE: views/superadmin/loket/tambah-loket.php
 * DESKRIPSI: Halaman tambah data loket operasional baru dengan tema warna biru (Blue).
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

// 2. LOGIKA AMBIL DATA PETUGAS (KASIR/STAFF) UNTUK DROPDOWN
// Mengambil user yang belum ditugaskan di loket manapun untuk menghindari error Unique Constraint
$daftar_petugas = [];
try {
    $sql_petugas = "SELECT id_user, username, nama_lengkap 
                    FROM tbl_users 
                    WHERE LOWER(role) IN ('admin', 'teller', 'petugas', 'staff')
                    AND id_user NOT IN (SELECT id_petugas FROM tbl_loket WHERE id_petugas IS NOT NULL)
                    ORDER BY nama_lengkap ASC";
    $stmt_petugas = $pdo->query($sql_petugas);
    $daftar_petugas = $stmt_petugas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Master Petugas Error: " . $e->getMessage());
}

// 3. LOGIKA PROSES SIMPAN DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_loket  = isset($_POST['nomor_loket']) ? (int)$_POST['nomor_loket'] : 0;
    $nama_loket   = trim($_POST['nama_loket']);
    $id_petugas   = !empty($_POST['id_petugas']) ? (int)$_POST['id_petugas'] : null;
    $status_loket = $_POST['status_loket'] ?? 'tutup';

    // Validasi data wajib isi
    if ($nomor_loket <= 0 || empty($nama_loket)) {
        $error_msg = "Nomor Loket dan Nama Loket wajib diisi secara valid!";
    } else {
        try {
            // Jalankan perintah Insert Data
            $sql_insert = "INSERT INTO tbl_loket (nomor_loket, nama_loket, id_petugas, status_loket) 
                           VALUES (:nomor_loket, :nama_loket, :id_petugas, :status_loket)";

            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([
                'nomor_loket'  => $nomor_loket,
                'nama_loket'   => $nama_loket,
                'id_petugas'   => $id_petugas,
                'status_loket' => $status_loket
            ]);

            // Catat log jika fungsi log global aktif
            if (function_exists('writeLog')) {
                writeLog($pdo, "➕ TAMBAH LOKET: Berhasil mendaftarkan loket baru '$nama_loket' (Nomor: $nomor_loket).");
            }

            $pesan_sukses = "Infrastruktur loket baru ($nama_loket) berhasil didaftarkan ke sistem.";
            header("Location: main.php?page=loket&msg=" . urlencode($pesan_sukses) . "&type=success");
            exit();
        } catch (PDOException $e) {
            error_log("Insert Loket Error: " . $e->getMessage());

            // Deteksi Error Duplikat Data (Unique Key)
            if ($e->getCode() == 23000) {
                // Periksa apakah nomor loket atau petugas yang duplikat
                if (strpos($e->getMessage(), 'nomor_loket') !== false) {
                    $error_msg = "Gagal simpan! Nomor loket [$nomor_loket] sudah digunakan oleh loket lain.";
                } elseif (strpos($e->getMessage(), 'id_petugas') !== false) {
                    $error_msg = "Gagal simpan! Petugas tersebut sudah ditugaskan di loket lain.";
                } else {
                    $error_msg = "Gagal simpan! Terjadi pelanggaran relasi data unik pada sistem database.";
                }
            } else {
                $error_msg = "Gagal menambahkan data loket karena kendala sistem internal.";
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
                    <i class="fas fa-plus-circle text-xs"></i>
                </div>
                <div>
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Form Pendaftaran Loket Baru</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Tambah gerbang antrean baru untuk optimalisasi alur pelayanan kasir.</p>
                </div>
            </div>
            <a href="main.php?page=loket" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-800 text-[11px] leading-relaxed">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Ketentuan Pengisian Jalur Gerbang:</span> Setiap <span class="font-bold">Nomor Loket</span> bersifat unik dan tidak boleh kembar. Penugasan petugas juga disaring secara otomatis sehingga staf yang sedang aktif menjaga loket tertentu tidak akan muncul kembali pada pilihan formulir ini.
            </div>
        </div>

        <div class="p-6 space-y-6">

            <?php if (isset($error_msg)): ?>
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-semibold flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-sm mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="block font-bold uppercase text-[10px] tracking-wider mb-0.5">Proses Gagal</span>
                        <p class="font-medium text-rose-500/90"><?= $error_msg ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-list-ol text-blue-500 text-[10px]"></i> Nomor Loket / Counter <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="nomor_loket" min="1" max="99" placeholder="Contoh: 1" required
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-mono font-bold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Masukkan angka index loket murni (Akan disimpan sebagai integer unik).</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-desktop text-slate-400 text-[10px]"></i> Nama / Identitas Loket <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama_loket" placeholder="Contoh: Loket Pembayaran Utama" required maxlength="50"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Beri nama penanda ruangan loket yang jelas (Maksimal 50 karakter).</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-user-check text-slate-400 text-[10px]"></i> Plotting Staff / Petugas Penjaga
                        </label>
                        <select name="id_petugas" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-bold text-slate-800 transition-all">
                            <option value="">-- Kosongkan Terlebih Dahulu (Bisa Di-set Nanti) --</option>
                            <?php if (!empty($daftar_petugas)): ?>
                                <?php foreach ($daftar_petugas as $staff): ?>
                                    <option value="<?= $staff['id_user'] ?>">
                                        <?= htmlspecialchars($staff['nama_lengkap']) ?> (<?= htmlspecialchars($staff['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Menampilkan staff berwenang yang berstatus bebas tugas (Belum terikat loket manapun).</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-door-closed text-slate-400 text-[10px]"></i> Status Gerbang Awal
                        </label>
                        <select name="status_loket" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-bold text-slate-800 transition-all">
                            <option value="tutup" selected>TUTUP (Standby / Offline)</option>
                            <option value="buka">BUKA (Langsung Siap Melayani)</option>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Status default awal gerbang saat dimasukkan ke dalam database.</small>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="reset" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm">
                        <i class="fas fa-undo mr-1"></i> Bersihkan Form
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-500 text-white hover:bg-blue-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-save text-[10px]"></i> Daftarkan Loket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>