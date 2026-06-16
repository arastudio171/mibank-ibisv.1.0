<?php

/**
 * ==========================================
 * 1. INISIALISASI & CONFIG DASAR
 * ==========================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengambil data primary key dari session login
$id_nasabah = $_SESSION['id_nasabah'] ?? null;
$nisn       = $_SESSION['nisn'] ?? 'NISN tidak tersedia';

// Default state penampung data UI agar tidak memicu error undefined variable di HTML
$saldo_riil        = 0;
$riwayat_transaksi = [];
$poin_literasi     = 0;
$judul_target      = "Belum Ada Target";
$nominal_target    = 500000; // Default batas target
$persen            = 0;

// Variabel penampung Info Akun & Jurusan Dinamis (Dipakai di HTML widget)
$pemilik_akun     = $_SESSION['nama'] ?? 'Pengguna';
$program_keahlian = 'Program Keahlian Tidak Tersedia';


/**
 * ==========================================
 * 2. PROSES FORM ACTIONS (POST)
 * ==========================================
 * Diproses di awal agar perubahan data langsung memengaruhi query SELECT di bawahnya.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_target']) && $id_nasabah) {
    $nama_target    = trim($_POST['nama_target']);
    $nominal_target = floatval($_POST['nominal_target']);

    if (!empty($nama_target) && $nominal_target > 0) {
        try {
            $pdo->beginTransaction();

            // Batalkan seluruh target aktif sebelumnya (Siswa hanya boleh memiliki 1 target aktif)
            $deactivate = $pdo->prepare("UPDATE tbl_target_tabungan SET status_target = 'canceled' WHERE id_nasabah = ? AND status_target = 'active'");
            $deactivate->execute([$id_nasabah]);

            // Masukkan data target yang baru
            $insert = $pdo->prepare("INSERT INTO tbl_target_tabungan (id_nasabah, nama_target, nominal_target, status_target) VALUES (?, ?, ?, 'active')");
            $insert->execute([$id_nasabah, $nama_target, $nominal_target]);

            $pdo->commit();

            // Refresh halaman agar data terbaru langsung ter-render bersih tanpa re-submit form
            echo "<script>alert('Target tabungan berhasil diperbarui!'); window.location.href='';</script>";
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Gagal memproses perubahan target: " . $e->getMessage());
            echo "<script>alert('Gagal menyimpan target baru. Silakan coba kembali.');</script>";
        }
    }
}


/**
 * ==========================================
 * 3. QUERY DATA VIEW / DASHBOARD (SELECT)
 * ==========================================
 */
if ($id_nasabah) {
    try {
        // A. Ambil Saldo, Kelas, dan Nama Jurusan Resmi dengan LEFT JOIN ke tbl_jurusan
        // Menggunakan alias 'n' dan 'j' agar nama kolom spesifik & tidak bentrok (Ambiguous)
        $stmt_nasabah = $pdo->prepare("
            SELECT 
                n.nama_nasabah, 
                n.saldo, 
                n.kelas, 
                j.kode_jurusan, 
                j.nama_jurusan 
            FROM tbl_nasabah n
            LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan 
            WHERE n.id_nasabah = ?
        ");
        $stmt_nasabah->execute([$id_nasabah]);
        $row_nasabah = $stmt_nasabah->fetch(PDO::FETCH_ASSOC);

        if ($row_nasabah) {
            $saldo_riil = $row_nasabah['saldo'] ?? 0;

            // Simpan komponen nama dan kelas secara terpisah untuk HTML
            $nama_asli   = $row_nasabah['nama_nasabah'] ?? 'Pengguna';
            $kelas_siswa = $row_nasabah['kelas'] ?? '';
            $kode_jrs    = $row_nasabah['kode_jurusan'] ?? '';

            // Gabungkan teks kelas dan jurusan jika tersedia (Contoh: "XI BDP")
            $kelas_lengkap = (!empty($kelas_siswa) && !empty($kode_jrs)) ? $kelas_siswa . ' ' . $kode_jrs : '';

            // Format nama pendek untuk cadangan/fallback
            $program_keahlian = $row_nasabah['nama_jurusan'] ?? 'Umum / Non-Kejuruan';
        }

        // B. Query Mutasi: Ambil 3 Mutasi Terakhir Buku Tabungan Nasabah Aktif
        // Kolom dipetakan dengan jelas menggunakan alias (m.nominal, t.id_transaksi, dll)
        $stmt_trans = $pdo->prepare("
            SELECT 
                m.id_mutasi, 
                m.jenis_mutasi, 
                m.nominal, 
                m.keterangan AS keterangan_mutasi, 
                m.created_at AS tanggal_mutasi,
                t.id_transaksi,
                t.id_nasabah AS id_pengirim,
                t.id_nasabah_penerima
            FROM tbl_mutasi m
            LEFT JOIN tbl_transaksi t ON m.id_transaksi = t.id_transaksi
            WHERE m.id_nasabah = ?
            ORDER BY m.created_at DESC 
            LIMIT 3
        ");
        $stmt_trans->execute([$id_nasabah]);
        $riwayat_transaksi = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);

        // C. Ambil Data Rencana Target Tabungan Aktif
        $stmt_target = $pdo->prepare("SELECT nama_target, nominal_target FROM tbl_target_tabungan WHERE id_nasabah = ? AND status_target = 'active' LIMIT 1");
        $stmt_target->execute([$id_nasabah]);
        $data_target = $stmt_target->fetch(PDO::FETCH_ASSOC);

        if ($data_target) {
            $judul_target   = $data_target['nama_target'];
            $nominal_target = floatval($data_target['nominal_target']);
        }

        // D. Kalkulasi Bisnis / Logika Tambahan
        $poin_literasi = floor($saldo_riil / 1000); // Kelipatan Rp 1.000 = 1 Poin
        $persen        = $nominal_target > 0 ? min(100, round(($saldo_riil / $nominal_target) * 100)) : 0;
    } catch (PDOException $e) {
        error_log("Gagal memuat data dashboard nasabah: " . $e->getMessage());
    }
}


/**
 * ==========================================
 * 4. COMPONENT STYLING HELPERS (SINKRON MUTASI)
 * ==========================================
 */
function getTransactionStyle($tr, $id_nasabah_aktif)
{
    $jenis_mutasi = $tr['jenis_mutasi'] ?? 'kredit';
    $id_pengirim  = $tr['id_pengirim'] ?? 0;
    $id_penerima  = $tr['id_nasabah_penerima'] ?? null;

    // KREDIT = Uang Masuk / Saldo Bertambah
    if ($jenis_mutasi === 'kredit') {
        if ($id_penerima && $id_penerima == $id_nasabah_aktif) {
            return [
                'bg'     => 'bg-emerald-50',
                'text'   => 'text-emerald-600',
                'border' => 'border-emerald-100',
                'icon'   => 'fa-arrow-down',
                'amount' => 'text-emerald-600',
                'prefix' => '+',
                'label'  => 'Dana Masuk'
            ];
        }
        return [
            'bg'     => 'bg-blue-50',
            'text'   => 'text-blue-500',
            'border' => 'border-blue-100',
            'icon'   => 'fa-plus',
            'amount' => 'text-blue-600',
            'prefix' => '+',
            'label'  => 'Setor Tunai'
        ];
    }

    // DEBIT = Uang Keluar / Saldo Berkurang
    if ($jenis_mutasi === 'debit') {
        if ($id_pengirim == $id_nasabah_aktif && $id_penerima) {
            return [
                'bg'     => 'bg-purple-50',
                'text'   => 'text-purple-600',
                'border' => 'border-purple-100',
                'icon'   => 'fa-arrow-right',
                'amount' => 'text-purple-600',
                'prefix' => '-',
                'label'  => 'Transfer Keluar'
            ];
        }
        return [
            'bg'     => 'bg-rose-50',
            'text'   => 'text-rose-500',
            'border' => 'border-rose-100',
            'icon'   => 'fa-coins',
            'amount' => 'text-rose-600',
            'prefix' => '-',
            'label'  => 'Tarik Tunai'
        ];
    }

    return [
        'bg'     => 'bg-slate-50',
        'text'   => 'text-slate-500',
        'border' => 'border-slate-100',
        'icon'   => 'fa-exchange-alt',
        'amount' => 'text-slate-600',
        'prefix' => '',
        'label'  => 'Mutasi Sistem'
    ];
}
?>

<div x-data="{ alertOpen: true }" class="space-y-4 sm:space-y-6 max-w-12xl mx-auto px-3 sm:px-4 lg:px-0">

    <!-- ==========================================
         1. ALERT INFORMATIF (RESPONSIF HP)
         ========================================== -->
    <div x-show="alertOpen"
        x-transition:leave="transition ease-in duration-300 transform opacity-0 -translate-y-2"
        class="flex items-start justify-between p-3.5 sm:p-4 bg-blue-50 border border-blue-200 rounded-[1rem] text-blue-800 shadow-sm">
        <div class="flex gap-2.5 sm:gap-3">
            <div class="shrink-0 mt-0.5">
                <i class="fas fa-info-circle text-yellow-600 text-base sm:text-lg animate-pulse"></i>
            </div>
            <div class="space-y-0.5 sm:space-y-1">
                <h5 class="text-xs sm:text-sm font-bold tracking-tight">Informasi Keamanan & Sistem</h5>
                <p class="text-[11px] sm:text-xs text-slate-700/90 leading-relaxed">
                    Halo, <span class="font-bold text-rose-600"><?= htmlspecialchars($pemilik_akun) ?></span> akun anda telah aktif. Seluruh data saldo, target tabungan, dan mutasi disinkronkan secara real-time. Sesi aman diperbarui otomatis hingga <span class="font-bold"><?= date('H:i:s', strtotime('+15 minutes')) ?> WIB</span>.
                </p>
            </div>
        </div>
        <button @click="alertOpen = false" class="shrink-0 p-1 text-blue-500 hover:text-blue-800 hover:bg-blue-100/50 rounded-lg transition-colors cursor-pointer ml-1">
            <i class="fas fa-times text-xs sm:text-sm"></i>
        </button>
    </div>

    <!-- ==========================================
         2. BARIS UTAMA: GRID SALDO (6) & RENCANA (6)
         ========================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 items-stretch">

        <!-- KOLOM KIRI: CARD SALDO (TAMPILAN ASLI + ISIAN RESPONSIF) -->
        <div x-data="{ showBalance: false }" class="relative overflow-hidden bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] p-5 sm:p-8 rounded-[1rem] text-white shadow-2xl shadow-blue-200 transition-all duration-500 hover:shadow-blue-300 group flex flex-col justify-between">
            <!-- Ornamen Latar Belakang (Asli) -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-white/10 rounded-full blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
            <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-indigo-400/20 rounded-full blur-2xl"></div>

            <div class="relative z-10 w-full space-y-4 sm:space-y-5">
                <div class="flex justify-between items-start">
                    <div class="space-y-1 w-full">
                        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                            <p class="text-[9px] sm:text-[10px] font-black capitalize tracking-[0.25em] text-blue-100/60">
                                <i class="fas fa-coins text-yellow-400 text-sm sm:text-lg"></i>
                                SALDO TABUNGAN
                            </p>

                            <!-- Tombol Show/Hide (Asli Di Atas) -->
                            <button @click="showBalance = !showBalance"
                                class="flex items-center gap-1 px-2.5 py-0.5 sm:px-3 sm:py-1 bg-white/10 hover:bg-white/20 border border-white/20 rounded-full transition-all active:scale-90 backdrop-blur-md cursor-pointer">
                                <i class="fas text-[8px] sm:text-[9px]" :class="showBalance ? 'fa-eye-slash' : 'fa-eye'"></i>
                                <span class="text-[7px] sm:text-[8px] font-black capitalize tracking-widest" x-text="showBalance ? 'Sembunyikan' : 'Lihat Detail'"></span>
                            </button>
                        </div>

                        <div class="flex items-baseline gap-1 sm:gap-2 mt-1 sm:mt-2">
                            <span class="text-lg sm:text-xl font-bold text-blue-200/80">Rp</span>
                            <!-- Ukuran teks mengecil otomatis di HP (text-3xl) dan membesar di desktop (md:text-5xl) agar tidak pecah/lewat batas screen -->
                            <h1 class="text-4xl sm:text-4xl md:text-5xl font-extrabold tracking-tight truncate flex items-baseline">
                                <span x-text="showBalance ? '<?= number_format($saldo_riil, 0, ',', '.') ?>' : '••••••'"></span>

                                <span x-show="showBalance" class="text-lg sm:text-xl md:text-2xl font-semibold opacity-80 ml-0.5">,00</span>
                            </h1>
                        </div>

                        <!-- Batas Informasi Detail (Edisi Ragam Warna & Teks Profesional) -->
                        <div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-white/10 space-y-1.5 sm:space-y-2 text-[11px] sm:text-[12px]">
                            <!-- 1. Nomor Induk Nasional (NISN) -->
                            <p class="text-blue-100/70 font-medium flex items-center gap-2 truncate">
                                <i class="far fa-id-card text-teal-300 w-4 shrink-0 text-xs sm:text-sm"></i>
                                Nomor Induk (NISN): <span class="text-white font-bold ml-0.5 tracking-wider"><?= htmlspecialchars($nisn) ?></span>
                            </p>

                            <!-- 2. Nama Pemilik Akun -->
                            <p class="text-blue-100/70 font-medium flex items-center gap-2 truncate">
                                <i class="far fa-user text-amber-400 w-4 shrink-0 text-xs sm:text-sm"></i>
                                Pemilik Akun:
                                <span class="ml-0.5 uppercase tracking-wide flex items-center gap-1.5">
                                    <span class="text-amber-400 font-black"><?= htmlspecialchars($nama_asli) ?></span>

                                    <?php if (!empty($kelas_lengkap)): ?>
                                        <span class="text-white font-bold">(<?= htmlspecialchars($kelas_lengkap) ?>)</span>
                                    <?php endif; ?>
                                </span>
                            </p>

                            <!-- 3. Nama Pemilik Akun -->
                            <p class="text-blue-100/70 font-medium flex items-center gap-2 truncate mt-1">
                                <i class="fas fa-graduation-cap text-emerald-400 w-4 shrink-0 text-xs sm:text-sm"></i>
                                Program Keahlian: <span class="text-white font-semibold ml-0.5"><?= htmlspecialchars($program_keahlian) ?></span>
                            </p>

                            <!-- 4. Nama Pemilik Akun -->
                            <p class="text-blue-100/70 font-medium flex items-center gap-2">
                                <i class="far fa-calendar-alt text-sky-300 w-4 shrink-0 text-xs sm:text-sm"></i>
                                Tanggal Sinkronisasi: <span class="text-white font-semibold ml-0.5"><?= date('l, d M Y') ?></span>
                            </p>

                            <!-- 5. Nama Pemilik Akun -->
                            <p class="text-blue-100/70 font-medium flex items-center gap-2">
                                <i class="far fa-clock text-fuchsia-300 w-4 shrink-0 text-xs sm:text-sm"></i>
                                Jam Pembaruan: <span class="text-white font-semibold ml-0.5"><?= date('H:i:s') ?> WIB</span>
                            </p>

                            <!-- Kalimat Informatif Tambahan (Sekarang Memiliki Padding Fleksibel & Teks Fleksibel) -->
                            <div class="mt-4 bg-white/5 border border-white/10 p-2 sm:p-3 rounded-xl backdrop-blur-sm text-[10px] sm:text-[11px] text-blue-100/90 leading-relaxed">
                                <i class="fas fa-shield-alt text-emerald-400 mr-1"></i> Saldo anda saat ini siap untuk dialokasikan.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: CARD RENCANA TABUNGAN (PUTIH MINIMALIS + TOMBOL GRADIENT) -->
        <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm p-5 sm:p-6 md:p-8 flex flex-col justify-between relative overflow-hidden group">
            <div class="bg-amber-50 rounded-full group-hover:bg-amber-100/50 transition-colors duration-500"></div>

            <div>
                <!-- Header Rencana -->
                <div class="flex justify-between items-start gap-2 mb-4">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg text-[9px] sm:text-[10px] font-black capitalize tracking-wider">
                            <i class="fas fa-wallet text-xs"></i> Financial Goals
                        </span>
                    </div>
                    <!-- Aksen Badge Menggunakan Gradient Halus -->
                    <span class="px-2.5 py-0.5 sm:px-3 sm:py-1 bg-green-600 text-white hover:opacity-95 text-[10px] sm:text-xs font-black rounded-lg shrink-0">
                        <?= $persen ?>% Tercapai
                    </span>
                </div>

                <!-- Detail Target (Grid responsif) -->
                <div class="bg-green-50 p-3.5 sm:p-4 rounded-xl grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 my-3 sm:my-4">
                    <div class="min-w-0">
                        <p class="text-[9px] sm:text-[10px] capitalize tracking-wider text-slate-400 font-bold">Target Impian</p>
                        <p class="text-xs sm:text-sm font-extrabold text-rose-600 mt-0.5 capitalize truncate"><?= htmlspecialchars($judul_target) ?></p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[9px] sm:text-[10px] capitalize tracking-wider text-slate-400 font-bold">Progres Nominal</p>
                        <p class="text-xs sm:text-sm font-bold text-slate-700 mt-0.5 truncate flex items-baseline gap-0.5">
                            <span>Rp <?= number_format($saldo_riil, 0, ',', '.') ?><span class="text-[10px] sm:text-xs font-normal text-slate-500">,00</span></span>

                            <span class="text-slate-300 mx-0.5">/</span>

                            <span class="text-rose-600 font-extrabold">Rp <?= number_format($nominal_target, 0, ',', '.') ?><span class="text-[10px] sm:text-xs font-normal text-rose-400">,00</span></span>
                        </p>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="space-y-1.5">
                    <div class="w-full h-2.5 sm:h-3 bg-slate-100 rounded-full overflow-hidden border border-slate-200/50">
                        <div class="h-full bg-gradient-to-br from-green-400 to-green-700 transition-all duration-700" style="width: <?= $persen ?>%"></div>
                    </div>
                    <p class="text-[9px] sm:text-[10px] text-slate-400 font-medium text-right flex items-baseline justify-end gap-0.5">
                        Kekurangan:
                        <span class="text-rose-500 font-bold ml-1">
                            Rp <?= number_format(max(0, $nominal_target - $saldo_riil), 0, ',', '.') ?><span class="text-[8px] sm:text-[9px] font-normal text-rose-400">,00</span>
                        </span>
                        lagi
                    </p>
                </div>
            </div>

            <!-- Tombol Aksi Target - GRADASI TETAP SAMA & SEKARANG MUNCUL DI HP -->
            <div class="mt-4 sm:mt-6">
                <!-- Perbaikan: Menghapus prefix sm: pada bg-gradient, from, via, dan to agar warna aktif di HP -->
                <button onclick="openTargetModal()" class="w-full py-2.5 sm:py-3 bg-[#1257aa] text-white hover:opacity-95 hover:brightness-110 text-white rounded-xl text-[11px] sm:text-xs font-black capitalize tracking-wider transition-all duration-300 active:scale-95 cursor-pointer flex items-center justify-center gap-2">
                    <i class="fas fa-bullseye text-[10px] sm:text-xs"></i> BUAT RENCANA TABUNGAN
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================
        4. WIDGET QUICK ACTIONS (BRIGHT VIBRANT THEME)
        ========================================== -->
    <div class="block lg:hidden w-full bg-white p-3 rounded-[1rem] shadow-sm border border-slate-100">
        <div class="grid grid-cols-4 gap-2">

            <a href="?page=qr-payment" class="group flex flex-col items-center gap-1.5 bg-transparent">
                <div class="w-9 h-9 bg-blue-50 text-[#2978d7] rounded-xl flex items-center justify-center border border-blue-100/70 group-hover:scale-105 transition-all shrink-0">
                    <i class="fas fa-qrcode text-sm"></i>
                </div>
                <div class="text-center min-w-0 w-full">
                    <h4 class="text-[10px] font-bold text-slate-700 tracking-tight truncate">QR Code</h4>
                </div>
            </a>

            <a href="?page=transfer-dana" class="group flex flex-col items-center gap-1.5 bg-transparent">
                <div class="w-9 h-9 bg-cyan-50 text-[#1566c7] rounded-xl flex items-center justify-center border border-cyan-100/70 group-hover:scale-105 transition-all shrink-0">
                    <i class="fas fa-exchange-alt text-sm"></i>
                </div>
                <div class="text-center min-w-0 w-full">
                    <h4 class="text-[10px] font-bold text-slate-700 tracking-tight truncate">Transfer</h4>
                </div>
            </a>

            <a href="?page=ambil-antrean" class="group flex flex-col items-center gap-1.5 bg-transparent">
                <div class="w-9 h-9 bg-indigo-50 text-[#4f46e5] rounded-xl flex items-center justify-center border border-indigo-100/70 group-hover:scale-105 transition-all relative shrink-0">
                    <i class="fas fa-ticket-alt text-sm"></i>
                    <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                    </span>
                </div>
                <div class="text-center min-w-0 w-full">
                    <h4 class="text-[10px] font-bold text-slate-700 tracking-tight truncate">Antrian</h4>
                </div>
            </a>

            <a href="?page=transaksi-nasabah" class="group flex flex-col items-center gap-1.5 bg-transparent">
                <div class="w-9 h-9 bg-purple-50 text-[#8b5cf6] rounded-xl flex items-center justify-center border border-purple-100/70 group-hover:scale-105 transition-all shrink-0">
                    <i class="fas fa-history text-sm"></i>
                </div>
                <div class="text-center min-w-0 w-full">
                    <h4 class="text-[10px] font-bold text-slate-700 tracking-tight truncate">Mutasi</h4>
                </div>
            </a>

        </div>
    </div>
    <div class="hidden lg:grid lg:grid-cols-2 lg:gap-6 w-full">

        <div class="grid grid-cols-3 gap-4">

            <a href="?page=qr-payment" class="group flex flex-row items-center gap-4 bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] p-4 rounded-[1rem] shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5">
                <div class="w-11 h-11 bg-white/15 text-white rounded-xl flex items-center justify-center group-hover:scale-105 transition-all shrink-0">
                    <i class="fas fa-qrcode text-base"></i>
                </div>
                <div class="text-left min-w-0 w-full">
                    <h4 class="text-sm font-bold text-white tracking-tight truncate">QR Payment</h4>
                    <p class="text-[10px] text-white/80 truncate">Transfer saldo melalui QR Code</p>
                </div>
            </a>

            <a href="?page=transfer-dana" class="group flex flex-row items-center gap-4 bg-gradient-to-br from-[#0ea5e9] via-[#0284c7] to-[#0369a1] p-4 rounded-[1rem] shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5">
                <div class="w-11 h-11 bg-white/15 text-white rounded-xl flex items-center justify-center group-hover:scale-105 transition-all shrink-0">
                    <i class="fas fa-exchange-alt text-base"></i>
                </div>
                <div class="text-left min-w-0 w-full">
                    <h4 class="text-sm font-bold text-white tracking-tight truncate">Transfer</h4>
                    <p class="text-[10px] text-white/80 truncate">Kirim saldo antar nasabah</p>
                </div>
            </a>

            <a href="?page=ambil-antrean" class="group flex flex-row items-center gap-4 bg-gradient-to-br from-[#6366f1] via-[#4f46e5] to-[#4338ca] p-4 rounded-[1rem] shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5">
                <div class="w-11 h-11 bg-white/15 text-white rounded-xl flex items-center justify-center group-hover:scale-105 transition-all relative shrink-0">
                    <i class="fas fa-ticket-alt text-base"></i>
                    <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                    </span>
                </div>
                <div class="text-left min-w-0 w-full">
                    <h4 class="text-sm font-bold text-white tracking-tight truncate">Antrian</h4>
                    <p class="text-[10px] text-white/80 truncate">Ambil token layanan cetak</p>
                </div>
            </a>

        </div>

        <div class="flex w-full">
            <a href="?page=transaksi-nasabah" class="group flex flex-row items-center gap-4 bg-gradient-to-br from-[#a855f7] via-[#8b5cf6] to-[#6d28d9] p-4 rounded-[1rem] shadow-sm hover:shadow-md transition-all duration-300 transform hover:-translate-y-0.5 w-full h-full">
                <div class="w-11 h-11 bg-white/15 text-white rounded-xl flex items-center justify-center group-hover:scale-105 transition-all shrink-0">
                    <i class="fas fa-history text-base"></i>
                </div>
                <div class="text-left min-w-0 w-full">
                    <h4 class="text-sm font-bold text-white tracking-tight truncate">Mutasi</h4>
                    <p class="text-[10px] text-white/80 truncate">Riwayat lengkap transaksi</p>
                </div>
            </a>
        </div>

    </div>

    <!-- ==========================================
        3. WIDGET INFAQ INFORMATIF (FULL WIDTH)
    ========================================== -->
    <div class="w-full bg-gradient-to-r from-green-50 to-emerald-50 border border-green-100 rounded-[1rem] p-4 sm:p-5 shadow-sm relative overflow-hidden group">
        <!-- Dekorasi latar belakang khas Islami/Geometris samar -->
        <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-green-100/40 rounded-full blur-2xl group-hover:scale-120 transition-transform duration-500"></div>

        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 relative z-10">
            <div class="flex items-start gap-4 min-w-0 flex-1">
                <!-- Icon Box (Green Theme) -->
                <div class="w-10 h-10 sm:w-12 sm:h-12 bg-amber-500 text-white rounded-xl flex items-center justify-center shadow-md shrink-0 mt-0.5 md:mt-0">
                    <i class="fas fa-hands-helping text-base sm:text-lg"></i>
                </div>
                <!-- Teks Kalimat Informatif Ringkas -->
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="text-sm font-extrabold text-green-600 tracking-tight">Infaq & Sedekah Digital</h4>
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-[9px] font-bold rounded-md uppercase tracking-wider">Fitur Berkah</span>
                    </div>
                    <p class="text-[11px] sm:text-xs text-slate-700/90 leading-relaxed">
                        <i class="fas fa-info-circle mr-1"></i>
                        Sisihkan sebagian saldo tabungan anda secara sukarela untuk mendukung pemeliharaan tempat ibadah dan bantuan sosial.
                    </p>
                </div>
            </div>
            <!-- Tombol Aksi Kanan -->
            <div class="w-full md:w-auto shrink-0 pt-2 md:pt-0">
                <a href="?page=infaq-dana" class="w-full md:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-bold text-xs tracking-wider rounded-xl transition-all duration-300 active:scale-95 shadow-lg shadow-green-600/20 cursor-pointer">
                    <i class="fas fa-heart text-[10px]"></i> MULAI BERINFAQ
                </a>
            </div>
        </div>
    </div>

    <!-- ==========================================
        5. RIWAYAT TRANSAKSI DENGAN LOGIKA REKENING KORAN MUTASI
        ========================================== -->
    <!-- <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-blue-50/60">
            <div>
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-sm sm:text-base">
                    <i class="fas fa-history text-yellow-500"></i> Aktivitas Finansial Terbaru
                </h3>
                <p class="text-[12px] text-slate-400 mt-0.5">Pantau seluruh aktivitas setoran, penarikan, dan mutasi saldo secara langsung.</p>
            </div>
        </div>

        <div class="divide-y divide-slate-100">
            <?php if (empty($riwayat_transaksi)): ?>
                <div class="p-8 sm:p-12 text-center text-slate-400 space-y-2">
                    <i class="fas fa-folder-open text-3xl sm:text-4xl text-slate-200"></i>
                    <p class="text-xs sm:text-sm">Belum terdeteksi adanya rekaman aktivitas mutasi terbaru.</p>
                </div>
            <?php else: ?>
                <?php foreach ($riwayat_transaksi as $tr):
                    // Sinkronisasi data visual menggunakan helper getTransactionStyle berbasis tbl_mutasi
                    $style = getTransactionStyle($tr, $id_nasabah);
                    $id_transaksi = $tr['id_transaksi'] ?? 0;
                ?>
                    <div class="p-3.5 sm:p-5 flex items-center justify-between hover:bg-slate-50/60 transition-colors gap-3">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="w-9 h-9 sm:w-11 sm:h-11 shrink-0 <?= $style['bg'] ?> <?= $style['text'] ?> rounded-xl flex items-center justify-center border <?= $style['border'] ?> shadow-sm">
                                <i class="fas <?= $style['icon'] ?> text-xs sm:text-sm"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs sm:text-sm font-bold text-slate-800 truncate tracking-tight">
                                    <?= htmlspecialchars($tr['keterangan_mutasi'] ?: 'Transaksi Tanpa Keterangan') ?>
                                </p>

                                <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 mt-1 text-[10px] sm:text-[11px] text-slate-400">
                                    <span class="inline-flex items-center font-bold bg-slate-100 px-1.5 py-0.5 rounded text-slate-600 text-[9px] sm:text-[10px]">
                                        <i class="far fa-calendar-alt mr-1 text-[9px]"></i>
                                        <?= date('l', strtotime($tr['tanggal_mutasi'])) ?>
                                    </span>
                                    <span class="text-slate-300">•</span>
                                    <span class="font-medium text-slate-500">
                                        <?= date('d M Y', strtotime($tr['tanggal_mutasi'])) ?>
                                    </span>
                                    <span class="text-slate-300">•</span>
                                    <span class="inline-flex items-center font-semibold text-slate-600 bg-blue-50/50 px-1 py-0.5 rounded border border-blue-100/30 text-[9px] sm:text-[10px]">
                                        <i class="far fa-clock mr-1 text-blue-500"></i>
                                        <?= date('H:i:s', strtotime($tr['tanggal_mutasi'])) ?> WIB
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 sm:gap-3 shrink-0 text-right">
                            <div class="space-y-0.5">
                                <p class="text-xs sm:text-sm font-black tracking-tight <?= $style['amount'] ?>">
                                    <?= $style['prefix'] ?> Rp<?= number_format($tr['nominal'], 0, ',', '.') ?>
                                </p>
                                <span class="text-[8px] sm:text-[9px] capitalize tracking-wider font-extrabold px-1.5 py-0.5 bg-slate-50 border border-slate-100 rounded text-slate-400 inline-block">
                                    <?= $style['label'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div> -->

</div>

<!-- ========================================== -->
<!-- MODAL POPUP: TAMBAH TARGET (LARGE SIZE - RESPONSIF) -->
<!-- ========================================== -->
<div id="targetModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/50 transition-opacity duration-300">

    <div class="bg-slate-50 rounded-2xl max-w-2xl w-full shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300 flex flex-col">

        <div class="bg-[#1566c7] p-5 text-white flex justify-between items-center shadow-md">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-flag text-amber-300"></i> Tentukan Target Finansialmu
                </h3>
                <p class="text-[10px] text-slate-200 mt-0.5 font-medium">Laporan rincian target impian, nominal kebutuhan, dan kalkulasi otomatis sistem.</p>
            </div>
            <button type="button" onclick="closeTargetModal()" class="text-white opacity-80 hover:opacity-100 transition-opacity text-xl cursor-pointer p-1">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-5 sm:p-6 space-y-4">

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                        <i class="fas fa-bullseye text-blue-500"></i> Apa Impian/Targetmu?
                    </label>
                    <input type="text" name="nama_target" placeholder="Contoh: Beli Laptop, Study Tour Bali" required
                        class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 font-medium text-slate-700 bg-slate-50/50 transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                        <i class="fas fa-money-bill-wave text-emerald-500"></i> Nominal yang Dibutuhkan (Rp)
                    </label>
                    <input type="number" name="nominal_target" placeholder="Contoh: 1000000" min="10000" required
                        class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 font-black text-slate-700 bg-slate-50/50 transition-all">
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl flex gap-3 items-start shadow-sm">
                <i class="fas fa-circle-info text-blue-500 text-sm mt-0.5 shrink-0"></i>
                <p class="text-[11px] text-blue-800 font-medium leading-relaxed">
                    Sistem akan otomatis menghitung persentase pencapaian berdasarkan saldo riil tabunganmu saat ini secara real-time. Target aktif sebelumnya akan otomatis digantikan secara aman oleh sistem enkripsi.
                </p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end gap-2.5 pt-4 border-t border-slate-200">
                <!-- <button type="button" onclick="closeTargetModal()"
                    class="w-full sm:w-32 flex items-center justify-center bg-slate-300 hover:bg-slate-400 text-slate-700 text-[10px] font-bold py-3 rounded-xl transition-all shadow-sm tracking-wider uppercase cursor-pointer">
                    Batal
                </button> -->
                <button type="submit" name="simpan_target"
                    class="w-full flex items-center justify-center bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase">
                    <i class="fas fa-save mr-2"></i> Simpan Target
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- JAVASCRIPT CONTROL UNTUK MODAL INTERAKSI -->
<!-- ========================================== -->
<script>
    const modal = document.getElementById('targetModal');

    function openTargetModal() {
        modal.classList.remove('hidden');
        // Trigger animasi scale up halus
        setTimeout(() => {
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }, 10);
    }

    function closeTargetModal() {
        modal.querySelector('.transform').classList.remove('scale-100');
        modal.querySelector('.transform').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 150); // Menunggu transisi animasi selesai
    }

    // Menutup modal jika user klik di luar area kotak modal
    window.onclick = function(event) {
        if (event.target == modal) {
            closeTargetModal();
        }
    }
</script>