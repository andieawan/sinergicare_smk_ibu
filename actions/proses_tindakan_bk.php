<?php
// proses_tindakan_bk.php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id       = $_POST['student_id'] ?? '';
    $deskripsi_tugas  = trim($_POST['deskripsi_tugas'] ?? '');
    $penanggung_jawab = $_POST['penanggung_jawab'] ?? '';
    $bk_id            = $_SESSION['user_id'] ?? null;

    if (!$bk_id) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Anda harus login terlebih dahulu!'];
        header("Location: ../index.php");
        exit();
    }

    if (empty($student_id) || empty($deskripsi_tugas) || empty($penanggung_jawab)) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Data tugas tidak lengkap.'];
        header("Location: ../index.php");
        exit();
    }

    try {
        // PERBAIKAN: Tambahkan kolom bk_id yang ada di schema consequences
        $stmt = $conn->prepare("
            INSERT INTO consequences (student_id, bk_id, deskripsi_tugas, status_tugas, penanggung_jawab)
            VALUES (:student_id, :bk_id, :deskripsi_tugas, 'pending', :penanggung_jawab)
        ");
        $stmt->execute([
            'student_id'       => $student_id,
            'bk_id'            => $bk_id,
            'deskripsi_tugas'  => $deskripsi_tugas,
            'penanggung_jawab' => $penanggung_jawab,
        ]);

        $_SESSION['notif'] = ['type' => 'success', 'message' => '📋 Tugas konsekuensi disiplin berhasil diterbitkan!'];
    } catch (PDOException $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menyimpan data: ' . $e->getMessage()];
    }

    header("Location: ../index.php");
    exit();
}

// Jika bukan POST, kembalikan ke dashboard
header("Location: ../index.php");
exit();
