<?php
// api/log_cetak.php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'failed', 'message' => 'Unauthorized']);
    exit();
}

$student_id    = $_POST['student_id']   ?? '';
$tipe_surat    = $_POST['tipe_surat']   ?? '';
$tanggal_surat = $_POST['tanggal']      ?? '';
$jam_surat     = $_POST['jam']          ?? '';
$dibuat_oleh   = $_SESSION['user_id'];

if ($conn === null || empty($student_id)) {
    echo json_encode(['status' => 'failed', 'message' => 'No DB or missing student_id']);
    exit();
}

try {
    // ===========================================================================
    // BUG FIX: Tabel 'log_surat' tidak dibuat oleh setup.php sehingga INSERT
    // selalu gagal dengan "table not found". Solusi: buat tabel jika belum ada.
    // ===========================================================================
    $conn->exec("CREATE TABLE IF NOT EXISTS `log_surat` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `student_id`    INT NOT NULL,
        `tipe_surat`    VARCHAR(50) NOT NULL,
        `tanggal_surat` DATE NULL,
        `jam_surat`     TIME NULL,
        `dibuat_oleh`   INT NOT NULL,
        `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`)     ON DELETE CASCADE,
        FOREIGN KEY (`dibuat_oleh`) REFERENCES `staf_sekolah`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $stmt = $conn->prepare("INSERT INTO log_surat (student_id, tipe_surat, tanggal_surat, jam_surat, dibuat_oleh) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$student_id, $tipe_surat, $tanggal_surat ?: null, $jam_surat ?: null, $dibuat_oleh]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'failed', 'message' => $e->getMessage()]);
}
?>
