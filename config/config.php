<?php
// config/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_CONFIG_FILE', __DIR__ . '/../config/db_credentials.json');

// ==============================================================================
// BUG FIX: Redirect sebelumnya menggunakan path relatif "setup.php" yang
// hanya benar jika dipanggil dari root. Ketika dipanggil dari subfolder
// (actions/, api/, prints/), redirect menjadi "actions/setup.php" → 404.
// Solusi: gunakan path absolut berbasis DOCUMENT_ROOT.
// ==============================================================================
$setup_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') . '/database/setup.php';

// Alternatif lebih sederhana: arahkan selalu ke /database/setup.php dari root web
// Deteksi base URL dari posisi config.php (berada di /config/)
$base_path = str_replace('\\', '/', dirname(__DIR__));
$doc_root  = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$web_base  = ($doc_root && strpos($base_path, $doc_root) === 0)
    ? substr($base_path, strlen($doc_root))
    : '';

$conn = null;

if (!file_exists(DB_CONFIG_FILE)) {
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        header("Location: {$web_base}/database/setup.php");
        exit();
    }
} else {
    $db_info  = json_decode(file_get_contents(DB_CONFIG_FILE), true);

    $host     = $db_info['host']     ?? 'localhost';
    $db_name  = $db_info['db_name']  ?? '';
    $username = $db_info['username'] ?? 'root';
    $password = $db_info['password'] ?? '';

    try {
        $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE,          PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
            die("Gagal menyambung ke database SinergiCare. Hubungi Admin atau hapus file db_credentials.json untuk setup ulang. Error: " . htmlspecialchars($e->getMessage()));
        }
    }
}
?>
