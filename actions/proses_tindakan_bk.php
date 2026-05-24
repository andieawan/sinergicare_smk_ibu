<?php
// proses_tindakan_bk.php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id       = $_POST['student_id']      ?? '';
    $deskripsi_tugas  = $_POST['deskripsi_tugas'] ?? '';
    $penanggung_jawab = $_POST['penanggung_jawab'] ?? '';

    $bk_id = $_SESSION['user_id'] ?? null;

    if (!$bk_id) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Anda harus login terlebih dahulu!'];
        header("Location: ../index.php");
        exit();
    }

    try {
        // ======================================================================
        // BUG FIX: Kolom 'bk_id' TIDAK ADA di tabel consequences (lihat setup.php).
        // Kolom yang ada: student_id, deskripsi_tugas, penanggung_jawab, status_tugas.
        // Query lama menyertakan bk_id sehingga selalu error PDOException.
        // ======================================================================
        $stmt = $conn->prepare("
            INSERT INTO consequences (student_id, deskripsi_tugas, status_tugas, penanggung_jawab)
            VALUES (:student_id, :deskripsi_tugas, 'pending', :penanggung_jawab)
        ");

        $stmt->execute([
            'student_id'       => $student_id,
            'deskripsi_tugas'  => $deskripsi_tugas,
            'penanggung_jawab' => $penanggung_jawab,
        ]);

        $_SESSION['notif'] = ['type' => 'success', 'message' => '📋 Tugas konsekuensi disiplin berhasil diterbitkan untuk siswa!'];

    } catch (PDOException $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menyimpan data: ' . $e->getMessage()];
    }

    header("Location: ../index.php");
    exit();
}
?>
