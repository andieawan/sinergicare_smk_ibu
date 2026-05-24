<?php
// actions/proses_profile_edit.php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $conn === null) {
    header("Location: ../edit_profile.php");
    exit();
}

// =========================================================================
// Ambil & sanitasi input
// =========================================================================
$nama             = trim(htmlspecialchars_decode(strip_tags($_POST['nama']          ?? '')));
$email            = trim($_POST['email']            ?? '');
$password_lama    = $_POST['password_lama']    ?? '';
$password_baru    = $_POST['password_baru']    ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

$redirect_back = function($msg) {
    $_SESSION['notif'] = ['type' => 'error', 'message' => $msg];
    header("Location: ../edit_profile.php");
    exit();
};

// =========================================================================
// Validasi dasar
// =========================================================================
if (empty($nama)) {
    $redirect_back('❌ Nama tidak boleh kosong!');
}

if (mb_strlen($nama) > 150) {
    $redirect_back('❌ Nama terlalu panjang (maks 150 karakter).');
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $redirect_back('❌ Format email tidak valid!');
}

if (empty($password_lama)) {
    $redirect_back('❌ Password saat ini wajib diisi untuk verifikasi keamanan!');
}

if (!empty($password_baru)) {
    if (mb_strlen($password_baru) < 6) {
        $redirect_back('❌ Password baru minimal 6 karakter!');
    }
    if ($password_baru !== $password_confirm) {
        $redirect_back('❌ Konfirmasi password baru tidak cocok!');
    }
}

// =========================================================================
// Ambil data user dari DB dan verifikasi password lama
// =========================================================================
try {
    $stmt = $conn->prepare("SELECT password FROM staf_sekolah WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $redirect_back('❌ Akun tidak ditemukan!');
    }

    // Support hashed (bcrypt) dan plaintext legacy
    $valid = password_verify($password_lama, $user['password']) || ($password_lama === $user['password']);
    if (!$valid) {
        $redirect_back('❌ Password saat ini yang Anda masukkan salah!');
    }

    // =========================================================================
    // Simpan perubahan
    // =========================================================================
    $conn->beginTransaction();

    if (!empty($password_baru)) {
        $hashed = password_hash($password_baru, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE staf_sekolah SET nama = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$nama, $email ?: null, $hashed, $user_id]);
    } else {
        $stmt = $conn->prepare("UPDATE staf_sekolah SET nama = ?, email = ? WHERE id = ?");
        $stmt->execute([$nama, $email ?: null, $user_id]);
    }

    $conn->commit();

    // Sinkronkan session
    $_SESSION['user_nama'] = $nama;

    $msg = !empty($password_baru)
        ? '✅ Profile dan password berhasil diperbarui!'
        : '✅ Profile berhasil diperbarui!';

    $_SESSION['notif'] = ['type' => 'success', 'message' => $msg];

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menyimpan: ' . $e->getMessage()];
}

header("Location: ../edit_profile.php");
exit();
?>
