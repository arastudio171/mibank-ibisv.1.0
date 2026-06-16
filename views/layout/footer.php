<script>
    /**
     * ==========================================
     * 1. MANAJEMEN UI (SIDEBAR & NAVIGATION)
     * ==========================================
     */

    // Toggle Sidebar Desktop: Mengubah lebar sidebar dan ikon panah
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const icon = document.getElementById('toggle-icon');
        sidebar.classList.toggle('sidebar-collapsed');

        if (icon) {
            icon.className = sidebar.classList.contains('sidebar-collapsed') ?
                "fas fa-chevron-right text-[10px]" :
                "fas fa-chevron-left text-[10px]";
        }
    }

    // Mobile Menu: Menampilkan/menyembunyikan sidebar pada layar kecil dengan overlay
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('hidden');
    }

    // Navigasi Halaman: Mengatur pergantian konten section dan status aktif menu
    function navigate(sectionId) {
        const titles = {
            'dashboard': 'Dashboard Utama',
            'monitoring': 'Live Monitor',
            'admin': 'Manajemen Admin',
            'petugas': 'Manajemen Petugas'
        };

        // Update judul halaman
        document.getElementById('section-title').innerText = titles[sectionId] || 'Admin Panel';

        // Sembunyikan semua section, tampilkan yang dipilih
        document.querySelectorAll('#content-area > section').forEach(s => s.classList.add('hidden'));
        const target = document.getElementById('section-' + sectionId);
        if (target) target.classList.remove('hidden');

        // Update status aktif pada tombol navigasi
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active-nav'));
        const activeBtn = document.getElementById('nav-' + sectionId);
        if (activeBtn) activeBtn.classList.add('active-nav');

        // Tutup menu jika di perangkat mobile
        if (window.innerWidth < 1024) toggleMobileMenu();
    }

    // Konfirmasi Logout
    function handleLogout() {
        if (confirm('Keluar dari sistem?')) window.location.reload();
    }

    /**
     * ==========================================
     * 2. INITIALIZATION (CHART & WAKTU)
     * ==========================================
     */
    window.onload = function() {
        if (typeof updateDate === 'function') updateDate();

        // Inisialisasi Grafik Statistik Login Petugas menggunakan Chart.js
        const ctx = document.getElementById('mainChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                datasets: [{
                    label: 'Login Petugas',
                    data: [65, 59, 80, 81, 56, 40, 30],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.05)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        grid: {
                            borderDash: [5, 5],
                            color: '#e2e8f0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    };

    /**
     * ==========================================
     * 3. SECURITY LAYERS (ANTI-INSPECT ELEMENT)
     * ==========================================
     */

    // Blokir klik kanan dan shortcut DevTools (F12, View Source)
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', function(e) {
        if (
            e.key === "F12" ||
            (e.ctrlKey && e.key === "u") ||
            (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J" || e.key === "C"))
        ) {
            e.preventDefault();
            return false;
        }
    });

    // Infinite Debugger: Memaksa browser melakukan 'pause' jika DevTools dibuka
    (function() {
        const d = function() {
            try {
                (function() {}).constructor("debugger")();
                setTimeout(d, 500);
            } catch (e) {
                setTimeout(d, 500);
            }
        };
        setTimeout(d, 500);
    })();

    // Auto Console Cleaner: Membersihkan log konsol setiap detik
    setInterval(function() {
        console.clear();
    }, 1000);
</script>
</body>

</html>