<?php
// config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Menentukan lokasi file rahasia penyimpanan kredensial database
define('DB_CONFIG_FILE', __DIR__ . '/db_credentials.json');

$conn = null;

// Cek apakah file konfigurasi hasil setup sudah terbentuk
if (!file_exists(DB_CONFIG_FILE)) {
    // Jika belum diinstal dan user tidak sedang di halaman setup, paksa alihkan ke setup.php
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        header("Location: setup.php");
        exit();
    }
} else {
    // Jika file konfigurasi ada, baca datanya untuk mengaktifkan koneksi PDO
    $db_info = json_decode(file_get_contents(DB_CONFIG_FILE), true);
    
    $host     = $db_info['host'];
    $db_name  = $db_info['db_name'];
    $username = $db_info['username'];
    $password = $db_info['password'];

    try {
        $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Jika koneksi gagal (misal database terhapus manual), izinkan masuk ke setup.php untuk perbaikan
        if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
            die("Gagal menyambung ke database SinergiCare. Hubungi Admin atau hapus file db_credentials.json untuk setup ulang. Eror: " . $e->getMessage());
        }
    }
}
// Bug kurung kurawal ekstra di bagian bawah sudah dibersihkan di sini