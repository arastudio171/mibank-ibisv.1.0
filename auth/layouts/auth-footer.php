<script>
    // Toggle show/hide password
    function togglePass() {
        const pwd = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');

        if (!pwd || !icon) return;

        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // Toast notification
    function showToast(msg) {
        const toast = document.getElementById('toast');
        const message = document.getElementById('toastMessage');

        if (!toast || !message) return;

        message.innerText = msg;

        toast.classList.remove('opacity-0', 'translate-y-[20px]');
        toast.classList.add('opacity-100', 'translate-y-0');

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-[20px]');
            toast.classList.remove('opacity-100', 'translate-y-0');
        }, 4000);
    }

    // Open forgot PIN modal
    function openForgotPinModal() {
        const modal = document.getElementById('forgotPinModal');
        if (!modal) return;

        modal.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
        modal.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
    }

    // Close forgot PIN modal
    function closeForgotPinModal() {
        const modal = document.getElementById('forgotPinModal');
        if (!modal) return;

        modal.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
        modal.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
    }

    // Close modal when clicking outside
    const modal = document.getElementById('forgotPinModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeForgotPinModal();
            }
        });
    }

    // Hide preloader
    function hidePreloader() {
        const preloader = document.getElementById('pagePreloader');

        if (preloader && !preloader.classList.contains('preloader-hidden')) {
            preloader.classList.add('preloader-hidden');
        }
    }

    window.addEventListener('DOMContentLoaded', hidePreloader);
    window.addEventListener('load', hidePreloader);

    setTimeout(hidePreloader, 900);

    /**
     1. PROTEKSI INSPECT ELEMENT
     * Mencegah klik kanan, shortcut keyboard untuk membuka DevTools, dan lainnya.
     * Ini bukan metode yang benar-benar aman, tapi cukup untuk menghalangi pengguna awam.
     */
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', function(e) {
        if (
            e.key === "F12" ||
            (e.ctrlKey && e.key === "u") ||
            (e.ctrlKey && e.key === "c") || // Blokir Ctrl+C (Copy)
            (e.ctrlKey && e.key === "x") || // Blokir Ctrl+X (Cut)
            (e.ctrlKey && e.key === "a") || // Blokir Ctrl+A (Select All)
            (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J" || e.key === "C"))
        ) {
            e.preventDefault();
            return false;
        }
    });

    /**
     2. PROTEKSI DRAG & DROP (Anti Seret Teks/Gambar)
     */
    document.addEventListener('dragstart', e => e.preventDefault());
    document.addEventListener('selectstart', e => e.preventDefault());

    /**
     3. INFINITE DEBUGGER LOGIC (Senjata Utama)
     * Jika DevTools terbuka, browser akan dipaksa berhenti (pause) setiap 100ms.
     * Ini membuat Inspect Element menjadi hang/macet dan tidak bisa digunakan.
     */
    (function() {
        const d = function() {
            function debug(i) {
                if (("" + i / i).length !== 1 || i % 20 === 0) {
                    (function() {}).constructor("debugger")();
                } else {
                    (function() {}).constructor("debugger")();
                }
                debug(++i);
            }
            try {
                debug(0);
            } catch (e) {
                // Berulang terus untuk mengunci tab browser jika devtools aktif
                setTimeout(d, 100);
            }
        };
        // Jalankan pelacakan
        setTimeout(d, 500);
    })();

    /**
     4. AUTO CONSOLE CLEANER
     * Menghapus semua log secara paksa jika ada yang mencoba menyuntikkan script via console
     */
    setInterval(function() {
        console.clear();
    }, 300);

    document.addEventListener('DOMContentLoaded', () => {
        const inputUsername = document.getElementById('username');
        const inputPassword = document.getElementById('password');

        // Elemen Indikator Kekuatan Password
        const meterContainer = document.getElementById('strength-meter-container');
        const strengthText = document.getElementById('strength-text');
        const bars = [
            document.getElementById('bar-1'),
            document.getElementById('bar-2'),
            document.getElementById('bar-3'),
            document.getElementById('bar-4')
        ];

        let hasRecommended = false; // Flag agar tidak terus-terusan mengacak saat user mengetik NISN

        // 1. Fungsi Generator Password Acak Kuat (Contoh output: AXmj675EnM!)
        function generateSecurePassword(length = 11) {
            const uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            const lowercase = "abcdefghijklmnopqrstuvwxyz";
            const numbers = "0123456789";
            const symbols = "!@#$%^&*";
            const allChars = uppercase + lowercase + numbers + symbols;

            let password = "";
            password += uppercase[Math.floor(Math.random() * uppercase.length)];
            password += lowercase[Math.floor(Math.random() * lowercase.length)];
            password += numbers[Math.floor(Math.random() * numbers.length)];
            password += symbols[Math.floor(Math.random() * symbols.length)];

            for (let i = 4; i < length; i++) {
                password += allChars[Math.floor(Math.random() * allChars.length)];
            }
            // Acak urutan karakter agar posisinya tidak selalu sama di depan
            return password.split('').sort(() => 0.5 - Math.random()).join('');
        }

        // 2. Fungsi Pengecek Kekuatan Password (1 - 4)
        function checkPasswordStrength(password) {
            if (!password) return 0;

            let score = 0;
            if (password.length >= 6) score++;
            if (password.length >= 10) score++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            let finalScore = Math.min(Math.max(Math.floor(score * 0.8), 1), 4);
            if (password.length < 6) finalScore = 1;

            return finalScore;
        }

        // 3. Fungsi Update Tampilan Bar Warna
        function updateMeterUI(score) {
            bars.forEach(bar => bar.className = "h-full rounded-full bg-slate-200 transition-all duration-300");

            if (score === 0) {
                strengthText.textContent = "Belum diisi";
                strengthText.className = "text-slate-400";
                return;
            }

            const levels = {
                1: {
                    text: "Lemah ❌",
                    color: "bg-red-500",
                    textColor: "text-red-500"
                },
                2: {
                    text: "Sedang ⚠️",
                    color: "bg-amber-500",
                    textColor: "text-amber-500"
                },
                3: {
                    text: "Kuat 💪",
                    color: "bg-blue-500",
                    textColor: "text-blue-500"
                },
                4: {
                    text: "Sangat Kuat 🔥",
                    color: "bg-emerald-500",
                    textColor: "text-emerald-500"
                }
            };

            const current = levels[score];
            strengthText.textContent = current.text;
            strengthText.className = `transition-colors duration-300 ${current.textColor}`;

            for (let i = 0; i < score; i++) {
                bars[i].classList.remove('bg-slate-200');
                bars[i].classList.add(current.color);
            }
        }

        // TRIGGER 1: Saat NISN Mulai Diketik, Isi Otomatis Kolom Password
        inputUsername.addEventListener('input', () => {
            if (inputUsername.value.trim().length > 0) {
                // Isi password otomatis hanya jika kolom password masih kosong dan belum pernah direkomendasikan
                if (inputPassword.value === "" && !hasRecommended) {
                    inputPassword.value = generateSecurePassword(11);
                    hasRecommended = true;

                    // Tampilkan indikator kekuatan karena password baru terisi
                    meterContainer.classList.remove('hidden');
                    const score = checkPasswordStrength(inputPassword.value);
                    updateMeterUI(score);
                }
            } else {
                // Jika NISN dihapus total oleh user, kosongkan password kembali
                inputPassword.value = "";
                hasRecommended = false;
                meterContainer.classList.add('hidden');
            }
        });

        // TRIGGER 2: Saat User Mengetik Sendiri / Mengubah Password
        inputPassword.addEventListener('input', () => {
            meterContainer.classList.remove('hidden'); // Selalu tampilkan saat sedang diketik
            const score = checkPasswordStrength(inputPassword.value);
            updateMeterUI(score);
        });

        // TRIGGER 3: Saat User Klik Masuk ke Kolom Password (Focus)
        inputPassword.addEventListener('focus', () => {
            if (inputPassword.value.length > 0) {
                meterContainer.classList.remove('hidden');
                const score = checkPasswordStrength(inputPassword.value);
                updateMeterUI(score);
            }
        });

        // TRIGGER 4: Saat User Selesai & Pindah ke Kolom Lain (Blur) -> KEMBALI NORMAL / HILANG
        inputPassword.addEventListener('blur', () => {
            // Beri sedikit delay halus sebelum menghilang agar transisinya rapi
            setTimeout(() => {
                meterContainer.classList.add('hidden');
            }, 150);
        });
    });
</script>
</body>

</html>