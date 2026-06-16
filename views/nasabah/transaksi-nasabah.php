<?php
$id_nasabah = $_SESSION['id_nasabah'] ?? null;
$semua_transaksi = [];

if ($id_nasabah) {
    try {
        // QUERY BARU: Berpatokan pada tbl_mutasi milik nasabah yang sedang login
        $stmt_riwayat = $pdo->prepare("
            SELECT 
                m.id_mutasi,
                m.jenis_mutasi,
                m.nominal,
                m.saldo_tersedia,
                m.keterangan AS keterangan_mutasi,
                m.created_at AS tanggal_mutasi,
                t.id_transaksi,
                t.kode_transaksi,
                t.biaya_admin,
                t.id_nasabah AS id_pengirim,
                t.id_nasabah_penerima,
                n_pengirim.nama_nasabah AS nama_pengirim,
                n_penerima.nama_nasabah AS nama_penerima
            FROM tbl_mutasi m
            LEFT JOIN tbl_transaksi t ON m.id_transaksi = t.id_transaksi
            LEFT JOIN tbl_nasabah n_pengirim ON t.id_nasabah = n_pengirim.id_nasabah
            LEFT JOIN tbl_nasabah n_penerima ON t.id_nasabah_penerima = n_penerima.id_nasabah
            WHERE m.id_nasabah = ?
            ORDER BY m.created_at DESC
        ");

        $stmt_riwayat->execute([$id_nasabah]);
        $semua_transaksi = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Gagal mengambil riwayat mutasi: " . $e->getMessage());
    }
}

$id_nasabah_aktif = intval($id_nasabah);
$json_transaksi = json_encode($semua_transaksi);
?>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<style>
    @media print {
        .no-print {
            display: none !important;
        }

        body {
            background: white;
        }

        .bg-white {
            border: none !important;
            box-shadow: none !important;
        }

        .rounded-\[1rem\] {
            border-radius: 0 !important;
        }

        #section-riwayat {
            margin: 0;
            padding: 0;
        }

        .admin-badge {
            background-color: #fff1f2 !important;
            color: #e11d48 !important;
            border: 1px solid #ffe4e6 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div id="section-riwayat" class="space-y-6"
    x-data="{
        transaksiAsli: <?= htmlspecialchars($json_transaksi, ENT_QUOTES, 'UTF-8') ?>,
        myId: <?= $id_nasabah_aktif ?>,
        filterJenis: 'semua',
        filterMulai: '',
        filterSelesai: '',

        formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(angka);
        },

        // Menentukan kategori tampilan berdasarkan jenis_mutasi dan detail transaksi
        getKategoriTampilan(item) {
            let jm = item.jenis_mutasi.toLowerCase(); // debit atau kredit
            let idPengirim = parseInt(item.id_pengirim);
            let idPenerima = parseInt(item.id_nasabah_penerima);

            if (jm === 'kredit') {
                // Jika ada id_nasabah_penerima dan pengirimnya bukan dirinya sendiri, berarti transfer masuk
                if (idPenerima === this.myId && idPengirim !== this.myId) {
                    return 'dana_masuk';
                }
                return 'setor'; // Default uang masuk lainnya (setor tunai)
            } else if (jm === 'debit') {
                // Jika dia pengirim dan ada penerimanya, berarti transfer keluar
                if (idPengirim === this.myId && idPenerima && idPenerima !== this.myId) {
                    return 'dana_keluar';
                }
                return 'tarik'; // Default uang keluar lainnya (tarik tunai)
            }
            return 'setor';
        },

        get transaksiDifilter() {
            return this.transaksiAsli.filter(item => {
                let cocokJenis = true;
                let kategori = this.getKategoriTampilan(item);

                if (this.filterJenis !== 'semua' && this.filterJenis !== kategori) {
                    cocokJenis = false;
                }

                let cocokTanggal = true;
                if (item.tanggal_mutasi) {
                    let tglItem = item.tanggal_mutasi.substring(0, 10);
                    if (this.filterMulai && tglItem < this.filterMulai) cocokTanggal = false;
                    if (this.filterSelesai && tglItem > this.filterSelesai) cocokTanggal = false;
                }

                return cocokJenis && cocokTanggal;
            });
        },

        formatTanggalId(dateString) {
            const hariNama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const bulanNama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            
            let d = new Date(dateString);
            if (isNaN(d.getTime())) return dateString;

            return {
                hariTgl: `${hariNama[d.getDay()]}`,
                tglLengkap: `${String(d.getDate()).padStart(2, '0')} ${bulanNama[d.getMonth()]} ${d.getFullYear()}`,
                jamWib: `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')} WIB`
            };
        }
     }">

    <div class="no-print bg-white p-4 rounded-[1rem] border border-slate-100 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Jenis Aktivitas</label>
            <select x-model="filterJenis" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 outline-none focus:border-blue-500 transition-colors">
                <option value="semua">--- Semua Mutasi ---</option>
                <option value="setor">🟢 Setor Tunai</option>
                <option value="tarik">🔴 Tarik Tunai</option>
                <option value="dana_masuk">📥 Transfer Masuk (Dana Masuk)</option>
                <option value="dana_keluar">📤 Transfer Keluar (Dana Keluar)</option>
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Dari Tanggal</label>
            <input type="date" x-model="filterMulai" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 outline-none focus:border-blue-500 transition-colors">
        </div>
        <div class="flex flex-col gap-1.5">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Sampai Tanggal</label>
            <input type="date" x-model="filterSelesai" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 outline-none focus:border-blue-500 transition-colors">
        </div>
    </div>

    <div class="bg-white rounded-[1rem] border border-slate-100 overflow-hidden shadow-sm">
        <div class="p-4 sm:p-6 pb-0">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 pb-4 border-b border-slate-50 gap-4">
                <div>
                    <h3 class="font-black text-[#506a8a] flex items-center gap-2 text-sm sm:text-base">
                        <i class="fas fa-exchange-alt text-amber-500"></i>Mutasi Rekening Tabungan
                    </h3>
                    <p class="text-[11px] text-slate-400 font-medium">Layanan rekam aktivitas, riwayat debit-kredit, dan pelacakan saldo simpanan instan aman terintegrasi.</p>
                </div>
                <a href="?page=main" class="self-start sm:self-center text-[10px] font-black uppercase tracking-wider text-slate-400 hover:text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100 transition-colors shrink-0">
                    <i class="fas fa-arrow-left mr-1.5"></i> Kembali
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] text-center">No.</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Waktu Mutasi</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Kategori</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Nominal</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Saldo Tersedia / Keterangan</th>
                        <th class="px-4 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <template x-if="transaksiDifilter.length === 0">
                        <tr>
                            <td colspan="7" class="px-8 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <i class="fas fa-receipt text-4xl text-slate-200"></i>
                                    <p class="text-xs text-slate-400 font-medium">Tidak ada rekaman aktivitas mutasi saldo yang cocok.</p>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <template x-for="(row, index) in transaksiDifilter" :key="row.id_mutasi">
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <td class="px-4 py-4.5 text-center">
                                <p class="text-xs font-black text-slate-300 group-hover:text-blue-500" x-text="(index + 1) + '.'"></p>
                            </td>

                            <td class="px-6 py-4.5">
                                <div class="flex flex-col gap-0.5">
                                    <p class="text-xs font-bold text-slate-700">
                                        <span x-text="formatTanggalId(row.tanggal_mutasi).hariTgl"></span>,
                                        <span class="text-slate-500 font-medium" x-text="formatTanggalId(row.tanggal_mutasi).tglLengkap"></span>
                                    </p>
                                    <p class="text-[10px] text-slate-400 font-mono font-medium flex items-center gap-1">
                                        <i class="far fa-clock text-[9px] text-slate-300"></i>
                                        <span x-text="formatTanggalId(row.tanggal_mutasi).jamWib"></span>
                                    </p>
                                </div>
                            </td>

                            <td class="px-6 py-4.5">
                                <span :class="{
                                    'bg-emerald-50 text-emerald-600 border-emerald-100/60': row.jenis_mutasi === 'kredit',
                                    'bg-rose-50 text-rose-600 border-rose-100/60': row.jenis_mutasi === 'debit'
                                }" class="px-2.5 py-1 text-[10px] font-black uppercase rounded-lg tracking-wider border">
                                    <span x-text="row.jenis_mutasi === 'kredit' ? 'KREDIT (MASUK)' : 'DEBIT (KELUAR)'"></span>
                                </span>
                            </td>

                            <td class="px-6 py-4.5">
                                <div class="flex flex-col">
                                    <p :class="row.jenis_mutasi === 'debit' ? 'text-rose-600' : 'text-emerald-600'" class="text-xs font-black">
                                        <span x-text="row.jenis_mutasi === 'debit' ? '-' : '+'"></span>
                                        Rp <span x-text="formatRupiah(row.nominal)"></span>
                                    </p>

                                    <template x-if="row.jenis_mutasi === 'debit' && parseFloat(row.biaya_admin) > 0">
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="admin-badge px-1 py-0.5 bg-rose-50 border border-rose-100 text-rose-500 font-mono text-[8px] font-bold rounded">
                                                Inc. Admin Rp <span x-text="formatRupiah(row.biaya_admin)"></span>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </td>

                            <td class="px-6 py-4.5">
                                <div class="flex flex-col gap-1">
                                    <template x-if="getKategoriTampilan(row) === 'dana_masuk'">
                                        <p class="text-[11px] text-slate-600 font-medium">
                                            Pengirim: <span class="font-bold text-slate-800" x-text="row.nama_pengirim"></span>
                                        </p>
                                    </template>

                                    <template x-if="getKategoriTampilan(row) === 'dana_keluar'">
                                        <p class="text-[11px] text-slate-600 font-medium">
                                            Penerima: <span class="font-bold text-slate-800" x-text="row.nama_penerima"></span>
                                        </p>
                                    </template>

                                    <p class="text-xs font-bold text-slate-500 font-mono">
                                        Saldo: <span class="text-slate-700 font-black">Rp <span x-text="formatRupiah(row.saldo_tersedia)"></span></span>
                                    </p>

                                    <p class="text-[10px] text-slate-400 italic">
                                        " <span x-text="row.keterangan_mutasi ? row.keterangan_mutasi : 'Tanpa Keterangan'"></span> "
                                    </p>
                                </div>
                            </td>

                            <td class="px-4 py-4.5 text-center">
                                <div class="flex items-center justify-center gap-1 text-emerald-500">
                                    <i class="fas fa-check-circle text-sm"></i>
                                    <span class="text-[10px] font-bold uppercase hidden sm:inline">Sukses</span>
                                </div>
                            </td>

                            <td class="px-6 py-4.5 no-print text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <template x-if="row.id_transaksi">
                                        <div class="flex gap-1">
                                            <a :href="'modules/transaksi/cetak-struk.php?id=' + row.id_transaksi" target="_blank" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-emerald-500 text-slate-400 hover:text-white flex items-center justify-center border border-slate-100 transition-all active:scale-90 shadow-sm" title="Cetak Struk">
                                                <i class="fas fa-print text-[10px]"></i>
                                            </a>
                                            <a :href="'modules/transaksi/unduh-struk.php?id=' + row.id_transaksi" target="_blank" class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-[#2978d7] text-slate-400 hover:text-white flex items-center justify-center border border-slate-100 transition-all active:scale-90 shadow-sm" title="Unduh PDF">
                                                <i class="fas fa-download text-[10px]"></i>
                                            </a>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="no-print bg-slate-50 border-t border-slate-100 p-4 flex items-start gap-3">
            <div class="text-blue-500 mt-0.5">
                <i class="fas fa-info-circle text-sm"></i>
            </div>
            <div class="space-y-0.5">
                <h5 class="text-[11px] font-black text-slate-700 uppercase tracking-wider">Informasi Sinkronisasi Mutasi</h5>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Tabel di atas merupakan data **Mutasi Resmi** yang mencatat perubahan saldo tabungan Anda secara kronologis (*real-time*). Nilai saldo yang tertera mencerminkan saldo akhir Anda tepat setelah transaksi tersebut berhasil dieksekusi oleh sistem pembukuan.
                </p>
            </div>
        </div>
    </div>
</div>