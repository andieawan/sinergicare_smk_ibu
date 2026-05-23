<?php
// proses_tindakan_waka.php
// PERBAIKAN PATH: Tambahkan ../ karena file berada di dalam folder actions/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi: Hanya Waka Kesiswaan atau Super Admin
if (!isset($_SESSION['user_id']) || !count(array_intersect(['waka_kesiswaan', 'super_admin'], $_SESSION['user_roles'] ?? [])) > 0) {
    $_SESSION['notif'] = [
        'type'    => 'error',
        'message' => '⚠️ Anda tidak memiliki otoritas Waka Kesiswaan!'
    ];
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['student_id']) && $conn !== null) {
    $student_id = (int)$_GET['student_id'];

    try {
        $stmt = $conn->prepare("UPDATE students SET status_warna = 'hijau' WHERE id = :id");
        $stmt->execute(['id' => $student_id]);

        $_SESSION['notif'] = [
            'type'    => 'success',
            'message' => '🛡️ Konsekuensi skala besar berhasil disahkan. Status karakter siswa telah diperbarui.'
        ];
    } catch (PDOException $e) {
        $_SESSION['notif'] = [
            'type'    => 'error',
            'message' => '⚠️ Gagal memproses data: ' . $e->getMessage()
        ];
    }
}

// PERBAIKAN PATH: Kembali ke root utama
header("Location: ../index.php");
exit();
