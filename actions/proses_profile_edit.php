<?php
// actions/proses_profile_edit.php - Proses edit profile user
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi: hanya user yang sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn !== null) {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validasi input
    if (empty($nama)) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Nama tidak boleh kosong!'];
        header("Location: ../edit_profile.php");
        exit();
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Format email tidak valid!'];
        header("Location: ../edit_profile.php");
        exit();
    }

    // Verifikasi password lama HANYA jika password lama tidak kosong atau ada password baru
    // (BUG FIX: Lebih fleksibel, hanya minta verifikasi jika ada yang diubah)
    try {
        $stmt = $conn->prepare("SELECT password FROM staf_sekolah WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ User tidak ditemukan!'];
            header("Location: ../edit_profile.php");
            exit();
        }

        // Validasi password lama - harus ada dan valid
        if (empty($password_lama)) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Password saat ini wajib diisi untuk verifikasi keamanan!'];
            header("Location: ../edit_profile.php");
            exit();
        }

        // Cek password lama - support untuk plaintext dan hashed password
        $password_valid = false;
        if (password_verify($password_lama, $user['password'])) {
            // Hashed password
            $password_valid = true;
        } elseif ($password_lama === $user['password']) {
            // Plaintext password (legacy)
            $password_valid = true;
        }

        if (!$password_valid) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Password saat ini yang Anda masukkan salah!'];
            header("Location: ../edit_profile.php");
            exit();
        }

        // Validasi password baru jika ada
        $password_to_save = null;
        if (!empty($password_baru)) {
            if (strlen($password_baru) < 6) {
                $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Password baru minimal 6 karakter!'];
                header("Location: ../edit_profile.php");
                exit();
            }

            if ($password_baru !== $password_confirm) {
                $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Konfirmasi password tidak cocok!'];
                header("Location: ../edit_profile.php");
                exit();
            }

            // Hash password baru
            $password_to_save = password_hash($password_baru, PASSWORD_BCRYPT);
        }

        // Update data profile
        try {
            $conn->beginTransaction();

            if ($password_to_save) {
                // Update nama, email, dan password
                $stmt = $conn->prepare("UPDATE staf_sekolah SET nama = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$nama, $email, $password_to_save, $user_id]);
            } else {
                // Update hanya nama dan email
                $stmt = $conn->prepare("UPDATE staf_sekolah SET nama = ?, email = ? WHERE id = ?");
                $stmt->execute([$nama, $email, $user_id]);
            }

            $conn->commit();

            // Update session
            $_SESSION['user_nama'] = $nama;

            if ($password_to_save) {
                $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Profile dan password berhasil diperbarui!'];
            } else {
                $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Profile berhasil diperbarui!'];
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menyimpan perubahan: ' . $e->getMessage()];
        }

    } catch (PDOException $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal terhubung ke database: ' . $e->getMessage()];
    }
}

header("Location: ../edit_profile.php");
exit();
?>
