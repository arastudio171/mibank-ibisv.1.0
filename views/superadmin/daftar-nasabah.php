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
    error_log("Error Fetch Nasabah Target: " . $e->getMessage());
    die("Terjadi gangguan saat memuat data nasabah.");
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
                    <i class="fas fa-database text-[#1257aa]"></i> Manajemen Data Nasabah
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Kelola data biodata, penyesuaian status aktif, perubahan data, dan penghapusan akun.</p>
            </div>

            <div class="w-full md:w-auto flex flex-wrap items-center gap-3 justify-end">
                <div class="relative w-full sm:w-auto">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-[10px]"></i>
                    </span>
                    <input type="text" id="inputCariNasabah" placeholder="Cari nama, NISN, kelas..."
                        class="pl-8 pr-3 py-2 w-full sm:w-56 text-[10px] bg-white border border-slate-200 rounded-lg text-slate-600 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-bold">
                </div>

                <!-- <span class="text-[10px] bg-slate-100 text-slate-600 px-3 py-2 rounded-lg font-bold">
                    Total: <b id="totalBadge"><?= count($data_nasabah) ?></b> Nasabah
                </span> -->
                <a href="?page=tambah-nasabah" class="text-[10px] font-black bg-[#1257aa] text-white px-3.5 py-2 rounded-lg transition-all shadow-sm flex items-center gap-1.5 tracking-wider uppercase">
                    <i class="fas fa-user-plus text-xs"></i> Tambah Nasabah
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="tabelNasabah">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 text-center">No.</th>
                        <th class="p-4">Identitas Nasabah</th>
                        <th class="p-4">Kelas & Jurusan</th>
                        <th class="p-4">Waktu Pendaftaran</th>
                        <th class="p-4 text-center">Status Nasabah</th>
                        <th class="p-4">Saldo & Target</th>
                        <th class="p-4 text-center">Kontrol Akses</th>
                        <th class="p-4 text-center">Manajemen Data</th>
                        <th class="p-4 text-center">Keamanan Akun</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($data_nasabah)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($data_nasabah as $item): ?>
                            <?php
                            $status = $item['status_nasabah'] ?? 'nonaktif';

                            if ($status === 'aktif') {
                                $badge_class = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                                $icon_class  = 'fa-check-circle';
                            } else {
                                $badge_class = 'bg-rose-50 text-rose-600 border-rose-100';
                                $icon_class  = 'fa-user-slash';
                            }

                            // Logika Persentase Data Kelengkapan
                            $kolom_diperiksa = ['nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'jenjang_pendidikan', 'telepon', 'email', 'alamat'];
                            $total_kolom = count($kolom_diperiksa);
                            $terisi = 0;

                            foreach ($kolom_diperiksa as $kolom) {
                                if (isset($item[$kolom]) && trim($item[$kolom]) !== '' && $item[$kolom] !== null) {
                                    $terisi++;
                                }
                            }

                            $persen_lengkap = ($terisi / $total_kolom) * 100;

                            // TEKS KUSTOMISASI ANDA DI SINI
                            if ($persen_lengkap < 50) {
                                $progress_color = 'bg-rose-500';
                                $text_color     = 'text-rose-600';
                                $pesan_notif    = '⚠️ Data belum lengkap!';
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

                            <tr class="row-nasabah hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400 target-no"><?= $no++ ?></td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2 flex-wrap">

                                        <?php if (isset($item['is_locked']) && $item['is_locked'] == 1): ?>
                                            <i class="fas fa-lock text-rose-500 text-sm animate-shake" title="Akun Terkunci"></i>
                                        <?php else: ?>
                                            <i class="fas fa-lock-open text-emerald-500 text-sm" title="Akun Terbuka & Aktif"></i>
                                        <?php endif; ?>

                                        <span class="target-nama"><?= htmlspecialchars($item['nama_nasabah']) ?></span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-1">
                                        <i class="fas fa-id-card text-indigo-500 mr-1"></i> NISN: <span class="target-nisn"><?= $item['nisn'] ?></span>
                                    </div>
                                    <div class="mt-2 max-w-[150px]">
                                        <div class="flex items-center justify-between text-[9px] font-bold mb-0.5">
                                            <span class="<?= $text_color ?>"><?= $persen_lengkap ?>% Terisi</span>
                                            <span class="text-[8px] font-medium text-slate-400"><?= $pesan_notif ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1">
                                            <div class="<?= $progress_color ?> h-1 rounded-full transition-all duration-500" style="width: <?= $persen_lengkap ?>%"></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="flex items-center gap-2 text-slate-700 font-bold mb-1">
                                        <i class="fas fa-chalkboard text-slate-400 text-[10px]"></i> <span class="target-kelas"><?= $item['kelas'] ?? '-' ?> <?= $item['kode_jurusan'] ?? '' ?></span>
                                    </div>
                                    <div class="text-[10px] text-slate-500">
                                        <i class="fas fa-graduation-cap text-amber-500 mr-1"></i> <?= $item['nama_jurusan'] ?? '-' ?> (<?= $item['kode_jurusan'] ?? '-' ?>)
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <i class="far fa-calendar-check text-slate-400"></i> <?= tgl_indo($item['created_at']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-1">
                                        <i class="fas fa-clock text-emerald-500 mr-1"></i> Waktu: <?= date('H:i:s', strtotime($item['created_at'])) ?> WIB
                                    </div>
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
                                        <div class="mt-1 text-[10px] text-slate-600 font-bold max-w-[150px] truncate">
                                            <i class="fas fa-bullseye mr-1 text-rose-500"></i> <?= htmlspecialchars($item['daftar_target']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-1 text-[10px] text-slate-400">
                                            <i class="fas fa-bullseye text-rose-500 mr-1"></i>
                                            Tidak ada target aktif
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-center">
                                    <?php if ($status === 'aktif'): ?>
                                        <a href="modules/nasabah/toggle-status.php?id=<?= $item['id_nasabah'] ?>&action=nonaktif"
                                            onclick="return confirm('Apakah Anda yakin ingin MENONAKTIFKAN nasabah ini? Siswa tidak akan bisa melakukan transaksi tabungan sementara waktu.')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 hover:bg-rose-100 hover:text-rose-700 transition-colors font-bold text-[10px]"
                                            title="Klik untuk Nonaktifkan">
                                            <i class="fas fa-toggle-on text-xs"></i> Aktif
                                        </a>
                                    <?php else: ?>
                                        <a href="modules/nasabah/toggle-status.php?id=<?= $item['id_nasabah'] ?>&action=aktif"
                                            onclick="return confirm('Aktifkan akun nasabah ini sekarang?')"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-200 text-slate-600 hover:bg-emerald-600 hover:text-white transition-colors font-bold text-[10px]"
                                            title="Klik untuk Aktifkan">
                                            <i class="fas fa-toggle-off text-xs"></i> Nonaktif
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center gap-1 p-1 rounded-xl">
                                        <button type="button"
                                            class="btn-detail w-7 h-7 flex items-center justify-center rounded-lg bg-white text-emerald-600 hover:bg-emerald-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Buka Informasi Rinci"
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
                                            <i class="fas fa-eye text-[11px]"></i>
                                        </button>

                                        <a href="main.php?page=edit-nasabah&id=<?= $item['id_nasabah'] ?>"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-amber-500 hover:bg-amber-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Ubah Biodata Nasabah">
                                            <i class="fas fa-edit text-[11px]"></i>
                                        </a>

                                        <a href="modules/nasabah/delete-nasabah.php?id=<?= $item['id_nasabah'] ?>"
                                            onclick="return confirm('🚨 PERHATIAN KRITIS!\n\nApakah Anda yakin ingin menghapus data nasabah: <?= addslashes($item['nama_nasabah']) ?>?\n\nTindakan ini bersifat PERMANEN dan akan menghapus data saldo serta riwayat mutasi terkait di database.')"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-red-600 hover:bg-red-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Hapus Akun Permanen">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </a>
                                    </div>
                                </td>

                                <td class="p-4 text-center">
                                    <a href="modules/nasabah/reset-password.php?id=<?= $item['id_nasabah'] ?>"
                                        onclick="return confirm('Reset password akun nasabah ini ke default? Pastikan untuk memberitahu siswa agar segera mengganti password setelah login kembali.')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-[#1257aa] hover:bg-blue-50 shadow-sm transition-all font-bold text-[10px] whitespace-nowrap"
                                        title="Reset Password ke Default">
                                        <i class="fas fa-key text-[11px] text-[#1257aa]"></i> Reset Akun
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="rowTidakDitemukan" class="hidden">
                            <td colspan="8" class="p-8 text-center text-slate-400">Tidak ada data nasabah yang cocok dengan pencarian.</td>
                        </tr>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400">Belum ada data nasabah.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="p-4 bg-slate-50 text-[10px] text-slate-400 border-t border-slate-100 flex justify-between">
            <span>* Gunakan kontrol manajemen di atas dengan penuh tanggung jawab demi keamanan finansial siswa.</span>
            <span>Total Entri Terdata: <span id="totalFooter"><?= count($data_nasabah) ?></span> orang.</span>
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

        <div class="bg-slate-100 px-5 py-3.5 border-t border-slate-200 flex flex-col gap-2">
            <button type="button" onclick="closeModalDetail()"
                class="w-full flex items-center justify-center bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase">
                <i class="fas fa-folder-minus mr-2"></i> Tutup Lembar Informasi
            </button>

            <!-- <a href="#" id="btnResetPassword"
                onclick="return confirm('🚨 TINDAKAN KEAMANAN!\n\nApakah Anda yakin ingin mereset password nasabah ini?\nPassword akan dikembalikan ke standar bawaan sistem: 123456')"
                class="w-full flex items-center justify-center bg-amber-600 hover:bg-amber-500 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase text-center decoration-none">
                <i class="fas fa-key mr-2"></i> Reset Password Akun
            </a> -->
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

                const statusBadge = document.getElementById('md-status-badge');
                statusBadge.textContent = status;
                if (status.toLowerCase() === 'aktif') {
                    statusBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 border-emerald-200";
                } else {
                    statusBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-100 text-rose-700 border-rose-200";
                }

                const lockedBadge = document.getElementById('md-locked-badge');
                lockedBadge.textContent = locked;
                if (locked.toLowerCase() === 'normal') {
                    lockedBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-slate-100 text-slate-700 border-slate-200";
                } else {
                    lockedBadge.className = "px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-500 text-white border-rose-600 animate-pulse";
                }

                modal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            });
        });
    });

    function closeModalDetail() {
        const modal = document.getElementById('modal-detail-nasabah');
        const modalContent = document.getElementById('modal-content');
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        modal.classList.add('invisible', 'opacity-0', 'pointer-events-none');
    }

    // var idNasabah = this.getAttribute('data-id');
    // document.getElementById('btnResetPassword').href = 'modules/nasabah/reset-password.php?id=' + idNasabah;

    document.addEventListener("DOMContentLoaded", function() {
        const inputCari = document.getElementById("inputCariNasabah");
        const rows = document.querySelectorAll(".row-nasabah");
        const rowTidakDitemukan = document.getElementById("rowTidakDitemukan");
        const totalBadge = document.getElementById("totalBadge");
        const totalFooter = document.getElementById("totalFooter");

        if (inputCari) {
            inputCari.addEventListener("input", function() {
                const keyword = this.value.toLowerCase().trim();
                let jumlahCocok = 0;

                rows.forEach((row) => {
                    // Mengambil text dari nama, nisn, dan kelas agar akurat saat dicari
                    const nama = row.querySelector(".target-nama") ? row.querySelector(".target-nama").textContent.toLowerCase() : "";
                    const nisn = row.querySelector(".target-nisn") ? row.querySelector(".target-nisn").textContent.toLowerCase() : "";
                    const kelas = row.querySelector(".target-kelas") ? row.querySelector(".target-kelas").textContent.toLowerCase() : "";

                    // Gabungkan string untuk dicocokkan
                    if (nama.includes(keyword) || nisn.includes(keyword) || kelas.includes(keyword)) {
                        row.classList.remove("hidden");
                        jumlahCocok++;
                    } else {
                        row.classList.add("hidden");
                    }
                });

                // Update counter total data sesuai dengan yang tampil akibat filter cari
                if (totalBadge) totalBadge.textContent = jumlahCocok;
                if (totalFooter) totalFooter.textContent = jumlahCocok;

                // Jika tidak ada data yang cocok, tampilkan baris info kosong
                if (jumlahCocok === 0 && rows.length > 0) {
                    rowTidakDitemukan.classList.remove("hidden");
                } else {
                    rowTidakDitemukan.classList.add("hidden");
                }
            });
        }
    });
</script>