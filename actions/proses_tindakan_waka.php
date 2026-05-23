<?php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Keamanan: Pastikan hanya Waka Kesiswaan atau Super Admin yang bisa mengakses
if (!isset($_SESSION['user_id']) || (!in_array('waka_kesiswaan', $_SESSION['user_roles']) && !in_array('super_admin', $_SESSION['user_roles']))) {
    $_SESSION['notif'] = [
        'type' => 'error',
        'message' => '⚠️ Anda tidak memiliki otoritas Waka Kesiswaan!'
    ];
    header("Location: index.php");
    exit();
}

if (isset($_GET['student_id']) && $conn !== null) {
    $student_id = $_GET['student_id'];

    try {
        // Turunkan status warna siswa kembali ke 'hijau' setelah tindakan resmi disahkan oleh Waka
        $stmt = $conn->prepare("UPDATE students SET status_warna = 'hijau' WHERE id = :id");
        $stmt->execute(['id' => $student_id]);

        $_SESSION['notif'] = [
            'type' => 'success',
            'message' => '🛡️ Konsekuensi skala besar berhasil disahkan. Status karakter siswa telah diperbarui.'
        ];
    } catch (PDOException $e) {
        $_SESSION['notif'] = [
            'type' => 'error',
            'message' => '⚠️ Gagal memproses data: ' . $e->getMessage()
        ];
    }
}

header("Location: index.php");
exit();