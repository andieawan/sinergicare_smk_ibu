<?php
// admin_panel.php - Unified Admin Panel (Master Data + User Management)
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mengantisipasi jika roles di session berupa string tunggal atau array
$user_roles = $_SESSION['user_roles'] ?? [];
if (!is_array($user_roles)) {
    $user_roles = [$user_roles];
}

// Proteksi: hanya admin atau super_admin yang boleh masuk
if (!isset($_SESSION['user_id']) || (!in_array('admin', $user_roles) && !in_array('super_admin', $user_roles))) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_nama = $_SESSION['user_nama'] ?? 'Administrator';

// Definisi $is_super_admin agar tidak error (Undefined variable)
$is_super_admin = in_array('super_admin', $user_roles);

// Ambil mode/tab yang aktif
$tab = $_GET['tab'] ?? 'overview';
$action = $_GET['action'] ?? '';

// Ambil data untuk edit
$edit_id = $_GET['edit_id'] ?? '';
$edit_data = [];

if ($edit_id && isset($conn) && $conn !== null) {
    $tab_for_edit = $_GET['edit_tab'] ?? 'staf';
    if ($tab_for_edit === 'staf' && ($is_super_admin || in_array('admin', $user_roles))) {
        $stmt = $conn->prepare("SELECT id, nama, email, username, roles FROM staf_sekolah WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($tab_for_edit === 'siswa') {
        $stmt = $conn->prepare("SELECT s.id, s.nisn, s.nama, s.class_id, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$notif = [];
if (!empty($_SESSION['notif'])) {
    $notif = $_SESSION['notif'];
    unset($_SESSION['notif']);
}

// Ambil daftar role yang tersedia
$available_roles = ['super_admin', 'admin', 'guru', 'bk', 'waka'];

// Ambil data global
$daftar_kelas = [];
$daftar_kategori = [];
$daftar_staf = [];
$daftar_siswa = [];

if (isset($conn) && $conn !== null) {
    $daftar_kelas = $conn->query("SELECT id, nama_kelas FROM classes ORDER BY nama_kelas ASC")->fetchAll(PDO::FETCH_ASSOC);
    $daftar_kategori = $conn->query("SELECT id, nama_kejadian, bobot_risiko FROM violation_categories ORDER BY nama_kejadian ASC")->fetchAll(PDO::FETCH_ASSOC);
    $daftar_staf = $conn->query("SELECT id, nama, email, username, roles FROM staf_sekolah ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);
    $daftar_siswa = $conn->query("SELECT s.id, s.nisn, s.nama, s.class_id, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.nama ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$stat_staf = count($daftar_staf);
$stat_siswa = count($daftar_siswa);
$stat_kelas = count($daftar_kelas);
$stat_kategori = count($daftar_kategori);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - SinergiCare</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f4f4f6; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark ::-webkit-scrollbar-track { background: #1e293b; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
        .tab-btn.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .tab-btn { transition: all 0.3s; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased min-h-screen">
    <!-- Navbar -->
    <nav class="sticky top-0 z-40 bg-white dark:bg-slate-900 border-b border-slate-200/60 dark:border-slate-800 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center text-white font-bold">🔧</div>
                <div>
                    <h1 class="text-lg font-extrabold tracking-tight">Admin Panel</h1>
                    <p class="text-xs text-slate-400">SinergiCare Management</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold">👤 <?php echo htmlspecialchars($user_nama); ?></span>
                <span class="text-xs bg-slate-100 dark:bg-slate-800 px-3 py-1 rounded-full font-bold text-slate-600 dark:text-slate-300">
                    <?php echo $is_super_admin ? '⭐ Super Admin' : '👤 Admin'; ?>
                </span>
                <div class="w-1 h-1 bg-slate-300 rounded-full"></div>
                <a href="edit_profile.php" class="text-sm text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">⚙️ Profile</a>
                <a href="logout.php" class="text-sm text-rose-600 dark:text-rose-400 font-semibold hover:underline">🚪 Logout</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Notifikasi -->
        <?php if (!empty($notif)): ?>
            <div class="mb-6 p-4 rounded-xl border <?php echo ($notif['type'] ?? 'success') === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'; ?>">
                <div class="flex items-center justify-between">
                    <span><?php echo htmlspecialchars($notif['message'] ?? ''); ?></span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-xl font-bold opacity-50 hover:opacity-100">×</button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/60 dark:border-slate-800 p-6 shadow-sm">
                <p class="text-xs text-slate-400 font-bold uppercase mb-2">👥 Total Staf</p>
                <p class="text-3xl font-extrabold text-indigo-600"><?php echo $stat_staf; ?></p>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/60 dark:border-slate-800 p-6 shadow-sm">
                <p class="text-xs text-slate-400 font-bold uppercase mb-2">🎓 Total Siswa</p>
                <p class="text-3xl font-extrabold text-purple-600"><?php echo $stat_siswa; ?></p>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/60 dark:border-slate-800 p-6 shadow-sm">
                <p class="text-xs text-slate-400 font-bold uppercase mb-2">🏫 Total Kelas</p>
                <p class="text-3xl font-extrabold text-blue-600"><?php echo $stat_kelas; ?></p>
            </div>
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/60 dark:border-slate-800 p-6 shadow-sm">
                <p class="text-xs text-slate-400 font-bold uppercase mb-2">⚖️ Kategori</p>
                <p class="text-3xl font-extrabold text-amber-600"><?php echo $stat_kategori; ?></p>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/60 dark:border-slate-800 shadow-sm mb-8 overflow-hidden">
            <div class="flex flex-wrap border-b border-slate-200 dark:border-slate-800">
                <a href="?tab=overview" class="tab-btn <?php echo $tab === 'overview' ? 'active' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'; ?> px-6 py-4 font-semibold text-sm">📊 Overview</a>
                <a href="?tab=staf" class="tab-btn <?php echo $tab === 'staf' ? 'active' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'; ?> px-6 py-4 font-semibold text-sm">👥 Manajemen Staf</a>
                <a href="?tab=siswa" class="tab-btn <?php echo $tab === 'siswa' ? 'active' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'; ?> px-6 py-4 font-semibold text-sm">🎓 Manajemen Siswa</a>
                <a href="?tab=kelas" class="tab-btn <?php echo $tab === 'kelas' ? 'active' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'; ?> px-6 py-4 font-semibold text-sm">🏫 Kelas & Kategori</a>
            </div>

            <div class="p-8">
                <!-- TAB: Overview -->
                <?php if ($tab === 'overview'): ?>
                    <div class="space-y-6">
                        <div class="bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-xl p-6 border border-indigo-200 dark:border-indigo-700">
                            <h3 class="text-lg font-bold mb-2">🎉 Selamat datang di Admin Panel</h3>
                            <p class="text-slate-600 dark:text-slate-300 text-sm">Kelola semua data master dan user dari satu tempat. Pilih tab di atas untuk mulai.</p>
                        </div>

                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-slate-800/50 rounded-xl p-6 border border-slate-200 dark:border-slate-700">
                                <h4 class="font-bold text-slate-900 dark:text-white mb-3">📋 Akses Cepat</h4>
                                <ul class="space-y-2 text-sm">
                                    <li><a href="?tab=staf&action=create" class="text-indigo-600 dark:text-indigo-400 hover:underline">➕ Tambah Staf Baru</a></li>
                                    <li><a href="?tab=siswa&action=create" class="text-indigo-600 dark:text-indigo-400 hover:underline">➕ Tambah Siswa Baru</a></li>
                                    <li><a href="?tab=kelas" class="text-indigo-600 dark:text-indigo-400 hover:underline">🏫 Manajemen Kelas</a></li>
                                    <li><a href="edit_profile.php" class="text-indigo-600 dark:text-indigo-400 hover:underline">⚙️ Edit Profil Saya</a></li>
                                </ul>
                            </div>
                            <div class="bg-white dark:bg-slate-800/50 rounded-xl p-6 border border-slate-200 dark:border-slate-700">
                                <h4 class="font-bold text-slate-900 dark:text-white mb-3">ℹ️ Informasi Sistem</h4>
                                <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                    <li>📦 Total Data Master: <span class="font-bold"><?php echo $stat_staf + $stat_siswa + $stat_kelas + $stat_kategori; ?></span></li>
                                    <li>✅ Database: <?php echo isset($conn) ? 'Connected' : '<span class="text-red-500">Disconnected</span>'; ?></li>
                                    <li>🔐 Role: <?php echo $is_super_admin ? 'Super Admin' : 'Admin'; ?></li>
                                    <li>⏰ Waktu Server: <?php echo date('d M Y H:i:s'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                <!-- TAB: Manajemen Staf -->
                <?php elseif ($tab === 'staf'): ?>
                    <div class="space-y-6">
                        <?php if ($action === 'create' || ($action === 'edit' && $edit_id)): ?>
                            <!-- Form Create/Edit Staf -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
                                <h3 class="font-bold text-lg mb-4"><?php echo $action === 'edit' ? '✏️ Edit Staf' : '➕ Tambah Staf Baru'; ?></h3>
                                <form method="POST" action="actions/proses_admin.php?aksi=manage_staf" class="space-y-4">
                                    <?php if ($action === 'edit' && $edit_id): ?>
                                        <input type="hidden" name="staf_id" value="<?php echo htmlspecialchars($edit_id); ?>">
                                    <?php endif; ?>

                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold mb-2">Nama Lengkap *</label>
                                            <input type="text" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="nama" value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-2">Email *</label>
                                            <input type="email" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="email" value="<?php echo htmlspecialchars($edit_data['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold mb-2">Username *</label>
                                            <input type="text" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="username" value="<?php echo htmlspecialchars($edit_data['username'] ?? ''); ?>" <?php echo $action === 'edit' ? 'readonly' : ''; ?> required>
                                            <p class="text-xs text-slate-500 mt-1">Username tidak bisa diubah setelah pembuatan</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-2">Role *</label>
                                            <select class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="roles" required>
                                                <option value="">-- Pilih Role --</option>
                                                <?php foreach ($available_roles as $role): ?>
                                                    <!-- Sembunyikan opsi super_admin jika pembuat bukan super_admin -->
                                                    <?php if($role === 'super_admin' && !$is_super_admin) continue; ?>
                                                    <option value="<?php echo htmlspecialchars($role); ?>" <?php echo ($edit_data['roles'] ?? '') === $role ? 'selected' : ''; ?>>
                                                        <?php echo ucwords(str_replace('_', ' ', $role)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold mb-2"><?php echo $action === 'create' ? 'Password Default' : 'Password Baru'; ?> <?php echo $action === 'create' ? '*' : ''; ?></label>
                                        <input type="password" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="password" placeholder="<?php echo $action === 'create' ? 'Minimal 6 karakter' : 'Kosongkan jika tidak ingin mengubah'; ?>" <?php echo $action === 'create' ? 'required' : ''; ?>>
                                    </div>

                                    <div class="flex gap-3 pt-4">
                                        <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition">💾 Simpan</button>
                                        <a href="?tab=staf" class="bg-slate-300 dark:bg-slate-700 text-slate-900 dark:text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition inline-block text-center">❌ Batal</a>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold">👥 Daftar Staf/User</h3>
                                <a href="?tab=staf&action=create" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-lg font-semibold hover:opacity-90 transition">+ Tambah Staf</a>
                            </div>
                        <?php endif; ?>

                        <!-- Tabel Staf -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">ID</th>
                                        <th class="px-4 py-3 text-left font-semibold">Nama</th>
                                        <th class="px-4 py-3 text-left font-semibold">Email</th>
                                        <th class="px-4 py-3 text-left font-semibold">Username</th>
                                        <th class="px-4 py-3 text-left font-semibold">Role</th>
                                        <th class="px-4 py-3 text-left font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    <?php foreach ($daftar_staf as $staf): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                        <td class="px-4 py-3"><span class="font-bold text-indigo-600"><?php echo htmlspecialchars($staf['id']); ?></span></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($staf['nama']); ?></td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($staf['email']); ?></td>
                                        <td class="px-4 py-3"><code class="bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-xs"><?php echo htmlspecialchars($staf['username']); ?></code></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?php
                                                $role_colors = [
                                                    'super_admin' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
                                                    'admin' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200',
                                                    'guru' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200',
                                                    'bk' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-200',
                                                    'waka' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200',
                                                ];
                                                echo $role_colors[$staf['roles'] ?? ''] ?? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300';
                                            ?>">
                                                <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($staf['roles'] ?? '-'))); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 flex gap-2">
                                            <a href="?tab=staf&action=edit&edit_id=<?php echo htmlspecialchars($staf['id']); ?>&edit_tab=staf" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold text-xs">✏️ Edit</a>
                                            <!-- Jangan izinkan menghapus diri sendiri -->
                                            <?php if($staf['id'] != $user_id): ?>
                                                <a href="actions/proses_admin.php?aksi=delete_staf&staf_id=<?php echo htmlspecialchars($staf['id']); ?>" class="text-rose-600 dark:text-rose-400 hover:underline font-semibold text-xs" onclick="return confirm('Yakin hapus staf ini?');">🗑️ Hapus</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($daftar_staf)): ?>
                                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Data Staf masih kosong.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                <!-- TAB: Manajemen Siswa -->
                <?php elseif ($tab === 'siswa'): ?>
                    <div class="space-y-6">
                        <?php if ($action === 'create' || ($action === 'edit' && $edit_id)): ?>
                            <!-- Form Create/Edit Siswa -->
                            <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-xl p-6">
                                <h3 class="font-bold text-lg mb-4"><?php echo $action === 'edit' ? '✏️ Edit Siswa' : '➕ Tambah Siswa Baru'; ?></h3>
                                <form method="POST" action="actions/proses_admin.php?aksi=manage_siswa" class="space-y-4">
                                    <?php if ($action === 'edit' && $edit_id): ?>
                                        <input type="hidden" name="siswa_id" value="<?php echo htmlspecialchars($edit_id); ?>">
                                    <?php endif; ?>

                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold mb-2">NISN *</label>
                                            <input type="text" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="nisn" value="<?php echo htmlspecialchars($edit_data['nisn'] ?? ''); ?>" <?php echo $action === 'edit' ? 'readonly' : ''; ?> required>
                                            <p class="text-xs text-slate-500 mt-1">NISN tidak bisa diubah setelah pembuatan</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-2">Nama Siswa *</label>
                                            <input type="text" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="nama" value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold mb-2">Kelas *</label>
                                        <select class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" name="class_id" required>
                                            <option value="">-- Pilih Kelas --</option>
                                            <?php foreach ($daftar_kelas as $kelas): ?>
                                                <option value="<?php echo htmlspecialchars($kelas['id']); ?>" <?php echo ($edit_data['class_id'] ?? '') == $kelas['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="flex gap-3 pt-4">
                                        <button type="submit" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition">💾 Simpan</button>
                                        <a href="?tab=siswa" class="bg-slate-300 dark:bg-slate-700 text-slate-900 dark:text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition inline-block text-center">❌ Batal</a>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold">🎓 Daftar Siswa</h3>
                                <a href="?tab=siswa&action=create" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-lg font-semibold hover:opacity-90 transition">+ Tambah Siswa</a>
                            </div>
                        <?php endif; ?>

                        <!-- Tabel Siswa -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">ID</th>
                                        <th class="px-4 py-3 text-left font-semibold">NISN</th>
                                        <th class="px-4 py-3 text-left font-semibold">Nama</th>
                                        <th class="px-4 py-3 text-left font-semibold">Kelas</th>
                                        <th class="px-4 py-3 text-left font-semibold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    <?php foreach ($daftar_siswa as $siswa): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                        <td class="px-4 py-3"><span class="font-bold text-purple-600"><?php echo htmlspecialchars($siswa['id']); ?></span></td>
                                        <td class="px-4 py-3"><code class="bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-xs"><?php echo htmlspecialchars($siswa['nisn']); ?></code></td>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($siswa['nama']); ?></td>
                                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></td>
                                        <td class="px-4 py-3 flex gap-2">
                                            <a href="?tab=siswa&action=edit&edit_id=<?php echo htmlspecialchars($siswa['id']); ?>&edit_tab=siswa" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold text-xs">✏️ Edit</a>
                                            <a href="actions/proses_admin.php?aksi=delete_siswa&siswa_id=<?php echo htmlspecialchars($siswa['id']); ?>" class="text-rose-600 dark:text-rose-400 hover:underline font-semibold text-xs" onclick="return confirm('Yakin hapus siswa ini?');">🗑️ Hapus</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($daftar_siswa)): ?>
                                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Data Siswa masih kosong.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                <!-- TAB: Kelas & Kategori -->
                <?php elseif ($tab === 'kelas'): ?>
                    <div class="grid lg:grid-cols-2 gap-8">
                        <!-- Kelas Management -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-bold">🏫 Manajemen Kelas</h3>
                            <form method="POST" action="actions/proses_admin.php?aksi=tambah_kelas" class="space-y-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2">Nama Kelas Baru</label>
                                    <input type="text" name="nama_kelas" placeholder="Contoh: XI DKV 1" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" required>
                                </div>
                                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition">➕ Tambah Kelas</button>
                            </form>

                            <div class="space-y-2 max-h-80 overflow-y-auto">
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">Daftar Kelas (<?php echo count($daftar_kelas); ?>)</p>
                                <?php foreach ($daftar_kelas as $kls): ?>
                                    <div class="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                                        <span class="font-semibold">🏢 <?php echo htmlspecialchars($kls['nama_kelas']); ?></span>
                                        <button onclick="editKelas('<?php echo htmlspecialchars($kls['id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($kls['nama_kelas'], ENT_QUOTES, 'UTF-8'); ?>')" class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs font-semibold">✏️ Edit</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Kategori Management -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-bold">⚖️ Manajemen Kategori Pelanggaran</h3>
                            <form method="POST" action="actions/proses_admin.php?aksi=tambah_kategori" class="space-y-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                                <div>
                                    <label class="block text-sm font-semibold mb-2">Nama Pelanggaran</label>
                                    <input type="text" name="nama_kejadian" placeholder="Contoh: Terlambat masuk kelas" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-2">Bobot Risiko</label>
                                    <select name="bobot_risiko" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" required>
                                        <option value="ringan">🟢 Ringan (Hijau)</option>
                                        <option value="sedang">🟡 Sedang (Kuning)</option>
                                        <option value="berat">🔴 Berat (Merah)</option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full bg-amber-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-amber-700 transition">➕ Tambah Kategori</button>
                            </form>

                            <div class="space-y-2 max-h-80 overflow-y-auto">
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">Daftar Kategori (<?php echo count($daftar_kategori); ?>)</p>
                                <?php foreach ($daftar_kategori as $kat): ?>
                                    <div class="bg-white dark:bg-slate-800 p-3 rounded-lg border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                                        <div>
                                            <span class="font-semibold">⚖️ <?php echo htmlspecialchars($kat['nama_kejadian']); ?></span>
                                            <span class="ml-2 text-xs font-bold <?php
                                                $risk_colors = [
                                                    'ringan' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
                                                    'sedang' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200',
                                                    'berat' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
                                                ];
                                                echo $risk_colors[$kat['bobot_risiko']] ?? 'bg-slate-100 text-slate-800';
                                            ?> px-2 py-1 rounded">
                                                <?php echo ucfirst(htmlspecialchars($kat['bobot_risiko'])); ?>
                                            </span>
                                        </div>
                                        <button onclick="editKategori('<?php echo htmlspecialchars($kat['id'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($kat['nama_kejadian'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($kat['bobot_risiko'], ENT_QUOTES, 'UTF-8'); ?>')" class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs font-semibold">✏️ Edit</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Edit Kelas -->
    <div id="modalEditKelas" class="fixed inset-0 bg-black/40 hidden z-50 flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-lg mb-4">✏️ Edit Kelas</h3>
            <form method="POST" action="actions/proses_admin.php?aksi=edit_kelas" class="space-y-4">
                <input type="hidden" id="editKelasId" name="id">
                <div>
                    <label class="block text-sm font-semibold mb-2">Nama Kelas</label>
                    <input type="text" id="editKelasNama" name="nama" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" required>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-indigo-700 transition">💾 Simpan</button>
                    <button type="button" onclick="document.getElementById('modalEditKelas').classList.add('hidden')" class="flex-1 bg-slate-300 dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-2 rounded-lg font-semibold hover:opacity-90 transition">❌ Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Kategori -->
    <div id="modalEditKategori" class="fixed inset-0 bg-black/40 hidden z-50 flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800">
            <h3 class="font-bold text-lg mb-4">✏️ Edit Kategori</h3>
            <form method="POST" action="actions/proses_admin.php?aksi=edit_kategori" class="space-y-4">
                <input type="hidden" id="editKategoriId" name="id">
                <div>
                    <label class="block text-sm font-semibold mb-2">Nama Pelanggaran</label>
                    <input type="text" id="editKategoriNama" name="nama" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Bobot Risiko</label>
                    <select id="editKategoriBobot" name="bobot_risiko" class="w-full p-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white outline-none focus:border-indigo-500" required>
                        <option value="ringan">🟢 Ringan</option>
                        <option value="sedang">🟡 Sedang</option>
                        <option value="berat">🔴 Berat</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-indigo-700 transition">💾 Simpan</button>
                    <button type="button" onclick="document.getElementById('modalEditKategori').classList.add('hidden')" class="flex-1 bg-slate-300 dark:bg-slate-700 text-slate-900 dark:text-white px-4 py-2 rounded-lg font-semibold hover:opacity-90 transition">❌ Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editKelas(id, nama) {
            document.getElementById('editKelasId').value = id;
            document.getElementById('editKelasNama').value = nama;
            document.getElementById('modalEditKelas').classList.remove('hidden');
        }

        function editKategori(id, nama, bobot) {
            document.getElementById('editKategoriId').value = id;
            document.getElementById('editKategoriNama').value = nama;
            document.getElementById('editKategoriBobot').value = bobot;
            document.getElementById('modalEditKategori').classList.remove('hidden');
        }

        // Dark mode support
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>