<?php

/**
 * FILE: views/superadmin/pengaturan/index.php
 * DESKRIPSI: Pengaturan Sistem - Model Tabs Modern dengan Sinkronisasi Komponen Saldo Awal Default
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Posisi file hanya 2 tingkat dari root
require_once __DIR__ . '/../../auth/database.php';

// 🔒 VALIDASI HAK AKSES: Hanya Admin / Superadmin yang bisa mengonfigurasi sistem
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>window.location.href = 'auth/auth-login.php?msg=Akses terbatas!&type=error';</script>";
    exit();
}

$error_msg = '';
$success_msg = '';

// 1. AMBIL DATA PENGATURAN (Baris Pertama / ID 1)
try {
    $stmt = $pdo->query("SELECT * FROM tbl_pengaturan LIMIT 1");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika tabel kosong (belum ada row), buat default row agar tidak error
    if (!$setting) {
        $pdo->query("INSERT INTO tbl_pengaturan (id_pengaturan) VALUES (1)");
        $stmt = $pdo->query("SELECT * FROM tbl_pengaturan LIMIT 1");
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_msg = "Gagal memuat konfigurasi: " . $e->getMessage();
}

// 2. PROSES UPDATE DATA (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitisasi Input String
    $nama_aplikasi          = trim($_POST['nama_aplikasi']);
    $subjudul               = trim($_POST['subjudul']);
    $tagline_1              = trim($_POST['tagline_1']);
    $tagline_2              = trim($_POST['tagline_2']);
    $versi_aplikasi         = trim($_POST['versi_aplikasi']);
    $developed_by           = trim($_POST['developed_by']);
    $nama_sekolah           = trim($_POST['nama_sekolah']);
    $alamat_sekolah         = trim($_POST['alamat_sekolah']);
    $telp_sekolah           = trim($_POST['telp_sekolah']);
    $whatsapp_admin         = trim($_POST['whatsapp_admin']);
    $email_sekolah          = trim($_POST['email_sekolah']);
    $nama_kepala_sekolah    = trim($_POST['nama_kepala_sekolah']);
    $nip_kepala_sekolah     = trim($_POST['nip_kepala_sekolah']);
    $format_nomor_transaksi   = trim($_POST['format_nomor_transaksi']);
    $jam_operasional          = trim($_POST['jam_operasional']);

    // Konversi Angka Desimal & Integer (Keuangan & Kebijakan)
    $minimal_penarikan        = (float)$_POST['minimal_penarikan'];
    $minimal_saldo_mengendap  = (float)$_POST['minimal_saldo_mengendap'];
    $biaya_admin_default      = (float)$_POST['biaya_admin_default'];
    $biaya_transfer_default   = (float)$_POST['biaya_transfer_default'];
    $limit_transfer_harian    = (float)$_POST['limit_transfer_harian'];
    $saldo_awal_default       = (float)$_POST['saldo_awal_default']; // 🌟 MENANGKAP INPUT BARU

    // Validasi Tanggal Potong (Aman untuk bulan Februari - rentang wajib 1 s.d 28)
    $tanggal_potong_admin     = (int)$_POST['tanggal_potong_admin'];
    if ($tanggal_potong_admin < 1 || $tanggal_potong_admin > 28) {
        $tanggal_potong_admin = 1; // Fallback jika di-bypass lewat inspect element
    }

    // 🔒 HANDLE UPLOAD LOGO SEKOLAH (ANTI-SHELL / ANTI-JUDOL)
    $logo_final = $setting['logo_sekolah']; // default pakai logo lama

    if (isset($_FILES['logo_sekolah']) && $_FILES['logo_sekolah']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['logo_sekolah']['tmp_name'];
        $file_name = $_FILES['logo_sekolah']['name'];
        $file_size = $_FILES['logo_sekolah']['size'];

        // 1. Validasi Ekstensi Nyata 
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'svg'];

        // 2. Validasi Ukuran File (Maksimal 2MB)
        $max_size = 2 * 1024 * 1024;

        if (!in_array($ext, $allowed_ext)) {
            $error_msg = "Format logo tidak valid! Hanya diperbolehkan JPG, PNG, atau SVG.";
        } elseif ($file_size > $max_size) {
            $error_msg = "Ukuran file terlalu besar! Maksimal adalah 2MB.";
        } else {
            // 3. Validasi Konten Asli File (MIME Type)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            $allowed_mime = ['image/jpeg', 'image/png', 'image/svg+xml'];

            if (!in_array($mime_type, $allowed_mime)) {
                $error_msg = "Konten berkas berbahaya terdeteksi! Upload dibatalkan.";
            } else {
                // 4. Tambahan Proteksi Khusus File Gambar
                if ($mime_type !== 'image/svg+xml' && @getimagesize($file_tmp) === false) {
                    $error_msg = "File gambar rusak atau palsu!";
                } else {
                    $new_name = "logo_sekolah_" . bin2hex(random_bytes(8)) . "_" . time() . "." . $ext;
                    $upload_dir = __DIR__ . '/../../../assets/uploads/';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                        if (!empty($setting['logo_sekolah']) && file_exists(__DIR__ . '/../../../' . $setting['logo_sekolah'])) {
                            @unlink(__DIR__ . '/../../../' . $setting['logo_sekolah']);
                        }
                        $logo_final = 'assets/uploads/' . $new_name;
                    } else {
                        $error_msg = "Gagal memindahkan file ke direktori server.";
                    }
                }
            }
        }
    }

    if (empty($error_msg)) {
        try {
            // 🌟 MENAMBAHKAN PARAMETER :saldo_awal_default KEDALAM KUERI UPDATE SQL
            $sql_update = "UPDATE tbl_pengaturan SET 
                nama_aplikasi = :nama_aplikasi, subjudul = :subjudul, tagline_1 = :tagline_1, tagline_2 = :tagline_2,
                versi_aplikasi = :versi_aplikasi, developed_by = :developed_by, nama_sekolah = :nama_sekolah,
                alamat_sekolah = :alamat_sekolah, telp_sekolah = :telp_sekolah, whatsapp_admin = :whatsapp_admin,
                email_sekolah = :email_sekolah, logo_sekolah = :logo_sekolah, nama_kepala_sekolah = :nama_kepala_sekolah,
                nip_kepala_sekolah = :nip_kepala_sekolah, minimal_penarikan = :minimal_penarikan,
                minimal_saldo_mengendap = :minimal_saldo_mengendap, biaya_admin_default = :biaya_admin_default,
                biaya_transfer_default = :biaya_transfer_default, format_nomor_transaksi = :format_nomor_transaksi,
                jam_operasional = :jam_operasional, limit_transfer_harian = :limit_transfer_harian,
                tanggal_potong_admin = :tanggal_potong_admin, saldo_awal_default = :saldo_awal_default
                WHERE id_pengaturan = :id";

            $stmt_up = $pdo->prepare($sql_update);
            $stmt_up->execute([
                'nama_aplikasi'           => $nama_aplikasi,
                'subjudul'                => $subjudul,
                'tagline_1'               => $tagline_1,
                'tagline_2'               => $tagline_2,
                'versi_aplikasi'          => $versi_aplikasi,
                'developed_by'            => $developed_by,
                'nama_sekolah'            => $nama_sekolah,
                'alamat_sekolah'          => $alamat_sekolah,
                'telp_sekolah'            => $telp_sekolah,
                'whatsapp_admin'          => $whatsapp_admin,
                'email_sekolah'           => $email_sekolah,
                'logo_sekolah'            => $logo_final,
                'nama_kepala_sekolah'     => $nama_kepala_sekolah,
                'nip_kepala_sekolah'      => $nip_kepala_sekolah,
                'minimal_penarikan'       => $minimal_penarikan,
                'minimal_saldo_mengendap' => $minimal_saldo_mengendap,
                'biaya_admin_default'     => $biaya_admin_default,
                'biaya_transfer_default'  => $biaya_transfer_default,
                'format_nomor_transaksi'  => $format_nomor_transaksi,
                'jam_operasional'         => $jam_operasional,
                'limit_transfer_harian'   => $limit_transfer_harian,
                'tanggal_potong_admin'    => $tanggal_potong_admin,
                'saldo_awal_default'      => $saldo_awal_default, // 🌟 BIND DATA VALUE BARU
                'id'                      => $setting['id_pengaturan']
            ]);

            $success_msg = "Konfigurasi parameter core perbankan berhasil diperbarui!";

            // Refresh data terbaru untuk ditampilkan kembali ke Form HTML
            $stmt = $pdo->query("SELECT * FROM tbl_pengaturan LIMIT 1");
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error_msg = "Gagal menyimpan perubahan database: " . $e->getMessage();
        }
    }
}
?>

<div class="w-full space-y-6 text-xs">

    <div class="w-full bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-500 border border-indigo-100/80 shadow-sm flex-shrink-0">
            <i class="fas fa-cogs text-base"></i>
        </div>
        <div class="space-y-0.5">
            <h1 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Konfigurasi & Parameter Sistem</h1>
            <p class="text-[11px] text-slate-400 font-medium leading-relaxed">
                Ubah identitas core aplikasi instansi, batasan limit saldo, dan skema biaya di sini.
            </p>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="w-full p-4 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 font-semibold flex items-center gap-3">
            <i class="fas fa-check-circle text-base"></i>
            <span><?= $success_msg ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="w-full p-4 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 font-semibold flex items-center gap-3">
            <i class="fas fa-exclamation-triangle text-base"></i>
            <span><?= $error_msg ?></span>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="w-full space-y-5">

        <div class="w-full bg-white border border-slate-100 rounded-2xl p-2 shadow-sm flex flex-wrap gap-1.5">
            <button type="button" onclick="switchSettingTab(this, 'panel-branding')" data-tab="branding"
                class="tab-btn active flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-indigo-50 text-indigo-600 border border-indigo-100 shadow-sm">
                <i class="fas fa-desktop text-xs"></i> Core Branding
            </button>
            <button type="button" onclick="switchSettingTab(this, 'panel-finansial')" data-tab="finansial"
                class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-transparent text-slate-500 border border-transparent hover:bg-slate-50 hover:text-slate-700">
                <i class="fas fa-wallet text-xs"></i> Parameter Finansial
            </button>
            <button type="button" onclick="switchSettingTab(this, 'panel-sekolah')" data-tab="sekolah"
                class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-transparent text-slate-500 border border-transparent hover:bg-slate-50 hover:text-slate-700">
                <i class="fas fa-school text-xs"></i> Profil Sekolah
            </button>
            <button type="button" onclick="switchSettingTab(this, 'panel-legitimasi')" data-tab="legitimasi"
                class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-transparent text-slate-500 border border-transparent hover:bg-slate-50 hover:text-slate-700">
                <i class="fas fa-user-tie text-xs"></i> Legitimasi Cetak
            </button>
        </div>

        <div class="w-full">

            <div id="panel-branding" class="tab-panel block animation-fade">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-4 bg-slate-50/70 border-b border-slate-100 flex items-center gap-2">
                        <i class="fas fa-desktop text-indigo-500 text-xs"></i>
                        <h2 class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">Core Branding & Meta Aplikasi</h2>
                    </div>

                    <div class="p-5 flex-1 space-y-4">
                        <div class="p-3 bg-indigo-50/50 border border-indigo-100/70 rounded-xl text-indigo-950 flex items-start gap-2.5 leading-relaxed text-[11px]">
                            <i class="fas fa-info-circle text-indigo-500 mt-0.5"></i>
                            <span>Atribut ini mengonfigurasi teks pembeda aplikasi, logo meta, serta lisensi kepemilikan yang terlihat di browser.</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600">Nama Aplikasi Utama</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-laptop-code absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="nama_aplikasi" value="<?= htmlspecialchars($setting['nama_aplikasi'] ?? '') ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 font-bold text-slate-700 transition-all">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium">Nama brand utama sistem perbankan yang muncul di title browser.</small>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600">Versi Engine</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-code-branch absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="versi_aplikasi" value="<?= htmlspecialchars($setting['versi_aplikasi'] ?? '') ?>"
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 font-semibold text-slate-600 transition-all">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium">Versi kode rilis sistem untuk mempermudah tracking pembaharuan.</small>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-bold text-slate-600">Subjudul Sistem</label>
                            <div class="relative flex items-center">
                                <i class="fas fa-heading absolute left-3.5 text-slate-400 text-xs"></i>
                                <input type="text" name="subjudul" value="<?= htmlspecialchars($setting['subjudul'] ?? '') ?>"
                                    class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 font-medium text-slate-600 transition-all">
                            </div>
                            <small class="text-[10px] text-slate-400 font-medium">Deskripsi pelengkap nama aplikasi yang biasanya tampil di halaman login utama.</small>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600">Tagline Banner 1</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-star absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="tagline_1" value="<?= htmlspecialchars($setting['tagline_1'] ?? '') ?>"
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 text-slate-600 transition-all">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium">Pesan motivasi atau pengumuman baris pertama pada welcome banner dashboard.</small>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600">Tagline Banner 2</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-quote-left absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="tagline_2" value="<?= htmlspecialchars($setting['tagline_2'] ?? '') ?>"
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 text-slate-600 transition-all">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium">Pesan motivasi pelengkap baris kedua pada welcome banner dashboard.</small>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-bold text-slate-600">Hak Cipta / Developed By</label>
                            <div class="relative flex items-center">
                                <i class="fas fa-copyright absolute left-3.5 text-slate-400 text-xs"></i>
                                <input type="text" name="developed_by" value="<?= htmlspecialchars($setting['developed_by'] ?? '') ?>" required
                                    class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 text-slate-600 transition-all">
                            </div>
                            <small class="text-[10px] text-slate-400 font-medium">Nama vendor/developer pengembang yang akan dicantumkan pada bagian footer bawah.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div id="panel-finansial" class="tab-panel hidden animation-fade">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-4 bg-slate-50/70 border-b border-slate-100 flex items-center gap-2">
                        <i class="fas fa-wallet text-emerald-500 text-xs"></i>
                        <h2 class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">Parameter Finansial & Kebijakan Kas</h2>
                    </div>

                    <div class="p-5 flex-1 space-y-5">
                        <div class="p-3 bg-emerald-50/60 border border-emerald-100/70 rounded-xl text-emerald-950 flex items-start gap-2.5 leading-relaxed text-[11px]">
                            <i class="fas fa-exclamation-circle text-emerald-500 mt-0.5"></i>
                            <span><span class="font-bold">PENTING:</span> Aturan kalkulasi pembatasan transaksi finansial dan potongan wajib kas default bank mini instansi.</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-5 gap-y-5">

                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Minimal Tarik Tunai (Rp)</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-money-bill-wave absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="number" step="0.01" name="minimal_penarikan" value="<?= (float)$setting['minimal_penarikan'] ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono font-bold text-slate-700 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Batas minimal penarikan kas di loket.</small>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Minimal Saldo Mengendap (Rp)</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-lock absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="number" step="0.01" name="minimal_saldo_mengendap" value="<?= (float)$setting['minimal_saldo_mengendap'] ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono font-bold text-slate-700 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Saldo minimum wajib tersisa di rekening.</small>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Limit Transfer Harian (Rp)</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-sliders-h absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="number" step="0.01" name="limit_transfer_harian" value="<?= (float)$setting['limit_transfer_harian'] ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono text-slate-600 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Batas akumulasi transfer harian siswa.</small>
                            </div>


                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Saldo Awal Default (Rp)</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-coins absolute left-3.5 text-emerald-500 text-xs"></i>
                                    <input type="number" step="0.01" name="saldo_awal_default" value="<?= (float)($setting['saldo_awal_default'] ?? 25000.00) ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-emerald-50/30 border border-emerald-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono font-bold text-slate-700 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Hadiah/modal saldo awal registrasi.</small>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Biaya Admin Bulanan (Rp)</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-calculator absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="number" step="0.01" name="biaya_admin_default" value="<?= (float)$setting['biaya_admin_default'] ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono text-slate-600 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Tarif potongan saldo bulanan nasabah.</small>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Biaya per Transfer (Rp)</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-exchange-alt absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="number" step="0.01" name="biaya_transfer_default" value="<?= (float)$setting['biaya_transfer_default'] ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono text-slate-600 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Tarif admin transfer sesama rekening.</small>
                            </div>


                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Tanggal Potong Bulanan</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-calendar-day absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="number" min="1" max="28" name="tanggal_potong_admin" value="<?= (int)($setting['tanggal_potong_admin'] ?? 1) ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono font-bold text-emerald-600 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Siklus autodebet bulanan (1-28).</small>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Format Serial Transaksi</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-barcode absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="format_nomor_transaksi" value="<?= htmlspecialchars($setting['format_nomor_transaksi'] ?? '') ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-mono font-bold text-slate-600 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Prefix awalan kuitansi otomatis sistem.</small>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600 text-[11px]">Jam Operasional Pelayanan</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-clock absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="jam_operasional" value="<?= htmlspecialchars($setting['jam_operasional'] ?? '') ?>" required
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 font-semibold text-slate-600 transition-all text-sm">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium leading-normal">Keterangan jam pelayanan kas aktif.</small>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div id="panel-sekolah" class="tab-panel hidden animation-fade">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-4 bg-slate-50/70 border-b border-slate-100 flex items-center gap-2">
                        <i class="fas fa-school text-amber-500 text-xs"></i>
                        <h2 class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">Profil Instansi Lembaga / Sekolah</h2>
                    </div>

                    <div class="p-5 flex-1 space-y-4">
                        <div class="p-3 bg-amber-50/50 border border-amber-100/70 rounded-xl text-amber-950 flex items-start gap-2.5 leading-relaxed text-[11px]">
                            <i class="fas fa-graduation-cap text-amber-500 mt-0.5"></i>
                            <span>Atribut profil di bawah ini berfungsi sebagai identitas kop surat, data kontak, dan pencantuman nama sekolah terdaftar di slip nota.</span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="font-bold text-slate-600">Nama Sekolah Oficial</label>
                            <div class="relative flex items-center">
                                <i class="fas fa-school absolute left-3.5 text-slate-400 text-xs"></i>
                                <input type="text" name="nama_sekolah" value="<?= htmlspecialchars($setting['nama_sekolah'] ?? '') ?>"
                                    class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100 font-bold text-slate-700 transition-all">
                            </div>
                            <small class="text-[10px] text-slate-400 font-medium">Nama lembaga sekolah/madrasah resmi pengelola keuangan bank mini.</small>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-bold text-slate-600">Alamat Lengkap Sekolah</label>
                            <div class="relative flex items-start">
                                <i class="fas fa-map-marker-alt absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                                <textarea name="alamat_sekolah" rows="2" class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100 text-slate-600 transition-all"><?= htmlspecialchars($setting['alamat_sekolah'] ?? '') ?></textarea>
                            </div>
                            <small class="text-[10px] text-slate-400 font-medium">Alamat fisik lengkap instansi (Nama jalan, nomor, RT/RW, kecamatan, kabupaten/kota).</small>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600">No. Telepon</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-phone absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="telp_sekolah" value="<?= htmlspecialchars($setting['telp_sekolah'] ?? '') ?>"
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100 text-slate-600 transition-all">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium">Nomor telepon kantor/telkom aktif instansi.</small>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600">WhatsApp Admin</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-comments absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="text" name="whatsapp_admin" value="<?= htmlspecialchars($setting['whatsapp_admin'] ?? '') ?>"
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100 font-semibold text-slate-600 transition-all">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium">Nomor kontak WhatsApp admin pengelola pengaduan nasabah.</small>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-bold text-slate-600">Email Instansi</label>
                                <div class="relative flex items-center">
                                    <i class="fas fa-envelope absolute left-3.5 text-slate-400 text-xs"></i>
                                    <input type="email" name="email_sekolah" value="<?= htmlspecialchars($setting['email_sekolah'] ?? '') ?>"
                                        class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-amber-100 text-slate-600 transition-all">
                                </div>
                                <small class="text-[10px] text-slate-400 font-medium">Surat elektronik resmi lembaga korespondensi digital bank.</small>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 border border-slate-200/60 rounded-xl flex flex-col sm:flex-row items-center gap-4">
                            <div class="w-14 h-14 bg-white border border-slate-200 rounded-xl flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm">
                                <?php if (!empty($setting['logo_sekolah'])): ?>
                                    <img src="<?= '../../' . $setting['logo_sekolah'] ?>" class="w-full h-full object-contain">
                                <?php else: ?>
                                    <i class="fas fa-image text-slate-300 text-xl"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-col gap-1 w-full">
                                <label class="font-bold text-slate-600">Ganti Atribut Logo Sekolah</label>
                                <input type="file" name="logo_sekolah" class="text-[11px] file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200 text-slate-400">
                                <small class="text-[9px] text-slate-400">File valid: PNG, JPG, JPEG, SVG. Ukuran disarankan berbentuk persegi (1:1).</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="panel-legitimasi" class="tab-panel hidden animation-fade">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="p-4 bg-slate-50/70 border-b border-slate-100 flex items-center gap-2">
                        <i class="fas fa-user-tie text-blue-500 text-xs"></i>
                        <h2 class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">Legitimasi Kepala Sekolah & Laporan</h2>
                    </div>

                    <div class="p-5 flex-1 space-y-4">
                        <div class="p-3.5 bg-blue-50/60 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-950 leading-relaxed text-[11px]">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            <div>
                                <span class="font-bold">Informasi Dokumen Legitimasi:</span> Nama dan NIP di bawah ini akan otomatis disematkan secara fisik pada dokumen ekspor seperti **Buku Tabungan, Slip Penarikan/Penyetoran, serta Laporan Keuangan Bulanan**.
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-bold text-slate-600">Nama Kepala Sekolah (Lengkap beserta Gelar)</label>
                            <div class="relative flex items-center">
                                <i class="fas fa-user-tie absolute left-3.5 text-slate-400 text-xs"></i>
                                <input type="text" name="nama_kepala_sekolah" value="<?= htmlspecialchars($setting['nama_kepala_sekolah'] ?? '') ?>"
                                    class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100 font-semibold text-slate-700 transition-all">
                            </div>
                            <small class="text-[10px] text-slate-400 font-medium">Tulis nama pejabat kepala sekolah aktif yang bertanggung jawab atas laporan fisik.</small>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-bold text-slate-600">Nomor Induk Pegawai (NIP)</label>
                            <div class="relative flex items-center">
                                <i class="fas fa-id-card absolute left-3.5 text-slate-400 text-xs"></i>
                                <input type="text" name="nip_kepala_sekolah" value="<?= htmlspecialchars($setting['nip_kepala_sekolah'] ?? '') ?>"
                                    class="w-full pl-9 pr-3 py-2 rounded-xl bg-slate-50/50 border border-slate-200 focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100 font-mono text-slate-600 transition-all">
                            </div>
                            <small class="text-[10px] text-slate-400 font-medium">Gunakan nomor induk kepegawaian standar tanpa spasi berlebih (Contoh: 19820311...).</small>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="w-full bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center justify-between gap-3">
            <div class="hidden sm:flex items-center gap-2 text-slate-400 font-medium">
                <i class="fas fa-info-circle text-xs"></i>
                <span>Perubahan tab tidak akan menghapus data yang sudah Anda ketik di tab lain.</span>
            </div>
            <div class="flex items-center gap-2 ml-auto w-full sm:w-auto justify-end">
                <button type="reset" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-[11px] font-bold flex items-center gap-1.5">
                    <i class="fas fa-undo text-[10px]"></i> Reset Form
                </button>
                <button type="submit" id="submit-all-btn" class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-all text-[11px] font-bold shadow-md shadow-indigo-100 flex items-center justify-center gap-2">
                    <i class="fas fa-save text-xs"></i> Simpan Seluruh Konfigurasi Sistem
                </button>
            </div>
        </div>

    </form>
</div>

<script>
    function switchSettingTab(button, panelId) {
        // 1. Sembunyikan seluruh panel konten tab
        const panels = document.querySelectorAll('.tab-panel');
        panels.forEach(panel => {
            panel.classList.remove('block');
            panel.classList.add('hidden');
        });

        // 2. Tampilkan panel target yang dipilih
        const targetPanel = document.getElementById(panelId);
        if (targetPanel) {
            targetPanel.classList.remove('hidden');
            targetPanel.classList.add('block');
        }

        // 3. Kembalikan semua style button tab ke posisi tidak aktif (slate-tinted)
        const buttons = document.querySelectorAll('.tab-btn');
        buttons.forEach(btn => {
            btn.className = "tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-transparent text-slate-500 border border-transparent hover:bg-slate-50 hover:text-slate-700";
        });

        // 4. Setel style tombol aktif dan sinkronkan tema warna tombol simpan global
        const tabName = button.getAttribute('data-tab');
        const submitBtn = document.getElementById('submit-all-btn');

        if (tabName === 'branding') {
            button.className = "tab-btn active flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-indigo-50 text-indigo-600 border border-indigo-100 shadow-sm";
            submitBtn.className = "px-6 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-all text-[11px] font-bold shadow-md shadow-indigo-100 flex items-center justify-center gap-2";
        } else if (tabName === 'finansial') {
            button.className = "tab-btn active flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-emerald-50 text-emerald-600 border border-emerald-100 shadow-sm";
            submitBtn.className = "px-6 py-2.5 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 transition-all text-[11px] font-bold shadow-md shadow-emerald-100 flex items-center justify-center gap-2";
        } else if (tabName === 'sekolah') {
            button.className = "tab-btn active flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-amber-50 text-amber-700 border border-amber-100 shadow-sm";
            submitBtn.className = "px-6 py-2.5 rounded-xl bg-amber-600 text-white hover:bg-amber-700 transition-all text-[11px] font-bold shadow-md shadow-amber-100 flex items-center justify-center gap-2";
        } else if (tabName === 'legitimasi') {
            button.className = "tab-btn active flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold transition-all text-[11px] bg-blue-50 text-blue-600 border border-blue-100 shadow-sm";
            submitBtn.className = "px-6 py-2.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition-all text-[11px] font-bold shadow-md shadow-blue-100 flex items-center justify-center gap-2";
        }
    }
</script>

<style>
    .animation-fade {
        animation: fadeInTab 0.2s ease-in-out forwards;
    }

    @keyframes fadeInTab {
        from {
            opacity: 0;
            transform: translateY(3px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>