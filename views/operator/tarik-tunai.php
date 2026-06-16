<?php

/**
 * Aplikasi Mini Bank Sekolah
 * Berkas: views/transaksi/penarikan.php (VERSI FINAL - INTEGRASI LACI KAS & MUTASI)
 * Deskripsi: Kelola penarikan tunai dengan konfigurasi dinamis, mutasi rekening, dan otomatisasi laci kas teller.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Identifikasi Petugas/Operator yang login
$id_petugas = $_SESSION['id_user'] ?? null;

// =========================================================================
// 1. PEMETAAN ID RELASI (SESUAIKAN DENGAN ISI TABEL MASTER ANDA)
// =========================================================================
$id_jenis_transaksi  = 2; // KODE_CONTOH: Silakan sesuaikan dengan ID 'Tarik Tunai' di tbl_jenis_transaksi
$id_metode_transaksi = 1; // KODE_CONTOH: Silakan sesuaikan dengan ID 'Tunai' di tbl_metode_transaksi

// =========================================================================
// 2. AMBIL KONFIGURASI DINAMIS DARI TBL_PENGATURAN
// =========================================================================
try {
    $stmt_config = $pdo->query("
        SELECT minimal_penarikan, minimal_saldo_mengendap, biaya_admin_default 
        FROM tbl_pengaturan 
        LIMIT 1
    ");
    $config = $stmt_config->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $biaya_admin     = floatval($config['biaya_admin_default']);
        $saldo_mengendap = floatval($config['minimal_saldo_mengendap']);
        $min_penarikan   = floatval($config['minimal_penarikan']);
    } else {
        $biaya_admin     = 2500;
        $saldo_mengendap = 15000;
        $min_penarikan   = 10000;
    }
} catch (PDOException $e) {
    error_log("Gagal memuat tbl_pengaturan: " . $e->getMessage());
    $biaya_admin     = 2500;
    $saldo_mengendap = 15000;
    $min_penarikan   = 10000;
}

$error_msg      = null;
$success_msg    = null;

// =========================================================================
// 3. DATA UNTUK AUTO-COMPLETE NASABAH (DENGAN LEFT JOIN JURUSAN)
// =========================================================================
$list_nasabah_json = "[]";
try {
    $stmt_list = $pdo->query("
        SELECT 
            n.id_nasabah, 
            n.nisn, 
            n.nama_nasabah, 
            CAST(n.saldo AS UNSIGNED) as saldo, 
            n.kelas,
            j.kode_jurusan,
            j.nama_jurusan
        FROM tbl_nasabah n
        LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
        WHERE n.status_nasabah = 'aktif'
        ORDER BY n.nama_nasabah ASC
    ");
    $all_nasabah = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
    $list_nasabah_json = json_encode($all_nasabah);
} catch (PDOException $e) {
    error_log("Gagal memuat daftar nasabah: " . $e->getMessage());
}

// =========================================================================
// 4. PROSES EKSEKUSI PENARIKAN (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_tarik'])) {
    $id_nasabah   = filter_var($_POST['id_nasabah'], FILTER_VALIDATE_INT);
    $jumlah_tarik = floatval($_POST['jumlah_tarik']);
    $keterangan   = !empty(trim($_POST['keterangan'])) ? trim($_POST['keterangan']) : "Tarik Tunai via Operator";

    $total_debet  = $jumlah_tarik + $biaya_admin;

    try {
        if (!$id_petugas) {
            throw new Exception("Sesi Anda telah berakhir. Silakan login kembali.");
        }
        if (!$id_nasabah) throw new Exception("Data siswa wajib dipilih dari hasil pencarian.");
        if ($jumlah_tarik < $min_penarikan) {
            throw new Exception("Minimal penarikan tunai adalah Rp " . number_format($min_penarikan, 0, ',', '.') . ".");
        }

        $pdo->beginTransaction();

        // =========================================================================
        // PENGAMAN 1: VALIDASI STATUS LACI KAS TELLER (WAJIB OPEN)
        // =========================================================================
        // Mencari laci kas milik teller ini yang berstatus 'open' untuk mengunci ID Jurnal berjalan
        $stmt_jurnal = $pdo->prepare("SELECT id_jurnal FROM tbl_jurnal_kas WHERE id_user = ? AND status_jurnal = 'open'");
        $stmt_jurnal->execute([$id_petugas]);
        $jurnal_aktif = $stmt_jurnal->fetch(PDO::FETCH_ASSOC);

        if (!$jurnal_aktif) {
            throw new Exception("Transaksi ditolak! Anda belum melakukan 'Buka Sesi' laci kas. Silakan buka laci Anda terlebih dahulu di menu Halaman Kas.");
        }
        $id_jurnal_berjalan = $jurnal_aktif['id_jurnal'];
        // =========================================================================

        $stmt_lock = $pdo->prepare("SELECT saldo, nama_nasabah, nisn FROM tbl_nasabah WHERE id_nasabah = ? FOR UPDATE");
        $stmt_lock->execute([$id_nasabah]);
        $nasabah_lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);

        if (!$nasabah_lock) throw new Exception("Data siswa tidak ditemukan di sistem.");

        $saldo_awal = floatval($nasabah_lock['saldo']);

        if (($saldo_awal - $total_debet) < $saldo_mengendap) {
            throw new Exception("Saldo Anda tidak mencukupi. Harap sisakan saldo mengendap minimal Rp " . number_format($saldo_mengendap, 0, ',', '.') . " di rekening.");
        }

        // A. Potong Saldo Rekening Siswa
        $saldo_baru = $saldo_awal - $total_debet;
        $update_saldo = $pdo->prepare("UPDATE tbl_nasabah SET saldo = ? WHERE id_nasabah = ?");
        $update_saldo->execute([$saldo_baru, $id_nasabah]);

        // Generate Kode Transaksi
        $kode_transaksi = "TRX-" . date('Ymd') . "-" . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $keterangan_sistem = $keterangan . " (Potongan admin Rp " . number_format($biaya_admin, 0, ',', '.') . ")";

        // B. Simpan Riwayat Log Transaksi
        $insert_transaksi = $pdo->prepare("
            INSERT INTO tbl_transaksi (
                kode_transaksi, 
                id_nasabah, 
                id_jenis_transaksi, 
                id_metode_transaksi, 
                jumlah, 
                biaya_admin, 
                saldo_awal, 
                saldo_akhir, 
                keterangan, 
                id_petugas, 
                status_approval, 
                tanggal_transaksi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
        ");
        $insert_transaksi->execute([
            $kode_transaksi,
            $id_nasabah,
            $id_jenis_transaksi,
            $id_metode_transaksi,
            $jumlah_tarik,
            $biaya_admin,
            $saldo_awal,
            $saldo_baru,
            $keterangan_sistem,
            $id_petugas
        ]);
        $id_transaksi = $pdo->lastInsertId();

        // Otomatis isi tbl_mutasi (Jenis Mutasi: DEBIT)
        $stmt_mutasi = $pdo->prepare("
            INSERT INTO tbl_mutasi 
            (id_nasabah, id_transaksi, jenis_mutasi, nominal, saldo_tersedia, keterangan, created_at) 
            VALUES (?, ?, 'debit', ?, ?, ?, NOW())
        ");
        $stmt_mutasi->execute([
            $id_nasabah,
            $id_transaksi,
            $total_debet,       // Total saldo yang keluar dari rekening siswa (Penarikan + Admin)
            $saldo_baru,        // Saldo akhir setelah dikurangi transaksi penarikan
            $keterangan_sistem
        ]);

        // Otomatis isi tbl_notifikasi (Pemberitahuan Penarikan)
        $notif_judul = "Penarikan Tunai Berhasil";
        $notif_pesan = "Halo " . $nasabah_lock['nama_nasabah'] . ", penarikan dana tunai sebesar Rp " . number_format($jumlah_tarik, 0, ',', '.') . " telah sukses diproses di loket (Biaya Admin: Rp " . number_format($biaya_admin, 0, ',', '.') . "). Sisa saldo tabungan Anda saat ini adalah Rp " . number_format($saldo_baru, 0, ',', '.') . ".";

        $stmt_notif = $pdo->prepare("
            INSERT INTO tbl_notifikasi 
            (id_nasabah, judul, pesan, is_read, created_at) 
            VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt_notif->execute([
            $id_nasabah,
            $notif_judul,
            $notif_pesan
        ]);

        // =========================================================================
        // PENGAMAN 2: UPDATE OTOMATIS NILAI LACI KAS JURNAL TELLER (CASH OUT)
        // =========================================================================
        // Mengurangi Saldo Akhir Laci fisik dan mengakumulasikan Total Cash Out (Penarikan)
        // Uang fisik yang keluar dari laci loket adalah sebesar $jumlah_tarik
        $stmt_update_laci = $pdo->prepare("
            UPDATE tbl_jurnal_kas 
            SET saldo_akhir_laci = saldo_akhir_laci - ?,
                total_penarikan = total_penarikan + ?
            WHERE id_jurnal = ?
        ");
        $stmt_update_laci->execute([$jumlah_tarik, $jumlah_tarik, $id_jurnal_berjalan]);
        // =========================================================================

        // C. Catat Log Aktivitas Operator
        $log_act = $pdo->prepare("
            INSERT INTO log_activity (id_user, id_nasabah, role_pelaku, aktivitas, ip_address, user_agent) 
            VALUES (?, ?, 'operator', ?, ?, ?)
        ");
        $aktivitas_msg = "Memproses penarikan tunai Rp " . number_format($jumlah_tarik, 0, ',', '.') . " untuk siswa: " . $nasabah_lock['nama_nasabah'];
        $log_act->execute([$id_petugas, $id_nasabah, $aktivitas_msg, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

        // Komit semua transaksi di atas
        $pdo->commit();

        $nama_siswa = addslashes($nasabah_lock['nama_nasabah']);
        $nisn_siswa = addslashes($nasabah_lock['nisn']);

        echo "<script>
            alert('Penarikan a.n. " . $nama_siswa . " dengan NISN " . $nisn_siswa . " berhasil diterima dan dicatat oleh sistem.'); 
            window.open('cetak_struk.php?id=$id_transaksi', '_blank'); 
            window.location.href='?page=tarik-tunai';
        </script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = $e->getMessage();
        echo "<script>alert('Gagal: " . addslashes($error_msg) . "');</script>";
    }
}
?>

<div id="section-penarikan" class="w-full xl:max-w-12xl mx-auto space-y-6">
    <div class="bg-white p-6 md:p-8 rounded-[1rem] border border-slate-100 shadow-sm relative overflow-hidden">

        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
            <div>
                <h3 class="font-black text-[#506a8a] flex items-center gap-2">
                    <i class="fas fa-hand-holding-usd text-amber-500"></i>Penarikan Tunai (Debet)
                </h3>
                <p class="text-[11px] text-slate-400 font-medium">Mengurangi saldo rekening tabungan siswa melalui transaksi penarikan tunai.</p>
            </div>
            <a href="?page=main" class="text-[10px] font-black uppercase tracking-wider text-slate-400 hover:text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                <i class="fas fa-arrow-left mr-1.5"></i> Kembali
            </a>
        </div>

        <form method="POST" id="formPenarikan" class="space-y-6" autocomplete="off">
            <div class="mt-6 bg-amber-50 border border-amber-100 rounded-xl p-4 flex gap-3.5 items-start">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fas fa-exclamation-triangle text-sm"></i>
                </div>
                <div class="space-y-1">
                    <h5 class="text-xs font-black text-amber-950 tracking-wide">Pemberitahuan Prosedur Operasional (SOP Kasir)</h5>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium">
                        Setiap penarikan wajib menyisakan saldo minimal <strong class="text-amber-700">Rp <?= number_format($saldo_mengendap, 0, ',', '.') ?></strong> di dalam rekening siswa serta dikenakan biaya administrasi penarikan sebesar <strong class="text-slate-900">Rp <?= number_format($biaya_admin, 0, ',', '.') ?></strong> secara otomatis oleh sistem.
                    </p>
                </div>
            </div>

            <div class="space-y-8">
                <div class="flex flex-col lg:flex-row gap-6">
                    <div class="flex-1 space-y-3 relative">
                        <label class="text-[11px] font-black text-slate-400 tracking-wider flex items-center gap-2 mb-1">
                            <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] font-bold">1</span>
                            Ketik Nama Siswa atau NISN
                        </label>
                        <div class="relative flex items-center border-2 border-slate-100 bg-slate-50 rounded-xl focus-within:border-blue-500 focus-within:bg-white transition-all p-1">
                            <i class="fas fa-search absolute left-5 text-slate-300"></i>
                            <input type="text" id="pencarian_input" onkeyup="liveSearchSiswa(this.value)" placeholder="Cari nama atau nomor NISN siswa..."
                                class="w-full pl-14 pr-4 py-3.5 bg-transparent outline-none font-bold text-slate-700 placeholder:text-slate-300 text-sm md:text-base">
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1 leading-normal">
                            <i class="fas fa-keyboard text-slate-400 mr-1"></i> Masukkan minimal 2 karakter untuk memicu pencarian data otomatis siswa.
                        </small>

                        <div id="dropdown-search" class="absolute left-0 right-0 mt-2 bg-white border border-slate-100 rounded-xl shadow-xl z-50 max-h-60 overflow-y-auto hidden"></div>
                    </div>

                    <div id="status_box" class="w-full lg:w-72 bg-slate-50 p-5 rounded-xl border-2 border-dashed border-slate-200 flex flex-col justify-center items-center text-center transition-all min-h-[140px] shrink-0">
                        <div id="status_icon" class="w-12 h-12 bg-white text-slate-300 rounded-full border border-slate-100 shadow-sm flex items-center justify-center text-base mb-2 shrink-0">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <input type="hidden" name="id_nasabah" id="res_id_nasabah">
                        <p id="res_nama_nasabah" class="text-xs font-black text-slate-400 tracking-wide uppercase">Belum Ada Siswa Terpilih</p>

                        <div id="meta_detail_siswa" class="mt-3 text-left hidden w-full bg-white p-4 rounded-xl border border-slate-100 space-y-1.5 shadow-sm">
                            <div class="flex justify-between items-center text-[11px] font-bold">
                                <span class="text-slate-400 tracking-tight">Rombel Kelas</span>
                                <span id="lbl_kelas" class="text-slate-700 font-black"></span>
                            </div>
                            <div class="h-px bg-slate-100 w-full"></div>

                            <div class="flex justify-between items-center text-[11px] font-bold">
                                <span class="text-slate-400 tracking-tight">Jurusan</span>
                                <span id="lbl_jurusan" class="text-slate-700 font-black text-right max-w-[140px] truncate" title=""></span>
                            </div>
                            <div class="h-px bg-slate-100 w-full"></div>

                            <div class="flex justify-between items-center text-[11px] font-bold">
                                <span class="text-slate-400 tracking-tight">Saldo Lama</span>
                                <span id="lbl_saldo" class="text-blue-600 font-black"></span>
                            </div>
                            <div class="h-px bg-slate-100 w-full"></div>
                            <div class="flex justify-between items-center text-[11px] font-bold">
                                <span class="text-slate-400 tracking-tight">Estimasi Akhir</span>
                                <span id="lbl_saldo_baru" class="text-amber-600 font-black"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="h-px bg-slate-100 w-full"></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-slate-400 tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-bold">2</span>
                            Jumlah Penarikan Tunai (Rp)
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-5 font-black text-slate-400 text-xs">Rp</span>
                            <input type="number" name="jumlah_tarik" id="input_nominal" requiamber disabled oninput="hitungSimulasiTarik()" placeholder="0"
                                class="w-full pl-12 pr-4 py-3.5 border-2 border-slate-100 bg-slate-100 rounded-xl focus:border-blue-500 focus:bg-white outline-none font-bold text-sm text-slate-700 transition-all disabled:cursor-not-allowed h-[52px]">
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1 leading-normal">
                            <i class="fas fa-exclamation-circle text-emerald-500 mr-1"></i> Ketik nominal bersih tanpa titik/koma (Minimal Rp <?= number_format($min_penarikan, 0, ',', '.') ?>).
                        </small>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-slate-400 tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-[10px] font-bold">3</span>
                            Biaya Admin Penarikan (Sistem)
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-5 text-slate-400">
                                <i class="fas fa-calculator text-xs"></i>
                            </span>
                            <input type="text" disabled value="Rp <?= number_format($biaya_admin, 0, ',', '.') ?>"
                                class="w-full pl-12 pr-4 py-3.5 border-2 border-slate-100 bg-slate-100 rounded-xl outline-none font-bold text-sm text-amber-600 transition-all cursor-not-allowed h-[52px]">
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1 leading-normal">
                            <i class="fas fa-info-circle text-amber-500 mr-1"></i> Potongan tetap dari sistem per transaksi penarikan tunai.
                        </small>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-slate-400 tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-[10px] font-bold">4</span>
                            Berita Acara / Keterangan Pembayaran
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-5 text-slate-400">
                                <i class="far fa-comment-alt text-xs"></i>
                            </span>
                            <textarea name="keterangan" id="input_keterangan" rows="1" disabled placeholder="Contoh: Penarikan Keperluan Prakerin, Uang Saku..."
                                class="w-full pl-12 pr-4 py-[13px] border-2 border-slate-100 bg-slate-100 rounded-xl focus:border-blue-500 focus:bg-white outline-none font-bold text-sm text-slate-700 transition-all disabled:cursor-not-allowed h-[52px] resize-none"></textarea>
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1">
                            <i class="far fa-comment-alt text-purple-400 mr-1"></i> Jika kosong, otomatis diisi: <em>'Tarik Tunai via Operator'</em>.
                        </small>
                    </div>

                </div>

                <div id="quick-pick-panel" class="pt-2">
                    <button type="submit" name="proses_tarik" id="btnSubmit" disabled
                        class="w-full py-4 bg-slate-100 text-slate-400 font-black rounded-xl cursor-not-allowed transition-all flex items-center justify-center gap-3 tracking-[0.1em] text-xs uppercase">
                        <i class="fas fa-lock text-sm"></i> Silakan pilih data siswa terlebih dahulu
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const dataNasabahMaster = <?php echo $list_nasabah_json; ?>;
    let currentSiswaSaldo = 0;
    const biayaAdmin = <?= $biaya_admin ?>;

    function liveSearchSiswa(keyword) {
        const dropdown = document.getElementById('dropdown-search');
        const searchKey = keyword.toLowerCase().trim();

        if (searchKey.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        const hasilFilter = dataNasabahMaster.filter(siswa => {
            return siswa.nama_nasabah.toLowerCase().includes(searchKey) || siswa.nisn.includes(searchKey);
        });

        dropdown.innerHTML = '';

        if (hasilFilter.length === 0) {
            dropdown.innerHTML = `<div class="p-4 text-xs font-bold text-slate-400"><i class="fas fa-user-times mr-1"></i> Siswa tidak ditemukan atau status nonaktif</div>`;
            dropdown.classList.remove('hidden');
            return;
        }

        hasilFilter.slice(0, 5).forEach(siswa => {
            const rombelText = `${siswa.kelas ?? ''} ${siswa.kode_jurusan ?? ''}`.trim() || '-';

            const item = document.createElement('div');
            item.className = "p-4 hover:bg-blue-50 cursor-pointer border-b border-slate-50 flex justify-between items-center transition-colors";
            item.innerHTML = `
                <div>
                    <p class="text-xs font-black text-slate-800">${siswa.nama_nasabah}</p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">NISN: ${siswa.nisn} | Kelas: ${rombelText}</p>
                </div>
                <span class="text-[10px] font-black bg-slate-100 text-slate-600 px-3 py-1 rounded-full">Rp ${new Intl.NumberFormat('id-ID').format(siswa.saldo)}</span>
            `;
            item.onclick = function() {
                pilihSiswa(siswa);
            };
            dropdown.appendChild(item);
        });

        dropdown.classList.remove('hidden');
    }

    function pilihSiswa(siswa) {
        currentSiswaSaldo = parseFloat(siswa.saldo);

        document.getElementById('res_id_nasabah').value = siswa.id_nasabah;
        document.getElementById('pencarian_input').value = `${siswa.nama_nasabah} (${siswa.nisn})`;
        document.getElementById('dropdown-search').classList.add('hidden');

        document.getElementById('res_nama_nasabah').innerText = siswa.nama_nasabah;
        document.getElementById('res_nama_nasabah').className = "text-xs font-black text-blue-900 uppercase tracking-wide";

        const iconState = document.getElementById('status_icon');
        iconState.className = "w-12 h-12 bg-blue-50 text-blue-500 rounded-full border border-blue-200 shadow-sm flex items-center justify-center text-base mb-2 shrink-0 transition-all";

        const rombelGabung = `${siswa.kelas ?? ''} ${siswa.kode_jurusan ?? ''}`.trim() || '-';
        document.getElementById('lbl_kelas').innerText = rombelGabung;

        document.getElementById('lbl_jurusan').innerText = siswa.nama_jurusan ?? '-';
        document.getElementById('lbl_jurusan').setAttribute('title', siswa.nama_jurusan ?? '-');

        document.getElementById('lbl_saldo').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(siswa.saldo)}`;
        document.getElementById('lbl_saldo_baru').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(siswa.saldo)}`;

        document.getElementById('meta_detail_siswa').classList.remove('hidden');
        document.getElementById('status_box').className = "w-full lg:w-72 bg-blue-50/40 p-5 rounded-xl border-2 border-solid border-blue-200 flex flex-col justify-center items-center text-center shrink-0 shadow-sm transition-all";

        ['input_nominal', 'input_keterangan'].forEach(id => {
            const el = document.getElementById(id);
            el.disabled = false;
            el.classList.remove('bg-slate-100');
            el.classList.add('bg-slate-50');
        });

        const btn = document.getElementById('btnSubmit');
        btn.disabled = false;
        btn.className = "w-full py-4 bg-[#1257aa] text-white font-black rounded-xl active:scale-[0.99] shadow-lg transition-all flex items-center justify-center gap-3 tracking-[0.15em] text-xs cursor-pointer";
        btn.innerHTML = `<i class="fas fa-minus-circle text-sm"></i> TARIK TUNAI SEKARANG`;
    }

    function hitungSimulasiTarik() {
        const inputNominal = parseFloat(document.getElementById('input_nominal').value) || 0;
        if (inputNominal === 0) {
            document.getElementById('lbl_saldo_baru').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(currentSiswaSaldo)}`;
            return;
        }
        const totalDebet = inputNominal + biayaAdmin;
        const totalEstimasi = currentSiswaSaldo - totalDebet;
        document.getElementById('lbl_saldo_baru').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(totalEstimasi)}`;
    }

    document.addEventListener('click', function(e) {
        if (!document.getElementById('pencarian_input').contains(e.target)) {
            document.getElementById('dropdown-search').classList.add('hidden');
        }
    });
</script>