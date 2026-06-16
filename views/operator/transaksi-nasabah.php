<?php

/**
 * Berkas: views/operator/laporan_transaksi.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$filter_jenis   = $_GET['jenis_transaksi'] ?? 'semua';
$tanggal_mulai  = $_GET['tgl_mulai'] ?? '';
$tanggal_akhir  = $_GET['tgl_akhir'] ?? '';

$semua_laporan_gabungan = [];

$ambil_tabungan = in_array($filter_jenis, ['semua', 'setor', 'tarik', 'transfer']);
$ambil_infaq    = ($filter_jenis === 'semua' || strpos($filter_jenis, 'infaq_') === 0);

try {
    // =========================================================================
    // A. AMBIL DATA DARI TABEL UTAMA TRANSAKSI (TABUNGAN & MUTASI)
    // =========================================================================
    if ($ambil_tabungan) {
        $query_transaksi = "
            SELECT 
                t.id_transaksi AS id_unik,
                t.kode_transaksi AS kode_reg,
                jt.kode_jenis AS jenis_raw, 
                t.jumlah AS nominal,
                t.biaya_admin AS admin,
                t.tanggal_transaksi AS waktu,
                t.keterangan AS ket,
                t.status_approval AS status,
                n1.nama_nasabah AS subjek_utama,
                n1.nisn AS nisn_utama,
                n1.kelas AS kelas_utama,
                j1.nama_jurusan AS jurusan_utama,
                n2.nama_nasabah AS subjek_penerima,
                n2.nisn AS nisn_penerima,
                n2.kelas AS kelas_penerima,
                j2.nama_jurusan AS jurusan_penerima,
                u.nama_lengkap AS nama_petugas
            FROM tbl_transaksi t
            INNER JOIN tbl_jenis_transaksi jt ON t.id_jenis_transaksi = jt.id_jenis_transaksi 
            LEFT JOIN tbl_nasabah n1 ON t.id_nasabah = n1.id_nasabah
            LEFT JOIN tbl_jurusan j1 ON n1.id_jurusan = j1.id_jurusan 
            LEFT JOIN tbl_nasabah n2 ON t.id_nasabah_penerima = n2.id_nasabah
            LEFT JOIN tbl_jurusan j2 ON n2.id_jurusan = j2.id_jurusan 
            LEFT JOIN tbl_users u ON t.id_petugas = u.id_user
            WHERE t.status_approval = 'approved'
        ";

        $params_t = [];
        if (!empty($tanggal_mulai)) {
            $query_transaksi .= " AND t.tanggal_transaksi >= :tgl_mulai";
            $params_t[':tgl_mulai'] = $tanggal_mulai . " 00:00:00";
        }
        if (!empty($tanggal_akhir)) {
            $query_transaksi .= " AND t.tanggal_transaksi <= :tgl_akhir";
            $params_t[':tgl_akhir'] = $tanggal_akhir . " 23:59:59";
        }
        if ($filter_jenis !== 'semua') {
            $query_transaksi .= " AND jt.kode_jenis = :jenis_raw";
            $params_t[':jenis_raw'] = $filter_jenis;
        }

        $stmt_t = $pdo->prepare($query_transaksi);
        $stmt_t->execute($params_t);

        while ($row = $stmt_t->fetch(PDO::FETCH_ASSOC)) {
            $kat_murni = $row['jenis_raw'];
            if ($row['jenis_raw'] === 'transfer') {
                $kat_murni = 'transfer_keluar';
            }

            $semua_laporan_gabungan[] = [
                'id'            => 'T-' . $row['id_unik'],
                'kode'          => $row['kode_reg'],
                'kategori'      => $kat_murni,
                'waktu'         => $row['waktu'],
                'siswa'         => $row['subjek_utama'] ?? 'Umum/Anonim',
                'nisn'          => $row['nisn_utama'] ?? '-',
                'kelas'         => $row['kelas_utama'] ?? '-',
                'jurusan'       => $row['jurusan_utama'] ?? '-',
                'penerima'      => $row['subjek_penerima'] ?? '-',
                'nominal'       => floatval($row['nominal']),
                'admin'         => floatval($row['admin']),
                'keterangan'    => $row['ket'] ?? 'Transaksi Kasir',
                'petugas'       => $row['nama_petugas'] ?? 'Sistem',
                'sumber_tabel'  => 'tabungan'
            ];

            if ($row['jenis_raw'] === 'transfer' && !empty($row['subjek_penerima']) && ($filter_jenis === 'semua' || $filter_jenis === 'transfer')) {
                $semua_laporan_gabungan[] = [
                    'id'            => 'T-IN-' . $row['id_unik'],
                    'kode'          => $row['kode_reg'],
                    'kategori'      => 'transfer_masuk',
                    'waktu'         => $row['waktu'],
                    'siswa'         => $row['subjek_penerima'],
                    'nisn'          => $row['nisn_penerima'] ?? '-',
                    'kelas'         => $row['kelas_penerima'] ?? '-',
                    'jurusan'       => $row['jurusan_penerima'] ?? '-',
                    'penerima'      => $row['subjek_utama'],
                    'nominal'       => floatval($row['nominal']),
                    'admin'         => 0,
                    'keterangan'    => 'Terima Dana: ' . ($row['ket'] ?? ''),
                    'petugas'       => $row['nama_petugas'] ?? 'Sistem',
                    'sumber_tabel'  => 'tabungan'
                ];
            }
        }
    }

    // =========================================================================
    // B. AMBIL DATA DARI TABEL INFAQ
    // =========================================================================
    if ($ambil_infaq) {
        $query_infaq = "
            SELECT 
                i.id_infaq AS id_unik,
                i.kode_infaq AS kode_reg,
                i.jenis_infaq AS jenis_raw,
                i.nominal_infaq AS nominal,
                i.tanggal_infaq AS waktu,
                i.keterangan AS ket,
                n.nama_nasabah AS nama_siswa,
                n.nisn AS nisn_siswa,
                n.kelas AS kelas_siswa,            
                j.nama_jurusan AS jurusan_siswa,   
                u.nama_lengkap AS nama_petugas
            FROM tbl_transaksi_infaq i
            LEFT JOIN tbl_nasabah n ON i.id_nasabah = n.id_nasabah
            LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan 
            LEFT JOIN tbl_users u ON i.id_petugas = u.id_user
            WHERE 1=1
        ";

        $params_i = [];
        if (!empty($tanggal_mulai)) {
            $query_infaq .= " AND i.tanggal_infaq >= :tgl_mulai";
            $params_i[':tgl_mulai'] = $tanggal_mulai . " 00:00:00";
        }
        if (!empty($tanggal_akhir)) {
            $query_infaq .= " AND i.tanggal_infaq <= :tgl_akhir";
            $params_i[':tgl_akhir'] = $tanggal_akhir . " 23:59:59";
        }
        if (strpos($filter_jenis, 'infaq_') === 0) {
            $sub_infaq = str_replace('infaq_', '', $filter_jenis);
            $query_infaq .= " AND i.jenis_infaq = :jenis_raw";
            $params_i[':jenis_raw'] = $sub_infaq;
        }

        $stmt_i = $pdo->prepare($query_infaq);
        $stmt_i->execute($params_i);

        while ($row = $stmt_i->fetch(PDO::FETCH_ASSOC)) {
            $semua_laporan_gabungan[] = [
                'id'            => 'I-' . $row['id_unik'],
                'kode'          => $row['kode_reg'],
                'kategori'      => 'infaq_' . $row['jenis_raw'],
                'waktu'         => $row['waktu'],
                'siswa'         => $row['nama_siswa'] ?? 'Hamba Allah (Anonim)',
                'nisn'          => $row['nisn_siswa'] ?? '-',
                'kelas'         => $row['kelas_siswa'] ?? '-',
                'jurusan'       => $row['jurusan_siswa'] ?? '-',
                'penerima'      => '-',
                'nominal'       => floatval($row['nominal']),
                'admin'         => 0,
                'keterangan'    => $row['ket'] ?? 'Infaq Tabungan',
                'petugas'       => $row['nama_petugas'] ?? 'Operator',
                'sumber_tabel'  => 'infaq'
            ];
        }
    }

    // Urutkan data berdasarkan waktu terbaru
    usort($semua_laporan_gabungan, function ($a, $b) {
        return strcmp($b['waktu'], $a['waktu']);
    });
} catch (PDOException $e) {
    error_log("Gagal menyusun kompilasi laporan: " . $e->getMessage());
}

$json_laporan_master = json_encode($semua_laporan_gabungan, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<style>
    @media print {
        .no-print {
            display: none !important;
        }

        body {
            background: white;
            color: black;
            padding: 0;
            margin: 0;
        }

        .bg-white {
            border: none !important;
            box-shadow: none !important;
        }

        #section-laporan {
            margin: 0;
            padding: 0;
        }

        .print-border {
            border: 1px solid #cbd5e1 !important;
        }
    }
</style>

<div id="section-laporan" class="space-y-6" x-data="laporanKomponen">

    <div class="no-print bg-white p-5 rounded-xl border border-slate-100 shadow-sm space-y-4">
        <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
            <i class="fas fa-sliders-h text-amber-500"></i> Pengaturan Penyaringan Laporan
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Kategori Entri</label>
                <select x-model="filterJenis" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 outline-none focus:border-blue-500 transition-colors cursor-pointer">
                    <option value="semua">--- Semua Klasifikasi ---</option>
                    <option value="setor">🟢 Setor Tunai Rekening</option>
                    <option value="tarik">🔴 Tarik Tunai Rekening</option>
                    <option value="transfer_masuk">📥 Mutasi Masuk (Transfer Trf)</option>
                    <option value="transfer_keluar">📤 Mutasi Keluar (Transfer Trf)</option>
                    <option value="infaq">Masyarakat / Rekap Infaq</option>
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Periode Makro (Cepat)</label>
                <select x-model="filterWaktuCepat" @change="setPresetWaktu()" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 outline-none focus:border-blue-500 transition-colors cursor-pointer">
                    <option value="semua">Semua Waktu Tersimpan</option>
                    <option value="hari_ini">Hari Ini (Real-Time)</option>
                    <option value="minggu_ini">Minggu Berjalan Ini</option>
                    <option value="bulan_ini">Bulan Berjalan Ini</option>
                    <option value="tahun_ini">Tahun Kalender Ini</option>
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Dari Batas Tanggal</label>
                <input type="date" x-model="filterMulai" @input="filterWaktuCepat = 'kustom'" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 outline-none focus:border-blue-500 transition-colors">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wide">Sampai Batas Tanggal</label>
                <input type="date" x-model="filterSelesai" @input="filterWaktuCepat = 'kustom'" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 outline-none focus:border-blue-500 transition-colors">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 print-border overflow-hidden shadow-sm">

        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="w-full md:w-auto">
                <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-blue-600 text-base"></i> Laporan Transaksi & Nasabah
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Verifikasi dan validasi data rekaman yang terdaftar berdasarkan penyaringan aktif.</p>
            </div>

            <div class="w-full md:w-auto flex items-center gap-2 justify-end no-print">
                <button @click="unduhExcel()" class="flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200/80 text-emerald-700 border border-slate-200/60 rounded-xl text-[11px] font-black transition-colors cursor-pointer shadow-none">
                    <i class="fas fa-file-excel text-emerald-600 text-xs"></i> Ekspor Excel
                </button>
                <button @click="window.print()" class="flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200/80 text-rose-700 border border-slate-200/60 rounded-xl text-[11px] font-black transition-colors cursor-pointer shadow-none">
                    <i class="fas fa-file-pdf text-rose-600 text-xs"></i> Unduh PDF
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed min-w-[1050px]">
                <thead class="bg-slate-50/70 border-b border-slate-100">
                    <tr>
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-center w-12">No.</th>
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider w-44">Kode & Waktu</th>
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider w-36 text-center">Jenis Transaksi</th>
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider w-72">Nasabah / Subjek Relasi</th>
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-right w-44">Nominal Pokok</th>
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider text-right w-36">Biaya Admin</th>
                        <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-wider w-64">Memo / Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <template x-if="dataDifilter.length === 0">
                        <tr>
                            <td colspan="7" class="p-8 text-center">
                                <div class="flex flex-col items-center gap-3 py-8">
                                    <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center text-slate-300">
                                        <i class="fas fa-folder-open text-xl"></i>
                                    </div>
                                    <p class="text-xs text-slate-400 font-bold">Tidak ditemukan berkas rekaman transaksi pada parameter filter ini.</p>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <template x-for="(row, index) in dataDifilter" :key="row.id">
                        <tr class="hover:bg-slate-50/40 transition-colors group text-xs text-slate-600">
                            <td class="p-4 text-center text-slate-400 font-bold group-hover:text-blue-500" x-text="(index + 1) + '.'"></td>

                            <td class="p-4 whitespace-nowrap">
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-mono font-black text-slate-700 tracking-tight" x-text="row.kode"></span>
                                    <span class="text-[10px] text-slate-500 font-semibold" x-text="formatTanggalId(row.waktu).hariTgl"></span>
                                    <span class="text-[9px] text-slate-400 font-mono" x-text="formatTanggalId(row.waktu).jamWib"></span>
                                </div>
                            </td>

                            <td class="p-4 text-center whitespace-nowrap">
                                <span :class="{
                                    'bg-emerald-50 text-emerald-600 border-emerald-100/70': row.kategori === 'setor',
                                    'bg-rose-50 text-rose-600 border-rose-100/70': row.kategori === 'tarik',
                                    'bg-blue-50 text-blue-600 border-blue-100/70': row.kategori === 'transfer_masuk',
                                    'bg-purple-50 text-purple-600 border-purple-100/70': row.kategori === 'transfer_keluar',
                                    'bg-amber-50 text-amber-700 border-amber-200/50': row.kategori.startsWith('infaq')
                                }" class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-md border tracking-wide inline-block w-28 text-center">
                                    <span x-text="
                                        row.kategori === 'setor' ? 'Setor Tunai' :
                                        (row.kategori === 'tarik' ? 'Tarik Tunai' :
                                        (row.kategori === 'transfer_masuk' ? 'Trf Masuk' :
                                        (row.kategori === 'transfer_keluar' ? 'Trf Keluar' : 'Infaq Diterima')))
                                    "></span>
                                </span>
                            </td>

                            <td class="p-4">
                                <div class="font-bold text-slate-800 truncate" :title="row.siswa" x-text="row.siswa"></div>
                                <div class="text-[10px] text-slate-500 mt-0.5 flex items-center flex-wrap gap-x-2 gap-y-0.5">
                                    <span><i class="fas fa-graduation-cap text-[10px] text-amber-500"></i></span>
                                    <span>NISN: <b x-text="row.nisn"></b></span>
                                    <span class="text-slate-300">|</span>
                                    <span>Kelas: <b x-text="row.kelas"></b></span>
                                    <span class="text-slate-300">|</span>
                                    <span>Jurusan: <b class="text-[#1258ab]" x-text="row.jurusan"></b></span>
                                </div>

                                <template x-if="row.kategori === 'transfer_keluar'">
                                    <div class="text-[10px] text-slate-400 mt-1 truncate">Ke: <b class="text-slate-500" x-text="row.penerima"></b></div>
                                </template>
                                <template x-if="row.kategori === 'transfer_masuk'">
                                    <div class="text-[10px] text-slate-400 mt-1 truncate">Pengirim: <b class="text-slate-500" x-text="row.penerima"></b></div>
                                </template>
                            </td>

                            <td class="p-4 text-right font-bold whitespace-nowrap">
                                <span :class="row.kategori === 'tarik' || row.kategori === 'transfer_keluar' ? 'text-rose-600' : 'text-emerald-600'"
                                    x-text="(row.kategori === 'tarik' || row.kategori === 'transfer_keluar' ? '-' : '+') + ' ' + formatRupiah(row.nominal)">
                                </span>
                            </td>

                            <td class="p-4 text-right font-mono text-slate-500 font-bold whitespace-nowrap">
                                <span x-text="row.admin > 0 ? formatRupiah(row.admin) : '-'"></span>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-col max-w-full">
                                    <span class="text-slate-600 italic text-[11px] truncate" :title="row.keterangan" x-text="'&ldquo; ' + row.keterangan + ' &rdquo;'"></span>
                                    <span class="text-[10px] text-slate-400 font-semibold mt-1 flex items-center gap-1">
                                        <i class="far fa-user text-[9px]"></i>Op: <span class="text-slate-600" x-text="row.petugas"></span>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="bg-slate-50/80 font-black border-t-2 border-slate-100 text-slate-700 text-xs">
                    <tr>
                        <td colspan="4" class="p-4 text-right font-black uppercase tracking-wider text-slate-400">Total Akumulasi Terfilter :</td>
                        <td class="p-4 text-right text-blue-700 font-mono font-black whitespace-nowrap text-sm" x-text="formatRupiah(ringkasanTotal.setor + ringkasanTotal.trf_masuk + ringkasanTotal.infaq - ringkasanTotal.tarik - ringkasanTotal.trf_keluar)"></td>
                        <td class="p-4 text-right text-slate-800 font-mono whitespace-nowrap text-xs" x-text="formatRupiah(ringkasanTotal.admin)"></td>
                        <td class="p-4 text-[10px] text-slate-400 font-medium italic truncate">*Nilai bersih kas</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('laporanKomponen', () => ({
            masterData: <?= $json_laporan_master ?>,
            filterJenis: 'semua',
            filterWaktuCepat: 'semua',
            filterMulai: '',
            filterSelesai: '',

            formatRupiah(angka) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: 0
                }).format(angka);
            },

            formatTanggalId(dateString) {
                if (!dateString) return {
                    hariTgl: '-',
                    jamWib: ''
                };
                const hariNama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const bulanNama = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                let d = new Date(dateString);
                if (isNaN(d.getTime())) return {
                    hariTgl: dateString,
                    jamWib: ''
                };
                return {
                    hariTgl: `${hariNama[d.getDay()]}, ${String(d.getDate()).padStart(2, '0')} ${bulanNama[d.getMonth()]} ${d.getFullYear()}`,
                    jamWib: `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')} WIB`
                };
            },

            setPresetWaktu() {
                const sekarang = new Date();
                const yyyy = sekarang.getFullYear();
                const mm = String(sekarang.getMonth() + 1).padStart(2, '0');
                const dd = String(sekarang.getDate()).padStart(2, '0');
                const formatTgl = (d) => d.toISOString().substring(0, 10);

                if (this.filterWaktuCepat === 'hari_ini') {
                    this.filterMulai = `${yyyy}-${mm}-${dd}`;
                    this.filterSelesai = `${yyyy}-${mm}-${dd}`;
                } else if (this.filterWaktuCepat === 'minggu_ini') {
                    const day = sekarang.getDay();
                    const diff = sekarang.getDate() - day + (day === 0 ? -6 : 1);
                    const senin = new Date(sekarang.setDate(diff));
                    const minggu = new Date(senin);
                    minggu.setDate(minggu.getDate() + 6);
                    this.filterMulai = formatTgl(senin);
                    this.filterSelesai = formatTgl(minggu);
                } else if (this.filterWaktuCepat === 'bulan_ini') {
                    this.filterMulai = `${yyyy}-${mm}-01`;
                    this.filterSelesai = `${yyyy}-${mm}-${new Date(yyyy, sekarang.getMonth() + 1, 0).getDate()}`;
                } else if (this.filterWaktuCepat === 'tahun_ini') {
                    this.filterMulai = `${yyyy}-01-01`;
                    this.filterSelesai = `${yyyy}-12-31`;
                } else {
                    this.filterMulai = '';
                    this.filterSelesai = '';
                }
            },

            unduhExcel() {
                if (this.dataDifilter.length === 0) {
                    alert('Tidak ada entri data yang cocok untuk diekspor!');
                    return;
                }

                const header = ['No', 'Kode Registrasi', 'Waktu Penginputan', 'Kategori Jenis', 'Nama Nasabah/Relasi', 'NISN', 'Kelas', 'Jurusan', 'Nominal Pokok', 'Biaya Admin', 'Keterangan Memo', 'Operator Kasir'];

                const baris = this.dataDifilter.map((item, index) => [
                    index + 1,
                    `"${item.kode}"`,
                    `"${item.waktu}"`,
                    item.kategori.toUpperCase(),
                    `"${item.siswa}"`,
                    `"${item.nisn}"`,
                    item.kelas,
                    `"${item.jurusan}"`,
                    item.nominal,
                    item.admin,
                    `"${item.keterangan.replace(/"/g, '""')}"`,
                    `"${item.petugas}"`
                ]);

                const isiKonten = [header.join('\t'), ...baris.map(e => e.join('\t'))].join('\n');
                const blob = new Blob([isiKonten], {
                    type: 'text/xls;charset=utf-8;'
                });
                const link = document.createElement("a");
                const url = URL.createObjectURL(blob);

                link.setAttribute("href", url);
                link.setAttribute("download", `Laporan_Gabungan_${this.filterJenis}_${new Date().toISOString().substring(0,10)}.xls`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            },

            get dataDifilter() {
                return this.masterData.filter(item => {
                    let cocokJenis = true;
                    if (this.filterJenis !== 'semua') {
                        if (this.filterJenis === 'infaq' && !item.kategori.startsWith('infaq')) cocokJenis = false;
                        else if (this.filterJenis !== 'infaq' && item.kategori !== this.filterJenis) cocokJenis = false;
                    }

                    let cocokTanggal = true;
                    if (item.waktu) {
                        let tglItem = item.waktu.substring(0, 10);
                        if (this.filterMulai && tglItem < this.filterMulai) cocokTanggal = false;
                        if (this.filterSelesai && tglItem > this.filterSelesai) cocokTanggal = false;
                    }
                    return cocokJenis && cocokTanggal;
                });
            },

            get ringkasanTotal() {
                let akumulasi = {
                    setor: 0,
                    tarik: 0,
                    trf_masuk: 0,
                    trf_keluar: 0,
                    infaq: 0,
                    admin: 0
                };
                this.dataDifilter.forEach(item => {
                    akumulasi.admin += item.admin;
                    if (item.kategori === 'setor') akumulasi.setor += item.nominal;
                    else if (item.kategori === 'tarik') akumulasi.tarik += item.nominal;
                    else if (item.kategori === 'transfer_masuk') akumulasi.trf_masuk += item.nominal;
                    else if (item.kategori === 'transfer_keluar') akumulasi.trf_keluar += item.nominal;
                    else if (item.kategori.startsWith('infaq')) akumulasi.infaq += item.nominal;
                });
                return akumulasi;
            }
        }));
    });
</script>