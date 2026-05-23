<?php
// get_riwayat.php (VERSI SINKRON V2.1)
require_once '../config/config.php';
header('Content-Type: application/json');

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi: User wajib login
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if ($student_id > 0 && $conn !== null) {
    try {
        // Query diselaraskan dengan struktur incidents dan violation_categories
        $stmt = $conn->prepare("SELECT i.*, vc.nama_kejadian, vc.bobot_risiko 
                                FROM incidents i 
                                JOIN violation_categories vc ON i.category_id = vc.id 
                                WHERE i.student_id = ? 
                                ORDER BY i.id DESC");
        $stmt->execute([$student_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}