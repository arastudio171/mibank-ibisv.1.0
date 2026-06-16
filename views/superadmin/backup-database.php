<?php

/**
 * FILE: views/superadmin/backup.php
 * DESKRIPSI: Manajemen Backup Database (Card Aksi & Tabel Riwayat File) - w-full
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔍 KONEKSI DATABASE: Menyesuaikan jalur 2 tingkat dari folder superadmin
require_once __DIR__ . '/../../auth/database.php';

// 🔒 VALIDASI HAK AKSES: Hanya Admin yang bisa melakukan manajemen basis data
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>window.location.href = 'auth/auth-login.php?msg=Akses terbatas!&type=error';</script>";
    exit();
}

// Tentukan direktori penyimpanan file backup (Lokasi: assets/backups/)
$backup_dir = __DIR__ . '/../../views/superadmin/mysqli/';

// Buat folder otomatis jika belum ada di dalam project
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// ==========================================
// 🚨 [PROSES UTAMA] KONDISI 2: DOWNLOAD FILE BACKUP
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'download' && !empty($_GET['file'])) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    $file = basename($_GET['file']);
    $target_file = $backup_dir . $file;

    if (file_exists($target_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($target_file));

        session_write_close();
        readfile($target_file);
        exit();
    } else {
        echo "<script>window.location.href = '?page=backup&msg=File tidak ditemukan di server!&type=error';</script>";
        exit();
    }
}

// ==========================================
// KONDISI 1: PROSES GENERATE BACKUP (EKSEKUSI)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'generate') {
    try {
        $db_name_query = $pdo->query("SELECT DATABASE()");
        $db_name = $db_name_query->fetchColumn();

        $filename = 'backup_' . $db_name . '_' . date('Y-m-d_H-i-s') . '.sql';
        $file_path = $backup_dir . $filename;

        $handle = fopen($file_path, 'w+');
        fwrite($handle, "-- IBIS (Internet Banking Sekolah) Database Dump\n");
        fwrite($handle, "-- Waktu Backup: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: `" . $db_name . "`\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables_stmt = $pdo->query("SHOW TABLES");
        $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $create_stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $create_row = $create_stmt->fetch(PDO::FETCH_ASSOC);

            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $create_row['Create Table'] . ";\n\n");

            $data_stmt = $pdo->query("SELECT * FROM `$table`");
            while ($row = $data_stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_keys($row);
                $escaped_values = [];

                foreach ($row as $value) {
                    if ($value === null) {
                        $escaped_values[] = 'NULL';
                    } else {
                        $escaped_values[] = $pdo->quote($value);
                    }
                }

                $sql_insert = "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) VALUES (" . implode(", ", $escaped_values) . ");\n";
                fwrite($handle, $sql_insert);
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        echo "<script>window.location.href = '?page=backup&msg=Database berhasil dibackup dengan nama file: $filename&type=success';</script>";
        exit();
    } catch (Exception $e) {
        echo "<script>window.location.href = '?page=backup&msg=Gagal melakukan backup: " . urlencode($e->getMessage()) . "&type=error';</script>";
        exit();
    }
}

// ==========================================
// KONDISI 3: PROSES HAPUS FILE BACKUP
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $target_file = $backup_dir . $file;

    if (file_exists($target_file)) {
        unlink($target_file);
        echo "<script>window.location.href = '?page=backup&msg=File backup $file berhasil dihapus dari storage.&type=success';</script>";
        exit();
    } else {
        echo "<script>window.location.href = '?page=backup&msg=Gagal menghapus, file tidak eksis.&type=error';</script>";
        exit();
    }
}

// ==========================================
// MEMBACA DAFTAR FILE BACKUP DI FOLDER
// ==========================================
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';
$backup_files = [];
$total_size_kb = 0;

if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_absolute = $backup_dir . $file;
            $raw_size = filesize($file_absolute);
            $size_kb = round($raw_size / 1024, 2);
            $total_size_kb += $size_kb;

            $backup_files[] = [
                'name' => $file,
                'date' => date('Y-m-d H:i:s', filemtime($file_absolute)),
                'size' => $size_kb . ' KB'
            ];
        }
    }
    usort($backup_files, function ($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

$total_files = count($backup_files);
$footer_total_display = ($total_size_kb >= 1024) ? round($total_size_kb / 1024, 2) . ' MB' : $total_size_kb . ' KB';
?>

<div class="w-full space-y-6 text-xs animate-fade-in">

    <div class="w-full bg-white rounded-[1rem] border border-slate-100 shadow-sm p-6 sm:p-8">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
            <div class="flex items-start gap-4 max-w-2xl">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100/70 flex items-center justify-center text-indigo-500 shrink-0 shadow-sm">
                    <i class="fas fa-database text-lg"></i>
                </div>
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-sm font-black uppercase tracking-wider text-slate-800">Utility Backup Database</h1>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold text-[8px] uppercase tracking-widest bg-amber-50 text-amber-700 border border-amber-100/60">
                            <i class="fas fa-shield-alt text-[8px]"></i> Rekomendasi Keamanan
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium leading-relaxed">
                        Proses pencadangan ini akan membaca skema DDL relasi <span class="text-indigo-600 font-mono font-bold">foreign key</span> tabel perbankan secara aman tanpa mengganggu antrean operasional transaksi aktif nasabah di server.
                    </p>
                </div>
            </div>
            <a href="?page=backup&action=generate" class="w-full lg:w-auto px-6 py-3.5 rounded-xl bg-[#1257aa] hover:bg-[#0e468a] text-white font-bold text-center shadow-md shadow-blue-100 transition-all flex items-center justify-center gap-2.5 whitespace-nowrap text-xs shrink-0 cursor-pointer">
                <i class="fas fa-sync-alt text-[10px]"></i> Backup Sekarang (.SQL)
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="w-full p-4 rounded-2xl font-bold flex items-center gap-3.5 shadow-sm border <?= $type === 'success' ? 'bg-emerald-50 border-emerald-200/60 text-emerald-800' : 'bg-rose-50 border-rose-200/60 text-rose-800' ?>">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 <?= $type === 'success' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600' ?>">
                <i class="fas <?= $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> text-base"></i>
            </div>
            <span class="leading-relaxed"><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <div>
                <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fas fa-history text-indigo-500"></i> Riwayat Manajemen Backup
                </h4>
                <p class="text-[11px] text-slate-400">Daftar berkas cadangan database yang tersimpan dalam direktori lokal server.</p>
            </div>
            <div class="px-3 py-1 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-lg text-[9px] font-bold flex items-center gap-2">
                <i class="fas fa-server"></i> <?= $total_files ?> Backup Files
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 text-center w-14">No.</th>
                        <th class="p-4">Info Dokumen SQL</th>
                        <th class="p-4">Spesifikasi Server</th>
                        <th class="p-4 text-right pr-8">Ukuran Berkas</th>
                        <th class="p-4 text-center w-48">Aksi Kontrol</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($backup_files)): $no = 1;
                        foreach ($backup_files as $file): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400 font-mono"><?= $no++ ?></td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <i class="fas fa-file-code text-indigo-400/80"></i>
                                        <span class="truncate max-w-xs md:max-w-md font-mono text-[11px]" title="<?= htmlspecialchars($file['name']) ?>">
                                            <?= htmlspecialchars($file['name']) ?>
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-1">
                                        <i class="far fa-calendar-alt mr-1"></i> <?= date('d M Y, H:i', strtotime($file['date'])) ?>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="text-slate-700 font-bold mb-0.5 uppercase tracking-wide text-[10px]">MySQL Dump Storage</div>
                                    <div class="text-[10px] text-slate-400 font-mono">
                                        <i class="fas fa-folder mr-1 text-slate-300"></i> views/superadmin/mysqli/
                                    </div>
                                </td>
                                <td class="p-4 text-right pr-8 font-mono font-bold text-slate-700 tabular-nums">
                                    <?= $file['size'] ?>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex gap-2 justify-center">
                                        <a href="?page=backup&action=download&file=<?= urlencode($file['name']) ?>"
                                            class="px-3 py-1.5 rounded-lg text-[9px] font-bold bg-emerald-500 hover:bg-emerald-600 text-white transition-all flex items-center gap-1.5 shadow-sm shadow-emerald-100">
                                            <i class="fas fa-download"></i> UNDUH
                                        </a>
                                        <a href="?page=backup&action=delete&file=<?= urlencode($file['name']) ?>"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus permanen berkas backup ini dari penyimpanan server?')"
                                            class="px-3 py-1.5 rounded-lg text-[9px] font-bold bg-rose-500 hover:bg-rose-600 text-white transition-all flex items-center gap-1.5 shadow-sm shadow-rose-100">
                                            <i class="fas fa-trash-alt"></i> HAPUS
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center text-slate-400 bg-slate-50/10">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-300">
                                        <i class="fas fa-folder-open text-base"></i>
                                    </div>
                                    <span class="font-medium text-[11px]">Belum ada riwayat berkas data backup yang dibuat di server.</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-slate-50 text-[10px] text-slate-400 border-t border-slate-100 flex justify-between items-center">
            <span><i class="fas fa-info-circle text-indigo-500 mr-1"></i> Disarankan untuk rutin menghapus berkas lama agar menjaga kapasitas sisa ruang penyimpanan bank sekolah.</span>
            <span class="font-medium text-slate-500">Total Size: <strong class="text-indigo-600 font-mono"><?= $footer_total_display ?></strong></span>
        </div>
    </div>

</div>