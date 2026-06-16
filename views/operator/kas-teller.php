<?php
// 1. Ambil data user yang sedang login dari session sistem Anda
$user_login_id   = $_SESSION['id_user'] ?? 0;
$user_login_role = $_SESSION['nama_role'] ?? 'operator';

// QUERY SUMMARY DASHBOARD (DIOPTIMALKAN)
// Mengambil summary real-time langsung dari laci kas yang sedang aktif/open
$summary_query = "SELECT 
    SUM(saldo_awal_laci) as total_awal,
    SUM(saldo_akhir_laci) as total_kas,
    SUM(total_setoran_tunai) as total_setor,
    SUM(total_penarikan) as total_tarik
    FROM tbl_jurnal_kas 
    WHERE status_jurnal = 'open'";

$stmt_sum = $pdo->query($summary_query);
$sum = $stmt_sum->fetch(PDO::FETCH_ASSOC);

// Variabel penentu balance (jika null, otomatis berikan nilai 0)
$total_awal  = $sum['total_awal'] ?? 0;
$total_kas   = $sum['total_kas'] ?? 0;
$total_setor = $sum['total_setor'] ?? 0;
$total_tarik = $sum['total_tarik'] ?? 0;

// Rumus ekspektasi kas menurut hitungan sistem
$ekspektasi_kas = $total_awal + $total_setor - $total_tarik;

// Cek apakah balance atau ada selisih 
// Menggunakan round() agar perbandingan tipe data decimal/float akurat murni hingga 2 angka di belakang koma
$is_balanced = (round($total_kas, 2) == round($ekspektasi_kas, 2));
$selisih = $total_kas - $ekspektasi_kas;

// Query data loket & jurnal (tetap seperti kode asli Anda)
$query = "SELECT l.id_loket, l.nama_loket, jk.id_jurnal, jk.status_jurnal, jk.saldo_awal_laci, jk.saldo_akhir_laci, jk.waktu_buka, u.nama_lengkap as nama_teller 
          FROM tbl_loket l 
          LEFT JOIN tbl_jurnal_kas jk ON l.id_loket = jk.id_loket AND jk.status_jurnal = 'open'
          LEFT JOIN tbl_users u ON jk.id_user = u.id_user";
$data_loket = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// 2. PROTEKSI DROP-DOWN SISTEM (ANTI-MANIPULASI ID USER)
if ($user_login_role === 'operator') {
    // Jika yang login adalah OPERATOR, KUNCI database agar HANYA menampilkan data dirinya sendiri!
    $users_stmt = $pdo->prepare("SELECT u.id_user, u.username, u.nama_lengkap 
                                 FROM tbl_users u 
                                 WHERE u.id_user = ? AND u.status_akun = 'aktif'");
    $users_stmt->execute([$user_login_id]);
    $list_user = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Jika yang login adalah ADMIN/SUPERVISOR, ijinkan melihat dan memilih seluruh personel operator
    $users_stmt = $pdo->query("SELECT u.id_user, u.username, u.nama_lengkap 
                               FROM tbl_users u 
                               INNER JOIN tbl_roles r ON u.id_role = r.id_role 
                               WHERE r.nama_role = 'operator' AND u.status_akun = 'aktif'");
    $list_user = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="space-y-6">

    <?php if ($is_balanced): ?>
        <div class="p-3 mb-4 rounded-xl bg-emerald-50 border border-emerald-200/80 flex items-center gap-3 text-emerald-900 shadow-sm">
            <div class="w-6 h-6 rounded-md bg-emerald-500/10 flex items-center justify-center text-emerald-600 shrink-0">
                <i class="fas fa-check-circle text-xs"></i>
            </div>
            <div class="flex-1">
                <h5 class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 inline-block mr-1">Status:</h5>
                <span class="text-[11px] text-emerald-700 font-medium">Kas seimbang. Seluruh catatan transaksi telah sesuai dengan uang fisik di laci loket.</span>
            </div>
        </div>
    <?php else: ?>
        <div class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-950 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="w-6 h-6 rounded-md bg-rose-500/10 flex items-center justify-center text-rose-600 shrink-0 mt-0.5">
                    <i class="fas fa-exclamation-triangle text-xs"></i>
                </div>
                <div class="flex-1">
                    <h5 class="text-[11px] font-bold uppercase tracking-wider text-rose-900">Selisih Kas Terdeteksi (Unbalanced)</h5>
                    <p class="text-[11px] text-rose-700 font-medium mt-0.5">
                        Ditemukan selisih sebesar <b class="text-rose-800 font-bold">Rp <?= number_format(abs($selisih), 0, ',', '.') ?></b> antara kondisi fisik laci dan jurnal berjalan.
                    </p>

                    <div class="mt-2 p-2 bg-white/80 border border-rose-100 rounded-md grid grid-cols-1 sm:grid-cols-3 gap-2 text-[10px] font-mono text-slate-700">
                        <div><span class="text-slate-400">Kas Laci:</span> <b class="text-slate-800">Rp <?= number_format($total_kas, 0, ',', '.') ?></b></div>
                        <div><span class="text-slate-400">Sistem:</span> <b class="text-slate-800">Rp <?= number_format($ekspektasi_kas, 0, ',', '.') ?></b></div>
                        <div class="text-rose-700 font-sans font-bold flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 inline-block"></span>
                            Indikator: <?= $selisih > 0 ? 'Kas Berlebih' : 'Kas Kurang' ?>
                        </div>
                    </div>

                    <p class="text-[10px] text-rose-600/90 font-medium mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        <?php if ($selisih > 0): ?>
                            Periksa transaksi <b>Setoran (Cash In)</b> yang uang fisiknya sudah diterima namun belum di-input ke sistem.
                        <?php else: ?>
                            Periksa bukti slip <b>Penarikan (Cash Out)</b> yang uang fisiknya sudah keluar namun belum tercatat di sistem.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] p-5 rounded-[1rem] shadow-lg flex flex-col justify-between group hover:shadow-xl transition-all relative overflow-hidden text-white">
            <div class="flex items-start justify-between w-full relative z-10 mb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-[10px] uppercase font-bold opacity-80">Total Cash Balance</span>

                        <?php if ($is_balanced): ?>
                            <span class="bg-emerald-500/30 text-emerald-200 text-[9px] px-2 py-0.5 rounded-full font-bold border border-emerald-400/30 flex items-center gap-1 animate-pulse">
                                <i class="fas fa-check-circle text-[10px]"></i> Balanced
                            </span>
                        <?php else: ?>
                            <span class="bg-rose-500/30 text-rose-200 text-[9px] px-2 py-0.5 rounded-full font-bold border border-rose-400/30 flex items-center gap-1">
                                <i class="fas fa-exclamation-triangle text-[10px]"></i> Unbalanced
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="text-xl font-black">
                        Rp <?= number_format($sum['total_kas'] ?? 0, 0, ',', '.') ?>
                    </div>
                </div>
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center border border-white/15 text-lg group-hover:scale-110 group-hover:bg-white/20 transition-all shrink-0">
                    <i class="fas fa-university text-white"></i>
                </div>
            </div>

            <div class="text-[9px] opacity-70 border-t border-white/20 pt-2 relative z-10 w-full flex flex-col gap-0.5">
                <p>
                    <i class="fas fa-wallet mr-1 opacity-80"></i> Akumulasi seluruh saldo aktif di laci teller.
                </p>
                <?php if (!$is_balanced): ?>
                    <p class="text-rose-200 font-semibold mt-1">
                        <i class="fas fa-info-circle mr-1"></i> Selisih: Rp <?= number_format($selisih, 0, ',', '.') ?> (Harusnya: Rp <?= number_format($ekspektasi_kas, 0, ',', '.') ?>)
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white p-5 rounded-[1rem] border border-slate-100 shadow-sm flex flex-col justify-between group hover:shadow-md transition-all relative overflow-hidden">
            <i class="fas fa-money-bill-wave absolute -right-4 -bottom-4 text-slate-50 text-6xl pointer-events-none"></i>
            <div class="flex items-center justify-between w-full relative z-10 mb-3">
                <div>
                    <div class="text-[10px] text-slate-400 uppercase font-bold mb-1">Total Cash In (Setoran)</div>
                    <div class="text-xl font-black text-emerald-600">
                        Rp <?= number_format($sum['total_setor'] ?? 0, 0, ',', '.') ?>
                    </div>
                </div>
                <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center border border-emerald-100 text-lg text-emerald-600 group-hover:scale-110 group-hover:bg-emerald-100 transition-all shrink-0">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
            <p class="text-[9px] text-slate-400 border-t border-slate-100 pt-2 relative z-10 w-full">
                <i class="fas fa-arrow-alt-circle-down mr-1 text-emerald-500"></i> Total dana masuk ke sistem hari ini.
                <span class="inline-block transition-transform group-hover:translate-x-1 ml-1 text-emerald-500">→</span>
            </p>
        </div>

        <div class="bg-white p-5 rounded-[1rem] border border-slate-100 shadow-sm flex flex-col justify-between group hover:shadow-md transition-all relative overflow-hidden">
            <i class="fas fa-hand-holding-usd absolute -right-4 -bottom-4 text-slate-50 text-6xl pointer-events-none"></i>
            <div class="flex items-center justify-between w-full relative z-10 mb-3">
                <div>
                    <div class="text-[10px] text-slate-400 uppercase font-bold mb-1">Total Cash Out (Penarikan)</div>
                    <div class="text-xl font-black text-amber-600">
                        Rp <?= number_format($sum['total_tarik'] ?? 0, 0, ',', '.') ?>
                    </div>
                </div>
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center border border-amber-100 text-lg text-amber-600 group-hover:scale-110 group-hover:bg-amber-100 transition-all shrink-0">
                    <i class="fas fa-money-check-alt"></i>
                </div>
            </div>
            <p class="text-[9px] text-slate-400 border-t border-slate-100 pt-2 relative z-10 w-full">
                <i class="fas fa-arrow-alt-circle-up mr-1 text-amber-500"></i> Total dana keluar hari ini.
                <span class="inline-block transition-transform group-hover:translate-x-1 ml-1 text-amber-500">→</span>
            </p>
        </div>
    </div>

    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fas fa-layer-group text-indigo-500"></i>
                    Status & Monitoring Laci Kas Teller
                </h4>
                <p class="text-[11px] text-slate-500 mt-0.5">Otorisasi pembukaan modal awal laci dan rekonsiliasi penutupan jurnal harian.</p>
            </div>
            <div class="text-right shrink-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-indigo-50 text-indigo-600 border border-indigo-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                    SINKRON DATABASE OK
                </span>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div id="alert-container" class="p-4 bg-slate-50/50 border-b border-slate-100">
                <?php if ($_GET['msg'] === 'gagal_sudah_buka'): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 flex items-start gap-3 p-3.5 rounded-xl text-xs font-medium shadow-sm">
                        <i class="fas fa-exclamation-triangle text-base text-rose-500 mt-0.5 shrink-0"></i>
                        <div>
                            <strong class="block font-black text-rose-800 text-[13px] mb-0.5">AKSES DITOLAK: Batasan Multi-Sesi!</strong>
                            <span>Personel Teller yang Anda pilih saat ini terdeteksi masih aktif bertugas di <span class="underline font-bold text-rose-950"><?= htmlspecialchars($_GET['loket_aktif'] ?? 'Loket Lain') ?></span>. Selesaikan atau tutup sesi loket tersebut terlebih dahulu sebelum mendaftarkannya di laci kas baru.</span>
                        </div>
                    </div>
                    <script>
                        setTimeout(function() {
                            window.history.replaceState({}, document.title, "main.php?page=kas-teller");
                            document.getElementById('alert-container').remove();
                        }, 6000);
                    </script>
                <?php elseif ($_GET['msg'] === 'sukses_buka'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-start gap-3 p-3.5 rounded-xl text-xs font-medium shadow-sm">
                        <i class="fas fa-check-circle text-base text-emerald-500 mt-0.5 shrink-0"></i>
                        <div>
                            <strong class="block font-black text-emerald-800 text-[13px] mb-0.5">OTORISASI BERHASIL!</strong>
                            <span>Sesi operasional kas laci telah berhasil diaktifkan. Jurnal saldo awal dideklarasikan dan siap melayani transaksi keuangan siswa.</span>
                        </div>
                    </div>
                    <script>
                        setTimeout(function() {
                            window.history.replaceState({}, document.title, "main.php?page=kas-teller");
                            document.getElementById('alert-container').remove();
                        }, 4000);
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold bg-slate-50/30 tracking-wider">
                        <th class="p-4 text-center w-12">No.</th>
                        <th class="p-4">Identitas Loket & Petugas</th>
                        <th class="p-4 text-center">Waktu Aktivasi</th>
                        <th class="p-4 text-center">Status Sesi</th>
                        <th class="p-4">Log Arus Kas Laci (IDR)</th>
                        <th class="p-4 text-center w-36">Aksi Kontrol</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <?php $no = 1;
                    foreach ($data_loket as $item):
                        $is_open = ($item['status_jurnal'] == 'open');
                    ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="p-4 text-center text-slate-400 font-bold"><?= $no++ ?></td>
                            <td class="p-4">
                                <div class="font-extrabold text-slate-800 text-sm">
                                    <?= htmlspecialchars($item['nama_loket']) ?>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1">
                                    <?php if ($is_open): ?>
                                        <i class="fas fa-user text-indigo-500 text-[10px]"></i>
                                        <span class="font-semibold text-slate-700"><?= htmlspecialchars($item['nama_teller']) ?></span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded text-[9px] font-bold border border-amber-200/60">
                                            <i class="fas fa-exclamation-circle text-[9px]"></i> Menunggu Personel
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-4 text-center text-slate-600 font-medium font-mono">
                                <?= $is_open ? date('H:i:s', strtotime($item['waktu_buka'])) . ' WIB' : '<span class="text-slate-300">-</span>' ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if ($is_open): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black bg-emerald-100 text-emerald-700 border border-emerald-200">
                                        <span class="w-1 h-1 rounded-full bg-emerald-500 animate-ping"></span> OPEN
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black bg-slate-100 text-slate-500 border border-slate-200">
                                        CLOSED
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if ($is_open): ?>
                                    <div class="space-y-0.5 font-mono">
                                        <div class="text-[10px] text-slate-400 flex justify-between max-w-[180px]">
                                            <span>Modal Awal:</span>
                                            <span class="font-bold">Rp <?= number_format($item['saldo_awal_laci'], 0, ',', '.') ?></span>
                                        </div>
                                        <div class="text-[12px] text-slate-800 font-extrabold flex justify-between max-w-[180px] border-t border-dashed border-slate-200 pt-0.5">
                                            <span>Kas Saat Ini:</span>
                                            <span class="text-indigo-600">Rp <?= number_format($item['saldo_akhir_laci'], 0, ',', '.') ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="font-mono text-slate-300 font-bold">- Void -</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if ($is_open): ?>
                                    <button onclick="if(confirm('Apakah anda yakin ingin melakukan penutupan buku dan mengunci sesi laci kas ini? Pastikan uang fisik sudah dihitung.')) window.location.href='modules/transaksi/tutup-kas.php?id=<?= $item['id_jurnal'] ?>'"
                                        class="text-[10px] font-black bg-amber-500 text-white px-3 py-1.5 rounded-xl hover:bg-amber-600 transition-all inline-flex items-center gap-1 shadow-sm hover:shadow tracking-wider uppercase">
                                        <i class="fas fa-lock text-[9px]"></i> Tutup Sesi
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                        class="btn-buka-laci text-[10px] font-black bg-emerald-600 text-white px-3 py-1.5 rounded-xl hover:bg-emerald-500 transition-all inline-flex items-center gap-1 shadow-sm hover:shadow tracking-wider uppercase"
                                        data-id="<?= $item['id_loket'] ?>">
                                        <i class="fas fa-unlock text-[9px]"></i> Buka Laci
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-slate-50 text-[10px] text-slate-400 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <span class="flex items-center gap-2 font-medium">
                <i class="fas fa-info-circle text-indigo-500 text-xs"></i>
                SOP Keuangan Sekolah: Wajib lakukan rekonsiliasi (hitung uang fisik di laci) sebelum menekan tombol "Tutup Sesi" di sore hari.
            </span>
            <span class="font-bold tracking-wide">Terakhir Diperbarui: <?= date('H:i') ?> WIB</span>
        </div>
    </div>
</div>

<div id="modal-buka-laci" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none flex items-center justify-center bg-black/50 p-4 transition-all duration-300 ease-out overflow-y-auto backdrop-blur-xs">
    <div id="modal-buka-content" class="bg-slate-50 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform scale-95 opacity-0 transition-all duration-300 ease-out my-8">

        <div class="bg-[#1566c7] p-5 text-white flex justify-between items-center shadow-md">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-lock text-amber-300"></i> Kas Operasional
                </h3>
                <p class="text-[10px] text-slate-200 mt-0.5 font-medium">Operator mendaftarkan hak tugas dan modal awal kas.</p>
            </div>
            <button type="button" onclick="closeModalBuka()" class="text-white/80 hover:text-white text-xl focus:outline-none transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-5 space-y-4">
            <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-xl text-[11px] font-medium flex gap-2">
                <i class="fas fa-shield-alt text-base text-amber-500 shrink-0 mt-0.5"></i>
                <div>
                    <strong class="font-black block text-amber-900 mb-0.5">PENTING (PRINSIP AKUNTANSI):</strong>
                    Input modal awal laci bersifat <span class="underline font-bold text-amber-950">Sekali Kunci (Read-Only)</span>. Setelah laci aktif, angka ini tidak dapat diubah kembali untuk meminimalkan celah manipulasi kas.
                </div>
            </div>

            <form action="modules/transaksi/buka-kas.php" method="POST" class="space-y-4">
                <input type="hidden" name="id" id="inputLoketId">

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs focus-within:border-[#1566c7] focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">
                        <i class="fas fa-user-tie text-indigo-500 mr-1"></i> Pilih Teller / Petugas Loket
                    </label>

                    <select name="id_user_teller" required class="w-full py-2 px-1 border-b border-slate-200 focus:border-[#1566c7] outline-none text-slate-800 font-extrabold text-xs bg-transparent cursor-pointer">
                        <?php if ($user_login_role !== 'operator'): ?>
                            <option value="" class="font-medium text-slate-400">-- Pilih Personel Operator --</option>
                        <?php endif; ?>

                        <?php foreach ($list_user as $u): ?>
                            <option value="<?= $u['id_user'] ?>" class="font-bold text-slate-700" <?= ($user_login_role === 'operator') ? 'selected' : '' ?>>
                                ID: <?= htmlspecialchars($u['username']) ?> - <?= htmlspecialchars($u['nama_lengkap']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="text-[10px] text-slate-400 mt-2 block font-medium">
                        <i class="fas fa-info-circle text-indigo-400 mr-1"></i>
                        <?php if ($user_login_role === 'operator'): ?>
                            <span class="text-rose-600 font-bold">AKSES DIKUNCI:</span> Silahkan pilih loket yang ingin Anda buka.
                        <?php else: ?>
                            Hak Akses Supervisor: Anda bebas menugaskan operator mana pun ke loket ini.
                        <?php endif; ?>
                    </small>
                </div>

                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs focus-within:border-[#1566c7] focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">
                            <i class="fas fa-money-bill-wave text-emerald-500 mr-1"></i> Masukkan Kas Awal
                        </label>
                        <span class="text-[9px] font-black text-slate-400 uppercase bg-slate-100 px-1.5 py-0.5 rounded tracking-wide">IDR</span>
                    </div>
                    <div class="relative">
                        <span class="absolute left-4 top-2.5 text-slate-400 font-extrabold text-base">Rp</span>
                        <input type="number" name="modal" required placeholder="0" min="0" class="w-full pl-11 pr-4 py-2.5 border-b-2 border-slate-100 focus:border-[#1566c7] outline-none font-black text-slate-800 text-lg tracking-wide font-mono">
                    </div>
                    <small class="text-[10px] text-slate-400 mt-2 block font-medium">
                        <i class="fas fa-info-circle text-emerald-400 mr-1"></i> Masukan nominal murni angka tanpa titik/koma.
                    </small>
                </div>

                <div class="pt-1">
                    <button type="submit" class="w-full flex items-center justify-center bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase">
                        <i class="fas fa-key mr-2"></i> Otorisasi & Aktifkan Laci Kas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const bukaButtons = document.querySelectorAll('.btn-buka-laci');
        const modal = document.getElementById('modal-buka-laci');
        const modalContent = document.getElementById('modal-buka-content');

        bukaButtons.forEach(button => {
            button.addEventListener('click', function() {
                const idLoket = this.getAttribute('data-id');
                document.getElementById('inputLoketId').value = idLoket;

                modal.classList.remove('invisible', 'opacity-0', 'pointer-events-none');
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            });
        });
    });

    function closeModalBuka() {
        const modal = document.getElementById('modal-buka-laci');
        const modalContent = document.getElementById('modal-buka-content');
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        modal.classList.add('invisible', 'opacity-0', 'pointer-events-none');
    }
</script>