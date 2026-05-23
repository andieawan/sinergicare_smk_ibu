<?php
// proses_login.php
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder actions/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn !== null) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $_SESSION['error_login'] = '⚠️ Username dan Password wajib diisi!';
        // PERBAIKAN PATH: Tambahkan ../ agar kembali ke root utama
        header("Location: ../login.php");
        exit();
    }

    try {
        // Ambil data staf berdasarkan username
        $stmt = $conn->prepare("SELECT * FROM staf_sekolah WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Pengecekan password - Support untuk hashed dan plaintext password (legacy)
            $password_valid = false;
            
            if (password_verify($password, $user['password'])) {
                // Password di-hash dengan password_hash()
                $password_valid = true;
            } elseif ($password === $user['password']) {
                // Password plaintext (legacy support untuk existing password)
                $password_valid = true;
            }
            
            if ($password_valid) {
                // --- INTI ADAPTASI MULTI-ROLE SINERGICARE v2 ---
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_nama'] = $user['nama'];
                
                // Membungkus string role dari database menjadi array agar dibaca oleh in_array() di index.php
                $_SESSION['user_roles'] = [ $user['roles'] ]; 
                
                // Bersihkan error login jika ada
                unset($_SESSION['error_login']);

                // PERBAIKAN PATH: Tambahkan ../ agar kembali ke root utama
                header("Location: ../index.php");
                exit();
            } else {
                $_SESSION['error_login'] = '⚠️ Kata sandi yang Anda masukkan salah!';
            }
        } else {
            $_SESSION['error_login'] = '⚠️ Username tidak terdaftar di sistem!';
        }
    } catch (PDOException $e) {
        $_SESSION['error_login'] = '⚠️ Gagal terhubung ke database: ' . $e->getMessage();
    }
} else {
    $_SESSION['error_login'] = '⚠️ Metode request data tidak sah!';
}

// Jika gagal login, kembalikan ke halaman login semula
// PERBAIKAN PATH: Tambahkan ../ agar kembali ke root utama
header("Location: ../login.php");
exit();
?>