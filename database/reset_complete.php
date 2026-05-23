<?php
// reset_complete.php - Hapus database lama dan file konfigurasi
define('DB_CONFIG_FILE', __DIR__ . '/db_credentials.json');

$pesan = "";
$status = "pending";

// 1. Coba koneksi ke server MySQL tanpa database tertentu
try {
    $conn_test = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
    $conn_test->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Drop database lama jika ada
    $db_name = 'sinergicare_smk';
    $conn_test->exec("DROP DATABASE IF EXISTS `$db_name`");
    $pesan .= "✅ Database '$db_name' berhasil dihapus.\n";
    
    $status = "success";
} catch (Exception $e) {
    $pesan .= "⚠️ Tidak bisa drop database. Pastikan MySQL running dan akses root tersedia.\n";
    $pesan .= "Error: " . $e->getMessage() . "\n";
    $status = "warning";
}

// 3. Hapus file konfigurasi
if (file_exists(DB_CONFIG_FILE)) {
    unlink(DB_CONFIG_FILE);
    $pesan .= "✅ File db_credentials.json dihapus.\n";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Database - SinergiCare</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 p-8">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-xl shadow-lg">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">🔄 Reset Database</h1>
            <p class="text-gray-600 mt-2">Database lama sedang dibersihkan...</p>
        </div>

        <div class="bg-blue-50 p-4 rounded-lg border border-blue-300 mb-6">
            <p class="text-sm text-gray-800 whitespace-pre-line font-mono"><?php echo htmlspecialchars($pesan); ?></p>
        </div>

        <?php if ($status === "success"): ?>
            <div class="bg-green-50 border border-green-300 p-4 rounded-lg mb-6">
                <p class="text-green-800 font-semibold">✅ Reset Berhasil!</p>
                <p class="text-green-700 text-sm mt-2">Anda akan diarahkan ke halaman setup dalam 3 detik...</p>
            </div>
            <script>
                setTimeout(() => { window.location = 'setup.php'; }, 3000);
            </script>
        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-300 p-4 rounded-lg mb-6">
                <p class="text-yellow-800 font-semibold">⚠️ Ada Kendala</p>
                <p class="text-yellow-700 text-sm mt-2">Silakan reset manual atau hubungi admin server Anda.</p>
            </div>
            <a href="setup.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">
                Lanjut ke Setup →
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
