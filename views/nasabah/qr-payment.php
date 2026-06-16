<?php

/**
 * Aplikasi Mini Bank Sekolah - Modul Transfer All-in-One (Murni QR Code)
 * Deskripsi: Fitur transfer mandiri berbasis SCAN QR CODE (Tanpa Input Manual)
 * Fitur Fix: Bebas kedap-kedip kamera, Auto-routing AJAX, Clean Layout.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// [BAGIAN 1] LOGIKA INTERCEPTOR AJAX (Pengecekan NISN Otomatis via Kamera)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'cek_nisn') {
    // Bersihkan buffer agar output JSON tidak terkotori oleh sisa HTML template luar
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $nisn = isset($_GET['nisn']) ? trim($_GET['nisn']) : '';

    if (empty($nisn)) {
        echo json_encode(['success' => false, 'message' => 'NISN tidak terbaca atau kosong.']);
        exit;
    }

    try {
        // Otomatis mencari variabel koneksi PDO Anda yang aktif
        $db_pdo = null;
        if (isset($pdo)) {
            $db_pdo = $pdo;
        } elseif (isset($conn)) {
            $db_pdo = $conn;
        } elseif (isset($koneksi)) {
            $db_pdo = $koneksi;
        } elseif (isset($db)) {
            $db_pdo = $db;
        }

        if (!$db_pdo) {
            throw new PDOException("Variabel koneksi database (PDO) tidak terdeteksi di halaman utama.");
        }

        $stmt = $db_pdo->prepare("SELECT nama_nasabah, status_nasabah FROM tbl_nasabah WHERE TRIM(nisn) = ? LIMIT 1");
        $stmt->execute([$nisn]);
        $nasabah = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($nasabah) {
            if ($nasabah['status_nasabah'] !== 'aktif') {
                echo json_encode(['success' => false, 'message' => 'Akun nasabah tujuan sudah tidak aktif.']);
            } else {
                echo json_encode(['success' => true, 'nama_nasabah' => $nasabah['nama_nasabah']]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'NISN tidak terdaftar di sistem bank sekolah.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    exit; // Menghentikan eksekusi murni agar respon berupa JSON bersih
}

// =========================================================================
// [BAGIAN 2] LOGIKA BACKEND HALAMAN UTAMA (Memuat Profil Personal User)
// =========================================================================
$id_nasabah = $_SESSION['id_nasabah'] ?? null;
$status_notif = $_GET['status'] ?? null;
$msg_notif = $_GET['msg'] ?? null;

$my_nisn = '';
$my_nama = '';
$my_kelas = '';

try {
    $db_pdo = $pdo ?? $conn ?? $koneksi ?? $db ?? null;
    if ($id_nasabah && $db_pdo) {
        $stmt_me = $db_pdo->prepare("SELECT nisn, nama_nasabah, kelas FROM tbl_nasabah WHERE id_nasabah = ? LIMIT 1");
        $stmt_me->execute([$id_nasabah]);
        if ($me = $stmt_me->fetch()) {
            $my_nisn = $me['nisn'];
            $my_nama = $me['nama_nasabah'];
            $my_kelas = $me['kelas'];
        }
    }
} catch (PDOException $e) {
    error_log("Gagal memuat data personal QR: " . $e->getMessage());
}
?>

<div id="section-qr-payment" class="w-full max-w-4xl mx-auto p-2 sm:p-4 animate-fade-in">
    <div class="bg-white rounded-3xl border border-slate-100 shadow-xl shadow-slate-100/70 p-5 sm:p-8 transition-all duration-300">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center pb-6 border-b border-slate-100 mb-6 gap-5">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20 shrink-0">
                    <i id="header-icon" class="fas fa-id-card text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h3 id="header-title" class="font-black text-slate-800 text-base sm:text-lg tracking-tight">
                        Kode QR Saya
                    </h3>
                    <p id="header-desc" class="text-xs text-slate-400 font-medium mt-0.5 leading-relaxed">
                        Halaman kartu identitas digital personal Anda untuk menerima transfer saldo instan.
                    </p>
                </div>
            </div>

            <div class="bg-slate-100/80 p-1.5 rounded-2xl shrink-0 w-full md:w-auto flex items-center border border-slate-200/40 backdrop-blur-sm">
                <button id="btn-nav-show" onclick="switchQrTab('show')" class="bg-white text-blue-600 font-black text-xs px-5 py-2.5 rounded-xl shadow-md shadow-slate-200/80 flex items-center justify-center gap-2 flex-1 md:flex-none transition-all duration-300 cursor-pointer">
                    <i class="fas fa-id-card text-xs"></i> QR Saya
                </button>
                <button id="btn-nav-scan" onclick="switchQrTab('scan')" class="text-slate-500 font-bold text-xs px-5 py-2.5 rounded-xl flex items-center justify-center gap-2 flex-1 md:flex-none transition-all duration-300 cursor-pointer hover:text-slate-800">
                    <i class="fas fa-camera text-xs"></i> Pindai QR
                </button>
            </div>
        </div>

        <?php if ($status_notif && $msg_notif): ?>
            <div class="mb-6 p-4 rounded-2xl text-xs font-bold flex items-center gap-3.5 <?= $status_notif === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200/60' : 'bg-rose-50 text-rose-800 border border-rose-200/60' ?>">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 <?= $status_notif === 'success' ? 'bg-emerald-500/10' : 'bg-rose-500/10' ?>">
                    <i class="fas <?= $status_notif === 'success' ? 'fa-check-circle text-emerald-600' : 'fa-exclamation-circle text-rose-600' ?> text-lg"></i>
                </div>
                <span class="leading-relaxed"><?= htmlspecialchars($msg_notif) ?></span>
            </div>
        <?php endif; ?>

        <div id="wrapper-my-qr" class="w-full max-w-sm mx-auto flex flex-col items-center justify-center py-2 block">
            <div class="w-full bg-gradient-to-b from-slate-50 via-white to-slate-50/50 p-6 sm:p-8 rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/30 relative overflow-hidden flex flex-col items-center text-center">
                <span class="inline-flex items-center gap-1.5 text-[10px] font-extrabold tracking-widest text-indigo-600 bg-indigo-50 border border-indigo-100 px-3 py-1 rounded-full uppercase mb-6">
                    <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-ping"></span> E-Wallet Card ID
                </span>

                <div class="bg-white p-4 rounded-3xl shadow-xl border border-slate-100">
                    <?php if (!empty($my_nisn)): ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($my_nisn) ?>" alt="QR Code Saya" class="w-44 h-44 sm:w-48 sm:h-48 object-contain block mx-auto rounded-xl">
                    <?php else: ?>
                        <div class="w-44 h-44 flex flex-col items-center justify-center text-slate-400 gap-2">
                            <i class="fas fa-unlink text-2xl text-amber-500"></i> <span class="text-[11px] font-bold">QR Gagal Dimuat</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 w-full space-y-2">
                    <h4 class="font-black text-slate-800 text-sm sm:text-base uppercase tracking-wide truncate"><?= htmlspecialchars($my_nama ?: 'Nama Belum Terdata') ?></h4>
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-[10px] font-mono font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200/60">NISN: <?= htmlspecialchars($my_nisn ?: '-') ?></span>
                        <span class="text-[10px] font-extrabold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-100">Kelas <?= htmlspecialchars($my_kelas ?: '-') ?></span>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 font-semibold mt-5 max-w-[280px] leading-relaxed">Tunjukkan QR ini ke rekan Anda untuk menerima setoran saldo tanpa input manual.</p>
            </div>
        </div>

        <div id="wrapper-camera-scan" class="w-full max-w-sm mx-auto flex flex-col items-center justify-center py-2 hidden">
            <div class="relative w-full aspect-square rounded-3xl bg-slate-950 overflow-hidden border border-slate-900 shadow-2xl p-2">
                <div id="camera-reader" class="w-full h-full rounded-2xl overflow-hidden"></div>

                <div class="absolute top-5 left-5 w-8 h-8 border-t-4 border-l-4 border-blue-500 rounded-tl-xl pointer-events-none z-10"></div>
                <div class="absolute top-5 right-5 w-8 h-8 border-t-4 border-r-4 border-blue-500 rounded-tr-xl pointer-events-none z-10"></div>
                <div class="absolute bottom-5 left-5 w-8 h-8 border-b-4 border-l-4 border-blue-500 rounded-bl-xl pointer-events-none z-10"></div>
                <div class="absolute bottom-5 right-5 w-8 h-8 border-b-4 border-r-4 border-blue-500 rounded-br-xl pointer-events-none z-10"></div>
                <div class="absolute inset-x-0 top-0 h-0.5 bg-cyan-400 shadow-[0_0_12px_#22d3ee] opacity-90 pointer-events-none z-10" style="animation: laserMove 2.5s infinite linear;"></div>
                <div class="absolute inset-0 border-[28px] border-black/50 rounded-2xl pointer-events-none"></div>
            </div>
            <span class="mt-4 text-[10px] font-black tracking-wider text-slate-500 bg-slate-100 px-3.5 py-1.5 rounded-full border border-slate-200/50 flex items-center gap-2">
                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span> MENUNGGU QR CODE TERDETEKSI
            </span>
        </div>

        <div id="wrapper-form-transfer" class="hidden w-full max-w-md mx-auto space-y-5 border border-slate-100 p-5 sm:p-6 rounded-3xl bg-slate-50/70 shadow-inner">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-4 rounded-2xl flex items-center gap-3.5 text-white shadow-md">
                <div class="w-10 h-10 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center shrink-0">
                    <i class="fas fa-user-check text-sm text-white"></i>
                </div>
                <div class="min-w-0">
                    <span class="text-[8px] font-black uppercase tracking-widest bg-white/20 px-2 py-0.5 rounded-md">Penerima Sah</span>
                    <h4 id="txt_nama_penerima" class="text-xs sm:text-sm font-black truncate mt-0.5">-</h4>
                    <p id="txt_nisn_penerima" class="text-[10px] font-mono text-white/75 mt-0.5">-</p>
                </div>
            </div>

            <form action="proses-transfer-qr.php" method="POST" class="space-y-4 text-xs font-bold text-slate-700">
                <input type="hidden" id="hidden_nisn_penerima" name="nisn_penerima">

                <div class="flex flex-col gap-1.5 text-center">
                    <label class="font-extrabold text-slate-600">Jumlah Uang Dikirim</label>
                    <div class="relative flex items-center justify-center max-w-xs mx-auto w-full">
                        <span class="absolute left-4 font-black text-slate-400 text-lg">Rp</span>
                        <input type="number" name="jumlah" required placeholder="0" min="1000" class="w-full pl-12 pr-4 py-3.5 rounded-xl border border-slate-200 text-center font-black text-slate-800 text-xl focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 outline-none bg-white transition-all">
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-extrabold text-slate-600">Catatan / Pesan Pembayaran</label>
                    <input type="text" name="keterangan" placeholder="Contoh: Bayar uang kas kelompok" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-700 font-medium bg-white focus:border-blue-500 outline-none transition-all">
                </div>

                <div class="flex flex-col gap-1.5 pt-1 border-t border-slate-200/60 mt-2">
                    <label class="font-extrabold text-slate-600 flex items-center gap-1.5">
                        <i class="fas fa-shield-alt text-rose-500"></i> PIN Keamanan Transaksi (6 Angka)
                    </label>
                    <input type="password" name="pin_transaksi" required maxlength="6" pattern="\d{6}" inputmode="numeric" placeholder="••••••" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-center font-mono text-xl tracking-[0.8em] font-black text-slate-800 bg-white focus:border-blue-500 outline-none transition-all">
                </div>

                <div class="pt-3 flex gap-3">
                    <button type="button" onclick="window.location.reload();" class="w-1/3 py-3.5 bg-slate-200/80 hover:bg-slate-200 text-slate-600 font-black rounded-xl transition-all cursor-pointer">BATAL</button>
                    <button type="submit" class="w-2/3 py-3.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black rounded-xl shadow-lg hover:opacity-95 transition-all cursor-pointer">KIRIM SALDO SEKARANG</button>
                </div>
            </form>
        </div>

    </div>
</div>

<style>
    @keyframes laserMove {
        0% {
            top: 0%;
        }

        50% {
            top: 100%;
        }

        100% {
            top: 0%;
        }
    }

    /* Mengunci dimensi box container kamera agar browser tidak merubah rasio di tengah jalan */
    #camera-reader {
        border: none !important;
        width: 100% !important;
        height: 100% !important;
        position: relative;
        background-color: #020617;
    }

    #camera-reader video {
        object-fit: cover !important;
        width: 100% !important;
        height: 100% !important;
        display: block;
    }
</style>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    let html5QrcodeEngine = null;
    let isCameraSettingUp = false; // LOCK FLAG UTAMA: Mengunci double-execution asinkron penyebab kedap-kedip

    function switchQrTab(tab) {
        const btnScan = document.getElementById('btn-nav-scan');
        const btnShow = document.getElementById('btn-nav-show');
        const wrapScan = document.getElementById('wrapper-camera-scan');
        const wrapForm = document.getElementById('wrapper-form-transfer');
        const wrapMyQr = document.getElementById('wrapper-my-qr');

        const headerIcon = document.getElementById('header-icon');
        const headerTitle = document.getElementById('header-title');
        const headerDesc = document.getElementById('header-desc');

        const activeClasses = ['bg-white', 'text-blue-600', 'font-black', 'shadow-md'];
        const inactiveClasses = ['text-slate-500', 'font-bold', 'hover:text-slate-800'];

        if (tab === 'scan') {
            btnScan.classList.add(...activeClasses);
            btnScan.classList.remove(...inactiveClasses);
            btnShow.classList.remove(...activeClasses);
            btnShow.classList.add(...inactiveClasses);

            headerIcon.className = "fas fa-camera text-lg text-white";
            headerTitle.innerText = "Pindai QR Penerima";
            headerDesc.innerText = "Arahkan kamera ke QR Code NISN rekan Anda untuk mengirim saldo instan secara aman.";

            wrapMyQr.classList.add('hidden');
            if (document.getElementById('hidden_nisn_penerima').value !== "") {
                wrapForm.classList.remove('hidden');
                wrapScan.classList.add('hidden');
            } else {
                wrapScan.classList.remove('hidden');
                initKameraScanner(); // Auto-start pemicu streaming kamera aktif
            }
        } else if (tab === 'show') {
            btnShow.classList.add(...activeClasses);
            btnShow.classList.remove(...inactiveClasses);
            btnScan.classList.remove(...activeClasses);
            btnScan.classList.add(...inactiveClasses);

            headerIcon.className = "fas fa-id-card text-lg text-white";
            headerTitle.innerText = "Kode QR Saya";
            headerDesc.innerText = "Halaman kartu identitas digital personal Anda untuk menerima transfer saldo instan.";

            wrapScan.classList.add('hidden');
            wrapForm.classList.add('hidden');
            wrapMyQr.classList.remove('hidden');

            matikanKameraEngine();
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        matikanKameraEngine(); // Matikan kamera sesaat setelah data didapat agar resource hemat

        // AMANKAN PARAMETER: Membaca URL aktif & menyisipkan action AJAX tanpa merusak token page (?page=...)
        const urlTarget = new URL(window.location.href);
        urlTarget.searchParams.set('action', 'cek_nisn');
        urlTarget.searchParams.set('nisn', decodedText.trim());

        fetch(urlTarget.toString())
            .then(response => {
                if (!response.ok) throw new Error("Respon server bermasalah.");
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Masukkan data hasil scan ke struktur formulir nominal
                    document.getElementById('hidden_nisn_penerima').value = decodedText;
                    document.getElementById('txt_nama_penerima').innerText = data.nama_nasabah;
                    document.getElementById('txt_nisn_penerima').innerText = "NISN: " + decodedText;

                    // Tukar jendela visual dari kamera ke form nominal transfer
                    document.getElementById('wrapper-camera-scan').classList.add('hidden');
                    document.getElementById('wrapper-form-transfer').classList.remove('hidden');
                } else {
                    alert(data.message || "Identitas QR tidak dikenali.");
                    initKameraScanner(); // Hidupkan ulang kamera jika data ditolak DB
                }
            })
            .catch(err => {
                alert("Gagal membaca struktur database. Pastikan format endpoint mengembalikan JSON.");
                initKameraScanner();
            });
    }

    function initKameraScanner() {
        // Jika sedang proses booting up kamera, batalkan trigger berikutnya untuk memotong loop kedap-kedip
        if (isCameraSettingUp) return;

        if (!html5QrcodeEngine) {
            html5QrcodeEngine = new Html5Qrcode("camera-reader");
        }

        // Mulai streaming hanya jika status mesin benar-benar sedang mati
        if (!html5QrcodeEngine.isScanning) {
            isCameraSettingUp = true; // Pasang kunci pengaman

            html5QrcodeEngine.start({
                    facingMode: "environment"
                }, {
                    fps: 20, // Diatur stabil di 20 FPS agar chipset handphone tidak panas & render frame mulus
                    qrbox: {
                        width: 250,
                        height: 250
                    }
                },
                onScanSuccess,
                (errorMessage) => {
                    /* Callback scanning continuous sengaja dikosongkan agar memori stabil */ }
            ).then(() => {
                isCameraSettingUp = false; // Buka kunci pengaman setelah video berhasil tampil
            }).catch(err => {
                isCameraSettingUp = false; // Buka kunci jika akses ditolak agar bisa dicoba lagi nanti
                console.log("Gagal menyalakan komponen kamera:", err);
            });
        }
    }

    function matikanKameraEngine() {
        isCameraSettingUp = false;
        if (html5QrcodeEngine && html5QrcodeEngine.isScanning) {
            html5QrcodeEngine.stop().catch(err => console.log("Gagal menghentikan kamera:", err));
        }
    }
</script>