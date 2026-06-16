<?php

/**
 * SISTEM MANAJEMEN DATA LOKET + REAL-TIME JURNAL KAS (FINAL & CLEAN CODE)
 * Koneksi ke tabel database: tbl_loket, tbl_users, & tbl_jurnal_kas
 */

// 1. Fungsi Helper Komponen UI Status Loket
if (!function_exists('get_status_loket')) {
    function get_status_loket($status)
    {
        if ($status === 'buka') {
            return [
                'bg'    => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                'icon'  => 'fa-door-open',
                'text'  => 'BUKA'
            ];
        }
        return [
            'bg'    => 'bg-rose-50 text-rose-600 border-rose-100',
            'icon'  => 'fa-door-closed',
            'text'  => 'TUTUP'
        ];
    }
}

// 2. Query Data Loket + Left Join ke Users + Left Join ke Jurnal Kas (Hanya mengambil yang berstatus 'open')
$query = "SELECT l.*, 
                 p.nama_lengkap AS nama_petugas, 
                 p.username AS username_petugas,
                 j.saldo_akhir_laci AS saldo_aktif_saat_ini,
                 j.status_jurnal
          FROM tbl_loket l 
          LEFT JOIN tbl_users p ON l.id_petugas = p.id_user 
          LEFT JOIN tbl_jurnal_kas j ON l.id_loket = j.id_loket AND j.status_jurnal = 'open'
          ORDER BY l.nomor_loket ASC";

try {
    if (!isset($pdo)) {
        throw new PDOException("Variabel koneksi database (\$pdo) belum terdefinisi. Pastikan file koneksi sudah di-include.");
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $data_loket = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error Fetch Data Loket & Jurnal: " . $e->getMessage());

    die("
    <div style='padding: 20px; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; margin: 20px; font-family: sans-serif;'>
        <h3 style='color: #9f1239; margin-top: 0;'>🚨 Terjadi Gangguan Database!</h3>
        <p style='font-size: 13px; color: #4c0519; line-height: 1.5;'>Sistem gagal memuat data loket operasional. Berikut rincian masalah teknisnya:</p>
        <code style='display: block; background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #ffe4e6; color: #be123c; font-family: monospace; font-size: 12px; overflow-x: auto;'>
            " . htmlspecialchars($e->getMessage()) . "
        </code>
    </div>");
}
?>

<div class="space-y-6">

    <?php if (isset($_GET['msg']) && isset($_GET['type'])): ?>
        <?php
        $msg = htmlspecialchars($_GET['msg']);
        $type = $_GET['type'];

        if ($type === 'success') {
            $bg_class = 'bg-emerald-50 border-emerald-200 text-emerald-800';
            $icon_class = 'fa-check-circle text-emerald-500';
            $title = 'Berhasil!';
        } else {
            $bg_class = 'bg-rose-50 border-rose-200 text-rose-800';
            $icon_class = 'fa-exclamation-circle text-rose-500';
            $title = 'Perhatian!';
        }
        ?>
        <div id="system-alert" class="mb-5 p-4 rounded-xl border <?= $bg_class ?> flex items-start gap-3 shadow-sm transition-all animate-fade-in">
            <i class="fas <?= $icon_class ?> text-lg mt-0.5"></i>
            <div class="flex-1">
                <h4 class="text-xs font-black uppercase tracking-wider mb-0.5"><?= $title ?></h4>
                <p class="text-[11px] font-medium leading-relaxed opacity-90"><?= $msg ?></p>
            </div>
            <button type="button" onclick="document.getElementById('system-alert').remove()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="w-full md:w-auto">
                <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                    <i class="fas fa-desktop text-[#1257aa]"></i> Manajemen Data Loket & Kas Kerja
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Kelola konfigurasi loket, penugasan staff, pantau saldo laci aktif teller, dan peninjauan riwayat jurnal kas.</p>
            </div>

            <div class="w-full md:w-auto flex flex-wrap items-center gap-3 justify-end">
                <div class="relative w-full sm:w-auto">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-[10px]"></i>
                    </span>
                    <input type="text" id="inputCariLoket" placeholder="Cari nama, nomor loket atau kasir..."
                        class="pl-8 pr-3 py-2 w-full sm:w-56 text-[10px] bg-white border border-slate-200 rounded-lg text-slate-600 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-bold">
                </div>

                <a href="?page=tambah-loket" class="text-[10px] font-black bg-[#1257aa] text-white px-3.5 py-2 rounded-lg transition-all shadow-sm flex items-center gap-1.5 tracking-wider uppercase">
                    <i class="fas fa-plus-circle text-xs"></i> Tambah Loket Baru
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="tabelLoket">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 text-center w-12">No.</th>
                        <th class="p-4 text-center w-16">Loket</th>
                        <th class="p-4">Identitas & Deskripsi Loket</th>
                        <th class="p-4">Kasir Penanggung Jawab</th>
                        <th class="p-4 text-right">Saldo Laci Aktif (Open)</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-center">Gerbang</th>
                        <th class="p-4 text-center w-36">Manajemen Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($data_loket)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($data_loket as $item): ?>
                            <?php
                            $status_str = $item['status_loket'] ?? 'tutup';
                            $ui_status = get_status_loket($status_str);

                            // Hitung indikator kematangan operasional loket
                            $terisi = 0;
                            if (!empty($item['id_petugas'])) $terisi += 50;
                            if ($status_str === 'buka') $terisi += 50;

                            if ($terisi == 0) {
                                $progress_color = 'bg-rose-500';
                                $text_color     = 'text-rose-600';
                                $pesan_notif    = '⚠️ Tanpa Petugas';
                            } elseif ($terisi == 50) {
                                $progress_color = 'bg-amber-500';
                                $text_color     = 'text-amber-600';
                                $pesan_notif    = '⏳ Siap / Istirahat';
                            } else {
                                $progress_color = 'bg-emerald-500';
                                $text_color     = 'text-emerald-600';
                                $pesan_notif    = '✅ Melayani';
                            }
                            ?>

                            <tr class="row-loket hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400"><?= $no++ ?></td>

                                <td class="p-4 text-center font-mono font-black text-slate-700 text-sm target-nomor">
                                    <?= str_pad($item['nomor_loket'], 2, '0', STR_PAD_LEFT) ?>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2 flex-wrap">
                                        <?php if ($status_str === 'buka'): ?>
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="Aktif Melayani"></span>
                                        <?php else: ?>
                                            <span class="w-2 h-2 rounded-full bg-slate-300" title="Offline"></span>
                                        <?php endif; ?>
                                        <span class="target-nama"><?= htmlspecialchars($item['nama_loket']) ?></span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        ID Referensi: <span class="font-mono"><?= $item['id_loket'] ?></span>
                                    </div>
                                    <div class="mt-2 max-w-[150px]">
                                        <div class="flex items-center justify-between text-[9px] font-bold mb-0.5">
                                            <span class="<?= $text_color ?>">Kesiapan: <?= $terisi ?>%</span>
                                            <span class="text-[8px] font-medium text-slate-400"><?= $pesan_notif ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1">
                                            <div class="<?= $progress_color ?> h-1 rounded-full transition-all duration-500" style="width: <?= $terisi ?>%"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <?php if (!empty($item['id_petugas'])): ?>
                                        <div class="flex items-center gap-2 text-slate-700 font-bold mb-0.5">
                                            <i class="fas fa-user-shield text-indigo-500 text-[10px]"></i>
                                            <span class="target-petugas"><?= htmlspecialchars($item['nama_petugas']) ?></span>
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-mono">
                                            @<?= htmlspecialchars($item['username_petugas'] ?? 'unknown') ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-rose-500 font-medium italic flex items-center gap-1">
                                            <i class="fas fa-exclamation-circle text-[10px]"></i> Belum ada staff
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-right font-mono font-bold">
                                    <?php if ($item['status_jurnal'] === 'open'): ?>
                                        <span class="text-slate-900 text-xs">Rp <?= number_format($item['saldo_aktif_saat_ini'], 2, ',', '.') ?></span>
                                        <span class="block text-[9px] text-emerald-600 font-sans font-extrabold uppercase tracking-wide mt-0.5">
                                            <i class="fas fa-lock-open text-[8px]"></i> Jurnal Terbuka
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs">Rp 0,00</span>
                                        <span class="block text-[9px] text-slate-400 font-sans font-semibold italic mt-0.5">
                                            <i class="fas fa-lock text-[8px]"></i> Laci Kosong/Tutup
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase inline-flex items-center <?= $ui_status['bg'] ?> border">
                                        <i class="fas <?= $ui_status['icon'] ?> mr-1"></i>
                                        <?= $ui_status['text'] ?>
                                    </span>
                                </td>

                                <td class="p-4 text-center">
                                    <?php if ($status_str === 'buka'): ?>
                                        <a href="modules/loket/toggle-loket.php?id=<?= $item['id_loket'] ?>&action=tutup"
                                            onclick="return confirm('Apakah Anda yakin ingin MENUTUP operasional <?= addslashes($item['nama_loket']) ?>?')"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 hover:bg-rose-100 hover:text-rose-700 transition-colors font-bold text-[10px]">
                                            <i class="fas fa-toggle-on text-xs"></i> Buka
                                        </a>
                                    <?php else: ?>
                                        <a href="modules/loket/toggle-loket.php?id=<?= $item['id_loket'] ?>&action=buka"
                                            onclick="return confirm('Buka loket antrean ini sekarang? Pastikan petugas penanggung jawab sudah siap.')"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 hover:bg-emerald-600 hover:text-white transition-colors font-bold text-[10px]">
                                            <i class="fas fa-toggle-off text-xs"></i> Tutup
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        <button type="button"
                                            class="btn-detail-loket w-7 h-7 flex items-center justify-center rounded-lg bg-white text-emerald-600 hover:bg-emerald-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Buka Audit Ringkas"
                                            data-id="<?= $item['id_loket'] ?>"
                                            data-nomor="<?= $item['nomor_loket'] ?>"
                                            data-nama="<?= htmlspecialchars($item['nama_loket']) ?>"
                                            data-idpetugas="<?= $item['id_petugas'] ?? '-' ?>"
                                            data-petugas="<?= htmlspecialchars($item['nama_petugas'] ?? 'Belum Ditugaskan') ?>"
                                            data-username="<?= htmlspecialchars($item['username_petugas'] ?? '-') ?>"
                                            data-status="<?= strtoupper($status_str) ?>">
                                            <i class="fas fa-eye text-[10px]"></i>
                                        </button>

                                        <a href="main.php?page=riwayat-jurnal-kas&id_loket=<?= $item['id_loket'] ?>"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-indigo-600 hover:bg-indigo-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Lihat Histori Ringkasan Jurnal Kas Harian">
                                            <i class="fas fa-book text-[10px]"></i>
                                        </a>

                                        <a href="main.php?page=edit-loket&id=<?= $item['id_loket'] ?>"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-amber-500 hover:bg-amber-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Ubah Konfigurasi">
                                            <i class="fas fa-edit text-[10px]"></i>
                                        </a>

                                        <a href="modules/loket/delete-loket.php?id=<?= $item['id_loket'] ?>"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus sistem <?= addslashes($item['nama_loket']) ?>?')"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-red-600 hover:bg-red-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Hapus Jalur Loket">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="rowTidakDitemukan" class="hidden">
                            <td colspan="8" class="p-8 text-center text-slate-400">Tidak ada data ruangan loket yang cocok dengan kriteria pencarian.</td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400">Belum ada infrastruktur loket yang terdaftar dalam database internal.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-slate-50 text-[10px] text-slate-400 border-t border-slate-100 flex justify-between">
            <span>* Penyesuaian status buka/tutup loket akan langsung mengubah hak akses pemanggilan nomor antrean secara real-time.</span>
            <span>Total Loket: <span id="totalFooter"><?= count($data_loket) ?></span> unit terdata.</span>
        </div>
    </div>
</div>

<div id="modal-detail-loket" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none flex items-center justify-center bg-black/50 p-4 transition-all duration-300 ease-out overflow-y-auto">
    <div id="modal-content" class="bg-slate-50 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform scale-95 opacity-0 transition-all duration-300 ease-out my-8">

        <div class="bg-[#1566c7] p-5 text-white flex justify-between items-center shadow-md">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-desktop text-amber-300"></i> Dokumen Audit Loket
                </h3>
                <p class="text-[10px] text-slate-200 mt-0.5 font-medium">Informasi mendalam mengenai pemetaan gerbang loket operasional.</p>
            </div>
            <button type="button" onclick="closeModalDetail()" class="text-white opacity-80 hover:opacity-100 transition-opacity text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-5 space-y-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 grid grid-cols-2 gap-4 shadow-sm">
                <div class="border-r border-dashed border-slate-100 pr-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Nomor Gerbang</span>
                    <h2 id="md-nomor" class="text-2xl font-black text-indigo-600 font-mono">00</h2>
                </div>
                <div class="flex flex-col justify-center pl-2">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Kondisi:</span>
                        <span id="md-status-badge" class="px-2 py-0.5 rounded text-[9px] font-black uppercase border">TUTUP</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm space-y-3 text-xs">
                <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5 flex items-center gap-1.5">
                    <i class="fas fa-info-circle text-amber-500"></i> Detail Struktural
                </h4>
                <div class="flex flex-col">
                    <span class="text-[10px] text-slate-400 font-medium">Nama/Deskripsi Loket</span>
                    <span id="md-nama" class="font-bold text-slate-800">-</span>
                </div>
                <div class="flex flex-col pt-1.5 border-t border-slate-50">
                    <span class="text-[10px] text-slate-400 font-medium">Petugas yang Bertugas</span>
                    <span id="md-petugas" class="font-bold text-slate-800 text-sm">-</span>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-1.5 border-t border-slate-50 font-mono">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-sans font-medium">ID Petugas</span>
                        <span id="md-idpetugas" class="font-bold text-slate-600">-</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-sans font-medium">Username</span>
                        <span id="md-username" class="font-bold text-slate-600">-</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-slate-100 px-5 py-3.5 border-t border-slate-200">
            <button type="button" onclick="closeModalDetail()"
                class="w-full flex items-center justify-center bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase">
                Tutup Dokumen Audit
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Real-time Search Engine
        const inputCari = document.getElementById("inputCariLoket");
        const tabel = document.getElementById("tabelLoket");

        if (inputCari && tabel) {
            const barisData = tabel.querySelectorAll(".row-loket");
            const rowKosong = document.getElementById("rowTidakDitemukan");

            inputCari.addEventListener("keyup", function() {
                const keyword = inputCari.value.toLowerCase().trim();
                let adaDataMatch = false;

                barisData.forEach((row) => {
                    const nama = row.querySelector(".target-nama").textContent.toLowerCase();
                    const nomor = row.querySelector(".target-nomor").textContent.toLowerCase();
                    const petugasEl = row.querySelector(".target-petugas");
                    const petugas = petugasEl ? petugasEl.textContent.toLowerCase() : '';

                    if (nama.includes(keyword) || nomor.includes(keyword) || petugas.includes(keyword)) {
                        row.classList.remove("hidden");
                        adaDataMatch = true;
                    } else {
                        row.classList.add("hidden");
                    }
                });

                if (adaDataMatch || keyword === "") {
                    rowKosong.classList.add("hidden");
                } else {
                    rowKosong.classList.remove("hidden");
                }
            });
        }

        // Binder Data Transaksi ke Modal Objek Antrean
        const tombolDetail = document.querySelectorAll(".btn-detail-loket");
        tombolDetail.forEach(btn => {
            btn.addEventListener("click", function() {
                document.getElementById("md-nomor").textContent = this.getAttribute("data-nomor").padStart(2, '0');
                document.getElementById("md-nama").textContent = this.getAttribute("data-nama");
                document.getElementById("md-idpetugas").textContent = this.getAttribute("data-idpetugas");
                document.getElementById("md-petugas").textContent = this.getAttribute("data-petugas");

                const usernameAttr = this.getAttribute("data-username");
                document.getElementById("md-username").textContent = (usernameAttr && usernameAttr !== '-') ? '@' + usernameAttr : '-';

                const status = this.getAttribute("data-status");
                const badgeStatus = document.getElementById("md-status-badge");
                badgeStatus.textContent = status;

                if (status === "BUKA") {
                    badgeStatus.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase border bg-emerald-50 text-emerald-600 border-emerald-100";
                } else {
                    badgeStatus.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase border bg-rose-50 text-rose-600 border-rose-100";
                }

                openModalDetail();
            });
        });
    });

    function openModalDetail() {
        const modal = document.getElementById("modal-detail-loket");
        const content = modal.querySelector("#modal-content");
        modal.classList.remove("invisible", "opacity-0", "pointer-events-none");
        content.classList.remove("scale-95", "opacity-0");
    }

    function closeModalDetail() {
        const modal = document.getElementById("modal-detail-loket");
        const content = modal.querySelector("#modal-content");
        content.classList.add("scale-95", "opacity-0");
        setTimeout(() => {
            modal.classList.add("invisible", "opacity-0", "pointer-events-none");
        }, 200);
    }
</script>