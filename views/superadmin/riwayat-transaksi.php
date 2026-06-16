<?php

/**
 * FILE: views/superadmin/nasabah/riwayat-kelas.php
 * DESKRIPSI: Laporan Riwayat Kelas dengan Clean Filter Routing (Bebas Error 403)
 * CATATAN: Menggunakan variabel $pdo yang otomatis aktif dari main.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. VALIDASI AKSES
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';
if (!in_array($user_role, ['admin', 'superadmin'])) {
    die("<div class='p-4 text-rose-600 bg-rose-50 rounded-xl font-bold text-xs'>🚨 Akses Ditolak! Anda tidak memiliki hak akses data ini.</div>");
}

// 2. PROSES AMBIL DATA FILTER (UNTUK DROPDOWN)
$list_tahun = [];
$list_jurusan = [];
try {
    $list_tahun = $pdo->query("SELECT * FROM tbl_tahun_ajaran ORDER BY tahun_ajaran DESC")->fetchAll(PDO::FETCH_ASSOC);
    $list_jurusan = $pdo->query("SELECT * FROM tbl_jurusan ORDER BY nama_jurusan ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Filter Error: " . $e->getMessage());
}

// 3. MENANGKAP PARAMETER FILTER (KEMBALI KE GET NAMUN DENGAN SANITASI AMAN)
$filter_tahun   = isset($_GET['f_tahun']) ? $_GET['f_tahun'] : '';
$filter_kelas   = isset($_GET['f_kelas']) ? $_GET['f_kelas'] : '';
$filter_jurusan = isset($_GET['f_jurusan']) ? $_GET['f_jurusan'] : '';
$search_keyword = isset($_GET['f_search']) ? trim($_GET['f_search']) : '';

// 4. MEMBUAT QUERY DINAMIS DENGAN FILTER
$query_base = "SELECT r.*, n.nama_nasabah, n.nisn, t.tahun_ajaran, j.kode_jurusan, j.nama_jurusan 
               FROM tbl_riwayat_kelas r
               JOIN tbl_nasabah n ON r.id_nasabah = n.id_nasabah
               JOIN tbl_tahun_ajaran t ON r.id_tahun_ajaran = t.id_tahun_ajaran
               JOIN tbl_jurusan j ON r.id_jurusan_saat_itu = j.id_jurusan
               WHERE 1=1";

$params = [];

if (!empty($filter_tahun)) {
    $query_base .= " AND r.id_tahun_ajaran = :id_tahun";
    $params['id_tahun'] = $filter_tahun;
}
if (!empty($filter_kelas)) {
    $query_base .= " AND r.kelas_saat_itu = :kelas";
    $params['kelas'] = $filter_kelas;
}
if (!empty($filter_jurusan)) {
    $query_base .= " AND r.id_jurusan_saat_itu = :id_jurusan";
    $params['id_jurusan'] = $filter_jurusan;
}
if (!empty($search_keyword)) {
    // Menggunakan pembersihan spasi ganda untuk mencegah deteksi false-positive sql injection oleh Apache
    $clean_search = str_replace(array("'", '"', ';', '--'), '', $search_keyword);
    $query_base .= " AND (n.nama_nasabah LIKE :search OR n.nisn LIKE :search)";
    $params['search'] = "%" . $clean_search . "%";
}

$query_base .= " ORDER BY t.tahun_ajaran DESC, r.kelas_saat_itu ASC, n.nama_nasabah ASC";

$riwayat_data = [];
$grand_total_saldo = 0;

try {
    $stmt = $pdo->prepare($query_base);
    $stmt->execute($params);
    $riwayat_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class='p-4 text-rose-600 bg-rose-50 rounded-xl font-bold text-xs'>Error Query: " . $e->getMessage() . "</div>");
}

// Hitung total saldo untuk bagian footer summary
foreach ($riwayat_data as $row) {
    $grand_total_saldo += $row['saldo_akhir_tahun'];
}
?>

<div class="space-y-6 w-full">

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 border border-blue-100/50 shadow-sm flex-shrink-0">
                <i class="fas fa-history text-xs"></i>
            </div>
            <div>
                <h1 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Arsip Riwayat Kelas & Saldo Tahunan</h1>
                <p class="text-[10px] text-slate-400 font-medium">Laporan penutupan buku historis data kelas, jurusan, serta saldo mengendap siswa per tahun ajaran.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded[1rem] border border-slate-100 shadow-sm p-4">
        <form method="GET" action="main.php" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 text-xs">
            <input type="hidden" name="page" value="riwayat-kelas">

            <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-600">Cari Siswa</label>
                <input type="text" name="f_search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="Nama / NISN..."
                    class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 font-medium transition-all">
            </div>

            <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-600">Tahun Ajaran</label>
                <select name="f_tahun" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 font-semibold text-slate-700 transition-all">
                    <option value="">-- Semua Tahun --</option>
                    <?php foreach ($list_tahun as $ta): ?>
                        <option value="<?= $ta['id_tahun_ajaran'] ?>" <?= $filter_tahun == $ta['id_tahun_ajaran'] ? 'selected' : '' ?>>
                            <?= $ta['tahun_ajaran'] ?> <?= $ta['status_aktif'] === 'aktif' ? '(Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-600">Tingkat Kelas</label>
                <select name="f_kelas" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 font-semibold text-slate-700 transition-all">
                    <option value="">-- Semua Kelas --</option>
                    <option value="X" <?= $filter_kelas === 'X' ? 'selected' : '' ?>>Kelas X</option>
                    <option value="XI" <?= $filter_kelas === 'XI' ? 'selected' : '' ?>>Kelas XI</option>
                    <option value="XII" <?= $filter_kelas === 'XII' ? 'selected' : '' ?>>Kelas XII</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="font-bold text-slate-600">Kompetensi Keahlian</label>
                <select name="f_jurusan" class="w-full px-3 py-2 rounded-xl bg-slate-50 border border-slate-200/80 focus:outline-none focus:border-blue-500 font-semibold text-slate-700 transition-all">
                    <option value="">-- Semua Jurusan --</option>
                    <?php foreach ($list_jurusan as $jr): ?>
                        <option value="<?= $jr['id_jurusan'] ?>" <?= $filter_jurusan == $jr['id_jurusan'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($jr['nama_jurusan']) ?> (<?= $jr['kode_jurusan'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 rounded-xl bg-blue-500 hover:bg-blue-600 text-white font-bold text-center shadow-sm transition-all">
                    <i class="fas fa-filter mr-1 text-[10px]"></i> Saring
                </button>
                <a href="main.php?page=riwayat-kelas" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-center font-bold shadow-sm transition-all" title="Reset Filter">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/40">
                        <th class="p-4 text-center w-12">No</th>
                        <th class="p-4 w-32">NISN</th>
                        <th class="p-4">Nama Lengkap Siswa</th>
                        <th class="p-4 text-center">Identitas Kelas</th>
                        <th class="p-4">Program Keahlian / Jurusan</th>
                        <th class="p-4 text-center">Tahun Ajaran</th>
                        <th class="p-4 text-right">Total Saldo Pertahun</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($riwayat_data)): ?>
                        <?php $no = 1;
                        foreach ($riwayat_data as $row): ?>
                            <tr class="hover:bg-slate-50/40 transition-colors">
                                <td class="p-4 text-center font-medium text-slate-400 font-mono"><?= $no++ ?></td>
                                <td class="p-4 font-mono font-bold text-slate-700"><?= htmlspecialchars($row['nisn']) ?></td>
                                <td class="p-4 font-bold text-slate-800"><?= htmlspecialchars($row['nama_nasabah']) ?></td>
                                <td class="p-4 text-center font-bold">
                                    <span class="px-2 py-0.5 bg-blue-50 border border-blue-100 text-blue-600 rounded-md font-mono text-[11px]">
                                        Kelas <?= htmlspecialchars($row['kelas_saat_itu']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($row['nama_jurusan']) ?></span>
                                    <span class="text-[10px] font-mono text-slate-400 block">ID KODE: <?= $row['kode_jurusan'] ?></span>
                                </td>
                                <td class="p-4 text-center font-mono font-bold text-slate-500">
                                    <?= htmlspecialchars($row['tahun_ajaran']) ?>
                                </td>
                                <td class="p-4 text-right font-mono font-black text-slate-900 text-[13px]">
                                    Rp <?= number_format($row['saldo_akhir_tahun'], 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-12 text-center text-slate-400">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <i class="fas fa-folder-open text-3xl text-slate-200"></i>
                                    <p class="text-xs font-bold text-slate-500">Arsip Tidak Ditemukan</p>
                                    <p class="text-[10px] text-slate-400 leading-relaxed">Data riwayat kelas untuk parameter filter di atas belum dibukukan atau kosong.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

                <?php if (!empty($riwayat_data)): ?>
                    <tfoot class="bg-slate-50/80 border-t-2 border-slate-200/80 text-xs font-bold text-slate-700">
                        <tr>
                            <td colspan="6" class="p-4 text-right uppercase tracking-wider text-[10px] text-slate-400 font-extrabold">
                                Total Buku Tabungan Terbuku (Kapitalis):
                            </td>
                            <td class="p-4 text-right font-mono font-black text-sm text-[#1257aa]">
                                Rp <?= number_format($grand_total_saldo, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>