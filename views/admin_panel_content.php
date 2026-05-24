<?php
// views/admin_panel_content.php
if (!isset($conn)) {
    die("Database connection error.");
}

// 1. Fetch Data Kelas
$q_kelas = $conn->query("SELECT * FROM classes ORDER BY nama_kelas ASC");
$kelas_list = $q_kelas ? $q_kelas->fetchAll(PDO::FETCH_ASSOC) : [];

// 2. Fetch Data Staf/User
// BUG FIX: Kolom di tabel staf_sekolah adalah 'roles', bukan 'role'.
// Query lama: ORDER BY role ASC → Error unknown column.
$q_staf = $conn->query("SELECT * FROM staf_sekolah ORDER BY roles ASC, nama ASC");
$staf_list = $q_staf ? $q_staf->fetchAll(PDO::FETCH_ASSOC) : [];

// 3. Fetch Data Pelanggaran
$q_kat = $conn->query("SELECT * FROM violation_categories ORDER BY nama_kejadian ASC");
$kat_list = $q_kat ? $q_kat->fetchAll(PDO::FETCH_ASSOC) : [];
?>

<div class="space-y-6">
    <!-- Header Admin -->
    <div class="bg-indigo-900 p-6 rounded-[2rem] text-white shadow-lg">
        <h2 class="text-xl font-black">Panel Administrator</h2>
        <p class="text-indigo-200 text-sm">Kelola struktur data sekolah dan hak akses pengguna.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <!-- CARD KELAS -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-800 dark:text-white">🏢 Daftar Kelas</h3>
                <button onclick="bukaModalEdit('kelas', '', '')" class="bg-slate-900 text-white text-xs font-bold px-3 py-1.5 rounded-full">+ Tambah</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <?php foreach ($kelas_list as $k): ?>
                    <tr class="border-b dark:border-slate-800">
                        <td class="py-3 font-semibold"><?php echo htmlspecialchars($k['nama_kelas']); ?></td>
                        <td class="py-3 text-right">
                            <button onclick="bukaModalEdit('kelas', '<?php echo $k['id']; ?>', '<?php echo addslashes($k['nama_kelas']); ?>')" class="text-indigo-600 font-bold hover:underline">Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($kelas_list)): ?>
                    <tr><td colspan="2" class="py-6 text-center text-slate-400 italic">Belum ada kelas.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- CARD KATEGORI PELANGGARAN -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-800 dark:text-white">⚖️ Aturan Pelanggaran</h3>
                <button onclick="bukaModalEdit('kategori', '', '', 'ringan')" class="bg-slate-900 text-white text-xs font-bold px-3 py-1.5 rounded-full">+ Tambah</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <?php foreach ($kat_list as $kt): ?>
                    <tr class="border-b dark:border-slate-800">
                        <td class="py-3">
                            <?php echo htmlspecialchars($kt['nama_kejadian']); ?>
                            <span class="ml-2 px-2 py-0.5 rounded text-[9px] bg-slate-100 font-bold uppercase"><?php echo $kt['bobot_risiko']; ?></span>
                        </td>
                        <td class="py-3 text-right">
                            <button onclick="bukaModalEdit('kategori', '<?php echo $kt['id']; ?>', '<?php echo addslashes($kt['nama_kejadian']); ?>', '<?php echo $kt['bobot_risiko']; ?>')" class="text-indigo-600 font-bold hover:underline">Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($kat_list)): ?>
                    <tr><td colspan="2" class="py-6 text-center text-slate-400 italic">Belum ada kategori.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- CARD STAF / GURU -->
        <div class="xl:col-span-2 bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-slate-800 dark:text-white">👨‍🏫 Daftar Staf & Akses Sistem</h3>
                <button onclick="bukaModalEdit('guru', '', '', '', '', 'guru')" class="bg-slate-900 text-white text-xs font-bold px-3 py-1.5 rounded-full">+ Tambah Akun</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-slate-400 uppercase text-[10px]">
                            <th class="text-left py-2">Nama</th>
                            <th class="text-left py-2">Role</th>
                            <th class="text-right py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staf_list as $s): ?>
                        <tr class="border-t dark:border-slate-800">
                            <td class="py-3 font-semibold"><?php echo htmlspecialchars($s['nama']); ?></td>
                            <!-- BUG FIX: Gunakan $s['roles'] bukan $s['role'] -->
                            <td class="py-3 capitalize"><?php echo str_replace('_', ' ', htmlspecialchars($s['roles'] ?? '-')); ?></td>
                            <td class="py-3 text-right">
                                <button onclick="bukaModalEdit('guru', '<?php echo $s['id']; ?>', '<?php echo addslashes($s['nama']); ?>', '<?php echo addslashes($s['username'] ?? ''); ?>', '', '<?php echo $s['roles'] ?? 'guru'; ?>')" class="text-indigo-600 font-bold hover:underline">Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($staf_list)): ?>
                        <tr><td colspan="3" class="py-6 text-center text-slate-400 italic">Belum ada staf.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
