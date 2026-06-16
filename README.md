# 🏦 Sistem Aplikasi Mini Bank Sekolah

Sistem manajemen tabungan siswa yang dirancang untuk memudahkan sekolah dalam mengelola administrasi keuangan nasabah secara digital, aman, dan transparan.

## 🚀 Fitur Utama
*   **Multi-User Role**: Mendukung 4 level akses pengguna.
*   **Manajemen Transaksi**: Setoran, penarikan, dan cek saldo secara real-time.
*   **Laporan Otomatis**: Export laporan ke format PDF (via FPDF) dan Excel.
*   **Notifikasi Email**: Pengiriman struk atau notifikasi melalui email (via PHPMailer).
*   **Keamanan Data**: Enkripsi password dan perlindungan data sensitif menggunakan `.env`.

---

## 👥 Pengguna Sistem & Hak Akses

### 1. Administrator Utama (Super Admin)
*   **Kendali Penuh**: Mengelola seluruh data sistem dan manajemen pengguna (CRUD Admin, Petugas, Supervisor).
*   **Keamanan**: Reset password pengguna, backup & restore database, dan log aktivitas.
*   **Konfigurasi**: Pengaturan identitas sekolah dan manajemen role.
*   **Monitoring**: Dashboard statistik keseluruhan dan persetujuan akses.

### 2. Petugas / Admin Bank (Operator)
*   **Operasional**: CRUD data nasabah (siswa) dan data kelas.
*   **Transaksi**: Input setoran, penarikan, dan cetak struk transaksi.
*   **Pelaporan**: Melihat riwayat transaksi, filter laporan, dan export data (PDF/Excel).

### 3. Nasabah (Siswa / Anggota)
*   **Mandiri**: Login siswa untuk cek saldo dan riwayat transaksi secara personal.
*   **Visualisasi**: Melihat grafik perkembangan tabungan.
*   **Dokumentasi**: Download struk transaksi mandiri.
*   **Akun**: Mengubah password dan menerima notifikasi transaksi.

### 4. Pembimbing / Kepala Sekolah (Supervisor)
*   **Pengawasan**: Monitoring seluruh aktivitas transaksi dan statistik keuangan.
*   **Analitik**: Melihat laporan keseluruhan dengan filter (bulan/kelas/tahun).
*   **Otoritas**: Approval untuk transaksi tertentu.

---

## 🛠️ Teknologi yang Digunakan
*   **Bahasa**: PHP Native
*   **Database**: MySQL
*   **Library (Composer)**:
    *   `setasign/fpdf`: Untuk pembuatan laporan PDF.
    *   `phpmailer/phpmailer`: Untuk fitur notifikasi email.
    *   `vlucas/phpdotenv`: Untuk manajemen variabel environment (.env).
    *   `nesbot/carbon`: Untuk manipulasi tanggal dan waktu.

---