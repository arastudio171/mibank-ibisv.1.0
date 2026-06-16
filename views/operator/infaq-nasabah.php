<?php
// ==========================================
// LOGIKA PHP: MENGAMBIL DATA INFAQ (PERBAIKAN)
// ==========================================

try {
    // Ambil Semua Data Riwayat Infaq
    $query = "
        SELECT 
            ti.*, 
            n.id_nasabah,
            n.nisn, -- <--- KITA TAMBAHKAN KOLOM NISN DI SINI
            n.nama_nasabah, 
            n.kelas,
            j.nama_jurusan,
            u.username as nama_petugas
        FROM tbl_transaksi_infaq ti
        LEFT JOIN tbl_nasabah n ON ti.id_nasabah = n.id_nasabah
        LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
        LEFT JOIN tbl_users u ON ti.id_petugas = u.id_user
        ORDER BY ti.tanggal_infaq DESC
    ";
    $stmt_data = $pdo->query($query);
    $data_infaq = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='p-4 bg-red-50 text-red-600 rounded-xl mb-4 font-bold flex items-center gap-2'><i class='fas fa-exclamation-triangle'></i> Gagal memuat data infaq: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// Fungsi pembantu untuk konversi nama hari ke Bahasa Indonesia
if (!function_exists('getNamaHari')) {
    function getNamaHari($date_string)
    {
        $hari = date('D', strtotime($date_string));
        $daftar_hari = [
            'Sun' => 'Minggu',
            'Mon' => 'Senin',
            'Tue' => 'Selasa',
            'Wed' => 'Rabu',
            'Thu' => 'Kamis',
            'Fri' => 'Jumat',
            'Sat' => 'Sabtu'
        ];
        return $daftar_hari[$hari] ?? $hari;
    }
}
?>

<div class="space-y-6">

    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-5 border-b border-slate-100 bg-slate-50/50">
            <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fas fa-hands-helping text-violet-600"></i> Log Transaksi Infaq Masuk
            </h4>
            <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                <i class="fas fa-info-circle text-slate-400 text-[10px]"></i> Meningkatkan saldo rekening tabungan siswa melalui transaksi infaq.
            </p>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-violet-50/70 border border-violet-100 rounded-xl flex items-start gap-2.5 text-violet-900 text-[11px] leading-relaxed">
            <i class="fas fa-info-circle text-violet-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Informasi Pembukuan:</span> Transaksi infaq bersifat sukarela dan langsung tercatat ke dalam sistem keuangan utama sekolah. Jika kolom nama bertuliskan <span class="font-bold text-slate-800">Hamba Allah (Umum)</span>, berarti transaksi dilakukan tanpa melalui akun tabungan nasabah tertentu.
            </div>
        </div>

        <div class="overflow-x-auto mt-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 w-12 text-center">No.</th>
                        <th class="p-4"><i class="far fa-calendar-alt mr-1"></i> Hari, Tanggal & Waktu</th>
                        <th class="p-4"><i class="fas fa-receipt mr-1"></i> Kode Infaq</th>
                        <th class="p-4"><i class="fas fa-user mr-1"></i> Identitas Donatur</th>
                        <th class="p-4 w-28"><i class="fas fa-tags mr-1"></i> Kategori</th>
                        <th class="p-4"><i class="far fa-comment-alt mr-1"></i> Keterangan</th>
                        <th class="p-4"><i class="fas fa-user-shield mr-1"></i> Petugas</th>
                        <th class="p-4 text-right"><i class="fas fa-money-bill-wave mr-1"></i> Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($data_infaq)): ?>
                        <?php
                        $no = 1;
                        $total_nominal_infaq = 0; // Inisialisasi hitungan total awal
                        foreach ($data_infaq as $item):
                            $total_nominal_infaq += $item['nominal_infaq']; // Tambahkan nominal tiap baris ke total
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400 tabular-nums">
                                    <?= $no++; ?>
                                </td>

                                <td class="p-4 font-medium text-slate-600 whitespace-nowrap">
                                    <div class="flex items-center gap-2 font-bold text-slate-700">
                                        <i class="far fa-calendar text-slate-400"></i>
                                        <?= getNamaHari($item['tanggal_infaq']) . ', ' . date('d M Y', strtotime($item['tanggal_infaq'])) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 ml-5">
                                        <i class="far fa-clock text-slate-400"></i>
                                        Pukul: <?= date('H:i:s', strtotime($item['tanggal_infaq'])) ?> WIB
                                    </div>
                                </td>

                                <td class="p-4 font-mono font-bold text-slate-700">
                                    <?= htmlspecialchars($item['kode_infaq']) ?>
                                </td>

                                <td class="p-4">
                                    <?php if (!empty($item['id_nasabah'])): ?>
                                        <div class="font-bold text-slate-800">
                                            <?= htmlspecialchars($item['nama_nasabah']) ?>
                                        </div>
                                        <div class="text-[10px] text-slate-500 mt-0.5 flex items-center flex-wrap gap-x-2 gap-y-0.5">
                                            <span><i class="fas fa-graduation-cap text-[10px] text-amber-500"></i></span>
                                            <span>NISN: <b><?= htmlspecialchars($item['nisn']) ?></b></span>
                                            <span>|</span>
                                            <span>Kelas: <b><?= htmlspecialchars($item['kelas'] ?? '-') ?></b></span>
                                            <span>|</span>
                                            <span class="text-[#1258ab] font-semibold"><?= htmlspecialchars($item['nama_jurusan'] ?? '-') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="font-bold text-violet-700 flex items-center gap-1">
                                            <i class="fas fa-user-secret text-[10px]"></i> Hamba Allah
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Kategori: Umum / Non-Siswa</div>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4">
                                    <?php if ($item['jenis_infaq'] == 'khusus'): ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-indigo-100 text-indigo-700 font-bold text-[10px] uppercase tracking-wide inline-flex items-center">
                                            <i class="fas fa-star mr-1 text-[8px]"></i> Khusus
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-lg bg-violet-100 text-violet-700 font-bold text-[10px] uppercase tracking-wide inline-flex items-center">
                                            <i class="fas fa-globe mr-1 text-[8px]"></i> Umum
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 max-w-xs truncate text-slate-500 italic" title="<?= htmlspecialchars($item['keterangan'] ?? '-') ?>">
                                    <?= htmlspecialchars($item['keterangan'] ?? '-') ?>
                                </td>

                                <td class="p-4 text-slate-700 font-medium whitespace-nowrap">
                                    <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-mono text-[11px]">
                                        <i class="fas fa-user-edit text-[9px] mr-1"></i><?= htmlspecialchars($item['nama_petugas'] ?? 'System') ?>
                                    </span>
                                </td>

                                <td class="p-4 font-black text-violet-600 text-right tabular-nums text-sm">
                                    Rp <?= number_format($item['nominal_infaq'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="p-12 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-2xl mb-2 opacity-50 text-slate-300"></i>
                                    <p class="font-bold">Belum ada riwayat transaksi infaq masuk.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

                <?php if (!empty($data_infaq)): ?>
                    <tfoot>
                        <tr class="bg-slate-50/80 border-t-2 border-slate-200 text-xs">
                            <td colspan="7" class="p-4 font-black text-slate-700 text-right uppercase">
                                <i class="fas fa-calculator mr-1 text-slate-400"></i> Total Akumulasi Dana Infaq Masuk:
                            </td>
                            <td class="p-4 font-black text-violet-600 text-right tabular-nums text-lg">
                                Rp <?= number_format($total_nominal_infaq, 0, ',', '.') ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>

            </table>
        </div>
    </div>
</div>