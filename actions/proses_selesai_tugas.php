<?php
// proses_selesai_tugas.php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['id']) && isset($_GET['student_id'])) {
    $consequence_id = (int)$_GET['id'];
    $student_id     = (int)$_GET['student_id'];

    try {
        $conn->beginTransaction();

        // 1. Update status tugas konsekuensi menjadi selesai
        // PERBAIKAN: completed_at kini ada di schema (kolom ditambahkan di setup.php)
        $conn->prepare("UPDATE consequences SET status_tugas = 'selesai', completed_at = NOW() WHERE id = :id")
             ->execute(['id' => $consequence_id]);

        // 2. Ambil status warna siswa saat ini
        $stmt_siswa = $conn->prepare("SELECT status_warna FROM students WHERE id = :student_id");
        $stmt_siswa->execute(['student_id' => $student_id]);
        $status_sekarang = $stmt_siswa->fetchColumn();

        // 3. Logika Pemulihan Restoratif
        $warna_baru = 'hijau';
        $level_baru = 'teguran';

        if ($status_sekarang === 'merah') {
            $warna_baru = 'kuning';
            $level_baru = 'konseling';
        }
        // Jika kuning → otomatis turun ke hijau (default di atas)

        // 4. Update status siswa
        $conn->prepare("UPDATE students SET status_warna = :warna, level_eskalasi = :level WHERE id = :student_id")
             ->execute(['warna' => $warna_baru, 'level' => $level_baru, 'student_id' => $student_id]);

        $conn->commit();

        $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Konsekuensi selesai. Status perilaku siswa berhasil dipulihkan!'];

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal memperbarui data: ' . $e->getMessage()];
    }
} else {
    $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Parameter tidak lengkap.'];
}

header("Location: ../index.php");
exit();
