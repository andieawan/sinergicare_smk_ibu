<?php
// reset_db.php - Script untuk menghapus konfigurasi database lama
define('DB_CONFIG_FILE', __DIR__ . '/db_credentials.json');

// Delete the credentials file if it exists
if (file_exists(DB_CONFIG_FILE)) {
    unlink(DB_CONFIG_FILE);
    echo "✅ File db_credentials.json berhasil dihapus!\n";
    echo "🔄 Silakan akses setup.php untuk setup database baru dengan schema yang benar.\n";
} else {
    echo "ℹ️ File db_credentials.json tidak ditemukan.\n";
}
// Redirect to setup.php after 2 seconds using meta refresh
echo "<meta http-equiv=\"refresh\" content=\"2;url=setup.php\"/>";
?>
