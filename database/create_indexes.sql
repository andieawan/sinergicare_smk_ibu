-- ============================================
-- Database INDEX Creation Script
-- SinergiCare SMK v2.0
-- ============================================
-- Jalankan script ini di MySQL/phpMyAdmin untuk membuat INDEX
-- yang mengoptimalkan performa query di index.php dan cetak_administrasi.php

-- ============================================
-- 1. INDEX untuk TABLE: students
-- ============================================
-- Kolom status_warna paling sering diquery untuk GROUP BY dan WHERE
ALTER TABLE `students` ADD INDEX `idx_status_warna` (`status_warna`);

-- Kolom class_id untuk JOIN dengan classes
ALTER TABLE `students` ADD INDEX `idx_class_id` (`class_id`);

-- Combined index untuk query "WHERE status_warna IN (...) AND class_id = ?"
ALTER TABLE `students` ADD INDEX `idx_status_class` (`status_warna`, `class_id`);

-- Kolom nama untuk search functionality
ALTER TABLE `students` ADD INDEX `idx_nama` (`nama`);

-- Kolom nisn untuk lookup unik
ALTER TABLE `students` ADD INDEX `idx_nisn` (`nisn`);


-- ============================================
-- 2. INDEX untuk TABLE: incidents
-- ============================================
-- Foreign key untuk JOIN dengan students
ALTER TABLE `incidents` ADD INDEX `idx_student_id` (`student_id`);

-- Foreign key untuk JOIN dengan violation_categories
ALTER TABLE `incidents` ADD INDEX `idx_category_id` (`category_id`);

-- Untuk ORDER BY di query "5 insiden terbaru"
ALTER TABLE `incidents` ADD INDEX `idx_created_at` (`created_at`);


-- ============================================
-- 3. INDEX untuk TABLE: journals
-- ============================================
-- Query riwayat insiden per siswa
ALTER TABLE `journals` ADD INDEX `idx_student_id` (`student_id`);

-- Query jurnal by guru/user yang membuat
ALTER TABLE `journals` ADD INDEX `idx_user_id` (`user_id`);

-- JOIN dengan violation_categories
ALTER TABLE `journals` ADD INDEX `idx_category_id` (`category_id`);

-- ORDER BY tanggal untuk riwayat terbaru
ALTER TABLE `journals` ADD INDEX `idx_created_at` (`created_at`);

-- Combined: ambil riwayat siswa, sort by date (sangat efisien)
ALTER TABLE `journals` ADD INDEX `idx_student_created` (`student_id`, `created_at`);


-- ============================================
-- 4. INDEX untuk TABLE: consequences
-- ============================================
-- Query tugas by siswa
ALTER TABLE `consequences` ADD INDEX `idx_student_id` (`student_id`);

-- Query tugas yang dibuat oleh guru BK tertentu
ALTER TABLE `consequences` ADD INDEX `idx_bk_id` (`bk_id`);

-- Filter tugas pending vs selesai
ALTER TABLE `consequences` ADD INDEX `idx_status` (`status_tugas`);

-- Query tugas yang harus dikerjakan oleh guru pembimbing
ALTER TABLE `consequences` ADD INDEX `idx_penanggung_jawab` (`penanggung_jawab`);


-- ============================================
-- 5. INDEX untuk TABLE: classes
-- ============================================
-- Filter/search kelas by nama
ALTER TABLE `classes` ADD INDEX `idx_nama_kelas` (`nama_kelas`);


-- ============================================
-- 6. INDEX untuk TABLE: staf_sekolah
-- ============================================
-- Login query "SELECT * FROM staf_sekolah WHERE username = ?"
ALTER TABLE `staf_sekolah` ADD INDEX `idx_username` (`username`);

-- Email lookup
ALTER TABLE `staf_sekolah` ADD INDEX `idx_email` (`email`);

-- Filter guru by roles
ALTER TABLE `staf_sekolah` ADD INDEX `idx_roles` (`roles`);


-- ============================================
-- 7. INDEX untuk TABLE: violation_categories
-- ============================================
-- Search kategori by nama
ALTER TABLE `violation_categories` ADD INDEX `idx_nama_kejadian` (`nama_kejadian`);

-- Filter by severity/bobot
ALTER TABLE `violation_categories` ADD INDEX `idx_bobot_risiko` (`bobot_risiko`);


-- ============================================
-- VERIFIKASI INDEX YANG SUDAH DIBUAT
-- ============================================
-- Jalankan query di bawah untuk verifikasi:

-- Lihat semua index di students
-- SHOW INDEX FROM students;

-- Lihat struktur lengkap dengan index
-- SHOW CREATE TABLE students\G

-- Gunakan EXPLAIN untuk lihat apakah query menggunakan index
-- EXPLAIN SELECT status_warna, COUNT(*) FROM students GROUP BY status_warna;
-- EXPLAIN SELECT * FROM students WHERE status_warna = 'merah' AND class_id = 1;


-- ============================================
-- QUERY YANG DIOPTIMASI OLEH INDEX INI
-- ============================================

-- 1. Dashboard Statistics (index.php:17)
-- SELECT status_warna, COUNT(*) as jumlah FROM students GROUP BY status_warna;
-- ✅ Optimized by: idx_status_warna

-- 2. Ambil 5 insiden terbaru (index.php:56-61)
-- SELECT i.*, s.nama, c.nama_kelas, vc.nama_kejadian
-- FROM incidents i
-- JOIN students s ON i.student_id = s.id
-- LEFT JOIN classes c ON s.class_id = c.id
-- JOIN violation_categories vc ON i.category_id = vc.id
-- ORDER BY i.id DESC LIMIT 5;
-- ✅ Optimized by: idx_student_id, idx_category_id, idx_created_at

-- 3. Tindakan Segera BK (cetak_administrasi.php:246)
-- SELECT s.*, c.nama_kelas FROM students s
-- LEFT JOIN classes c ON s.class_id = c.id
-- WHERE s.status_warna IN ('kuning', 'merah');
-- ✅ Optimized by: idx_status_warna, idx_class_id

-- 4. RDR Laporan (cetak_administrasi.php:550)
-- SELECT s.*, c.nama_kelas FROM students s
-- LEFT JOIN classes c ON s.class_id = c.id
-- WHERE s.status_warna IN ('kuning', 'merah')
-- ORDER BY s.status_warna DESC;
-- ✅ Optimized by: idx_status_class

-- 5. Tugas BK Pending (cetak_administrasi.php:431)
-- SELECT c.*, s.nama FROM consequences c
-- JOIN students s ON c.student_id = s.id
-- WHERE c.penanggung_jawab = ? AND c.status_tugas = 'pending';
-- ✅ Optimized by: idx_penanggung_jawab, idx_status

-- 6. Login Query (proses_login.php:20)
-- SELECT * FROM staf_sekolah WHERE username = ?
-- ✅ Optimized by: idx_username

-- ============================================
-- END OF INDEX SCRIPT
-- ============================================
