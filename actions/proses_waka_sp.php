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

// ==================================================================================
// BUG FIX #1: Nama aksi di index.php adalah 'terbit_sp' bukan 'ajukan_sp'.
// Kedua nama ditangani agar backward-compatible.
// BUG FIX #2: Kolom 'alasan_sp' di tabel sp_records adalah NOT NULL.
// INSERT sebelumnya tidak menyertakan kolom ini sehingga query error.
// ==================================================================================

// 1. Aksi Waka: Terbitkan / Ajukan SP (is_approved = 0, menunggu Kepsek)
if ($aksi === 'terbit_sp' || $aksi === 'ajukan_sp') {
    $level  = (int)($_GET['level'] ?? 1);
    $alasan = "Diberikan penindakan disiplin bertahap Surat Peringatan Tingkat {$level} karena akumulasi kasus kritis pada Zona Merah.";

    $stmt = $conn->prepare("INSERT INTO sp_records (student_id, tingkat_sp, alasan_sp, is_approved, diterbitkan_oleh) VALUES (?, ?, ?, 0, ?)");
    $stmt->execute([$student_id, $level, $alasan, $user_id]);

    // Langsung update status_sp siswa agar tampilan index.php sinkron
    $conn->prepare("UPDATE students SET status_sp = ? WHERE id = ?")->execute([$level, $student_id]);

    $_SESSION['notif'] = ['type' => 'success', 'message' => "✅ Surat Peringatan {$level} berhasil diterbitkan."];
}

// 2. Aksi Kepsek: Setujui SP (set is_approved = 1 + aktifkan probation 30 hari)
if ($aksi === 'approve_sp') {
    $sp_id  = $_GET['sp_id']  ?? '';
    $student_id = $_GET['student_id'] ?? $student_id;
    $level  = (int)($_GET['level'] ?? 1);

    $conn->beginTransaction();
    $conn->prepare("UPDATE students SET status_sp = ?, is_probation = 1, probation_end = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?")->execute([$level, $student_id]);
    $conn->prepare("UPDATE sp_records SET is_approved = 1 WHERE id = ?")->execute([$sp_id]);
    $conn->commit();

    $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Pengajuan SP berhasil disetujui!'];
}

// 3. Aksi Kepsek/Waka: Eksekusi Drop Out (DO)
if ($aksi === 'drop_out') {
    $alasan_do = "Siswa dikeluarkan (Drop Out) setelah melewati semua tahapan Surat Peringatan.";

    $conn->beginTransaction();
    $conn->prepare("UPDATE students SET level_eskalasi = 'drop_out', status_warna = 'merah' WHERE id = ?")->execute([$student_id]);
    // BUG FIX: Sertakan alasan_sp agar tidak melanggar constraint NOT NULL
    $conn->prepare("INSERT INTO sp_records (student_id, tingkat_sp, alasan_sp, is_approved, diterbitkan_oleh) VALUES (?, 4, ?, 1, ?)")->execute([$student_id, $alasan_do, $user_id]);
    $conn->commit();

    $_SESSION['notif'] = ['type' => 'error', 'message' => '🚨 Status siswa telah diubah menjadi Drop Out (Dikeluarkan).'];
}

header("Location: ../index.php");
exit();
?>
