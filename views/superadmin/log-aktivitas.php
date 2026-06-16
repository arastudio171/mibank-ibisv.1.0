<?php
// Ambil seluruh data log aktivitas lengkap dengan join relasi nasabah & user
$stmt_all_logs = $pdo->query("
    SELECT 
        l.*, 
        u.username AS nama_operator,
        n.nama_nasabah,
        n.nisn,
        n.kelas,
        j.nama_jurusan
    FROM log_activity l
    LEFT JOIN tbl_users u ON l.id_user = u.id_user
    LEFT JOIN tbl_nasabah n ON l.id_nasabah = n.id_nasabah
    LEFT JOIN tbl_jurusan j ON n.id_jurusan = j.id_jurusan
    ORDER BY l.timestamp DESC
");
$all_activity_logs = $stmt_all_logs->fetchAll(PDO::FETCH_ASSOC);

// Hitung total log untuk dipasang pada bagian Tfoot / Ringkasan Kalkulator
$total_logs = count($all_activity_logs);
?>

<div class="space-y-6">
    <div class="bg-white rounded-[1rem] border border-slate-100 shadow-sm overflow-hidden">

        <div class="p-5 border-b border-slate-100 bg-slate-50/50">
            <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fas fa-fingerprint text-indigo-600"></i> Audit Sistem & Log Aktivitas Pengguna
            </h4>
            <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                <i class="fas fa-info-circle text-slate-400 text-[10px]"></i> Rekam jejak forensik keamanan, tindakan administratif, operasi kas, serta otentikasi login pengguna.
            </p>
        </div>

        <div class="mx-5 mt-4 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-start gap-2.5 text-blue-800 text-[11px] leading-relaxed">
            <i class="fas fa-shield-alt text-blue-500 mt-0.5 text-xs"></i>
            <div>
                <span class="font-bold">Log Kepatuhan (Audit Trail):</span> Data di bawah bersifat <span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded font-bold text-[10px]">READ-ONLY</span>. Seluruh parameter koneksi, alamat IP, dan agen pengguna dienkapsulasi secara otomatis oleh server demi menjaga validitas serta keamanan data dari manipulasi internal.
            </div>
        </div>

        <div class="overflow-x-auto mt-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 text-slate-400 text-[10px] uppercase tracking-wider font-bold bg-slate-50/30">
                        <th class="p-4 w-12 text-center">No.</th>
                        <th class="p-4"><i class="far fa-clock mr-1"></i> Hari, Tanggal & Waktu</th>
                        <th class="p-4"><i class="fas fa-user-shield mr-1"></i> Informasi Pelaku</th>
                        <th class="p-4"><i class="fas fa-tasks mr-1"></i> Tindakan / Aktivitas</th>
                        <th class="p-4"><i class="fas fa-network-wired mr-1"></i> IP Address</th>
                        <th class="p-4"><i class="fas fa-laptop mr-1"></i> User Agent (Asli)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                    <?php if (!empty($all_activity_logs)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($all_activity_logs as $log): ?>
                            <?php
                            // Deteksi entitas role pelaku
                            $is_nasabah = (strtolower($log['role_pelaku']) == 'nasabah');
                            $nama_pelaku = $is_nasabah ? $log['nama_nasabah'] : $log['nama_operator'];
                            $nama_pelaku = $nama_pelaku ?? 'Sistem / Otomatis';

                            // Deteksi otomatis indikasi log mencurigakan / error gawat
                            $is_suspicious = false;
                            $bad_words = ['gagal', 'delete', 'hapus', 'salah', 'unauthorized', 'banned', 'suspend', 'paksa', 'error', 'drop'];
                            foreach ($bad_words as $word) {
                                if (stripos($log['aktivitas'], $word) !== false) {
                                    $is_suspicious = true;
                                    break;
                                }
                            }

                            // Variasi style row jika terdeteksi indikasi mencurigakan
                            $row_style = $is_suspicious
                                ? 'bg-rose-50/30 hover:bg-rose-50/60 transition-colors'
                                : 'hover:bg-slate-50/40 transition-colors';
                            ?>
                            <tr class="<?= $row_style ?>">
                                <td class="p-4 text-center font-bold text-slate-400 font-mono"><?= $no++ ?></td>

                                <td class="p-4 font-medium text-slate-600 whitespace-nowrap">
                                    <div class="flex items-center gap-2 font-bold text-slate-700">
                                        <i class="far fa-calendar text-slate-400"></i>
                                        <?= function_exists('hariIndo') ? hariIndo($log['timestamp']) : date('l', strtotime($log['timestamp'])) ?>,
                                        <?= date('d M Y', strtotime($log['timestamp'])) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 ml-5 font-mono">
                                        <i class="far fa-clock text-slate-400"></i> <?= date('H:i:s', strtotime($log['timestamp'])) ?> WIB
                                    </div>
                                </td>

                                <td class="p-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                        <?= htmlspecialchars($nama_pelaku) ?>
                                        <?php if ($is_suspicious): ?>
                                            <span class="px-1.5 py-0.2 bg-rose-600 text-white text-[8px] font-black rounded uppercase tracking-wider animate-pulse inline-block">
                                                <i class="fas fa-exclamation-circle text-[7px]"></i> Alert
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-[10px] text-slate-500 mt-0.5 flex items-center flex-wrap gap-x-2 gap-y-0.5">
                                        <?php if ($is_nasabah): ?>
                                            <span><i class="fas fa-graduation-cap text-[10px] text-amber-500"></i></span>
                                            <span>NISN: <b><?= htmlspecialchars($log['nisn'] ?? '-') ?></b></span>
                                            <span class="text-slate-300">|</span>
                                            <span>Kelas: <b><?= htmlspecialchars($log['kelas'] ?? '-') ?></b></span>
                                            <span class="text-slate-300">|</span>
                                            <span>Jurusan: <b class="text-[#1258ab]"><?= htmlspecialchars($log['nama_jurusan'] ?? 'Umum') ?></b></span>
                                        <?php else: ?>
                                            <span><i class="fas fa-user-shield text-[10px] text-indigo-500"></i></span>
                                            <span>Hak Akses: <b class="uppercase text-indigo-600"><?= htmlspecialchars($log['role_pelaku'] ?? 'Sistem') ?></b></span>
                                            <span class="text-slate-300">|</span>
                                            <span>ID Log: <b>#<?= $log['id_log'] ?></b></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="<?= $is_suspicious ? 'text-rose-600 font-bold' : 'text-slate-700 font-medium' ?> min-w-[200px] max-w-[320px] break-words line-clamp-2" title="<?= htmlspecialchars($log['aktivitas']) ?>">
                                        <?= htmlspecialchars($log['aktivitas']) ?>
                                    </div>
                                </td>

                                <td class="p-4 whitespace-nowrap">
                                    <span class="font-mono font-bold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded text-[11px]">
                                        <?= htmlspecialchars($log['ip_address'] ?? '0.0.0.0') ?>
                                    </span>
                                </td>

                                <td class="p-4">
                                    <div class="text-slate-400 font-mono text-[10px] max-w-[280px] break-all line-clamp-2 leading-tight" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                        <?= htmlspecialchars($log['user_agent'] ?? 'Tidak Terdeteksi') ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="p-12 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-folder-open text-2xl mb-2 opacity-50 text-slate-300"></i>
                                    <p class="font-bold">Belum ada riwayat audit log aktivitas yang terekam.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

                <tfoot>
                    <tr class="bg-slate-50/80 border-t-2 border-slate-200 text-xs">
                        <td colspan="5" class="p-4 font-black text-slate-700 text-right uppercase">
                            <i class="fas fa-calculator mr-1 text-slate-400"></i> Total Entri Log Terdaftar Saat Ini:
                        </td>
                        <td class="p-4 font-black text-indigo-600 text-right tabular-nums text-xs">
                            <?= number_format($total_logs, 0, ',', '.') ?> <span class="text-xs text-slate-400 font-normal">Baris Log</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>