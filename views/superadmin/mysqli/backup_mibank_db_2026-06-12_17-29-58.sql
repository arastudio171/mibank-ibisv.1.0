-- IBIS (Internet Banking Sekolah) Database Dump
-- Waktu Backup: 2026-06-12 17:29:58
-- Database: `mibank_db`

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `log_activity`;
CREATE TABLE `log_activity` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_user` int DEFAULT NULL,
  `id_nasabah` int DEFAULT NULL,
  `role_pelaku` varchar(50) DEFAULT NULL,
  `aktivitas` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `id_user` (`id_user`),
  KEY `id_nasabah` (`id_nasabah`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `fk_log_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE CASCADE,
  CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) REFERENCES `tbl_users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=447 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('416', '1', NULL, 'admin', 'Login berhasil Staff: 0810805500', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:30:49');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('417', '1', NULL, 'admin', 'User 0810805500 (admin) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:32:12');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('418', '1', NULL, 'admin', 'Login berhasil Staff: 0810805500', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:32:56');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('419', '1', NULL, 'admin', 'User 0810805500 (admin) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:33:00');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('420', NULL, '5', 'nasabah', 'Nasabah login (NISN: 0810805506)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:34:47');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('421', NULL, '5', 'nasabah', 'User 0810805506 (nasabah) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:39:23');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('422', '1', NULL, 'admin', 'Login berhasil Staff: 0810805500', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:40:20');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('423', '1', NULL, 'admin', 'User 0810805500 (admin) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:40:31');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('424', NULL, '1', 'nasabah', 'Nasabah login (NISN: 0810805502)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:40:37');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('425', NULL, '1', 'nasabah', 'User 0810805502 (nasabah) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:50:58');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('426', '2', NULL, 'operator', 'Login berhasil Staff: 0810805501', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:51:01');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('427', '2', NULL, 'operator', 'User 0810805501 (operator) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:54:17');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('428', '1', NULL, 'admin', 'Login berhasil Staff: 0810805500', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 10:54:19');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('429', '1', NULL, 'admin', 'User 0810805500 (admin) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 11:00:29');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('430', NULL, '1', 'nasabah', 'Nasabah login (NISN: 0810805502)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 11:02:40');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('431', NULL, '1', 'nasabah', 'User 0810805502 (nasabah) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 11:03:10');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('432', '1', NULL, 'admin', 'Login berhasil Staff: 000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:00');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('433', '1', NULL, 'admin', 'User 000 (admin) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:06');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('434', '2', NULL, 'operator', 'Login berhasil Staff: 001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:32');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('435', '2', NULL, 'operator', 'User 001 (operator) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:36');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('436', NULL, '1', 'nasabah', 'Nasabah login (NISN: 002)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:43');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('437', NULL, '1', 'nasabah', 'User 002 (nasabah) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:49');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('438', NULL, '5', 'nasabah', 'Nasabah login (NISN: 004)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:55');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('439', NULL, '5', 'nasabah', 'User 004 (nasabah) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:24:59');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('440', '2', NULL, 'operator', 'Login berhasil Staff: 001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:25:04');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('441', '2', NULL, 'operator', 'User 001 (operator) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:26:24');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('442', '1', NULL, 'admin', 'Login berhasil Staff: 000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:28:41');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('443', '1', NULL, 'admin', 'User 000 (admin) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:29:01');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('444', '2', NULL, 'operator', 'Login berhasil Staff: 001', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:29:08');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('445', '2', NULL, 'operator', 'User 001 (operator) telah melakukan logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:29:13');
INSERT INTO `log_activity` (`id_log`, `id_user`, `id_nasabah`, `role_pelaku`, `aktivitas`, `ip_address`, `user_agent`, `timestamp`) VALUES ('446', '1', NULL, 'admin', 'Login berhasil Staff: 000', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-12 17:29:19');

DROP TABLE IF EXISTS `tbl_antrean`;
CREATE TABLE `tbl_antrean` (
  `id_antrean` int NOT NULL AUTO_INCREMENT,
  `nomor_antrean` varchar(10) NOT NULL,
  `angka_urutan` int NOT NULL,
  `id_nasabah` int NOT NULL,
  `id_loket` int DEFAULT NULL,
  `tanggal_antrean` date NOT NULL,
  `status_antrean` enum('tunggu','panggil','selesai','lewat') DEFAULT 'tunggu',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_antrean`),
  KEY `id_nasabah` (`id_nasabah`),
  KEY `id_loket` (`id_loket`),
  KEY `tanggal_antrean` (`tanggal_antrean`),
  CONSTRAINT `fk_antrean_loket` FOREIGN KEY (`id_loket`) REFERENCES `tbl_loket` (`id_loket`) ON DELETE CASCADE,
  CONSTRAINT `fk_antrean_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_jenis_transaksi`;
CREATE TABLE `tbl_jenis_transaksi` (
  `id_jenis_transaksi` int NOT NULL AUTO_INCREMENT,
  `kode_jenis` varchar(20) NOT NULL,
  `nama_jenis` varchar(50) NOT NULL,
  PRIMARY KEY (`id_jenis_transaksi`),
  UNIQUE KEY `kode_jenis` (`kode_jenis`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_jenis_transaksi` (`id_jenis_transaksi`, `kode_jenis`, `nama_jenis`) VALUES ('1', 'CR', 'Kredit (Saldo Masuk)');
INSERT INTO `tbl_jenis_transaksi` (`id_jenis_transaksi`, `kode_jenis`, `nama_jenis`) VALUES ('2', 'DB', 'Debet (Saldo Keluar)');
INSERT INTO `tbl_jenis_transaksi` (`id_jenis_transaksi`, `kode_jenis`, `nama_jenis`) VALUES ('3', 'SET', 'Setoran Tunai');
INSERT INTO `tbl_jenis_transaksi` (`id_jenis_transaksi`, `kode_jenis`, `nama_jenis`) VALUES ('4', 'TAR', 'Penarikan Tunai');
INSERT INTO `tbl_jenis_transaksi` (`id_jenis_transaksi`, `kode_jenis`, `nama_jenis`) VALUES ('5', 'PBK-M', 'Pindah Buku Masuk');
INSERT INTO `tbl_jenis_transaksi` (`id_jenis_transaksi`, `kode_jenis`, `nama_jenis`) VALUES ('6', 'PBK-K', 'Pindah Buku Keluar');

DROP TABLE IF EXISTS `tbl_jurnal_kas`;
CREATE TABLE `tbl_jurnal_kas` (
  `id_jurnal` int NOT NULL AUTO_INCREMENT,
  `id_user` int NOT NULL,
  `id_loket` int NOT NULL,
  `saldo_awal_laci` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_setoran_tunai` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_setoran_brilink` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_penarikan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `saldo_akhir_laci` decimal(15,2) NOT NULL DEFAULT '0.00',
  `waktu_buka` datetime NOT NULL,
  `waktu_tutup` datetime DEFAULT NULL,
  `status_jurnal` enum('open','closed') DEFAULT 'open',
  PRIMARY KEY (`id_jurnal`),
  KEY `id_user` (`id_user`),
  KEY `id_loket` (`id_loket`),
  KEY `status_jurnal` (`status_jurnal`),
  CONSTRAINT `fk_jurnal_loket` FOREIGN KEY (`id_loket`) REFERENCES `tbl_loket` (`id_loket`) ON DELETE RESTRICT,
  CONSTRAINT `fk_jurnal_user` FOREIGN KEY (`id_user`) REFERENCES `tbl_users` (`id_user`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_jurusan`;
CREATE TABLE `tbl_jurusan` (
  `id_jurusan` int NOT NULL AUTO_INCREMENT,
  `kode_jurusan` varchar(10) NOT NULL,
  `nama_jurusan` varchar(50) NOT NULL,
  PRIMARY KEY (`id_jurusan`),
  UNIQUE KEY `kode_jurusan` (`kode_jurusan`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_jurusan` (`id_jurusan`, `kode_jurusan`, `nama_jurusan`) VALUES ('1', 'RPL', 'Rekayasa Perangkat Lunak');
INSERT INTO `tbl_jurusan` (`id_jurusan`, `kode_jurusan`, `nama_jurusan`) VALUES ('3', 'AKL', 'Akuntansi dan Keuangan Lembaga');
INSERT INTO `tbl_jurusan` (`id_jurusan`, `kode_jurusan`, `nama_jurusan`) VALUES ('4', 'BDP', 'Bisnis Daring dan Pemasaran');

DROP TABLE IF EXISTS `tbl_loket`;
CREATE TABLE `tbl_loket` (
  `id_loket` int NOT NULL AUTO_INCREMENT,
  `nomor_loket` int NOT NULL,
  `nama_loket` varchar(50) NOT NULL,
  `id_petugas` int DEFAULT NULL,
  `status_loket` enum('buka','tutup') DEFAULT 'tutup',
  PRIMARY KEY (`id_loket`),
  UNIQUE KEY `nomor_loket` (`nomor_loket`),
  UNIQUE KEY `id_petugas` (`id_petugas`),
  CONSTRAINT `fk_loket_petugas` FOREIGN KEY (`id_petugas`) REFERENCES `tbl_users` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_loket` (`id_loket`, `nomor_loket`, `nama_loket`, `id_petugas`, `status_loket`) VALUES ('1', '1', 'TELLER 1', NULL, 'tutup');
INSERT INTO `tbl_loket` (`id_loket`, `nomor_loket`, `nama_loket`, `id_petugas`, `status_loket`) VALUES ('2', '2', 'TELLER 2', NULL, 'tutup');

DROP TABLE IF EXISTS `tbl_metode_transaksi`;
CREATE TABLE `tbl_metode_transaksi` (
  `id_metode_transaksi` int NOT NULL AUTO_INCREMENT,
  `kode_metode` varchar(20) NOT NULL,
  `nama_metode` varchar(50) NOT NULL,
  PRIMARY KEY (`id_metode_transaksi`),
  UNIQUE KEY `kode_metode` (`kode_metode`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_metode_transaksi` (`id_metode_transaksi`, `kode_metode`, `nama_metode`) VALUES ('1', 'TUNAI', 'Tunai');
INSERT INTO `tbl_metode_transaksi` (`id_metode_transaksi`, `kode_metode`, `nama_metode`) VALUES ('2', 'PBK', 'Pindah Buku');
INSERT INTO `tbl_metode_transaksi` (`id_metode_transaksi`, `kode_metode`, `nama_metode`) VALUES ('3', 'QRIS', 'QR Code');
INSERT INTO `tbl_metode_transaksi` (`id_metode_transaksi`, `kode_metode`, `nama_metode`) VALUES ('4', 'AUTO', 'Otomatis Sistem');

DROP TABLE IF EXISTS `tbl_mutasi`;
CREATE TABLE `tbl_mutasi` (
  `id_mutasi` int NOT NULL AUTO_INCREMENT,
  `id_nasabah` int NOT NULL,
  `id_transaksi` int DEFAULT NULL,
  `jenis_mutasi` enum('debit','kredit') NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `saldo_tersedia` decimal(15,2) NOT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mutasi`),
  KEY `id_nasabah` (`id_nasabah`),
  KEY `id_transaksi` (`id_transaksi`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_mutasi_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE CASCADE,
  CONSTRAINT `fk_mutasi_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `tbl_transaksi` (`id_transaksi`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_nasabah`;
CREATE TABLE `tbl_nasabah` (
  `id_nasabah` int NOT NULL AUTO_INCREMENT,
  `nisn` varchar(20) NOT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `nama_nasabah` varchar(100) NOT NULL,
  `nama_ibu_kandung` varchar(100) NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `jenjang_pendidikan` enum('PAUD','TK','SD','SMP','SMA','SMK') DEFAULT NULL,
  `kelas` enum('X','XI','XII') DEFAULT NULL,
  `id_jurusan` int DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `pin_transaksi` varchar(255) DEFAULT NULL,
  `saldo` decimal(15,2) NOT NULL DEFAULT '25000.00',
  `telepon` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat` text,
  `status_nasabah` enum('aktif','nonaktif') DEFAULT 'aktif',
  `is_locked` tinyint(1) DEFAULT '0',
  `pin_failed_attempts` int DEFAULT '0',
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_nasabah`),
  UNIQUE KEY `nisn` (`nisn`),
  UNIQUE KEY `nik` (`nik`),
  KEY `id_jurusan` (`id_jurusan`),
  CONSTRAINT `fk_nasabah_jurusan` FOREIGN KEY (`id_jurusan`) REFERENCES `tbl_jurusan` (`id_jurusan`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_nasabah` (`id_nasabah`, `nisn`, `nik`, `nama_nasabah`, `nama_ibu_kandung`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `jenjang_pendidikan`, `kelas`, `id_jurusan`, `password`, `pin_transaksi`, `saldo`, `telepon`, `email`, `alamat`, `status_nasabah`, `is_locked`, `pin_failed_attempts`, `remember_token`, `last_login`, `created_at`, `updated_at`) VALUES ('1', '002', '1871125202020002', 'Almeera Salsabila', 'Nurhayati', 'Jakarta', '2010-10-06', 'L', 'SMK', 'XI', '1', '$2y$10$e66OA1p4I2av2eaOsFGbhOt1TJZPABA1xPM.6RFKitF9Fz4Sh2yCq', NULL, '0.00', '083140440661', 'naurasalsabila@ptik4.sch.id', 'Jl. Endro Suratmin No.33, Way Dadi, Kec. Sukarame, Kota Bandar Lampung', 'aktif', '0', '0', NULL, '2026-06-12 17:24:43', '2026-06-03 21:16:08', '2026-06-12 17:24:43');
INSERT INTO `tbl_nasabah` (`id_nasabah`, `nisn`, `nik`, `nama_nasabah`, `nama_ibu_kandung`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `jenjang_pendidikan`, `kelas`, `id_jurusan`, `password`, `pin_transaksi`, `saldo`, `telepon`, `email`, `alamat`, `status_nasabah`, `is_locked`, `pin_failed_attempts`, `remember_token`, `last_login`, `created_at`, `updated_at`) VALUES ('5', '004', '1871125202020008', 'Aldi Nugraha', 'Suminah', NULL, NULL, NULL, NULL, 'XI', '4', '$2y$10$0tlHKNzlqIJcRORHJ/K3ne6maDajBralA5nU7tW62zzBknUIqmkmG', NULL, '0.00', NULL, NULL, NULL, 'aktif', '0', '0', NULL, '2026-06-12 17:24:55', '2026-06-10 09:26:08', '2026-06-12 17:24:55');

DROP TABLE IF EXISTS `tbl_notifikasi`;
CREATE TABLE `tbl_notifikasi` (
  `id_notifikasi` int NOT NULL AUTO_INCREMENT,
  `id_nasabah` int NOT NULL,
  `judul` varchar(100) DEFAULT NULL,
  `pesan` text,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notifikasi`),
  KEY `id_nasabah` (`id_nasabah`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `fk_notif_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_pengaturan`;
CREATE TABLE `tbl_pengaturan` (
  `id_pengaturan` int NOT NULL AUTO_INCREMENT,
  `nama_aplikasi` varchar(100) NOT NULL DEFAULT 'IBIS',
  `subjudul` varchar(150) DEFAULT 'Internet Banking Sekolah',
  `tagline_1` varchar(150) DEFAULT 'Cerdas Finansial,',
  `tagline_2` varchar(150) DEFAULT 'Hebat di Masa Depan.',
  `versi_aplikasi` varchar(20) DEFAULT '3.0',
  `developed_by` varchar(150) NOT NULL DEFAULT 'Pustik SMK PGRI 4 Bandar Lampung',
  `nama_sekolah` varchar(100) DEFAULT NULL,
  `alamat_sekolah` text,
  `telp_sekolah` varchar(20) DEFAULT NULL,
  `whatsapp_admin` varchar(20) DEFAULT NULL,
  `email_sekolah` varchar(100) DEFAULT NULL,
  `logo_sekolah` varchar(255) DEFAULT NULL,
  `nama_kepala_sekolah` varchar(100) DEFAULT NULL,
  `nip_kepala_sekolah` varchar(50) DEFAULT NULL,
  `minimal_penarikan` decimal(15,2) DEFAULT '10000.00',
  `minimal_saldo_mengendap` decimal(15,2) DEFAULT '15000.00',
  `biaya_admin_default` decimal(15,2) DEFAULT '2500.00',
  `biaya_transfer_default` decimal(15,2) DEFAULT '1500.00',
  `format_nomor_transaksi` varchar(50) DEFAULT 'TRX/[YYYY]/[MM]/[ID]',
  `jam_operasional` varchar(100) DEFAULT 'Senin - Jumat, 07:30 - 15:00 WIB',
  `limit_transfer_harian` decimal(15,2) DEFAULT '50000.00',
  PRIMARY KEY (`id_pengaturan`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_pengaturan` (`id_pengaturan`, `nama_aplikasi`, `subjudul`, `tagline_1`, `tagline_2`, `versi_aplikasi`, `developed_by`, `nama_sekolah`, `alamat_sekolah`, `telp_sekolah`, `whatsapp_admin`, `email_sekolah`, `logo_sekolah`, `nama_kepala_sekolah`, `nip_kepala_sekolah`, `minimal_penarikan`, `minimal_saldo_mengendap`, `biaya_admin_default`, `biaya_transfer_default`, `format_nomor_transaksi`, `jam_operasional`, `limit_transfer_harian`) VALUES ('1', 'IBIS', 'Internet Banking Sekolah', 'Smart Money', 'Bright Financial Future.', '1.0', 'PTIK 4 Bandar Lampung', 'SMK PGRI 4 Bandar Lampung', 'Jl. Endro Suratmin No.33, Way Dadi, Kec. Sukarame, Kota Bandar Lampung, Lampung 35131', '(0721) 701220', '6282181971379', 'smkpgri4bl@gmail.com', NULL, 'Dra. Sofiyah', '9663737638300002', '10000.00', '15000.00', '3000.00', '1500.00', 'TRX/[YYYY]/[MM]/[ID]', 'Senin - Jumat (07:30 - 15:00 WIB)', '50000.00');

DROP TABLE IF EXISTS `tbl_riwayat_kelas`;
CREATE TABLE `tbl_riwayat_kelas` (
  `id_riwayat` int NOT NULL AUTO_INCREMENT,
  `id_nasabah` int NOT NULL,
  `id_tahun_ajaran` int NOT NULL,
  `kelas_saat_itu` enum('X','XI','XII') NOT NULL,
  `id_jurusan_saat_itu` int NOT NULL,
  `saldo_akhir_tahun` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id_riwayat`),
  KEY `fk_riwayat_nasabah` (`id_nasabah`),
  KEY `fk_riwayat_tahun` (`id_tahun_ajaran`),
  KEY `fk_riwayat_jurusan` (`id_jurusan_saat_itu`),
  CONSTRAINT `fk_riwayat_jurusan` FOREIGN KEY (`id_jurusan_saat_itu`) REFERENCES `tbl_jurusan` (`id_jurusan`) ON DELETE RESTRICT,
  CONSTRAINT `fk_riwayat_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE CASCADE,
  CONSTRAINT `fk_riwayat_tahun` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tbl_tahun_ajaran` (`id_tahun_ajaran`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_roles`;
CREATE TABLE `tbl_roles` (
  `id_role` int NOT NULL AUTO_INCREMENT,
  `nama_role` varchar(20) NOT NULL,
  `deskripsi` text,
  PRIMARY KEY (`id_role`),
  UNIQUE KEY `nama_role` (`nama_role`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_roles` (`id_role`, `nama_role`, `deskripsi`) VALUES ('1', 'admin', 'Administrator Utama (Kepsek/TU/Pembina)');
INSERT INTO `tbl_roles` (`id_role`, `nama_role`, `deskripsi`) VALUES ('2', 'operator', 'Petugas operasional harian bank (Teller)');

DROP TABLE IF EXISTS `tbl_tahun_ajaran`;
CREATE TABLE `tbl_tahun_ajaran` (
  `id_tahun_ajaran` int NOT NULL AUTO_INCREMENT,
  `tahun_ajaran` varchar(9) NOT NULL,
  `status_aktif` enum('aktif','nonaktif') DEFAULT 'nonaktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_tahun_ajaran`),
  UNIQUE KEY `tahun_ajaran` (`tahun_ajaran`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_tahun_ajaran` (`id_tahun_ajaran`, `tahun_ajaran`, `status_aktif`, `created_at`) VALUES ('1', '2025/2026', 'nonaktif', '2026-06-11 16:46:15');
INSERT INTO `tbl_tahun_ajaran` (`id_tahun_ajaran`, `tahun_ajaran`, `status_aktif`, `created_at`) VALUES ('2', '2026/2027', 'aktif', '2026-06-11 16:46:15');
INSERT INTO `tbl_tahun_ajaran` (`id_tahun_ajaran`, `tahun_ajaran`, `status_aktif`, `created_at`) VALUES ('3', '2027/2028', 'nonaktif', '2026-06-11 16:46:15');
INSERT INTO `tbl_tahun_ajaran` (`id_tahun_ajaran`, `tahun_ajaran`, `status_aktif`, `created_at`) VALUES ('4', '2028/2029', 'nonaktif', '2026-06-11 16:46:15');
INSERT INTO `tbl_tahun_ajaran` (`id_tahun_ajaran`, `tahun_ajaran`, `status_aktif`, `created_at`) VALUES ('5', '2029/2030', 'nonaktif', '2026-06-11 16:46:15');

DROP TABLE IF EXISTS `tbl_target_tabungan`;
CREATE TABLE `tbl_target_tabungan` (
  `id_target` int NOT NULL AUTO_INCREMENT,
  `id_nasabah` int NOT NULL,
  `nama_target` varchar(100) NOT NULL,
  `nominal_target` decimal(15,2) NOT NULL,
  `status_target` enum('active','achieved','canceled') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_target`),
  KEY `id_nasabah` (`id_nasabah`),
  CONSTRAINT `fk_target_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_transaksi`;
CREATE TABLE `tbl_transaksi` (
  `id_transaksi` int NOT NULL AUTO_INCREMENT,
  `kode_transaksi` varchar(50) NOT NULL,
  `nomor_referensi_bank` varchar(100) DEFAULT NULL,
  `id_nasabah` int NOT NULL,
  `id_nasabah_penerima` int DEFAULT NULL,
  `id_jenis_transaksi` int NOT NULL,
  `id_metode_transaksi` int NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `biaya_admin` decimal(15,2) DEFAULT '0.00',
  `saldo_awal` decimal(15,2) NOT NULL,
  `saldo_akhir` decimal(15,2) NOT NULL,
  `keterangan` text,
  `tanggal_transaksi` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_petugas` int DEFAULT NULL,
  `status_approval` enum('pending','approved','rejected') DEFAULT 'approved',
  `id_supervisor` int DEFAULT NULL,
  `catatan_approval` text,
  `id_jurnal` int DEFAULT NULL,
  PRIMARY KEY (`id_transaksi`),
  UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  UNIQUE KEY `nomor_referensi_bank` (`nomor_referensi_bank`),
  KEY `id_nasabah` (`id_nasabah`),
  KEY `id_petugas` (`id_petugas`),
  KEY `id_supervisor` (`id_supervisor`),
  KEY `id_nasabah_penerima` (`id_nasabah_penerima`),
  KEY `id_jurnal` (`id_jurnal`),
  KEY `id_jenis_transaksi` (`id_jenis_transaksi`),
  KEY `id_metode_transaksi` (`id_metode_transaksi`),
  KEY `tanggal_transaksi` (`tanggal_transaksi`),
  CONSTRAINT `fk_transaksi_jenis` FOREIGN KEY (`id_jenis_transaksi`) REFERENCES `tbl_jenis_transaksi` (`id_jenis_transaksi`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_jurnal` FOREIGN KEY (`id_jurnal`) REFERENCES `tbl_jurnal_kas` (`id_jurnal`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transaksi_metode` FOREIGN KEY (`id_metode_transaksi`) REFERENCES `tbl_metode_transaksi` (`id_metode_transaksi`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transaksi_penerima` FOREIGN KEY (`id_nasabah_penerima`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transaksi_petugas` FOREIGN KEY (`id_petugas`) REFERENCES `tbl_users` (`id_user`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transaksi_supervisor` FOREIGN KEY (`id_supervisor`) REFERENCES `tbl_users` (`id_user`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_transaksi_infaq`;
CREATE TABLE `tbl_transaksi_infaq` (
  `id_infaq` int NOT NULL AUTO_INCREMENT,
  `kode_infaq` varchar(50) NOT NULL,
  `id_nasabah` int DEFAULT NULL,
  `jenis_infaq` enum('umum','khusus') NOT NULL,
  `nominal_infaq` decimal(15,2) NOT NULL,
  `keterangan` text,
  `tanggal_infaq` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_petugas` int DEFAULT NULL,
  `id_jurnal` int DEFAULT NULL,
  PRIMARY KEY (`id_infaq`),
  UNIQUE KEY `kode_infaq` (`kode_infaq`),
  KEY `id_nasabah` (`id_nasabah`),
  KEY `id_petugas` (`id_petugas`),
  KEY `id_jurnal` (`id_jurnal`),
  CONSTRAINT `fk_infaq_jurnal` FOREIGN KEY (`id_jurnal`) REFERENCES `tbl_jurnal_kas` (`id_jurnal`) ON DELETE RESTRICT,
  CONSTRAINT `fk_infaq_nasabah` FOREIGN KEY (`id_nasabah`) REFERENCES `tbl_nasabah` (`id_nasabah`) ON DELETE RESTRICT,
  CONSTRAINT `fk_infaq_petugas` FOREIGN KEY (`id_petugas`) REFERENCES `tbl_users` (`id_user`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


DROP TABLE IF EXISTS `tbl_users`;
CREATE TABLE `tbl_users` (
  `id_user` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `id_role` int NOT NULL,
  `status_akun` enum('aktif','nonaktif') DEFAULT 'aktif',
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  KEY `id_role` (`id_role`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`id_role`) REFERENCES `tbl_roles` (`id_role`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `tbl_users` (`id_user`, `username`, `password`, `nama_lengkap`, `id_role`, `status_akun`, `remember_token`, `last_login`, `created_at`) VALUES ('1', '000', '$2y$10$8GqEC//1h2wNsoVm5n0sR.ShFuiUrAJSkQfKdJLpl5dU3mNT0YAHy', 'Superadmin', '1', 'aktif', NULL, '2026-06-12 17:29:19', '2026-06-03 20:54:55');
INSERT INTO `tbl_users` (`id_user`, `username`, `password`, `nama_lengkap`, `id_role`, `status_akun`, `remember_token`, `last_login`, `created_at`) VALUES ('2', '001', '$2y$10$8GqEC//1h2wNsoVm5n0sR.ShFuiUrAJSkQfKdJLpl5dU3mNT0YAHy', 'Muhammad Rizal', '2', 'aktif', NULL, '2026-06-12 17:29:08', '2026-06-03 20:59:10');

SET FOREIGN_KEY_CHECKS=1;
