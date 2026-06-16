<?php

/**
 * Aplikasi Mini Bank Sekolah
 * Berkas: views/master/tahun_ajaran.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// AMBIL TOTAL SELURUH NASABAH UNTUK MENGHITUNG PERSENTASE DISTRIBUSI
try {
    $stmt_total = $pdo->query("SELECT COUNT(*) FROM tbl_nasabah");
    $total_nasabah_global = (int)$stmt_total->fetchColumn();
} catch (PDOException $e) {
    $total_nasabah_global = 0;
}

// QUERY UTAMA: Mengambil data tahun ajaran sesuai struktur tbl_tahun_ajaran + Hitung Relasi Jumlah Nasabah
// Catatan: Pastikan di tbl_nasabah Anda memiliki kolom id_tahun_ajaran untuk relasi ini
$query = "SELECT t.id_tahun_ajaran, t.tahun_ajaran, t.status_aktif, t.created_at,
                 (SELECT COUNT(*) FROM tbl_nasabah n WHERE n.id_tahun_ajaran = t.id_tahun_ajaran) as jml_nasabah
          FROM tbl_tahun_ajaran t 
          ORDER BY t.tahun_ajaran DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $data_tahun_ajaran = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error Fetch Master Tahun Ajaran: " . $e->getMessage());
    die("Terjadi gangguan saat memuat data master tahun ajaran.");
}
?>

<div class="space-y-6">
    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="w-full md:w-auto">
                <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-[#1257aa]"></i> Manajemen Data Master Tahun Ajaran
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Kelola rentang tahun ajaran sekolah, kontrol status aktif sistem, dan proteksi integritas relasi data tabungan siswa.</p>
            </div>

            <div class="w-full md:w-auto flex flex-wrap items-center gap-3 justify-end">
                <div class="relative w-full sm:w-auto">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-[10px]"></i>
                    </span>
                    <input type="text" id="inputCariTahun" placeholder="Cari tahun ajaran..."
                        class="pl-8 pr-3 py-2 w-full sm:w-56 text-[10px] bg-white border border-slate-200 rounded-lg text-slate-600 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-bold">
                </div>

                <a href="?page=tambah-tahun-ajaran" class="text-[10px] font-black bg-[#1257aa] text-white px-3.5 py-2 rounded-lg transition-all shadow-sm flex items-center gap-1.5 tracking-wider uppercase">
                    <i class="fas fa-plus text-xs"></i> Tambah Tahun Ajaran
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="tabelTahunAjaran">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 text-center w-16">No.</th>
                        <th class="p-4">Identitas Tahun Ajaran</th>
                        <th class="p-4">Proporsi & Distribusi Nasabah</th>
                        <th class="p-4 text-center">Status Relasi DB</th>
                        <th class="p-4 text-center">Manajemen Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($data_tahun_ajaran)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($data_tahun_ajaran as $item): ?>
                            <?php
                            // Hitung persentase kontribusi jumlah siswa di tahun ajaran ini dibanding total seluruh siswa
                            $persen_distribusi = $total_nasabah_global > 0 ? round(($item['jml_nasabah'] / $total_nasabah_global) * 100) : 0;
                            $is_locked = ((int)$item['jml_nasabah'] > 0 || $item['status_aktif'] === 'aktif');
                            ?>
                            <tr class="row-tahun hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400 target-no"><?= $no++ ?></td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2 flex-wrap">
                                        <span class="px-2 py-0.5 bg-slate-100 text-slate-800 rounded font-mono font-bold text-[10px] border border-slate-200 target-tahun uppercase">
                                            <?= htmlspecialchars($item['tahun_ajaran']) ?>
                                        </span>
                                        <?php if ($item['status_aktif'] === 'aktif'): ?>
                                            <span class="px-2 py-0.5 bg-emerald-500 text-white rounded font-bold text-[9px] uppercase tracking-wider animate-pulse">Aktif Berjalan</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-slate-300 text-white rounded font-bold text-[9px] uppercase tracking-wider">Nonaktif</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-1">
                                        <i class="fas fa-fingerprint text-indigo-500 mr-1"></i> ID Sistem: <span><?= $item['id_tahun_ajaran'] ?></span>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="flex items-center gap-2 text-slate-700 font-bold mb-1">
                                        <i class="fas fa-users text-slate-400 text-[10px]"></i>
                                        <span><?= $item['jml_nasabah'] ?> Anggota Terdaftar</span>
                                    </div>
                                    <div class="max-w-[200px]">
                                        <div class="flex items-center justify-between text-[9px] font-bold mb-0.5">
                                            <span class="text-blue-600"><?= $persen_distribusi ?>% Kepadatan</span>
                                            <span class="text-[8px] font-medium text-slate-400">Dari total nasabah</span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1">
                                            <div class="bg-[#1257aa] h-1 rounded-full transition-all duration-500" style="width: <?= $persen_distribusi ?>%"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4 text-center">
                                    <?php if ($item['status_aktif'] === 'aktif'): ?>
                                        <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase inline-flex items-center bg-red-50 text-red-600 border border-red-100" title="Data dikunci total karena merupakan tahun ajaran aktif sistem yang sedang berjalan">
                                            <i class="fas fa-exclamation-triangle mr-1 text-[9px]"></i> Sistem Aktif
                                        </span>
                                    <?php elseif ($is_locked): ?>
                                        <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase inline-flex items-center bg-amber-50 text-amber-600 border border-amber-100" title="Data terkunci aman karena memiliki arsip data nasabah terikat">
                                            <i class="fas fa-lock mr-1 text-[9px]"></i> Terikat Nasabah
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase inline-flex items-center bg-emerald-50 text-emerald-600 border border-emerald-100" title="Data aman dipindahkan atau dihapus bebas">
                                            <i class="fas fa-lock-open mr-1 text-[9px]"></i> Standalone (Bebas)
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center justify-center gap-1.5 p-1">
                                        <button type="button" class="btn-detail-tahun w-7 h-7 flex items-center justify-center rounded-lg bg-white text-emerald-600 hover:bg-emerald-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Buka Informasi Rinci"
                                            data-id="<?= $item['id_tahun_ajaran'] ?>"
                                            data-tahun="<?= htmlspecialchars($item['tahun_ajaran']) ?>"
                                            data-status="<?= $item['status_aktif'] ?>"
                                            data-created="<?= date('d F Y, H:i', strtotime($item['created_at'])) ?>"
                                            data-jumlah="<?= $item['jml_nasabah'] ?>"
                                            data-persen="<?= $persen_distribusi ?>%">
                                            <i class="fas fa-eye text-[11px]"></i>
                                        </button>

                                        <a href="main.php?page=edit-tahun-ajaran&id=<?= $item['id_tahun_ajaran'] ?>"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-amber-500 hover:bg-amber-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Ubah Konfigurasi Tahun Ajaran">
                                            <i class="fas fa-edit text-[11px]"></i>
                                        </a>

                                        <a href="modules/tahun_ajaran/delete-tahun.php?id=<?= $item['id_tahun_ajaran'] ?>"
                                            onclick="return confirm('🚨 PERHATIAN KRITIS!\n\nApakah Anda yakin ingin menghapus data master tahun ajaran: <?= addslashes($item['tahun_ajaran']) ?>?\n\nTindakan ini bersifat PERMANEN.')"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-red-600 hover:bg-red-50 border border-slate-200/60 transition-all shadow-sm <?= $is_locked ? 'opacity-30 pointer-events-none' : '' ?>"
                                            title="<?= $item['status_aktif'] === 'aktif' ? 'Tidak bisa dihapus karena statusnya sedang AKTIF!' : ($is_locked ? 'Tidak bisa dihapus karena masih mengikat arsip data nasabah!' : 'Hapus Tahun Ajaran Permanen') ?>">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="rowTidakDitemukan" class="hidden">
                            <td colspan="5" class="p-8 text-center text-slate-400">Tidak ada data master tahun ajaran yang cocok dengan pencarian Anda.</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400">Belum ada data master tahun ajaran yang terekam di sistem.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-slate-50 text-[10px] text-slate-400 border-t border-slate-100 flex justify-between">
            <span>* Penghapusan tahun ajaran otomatis ditolak sistem jika berstatus aktif berjalan atau terdeteksi mengikat akun nasabah.</span>
            <span>Total Entri Terdata: <span id="totalFooter" class="font-bold text-slate-600"><?= count($data_tahun_ajaran) ?></span> rentang periode.</span>
        </div>
    </div>
</div>

<div id="modal-detail-tahun" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none flex items-center justify-center bg-black/50 p-4 transition-all duration-300 ease-out overflow-y-auto">
    <div id="modal-content-tahun" class="bg-slate-50 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform scale-95 opacity-0 transition-all duration-300 ease-out my-8">

        <div class="bg-[#1566c7] p-5 text-white flex justify-between items-center shadow-md">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-amber-300"></i> Informasi Detail Tahun Ajaran
                </h3>
                <p class="text-[10px] text-slate-200 mt-0.5 font-medium">Rincian data konfigurasi log relasi periode akuntansi nasabah.</p>
            </div>
            <button type="button" onclick="closeModalTahun()" class="text-white opacity-80 hover:opacity-100 transition-opacity text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-5 space-y-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 grid grid-cols-2 gap-4 shadow-sm bg-gradient-to-r from-white to-slate-50/40">
                <div class="border-r border-dashed border-slate-200 pr-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1 flex items-center gap-1">
                        <i class="fas fa-users text-blue-500"></i> Total Afiliasi
                    </span>
                    <h2 id="md-tahun-jumlah" class="text-lg font-black text-blue-600 tabular-nums">0 Siswa</h2>
                    <span class="text-[9px] text-slate-400 block mt-0.5 font-medium">Nasabah terikat aktif</span>
                </div>
                <div class="pl-2 flex flex-col justify-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1 flex items-center gap-1">
                        <i class="fas fa-chart-pie text-emerald-500"></i> Rasio Sekolah
                    </span>
                    <h2 id="md-tahun-persen" class="text-lg font-black text-emerald-600 tabular-nums">0%</h2>
                    <span class="text-[9px] text-slate-400 block mt-0.5 font-medium">Kepadatan populasi</span>
                </div>
            </div>

            <div class="p-3 bg-blue-50/80 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-800 text-[10px] leading-relaxed shadow-sm">
                <i class="fas fa-shield-alt text-blue-500 mt-0.5 text-xs flex-shrink-0"></i>
                <div>
                    <span class="font-bold">Sistem Proteksi Data:</span> Jika tahun ajaran berstatus <span class="font-bold text-emerald-600">Aktif</span> atau memiliki afiliasi <span class="font-bold text-blue-600">lebih dari 0</span>, maka tombol hapus otomatis dikunci demi melindungi integritas data sistem.
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm space-y-4 text-xs">
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-slate-50 border border-slate-200/60 flex items-center justify-center text-slate-400 flex-shrink-0 mt-0.5 shadow-sm">
                        <i class="fas fa-fingerprint text-[11px]"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">ID Database Internal</span>
                        <span id="md-tahun-id" class="font-mono font-bold text-slate-700 tracking-wide mt-0.5">-</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Primary Key unik di sistem tabel database `tbl_tahun_ajaran`.</span>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-500 flex-shrink-0 mt-0.5 shadow-sm">
                        <i class="fas fa-tags text-[11px]"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Periode Rentang Tahun</span>
                        <span id="md-tahun-display" class="font-mono font-black text-[#1257aa] bg-blue-50 px-2 py-0.5 rounded w-max border border-blue-100 uppercase text-[10px] mt-1 tracking-wider">-</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Format baku penulisan tahun ajaran (contoh: 2025/2026).</span>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-500 flex-shrink-0 mt-0.5 shadow-sm">
                        <i class="fas fa-toggle-on text-[11px]"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Status Konfigurasi</span>
                        <span id="md-tahun-status" class="font-bold text-slate-800 text-[11px] mt-0.5 uppercase tracking-wide">-</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Indikator operasional pencatatan buku kas perbankan.</span>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-500 flex-shrink-0 mt-0.5 shadow-sm">
                        <i class="fas fa-clock text-[11px]"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Tanggal Registrasi Sistem</span>
                        <span id="md-tahun-created" class="font-bold text-slate-700 text-[11px] mt-0.5">-</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">Waktu pertama kali record di-insert ke database server.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-slate-100 px-5 py-3.5 border-t border-slate-200">
            <button type="button" onclick="closeModalTahun()"
                class="w-full flex items-center justify-center bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase">
                <i class="fas fa-folder-minus mr-2"></i> Tutup Informasi Rinci
            </button>
        </div>
    </div>
</div>

<script>
    // LIVE SEARCH REALTIME (Sama Persis Alurnya Seperti Jurusan)
    document.getElementById('inputCariTahun')?.addEventListener('input', function() {
        const filter = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#tabelTahunAjaran .row-tahun');
        let dataDitemukan = false;
        let counterNo = 1;

        rows.forEach(row => {
            const tahunText = row.querySelector('.target-tahun')?.textContent.toLowerCase() || '';

            if (tahunText.includes(filter)) {
                row.classList.remove('hidden');
                const noCell = row.querySelector('.target-no');
                if (noCell) noCell.textContent = counterNo++;
                dataDitemukan = true;
            } else {
                row.classList.add('hidden');
            }
        });

        const emptyRow = document.getElementById('rowTidakDitemukan');
        const totalFooter = document.getElementById('totalFooter');

        if (emptyRow) {
            if (!dataDitemukan && filter !== '') {
                emptyRow.classList.remove('hidden');
            } else {
                emptyRow.classList.add('hidden');
            }
        }
        if (totalFooter) {
            totalFooter.textContent = counterNo - 1;
        }
    });

    // MODAL CONTROLLER FOR DETAIL TAHUN AJARAN
    const modalTahun = document.getElementById('modal-detail-tahun');
    const modalContentTahun = document.getElementById('modal-content-tahun');

    document.querySelectorAll('.btn-detail-tahun').forEach(button => {
        button.addEventListener('click', function() {
            // Mapping dataset ke modal elements
            document.getElementById('md-tahun-id').textContent = this.dataset.id;
            document.getElementById('md-tahun-display').textContent = this.dataset.tahun;
            document.getElementById('md-tahun-jumlah').textContent = this.dataset.jumlah + ' Nasabah';
            document.getElementById('md-tahun-persen').textContent = this.dataset.persen;
            document.getElementById('md-tahun-created').textContent = this.dataset.created;

            const statusBadge = document.getElementById('md-tahun-status');
            if (this.dataset.status === 'aktif') {
                statusBadge.textContent = 'Aktif Berjalan';
                statusBadge.className = "font-black text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded w-max text-[10px]";
            } else {
                statusBadge.textContent = 'Nonaktif';
                statusBadge.className = "font-bold text-slate-500 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded w-max text-[10px]";
            }

            // Trigger opening animations
            modalTahun.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
            setTimeout(() => {
                modalContentTahun.classList.remove('scale-95', 'opacity-0');
            }, 10);
        });
    });

    function closeModalTahun() {
        modalContentTahun.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modalTahun.classList.add('invisible', 'opacity-0', 'pointer-events-none');
        }, 300);
    }

    // Close modal saat klik di luar area konten modal
    modalTahun?.addEventListener('click', function(e) {
        if (e.target === this) closeModalTahun();
    });
</script>