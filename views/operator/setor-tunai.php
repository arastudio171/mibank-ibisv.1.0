<?php

/**
 * Aplikasi Mini Bank Sekolah
 * Berkas: views/transaksi/setoran.php (VERSI FINAL - INTEGRASI LACI KAS & MUTASI)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Koneksi Database (Asumsi $pdo sudah di-include sebelumnya)
$id_petugas = $_SESSION['id_user'] ?? null;

// ==========================================
// 1. DATA UNTUK AUTO-COMPLETE (BACKEND)
// ==========================================
$list_nasabah_json = "[]";
try {
    // Mengambil data siswa aktif beserta kode dan nama jurusan menggunakan LEFT JOIN
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

// ==========================================
// 2. PROSES SIMPAN SETORAN (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_setoran'])) {
    $id_nasabah      = filter_var($_POST['id_nasabah'], FILTER_VALIDATE_INT);
    $nominal         = floatval($_POST['nominal']);
    $keterangan      = !empty($_POST['keterangan']) ? trim($_POST['keterangan']) : 'Setoran Tunai Sekolah';
    $nomor_referensi = !empty($_POST['nomor_referensi_bank']) ? trim($_POST['nomor_referensi_bank']) : null;

    try {
        if (!$id_petugas) {
            throw new Exception("Sesi Anda telah berakhir. Silakan login kembali.");
        }
        if (!$id_nasabah) throw new Exception("Data nasabah wajib dipilih dari daftar pencarian.");
        if ($nominal <= 0)  throw new Exception("Nominal setoran harus di atas Rp 0.");

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

        // Validasi Unique Key Nomor Referensi Bank jika diisi
        if ($nomor_referensi !== null) {
            $stmt_cek_ref = $pdo->prepare("SELECT COUNT(*) FROM tbl_transaksi WHERE nomor_referensi_bank = ?");
            $stmt_cek_ref->execute([$nomor_referensi]);
            if ($stmt_cek_ref->fetchColumn() > 0) {
                throw new Exception("Nomor referensi bank '" . htmlspecialchars($nomor_referensi) . "' sudah pernah digunakan sebelumnya!");
            }
        }

        // Kunci data nasabah & ambil data NISN + Nama untuk keperluan Alert Dinamis
        $stmt = $pdo->prepare("SELECT saldo, nama_nasabah, nisn FROM tbl_nasabah WHERE id_nasabah = ? FOR UPDATE");
        $stmt->execute([$id_nasabah]);
        $nasabah = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nasabah) throw new Exception("Siswa/Nasabah tidak ditemukan.");

        $saldo_lama = floatval($nasabah['saldo']);
        $saldo_baru = $saldo_lama + $nominal;

        $biaya_admin = 0;

        // Update Saldo Nasabah
        $update = $pdo->prepare("UPDATE tbl_nasabah SET saldo = ? WHERE id_nasabah = ?");
        $update->execute([$saldo_baru, $id_nasabah]);

        // Kode Transaksi Internal
        $kode_transaksi = "TRX-" . date('Ymd') . "-" . strtoupper(bin2hex(random_bytes(3)));
        $id_jenis_transaksi  = 1; // 1 = Setoran
        $id_metode_transaksi = 1; // 1 = Tunai

        // Insert ke Tabel Transaksi
        $log = $pdo->prepare("
            INSERT INTO tbl_transaksi 
            (kode_transaksi, nomor_referensi_bank, id_nasabah, id_jenis_transaksi, id_metode_transaksi, jumlah, biaya_admin, saldo_awal, saldo_akhir, keterangan, id_petugas, status_approval, tanggal_transaksi) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
        ");

        $log->execute([
            $kode_transaksi,
            $nomor_referensi,
            $id_nasabah,
            $id_jenis_transaksi,
            $id_metode_transaksi,
            $nominal,
            $biaya_admin,
            $saldo_lama,
            $saldo_baru,
            $keterangan,
            $id_petugas
        ]);
        $id_transaksi = $pdo->lastInsertId();

        // Otomatis isi tbl_mutasi
        $stmt_mutasi = $pdo->prepare("
            INSERT INTO tbl_mutasi 
            (id_nasabah, id_transaksi, jenis_mutasi, nominal, saldo_tersedia, keterangan, created_at) 
            VALUES (?, ?, 'kredit', ?, ?, ?, NOW())
        ");
        $stmt_mutasi->execute([
            $id_nasabah,
            $id_transaksi,
            $nominal,
            $saldo_baru,
            $keterangan
        ]);

        // Otomatis isi tbl_notifikasi
        $notif_judul = "Setoran Berhasil!";
        $notif_pesan = "Halo " . $nasabah['nama_nasabah'] . ", setoran tunai sebesar Rp " . number_format($nominal, 0, ',', '.') . " telah sukses diterima di loket. Saldo Anda sekarang menjadi Rp " . number_format($saldo_baru, 0, ',', '.') . ". Terima kasih.";

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
        // PENGAMAN 2: UPDATE OTOMATIS NILAI LACI KAS JURNAL TELLER
        // =========================================================================
        // Menambahkan Saldo Akhir Laci fisik dan mengakumulasikan Total Cash In (Setoran)
        $stmt_update_laci = $pdo->prepare("
            UPDATE tbl_jurnal_kas 
            SET saldo_akhir_laci = saldo_akhir_laci + ?,
                total_setoran_tunai = total_setoran_tunai + ?
            WHERE id_jurnal = ?
        ");
        $stmt_update_laci->execute([$nominal, $nominal, $id_jurnal_berjalan]);
        // =========================================================================

        // Catat Log Aktivitas Sistem
        $log_act = $pdo->prepare("
            INSERT INTO log_activity (id_user, id_nasabah, role_pelaku, aktivitas, ip_address, user_agent) 
            VALUES (?, ?, 'operator', ?, ?, ?)
        ");
        $aktivitas_msg = "Penyetoran kas tunai Rp " . number_format($nominal, 0, ',', '.') . " (Ref: " . ($nomor_referensi ?? '-') . ") untuk siswa: " . $nasabah['nama_nasabah'];
        $log_act->execute([$id_petugas, $id_nasabah, $aktivitas_msg, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

        // Komit semua query di atas bersamaan
        $pdo->commit();

        $nama_siswa = addslashes($nasabah['nama_nasabah']);
        $nisn_siswa = addslashes($nasabah['nisn']);

        echo "<script>
            alert('Setoran a.n. " . $nama_siswa . " dengan NISN " . $nisn_siswa . " berhasil diterima dan dicatat oleh sistem.'); 
            window.open('cetak_struk.php?id=$id_transaksi', '_blank'); 
            window.location.href='?page=setor-tunai';
        </script>";
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<script>alert('Gagal: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!-- ==========================================
INTERFACE UI / DESIGN ELEMEN
========================================== -->
<div id="section-setoran" class="w-full xl:max-w-12xl mx-auto space-y-6">

    <div class="bg-white p-6 md:p-8 rounded-[1rem] border border-slate-100 shadow-sm relative overflow-hidden">

        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
            <div>
                <h3 class="font-black text-[#506a8a] flex items-center gap-2">
                    <i class="fas fa-coins text-green-600"></i>Penyetoran Tunai (Kredit)
                </h3>
                <p class="text-[11px] text-slate-400 font-medium">Menambahkan saldo rekening tabungan siswa melalui transaksi setoran tunai..</p>
            </div>
            <a href="?page=main" class="text-[10px] font-black uppercase tracking-wider text-slate-400 hover:text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                <i class="fas fa-arrow-left mr-1.5"></i> Kembali
            </a>
        </div>

        <form method="POST" id="formSetoran" class="space-y-6" autocomplete="off">

            <div class="mt-6 bg-green-50 border border-green-200/70 rounded-xl p-4 flex gap-3.5 items-start">
                <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fas fa-info-circle text-sm"></i>
                </div>
                <div class="space-y-1">
                    <h5 class="text-xs font-black text-green-900 tracking-wide">Pemberitahuan Prosedur Operasional (SOP Kasir)</h5>
                    <p class="text-xs text-slate-800/80 leading-relaxed font-medium">
                        Harap hitung fisik uang tunai di depan nasabah/siswa terlebih dahulu dan rapihkan denominasi lembaran sebelum menginput nominal. Pastikan data akun siswa yang muncul di panel detail sudah sesuai demi menghindari kekeliruan pos alokasi dana tabungan.
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
                            <input type="text" id="pencarian_input" onkeyup="lokalLiveSearch(this.value)" placeholder="Cari nama atau nomor NISN siswa..."
                                class="w-full pl-14 pr-4 py-3.5 bg-transparent outline-none font-bold text-slate-700 placeholder:text-slate-300 text-sm md:text-base">
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1 leading-normal">
                            <i class="fas fa-keyboard text-slate-400 mr-1"></i> Masukkan minimal 2 karakter untuk memicu pencarian data otomatis siswa.
                        </small>

                        <div id="dropdown-search" class="absolute left-0 right-0 mt-2 bg-white border border-slate-100 rounded-xl shadow-xl z-50 max-h-60 overflow-y-auto hidden">
                        </div>
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
                                <span id="lbl_saldo_baru" class="text-emerald-600 font-black"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="h-px bg-slate-100 w-full"></div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-slate-400 tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-bold">2</span>
                            Jumlah Setoran Tunai (Rp)
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-5 font-black text-slate-400 text-xs">Rp</span>
                            <input type="number" name="nominal" id="input_nominal" required disabled oninput="hitungSimulasiSaldo()" placeholder="0"
                                class="w-full pl-12 pr-4 py-3.5 border-2 border-slate-100 bg-slate-100 rounded-xl focus:border-blue-500 focus:bg-white outline-none font-bold text-sm text-slate-700 transition-all disabled:cursor-not-allowed h-[52px]">
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1 leading-normal">
                            <i class="fas fa-exclamation-circle text-emerald-500 mr-1"></i> Ketik nominal tanpa titik/koma. Otomatis mengalkulasi simulasi saldo akhir.
                        </small>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[11px] font-black text-slate-400 tracking-wider flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] font-bold">3</span>
                            Nomor Referensi Bank <span class="text-slate-400 font-normal lowercase">(opsional)</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-5 text-slate-400">
                                <i class="fas fa-university text-xs"></i>
                            </span>
                            <input type="text" name="nomor_referensi_bank" id="input_nomor_referensi" disabled placeholder="Contoh: REF12345678"
                                class="w-full pl-12 pr-4 py-3.5 border-2 border-slate-100 bg-slate-100 rounded-xl focus:border-blue-500 focus:bg-white outline-none font-bold text-sm text-slate-700 transition-all disabled:cursor-not-allowed h-[52px]">
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1 leading-normal">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i> Isi jika bersumber dari transfer bank, kosongkan jika setoran tunai langsung.
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
                            <textarea name="keterangan" id="input_keterangan" rows="1" disabled placeholder="Contoh: Tabungan Karyawisata, Setoran Mingguan..."
                                class="w-full pl-12 pr-4 py-[13px] border-2 border-slate-100 bg-slate-100 rounded-xl focus:border-blue-500 focus:bg-white outline-none font-bold text-sm text-slate-700 transition-all disabled:cursor-not-allowed h-[52px] resize-none"></textarea>
                        </div>
                        <small class="block text-[10px] font-semibold text-slate-400 ml-1">
                            <i class="far fa-comment-alt text-purple-400 mr-1"></i> Jika kosong, otomatis diisi: <em>'Setoran Tunai Sekolah'</em>.
                        </small>
                    </div>

                </div>

                <div class="pt-2">
                    <button type="submit" name="proses_setoran" id="btnSubmit" disabled
                        class="w-full py-4 bg-slate-100 text-slate-400 font-black rounded-xl cursor-not-allowed transition-all flex items-center justify-center gap-3 tracking-[0.1em] text-xs uppercase">
                        <i class="fas fa-lock text-sm"></i> Silakan pilih data siswa terlebih dahulu
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Master data nasabah yang dipassing dari JSON PHP
    const dataNasabahMaster = <?php echo $list_nasabah_json; ?>;
    let currentSiswaSaldo = 0;

    // Fungsi Pencarian Siswa Otomatis (Live Search)
    function lokalLiveSearch(keyword) {
        const dropdown = document.getElementById('dropdown-search');
        const searchKey = keyword.toLowerCase().trim();

        if (searchKey.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }

        const hasilFilter = dataNasabahMaster.filter(siswa => {
            return siswa.nama_nasabah.toLowerCase().includes(searchKey) ||
                siswa.nisn.includes(searchKey);
        });

        dropdown.innerHTML = '';

        if (hasilFilter.length === 0) {
            dropdown.innerHTML = `<div class="p-4 text-xs font-bold text-slate-400"><i class="fas fa-user-times mr-1"></i> Data siswa tidak ditemukan atau nonaktif</div>`;
            dropdown.classList.remove('hidden');
            return;
        }

        hasilFilter.slice(0, 5).forEach(siswa => {
            // Gabungkan Kelas dengan Kode Jurusan (Contoh: XI BDP)
            const rombelText = `${siswa.kelas ?? ''} ${siswa.kode_jurusan ?? ''}`.trim() || '-';

            const item = document.createElement('div');
            item.className = "p-4 hover:bg-blue-50 cursor-pointer border-b border-slate-50 transition-colors flex justify-between items-center";
            item.innerHTML = `
                <div>
                    <p class="text-xs font-black text-slate-800">${siswa.nama_nasabah}</p>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">NISN: ${siswa.nisn} | Kelas: ${rombelText}</p>
                </div>
                <span class="text-[10px] font-black bg-slate-100 text-slate-600 px-3 py-1 rounded-full">Rp ${new Intl.NumberFormat('id-ID').format(siswa.saldo)}</span>
            `;
            item.onclick = function() {
                pilihSiswaLokal(siswa);
            };
            dropdown.appendChild(item);
        });

        dropdown.classList.remove('hidden');
    }

    // Fungsi Kunci Siswa Terpilih ke Form Input
    function pilihSiswaLokal(siswa) {
        currentSiswaSaldo = parseFloat(siswa.saldo);

        document.getElementById('res_id_nasabah').value = siswa.id_nasabah;
        document.getElementById('pencarian_input').value = `${siswa.nama_nasabah} (${siswa.nisn})`;
        document.getElementById('dropdown-search').classList.add('hidden');

        document.getElementById('res_nama_nasabah').innerText = siswa.nama_nasabah;
        document.getElementById('res_nama_nasabah').className = "text-xs font-black text-blue-900 uppercase tracking-wide";

        // Pasang Kelas + Kode Jurusan (Contoh: XI BDP)
        const rombelGabung = `${siswa.kelas ?? ''} ${siswa.kode_jurusan ?? ''}`.trim() || '-';
        document.getElementById('lbl_kelas').innerText = rombelGabung;

        // Pasang Nama Jurusan Lengkap (Contoh: Bisnis Daring dan Pemasaran)
        document.getElementById('lbl_jurusan').innerText = siswa.nama_jurusan ?? '-';
        document.getElementById('lbl_jurusan').setAttribute('title', siswa.nama_jurusan ?? '-');

        // Format Tampilan Finansial Awal
        document.getElementById('lbl_saldo').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(siswa.saldo)}`;
        document.getElementById('lbl_saldo_baru').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(siswa.saldo)}`;
        document.getElementById('meta_detail_siswa').classList.remove('hidden');

        // Update Style Panel Box Menjadi Aktif (Warna Biru)
        document.getElementById('status_box').className = "w-full lg:w-72 bg-blue-50/40 p-5 rounded-xl border-2 border-solid border-blue-200 flex flex-col justify-center items-center text-center transition-all shrink-0";
        document.getElementById('status_icon').className = "w-12 h-12 bg-blue-50 text-blue-600 border border-blue-200 rounded-full shadow-sm flex items-center justify-center text-base mb-2 transition-all";

        // Aktifkan Semua Elemen Input Form
        document.getElementById('input_nominal').disabled = false;
        document.getElementById('input_nominal').classList.remove('bg-slate-100');
        document.getElementById('input_nominal').classList.add('bg-slate-50');

        document.getElementById('input_nomor_referensi').disabled = false;
        document.getElementById('input_nomor_referensi').classList.remove('bg-slate-100');
        document.getElementById('input_nomor_referensi').classList.add('bg-slate-50');

        document.getElementById('input_keterangan').disabled = false;
        document.getElementById('input_keterangan').classList.remove('bg-slate-100');
        document.getElementById('input_keterangan').classList.add('bg-slate-50');

        // Ubah State Button Submit Menjadi Aktif & Berwarna (Gradient)
        const btn = document.getElementById('btnSubmit');
        btn.disabled = false;
        btn.className = "w-full py-4 bg-[#1257aa] text-white font-black rounded-xl active:scale-[0.99] shadow-lg transition-all flex items-center justify-center gap-3 tracking-[0.15em] text-xs cursor-pointer";
        btn.innerHTML = `<i class="fas fa-plus-circle text-sm"></i> SETOR TUNAI SEKARANG`;
    }

    // Fungsi Simulasi Kalkulasi Estimasi Saldo Akhir secara Real-Time
    function hitungSimulasiSaldo() {
        const inputNominal = parseFloat(document.getElementById('input_nominal').value) || 0;
        const totalEstimasi = currentSiswaSaldo + inputNominal;
        document.getElementById('lbl_saldo_baru').innerText = `Rp ${new Intl.NumberFormat('id-ID').format(totalEstimasi)}`;
    }

    // Menutup Dropdown Pencarian Jika Pengguna Klik di Luar Area Input
    document.addEventListener('click', function(e) {
        if (!document.getElementById('pencarian_input').contains(e.target)) {
            document.getElementById('dropdown-search').classList.add('hidden');
        }
    });
</script>