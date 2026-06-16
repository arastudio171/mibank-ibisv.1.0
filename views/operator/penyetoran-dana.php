<?php
// Query mengambil riwayat setoran tunai beserta identitas nasabah dan nama jurusannya
$query = "SELECT t.*, n.nama_nasabah, n.nisn, n.kelas, j.nama_jurusan 
          FROM tbl_transaksi t
          JOIN tbl_nasabah n ON t.id_nasabah = n.id_nasabah
          JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi
          LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
          WHERE jt.kode_jenis = 'setor' 
          AND t.status_approval = 'approved'
          ORDER BY t.tanggal_transaksi DESC";

try {
    $stmt = $pdo->query($query);
    $data_setor = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_setor = array_sum(array_column($data_setor, 'jumlah'));
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
                <i class="fas fa-wallet text-emerald-600"></i> Penyetoran Tunai (Kredit)
            </h4>
            <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                <i class="fas fa-info-circle text-slate-400 text-[10px]"></i> Menambahkan saldo rekening tabungan siswa melalui transaksi setoran tunai.
            </p>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-green-50/70 border border-green-100 rounded-xl flex items-start gap-2.5 text-green-800 text-[11px] leading-relaxed">
            <i class="fas fa-shield-alt text-green-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Log Buku Besar (Kredit):</span> Seluruh transaksi di bawah ini berstatus <span class="bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded font-bold text-[10px]">APPROVED</span>. Dana masuk telah berhasil divalidasi dan langsung dibukukan ke saldo aktif masing-masing rekening siswa.
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
                        <th class="p-4 text-right"><i class="fas fa-coins mr-1"></i> Nominal Masuk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($data_setor)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($data_setor as $item): ?>
                            <tr class="hover:bg-emerald-50/30 transition-colors">
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
                                    <div class="font-bold text-slate-800 flex items-center gap-1.5">
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
                                    <span class="px-2 py-1 rounded-lg bg-emerald-100 text-emerald-700 font-black text-[10px] uppercase tracking-wide inline-flex items-center">
                                        <i class="fas fa-arrow-up mr-1 text-[8px]"></i> Kredit
                                    </span>
                                </td>

                                <td class="p-4 text-right font-black text-emerald-600 tabular-nums">+ Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="p-12 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-2xl mb-2 opacity-50 text-slate-300"></i>
                                    <p class="font-bold">Belum ada data penyetoran yang tercatat.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50/80 border-t-2 border-slate-200 text-xs">
                        <td colspan="5" class="p-4 font-black text-slate-700 text-right uppercase">
                            <i class="fas fa-calculator mr-1 text-slate-400"></i> Total Penyetoran Bersih (Semua Riwayat):
                        </td>
                        <td class="p-4 font-black text-emerald-600 text-right tabular-nums text-lg">
                            Rp <?= number_format($total_setor, 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>