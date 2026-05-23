<?php
// proses_waka_sp.php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$aksi       = $_GET['aksi'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$user_id    = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../login.php");
    exit();
}

// 1. Aksi Waka: Terbitkan SP (langsung approved oleh Waka)
// PERBAIKAN: Nama aksi disesuaikan menjadi 'terbit_sp' agar cocok dengan tombol di index.php
if ($aksi === 'terbit_sp') {
    $level = (int)($_GET['level'] ?? 0);
    if ($level < 1 || $level > 3) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Level SP tidak valid.'];
        header("Location: ../index.php");
        exit();
    }

    $conn->beginTransaction();
    // Update status SP dan probation siswa
    $conn->prepare("UPDATE students SET status_sp = ?, is_probation = 1, probation_end = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?")
         ->execute([$level, $student_id]);

    // PERBAIKAN: Sertakan alasan_sp agar tidak melanggar constraint NOT NULL (jika kolom NOT NULL)
    $alasan = "Diterbitkan SP $level oleh Waka Kesiswaan karena akumulasi pelanggaran Zona Merah.";
    $conn->prepare("INSERT INTO sp_records (student_id, tingkat_sp, alasan_sp, is_approved, diterbitkan_oleh) VALUES (?, ?, ?, 1, ?)")
         ->execute([$student_id, $level, $alasan, $user_id]);

    $conn->commit();
    $_SESSION['notif'] = ['type' => 'success', 'message' => "✅ Surat Peringatan $level (SP $level) berhasil diterbitkan!"];
}

// 2. Aksi Kepsek: Setujui SP yang diajukan (is_approved = 0 → 1)
elseif ($aksi === 'approve_sp') {
    $sp_id = $_GET['sp_id'] ?? null;
    $level = $_GET['level'] ?? 0;

    if (!$sp_id) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ ID SP tidak ditemukan.'];
        header("Location: ../index.php");
        exit();
    }

    $conn->beginTransaction();
    $conn->prepare("UPDATE students SET status_sp = ?, is_probation = 1, probation_end = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?")
         ->execute([$level, $student_id]);
    $conn->prepare("UPDATE sp_records SET is_approved = 1 WHERE id = ?")
         ->execute([$sp_id]);
    $conn->commit();

    $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Surat Peringatan berhasil disetujui!'];
}

// 3. Eksekusi Drop Out (DO)
elseif ($aksi === 'drop_out') {
    $conn->beginTransaction();
    $conn->prepare("UPDATE students SET level_eskalasi = 'drop_out', status_warna = 'merah' WHERE id = ?")
         ->execute([$student_id]);

    $alasan_do = "Siswa dikeluarkan (Drop Out) setelah melewati batas SP 3.";
    $conn->prepare("INSERT INTO sp_records (student_id, tingkat_sp, alasan_sp, is_approved, diterbitkan_oleh) VALUES (?, 4, ?, 1, ?)")
         ->execute([$student_id, $alasan_do, $user_id]);
    $conn->commit();

    $_SESSION['notif'] = ['type' => 'error', 'message' => '🚨 Status siswa telah diubah menjadi Drop Out (Dikeluarkan).'];
}

else {
    $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Aksi tidak dikenali.'];
}

header("Location: ../index.php");
exit();
