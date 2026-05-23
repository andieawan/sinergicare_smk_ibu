<?php
// get_siswa.php
require_once '../config/config.php';
header('Content-Type: application/json');

try {
    if (isset($_GET['kelas']) && !empty($_GET['kelas'])) {
        $stmt = $conn->prepare("
            SELECT s.id, s.nama, s.nisn, s.class_id, c.nama_kelas 
            FROM students s 
            JOIN classes c ON s.class_id = c.id 
            WHERE c.nama_kelas = :nama_kelas
            ORDER BY s.nama ASC
        ");
        $stmt->execute(['nama_kelas' => $_GET['kelas']]);
    } else {
        $stmt = $conn->query("
            SELECT s.id, s.nama, s.nisn, s.class_id, c.nama_kelas 
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            ORDER BY s.nama ASC
        ");
    }
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
} catch(PDOException $e) {
    echo json_encode([]);
}
?>