<?php
// PERBAIKAN QUERY: Menambahkan JOIN ke tbl_jenis_transaksi dan mengubah t.jenis_transaksi menjadi jt.kode_jenis
$query = "SELECT t.*, n.nama_nasabah, n.nisn, n.kelas, j.nama_jurusan 
          FROM tbl_transaksi t
          JOIN tbl_nasabah n ON t.id_nasabah = n.id_nasabah
          JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi
          LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
          WHERE jt.kode_jenis = 'tarik' 
          AND t.status_approval = 'approved'
          ORDER BY t.tanggal_transaksi DESC";

try {
    $stmt = $pdo->query($query);
    $data_tarik = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// Fungsi konversi hari Indonesia
if (!function_exists('hariIndo')) {
    function hariIndo($tanggal)
    {
        $hari = date("l", strtotime($tanggal));
        $daftar_hari = array(
            'Sunday'    => 'Minggu',
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu'
        );
        return $daftar_hari[$hari] ?? $hari;
    }
}
?>

<div class="space-y-6">
    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-5 border-b border-slate-100 bg-slate-50/50">
            <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fas fa-wallet text-amber-600"></i> Penarikan Tunai (Debet)
            </h4>
            <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                <i class="fas fa-info-circle text-slate-400 text-[10px]"></i> Mengurangi saldo rekening tabungan siswa melalui transaksi penarikan tunai.
            </p>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-amber-50/70 border border-amber-100 rounded-xl flex items-start gap-2.5 text-amber-800 text-[11px] leading-relaxed">
            <i class="fas fa-shield-alt text-amber-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Log Buku Besar (Debet):</span> Seluruh transaksi di bawah ini berstatus <span class="bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-bold text-[10px]">APPROVED</span>. Dana keluar telah berhasil divalidasi dan memotong saldo aktif masing-masing rekening siswa secara *real-time*.
            </div>
        </div>

        <div class="overflow-x-auto mt-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 w-12 text-center">No.</th>
                        <th class="p-4"><i class="far fa-calendar-alt mr-1"></i> Hari, Tanggal & Waktu</th>
                        <th class="p-4"><i class="fas fa-receipt mr-1"></i> Kode & Ref Bank</th>
                        <th class="p-4"><i class="fas fa-user-graduate mr-1"></i> Identitas Nasabah</th>
                        <th class="p-4"><i class="fas fa-toggle-on mr-1"></i> Status</th>
                        <th class="p-4 text-right"><i class="fas fa-arrow-down mr-1"></i> Nominal Penarikan</th>
                        <th class="p-4 text-right"><i class="fas fa-percentage mr-1"></i> Biaya Admin</th>
                        <th class="p-4 text-right"><i class="fas fa-coins mr-1"></i> Total Diterima (Netto)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php
                    $total_bersih_seluruh = 0;
                    if (!empty($data_tarik)):
                        $no = 1;
                        foreach ($data_tarik as $item):
                            $nominal_bersih = $item['jumlah'] - $item['biaya_admin'];
                            $total_bersih_seluruh += $nominal_bersih;
                    ?>
                            <tr class="hover:bg-amber-50/30 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400"><?= $no++ ?></td>

                                <td class="p-4 font-medium text-slate-600 whitespace-nowrap">
                                    <div class="flex items-center gap-2 font-bold text-slate-700">
                                        <i class="far fa-calendar text-slate-400"></i> <?= hariIndo($item['tanggal_transaksi']) ?>, <?= date('d M Y', strtotime($item['tanggal_transaksi'])) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 ml-5">
                                        <i class="far fa-clock text-slate-400"></i> <?= date('H:i:s', strtotime($item['tanggal_transaksi'])) ?> WIB
                                    </div>
                                </td>

                                <td class="p-4 font-mono font-bold text-slate-700">
                                    <a href="modules/transaksi/unduh-struk.php?id=<?= urlencode($item['id_transaksi']) ?>" target="_blank" class="text-[#1258ab] hover:underline flex items-center gap-1 mb-0.5">
                                        <i class="fas fa-print text-[10px]"></i> <?= htmlspecialchars($item['kode_transaksi']) ?>
                                    </a>
                                    <div class="text-[10px] text-slate-400 font-sans font-normal flex items-center gap-1">
                                        <i class="fas fa-university text-[9px] text-slate-400"></i> Ref: <span class="text-slate-600 font-mono font-semibold"><?= htmlspecialchars($item['nomor_referensi_bank'] ?? '-') ?></span>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800">
                                        <?= htmlspecialchars($item['nama_nasabah']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5 flex items-center flex-wrap gap-x-2 gap-y-0.5">
                                        <span><i class="fas fa-graduation-cap text-[10px] text-amber-500"></i></span>
                                        <span>NISN: <b><?= htmlspecialchars($item['nisn']) ?></b></span>
                                        <span class="text-slate-300">|</span>
                                        <span>Kelas: <b><?= htmlspecialchars($item['kelas'] ?? '-') ?></b></span>
                                        <span class="text-slate-300">|</span>
                                        <span>Jurusan: <b class="text-[#1258ab]"><?= htmlspecialchars($item['nama_jurusan'] ?? '-') ?></b></span>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <span class="px-2 py-1 rounded-lg bg-amber-100 text-amber-700 font-black text-[10px] uppercase tracking-wide inline-flex items-center">
                                        <i class="fas fa-arrow-down mr-1 text-[8px]"></i> Debet
                                    </span>
                                </td>

                                <td class="p-4 text-right font-bold text-slate-600 tabular-nums">Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>

                                <td class="p-4 text-right font-bold text-slate-500 tabular-nums">Rp <?= number_format($item['biaya_admin'], 0, ',', '.') ?></td>

                                <td class="p-4 text-right font-black text-amber-600 tabular-nums">- Rp <?= number_format($nominal_bersih, 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="8" class="p-12 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-2xl mb-2 opacity-50 text-slate-300"></i>
                                    <p class="font-bold">Belum ada data penarikan yang tercatat.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50/80 border-t-2 border-slate-200 text-xs">
                        <td colspan="7" class="p-4 font-black text-slate-700 text-right uppercase">
                            <i class="fas fa-calculator mr-1 text-slate-400"></i> Total Penarikan Bersih (Semua Riwayat):
                        </td>
                        <td class="p-4 font-black text-amber-600 text-right tabular-nums text-lg">
                            Rp <?= number_format($total_bersih_seluruh, 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>