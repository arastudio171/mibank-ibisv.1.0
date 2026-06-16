<?php
// Fungsi untuk format tanggal Indonesia
function tgl_indo($tanggal)
{
    if (!$tanggal) return '-';
    $hari = array(1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu');
    $bulan = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
    $timestamp = strtotime($tanggal);
    $num = date('N', $timestamp);
    return $hari[$num] . ', ' . date('d', $timestamp) . ' ' . $bulan[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

// FINAL UPDATE: Menggunakan n.kelas dan JOIN ke tbl_jurusan yang sesuai dengan database Anda
$query = "SELECT n.*, j.kode_jurusan, j.nama_jurusan,
                 (SELECT COUNT(*) FROM tbl_target_tabungan t 
                  WHERE t.id_nasabah = n.id_nasabah AND t.status_target = 'active') as jml_target,
                 (SELECT GROUP_CONCAT(nama_target SEPARATOR ', ') FROM tbl_target_tabungan t 
                  WHERE t.id_nasabah = n.id_nasabah AND t.status_target = 'active') as daftar_target
          FROM tbl_nasabah n 
          LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan 
          ORDER BY n.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $data_nasabah = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Amankan pesan error agar tidak mengekspos detail query ke user, catat di log server
    error_log("Error Fetch Nasabah Target: " . $e->getMessage());
    die("Terjadi gangguan saat memuat data nasabah.");
}
?>

<div class="space-y-6">

    <?php if (isset($_GET['msg']) && isset($_GET['type'])): ?>
        <?php
        $msg = htmlspecialchars($_GET['msg']);
        $type = $_GET['type'];

        // Menentukan desain berdasarkan tipe (success / error)
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
        <!-- Header Tabel -->
        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">

            <!-- Bagian Kiri: Judul & Deskripsi -->
            <div class="w-full md:w-auto">
                <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                    <i class="fas fa-database text-blue-500"></i> Data Nasabah
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Verifikasi dan validasi data nasabah yang terdaftar.</p>
            </div>

            <!-- Bagian Kanan: Aksi & Statistik -->
            <div class="w-full md:w-auto flex items-center gap-3 justify-end">
                <span class="text-[10px] bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg font-bold">
                    Total Nasabah Terdaftar: <b><?= count($data_nasabah) ?></b>
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 text-center">No.</th>
                        <th class="p-4">Identitas Nasabah</th>
                        <th class="p-4">Kelas & Jurusan</th>
                        <th class="p-4">Waktu Pendaftaran</th>
                        <th class="p-4 text-center">Status Nasabah</th>
                        <th class="p-4">Saldo & Target</th>
                        <th class="p-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($data_nasabah)): $no = 1;
                        foreach ($data_nasabah as $item):
                            // 1. SINKRONISASI DATABASE: Ambil status asli ('aktif' atau 'nonaktif')
                            $status = $item['status_nasabah'] ?? 'nonaktif';

                            // 2. Logika warna badge (Hanya ada aktif dan nonaktif)
                            if ($status === 'aktif') {
                                $badge_class = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                                $icon_class  = 'fa-check-circle';
                            } else {
                                // Otomatis nonaktif
                                $badge_class = 'bg-rose-50 text-rose-600 border-rose-100';
                                $icon_class  = 'fa-user-slash';
                            }

                            // --- KODE TAMBAHAN BARU (LOGIKA PERSENTASE) ---
                            $kolom_diperiksa = ['nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'jenjang_pendidikan', 'telepon', 'email', 'alamat'];
                            $total_kolom = count($kolom_diperiksa);
                            $terisi = 0;

                            foreach ($kolom_diperiksa as $kolom) {
                                if (isset($item[$kolom]) && trim($item[$kolom]) !== '' && $item[$kolom] !== null) {
                                    $terisi++;
                                }
                            }

                            $persen_lengkap = ($terisi / $total_kolom) * 100;

                            if ($persen_lengkap < 50) {
                                $progress_color = 'bg-rose-500';
                                $text_color     = 'text-rose-600';
                                $pesan_notif    = '⚠️ Data kritis, mohon lengkapi!';
                            } elseif ($persen_lengkap < 100) {
                                $progress_color = 'bg-amber-500';
                                $text_color     = 'text-amber-600';
                                $pesan_notif    = '⏳ Belum lengkap';
                            } else {
                                $progress_color = 'bg-emerald-500';
                                $text_color     = 'text-emerald-600';
                                $pesan_notif    = '✅ 100% Lengkap';
                            }
                    ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400"><?= $no++ ?></td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <i class="fas fa-user-circle text-amber-300"></i> <?= htmlspecialchars($item['nama_nasabah']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-1"><i class="fas fa-id-card mr-1"></i> NISN: <?= $item['nisn'] ?></div>
                                    <div class="mt-2 max-w-[180px]">
                                        <div class="flex items-center justify-between text-[9px] font-bold mb-1">
                                            <span class="<?= $text_color ?>"><?= $persen_lengkap ?>% Terisi</span>
                                            <span class="text-[8px] font-medium text-slate-400"><?= $pesan_notif ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1">
                                            <div class="<?= $progress_color ?> h-1 rounded-full transition-all duration-500" style="width: <?= $persen_lengkap ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2 text-slate-700 font-bold mb-1"><i class="fas fa-chalkboard text-slate-400 text-[10px]"></i> <?= $item['kelas'] ?? '-' ?> <?= $item['kode_jurusan'] ?? '' ?></div>
                                    <div class="text-[10px] text-slate-500"><i class="fas fa-graduation-cap mr-1"></i> <?= $item['nama_jurusan'] ?? '-' ?></div>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <i class="far fa-calendar-check text-emerald-600"></i> <?= tgl_indo($item['created_at']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-1"><i class="fas fa-clock mr-1"></i> Waktu: <?= date('H:i:s', strtotime($item['created_at'])) ?> WIB</div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase inline-flex items-center <?= $badge_class ?>">
                                        <i class="fas <?= $icon_class ?> mr-1"></i>
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <?php
                                    $nilai_saldo = (float)($item['saldo'] ?? 0);
                                    if ($nilai_saldo < 15000) {
                                        $warna_saldo = 'text-rose-600';
                                    } elseif ($nilai_saldo < 25000) {
                                        $warna_saldo = 'text-amber-500';
                                    } else {
                                        $warna_saldo = 'text-emerald-600';
                                    }
                                    $format_saldo = 'Rp ' . number_format($nilai_saldo, 2, ',', '.');
                                    ?>

                                    <div class="font-black tabular-nums <?= $warna_saldo ?>">
                                        <?= $format_saldo ?>
                                    </div>

                                    <?php if (!empty($item['daftar_target'])): ?>
                                        <div class="mt-1 text-[10px] text-slate-600 font-bold">
                                            <i class="fas fa-bullseye mr-1"></i> Target: <?= htmlspecialchars($item['daftar_target']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-1 text-[10px] text-slate-400">Tidak ada target aktif</div>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <?php if ($status !== 'aktif'): ?>
                                            <a href="modules/nasabah/aktifkan-nasabah.php?id=<?= $item['id_nasabah'] ?>"
                                                onclick="return confirm('Apakah Anda yakin ingin mengaktifkan akun nasabah ini?')"
                                                class="text-[9px] font-bold bg-emerald-600 text-white px-3 py-1.5 rounded-lg hover:opacity-90 transition-colors inline-flex items-center">
                                                <i class="fas fa-check mr-1"></i> AKTIFKAN
                                            </a>
                                        <?php else: ?>
                                            <button type="button"
                                                class="btn-detail text-[9px] font-bold bg-[#1566c7] text-white px-3 py-1.5 rounded-lg hover:bg-[#1a78e8] transition-colors inline-flex items-center tracking-wider"
                                                data-id="<?= $item['id_nasabah'] ?>"
                                                data-nama="<?= htmlspecialchars($item['nama_nasabah']) ?>"
                                                data-nisn="<?= htmlspecialchars($item['nisn']) ?>"
                                                data-nik="<?= htmlspecialchars($item['nik'] ?? 'Belum Diisi') ?>"
                                                data-ibu="<?= htmlspecialchars($item['nama_ibu_kandung']) ?>"
                                                data-ttl="<?= htmlspecialchars(($item['tempat_lahir'] ?? '-') . ', ' . ($item['tanggal_lahir'] ? tgl_indo($item['tanggal_lahir']) : '-')) ?>"
                                                data-jk="<?= $item['jenis_kelamin'] === 'L' ? 'Laki-laki' : ($item['jenis_kelamin'] === 'P' ? 'Perempuan' : '-') ?>"
                                                data-jenjang="<?= htmlspecialchars($item['jenjang_pendidikan'] ?? '-') ?>"
                                                data-kelas="<?= htmlspecialchars($item['kelas'] ?? '-') ?> - <?= htmlspecialchars($item['kode_jurusan'] ?? '') ?>"
                                                data-jurusan="<?= htmlspecialchars($item['nama_jurusan'] ?? '-') ?>"
                                                data-saldo="<?= $format_saldo ?>"
                                                data-target="<?= htmlspecialchars($item['daftar_target'] ?? 'Tidak ada target aktif') ?>"
                                                data-telepon="<?= htmlspecialchars($item['telepon'] ?? 'Tidak ada') ?>"
                                                data-email="<?= htmlspecialchars($item['email'] ?? 'Tidak ada') ?>"
                                                data-alamat="<?= htmlspecialchars($item['alamat'] ?? 'Alamat belum dilengkapi.') ?>"
                                                data-status="<?= htmlspecialchars($status) ?>"
                                                data-locked="<?= $item['is_locked'] ? 'TERKUNCI' : 'NORMAL' ?>"
                                                data-failed="<?= $item['pin_failed_attempts'] ?>"
                                                data-login="<?= $item['last_login'] ? tgl_indo($item['last_login']) . ' ' . date('H:i:s', strtotime($item['last_login'])) . ' WIB' : 'Belum pernah login' ?>"
                                                data-created="<?= tgl_indo($item['created_at']) ?> pada <?= date('H:i:s', strtotime($item['created_at'])) ?> WIB">
                                                <i class="fas fa-history mr-1"></i> DETAIL NASABAH
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">Belum ada data nasabah.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Footer Informatif -->
        <div class="p-4 bg-slate-50 text-[10px] text-slate-400 border-t border-slate-100 flex justify-between">
            <span>* Pastikan melakukan verifikasi data sebelum tindakan.</span>
            <span>Total Nasabah Terdaftar: <?= count($data_nasabah) ?> orang.</span>
        </div>
    </div>
</div>

<div id="modal-detail-nasabah" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none flex items-center justify-center bg-black/50 p-4 transition-all duration-300 ease-out overflow-y-auto">

    <div id="modal-content" class="bg-slate-50 rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden transform scale-95 opacity-0 transition-all duration-300 ease-out my-8">

        <div class="bg-[#1566c7] p-5 text-white flex justify-between items-center shadow-md">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-university text-amber-300"></i> Informasi Nasabah
                </h3>
                <p class="text-[10px] text-slate-200 mt-0.5 font-medium">Laporan rincian biodata, finansial, dan status keamanan akun internal.</p>
            </div>
            <button type="button" onclick="closeModalDetail()" class="text-white opacity-80 hover:opacity-100 transition-opacity text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">

            <div class="bg-white p-4 rounded-xl border border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-4 shadow-sm">
                <div class="border-r border-dashed border-slate-100 pr-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1"><i class="fas fa-wallet mr-1 text-emerald-500"></i> Total Saldo Tabungan</span>
                    <h2 id="md-saldo" class="text-xl font-black text-emerald-600 tabular-nums">Rp 0,00</h2>
                    <span id="md-target" class="text-[10px] text-slate-500 font-medium block mt-1">Target: -</span>
                </div>
                <div class="pl-0 sm:pl-2 flex flex-col justify-center">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Status Akun:</span>
                        <span id="md-status-badge" class="px-2 py-0.5 rounded text-[9px] font-black uppercase border">ACTIVE</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Proteksi PIN:</span>
                        <span id="md-locked-badge" class="px-2 py-0.5 rounded text-[9px] font-black uppercase border">NORMAL</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm space-y-2.5 text-xs">
                    <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5 mb-2 flex items-center gap-1.5">
                        <i class="fas fa-user text-amber-500"></i> Identitas Diri
                    </h4>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium">Nama Lengkap</span>
                        <span id="md-nama" class="font-bold text-slate-800">-</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium">Nomor Induk Siswa Nasional (NISN)</span>
                        <span id="md-nisn" class="font-mono font-bold text-slate-700">-</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium">Nomor Induk Kependudukan (NIK)</span>
                        <span id="md-nik" class="font-mono font-bold text-slate-700">-</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium">Nama Ibu Kandung</span>
                        <span id="md-ibu" class="font-medium text-slate-800">-</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium">Tempat, Tanggal Lahir</span>
                        <span id="md-ttl" class="font-medium text-slate-800">-</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium">Jenis Kelamin</span>
                        <span id="md-jk" class="font-medium text-slate-800">-</span>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm space-y-2.5 text-xs flex-1">
                        <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5 mb-2 flex items-center gap-1.5">
                            <i class="fas fa-graduation-cap text-blue-500"></i> Data Pendidikan
                        </h4>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-slate-400 font-medium">Jenjang Sekolah</span>
                            <span id="md-jenjang" class="font-bold text-slate-800">-</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-slate-400 font-medium">Kelas / Ruang</span>
                            <span id="md-kelas" class="font-bold text-slate-800">-</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-slate-400 font-medium">Kompetensi Keahlian (Jurusan)</span>
                            <span id="md-jurusan" class="font-medium text-slate-600">-</span>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm space-y-2.5 text-xs">
                        <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5 mb-2 flex items-center gap-1.5">
                            <i class="fas fa-shield-alt text-rose-500"></i> Keamanan & Log Sistem
                        </h4>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] text-slate-400 font-medium">Gagal Akses PIN</span>
                            <span id="md-failed" class="font-bold text-rose-600 font-mono bg-rose-50 px-1.5 py-0.5 rounded">-</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-slate-400 font-medium">Sesi Login Terakhir</span>
                            <span id="md-login" class="font-mono text-slate-600 text-[11px]">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm text-xs space-y-3">
                <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5 flex items-center gap-1.5">
                    <i class="fas fa-address-book text-emerald-500"></i> Informasi Kontak & Alamat Domisili
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium"><i class="fas fa-phone text-slate-400 mr-1"></i> No. Telepon / WA</span>
                        <span id="md-telepon" class="font-bold text-slate-800 font-mono">-</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-slate-400 font-medium"><i class="fas fa-envelope text-slate-400 mr-1"></i> Email Aktif</span>
                        <span id="md-email" class="font-bold text-slate-800 font-mono break-all">-</span>
                    </div>
                </div>
                <div class="flex flex-col pt-1.5 border-t border-slate-50">
                    <span class="text-[10px] text-slate-400 font-medium"><i class="fas fa-map-marker-alt text-slate-400 mr-1"></i> Alamat Rumah Lengkap</span>
                    <p id="md-alamat" class="text-slate-700 font-medium leading-relaxed mt-0.5 bg-slate-50 p-2.5 rounded-lg border border-slate-100">-</p>
                </div>
            </div>

            <div class="text-center text-[10px] text-slate-400 font-medium font-mono pt-1">
                <i class="fas fa-history mr-1"></i> Akun dibuat pada: <span id="md-created" class="text-slate-500 font-bold">-</span>
            </div>
        </div>

        <div class="bg-slate-100 px-5 py-3.5 border-t border-slate-200">
            <button type="button" onclick="closeModalDetail()"
                class="w-full flex items-center justify-center bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase">
                <i class="fas fa-folder-minus mr-2"></i> Tutup Lembar Informasi
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const detailButtons = document.querySelectorAll('.btn-detail');
        const modal = document.getElementById('modal-detail-nasabah');
        const modalContent = document.getElementById('modal-content');

        detailButtons.forEach(button => {
            button.addEventListener('click', function() {
                // 1. Ambil seluruh data- dari tombol
                const nama = this.getAttribute('data-nama');
                const nisn = this.getAttribute('data-nisn');
                const nik = this.getAttribute('data-nik');
                const ibu = this.getAttribute('data-ibu');
                const ttl = this.getAttribute('data-ttl');
                const jk = this.getAttribute('data-jk');
                const jenjang = this.getAttribute('data-jenjang');
                const kelas = this.getAttribute('data-kelas');
                const jurusan = this.getAttribute('data-jurusan');
                const saldo = this.getAttribute('data-saldo');
                const target = this.getAttribute('data-target');
                const telepon = this.getAttribute('data-telepon');
                const email = this.getAttribute('data-email');
                const alamat = this.getAttribute('data-alamat');
                const status = this.getAttribute('data-status');
                const locked = this.getAttribute('data-locked');
                const failed = this.getAttribute('data-failed');
                const login = this.getAttribute('data-login');
                const created = this.getAttribute('data-created');

                // 2. Pasang teks ke elemen modal
                document.getElementById('md-nama').textContent = nama;
                document.getElementById('md-nisn').textContent = nisn;
                document.getElementById('md-nik').textContent = nik;
                document.getElementById('md-ibu').textContent = ibu;
                document.getElementById('md-ttl').textContent = ttl;
                document.getElementById('md-jk').textContent = jk;
                document.getElementById('md-jenjang').textContent = jenjang;
                document.getElementById('md-kelas').textContent = kelas;
                document.getElementById('md-jurusan').textContent = jurusan;
                document.getElementById('md-saldo').textContent = saldo;
                document.getElementById('md-target').textContent = "Target Tabungan: " + target;
                document.getElementById('md-telepon').textContent = telepon;
                document.getElementById('md-email').textContent = email;
                document.getElementById('md-alamat').textContent = alamat;
                document.getElementById('md-failed').textContent = failed + " Kali Percobaan";
                document.getElementById('md-login').textContent = login;
                document.getElementById('md-created').textContent = created;

                // 3. Style dinamis untuk badge status nasabah
                const statusBadge = document.getElementById('md-status-badge');
                statusBadge.textContent = status;
                if (status.toLowerCase() === 'aktif') {
                    statusBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 border-emerald-200";
                } else {
                    statusBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-100 text-rose-700 border-rose-200";
                }

                // 4. Style dinamis untuk badge kunci proteksi PIN
                const lockedBadge = document.getElementById('md-locked-badge');
                lockedBadge.textContent = locked;
                if (locked.toLowerCase() === 'normal') {
                    lockedBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-slate-100 text-slate-700 border-slate-200";
                } else {
                    lockedBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-500 text-white border-rose-600 animate-pulse";
                }

                // 5. MEMICU ANIMASI MASUK (SMOOTH FADE IN & ZOOM)
                // Hilangkan pemblokir visibilitas pada background luar
                modal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');

                // Masukkan efek zoom membesar lembut dan munculkan card utama
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            });
        });
    });

    // Fungsi untuk menutup modal dengan animasi keluar yang halus
    function closeModalDetail() {
        const modal = document.getElementById('modal-detail-nasabah');
        const modalContent = document.getElementById('modal-content');

        // Kembalikan card utama ke posisi mengecil & transparan
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');

        // Sembunyikan background luar setelah efek card selesai bekerja
        modal.classList.add('invisible', 'opacity-0', 'pointer-events-none');
    }
</script>