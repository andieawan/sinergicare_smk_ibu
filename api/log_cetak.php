<?php
// log_cetak.php
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder api/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi Keamanan: Harus sudah login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $student_id   = $_POST['student_id'];
    $tipe_surat   = $_POST['tipe_surat'];
    $tanggal_surat = $_POST['tanggal'];
    $jam_surat     = $_POST['jam'];
    $dibuat_oleh   = $_SESSION['user_id'];

    if ($conn !== null && !empty($student_id)) {
        $stmt = $conn->prepare("INSERT INTO log_surat (student_id, tipe_surat, tanggal_surat, jam_surat, dibuat_oleh) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $tipe_surat, $tanggal_surat, $jam_surat, $dibuat_oleh]);
        
        echo json_encode(['status' => 'success']);
        exit();
    }
}
echo json_encode(['status' => 'failed']);
// Bug kurung kurawal penutup ekstra di bawah sini sudah dibersihkan