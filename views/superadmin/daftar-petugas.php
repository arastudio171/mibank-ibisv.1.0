<?php

/**
 * FILE: views/superadmin/operator/operator.php
 * DESKRIPSI: Manajemen Data Operator dengan UI Premium & Fitur Interaktif (Search + Modal Detail)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// VALIDASI AKSES: Menggunakan session asli Anda ('admin')
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>window.location.href = 'auth/auth-login.php?msg=Akses ditolak! Khusus Admin.&type=error';</script>";
    exit();
}

// ID Role Operator di database Anda (Sesuai set sebelumnya yaitu ID: 2)
$id_role_operator = 2;
$operators = [];

try {
    $sql = "SELECT * FROM tbl_users WHERE id_role = :id_role ORDER BY id_user DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_role' => $id_role_operator]);
    $operators = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Operator Error: " . $e->getMessage());
}
?>

<div class="space-y-6 w-full">
    <?php if (isset($_GET['msg']) && isset($_GET['type'])): ?>
        <div class="p-4 rounded-xl text-xs font-semibold flex items-start gap-3 <?= $_GET['type'] === 'success' ? 'bg-emerald-50 border border-emerald-100 text-emerald-600' : 'bg-rose-50 border border-rose-100 text-rose-600' ?>">
            <i class="fas <?= $_GET['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> text-sm mt-0.5"></i>
            <div><?= htmlspecialchars($_GET['msg']) ?></div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="w-full md:w-auto">
                <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                    <i class="fas fa-database text-[#1257aa]"></i> Manajemen Data Operator
                </h3>
                <p class="text-[10px] text-slate-400 mt-0.5">Kelola data petugas, hak akses login, status operasional, keamanan password, dan log aktivitas.</p>
            </div>

            <div class="w-full md:w-auto flex flex-wrap items-center gap-3 justify-end">
                <div class="relative w-full sm:w-auto">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-[10px]"></i>
                    </span>
                    <input type="text" id="inputCariOperator" placeholder="Cari nama atau username..."
                        class="pl-8 pr-3 py-2 w-full sm:w-56 text-[10px] bg-white border border-slate-200 rounded-lg text-slate-600 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-bold">
                </div>

                <a href="?page=tambah-petugas" class="text-[10px] font-black bg-[#1257aa] text-white px-3.5 py-2 rounded-lg transition-all shadow-sm flex items-center gap-1.5 tracking-wider uppercase">
                    <i class="fas fa-user-plus text-xs"></i> Tambah Operator
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="tabelOperator">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 text-center w-12">No.</th>
                        <th class="p-4">Identitas Petugas</th>
                        <th class="p-4">Otoritas Sistem</th>
                        <th class="p-4">Waktu Pendaftaran</th>
                        <th class="p-4 text-center">Status Akun</th>
                        <th class="p-4 text-center">Kontrol Akses</th>
                        <th class="p-4 text-center">Manajemen Data</th>
                        <th class="p-4 text-center">Keamanan Akun</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (empty($operators)): ?>
                        <tr id="rowKosong">
                            <td colspan="8" class="p-8 text-center text-slate-400 font-medium">
                                <i class="fas fa-users-slash block text-xl mb-2 text-slate-300"></i>
                                Tidak ada data operator terdaftar dengan ID Role ini.
                            </td>
                        </tr>
                        <?php else: $no = 1;
                        foreach ($operators as $item): ?>
                            <tr class="row-operator hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 text-center font-bold text-slate-400 target-no"><?= $no++ ?></td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2 flex-wrap">
                                        <i class="fas <?= $item['status_akun'] === 'aktif' ? 'fa-lock-open text-emerald-500' : 'fa-lock text-rose-400' ?> text-sm"></i>
                                        <span class="target-nama"><?= htmlspecialchars($item['nama_lengkap']) ?></span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-1">
                                        <i class="fas fa-user text-indigo-500 mr-1"></i> Username: @<span class="target-user"><?= htmlspecialchars($item['username']) ?></span>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="flex items-center gap-2 text-slate-700 font-bold mb-1">
                                        <i class="fas fa-user-shield text-blue-500 text-[10px]"></i> <span>Petugas Internal</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium">Akses Operasional Bank</div>
                                </td>

                                <td class="p-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <i class="far fa-calendar-alt text-slate-400"></i>
                                        <?= date('d M Y', strtotime($item['created_at'])) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-1">
                                        <i class="fas fa-clock text-slate-400 mr-1"></i> Jam: <?= date('H:i:s', strtotime($item['created_at'])) ?> WIB
                                    </div>
                                </td>

                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase inline-flex items-center <?= $item['status_akun'] === 'aktif' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-rose-50 text-rose-600 border border-rose-100' ?>">
                                        <i class="fas <?= $item['status_akun'] === 'aktif' ? 'fa-check-circle mr-1' : 'fa-times-circle mr-1' ?>"></i>
                                        <?= $item['status_akun'] ?>
                                    </span>
                                </td>

                                <td class="p-4 text-center">
                                    <a href="modules/petugas/toggle-petugas.php?id=<?= $item['id_user'] ?>"
                                        onclick="return confirm('Apakah Anda yakin ingin mengubah status operasional petugas ini?')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full transition-colors font-bold text-[10px] <?= $item['status_akun'] === 'aktif' ? 'bg-emerald-100 text-emerald-700 hover:bg-rose-100 hover:text-rose-700' : 'bg-rose-100 text-rose-700 hover:bg-emerald-100 hover:text-emerald-700' ?>">
                                        <i class="fas <?= $item['status_akun'] === 'aktif' ? 'fa-toggle-on' : 'fa-toggle-off' ?> text-xs"></i>
                                        <?= $item['status_akun'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?>
                                    </a>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center gap-1 p-1 rounded-xl">
                                        <button type="button"
                                            class="btn-detail-operator w-7 h-7 flex items-center justify-center rounded-lg bg-white text-emerald-600 hover:bg-emerald-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Buka Log Detail"
                                            data-id="<?= $item['id_user'] ?>"
                                            data-nama="<?= htmlspecialchars($item['nama_lengkap']) ?>"
                                            data-user="<?= htmlspecialchars($item['username']) ?>"
                                            data-status="<?= $item['status_akun'] ?>"
                                            data-login="<?= $item['last_login'] ? date('d M Y H:i:s', strtotime($item['last_login'])) . ' WIB' : 'Belum Pernah Login' ?>"
                                            data-created="<?= date('d F Y \p\a\d\a H:i:s', strtotime($item['created_at'])) ?> WIB">
                                            <i class="fas fa-eye text-[11px]"></i>
                                        </button>

                                        <a href="main.php?page=edit-petugas&id=<?= $item['id_user'] ?>"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-amber-500 hover:bg-amber-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Ubah Data Petugas">
                                            <i class="fas fa-edit text-[11px]"></i>
                                        </a>

                                        <a href="modules/petugas/delete-petugas.php?id=<?= $item['id_user'] ?>"
                                            onclick="return confirm('🚨 TINDAKAN PERMANEN!\nHapus data akun petugas: <?= addslashes($item['nama_lengkap']) ?>?')"
                                            class="w-7 h-7 flex items-center justify-center rounded-lg bg-white text-red-600 hover:bg-red-50 border border-slate-200/60 transition-all shadow-sm"
                                            title="Hapus Akun Permanen">
                                            <i class="fas fa-trash-alt text-[10px]"></i>
                                        </a>
                                    </div>
                                </td>

                                <td class="p-4 text-center">
                                    <a href="modules/petugas/reset-password.php?id=<?= $item['id_user'] ?>"
                                        onclick="return confirm('Reset password akun petugas ini ke standar bawaan sistem?')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-100 text-[#1257aa] hover:bg-blue-50 shadow-sm transition-all font-bold text-[10px] whitespace-nowrap"
                                        title="Reset Password">
                                        <i class="fas fa-key text-[11px] text-[#1257aa]"></i> Reset Akun
                                    </a>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>

                    <tr id="rowTidakDitemukan" class="hidden">
                        <td colspan="8" class="p-8 text-center text-slate-400 font-medium">Tidak ada data petugas yang cocok dengan pencarian.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="p-4 bg-slate-50 text-[10px] text-slate-400 border-t border-slate-100 flex justify-between">
            <span>* Kelola data otoritas tingkat tinggi ini dengan bijak dan rahasia demi keamanan sistem perbankan.</span>
            <span>Total Petugas: <span id="totalFooter"><?= count($operators) ?></span> Orang.</span>
        </div>
    </div>
</div>

<div id="modal-detail-operator" class="fixed inset-0 z-50 invisible opacity-0 pointer-events-none flex items-center justify-center bg-black/50 p-4 transition-all duration-300 ease-out overflow-y-auto">
    <div id="modal-content" class="bg-slate-50 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform scale-95 opacity-0 transition-all duration-300 ease-out my-8">

        <div class="bg-[#1566c7] p-5 text-white flex justify-between items-center shadow-md">
            <div>
                <h3 class="text-sm font-black uppercase tracking-wider flex items-center gap-2">
                    <i class="fas fa-user-shield text-amber-300"></i> Informasi Akun Petugas
                </h3>
                <p class="text-[10px] text-slate-200 mt-0.5 font-medium">Rincian hak akses kredensial login dan riwayat autentikasi server.</p>
            </div>
            <button type="button" onclick="closeModalDetail()" class="text-white opacity-80 hover:opacity-100 transition-opacity text-xl">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-5 space-y-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 flex justify-between items-center shadow-sm">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">ID Pengguna</span>
                    <h2 id="md-id-user" class="text-lg font-black text-slate-800 font-mono">#0</h2>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1 text-right">Otoritas Status:</span>
                    <span id="md-status-badge" class="px-2.5 py-1 rounded text-[9px] font-black uppercase border block text-center">ACTIVE</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm space-y-3 text-xs">
                <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5 mb-2 flex items-center gap-1.5">
                    <i class="fas fa-id-card text-blue-500"></i> Berkas Kredensial Pengguna
                </h4>
                <div class="flex flex-col">
                    <span class="text-[10px] text-slate-400 font-medium">Nama Lengkap</span>
                    <span id="md-nama" class="font-bold text-slate-800 text-sm">-</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-slate-400 font-medium">Username Aplikasi</span>
                    <span id="md-user" class="font-mono font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded w-max mt-0.5">-</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-slate-400 font-medium">Level Tingkatan Tingkat</span>
                    <span class="font-bold text-slate-700"><i class="fas fa-user-tag text-amber-500 mr-1"></i> Operator / Petugas Bank</span>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm space-y-2.5 text-xs">
                <h4 class="text-[11px] font-black text-slate-800 uppercase tracking-wide border-b border-slate-100 pb-1.5 mb-2 flex items-center gap-1.5">
                    <i class="fas fa-history text-rose-500"></i> Rekam Keamanan Sistem
                </h4>
                <div class="flex flex-col">
                    <span class="text-[10px] text-slate-400 font-medium">Sesi Login Terakhir Terdeteksi</span>
                    <span id="md-login" class="font-mono text-slate-700 font-bold bg-slate-50 p-1.5 rounded border border-slate-100 mt-1 text-[11px]">-</span>
                </div>
            </div>

            <div class="text-center text-[10px] text-slate-400 font-medium font-mono pt-1">
                <i class="fas fa-calendar-plus mr-1"></i> Akun terdaftar sejak: <span id="md-created" class="text-slate-500 font-bold">-</span>
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

        // 1. ENGINE FITUR PENCARIAN LIVE (LIVE SEARCH)
        const inputCari = document.getElementById("inputCariOperator");
        const rows = document.querySelectorAll(".row-operator");
        const rowTidakDitemukan = document.getElementById("rowTidakDitemukan");
        const totalFooter = document.getElementById("totalFooter");

        if (inputCari) {
            inputCari.addEventListener("keyup", function() {
                const keyword = inputCari.value.toLowerCase().trim();
                let countMatch = 0;

                rows.forEach(row => {
                    const nama = row.querySelector(".target-nama").textContent.toLowerCase();
                    const user = row.querySelector(".target-user").textContent.toLowerCase();

                    if (nama.includes(keyword) || user.includes(keyword)) {
                        row.style.display = ""; // tampilkan row
                        countMatch++;
                        // Re-index urutan nomor baris agar rapi kembali saat di-filter
                        row.querySelector(".target-no").textContent = countMatch;
                    } else {
                        row.style.display = "none"; // sembunyikan row
                    }
                });

                // Tampilkan baris pemberitahuan kosong jika tidak ada yang cocok
                if (countMatch === 0 && rows.length > 0) {
                    rowTidakDitemukan.classList.remove("hidden");
                } else {
                    rowTidakDitemukan.classList.add("hidden");
                }

                // Update info counter total pada footer secara dinamis
                if (totalFooter) totalFooter.textContent = countMatch;
            });
        }

        // 2. ENGINE MODAL POPUP DETAIL SYSTEM
        const modal = document.getElementById("modal-detail-operator");
        const modalContent = document.getElementById("modal-content");
        const btnDetails = document.querySelectorAll(".btn-detail-operator");

        btnDetails.forEach(btn => {
            btn.addEventListener("click", function() {
                // Tarik dataset dari tombol baris yang di-klik
                document.getElementById("md-id-user").textContent = "#" + this.dataset.id;
                document.getElementById("md-nama").textContent = this.dataset.nama;
                document.getElementById("md-user").textContent = "@" + this.dataset.user;
                document.getElementById("md-login").textContent = this.dataset.login;
                document.getElementById("md-created").textContent = this.dataset.created;

                // Modifikasi Badge Status
                const badgeStatus = document.getElementById("md-status-badge");
                const statusVal = this.dataset.status;
                badgeStatus.textContent = statusVal.toUpperCase();

                if (statusVal === 'aktif') {
                    badgeStatus.className = "px-2.5 py-1 rounded text-[9px] font-black uppercase border bg-emerald-50 border-emerald-200 text-emerald-600 block text-center";
                } else {
                    badgeStatus.className = "px-2.5 py-1 rounded text-[9px] font-black uppercase border bg-rose-50 border-rose-200 text-rose-600 block text-center";
                }

                // Munculkan Modal dengan animasi transisi Tailwind
                modal.classList.remove("invisible", "opacity-0", "pointer-events-none");
                modalContent.classList.remove("scale-95", "opacity-0");
                modalContent.classList.add("scale-100", "opacity-100");
            });
        });
    });

    // FUNGSI CLOSING MODAL
    function closeModalDetail() {
        const modal = document.getElementById("modal-detail-operator");
        const modalContent = document.getElementById("modal-content");

        modal.classList.add("invisible", "opacity-0", "pointer-events-none");
        modalContent.classList.remove("scale-100", "opacity-100");
        modalContent.classList.add("scale-95", "opacity-0");
    }
</script>