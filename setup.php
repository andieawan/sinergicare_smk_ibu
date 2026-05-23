<?php
// setup.php - DATABASE AUTO-INITIALIZER ENGINE FOR SINERGICARE
header("Content-Type: text/plain");

$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "sinergicare_smk";

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "====================================================\n";
    echo " ⚡ SINERGICARE v3.0 DATABASE SETUP WIZARD\n";
    echo "====================================================\n\n";

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✔ Database `$dbname` berhasil diverifikasi/dibuat.\n";

    $pdo->exec("USE `$dbname`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // PERBAIKAN: Tambahkan log_surat ke daftar tabel yang dibersihkan
    $tables = ['log_surat', 'sp_records', 'journals', 'incidents', 'consequences', 'user_roles', 'roles', 'students', 'classes', 'staf_sekolah', 'violation_categories'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    echo "✔ Pembersihan tabel usang selesai (Fresh Rebuild).\n\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // ====================================================================================
    // [STRUKTUR TABEL]
    // ====================================================================================
    $pdo->exec("CREATE TABLE `classes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_kelas` VARCHAR(50) NOT NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE `violation_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_kejadian` VARCHAR(255) NOT NULL,
        `bobot_risiko` VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE `staf_sekolah` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama` VARCHAR(150) NOT NULL,
        `email` VARCHAR(100) NULL,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `roles` VARCHAR(50) NOT NULL DEFAULT 'guru',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE `roles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_role` VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE `user_roles` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `role_id` INT NOT NULL,
        FOREIGN KEY (`user_id`) REFERENCES `staf_sekolah`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE `students` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nisn` VARCHAR(20) NOT NULL UNIQUE,
        `nama` VARCHAR(150) NOT NULL,
        `class_id` INT NULL,
        `status_warna` VARCHAR(20) NOT NULL DEFAULT 'hijau',
        `level_eskalasi` VARCHAR(50) NOT NULL DEFAULT 'teguran',
        `status_sp` INT NOT NULL DEFAULT 0,
        `is_probation` INT NOT NULL DEFAULT 0,
        `probation_end` DATE NULL,
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE `incidents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        `user_id` INT NULL,
        `catatan` TEXT NOT NULL,
        `lokasi_kejadian` VARCHAR(100) NULL,
        `tanggal_kejadian` DATE NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `violation_categories`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `staf_sekolah`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE `journals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        `catatan` TEXT NOT NULL,
        `lokasi_kejadian` VARCHAR(100) NULL,
        `tanggal_kejadian` DATE NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `staf_sekolah`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `violation_categories`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // PERBAIKAN: Tambahkan kolom bk_id dan completed_at yang dibutuhkan oleh action-action
    $pdo->exec("CREATE TABLE `consequences` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `bk_id` INT NULL,
        `deskripsi_tugas` TEXT NOT NULL,
        `penanggung_jawab` INT NOT NULL,
        `status_tugas` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `completed_at` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`bk_id`) REFERENCES `staf_sekolah`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`penanggung_jawab`) REFERENCES `staf_sekolah`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // PERBAIKAN: alasan_sp harus nullable karena proses_waka_sp.php tidak selalu mengisinya
    $pdo->exec("CREATE TABLE `sp_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `tingkat_sp` INT NOT NULL,
        `alasan_sp` TEXT NULL,
        `diterbitkan_oleh` INT NOT NULL,
        `is_approved` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`diterbitkan_oleh`) REFERENCES `staf_sekolah`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // PERBAIKAN: Tambahkan tabel log_surat yang dipakai oleh api/log_cetak.php
    $pdo->exec("CREATE TABLE `log_surat` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `tipe_surat` VARCHAR(50) NOT NULL,
        `tanggal_surat` DATE NULL,
        `jam_surat` VARCHAR(10) NULL,
        `dibuat_oleh` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`dibuat_oleh`) REFERENCES `staf_sekolah`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    echo "✔ Seluruh struktur tabel berhasil di-compile secara berjenjang.\n\n";

    // ====================================================================================
    // DATA SEEDING
    // ====================================================================================
    echo "----------------------------------------------------\n";
    echo " ⚙️ MEMULAI PROSES IMPORT DATA AWAL (SEEDING)...\n";
    echo "----------------------------------------------------\n";

    $classes = [
        ['5', 'X DKV 1'], ['4', 'XI BD 1'], ['1', 'XI DKV 1'], ['2', 'XI DKV 2'], ['3', 'XI DKV 3']
    ];
    $stmt = $pdo->prepare("INSERT INTO `classes` (id, nama_kelas) VALUES (?, ?)");
    foreach ($classes as $c) $stmt->execute($c);

    $v_categories = [
        ['1', 'Terlambat masuk lingkungan sekolah', 'ringan'],
        ['2', 'Atribut seragam tidak lengkap atau menyimpang', 'ringan'],
        ['3', 'Membolos atau keluar kelas tanpa izin saat jam KBM', 'sedang'],
        ['4', 'Membawa atau bermain HP saat ujian tanpa instruksi', 'sedang'],
        ['5', 'Terlibat perkelahian atau tawuran pelajar', 'berat'],
        ['6', 'Merusak aset atau fasilitas utama instansi sekolah', 'berat']
    ];
    $stmt = $pdo->prepare("INSERT INTO `violation_categories` (id, nama_kejadian, bobot_risiko) VALUES (?, ?, ?)");
    foreach ($v_categories as $vc) $stmt->execute($vc);

    $staf = [
        ['1', 'Administrator Utama', 'super@smk.sch.id', 'admin', 'admin123', 'super_admin', '2026-05-22 18:31:34'],
        ['2', 'Budi Santoso, M.Pd', 'budisbk@smk.sch.id', 'budi', 'budi', 'bk', '2026-05-22 20:30:13'],
        ['3', 'Guru Piket', 'guru@smk.sch.id', 'guru', 'guru', 'guru', '2026-05-22 21:31:51']
    ];
    $stmt = $pdo->prepare("INSERT INTO `staf_sekolah` (id, nama, email, username, password, roles, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($staf as $s) $stmt->execute($s);

    $roles = [['1', 'admin'], ['3', 'bk'], ['2', 'guru'], ['5', 'super_admin'], ['4', 'yayasan']];
    $stmt = $pdo->prepare("INSERT INTO `roles` (id, nama_role) VALUES (?, ?)");
    foreach ($roles as $r) $stmt->execute($r);

    $u_roles = [['1', '1', '5'], ['2', '2', '3'], ['3', '3', '2']];
    $stmt = $pdo->prepare("INSERT INTO `user_roles` (id, user_id, role_id) VALUES (?, ?, ?)");
    foreach ($u_roles as $ur) $stmt->execute($ur);

    $students = [
        ['1', '202601', 'Bambang Tole', '1', 'kuning', 'konseling', '0', '0', null],
        ['2', '202602', 'Bambang Paas', '2', 'hijau', 'teguran', '0', '0', null],
        ['3', '202603', 'Bambang Pamas', '3', 'merah', 'teguran', '1', '0', null],
        ['4', '202670', 'Adi Pratama', '1', 'hijau', 'teguran', '0', '0', null]
    ];
    $stmt = $pdo->prepare("INSERT INTO `students` (id, nisn, nama, class_id, status_warna, level_eskalasi, status_sp, is_probation, probation_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($students as $st) $stmt->execute($st);

    $incidents = [
        ['1', '3', '1', null, 'Terlambat', '2026-05-22 21:20:55'],
        ['2', '3', '5', null, 'Berkelahi dengan teman sekelas', '2026-05-22 21:22:02'],
        ['3', '2', '5', null, 'Terlibat tawuran di luar gerbang', '2026-05-22 21:28:38'],
        ['4', '4', '1', '3', 'Telat masuk kelas', '2026-05-22 22:00:48']
    ];
    $stmt = $pdo->prepare("INSERT INTO `incidents` (id, student_id, category_id, user_id, catatan, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($incidents as $inc) $stmt->execute($inc);

    $journals = [
        ['1', '1', '1', '1', 'Terlambat dikarenakan bangun siang', 'Lingkungan Sekolah', '2026-05-22', '2026-05-22 21:05:27'],
        ['2', '1', '1', '2', 'Atribut tidak lengkap', 'Lingkungan Sekolah', '2026-05-22', '2026-05-22 21:16:28'],
        ['3', '1', '1', '3', 'Bolos jam pelajaran ke-3', 'Lingkungan Sekolah', '2026-05-22', '2026-05-22 21:17:22']
    ];
    $stmt = $pdo->prepare("INSERT INTO `journals` (id, student_id, user_id, category_id, catatan, lokasi_kejadian, tanggal_kejadian, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($journals as $j) $stmt->execute($j);

    // PERBAIKAN: Sertakan alasan_sp agar sesuai skema yang digunakan setup.php sebelumnya
    $sp_records = [
        ['1', '3', '1', 'Akumulasi kasus kritis pada Zona Merah.', '1', '2026-05-22 22:37:20', '0']
    ];
    $stmt = $pdo->prepare("INSERT INTO `sp_records` (id, student_id, tingkat_sp, alasan_sp, diterbitkan_oleh, created_at, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($sp_records as $sp) $stmt->execute($sp);

    echo "✔ Proses Seeding 100% Selesai!\n\n";
    echo "====================================================\n";
    echo " 🎉 SYSTEM IS READY FOR PRODUCTION!\n";
    echo " Silakan hapus file setup.php ini demi keamanan.\n";
    echo "====================================================\n";

} catch (PDOException $e) {
    echo "\n❌ [FATAL ERROR]: " . $e->getMessage() . "\n";
}
