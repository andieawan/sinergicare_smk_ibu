<?php
// edit_profile.php - Self-service profile edit untuk semua user
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi: hanya user yang sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nama = $_SESSION['user_nama'];
$user_roles = $_SESSION['user_roles'] ?? [];

// Ambil data user dari database
$user_data = [];
if ($conn !== null) {
    $stmt = $conn->prepare("SELECT id, nama, email, username FROM staf_sekolah WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

$notif = [];
if (!empty($_SESSION['notif'])) {
    $notif = $_SESSION['notif'];
    unset($_SESSION['notif']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - SinergiCare</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .profile-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
        .card-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn-update:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
            transition: background 0.2s;
        }
        .btn-back:hover {
            background: #5a6268;
            text-decoration: none;
        }
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .user-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info-label {
            font-weight: 600;
            color: #667eea;
        }
        .divider {
            border-top: 2px solid #e0e0e0;
            margin: 25px 0;
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Notifikasi -->
        <?php if (!empty($notif)): ?>
            <?php if ($notif['type'] === 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($notif['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif ($notif['type'] === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($notif['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>⚙️ Edit Profile Saya</h3>
            </div>
            <div class="card-body" style="padding: 30px;">
                <!-- Info User Saat Ini -->
                <div class="user-info">
                    <p><span class="info-label">👤 Username:</span> <strong><?php echo htmlspecialchars($user_data['username']); ?></strong> (tidak bisa diubah)</p>
                    <p><span class="info-label">🆔 ID User:</span> <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
                </div>

                <form method="POST" action="actions/proses_profile_edit.php" id="editProfileForm">
                    <!-- Edit Nama -->
                    <div class="form-group">
                        <label for="nama">👤 Nama Lengkap</label>
                        <input type="text" class="form-control" id="nama" name="nama" 
                               value="<?php echo htmlspecialchars($user_data['nama'] ?? ''); ?>" 
                               placeholder="Masukkan nama lengkap Anda" required>
                    </div>

                    <!-- Edit Email -->
                    <div class="form-group">
                        <label for="email">📧 Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" 
                               placeholder="Masukkan email Anda">
                    </div>

                    <div class="divider"></div>

                    <!-- Ubah Password -->
                    <h5 style="margin-bottom: 20px; color: #333;">🔒 Ubah Password</h5>
                    
                    <div class="form-group">
                        <label for="password_lama">Password Saat Ini <span style="color: red;">*</span></label>
                        <input type="password" class="form-control" id="password_lama" name="password_lama" 
                               placeholder="Masukkan password saat ini untuk verifikasi" required>
                        <small class="text-muted">Diperlukan untuk keamanan saat mengubah data</small>
                    </div>

                    <div class="form-group">
                        <label for="password_baru">Password Baru (kosongkan jika tidak ingin mengubah)</label>
                        <input type="password" class="form-control" id="password_baru" name="password_baru" 
                               placeholder="Masukkan password baru (minimal 6 karakter)">
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                               placeholder="Ketik ulang password baru untuk konfirmasi">
                    </div>

                    <button type="submit" class="btn-update">💾 Simpan Perubahan</button>
                </form>

                <a href="index.php" class="btn-back">← Kembali ke Dashboard</a>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validasi password baru dan konfirmasi saat user mengetik
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            const passwordBaru = document.getElementById('password_baru').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            if (passwordBaru && passwordBaru !== passwordConfirm) {
                e.preventDefault();
                alert('❌ Password baru dan konfirmasi password tidak cocok!');
                return false;
            }
            
            if (passwordBaru && passwordBaru.length < 6) {
                e.preventDefault();
                alert('❌ Password baru minimal 6 karakter!');
                return false;
            }
        });
    </script>
</body>
</html>
