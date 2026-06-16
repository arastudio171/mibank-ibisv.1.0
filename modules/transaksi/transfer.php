<?php

/**
 * Aplikasi Mini Bank Sekolah - Modul Transfer All-in-One (1 File Tunggal)
 * Berkas: modules/transaksi/transfer.php
 * Deskripsi: Fitur transfer mandiri antar siswa berbasis NISN (Aman & Sinkron Database)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. KONEKSI DATABASE (Relatif dari folder modules/transaksi/ ke auth/)
require_once __DIR__ . '/../../auth/database.php';

// Deteksi otomatis nama variabel koneksi Anda
$db_koneksi = null;
if (isset($pdo)) {
    $db_koneksi = $pdo;
} elseif (isset($conn)) {
    $db_koneksi = $conn;
} elseif (isset($koneksi)) {
    $db_koneksi = $koneksi;
} elseif (isset($db)) {
    $db_koneksi = $db;
}

// Data Sesi Login Nasabah/Siswa (Transfer mandiri via Akun Siswa)
$id_petugas = $_SESSION['id_user'] ?? null; // Null jika siswa melakukan transfer mandiri tanpa operator
$id_nasabah_pengirim = $_SESSION['id_nasabah'] ?? null;

// =========================================================================
// CONFIG MASTER RELASI ID (Sesuaikan dengan isi data master tabel Anda)
// =========================================================================
$id_jenis_transaksi  = 3; // MISAL: ID untuk 'Transfer' di tbl_jenis_transaksi
$id_metode_transaksi = 3; // MISAL: ID untuk 'Pindah Buku / Sistem' di tbl_metode_transaksi 

// Konfigurasi Default Sistem (Backup jika tbl_pengaturan kosong)
$minimal_saldo_mengendap = 15000;
$biaya_transfer_default  = 1500;
$limit_transfer_harian   = 50000;

// Ambil konfigurasi riil dari database
if ($db_koneksi instanceof PDO) {
    try {
        $cfg = $db_koneksi->query("SELECT minimal_saldo_mengendap, biaya_transfer_default, limit_transfer_harian FROM tbl_pengaturan LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($cfg) {
            $minimal_saldo_mengendap = floatval($cfg['minimal_saldo_mengendap']);
            $biaya_transfer_default  = floatval($cfg['biaya_transfer_default']);
            $limit_transfer_harian   = floatval($cfg['limit_transfer_harian']);
        }
    } catch (PDOException $e) { /* Abaikan jika terjadi galat fisik */
    }
}

// =========================================================================
// [BAGIAN A] AJAX ENDPOINT - MERESPON PENCARIAN NISN ALPINE.JS
// =========================================================================
if (isset($_GET['cek_nisn_tujuan'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $nisn_dicari = trim($_GET['cek_nisn_tujuan']);
    $response = ['sukses' => false, 'nama' => '', 'id' => '', 'kelas' => '', 'jurusan' => ''];

    if (!empty($nisn_dicari) && $db_koneksi instanceof PDO) {
        try {
            // Disesuaikan dengan DDL asli: Menggunakan ENUM kelas dan JOIN ke tbl_jurusan
            $stmt = $db_koneksi->prepare("
                SELECT n.id_nasabah, n.nama_nasabah, n.kelas, j.nama_jurusan, j.kode_jurusan 
                FROM tbl_nasabah n
                LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
                WHERE TRIM(n.nisn) = ? AND n.status_nasabah = 'aktif' 
                LIMIT 1
            ");
            $stmt->execute([$nisn_dicari]);
            $nasabah = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($nasabah) {
                if ($id_nasabah_pengirim && $nasabah['id_nasabah'] == $id_nasabah_pengirim) {
                    $response['error_debug'] = "Sistem menolak transaksi. Tidak diperbolehkan mengirim dana ke nomor NISN milik Anda sendiri.";
                } else {
                    $response = [
                        'sukses' => true,
                        'id' => $nasabah['id_nasabah'],
                        'nama' => $nasabah['nama_nasabah'],
                        'kelas' => $nasabah['kelas'] ?? 'Tanpa Tingkat',
                        'jurusan' => $nasabah['nama_jurusan'] ?? 'Tanpa Jurusan',
                        'kode_jurusan' => $nasabah['kode_jurusan'] ?? 'N/A'
                    ];
                }
            }
        } catch (PDOException $e) {
            $response['error_debug'] = $e->getMessage();
        }
    }
    echo json_encode($response);
    exit;
}

// =========================================================================
// [BAGIAN B] PROSES POST - EKSEKUSI TRANSFER KE DATABASE
// =========================================================================
$error_msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_transfer'])) {
    $id_penerima     = filter_var($_POST['id_penerima'], FILTER_VALIDATE_INT);
    $jumlah_transfer = floatval($_POST['jumlah_transfer']);
    $keterangan      = !empty($_POST['keterangan']) ? trim($_POST['keterangan']) : 'Transfer Dana Antar Siswa';
    $pin_sandi       = $_POST['pin_sandi'] ?? '';

    // Total beban saldo pengirim = nominal transfer + biaya transfer khusus
    $total_debet     = $jumlah_transfer + $biaya_transfer_default;

    try {
        if (!$db_koneksi) throw new Exception("Koneksi ke pangkalan data terputus. Sila hubungi teknisi sistem.");
        if (!$id_nasabah_pengirim) throw new Exception("Sesi masuk akun pengirim tidak valid atau telah berakhir. Silakan log in kembali.");
        if (empty($pin_sandi)) throw new Exception("Otentikasi gagal. Kode PIN/Kata Sandi konfirmasi wajib diisi.");
        if (!$id_penerima) throw new Exception("Gagal memproses. Data nasabah penerima belum melalui tahapan verifikasi nomor NISN.");
        if ($id_nasabah_pengirim == $id_penerima) throw new Exception("Transaksi ditolak! Rekening tujuan identik dengan rekening asal.");
        if ($jumlah_transfer <= 0) throw new Exception("Nominal transfer tidak valid. Nilai uang wajib lebih besar dari Rp 0.");

        $db_koneksi->beginTransaction();

        // 1. Ambil data saldo & otentikasi pengirim
        // CATATAN: Jika Anda ingin menggunakan `pin_transaksi` ganti kolom password di bawah menjadi pin_transaksi
        $stmt_pengirim = $db_koneksi->prepare("SELECT saldo, nama_nasabah, password FROM tbl_nasabah WHERE id_nasabah = ? FOR UPDATE");
        $stmt_pengirim->execute([$id_nasabah_pengirim]);
        $pengirim = $stmt_pengirim->fetch(PDO::FETCH_ASSOC);

        if (!$pengirim) throw new Exception("Konfigurasi profil rekening utama Anda gagal dimuat oleh sistem.");

        // Validasi Sandi Keamanan
        if (!password_verify($pin_sandi, $pengirim['password'])) {
            throw new Exception("PIN atau Kata Sandi Keamanan yang Anda masukkan salah. Pemindahan dana dibatalkan.");
        }

        // 2. Cek Limit Harian (Disesuaikan menggunakan id_jenis_transaksi sesuai DDL Anda)
        $stmt_limit = $db_koneksi->prepare("
            SELECT SUM(jumlah) as total_hari_ini 
            FROM tbl_transaksi 
            WHERE id_nasabah = ? AND id_jenis_transaksi = ? AND DATE(tanggal_transaksi) = CURRENT_DATE
        ");
        $stmt_limit->execute([$id_nasabah_pengirim, $id_jenis_transaksi]);
        $total_hari_ini = floatval($stmt_limit->fetchColumn());

        if (($total_hari_ini + $jumlah_transfer) > $limit_transfer_harian) {
            throw new Exception("Transaksi gagal. Akumulasi transfer Anda melampaui limit harian. Sisa kuota hari ini: Rp " . number_format($limit_transfer_harian - $total_hari_ini, 0, ',', '.'));
        }

        // 3. Cek Saldo Kecukupan Dana Pengirim
        $saldo_awal_pengirim = floatval($pengirim['saldo']);
        if (($saldo_awal_pengirim - $total_debet) < $minimal_saldo_mengendap) {
            throw new Exception("Batas saldo tidak mencukupi. Berdasarkan regulasi, saldo akhir setelah transfer harus menyisakan minimal Rp " . number_format($minimal_saldo_mengendap, 0, ',', '.'));
        }

        // 4. Ambil Dana Penerima
        $stmt_penerima = $db_koneksi->prepare("SELECT saldo, nama_nasabah FROM tbl_nasabah WHERE id_nasabah = ? FOR UPDATE");
        $stmt_penerima->execute([$id_penerima]);
        $penerima = $stmt_penerima->fetch(PDO::FETCH_ASSOC);

        if (!$penerima) throw new Exception("Gagal mendeteksi tujuan. Rekening siswa sasaran tidak aktif atau dibekukan.");
        $saldo_awal_penerima = floatval($penerima['saldo']);

        // 5. Eksekusi Perubahan Saldo di Database
        $saldo_baru_pengirim = $saldo_awal_pengirim - $total_debet;
        $saldo_baru_penerima = $saldo_awal_penerima + $jumlah_transfer;

        $update_pengirim = $db_koneksi->prepare("UPDATE tbl_nasabah SET saldo = ? WHERE id_nasabah = ?");
        $update_pengirim->execute([$saldo_baru_pengirim, $id_nasabah_pengirim]);

        $update_penerima = $db_koneksi->prepare("UPDATE tbl_nasabah SET saldo = ? WHERE id_nasabah = ?");
        $update_penerima->execute([$saldo_baru_penerima, $id_penerima]);

        // 6. Simpan Riwayat Log Transaksi Utama (Sesuai Struktur DDL Berbasis ID Relasi)
        $kode_transaksi = "TRX/" . date('Y/m') . "/TRF" . strtoupper(bin2hex(random_bytes(2)));
        $ket_lengkap = $keterangan . " (Ke: " . $penerima['nama_nasabah'] . ")";

        $log_transaksi = $db_koneksi->prepare("
            INSERT INTO tbl_transaksi 
            (kode_transaksi, id_nasabah, id_nasabah_penerima, id_jenis_transaksi, id_metode_transaksi, jumlah, biaya_admin, saldo_awal, saldo_akhir, keterangan, id_petugas, status_approval, tanggal_transaksi) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
        ");
        $log_transaksi->execute([
            $kode_transaksi,
            $id_nasabah_pengirim,
            $id_penerima,
            $id_jenis_transaksi,
            $id_metode_transaksi,
            $jumlah_transfer,
            $biaya_transfer_default, // Menyimpan biaya_transfer_default ke field biaya_admin di transaksi
            $saldo_awal_pengirim,
            $saldo_baru_pengirim,
            $ket_lengkap,
            $id_petugas
        ]);
        $id_transaksi_cetak = $db_koneksi->lastInsertId();

        // 7. ISI OTOMATIS TABEL MUTASI (PENGIRIM & PENERIMA)
        // A. Mutasi Sisi Pengirim (Debit sebesar Nominal + Biaya Admin Transfer)
        $stmt_mutasi_pengirim = $db_koneksi->prepare("
            INSERT INTO tbl_mutasi (id_nasabah, id_transaksi, jenis_mutasi, nominal, saldo_tersedia, keterangan, created_at)
            VALUES (?, ?, 'debit', ?, ?, ?, NOW())
        ");
        $stmt_mutasi_pengirim->execute([
            $id_nasabah_pengirim,
            $id_transaksi_cetak,
            $total_debet,
            $saldo_baru_pengirim,
            "Transfer ke " . $penerima['nama_nasabah'] . " (Biaya TRF: Rp " . number_format($biaya_transfer_default, 0, ',', '.') . ")"
        ]);

        // B. Mutasi Sisi Penerima (Kredit sebesar Nominal Transfer Murni)
        $stmt_mutasi_penerima = $db_koneksi->prepare("
            INSERT INTO tbl_mutasi (id_nasabah, id_transaksi, jenis_mutasi, nominal, saldo_tersedia, keterangan, created_at)
            VALUES (?, ?, 'kredit', ?, ?, ?, NOW())
        ");
        $stmt_mutasi_penerima->execute([
            $id_penerima,
            $id_transaksi_cetak,
            $jumlah_transfer,
            $saldo_baru_penerima,
            "Transfer masuk dari " . $pengirim['nama_nasabah']
        ]);

        // 8. Kirim Notifikasi Sistem ke Kedua Belah Pihak
        // Notifikasi Pengirim
        $ins_notif_pengirim = $db_koneksi->prepare("INSERT INTO tbl_notifikasi (id_nasabah, judul, pesan, is_read, created_at) VALUES (?, 'Transfer Keluar Sukses', ?, 0, NOW())");
        $ins_notif_pengirim->execute([$id_nasabah_pengirim, "Transfer dana ke " . $penerima['nama_nasabah'] . " senilai Rp " . number_format($jumlah_transfer, 0, ',', '.') . " berhasil dilakukan."]);

        // Notifikasi Penerima
        $pesan_notif_penerima = "Saldo masuk dari " . $pengirim['nama_nasabah'] . " sebesar Rp " . number_format($jumlah_transfer, 0, ',', '.') . ". Berita: " . $keterangan;
        $ins_notif_penerima = $db_koneksi->prepare("INSERT INTO tbl_notifikasi (id_nasabah, judul, pesan, is_read, created_at) VALUES (?, 'Saldo Masuk (Transfer)', ?, 0, NOW())");
        $ins_notif_penerima->execute([$id_penerima, $pesan_notif_penerima]);

        $db_koneksi->commit();

        echo "<script>
            alert('Sukses! Pemindahan dana tabungan berhasil diproses.');
            window.open('modules/transaksi/cetak-struk.php?id=$id_transaksi_cetak', '_blank');
            window.location.href = '../../main.php?page=transfer-dana';
        </script>";
        exit;
    } catch (Exception $e) {
        if ($db_koneksi && $db_koneksi->inTransaction()) $db_koneksi->rollBack();
        $error_msg = $e->getMessage();

        echo "<script>
            alert('Gagal Memproses Transaksi: " . addslashes($error_msg) . "');
            window.location.href = '../../main.php?page=transfer-dana';
        </script>";
        exit;
    }
}

// =========================================================================
// [BAGIAN C] ANTARMUKA TAMPILAN USER (HTML / ALPINE.JS / TAILWIND)
// =========================================================================
?>

<div id="section-transfer" class="w-full xl:max-w-12xl"
    x-data="{
        step: 1,
        nisnTujuan: '',
        loadingCek: false,
        penerimaId: '',
        penerimaNama: '',
        penerimaKelas: '',
        penerimaKodeJurusan: '',
        penerimaJurusan: '', // Tambahan untuk menampung data jurusan
        msgErrorLocal: '',
        nominalTransfer: '',
        showModalPin: false,
        pinSandi: '',

        aksiCekNisn() {
            if(this.nisnTujuan.trim() === '') {
                this.msgErrorLocal = 'Formulir kosong! Sila ketik nomor NISN siswa sasaran terlebih dahulu.';
                return;
            }
            this.loadingCek = true;
            this.msgErrorLocal = '';
            
            fetch(`modules/transaksi/transfer.php?cek_nisn_tujuan=${this.nisnTujuan}`)
                .then(res => {
                    if(!res.ok) throw new Error('Modul pemrosesan data server terganggu.');
                    return res.json();
                })
                .then(data => {
                    this.loadingCek = false;
                    if(data.sukses) {
                        this.penerimaId = data.id;
                        this.penerimaNama = data.nama;
                        this.penerimaKelas = data.kelas;
                        this.penerimaKodeJurusan = data.kode_jurusan; // Memasukkan data kode jurusan dari server
                        this.penerimaJurusan = data.jurusan; // Memasukkan data jurusan dari server
                        this.step = 2; 
                    } else {
                        this.msgErrorLocal = data.error_debug ? data.error_debug : 'Verifikasi Gagal: Nomor NISN tidak ditemukan atau status akun siswa sedang tidak aktif.';
                        this.step = 1;
                    }
                })
                .catch(err => {
                    this.loadingCek = false;
                    this.msgErrorLocal = 'Koneksi Bermasalah: Gagal membangun komunikasi dengan modul enkripsi transfer.';
                });
        },

        bukaKonfirmasiPin() {
            if (!this.nominalTransfer || this.nominalTransfer <= 0) {
                alert('Silakan tentukan nominal jumlah dana transfer terlebih dahulu.');
                return;
            }
            this.pinSandi = ''; 
            this.showModalPin = true;
        }
     }">

    <div class="bg-white p-4 sm:p-6 md:p-8 rounded-[1.25rem] sm:rounded-[1rem] border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
            <div>
                <h3 class="font-black text-[#506a8a] flex items-center gap-2">
                    <i class="fas fa-random text-amber-500"></i>Kirim Saldo Tabungan
                </h3>
                <p class="text-[11px] text-slate-400 font-medium">Layanan distribusi dan kirim saldo simpanan instan aman terintegrasi Data Pokok Pendidikan.</p>
            </div>
            <a href="?page=main" class="text-[10px] font-black uppercase tracking-wider text-slate-400 hover:text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                <i class="fas fa-arrow-left mr-1.5"></i> Kembali
            </a>
        </div>

        <form id="formTransferUtama" method="POST" action="modules/transaksi/transfer.php" class="space-y-5 md:space-y-6">
            <input type="hidden" name="proses_transfer" value="1">
            <input type="hidden" name="id_penerima" :value="penerimaId">
            <input type="hidden" name="pin_sandi" :value="pinSandi">

            <div class="space-y-3">
                <label class="text-[11px] font-black text-slate-500 tracking-wider flex items-center gap-2">
                    <span class="w-5 h-5 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] font-bold">1</span>
                    REKENING TUJUAN NASABAH
                </label>

                <div class="p-3 bg-slate-50 rounded-xl text-[11px] text-slate-500 flex items-start gap-2 border border-slate-100">
                    <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                    <span>Sistem menggunakan nomor identitas <strong>NISN Aktif</strong> sebagai nomor rekening pemindahbukan dana.</span>
                </div>

                <div class="space-y-3">
                    <input type="number" name="nisn_tujuan" x-model="nisnTujuan" :disabled="step > 1" placeholder="Masukkan 10 digit NISN penerima..."
                        class="w-full px-4 py-3 sm:py-3.5 border-2 border-slate-100 bg-slate-50 disabled:bg-slate-100 disabled:text-slate-400 font-mono font-black text-sm sm:text-base rounded-xl outline-none focus:border-blue-500 transition-all">
                    <button type="button" x-show="step === 1" @click="aksiCekNisn()" :disabled="loadingCek"
                        class="w-full hover:opacity-95 py-3.5 sm:bg-gradient-to-br sm:from-[#2978d7] sm:via-[#1566c7] sm:to-[#1257aa] text-white font-black text-xs capitalize tracking-wider rounded-xl shadow-md flex items-center justify-center gap-2 cursor-pointer transition-colors">
                        <span x-show="!loadingCek"><i class="fas fa-shield-alt mr-2"></i> Cek Nomor NISN</span>
                        <span x-show="loadingCek" class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                    </button>
                </div>

                <template x-if="msgErrorLocal">
                    <div class="p-3 bg-rose-50 border border-rose-100 rounded-xl text-xs font-bold text-rose-600 flex items-center gap-2 mt-2">
                        <i class="fas fa-times-circle text-rose-500"></i>
                        <span x-text="msgErrorLocal"></span>
                    </div>
                </template>
            </div>

            <div x-show="step === 2" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" class="overflow-hidden bg-gradient-to-br from-slate-50 via-blue-50/30 to-indigo-50/40 border border-blue-100/80 rounded-2xl p-0.5 shadow-sm">
                <div class="p-4 sm:p-5">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">

                        <div class="flex items-start gap-4">
                            <!-- Container Utama Avatar Modern ala Bank Digital -->
                            <div class="relative shrink-0 group">
                                <!-- 1. Outer Glow Layer: Memberikan efek kedalaman (depth) dan bingkai lembut di bagian luar -->
                                <div class="p-1 bg-gradient-to-tr from-blue-50 via-indigo-50/50 to-white rounded-[1.15rem] ring-4 ring-blue-100/40 transition-all duration-300 group-hover:scale-105">

                                    <!-- 2. Inner Box: Menggunakan gradasi warna solid dengan efek bayangan dalam (shadow-inner) -->
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-[#3b8eed] via-[#1566c7] to-[#0b438a] text-white flex items-center justify-center shadow-md shadow-blue-500/20 relative overflow-hidden">

                                        <!-- Efek Pantulan Cahaya Diagonal (Glossy Overlay) -->
                                        <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-transparent opacity-70"></div>

                                        <!-- Ikon Utama -->
                                        <i class="fas fa-user-check text-xl drop-shadow-[0_2px_4px_rgba(0,0,0,0.15)] relative z-10"></i>
                                    </div>
                                </div>

                                <!-- 3. Badge Verifikasi: Diberi ring putih tebal (ring-2 ring-white) agar memotong latar belakang secara tegas dan rapi -->
                                <span class="absolute -bottom-1 -right-1 flex h-5 w-5">
                                    <!-- Efek Denyut Sinyal / Ping yang Lebih Lembut -->
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>

                                    <!-- Isian Badge dengan Gradasi Hijau Mint ke Teal -->
                                    <span class="relative inline-flex rounded-full h-5 w-5 bg-gradient-to-br from-emerald-400 to-teal-500 items-center justify-center text-white ring-2 ring-white shadow-sm shadow-emerald-900/20">
                                        <i class="fas fa-check text-[8px] font-black"></i>
                                    </span>
                                </span>
                            </div>
                            <div class="space-y-1">

                                <h5 class="text-base sm:text-lg font-semibold text-slate-800 tracking-wider uppercase leading-tight antialiased" x-text="penerimaNama"></h5>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold text-slate-500">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-graduation-cap text-amber-500 text-sm"></i>
                                        Kelas: <strong class="text-slate-700" x-text="penerimaKelas"></strong>
                                        <strong class="text-slate-700" x-text="penerimaKodeJurusan"></strong>
                                    </span>
                                    <span class="hidden sm:inline text-slate-300">•</span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-book text-purple-500 text-sm"></i>
                                        Jurusan: <strong class="text-slate-700" x-text="penerimaJurusan"></strong>
                                    </span>
                                    <span class="hidden sm:inline text-slate-300">•</span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-address-card text-indigo-500"></i>
                                        NISN: <strong class="text-slate-700 font-mono" x-text="nisnTujuan"></strong>
                                    </span>
                                </div>

                                <p class="text-[11px] text-slate-400 font-medium pt-1">
                                    <i class="fas fa-shield-alt text-slate-400 mr-1"></i> Mohon periksa kembali informasi rekening tujuan, identitas nasabah, dan nominal transfer. Pastikan seluruh data telah sesuai sebelum transaksi diproses.
                                </p>

                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700 border border-emerald-200/50">
                                        <i class="fas fa-check-circle"></i>
                                        Akun Terverifikasi
                                    </span>
                                    <span class="text-[11px] font-bold text-slate-400">ID Nasabah: #<span x-text="penerimaId" class="font-mono text-slate-600"></span></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-2 shrink-0 w-full lg:w-auto border-t lg:border-t-0 border-slate-100 pt-3 lg:pt-0">
                            <button type="button" @click="step = 1; nisnTujuan = ''; msgErrorLocal = ''; penerimaId = ''; penerimaNama = ''; penerimaKelas = ''; penerimaJurusan = ''; penerimaKodeJurusan = '';"
                                class="w-full sm:w-auto px-5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-700 font-bold text-[11px] uppercase tracking-wider rounded-xl cursor-pointer transition-colors flex items-center justify-center gap-1.5 order-2 sm:order-1">
                                <i class="fas fa-undo"></i> Salah Akun?
                            </button>

                            <button type="button" @click="step = 3"
                                class="w-full hover:opacity-95 sm:w-auto px-5 py-2 bg-gradient-to-br from-[#2978d7] via-[#1566c7] to-[#1257aa] border border-transparent text-white font-black text-[11px] uppercase tracking-wide rounded-xl cursor-pointer transition-all transform active:scale-95 flex items-center justify-center gap-2 order-1 sm:order-2">
                                Ya, Sudah Sesuai <i class="fas fa-arrow-right text-[10px]"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            <div x-show="step === 3" x-transition class="space-y-5 md:space-y-6 pt-5 sm:pt-6 border-t border-slate-100">

                <div class="space-y-2">
                    <label class="text-[11px] font-black text-slate-500 tracking-wider flex items-center gap-2">
                        <span class="w-5 h-5 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-bold">2</span>
                        NOMINAL JUMLAH TRANSFER
                    </label>

                    <div class="relative flex items-center">
                        <span class="absolute left-5 font-black text-slate-400 text-lg sm:text-xl">Rp</span>
                        <input type="number" name="jumlah_transfer" x-model="nominalTransfer" required placeholder="0" :min="1"
                            class="w-full pl-14 pr-5 py-3.5 sm:py-4 border-2 border-slate-100 bg-slate-50 rounded-xl focus:border-emerald-500 focus:bg-white font-black text-lg sm:text-xl text-emerald-600 outline-none transition-all">
                    </div>
                    <small class="block text-[10px] text-slate-400 mt-1 pl-1">
                        <i class="fas fa-info-circle text-slate-400 mr-1"></i> Masukkan jumlah transfer yang ingin Anda kirim.
                    </small>
                    <br>
                    <div class="space-y-1.5">
                        <span class="text-[10px] font-bold text-slate-400 block"><i class="fas fa-th-large mr-1"></i> Pilih Nominal Instan:</span>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="nominalTransfer = 5000" class="py-2 text-[11px] font-black rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-300 transition-colors">Rp. 5.000</button>
                            <button type="button" @click="nominalTransfer = 10000" class="py-2 text-[11px] font-black rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-300 transition-colors">Rp. 10.000</button>
                            <button type="button" @click="nominalTransfer = 20000" class="py-2 text-[11px] font-black rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-300 transition-colors">Rp. 20.000</button>
                            <button type="button" @click="nominalTransfer = 25000" class="py-2 text-[11px] font-black rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-300 transition-colors">Rp. 25.000</button>
                            <button type="button" @click="nominalTransfer = 50000" class="py-2 text-[11px] font-black rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-300 transition-colors">Rp. 50.000</button>
                            <button type="button" @click="nominalTransfer = ''" class="py-2 text-[11px] font-black rounded-lg border border-slate-200 text-rose-600 bg-rose-50/50 hover:bg-rose-100 transition-colors">Reset</button>
                        </div>
                    </div>

                    <span class="block text-[10px] font-semibold text-slate-400 mt-1 pl-1">
                        <i class="fas fa-info-circle text-amber-500 mr-1"></i> Pastikan saldo utama mencukupi untuk penarikan nominal di atas + biaya admin sistem.
                    </span>
                </div>

                <div class="space-y-2">
                    <label class="text-[11px] font-black text-slate-500 tracking-wider flex items-center gap-2">
                        <span class="w-5 h-5 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-[10px] font-bold">3</span>
                        BERITA / KETERANGAN TAMBAHAN
                    </label>
                    <input type="text" name="keterangan" placeholder="Contoh: Pengembalian uang buku, iuran kelompok..." maxlength="120"
                        class="w-full px-4 py-3 border-2 border-slate-100 bg-slate-50 rounded-xl outline-none font-medium text-xs sm:text-sm text-slate-700 focus:border-purple-500 focus:bg-white transition-all">

                    <span class="block text-[10px] font-semibold text-slate-400 mt-1 pl-1">
                        <i class="fas fa-comment-dots text-purple-500 mr-1"></i> Keterangan akan dicetak pada struk transaksi serta muncul di riwayat mutasi penerima.
                    </span>
                </div>

                <div class="p-3 sm:p-4 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-900 font-semibold space-y-1.5 shadow-inner">
                    <span class="block text-[10px] font-black uppercase tracking-wider text-amber-800"><i class="fas fa-exclamation-triangle mr-1"></i> Kebijakan Finansial Transaksi:</span>
                    <ul class="list-disc pl-5 font-medium text-amber-800/90 space-y-0.5">
                        <li>Beban biaya operasional administrasi senilai <span class="font-bold text-amber-600">Rp <?= number_format($biaya_transfer_default, 0, ',', '.') ?></span> per transaksi.</li>
                        <li>Sistem mewajibkan limitasi ambang saldo mengendap minimum sebesar <span class="font-bold">Rp <?= number_format($minimal_saldo_mengendap, 0, ',', '.') ?></span>.</li>
                    </ul>
                </div>

                <button type="button" @click="bukaKonfirmasiPin()" class="w-full py-3.5 sm:py-4 sm:bg-gradient-to-br sm:from-[#2978d7] sm:via-[#1566c7] sm:to-[#1257aa] hover:opacity-95 text-white font-black text-xs uppercase tracking-widest rounded-xl cursor-pointer transition-transform transform active:scale-[0.99] text-center">
                    <i class="fas fa-paper-plane mr-2"></i> Kirim Saldo Sekarang
                </button>
            </div>
        </form>
    </div>

    <!-- MODAL POPUP KONFIRMASI PIN KEAMANAN (STRUKTUR BARU) -->
    <div x-show="showModalPin" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 transition-all duration-300 ease-out overflow-y-auto backdrop-blur-xs"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;">

        <div class="bg-slate-50 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all duration-300 ease-out my-8"
            @click.away="showModalPin = false"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="scale-95 opacity-0"
            x-transition:enter-end="scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="scale-100 opacity-100"
            x-transition:leave-end="scale-95 opacity-0">

            <!-- HEADER MODAL -->
            <div class="bg-[#1566c7] p-5 text-white flex justify-between items-center shadow-md">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider flex items-center gap-2">
                        <i class="fas fa-lock text-amber-300"></i> Otentikasi Keamanan
                    </h3>
                    <p class="text-[10px] text-slate-200 mt-0.5 font-medium">Sistem membutuhkan konfirmasi identitas pemegang akun tabungan.</p>
                </div>
                <button type="button" @click="showModalPin = false" class="text-white/80 hover:text-white text-xl focus:outline-none transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- KONTEN MODAL -->
            <div class="p-5 space-y-4">
                <!-- NOTIFIKASI PRINSIP KEAMANAN -->
                <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-xl text-[11px] font-medium flex gap-2">
                    <i class="fas fa-shield-alt text-base text-amber-500 shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="font-black block text-amber-900 mb-0.5">PENTING (VALIDASI TRANSFER):</strong>
                        Mendeteksi permintaan transfer dana sebesar <span class="font-bold text-amber-950">Rp<span x-text="Number(nominalTransfer).toLocaleString('id-ID')"></span></span> menuju siswa <span class="underline font-bold text-amber-950 uppercase" x-text="penerimaNama"></span>. Tindakan ini tidak dapat dibatalkan setelah PIN diproses.
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- INPUT GROUP PIN / SANDI AKUN -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs focus-within:border-[#1566c7] focus-within:ring-2 focus-within:ring-indigo-100 transition-all">
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">
                                <i class="fas fa-key text-indigo-500 mr-1"></i> Masukkan PIN / Sandi Dompet
                            </label>
                            <span class="text-[9px] font-black text-slate-400 uppercase bg-slate-100 px-1.5 py-0.5 rounded tracking-wide">Secure</span>
                        </div>

                        <div class="relative">
                            <span class="absolute left-4 top-2.5 text-slate-400 font-extrabold text-base">
                                <i class="fas fa-lock text-sm"></i>
                            </span>
                            <input type="password" x-model="pinSandi" placeholder="••••••••" autocomplete="current-password" required
                                class="w-full pl-11 pr-4 py-2.5 border-b-2 border-slate-100 focus:border-[#1566c7] outline-none font-black text-slate-800 text-lg tracking-widest font-mono">
                        </div>

                        <small class="text-[10px] text-slate-400 mt-2 block font-medium">
                            <i class="fas fa-info-circle text-emerald-400 mr-1"></i> Masukkan sandi otorisasi atau PIN rahasia transaksi Anda.
                        </small>
                    </div>

                    <!-- AKSI TOMBOL -->
                    <div class="pt-1 flex gap-2">
                        <!-- <button type="button" @click="showModalPin = false" class="w-1/3 bg-slate-200 hover:bg-slate-300 text-slate-600 text-[10px] font-bold py-3 rounded-xl transition-all tracking-wider uppercase">
                            Batal
                        </button> -->
                        <button type="button" @click="if(!pinSandi.trim()){ alert('Harap isi Sandi/PIN keamanan terlebih dahulu.'); return; }; document.getElementById('formTransferUtama').submit();"
                            class="w-full flex items-center justify-center bg-slate-700 hover:bg-slate-800 text-white text-[10px] font-bold py-3 rounded-xl transition-all shadow-md tracking-wider uppercase">
                            <i class="fas fa-paper-plane mr-2"></i> Otorisasi & Kirim Dana
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>