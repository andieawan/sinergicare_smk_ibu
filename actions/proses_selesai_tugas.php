<?php
// proses_selesai_tugas.php
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder actions/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['id']) && isset($_GET['student_id'])) {
    $consequence_id = $_GET['id'];
    $student_id     = $_GET['student_id'];

    try {
        $conn->beginTransaction();

        // 1. Update status tugas konsekuensi menjadi selesai
        $stmt_tugas = $conn->prepare("
            UPDATE consequences \n            SET status_tugas = 'selesai', completed_at = NOW() \n            WHERE id = :id
        ");
        $stmt_tugas->execute(['id' => $consequence_id]);

        // 2. Ambil status warna siswa saat ini
        $stmt_siswa = $conn->prepare("SELECT status_warna FROM students WHERE id = :student_id");
        $stmt_siswa->execute(['student_id' => $student_id]);
        $status_sekarang = $stmt_siswa->fetchColumn();

        // 3. Logika Pemulihan (Restoratif): Turunkan tingkatan warna
        $warna_baru = 'hijau';
        $level_baru = 'teguran';

        if ($status_sekarang === 'merah') {
            $warna_baru = 'kuning';
            $level_baru = 'konseling';
        } // Jika awalnya kuning, otomatis akan turun ke hijau (sesuai nilai default di atas)

        // 4. Update status siswa yang sudah bertobat
        $stmt_update_siswa = $conn->prepare("
            UPDATE students \n            SET status_warna = :warna, level_eskalasi = :level \n            WHERE id = :student_id
        ");
        $stmt_update_siswa->execute([
            'warna' => $warna_baru,
            'level' => $level_baru,
            'student_id' => $student_id
        ]);

        $conn->commit();

        $_SESSION['notif'] = [
            'type' => 'success',
            'message' => 'Konsekuensi selesai dilaksanakan. Status perilaku siswa berhasil dipulihkan!'
        ];

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['notif'] = [
            'type' => 'error',
            'message' => 'Gagal memperbarui data: ' . $e->getMessage()
        ];
    }
}

// PERBAIKAN PATH: Tambahkan ../ agar kembali ke root utama
header("Location: ../index.php");
exit();
?>