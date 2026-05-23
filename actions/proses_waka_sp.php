<?php
// proses_waka_sp.php
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder actions/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$aksi       = $_GET['aksi'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$user_id    = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // PERBAIKAN PATH: Kembali ke root utama jika belum login
    header("Location: ../login.php");
    exit();
}

// 1. Aksi Waka: Ajukan SP (Masih Status Pending/is_approved = 0)
if ($aksi === 'ajukan_sp') {
    $level = (int)$_GET['level'];
    // Simpan ke DB dengan is_approved = 0
    $stmt = $conn->prepare("INSERT INTO sp_records (student_id, tingkat_sp, is_approved, diterbitkan_oleh) VALUES (?, ?, 0, ?)");
    $stmt->execute([$student_id, $level, $user_id]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ SP diajukan. Menunggu persetujuan Kepala Sekolah.'];
}

// 2. Aksi Kepsek: Setujui SP (Set is_approved = 1 + Set Probation)
if ($aksi === 'approve_sp') {
    $sp_id = $_GET['sp_id'];
    $student_id = $_GET['student_id'];
    $level = $_GET['level'];

    $conn->beginTransaction();
    // Update status SP siswa
    $conn->prepare("UPDATE students SET status_sp = ?, is_probation = 1, probation_end = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?")->execute([$level, $student_id]);
    // Update persetujuan rekam SP
    $conn->prepare("UPDATE sp_records SET is_approved = 1 WHERE id = ?")->execute([$sp_id]);
    $conn->commit();

    $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Pengajuan Surat Peringatan (SP) berhasil disetujui!'];
}

// 3. Aksi Kepsek/Waka: Eksekusi Drop Out (DO) / Keluar
if ($aksi === 'drop_out') {
    $conn->beginTransaction();
    // Ubah status level eskalasi siswa menjadi drop_out
    $conn->prepare("UPDATE students SET level_eskalasi = 'drop_out', status_warna = 'merah' WHERE id = ?")->execute([$student_id]);
    // Catat log di sp_records sebagai penanda
    $conn->prepare("INSERT INTO sp_records (student_id, tingkat_sp, is_approved, diterbitkan_oleh) VALUES (?, 4, 1, ?)")->execute([$student_id, $user_id]);
    $conn->commit();

    $_SESSION['notif'] = ['type' => 'error', 'message' => '🚨 Status siswa telah diubah menjadi Drop Out (Dikeluarkan).'];
}

// PERBAIKAN PATH: Tambahkan ../ agar kembali ke root utama
header("Location: ../index.php");
exit();
?>