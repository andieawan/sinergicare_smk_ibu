<?php
/**
 * create_indexes.php
 * Script untuk membuat INDEX database yang mengoptimalkan performa
 * query yang digunakan di index.php dan cetak_administrasi.php
 */

require_once 'config.php';

if ($conn === null) {
    die("❌ Tidak bisa terhubung ke database. Setup database terlebih dahulu!");
}

$queries_index = [
    // INDEX untuk table students - paling sering di-query
    "ALTER TABLE `students` ADD INDEX `idx_status_warna` (`status_warna`)",
    "ALTER TABLE `students` ADD INDEX `idx_class_id` (`class_id`)",
    "ALTER TABLE `students` ADD INDEX `idx_status_class` (`status_warna`, `class_id`)",
    "ALTER TABLE `students` ADD INDEX `idx_nama` (`nama`)",
    "ALTER TABLE `students` ADD INDEX `idx_nisn` (`nisn`)",
    
    // INDEX untuk table incidents - untuk JOIN dengan students
    "ALTER TABLE `incidents` ADD INDEX `idx_student_id` (`student_id`)",
    "ALTER TABLE `incidents` ADD INDEX `idx_category_id` (`category_id`)",
    "ALTER TABLE `incidents` ADD INDEX `idx_created_at` (`created_at`)",
    
    // INDEX untuk table journals - untuk riwayat siswa
    "ALTER TABLE `journals` ADD INDEX `idx_student_id` (`student_id`)",
    "ALTER TABLE `journals` ADD INDEX `idx_user_id` (`user_id`)",
    "ALTER TABLE `journals` ADD INDEX `idx_category_id` (`category_id`)",
    "ALTER TABLE `journals` ADD INDEX `idx_created_at` (`created_at`)",
    "ALTER TABLE `journals` ADD INDEX `idx_student_created` (`student_id`, `created_at`)",
    
    // INDEX untuk table consequences - untuk tracking tugas
    "ALTER TABLE `consequences` ADD INDEX `idx_student_id` (`student_id`)",
    "ALTER TABLE `consequences` ADD INDEX `idx_bk_id` (`bk_id`)",
    "ALTER TABLE `consequences` ADD INDEX `idx_status` (`status_tugas`)",
    "ALTER TABLE `consequences` ADD INDEX `idx_penanggung_jawab` (`penanggung_jawab`)",
    
    // INDEX untuk table classes
    "ALTER TABLE `classes` ADD INDEX `idx_nama_kelas` (`nama_kelas`)",
    
    // INDEX untuk table staf_sekolah
    "ALTER TABLE `staf_sekolah` ADD INDEX `idx_username` (`username`)",
    "ALTER TABLE `staf_sekolah` ADD INDEX `idx_email` (`email`)",
    "ALTER TABLE `staf_sekolah` ADD INDEX `idx_roles` (`roles`)",
    
    // INDEX untuk table violation_categories
    "ALTER TABLE `violation_categories` ADD INDEX `idx_nama_kejadian` (`nama_kejadian`)",
    "ALTER TABLE `violation_categories` ADD INDEX `idx_bobot_risiko` (`bobot_risiko`)",
];

$success_count = 0;
$error_count = 0;
$errors = [];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membuat Database Index - SinergiCare</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 p-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">🚀 Optimasi Database</h1>
                <p class="text-gray-600">Membuat INDEX untuk mempercepat query...</p>
            </div>

            <div class="bg-blue-50 border border-blue-300 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800 font-mono">Jumlah INDEX yang akan dibuat: <?php echo count($queries_index); ?></p>
            </div>

            <div class="space-y-2 mb-8 max-h-96 overflow-y-auto bg-gray-50 p-4 rounded-lg">
                <?php
                foreach ($queries_index as $idx => $query) {
                    try {
                        // Cek apakah INDEX sudah ada (untuk menghindari error duplicate)
                        $conn->exec($query);
                        $success_count++;
                        echo '<div class="text-xs text-green-700 bg-green-50 p-2 rounded border border-green-200">✅ ' . htmlspecialchars($query) . '</div>';
                    } catch (Exception $e) {
                        $error_count++;
                        $error_msg = $e->getMessage();
                        
                        // Abaikan error jika INDEX sudah ada (duplicate key name)
                        if (strpos($error_msg, 'Duplicate') !== false || strpos($error_msg, 'already exists') !== false) {
                            $success_count++;
                            echo '<div class="text-xs text-amber-700 bg-amber-50 p-2 rounded border border-amber-200">⚠️ INDEX sudah ada: ' . substr($query, 0, 60) . '...</div>';
                        } else {
                            $errors[] = $error_msg;
                            echo '<div class="text-xs text-red-700 bg-red-50 p-2 rounded border border-red-200">❌ ' . htmlspecialchars($error_msg) . '</div>';
                        }
                    }
                }
                ?>
            </div>

            <?php if ($error_count == 0): ?>
                <div class="bg-green-50 border border-green-300 rounded-lg p-6 mb-6 text-center">
                    <p class="text-green-800 font-bold text-lg">✅ Semua INDEX berhasil dibuat!</p>
                    <p class="text-green-700 text-sm mt-2">Total: <?php echo $success_count; ?> INDEX</p>
                </div>

                <div class="bg-blue-50 border border-blue-300 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-blue-900 mb-3">📊 Daftar INDEX yang Dibuat:</h3>
                    <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                        <li><strong>students</strong>: status_warna, class_id, nama, nisn, combined indexes</li>
                        <li><strong>incidents</strong>: student_id, category_id, created_at</li>
                        <li><strong>journals</strong>: student_id, user_id, category_id, created_at, combined indexes</li>
                        <li><strong>consequences</strong>: student_id, bk_id, status_tugas, penanggung_jawab</li>
                        <li><strong>classes</strong>: nama_kelas</li>
                        <li><strong>staf_sekolah</strong>: username, email, roles</li>
                        <li><strong>violation_categories</strong>: nama_kejadian, bobot_risiko</li>
                    </ul>
                </div>

                <div class="text-sm text-gray-700 bg-gray-50 p-4 rounded-lg mb-6">
                    <h4 class="font-bold mb-2">💡 Manfaat Indexing:</h4>
                    <ul class="space-y-1 list-disc list-inside">
                        <li>Query GROUP BY status_warna akan lebih cepat</li>
                        <li>Filter siswa berdasarkan status/kelas lebih cepat</li>
                        <li>JOIN dengan incidents/journals lebih optimized</li>
                        <li>Search siswa berdasarkan nama/NISN lebih responsif</li>
                        <li>Query urut created_at (log terbaru) lebih efisien</li>
                    </ul>
                </div>

            <?php else: ?>
                <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-6 mb-6">
                    <p class="text-yellow-800 font-bold">⚠️ Beberapa INDEX sudah ada atau ada error</p>
                    <p class="text-yellow-700 text-sm mt-2">Berhasil: <?php echo $success_count; ?> | Error: <?php echo count($errors); ?></p>
                </div>
            <?php endif; ?>

            <div class="flex gap-4 justify-center">
                <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-bold transition">
                    ✅ Selesai - Kembali ke Dashboard
                </a>
            </div>
        </div>

        <div class="mt-8 bg-white rounded-xl shadow-lg p-6 text-sm text-gray-700">
            <h3 class="font-bold mb-3">📋 Penjelasan INDEX</h3>
            <pre class="bg-gray-50 p-4 rounded overflow-x-auto text-xs">
INDEX adalah struktur data untuk mempercepat query pencarian.

Contoh penggunaan:
- idx_status_warna: Mempercepat GROUP BY dan WHERE status_warna
- idx_student_id: Mempercepat JOIN antara tabel
- idx_created_at: Mempercepat ORDER BY dan filtering berdasarkan tanggal
- Combined index (student_id, created_at): Mempercepat query yang filter student sekaligus order by date

Hasil: Query bisa 10-100x lebih cepat dengan data besar!
            </pre>
        </div>
    </div>
</body>
</html>
