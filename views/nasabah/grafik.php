<?php

/**
 * LOGIKA BACKEND: Menyiapkan data untuk Grafik
 * Kita mengambil histori saldo nasabah dalam 7-30 hari terakhir.
 */
$id_nasabah = $_SESSION['id_nasabah'] ?? null;
$labels = [];
$data_saldo = [];

if ($id_nasabah) {
    try {
        // Mengambil data saldo akhir setiap hari dari riwayat transaksi
        $stmt_grafik = $pdo->prepare("
            SELECT DATE(tanggal_transaksi) as tgl, saldo_akhir 
            FROM tbl_transaksi 
            WHERE id_nasabah = ? 
            AND tanggal_transaksi >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY tanggal_transaksi ASC
        ");
        $stmt_grafik->execute([$id_nasabah]);
        $result_grafik = $stmt_grafik->fetchAll();

        foreach ($result_grafik as $rg) {
            $labels[] = date('d M', strtotime($rg['tgl']));
            $data_saldo[] = $rg['saldo_akhir'];
        }
    } catch (PDOException $e) {
        error_log("Gagal memuat data grafik: " . $e->getMessage());
    }
}

// Konversi ke format JSON agar bisa dibaca oleh JavaScript
$json_labels = json_encode($labels);
$json_data = json_encode($data_saldo);
?>

<!-- SECTION: GRAFIK -->
<div id="section-grafik" class="w-full">
    <div class="bg-white p-4 sm:p-8 rounded-[1rem] border border-slate-100 shadow-sm transition-all duration-300">

        <!-- Header Grafik -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 sm:mb-8 gap-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-gradient-to-br from-[#2978d7] to-[#1257aa] flex items-center justify-center text-white shadow-md shadow-blue-500/10 shrink-0">
                    <i class="fas fa-chart-line text-white"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="font-black text-[#506a8a] flex items-center gap-2">
                        Visualisasi Tabungan Anda
                    </h3>
                    <p class="text-[11px] sm:text-xs text-slate-400 font-medium mt-0.5 leading-relaxed">
                        Pantau tren pertumbuhan saldo riil Anda berdasarkan akumulasi aktivitas transaksi 30 hari terakhir.
                    </p>
                </div>
            </div>

            <!-- Filter Rentang -->
            <div class="flex items-center gap-2 self-end sm:self-center bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-xl shrink-0">
                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-wider">Rentang:</span>
                <select class="bg-transparent text-[10px] font-black text-slate-700 outline-none cursor-pointer">
                    <option>30 Hari Terakhir</option>
                    <option disabled>3 Bulan (Segera)</option>
                </select>
            </div>
        </div>

        <!-- Wadah Grafik (Responsive Height Fix) -->
        <div class="relative h-[260px] sm:h-[360px] w-full">
            <canvas id="savingsChart"></canvas>
        </div>

        <!-- Informasi Edukasi & Insight Finansial di Bawah Grafik -->
        <div class="mt-6 sm:mt-8 grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 border-t border-slate-100 pt-5 sm:pt-6">

            <!-- Insight 1: Pertumbuhan -->
            <div class="flex items-start gap-3.5 p-4 bg-emerald-50/60 rounded-xl border border-emerald-100/50 hover:bg-emerald-50 transition-colors duration-300">
                <div class="w-9 h-9 sm:w-10 sm:h-10 bg-white rounded-xl flex items-center justify-center text-emerald-600 shadow-sm border border-emerald-100/50 shrink-0">
                    <i class="fas fa-seedling text-sm sm:text-base"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-[9px] font-black text-emerald-700 uppercase tracking-wider bg-emerald-100 px-1.5 py-0.5 rounded">Analisis Tren</span>
                    </div>
                    <p class="text-[11px] sm:text-xs text-slate-600 font-semibold leading-relaxed">
                        Garis grafik yang bergerak naik mengindikasikan tingkat retensi saldo harian yang sehat dan manajemen dana yang konsisten.
                    </p>
                </div>
            </div>

            <!-- Insight 2: Tips Literasi -->
            <div class="flex items-start gap-3.5 p-4 bg-amber-50/60 rounded-xl border border-amber-100/50 hover:bg-amber-50 transition-colors duration-300">
                <div class="w-9 h-9 sm:w-10 sm:h-10 bg-white rounded-xl flex items-center justify-center text-amber-600 shadow-sm border border-amber-100/50 shrink-0">
                    <i class="fas fa-lightbulb text-sm sm:text-base"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-[9px] font-black text-amber-700 uppercase tracking-wider bg-amber-100 px-1.5 py-0.5 rounded">Tips Finansial</span>
                    </div>
                    <p class="text-[11px] sm:text-xs text-slate-600 font-semibold leading-relaxed">
                        Minimalisir penurunan kurva yang curam dengan menetapkan batas aman penarikan tunai harian secara terjadwal.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Script Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('savingsChart').getContext('2d');

    // Inisialisasi Chart.js dengan optimasi tampilan professional
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $json_labels ?>,
            datasets: [{
                label: 'Total Saldo (Rp)',
                data: <?= $json_data ?>,
                borderColor: '#1566c7',
                backgroundColor: (context) => {
                    const chart = context.chart;
                    const {
                        ctx,
                        chartArea
                    } = chart;
                    if (!chartArea) return null;

                    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    gradient.addColorStop(0, 'rgba(21, 102, 199, 0.22)');
                    gradient.addColorStop(1, 'rgba(21, 102, 199, 0.00)');
                    return gradient;
                },
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#1566c7',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointHoverBorderWidth: 3,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // Menjaga agar height CSS diatur manual lewat Tailwind div pembungkus
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: {
                        size: 11,
                        weight: 'bold',
                        family: 'system-ui'
                    },
                    bodyFont: {
                        size: 12,
                        weight: '600',
                        family: 'system-ui'
                    },
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return ' Saldo: Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: {
                        color: '#f8fafc'
                    },
                    border: {
                        dash: [5, 5]
                    },
                    ticks: {
                        font: {
                            size: 9,
                            weight: '600'
                        },
                        color: '#94a3b8',
                        // Fungsi pintar untuk menyingkat angka panjang di layar HP agar rapi
                        callback: function(value) {
                            if (value >= 1e9) {
                                return (value / 1e9).toFixed(1).replace('.0', '') + ' M';
                            }
                            if (value >= 1e6) {
                                return (value / 1e6).toFixed(1).replace('.0', '') + ' jt';
                            }
                            if (value >= 1e3) {
                                return (value / 1e3).toFixed(0) + ' rb';
                            }
                            return 'Rp ' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 9,
                            weight: '600'
                        },
                        color: '#94a3b8',
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 6 // Mencegah label tanggal tumpang tindih di layar HP
                    }
                }
            }
        }
    });
</script>