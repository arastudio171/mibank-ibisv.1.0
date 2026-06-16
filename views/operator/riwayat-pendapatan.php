<?php
// Pastikan file ini dipanggil melalui main.php sehingga koneksi $pdo sudah tersedia

try {
    // 1. QUERY FILTERED: Ditambahkan pengambilan data kelas & jurusan nasabah
    $query = "
        SELECT 
            t.*, 
            n.nama_nasabah, 
            n.nisn, 
            n.kelas, -- <--- KITA AMBIL KOLOM KELAS
            j.nama_jurusan, -- <--- KITA AMBIL NAMA JURUSAN
            jt.kode_jenis, 
            jt.nama_jenis
        FROM tbl_transaksi t
        JOIN tbl_nasabah n ON t.id_nasabah = n.id_nasabah
        LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan -- <--- KITA JOIN KE TABEL JURUSAN
        JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi
        WHERE jt.kode_jenis IN ('tarik', 'transfer') 
        AND t.status_approval = 'approved' 
        AND t.biaya_admin > 0
        ORDER BY t.tanggal_transaksi DESC
    ";

    $stmt_detail = $pdo->query($query);
    $riwayat_admin = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

    // 2. HITUNG TOTAL PENDAPATAN OPERASIONAL (ADMIN TARIK + FEE TRANSFER)
    $total_pendapatan_hari_ini = 0;
    foreach ($riwayat_admin as $row) {
        $total_pendapatan_hari_ini += $row['biaya_admin'];
    }
} catch (PDOException $e) {
    echo "<div class='p-4 bg-red-50 text-red-600 rounded-xl mb-4 font-bold'>Gagal memuat data keuangan: " . htmlspecialchars($e->getMessage()) . "</div>";
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
                <i class="fas fa-chart-line text-indigo-500"></i> Log Aliran Pendapatan (Fee Transaksi)
            </h4>
            <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                <i class="fas fa-info-circle text-slate-400 text-[10px]"></i> Akumulasi profit operasional yang bersumber khusus dari biaya penarikan tunai dan transfer siswa.
            </p>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-indigo-50/60 border border-indigo-100 rounded-xl flex items-start gap-2.5 text-indigo-900 text-[11px] leading-relaxed">
            <i class="fas fa-check-circle text-indigo-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Regulasi Biaya Aplikasi:</span> Transaksi <span class="font-bold text-amber-700">Setor Tunai</span> diatur <b>Gratis (Rp 0)</b>. Pendapatan laba buku besar di bawah ini murni dikumpulkan dari potongan mandiri pada menu <span class="font-bold text-indigo-700">Tarik Tunai</span> dan tarif nominal <span class="font-bold text-slate-600">Transfer Antar Siswa</span> secara presisi.
            </div>
        </div>

        <div class="overflow-x-auto mt-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 w-12 text-center">No.</th>
                        <th class="p-4"><i class="far fa-calendar-alt mr-1"></i> Hari, Tanggal & Waktu</th>
                        <th class="p-4"><i class="fas fa-receipt mr-1"></i> Kode Transaksi</th>
                        <th class="p-4"><i class="fas fa-user-graduate mr-1"></i> Nasabah / Siswa</th>
                        <th class="p-4"><i class="fas fa-exchange-alt mr-1"></i> Jenis Log Fee</th>
                        <th class="p-4"><i class="fas fa-money-bill-wave mr-1"></i> Nominal Utama</th>
                        <th class="p-4 text-right"><i class="fas fa-coins mr-1"></i> Keuntungan Bersih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($riwayat_admin)): ?>
                        <?php
                        $no = 1;
                        foreach ($riwayat_admin as $item):
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400 tabular-nums">
                                    <?= $no++; ?>
                                </td>

                                <td class="p-4 font-medium text-slate-600 whitespace-nowrap">
                                    <div class="flex items-center gap-2 font-bold text-slate-700">
                                        <i class="far fa-calendar text-slate-400"></i> <?= getNamaHari($item['tanggal_transaksi']) . ', ' . date('d M Y', strtotime($item['tanggal_transaksi'])) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 ml-5">
                                        <i class="far fa-clock text-slate-400"></i> <?= date('H:i:s', strtotime($item['tanggal_transaksi'])) ?> WIB
                                    </div>
                                </td>

                                <td class="p-4 font-mono">
                                    <a href="modules/transaksi/unduh-struk.php?id=<?= urlencode($item['id_transaksi']) ?>" target="_blank" class="text-[#1258ab] hover:text-indigo-700 font-bold hover:underline inline-flex items-center gap-1">
                                        <i class="fas fa-print text-[10px] opacity-75"></i> <?= htmlspecialchars($item['kode_transaksi']) ?>
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
                                    <?php
                                    $badge_class = 'bg-slate-100 text-slate-700';
                                    $icon_jenis = 'fa-arrow-right';

                                    if ($item['kode_jenis'] == 'tarik') {
                                        $badge_class = 'bg-indigo-100 text-indigo-700';
                                        $icon_jenis = 'fa-arrow-down';
                                    } elseif ($item['kode_jenis'] == 'transfer') {
                                        $badge_class = 'bg-blue-100 text-blue-700';
                                        $icon_jenis = 'fa-exchange-alt';
                                    }
                                    ?>
                                    <span class="px-2.5 py-1 rounded-lg font-bold text-[10px] uppercase tracking-wide <?= $badge_class ?> inline-flex items-center">
                                        <i class="fas <?= $icon_jenis ?> mr-1 text-[8px]"></i> <?= htmlspecialchars($item['nama_jenis'] ?? $item['kode_jenis']) ?>
                                    </span>
                                </td>

                                <td class="p-4 font-medium text-slate-500 tabular-nums">
                                    Rp <?= number_format($item['jumlah'], 0, ',', '.') ?>
                                </td>

                                <td class="p-4 font-black text-indigo-600 text-right tabular-nums">
                                    +Rp <?= number_format($item['biaya_admin'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="p-12 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-2xl mb-2 opacity-50 text-slate-300"></i>
                                    <p class="font-bold">Belum ada komisi admin operasional masuk.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

                <?php if (!empty($riwayat_admin)): ?>
                    <tfoot>
                        <tr class="bg-slate-50/80 border-t-2 border-slate-200 text-xs">
                            <td colspan="6" class="p-4 font-black text-slate-700 text-right uppercase">
                                <i class="fas fa-calculator mr-1 text-slate-400"></i> Total Akumulasi Keuntungan Bersih Lembaga:
                            </td>
                            <td class="p-4 font-black text-indigo-600 text-right tabular-nums text-lg">
                                Rp <?= number_format($total_pendapatan_hari_ini, 0, ',', '.') ?>
                            </td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>