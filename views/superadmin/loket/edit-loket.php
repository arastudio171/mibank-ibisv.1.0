<?php

/**
 * FILE: views/superadmin/loket/edit-loket.php
 * DESKRIPSI: Halaman ubah konfigurasi data loket operasional dengan tema warna biru (Blue).
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

// 2. AMBIL DATA LOKET YANG AKAN DI-EDIT
$id_loket = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$loket = null;

if ($id_loket <= 0) {
    header("Location: main.php?page=loket&msg=ID Loket tidak valid!&type=error");
    exit();
}

try {
    $sql_get = "SELECT * FROM tbl_loket WHERE id_loket = :id_loket";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute(['id_loket' => $id_loket]);
    $loket = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$loket) {
        header("Location: main.php?page=loket&msg=Data loket tidak ditemukan di sistem!&type=error");
        exit();
    }
} catch (PDOException $e) {
    error_log("Fetch Loket Data Error: " . $e->getMessage());
    die("Koneksi gagal atau terjadi kendala query internal.");
}

// 3. LOGIKA AMBIL DATA PETUGAS (KASIR/STAFF) UNTUK DROPDOWN
// Mengambil user yang BELUM menjaga loket manapun ATAU user yang SEDANG menjaga loket ini saat ini.
$daftar_petugas = [];
try {
    $sql_petugas = "SELECT id_user, username, nama_lengkap 
                    FROM tbl_users 
                    WHERE LOWER(role) IN ('admin', 'teller', 'petugas', 'staff')
                    AND (
                        id_user NOT IN (SELECT id_petugas FROM tbl_loket WHERE id_petugas IS NOT NULL)
                        OR id_user = :current_petugas
                    )
                    ORDER BY nama_lengkap ASC";
    $stmt_petugas = $pdo->prepare($sql_petugas);
    $stmt_petugas->execute(['current_petugas' => $loket['id_petugas']]);
    $daftar_petugas = $stmt_petugas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Master Petugas Error: " . $e->getMessage());
}

// 4. LOGIKA PROSES UPDATE DATA (POST)
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
            // Jalankan perintah Update Data
            $sql_update = "UPDATE tbl_loket SET 
                            nomor_loket = :nomor_loket, 
                            nama_loket = :nama_loket, 
                            id_petugas = :id_petugas, 
                            status_loket = :status_loket 
                           WHERE id_loket = :id_loket";

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                'nomor_loket'  => $nomor_loket,
                'nama_loket'   => $nama_loket,
                'id_petugas'   => $id_petugas,
                'status_loket' => $status_loket,
                'id_loket'     => $id_loket
            ]);

            // Catat log jika fungsi log global aktif
            if (function_exists('writeLog')) {
                writeLog($pdo, "✏️ EDIT LOKET: Berhasil memperbarui data loket '$nama_loket' (ID: $id_loket).");
            }

            $pesan_sukses = "Perubahan data konfigurasi $nama_loket berhasil disimpan.";
            header("Location: main.php?page=loket&msg=" . urlencode($pesan_sukses) . "&type=success");
            exit();
        } catch (PDOException $e) {
            error_log("Update Loket Error: " . $e->getMessage());

            // Deteksi Error Duplikat Data (Unique Key Constraint)
            if ($e->getCode() == 23000) {
                if (strpos($e->getMessage(), 'nomor_loket') !== false) {
                    $error_msg = "Gagal simpan! Nomor loket [$nomor_loket] sudah digunakan oleh loket lain.";
                } elseif (strpos($e->getMessage(), 'id_petugas') !== false) {
                    $error_msg = "Gagal simpan! Petugas tersebut sudah aktif di gerbang loket lain.";
                } else {
                    $error_msg = "Gagal simpan! Terjadi bentrokan aturan data unik pada sistem database.";
                }
            } else {
                $error_msg = "Gagal memperbarui data loket karena kendala sistem basis data internal.";
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
                    <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Ubah Konfigurasi Sistem Loket</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Modifikasi pemetaan nomor gerbang dan penugasan kasir utama.</p>
                </div>
            </div>
            <a href="main.php?page=loket" class="w-full md:w-auto inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-500 hover:bg-slate-50 transition-all font-bold text-[10px] border border-slate-200/60 shadow-sm whitespace-nowrap">
                <i class="fas fa-arrow-left text-[9px]"></i> Kembali ke Tabel
            </a>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-800 text-[11px] leading-relaxed">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Mode Pengeditan Aktif:</span> Anda sedang mengubah data aset loket dengan ID sistem: <span class="font-mono bg-blue-100 text-blue-900 px-1 rounded font-bold"><?= $loket['id_loket'] ?></span>. Jika status diubah menjadi tutup, petugas loket terkait secara otomatis kehilangan hak panggil nomor urut antrean pada aplikasi client.
            </div>
        </div>

        <div class="p-6 space-y-6">

            <?php if (isset($error_msg)): ?>
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-semibold flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-sm mt-0.5 flex-shrink-0"></i>
                    <div>
                        <span class="block font-bold uppercase text-[10px] tracking-wider mb-0.5">Penyimpanan Gagal</span>
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
                            value="<?= htmlspecialchars($loket['nomor_loket']) ?>"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-mono font-bold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Ubah nomor indeks gerbang loket layanan utama.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-desktop text-slate-400 text-[10px]"></i> Nama / Identitas Loket <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="nama_loket" placeholder="Contoh: Loket Pembayaran Utama" required maxlength="50"
                            value="<?= htmlspecialchars($loket['nama_loket']) ?>"
                            class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-semibold text-slate-800 transition-all">
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Nama deskripsi penanda loket (Maksimal 50 karakter).</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-user-check text-slate-400 text-[10px]"></i> Plotting Staff / Petugas Penjaga
                        </label>
                        <select name="id_petugas" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-bold text-slate-800 transition-all">
                            <option value="">-- Bebas Tugas (Kosongkan Penjaga) --</option>
                            <?php if (!empty($daftar_petugas)): ?>
                                <?php foreach ($daftar_petugas as $staff): ?>
                                    <?php $selected = ($staff['id_user'] == $loket['id_petugas']) ? 'selected' : ''; ?>
                                    <option value="<?= $staff['id_user'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($staff['nama_lengkap']) ?> (<?= htmlspecialchars($staff['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Petugas lama tetap terpilih, staff yang menjaga loket lain disembunyikan otomatis.</small>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-bold text-slate-700 flex items-center gap-1">
                            <i class="fas fa-door-closed text-slate-400 text-[10px]"></i> Status Operasional Gerbang
                        </label>
                        <select name="status_loket" class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 font-bold text-slate-800 transition-all">
                            <option value="tutup" <?= $loket['status_loket'] === 'tutup' ? 'selected' : '' ?>>TUTUP (Standby / Offline)</option>
                            <option value="buka" <?= $loket['status_loket'] === 'buka' ? 'selected' : '' ?>>BUKA (Aktif Melayani Antrean)</option>
                        </select>
                        <small class="text-[10px] text-slate-400 font-medium leading-relaxed">Atur status kesiapan pemanggilan sistem antrean loket perbankan.</small>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <a href="main.php?page=loket" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold shadow-sm">
                        <i class="fas fa-times mr-1"></i> Batalkan Perubahan
                    </a>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-blue-500 text-white hover:bg-blue-600 transition-all text-[11px] font-bold shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-save text-[10px]"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>