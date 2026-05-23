<?php
// ====================================================================================
// [BLOCK 1: PHP CORE INITIALIZATION & DATABASE QUERIES] - SINERGICARE V3.0.0
// ====================================================================================
// ARSITEKTUR v3.0.0: Jalur konfigurasi database dialihkan ke folder config/
require_once 'config/config.php';

// Proteksi Sesi Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id_login = $_SESSION['user_id'];
$user_nama     = $_SESSION['user_nama'];
$user_roles    = $_SESSION['user_roles']; 

// --- [QUERY A: STATISTIK WARNA RADAR] ---
$count_hijau = 0; $count_kuning = 0; $count_merah = 0;
if ($conn !== null) {
    $q_stats = $conn->query("SELECT status_warna, COUNT(*) as jumlah FROM students GROUP BY status_warna");
    while($row = $q_stats->fetch(PDO::FETCH_ASSOC)) {
        if($row['status_warna'] == 'hijau') $count_hijau = $row['jumlah'];
        if($row['status_warna'] == 'kuning') $count_kuning = $row['jumlah'];
        if($row['status_warna'] == 'merah') $count_merah = $row['jumlah'];
    }
}

// --- [QUERY B: AMBIL DATA KELAS GLOBAL] ---
$daftar_kelas_global = [];
if ($conn !== null) {
    $q_global_cls = $conn->query("SELECT * FROM classes ORDER BY nama_kelas ASC");
    while($g_cls = $q_global_cls->fetch(PDO::FETCH_ASSOC)) {
        $daftar_kelas_global[] = $g_cls;
    }
}

// --- [QUERY C: AMBIL DATA GURU GLOBAL] ---
$daftar_guru_global = [];
if ($conn !== null) {
    $q_global_gru = $conn->query("SELECT * FROM staf_sekolah ORDER BY nama ASC");
    while($g_gru = $q_global_gru->fetch(PDO::FETCH_ASSOC)) {
        $daftar_guru_global[] = $g_gru;
    }
}

// --- [QUERY D: AMBIL DATA BUKU ATURAN GLOBAL] ---
$daftar_kategori_global = [];
if ($conn !== null) {
    $q_global_kat = $conn->query("SELECT * FROM violation_categories ORDER BY nama_kejadian ASC");
    while($g_kat = $q_global_kat->fetch(PDO::FETCH_ASSOC)) {
        $daftar_kategori_global[] = $g_kat;
    }
}

// --- [QUERY E: AMBIL DAFTAR SISWA UNTUK ADMINISTRASI BK] ---
$daftar_siswa_global = [];
if ($conn !== null) {
    $q_global_sis = $conn->query("SELECT s.*, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.nama ASC");
    while($g_sis = $q_global_sis->fetch(PDO::FETCH_ASSOC)) {
        $daftar_siswa_global[] = $g_sis;
    }
}

// --- [PERBAIKAN QUERY F v3.0.0: PENYARINGAN SEGMENTASI LOG KASUS BERDASARKAN ROLE] ---
$log_jurnal_terkini = [];
if ($conn !== null) {
    try {
        $is_bk_admin = count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0;
        
        if ($is_bk_admin) {
            $q_log = $conn->prepare("SELECT i.*, s.nama AS nama_siswa, c.nama_kelas, vc.nama_kejadian, vc.bobot_risiko 
                                     FROM incidents i 
                                     JOIN students s ON i.student_id = s.id 
                                     LEFT JOIN classes c ON s.class_id = c.id 
                                     JOIN violation_categories vc ON i.category_id = vc.id 
                                     WHERE DATE(i.created_at) = CURDATE()
                                     ORDER BY i.id DESC");
            $q_log->execute();
        } else {
            $q_log = $conn->prepare("SELECT i.*, s.nama AS nama_siswa, c.nama_kelas, vc.nama_kejadian, vc.bobot_risiko 
                                     FROM incidents i 
                                     JOIN students s ON i.student_id = s.id 
                                     LEFT JOIN classes c ON s.class_id = c.id 
                                     JOIN violation_categories vc ON i.category_id = vc.id 
                                     WHERE i.user_id = :user_id
                                     ORDER BY i.id DESC LIMIT 15");
            $q_log->execute(['user_id' => $user_id_login]);
        }

        while($r_log = $q_log->fetch(PDO::FETCH_ASSOC)) {
            $log_jurnal_terkini[] = $r_log;
        }
    } catch (Exception $e) {
        $log_jurnal_terkini = [];
    }
}

// --- [QUERY G: ANALITIK EXCLUSIVE WAKA - PETA KERAWANAN KELAS] ---
$peta_kerawanan_kelas = [];
if ($conn !== null) {
    $q_rawan = $conn->query("SELECT c.nama_kelas, COUNT(i.id) as total_cases 
                             FROM incidents i
                             JOIN students s ON i.student_id = s.id
                             JOIN classes c ON s.class_id = c.id
                             GROUP BY c.id ORDER BY total_cases DESC LIMIT 3");
    while($r_rawan = $q_rawan->fetch(PDO::FETCH_ASSOC)) {
        $peta_kerawanan_kelas[] = $r_rawan;
    }
}

// --- [QUERY H: TREN PELANGGARAN UTAMA BULAN INI] ---
$tren_pelanggaran = [];
if ($conn !== null) {
    $q_tren = $conn->query("SELECT vc.nama_kejadian, COUNT(i.id) as jumlah 
                            FROM incidents i
                            JOIN violation_categories vc ON i.category_id = vc.id
                            WHERE MONTH(i.created_at) = MONTH(CURRENT_DATE())
                            GROUP BY vc.id ORDER BY jumlah DESC LIMIT 3");
    while($r_tren = $q_tren->fetch(PDO::FETCH_ASSOC)) {
        $tren_pelanggaran[] = $r_tren;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SinergiCare SMK v3.0.0 - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = { darkMode: 'class' }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    </script>
    <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .dark ::-webkit-scrollbar-thumb { background: #334155; }
    </style>
</head>

<body class="bg-[#f4f4f6] dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased min-h-screen flex flex-col lg:flex-row p-2 sm:p-4 gap-4 transition-colors duration-300">

    <aside class="w-full lg:w-72 bg-white dark:bg-slate-900 flex flex-col shrink-0 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 p-5 lg:sticky lg:top-4 lg:h-[calc(100vh-2rem)] shadow-sm justify-between transition-colors duration-300">
        <div class="space-y-6">
            <div class="flex items-center justify-between lg:justify-start gap-3 px-2">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-slate-900 dark:bg-white flex items-center justify-center text-white dark:text-slate-900 font-black text-sm">S</div>
                    <div>
                        <h1 class="text-sm font-extrabold tracking-tight">SinergiCare</h1>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">Radar Karakter v3.0.0</p>
                    </div>
                </div>
                <button class="lg:hidden text-xs bg-slate-100 dark:bg-slate-800 border dark:border-slate-700 px-2 py-1 rounded-xl font-bold" onclick="toggleMobileSidebar()">☰ Menu</button>
            </div>

            <nav id="sidebar_links" class="space-y-4 hidden lg:block">
                <div class="space-y-1">
                    <span class="text-[9px] font-bold tracking-widest text-slate-400 dark:text-slate-500 uppercase px-3 block mb-1">Analisis</span>
                    <button id="btn_nav_section_dashboard" onclick="switchMenu('section_dashboard')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-bold bg-slate-900 dark:bg-white text-white dark:text-slate-900 transition text-left target-menu-btn">
                        📊 Overview Dashboard
                    </button>
                </div>

                <div class="space-y-1">
                    <span class="text-[9px] font-bold tracking-widest text-slate-400 dark:text-slate-500 uppercase px-3 block mb-1">Operasional & Kontrol</span>
                    <button id="btn_nav_section_jurnal" onclick="switchMenu('section_jurnal')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition text-left target-menu-btn">
                        🚨 Jurnal Insiden Harian
                    </button>

                    <?php if (in_array('guru', $user_roles) || in_array('super_admin', $user_roles)): ?>
                    <button id="btn_nav_section_mandat" onclick="switchMenu('section_mandat')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition text-left target-menu-btn">
                        📋 Pengawasan Lapangan
                    </button>
                    <?php endif; ?>

                    <?php if (count(array_intersect(['bk', 'super_admin', 'kepala_sekolah', 'waka_kesiswaan', 'kepala_jurusan', 'yayasan'], $user_roles)) > 0): ?>
                    <button id="btn_nav_section_radar_bk" onclick="switchMenu('section_radar_bk')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition text-left target-menu-btn">
                        🎯 Radar Urgent BK
                    </button>
                    <?php endif; ?>

                    <?php if (in_array('bk', $user_roles) || in_array('super_admin', $user_roles)): ?>
                    <button id="btn_nav_section_cetak_bk" onclick="switchMenu('section_cetak_bk')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition text-left target-menu-btn">
                        🖨️ Pusat Cetak Surat BK
                    </button>
                    <?php endif; ?>

                    <?php if (in_array('kepala_jurusan', $user_roles) || in_array('super_admin', $user_roles)): ?>
                    <button id="btn_nav_section_kajur" onclick="switchMenu('section_kajur')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition text-left target-menu-btn">
                        ⚡ Kelayakan PKL Jurusan
                    </button>
                    <?php endif; ?>

                    <?php if (in_array('waka_kesiswaan', $user_roles) || in_array('super_admin', $user_roles)): ?>
                    <button id="btn_nav_section_waka" onclick="switchMenu('section_waka')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition text-left target-menu-btn">
                        🛡️ Eskalasi Tindakan Waka
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (in_array('admin', $user_roles) || in_array('super_admin', $user_roles)): ?>
                <div class="space-y-1">
                    <span class="text-[9px] font-bold tracking-widest text-slate-400 dark:text-slate-500 uppercase px-3 block mb-1">Pengaturan</span>
                    <button id="btn_nav_section_master_data" onclick="switchMenu('section_master_data')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl text-xs font-semibold text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white transition text-left target-menu-btn">
                        ⚙️ Master Data Panel
                    </button>
                </div>
                <?php endif; ?>
            </nav>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
            <div class="truncate max-w-[120px]">
                <p class="font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($user_nama, ENT_QUOTES); ?></p>
                <p class="text-[9px] text-indigo-600 dark:text-indigo-400 font-extrabold uppercase tracking-wider">
                    <?php echo isset($user_roles[0]) ? htmlspecialchars(str_replace('_', ' ', $user_roles[0]), ENT_QUOTES) : 'GUEST'; ?>
                </p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <button onclick="toggleDarkMode()" class="h-7 w-7 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center hover:scale-105 transition">
                    <span id="theme_icon_sun" class="hidden dark:block text-[11px]">☀️</span>
                    <span id="theme_icon_moon" class="block dark:hidden text-[11px]">🌙</span>
                </button>
                <a href="logout.php" class="h-7 w-7 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center hover:bg-rose-50 text-slate-500 transition">💡</a>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col gap-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto pr-1">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 p-4 flex items-center justify-between shadow-sm shrink-0 transition-colors duration-300">
            <h2 id="page_title" class="text-base font-extrabold tracking-tight text-slate-900 dark:text-white pl-2">Dashboard Overview</h2>
            <div class="flex items-center gap-3">
                <div class="relative hidden sm:block">
                    <input type="text" id="global_top_search" oninput="aksiCariNamaGlobal()" class="w-48 lg:w-60 bg-slate-100/80 dark:bg-slate-800 text-xs px-4 py-2 rounded-full outline-none dark:text-white" placeholder="Cari siswa cepat...">
                </div>
                <?php if (in_array('guru', $user_roles) || in_array('super_admin', $user_roles)): ?>
                <button onclick="switchMenu('section_jurnal')" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold px-4 py-2 rounded-full transition hover:opacity-90">
                    + Input Kasus
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['notif'])): ?>
        <div class="p-4 rounded-2xl border flex items-center justify-between shrink-0 <?php echo $_SESSION['notif']['type'] === 'success' ? 'bg-emerald-50 dark:bg-emerald-950 border-emerald-100 dark:border-emerald-900 text-emerald-900' : 'bg-rose-50 text-rose-900'; ?>">
            <p class="text-xs font-semibold"><?php echo htmlspecialchars($_SESSION['notif']['message'], ENT_QUOTES); ?></p>
            <button onclick="this.parentElement.remove()" class="text-sm font-bold opacity-40 hover:opacity-100">&times;</button>
        </div>
        <?php unset($_SESSION['notif']); ?>
        <?php endif; ?>

        <div class="flex-1 flex flex-col gap-4">
            <div id="section_dashboard" class="grid grid-cols-1 xl:grid-cols-3 gap-4 target-content-section">
                <div class="xl:col-span-2 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block mb-1">Siswa Aman</span>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white"><?php echo $count_hijau; ?></span>
                                <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Zona Hijau</span>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block mb-1">Status Waspada</span>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white"><?php echo $count_kuning; ?></span>
                                <span class="text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">Zona Kuning</span>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block mb-1">Kondisi Kritis</span>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-black tracking-tight text-slate-900 dark:text-white"><?php echo $count_merah; ?></span>
                                <span class="text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full animate-pulse">Zona Merah</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm">
                        <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-4">Grafik Sebaran Risiko Karakter</h3>
                        <div class="relative h-64 w-full"><canvas id="chartStatusSiswa"></canvas></div>
                    </div>
                </div>

                <div class="xl:col-span-1 bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm flex flex-col">
                    <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-4">🚨 Tindakan Segera BK</h3>
                    <div class="space-y-2 flex-1 overflow-y-auto max-h-[340px] pr-1">
                        <?php
                        if ($conn !== null) {
                            $q_siswa = $conn->query("SELECT s.*, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.status_warna IN ('kuning', 'merah')");
                            if($q_siswa->rowCount() == 0) { 
                                echo '<div class="text-center py-12 text-slate-400 italic text-xs">Radar bersih! Belum ada kasus kritis terdeteksi.</div>';
                            } else { 
                                while($siswa = $q_siswa->fetch(PDO::FETCH_ASSOC)) { 
                                    ?>
                        <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-2xl border flex justify-between items-center gap-2">
                            <div class="truncate">
                                <h4 class="font-bold text-xs text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($siswa['nama'], ENT_QUOTES); ?></h4>
                                <p class="text-[10px] text-slate-400 font-semibold mt-0.5"><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-', ENT_QUOTES); ?></p>
                            </div>
                            <?php if (count(array_intersect(['bk', 'super_admin'], $user_roles)) > 0): ?>
                            <button onclick="openModalBK('<?php echo $siswa['id']; ?>', '<?php echo htmlspecialchars(addslashes($siswa['nama']), ENT_QUOTES); ?>')" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-[10px] font-bold py-1.5 px-3 rounded-full shrink-0 transition hover:opacity-80">Intervensi</button>
                            <?php // Perubahan di v2.5.0: Indikator Peringatan SP
                            elseif ((int)$siswa['status_sp'] > 0): ?>
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 uppercase animate-pulse">SP <?php echo $siswa['status_sp']; ?></span>
                            <?php else: ?>
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 uppercase">Dipantau</span>
                            <?php endif; ?>
                        </div>
                        <?php 
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div id="section_jurnal" class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm target-content-section hidden space-y-4">
                <?php if (count(array_intersect(['guru', 'super_admin'], $user_roles)) > 0): ?>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-slate-50 dark:bg-slate-800 p-3 rounded-2xl border dark:border-slate-700">
                    <input type="text" id="input_cari_nama" oninput="aksiCariNama()" class="w-full sm:w-64 px-3 py-1.5 text-xs border rounded-xl bg-white dark:bg-slate-900 outline-none dark:text-white" placeholder="Ketik Nama / NISN...">
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <select id="filter_tingkat" class="p-1.5 text-xs border dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-bold outline-none">
                            <option value="">Tingkat</option>
                            <option value="X">X</option>
                            <option value="XI">XI</option>
                            <option value="XII">XII</option>
                        </select>
                        <select id="filter_jurusan" class="p-1.5 text-xs border dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-bold outline-none" disabled>
                            <option value="">Keahlian</option>
                            <option value="DKV">DKV</option>
                            <option value="RPL">RPL</option>
                        </select>
                        <select id="filter_kelas" class="p-1.5 text-xs border dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-bold outline-none" disabled>
                            <option value="">Rombel</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead>
                            <tr class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800">
                                <th class="px-4 py-3 text-center" width="5%">No</th>
                                <th class="px-4 py-3" width="20%">NISN</th>
                                <th class="px-4 py-3" width="15%">Kelas</th>
                                <th class="px-4 py-3 cursor-pointer text-slate-900 dark:text-white font-black" onclick="aksiSortirNama()">Nama Murid <span id="sort_icon">↕️</span></th>
                                <th class="px-4 py-3 text-center" width="18%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="body_tabel_siswa" class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-700 dark:text-slate-300">
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="pt-2">
                    <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">
                        <?php echo count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0 ? '📋 Log Kasus Harian Seluruh Sekolah (Hari Ini)' : '📋 Log Insiden Yang Saya Laporkan'; ?>
                    </h3>
                    <div class="overflow-x-auto bg-slate-50/50 dark:bg-slate-950/30 rounded-2xl border dark:border-slate-800">
                        <table class="min-w-full text-[11px] text-left">
                            <thead>
                                <tr class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 bg-slate-100/60 dark:bg-slate-900/60">
                                    <th class="px-4 py-2.5" width="22%">Siswa / Kelas</th>
                                    <th class="px-4 py-2.5" width="22%">Indikasi Kejadian</th>
                                    <th class="px-4 py-2.5">Kronologi / Catatan Lapangan</th>
                                    <th class="px-4 py-2.5 text-center" width="10%">Risiko</th>
                                    <th class="px-4 py-2.5 text-center" width="12%">Opsi Kunci</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-600 dark:text-slate-300">
                                <?php if(empty($log_jurnal_terkini)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400 italic">Belum ada rekaman log insiden harian yang sesuai parameter wewenang Anda.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach($log_jurnal_terkini as $log): 
                                        $is_owner    = (($log['user_id'] ?? null) == $user_id_login);
                                        $within_time = (time() - strtotime($log['created_at'] ?? 'now') <= 1800);
                                        $is_bk_admin = count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0;
                                        $boleh_koreksi = $is_bk_admin || ($is_owner && $within_time);
                                    ?>
                                <tr class="hover:bg-slate-100/50 dark:hover:bg-slate-900/40 transition">
                                    <td class="px-4 py-2.5 font-bold text-slate-900 dark:text-white">
                                        <?php echo htmlspecialchars($log['nama_siswa'], ENT_QUOTES); ?>
                                        <span class="block text-[9px] text-slate-400 font-normal"><?php echo htmlspecialchars($log['nama_kelas'] ?? '-', ENT_QUOTES); ?></span>
                                    </td>
                                    <td class="px-4 py-2.5 font-semibold text-indigo-600 dark:text-indigo-400">
                                        <?php echo htmlspecialchars($log['nama_kejadian'], ENT_QUOTES); ?></td>
                                    <td class="px-4 py-2.5 italic text-slate-500 dark:text-slate-400">
                                        "<?php echo htmlspecialchars($log['catatan'], ENT_QUOTES); ?>"</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <?php 
                                        $badge = $log['bobot_risiko'] === 'berat' ? 'bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 border-rose-100 dark:border-rose-900' : ($log['bobot_risiko'] === 'sedang' ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 border-amber-100 dark:border-amber-900' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400 border-emerald-100 dark:border-emerald-900');
                                        ?>
                                        <span class="px-2 py-0.5 rounded-md text-[9px] font-bold border uppercase <?php echo $badge; ?>"><?php echo htmlspecialchars($log['bobot_risiko'], ENT_QUOTES); ?></span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-bold">
                                        <?php if($boleh_koreksi): ?>
                                        <div class="flex items-center justify-center gap-2 text-[10px]">
                                            <button type="button" onclick="bukaModalEditJurnal('<?php echo $log['id']; ?>', '<?php echo $log['category_id']; ?>', '<?php echo htmlspecialchars(addslashes($log['catatan']), ENT_QUOTES); ?>')" class="text-indigo-600 dark:text-indigo-400 hover:underline">✏️ Edit</button>
                                            <a href="actions/proses_lapor.php?aksi=hapus&id=<?php echo $log['id']; ?>" onclick="return confirm('Hapus log kasus ini? Status warna radar murid akan dikalkulasi ulang.')" class="text-rose-600 dark:text-rose-400 hover:underline">🗑️ Hapus</a>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-slate-400 text-[9px] italic font-normal">🔒 Terkunci</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (count(array_intersect(['guru', 'super_admin'], $user_roles)) > 0): ?>
            <div id="section_mandat" class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm target-content-section hidden space-y-3">
                <?php
                if ($conn !== null) {
                    $stmt_tugas_saya = $conn->prepare("SELECT c.*, s.nama AS nama_siswa, cl.nama_kelas FROM consequences c JOIN students s ON c.student_id = s.id LEFT JOIN classes cl ON s.class_id = cl.id WHERE c.penanggung_jawab = :user_id AND c.status_tugas = 'pending'");
                    $stmt_tugas_saya->execute(['user_id' => $user_id_login]);
                    if ($stmt_tugas_saya->rowCount() == 0) {
                        echo '<p class="text-xs text-slate-400 dark:text-slate-500 italic py-10 text-center">Tidak ada agenda pemantauan lapangan untuk Anda.</p>';
                    } else {
                        while($tugas = $stmt_tugas_saya->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                <div class="p-4 bg-slate-50 dark:bg-slate-800 border rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h4 class="font-bold text-xs text-slate-900 dark:text-white">
                            <?php echo htmlspecialchars($tugas['nama_siswa'], ENT_QUOTES); ?> <span class="text-slate-400 font-medium">(<?php echo htmlspecialchars($tugas['nama_kelas'] ?? '-', ENT_QUOTES); ?>)</span></h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">🎯 <span class="font-semibold text-slate-800 dark:text-slate-200">Tugas Kedisiplinan:</span> <?php echo htmlspecialchars($tugas['deskripsi_tugas'], ENT_QUOTES); ?></p>
                    </div>
                    <a href="actions/proses_selesai_tugas.php?id=<?php echo $tugas['id']; ?>&student_id=<?php echo $tugas['student_id']; ?>" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold py-2 px-4 rounded-xl text-center w-full sm:w-auto transition">Selesai</a>
                </div>
                <?php 
                        }
                    }
                }
                ?>
            </div>
            <?php endif; ?>

            <div id="section_radar_bk" class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm target-content-section hidden space-y-4">
                <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">🎯 Monitoring Radar Urgent BK</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    if ($conn !== null) {
                        $q_rdr = $conn->query("SELECT s.*, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.status_warna IN ('kuning', 'merah') ORDER BY s.status_warna DESC");
                        while($r = $q_rdr->fetch(PDO::FETCH_ASSOC)) {
                            $col = $r['status_warna'] == 'merah' ? 'border-rose-200 bg-rose-50/50' : 'border-amber-200 bg-amber-50/50';
                            echo "<div class='p-4 border rounded-2xl $col text-xs flex justify-between items-center'>
                                    <div>
                                        <p class='font-black text-slate-900 dark:text-white'>" . htmlspecialchars($r['nama'], ENT_QUOTES) . "</p>
                                        <p class='text-[10px] text-slate-400 font-bold mt-1'>Kelas: " . htmlspecialchars($r['nama_kelas'] ?? '-', ENT_QUOTES) . "</p>
                                    </div>
                                    <span class='font-bold uppercase text-[9px]'>Zona " . htmlspecialchars($r['status_warna'], ENT_QUOTES) . "</span>
                                  </div>";
                        }
                    }
                    ?>
                </div>
            </div>

            <?php if (count(array_intersect(['bk', 'super_admin'], $user_roles)) > 0): ?>
            <div id="section_cetak_bk" class="bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm target-content-section hidden space-y-4">
                <div class="border-b pb-2 border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">🖨️ Pusat Layanan Dokumen & Administrasi BK</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Sistem generator pembuatan surat panggilan resmi, surat izin keluar gerbang, dan surat perjanjian karakter.</p>
                </div>

                <div class="max-w-xl bg-slate-50 dark:bg-slate-800/40 p-5 rounded-3xl border border-slate-100 space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Cari / Pilih Nama Siswa</label>
                        <div class="relative">
                            <input type="text" id="pusat_student_search" oninput="filterSiswaPusat(this.value)" onfocus="bukaDropdownPusat()" placeholder="Ketik nama atau kelas siswa..." class="w-full p-2.5 text-xs font-bold border rounded-xl bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 outline-none" autocomplete="off">
                            <div id="pusat_dropdown_hasil" class="absolute left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-white dark:bg-slate-900 border rounded-xl shadow-lg hidden z-50 divide-y">
                                <?php foreach($daftar_siswa_global as $sis): ?>
                                <div class="p-2.5 hover:bg-slate-50 cursor-pointer text-xs item-siswa-pusat transition" data-nama="<?php echo htmlspecialchars($sis['nama'] . ' ' . ($sis['nama_kelas'] ?? ''), ENT_QUOTES); ?>" onclick="pilihSiswaPusat('<?php echo $sis['id']; ?>', '<?php echo htmlspecialchars(addslashes($sis['nama']), ENT_QUOTES); ?> (<?php echo htmlspecialchars(addslashes($sis['nama_kelas'] ?? '-'), ENT_QUOTES); ?>)')">
                                    <p class="font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($sis['nama'], ENT_QUOTES); ?></p>
                                    <p class="text-[10px] text-slate-400"><?php echo htmlspecialchars($sis['nama_kelas'] ?? 'Tanpa Rombel', ENT_QUOTES); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="pusat_student_id">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tanggal Kegiatan</label>
                            <input type="date" id="pusat_tgl" class="w-full p-2.5 text-xs border rounded-xl bg-white dark:bg-slate-900 outline-none font-semibold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Waktu / Jam Eksekusi</label>
                            <input type="time" id="pusat_jam" class="w-full p-2.5 text-xs border rounded-xl bg-white dark:bg-slate-900 outline-none font-semibold dark:text-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Format Template Surat Administrasi</label>
                        <select id="pusat_tipe_surat" class="w-full p-2.5 text-xs font-bold border rounded-xl bg-white dark:bg-slate-900 text-slate-700 outline-none">
                            <option value="panggilan">✉️ Template Surat Panggilan Orang Tua / Wali</option>
                            <option value="izin">🚗 Template Surat Izin Meninggalkan Lingkungan Sekolah</option>
                            <option value="pernyataan">✍️ Template Surat Pernyataan Perjanjian Disiplin Siswa</option>
                            <option value="sp1">📜 Template Surat Peringatan 1 (SP 1)</option>
                            <option value="sp2">📜 Template Surat Peringatan 2 (SP 2)</option>
                            <option value="sp3">📜 Template Surat Peringatan 3 (SP 3)</option>
                        </select>
                    </div>
                    <button type="button" onclick="aksiCetakPusatBK()" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold py-3 px-4 rounded-xl shadow-md transition">🖨️ Bangun & Cetak Dokumen Sekarang</button>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count(array_intersect(['kepala_jurusan', 'super_admin'], $user_roles)) > 0): ?>
            <div id="section_kajur" class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm target-content-section hidden space-y-4">
                <div class="border-b pb-2">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">⚡ Lembar Validasi Kelayakan Hubungan Industri (PKL)</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Saring kelayakan magang siswa berdasarkan rekam jejak radar karakter SinergiCare.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800 text-slate-400 font-bold uppercase border-b border-slate-100">
                                <th class="px-4 py-2.5">Siswa / Kelas</th>
                                <th class="px-4 py-2.5 text-center">Status Radar</th>
                                <th class="px-4 py-2.5 text-center">Rekomendasi Penempatan PKL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y font-medium text-slate-700 dark:text-slate-300">
                            <?php
                            if ($conn !== null) {
                                $q_pkl = $conn->query("SELECT s.*, c.nama_kelas FROM students s JOIN classes c ON s.class_id = c.id ORDER BY c.nama_kelas ASC, s.nama ASC");
                                while($spkl = $q_pkl->fetch(PDO::FETCH_ASSOC)) {
                                    $status_pkl = $spkl['status_warna'] === 'merah' ? '❌ Ditangguhkan (Butuh Bimbingan)' : ($spkl['status_warna'] === 'kuning' ? '⚠️ Pantauan Khusus Industri' : '✅ Layak Berangkat DUDI');
                                    $badge_c = $spkl['status_warna'] === 'merah' ? 'bg-rose-50 text-rose-600' : ($spkl['status_warna'] === 'kuning' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600');
                                    ?>
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">
                                    <?php echo htmlspecialchars($spkl['nama'], ENT_QUOTES); ?><span class="block text-[10px] text-slate-400 font-normal"><?php echo htmlspecialchars($spkl['nama_kelas'] ?? '-', ENT_QUOTES); ?></span>
                                </td>
                                <td class="px-4 py-3 text-center"><span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase <?php echo $badge_c; ?>"><?php echo htmlspecialchars($spkl['status_warna'], ENT_QUOTES); ?></span>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-indigo-600 dark:text-indigo-400"><?php echo htmlspecialchars($status_pkl, ENT_QUOTES); ?></td>
                            </tr>
                            <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count(array_intersect(['waka_kesiswaan', 'super_admin'], $user_roles)) > 0): ?>
            <div id="section_waka" class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm target-content-section hidden space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">📊 Analitik Eksklusif: Peta Kerawanan Sekolah</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border dark:border-slate-700/60 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">🏢 Top 3 Kelas Paling Rentan Kasus</span>
                            <div class="space-y-2">
                                <?php if(empty($peta_kerawanan_kelas)): ?>
                                <p class="text-xs text-slate-400 italic py-2">Belum ada pemetaan kelas.</p>
                                <?php else: ?>
                                <?php foreach($peta_kerawanan_kelas as $pk): ?>
                                <div class="flex justify-between items-center text-xs font-semibold border-b border-slate-100 dark:border-slate-800/40 pb-2 mb-2 last:border-0 last:pb-0">
                                    <span class="text-slate-700 dark:text-slate-300">🏢 Kelas <?php echo htmlspecialchars($pk['nama_kelas'], ENT_QUOTES); ?></span>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-600 font-bold"><?php echo $pk['total_cases']; ?> Insiden</span>
                                        <button onclick="alert('Peringatan dikirim ke Wali Kelas: <?php echo htmlspecialchars(addslashes($pk['nama_kelas']), ENT_QUOTES); ?> untuk Home Visit')" class="text-[9px] bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200 transition">
                                            📢 Tugaskan
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border dark:border-slate-700/60 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">⚠️ Tren Pelanggaran Utama Bulan Ini</span>
                            <div class="space-y-2">
                                <?php if(empty($tren_pelanggaran)): ?>
                                <p class="text-xs text-slate-400 italic py-2">Kondisi sekolah kondusif teratur.</p>
                                <?php else: ?>
                                <?php foreach($tren_pelanggaran as $tp): ?>
                                <div class="flex justify-between items-center text-xs font-semibold">
                                    <span class="text-slate-700 dark:text-slate-300 truncate max-w-[180px]">⚖️ <?php echo htmlspecialchars($tp['nama_kejadian'], ENT_QUOTES); ?></span>
                                    <span class="text-indigo-600 font-bold"><?php echo $tp['jumlah']; ?>x Terjadi</span>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-slate-100 dark:border-slate-800">

                <div>
                    <div class="border-b pb-2 dark:border-slate-800">
                        <h3 class="text-sm font-black text-slate-900 dark:text-white">🛡️ Panel Otorisasi Sanksi & Pemberhentian Siswa</h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Meja kerja penandatanganan Surat Peringatan (SP) dan penegakan hukum pemutusan hak studi siswa kritis.</p>
                    </div>

                    <div class="space-y-3 mt-4 max-w-3xl">
                        <?php
                        if ($conn !== null) {
                            $q_waka_list = $conn->query("SELECT s.*, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.status_warna = 'merah' AND s.status_sp < 4 ORDER BY s.status_sp DESC, s.nama ASC");
                            if($q_waka_list->rowCount() == 0) {
                                echo '<p class="text-xs italic text-slate-400 py-8 text-center bg-slate-50 dark:bg-slate-800/20 rounded-2xl border dark:border-slate-800">Radar Bersih! Tidak ada ajuan siswa Zona Merah yang membutuhkan tindakan penegakan hukum kesiswaan.</p>';
                            } else {
                                while($sw = $q_waka_list->fetch(PDO::FETCH_ASSOC)) {
                                    $current_sp = (int)$sw['status_sp'];
                                    
                                    if ($current_sp === 0) {
                                        $btn_text = "⚠️ Terbitkan Surat Peringatan 1 (SP 1)";
                                        $btn_color = "bg-amber-600 hover:bg-amber-700 text-white";
                                        $action_url = "actions/proses_waka_sp.php?aksi=terbit_sp&student_id=" . $sw['id'] . "&level=1";
                                    } elseif ($current_sp === 1) {
                                        $btn_text = "🔥 Naikkan Ke Peringatan 2 (SP 2)";
                                        $btn_color = "bg-orange-600 hover:bg-orange-700 text-white";
                                        $action_url = "actions/proses_waka_sp.php?aksi=terbit_sp&student_id=" . $sw['id'] . "&level=2";
                                    } elseif ($current_sp === 2) {
                                        $btn_text = "🚨 Terbitkan SP 3 / Panggilan Pleno";
                                        $btn_color = "bg-rose-600 hover:bg-rose-700 text-white";
                                        $action_url = "actions/proses_waka_sp.php?aksi=terbit_sp&student_id=" . $sw['id'] . "&level=3";
                                    } elseif ($current_sp === 3) {
                                        $btn_text = "❌ EKSEKUSI PEMBERHENTIAN SISWA (DO)";
                                        $btn_color = "bg-red-700 hover:bg-red-800 text-white animate-pulse";
                                        $action_url = "actions/proses_waka_sp.php?aksi=drop_out&student_id=" . $sw['id'];
                                    }
                                    ?>
                                    <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shadow-xs">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($sw['nama'], ENT_QUOTES); ?></h4>
                                                <span class="text-[10px] bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded font-bold text-slate-500"><?php echo htmlspecialchars($sw['nama_kelas'] ?? '-', ENT_QUOTES); ?></span>
                                            </div>
                                            <p class="text-[11px] text-slate-400 mt-1 font-semibold">
                                                Status Yuridis Saat Ini:
                                                <span class="text-indigo-600 dark:text-indigo-400">
                                                    <?php echo $current_sp === 0 ? 'Siswa Bersih (Belum ada SP)' : 'Telah Menerima SP ' . $current_sp; ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="w-full sm:w-auto shrink-0">
                                            <a href="<?php echo $action_url; ?>" onclick="return confirm('Apakah Anda yakin ingin mengeksekusi tindakan hukum ini terhadap siswa terkait? Keputusan ini akan dicatat dalam lembar riwayat permanen sekolah.')" class="block text-center text-xs font-bold py-2.5 px-4 rounded-xl transition <?php echo $btn_color; ?>">
                                                <?php echo $btn_text; ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count(array_intersect(['admin', 'super_admin'], $user_roles)) > 0): ?>
            <div id="section_master_data" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 target-content-section hidden">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 shadow-sm space-y-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">👤 Registrasi Siswa Tunggal</span>
                    <form action="actions/proses_admin.php?aksi=tambah_siswa" method="POST" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="nisn" placeholder="Nomor NISN" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" required>
                            <select name="class_id" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none text-slate-700 dark:text-slate-300 font-semibold" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach($daftar_kelas_global as $cls) { echo "<option value='{$cls['id']}'>" . htmlspecialchars($cls['nama_kelas'], ENT_QUOTES) . "</option>"; } ?>
                            </select>
                        </div>
                        <input type="text" name="nama" placeholder="Nama Lengkap Siswa" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" required>
                        <button type="submit" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold py-2.5 rounded-xl">Daftarkan Murid</button>
                    </form>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 shadow-sm space-y-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">📂 Impor Massal Spreadsheet</span>
                    <form action="actions/proses_admin.php?aksi=import_csv" method="POST" enctype="multipart/form-data" class="space-y-3">
                        <div>
                            <select name="tipe_impor" onchange="document.getElementById('link_template_download').href = 'actions/download_template.php?type=' + this.value" class="w-full p-2 text-xs font-bold border rounded-xl bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                <option value="siswa">Data Siswa Baru</option>
                                <option value="staf">Otoritas Staf & Guru Piket</option>
                            </select>
                        </div>
                        <input type="file" name="file_csv" accept=".csv" class="w-full text-xs text-slate-500 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-slate-100" required>
                        <div class="flex gap-2 pt-1">
                            <a id="link_template_download" href="actions/download_template.php?type=siswa" class="w-1/2 bg-slate-100 text-slate-800 text-center text-[11px] font-bold py-2.5 rounded-xl border block">📥 Template</a>
                            <button type="submit" class="w-1/2 bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-[11px] font-bold py-2.5 rounded-xl">📤 Unggah</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 shadow-sm space-y-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">🏫 Manajemen Struktur Kelas</span>
                    <form action="actions/proses_admin.php?aksi=tambah_kelas" method="POST" class="space-y-3">
                        <input type="text" name="nama_kelas" placeholder="Contoh: XI DKV 1" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" required>
                        <button type="submit" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold py-2.5 rounded-xl">Simpan Kelas Baru</button>
                    </form>
                    <hr class="my-2">
                    <div class="max-h-32 overflow-y-auto divide-y text-xs font-semibold pr-1">
                        <?php foreach($daftar_kelas_global as $c): ?>
                        <div class="py-1.5 flex justify-between items-center text-slate-700 dark:text-slate-300">
                            <span>🏢 <?php echo htmlspecialchars($c['nama_kelas'], ENT_QUOTES); ?></span>
                            <button type="button" onclick="bukaModalEdit('kelas', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars(addslashes($c['nama_kelas']), ENT_QUOTES); ?>')" class="text-indigo-600 font-bold text-[10px]">✏️ Edit</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php // Fitur v2.5.0: Input Staf Manual ?>
                <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 shadow-sm space-y-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">👨‍🏫 Otoritas Staf & Guru Piket</span>
                    <form action="actions/proses_admin.php?aksi=tambah_guru" method="POST" class="space-y-3">
                        <input type="text" name="nama_guru" placeholder="Nama Lengkap Staf" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" required>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="username" placeholder="Username" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" required>
                            <input type="password" name="password" placeholder="Password" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none" required>
                        </div>
                        <select name="role" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 font-bold text-slate-700 dark:text-slate-300" required>
                            <option value="guru">Guru Piket / Wali Kelas</option>
                            <option value="bk">Bimbingan Konseling (BK)</option>
                            <option value="waka_kesiswaan">Waka Kesiswaan</option>
                            <option value="kepala_jurusan">Kepala Jurusan (Kajur)</option>
                            <option value="admin">Administrator Sistem</option>
                        </select>
                        <button type="submit" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold py-2.5 rounded-xl">Daftarkan Akun</button>
                    </form>
                </div>

                <?php // Fitur v2.5.0: Input Aturan Manual ?>
                <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 shadow-sm space-y-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">⚖️ Buku Aturan & Bobot Risiko</span>
                    <form action="actions/proses_admin.php?aksi=tambah_kategori" method="POST" class="space-y-3">
                        <input type="text" name="nama_kejadian" placeholder="Nama Pelanggaran" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 dark:text-white" required>
                        <select name="bobot_risiko" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 font-bold text-slate-700 dark:text-slate-300" required>
                            <option value="ringan">Ringan (Hijau)</option>
                            <option value="sedang">Sedang (Kuning)</option>
                            <option value="berat">Berat (Merah)</option>
                        </select>
                        <button type="submit" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold py-2.5 rounded-xl">Tambahkan Kasus</button>
                    </form>
                </div>

                <?php // Fitur v2.5.0: Form Upload Logo Sekolah Terintegrasi v3.0.0 ?>
                <div class="bg-white dark:bg-slate-900 p-5 rounded-[2rem] border border-slate-200/60 shadow-sm space-y-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">🖼️ Logo Instansi Sekolah Resmi</span>
                    <form action="actions/proses_admin.php?aksi=upload_logo" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-800 p-2.5 rounded-xl border">
                            <img src="actions/logo_sekolah.png?t=<?php echo time(); ?>" onerror="this.src='https://placehold.co/100x100?text=KOP'" class="h-10 w-10 object-contain bg-white rounded border">
                            <input type="file" name="logo_file" accept=".png" class="w-full text-xs text-slate-500" required>
                        </div>
                        <button type="submit" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold py-2.5 rounded-xl">🔄 Perbarui Logo Instansi</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="modal_edit_master" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-end sm:items-center justify-center hidden z-50 p-2 sm:p-4">
        <div class="bg-white dark:bg-slate-900 rounded-t-[2rem] sm:rounded-[2rem] shadow-xl max-w-md w-full overflow-hidden border">
            <div class="p-5 border-b bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center">
                <h3 id="edit_master_title" class="font-extrabold text-xs uppercase text-slate-400 tracking-wider">Form Perubahan Data</h3>
                <button onclick="closeModalEditMaster()" class="text-xl font-bold p-1 text-slate-400">&times;</button>
            </div>
            <form id="form_edit_master" action="actions/proses_admin.php" method="POST" class="p-5 space-y-4">
                <input type="hidden" id="edit_master_id" name="id">
                <div>
                    <label id="edit_master_label" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Konten</label>
                    <input type="text" id="edit_master_nama" name="nama" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" required>
                </div>
                <div id="edit_master_guru_fields" class="hidden space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" id="edit_guru_username" name="username" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white">
                        <input type="password" id="edit_guru_password" name="password" placeholder="Sandi baru (jika diubah)" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none">
                    </div>
                    <select id="edit_guru_role" name="role" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 font-bold text-slate-700 dark:text-slate-300">
                        <option value="guru">Guru Piket / Wali Kelas</option>
                        <option value="bk">Bimbingan Konseling (BK)</option>
                        <option value="waka_kesiswaan">Waka Kesiswaan</option>
                        <option value="kepala_jurusan">Kepala Jurusan (Kajur)</option>
                        <option value="admin">Administrator Sistem</option>
                    </select>
                </div>
                <div id="edit_master_extra_field" class="hidden">
                    <select id="edit_master_bobot" name="bobot_risiko" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 font-bold dark:bg-slate-800">
                        <option value="ringan">Ringan (Hijau)</option>
                        <option value="sedang">Sedang (Kuning)</option>
                        <option value="berat">Berat (Merah)</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" onclick="closeModalEditMaster()" class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold py-2 px-4 rounded-xl text-xs">Batal</button>
                    <button type="submit" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold py-2 px-5 rounded-xl text-xs shadow">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal_catat" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-end sm:items-center justify-center hidden z-50 p-2 sm:p-4">
        <div class="bg-white dark:bg-slate-900 rounded-t-[2rem] sm:rounded-[2rem] shadow-xl max-w-md w-full overflow-hidden border">
            <div class="p-5 border-b bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center">
                <h3 class="font-extrabold text-xs uppercase text-slate-400 tracking-wider">Form Pencatatan Jurnal</h3>
                <button onclick="closeModal()" class="text-xl font-bold p-1 text-slate-400">&times;</button>
            </div>
            <form action="actions/proses_lapor.php" method="POST" class="p-5 space-y-4">
                <input type="hidden" id="modal_student_id" name="student_id">
                <p class="text-sm font-black text-slate-900 dark:text-white">Nama Siswa: <span id="modal_nama_siswa" class="text-indigo-600"></span></p>
                <div>
                    <select name="category_id" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 font-bold text-slate-700 dark:text-slate-300" required>
                        <option value="">-- Pilih Kasus Aturan --</option>
                        <?php if ($conn !== null) { $q_cat = $conn->query("SELECT * FROM violation_categories"); while($cat = $q_cat->fetch(PDO::FETCH_ASSOC)) { echo "<option value='{$cat['id']}'>" . htmlspecialchars($cat['nama_kejadian'], ENT_QUOTES) . " ({$cat['bobot_risiko']})</option>"; } } ?>
                    </select>
                </div>
                <div>
                    <textarea name="catatan" rows="3" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" placeholder="Tulis rincian kejadian lapangan..." required></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" onclick="closeModal()" class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold py-2 px-4 rounded-xl text-xs">Batal</button>
                    <button type="submit" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold py-2 px-5 rounded-xl text-xs">Simpan Data Jurnal</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal_bk" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-end sm:items-center justify-center hidden z-50 p-2 sm:p-4">
        <div class="bg-white dark:bg-slate-900 rounded-t-[2rem] sm:rounded-[2rem] shadow-xl max-w-lg w-full overflow-hidden flex flex-col max-h-[90vh] border">
            <div class="p-4 border-b bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center shrink-0">
                <h3 class="font-extrabold text-xs uppercase text-slate-400 tracking-wider">
                    <?php echo count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0 ? 'Panel Sidang Konseling BK' : '📋 Rekam Jejak Karakter Siswa'; ?>
                </h3>
                <button onclick="closeModalBK()" class="text-xl font-bold p-1 text-slate-400">&times;</button>
            </div>
            <div class="p-5 overflow-y-auto space-y-4">
                <p class="text-sm font-black text-slate-900 dark:text-white">Siswa Terkait: <span id="bk_nama_siswa" class="text-indigo-600"></span></p>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">📜 Lembar Rekam Kasus Digital</h4>
                        <button type="button" onclick="aksiCetak()" class="bg-slate-100 dark:bg-slate-800 text-[10px] font-bold py-1 px-2.5 rounded-lg border dark:border-slate-700/60">🖨️ Rekap Log</button>
                    </div>
                    <div id="bk_riwayat_box" class="space-y-1.5 max-h-40 overflow-y-auto bg-slate-50/50 p-2 rounded-xl border dark:border-slate-800"></div>
                </div>

                <?php if (count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0): ?>
                <hr class="border-slate-100 dark:border-slate-800">
                <div class="flex flex-col gap-2 mb-2">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">📜 Dokumen & Administrasi BK</h4>
                    <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border dark:border-slate-700 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" id="bk_tgl_panggil" class="w-full p-2 text-[11px] border rounded-lg bg-white dark:bg-slate-900 font-semibold dark:text-white">
                            <input type="time" id="bk_jam_panggil" class="w-full p-2 text-[11px] border rounded-lg bg-white dark:bg-slate-900 font-semibold dark:text-white">
                        </div>
                        <div class="flex gap-2">
                            <select id="bk_tipe_surat" class="flex-1 p-2 text-[11px] font-bold border rounded-lg bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 outline-none">
                                <option value="panggilan">✉️ Surat Panggilan Orang Tua</option>
                                <option value="izin">🚗 Surat Izin Meninggalkan Sekolah</option>
                                <option value="pernyataan">✍️ Surat Pernyataan Siswa (Perjanjian)</option>
                                <option value="sp1">📜 Draf Surat Peringatan 1 (SP 1)</option>
                                <option value="sp2">📜 Draf Surat Peringatan 2 (SP 2)</option>
                                <option value="sp3">📜 Draf Surat Peringatan 3 (SP 3)</option>
                            </select>
                            <button type="button" onclick="aksiCetakAdministrasiBK()" class="bg-indigo-600 text-white text-[11px] font-bold py-2 px-4 rounded-lg">🖨️ Cetak</button>
                        </div>
                    </div>
                </div>

                <form action="actions/proses_tindakan_bk.php" method="POST" class="space-y-3">
                    <input type="hidden" id="bk_student_id" name="student_id">
                    <textarea name="deskripsi_tugas" rows="2" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 dark:text-white" placeholder="Tulis petunjuk penugasan karakter..." required></textarea>
                    <select name="penanggung_jawab" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 text-slate-700 font-bold dark:bg-slate-800 dark:text-slate-300" required>
                        <option value="">-- Pilih Otoritas Guru --</option>
                        <?php if ($conn !== null) { $q_guru = $conn->query("SELECT id, nama FROM staf_sekolah"); while($guru = $q_guru->fetch(PDO::FETCH_ASSOC)) { echo "<option value='{$guru['id']}'>" . htmlspecialchars($guru['nama'], ENT_QUOTES) . "</option>"; } } ?>
                    </select>
                    <button type="submit" class="w-full bg-slate-900 text-white font-bold py-2.5 rounded-xl text-xs mt-2 dark:bg-white dark:text-slate-900">Kirim Mandat ke Lapangan</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modal_edit_jurnal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-end sm:items-center justify-center hidden z-50 p-2 sm:p-4">
        <div class="bg-white dark:bg-slate-900 rounded-t-[2rem] sm:rounded-[2rem] shadow-xl max-w-md w-full overflow-hidden border">
            <div class="p-5 border-b bg-slate-50 dark:bg-slate-800/50 flex justify-between items-center">
                <h3 class="font-extrabold text-xs uppercase text-slate-400 tracking-wider">✏️ Koreksi Catatan Kasus</h3>
                <button onclick="closeModalEditJurnal()" class="text-xl font-bold p-1 text-slate-400">&times;</button>
            </div>
            <form action="actions/proses_lapor.php?aksi=edit" method="POST" class="p-5 space-y-4">
                <input type="hidden" id="edit_jurnal_id" name="id">
                <select id="edit_jurnal_category_id" name="category_id" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 font-bold text-slate-700 dark:text-slate-300" required>
                    <?php foreach($daftar_kategori_global as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nama_kejadian'], ENT_QUOTES); ?> (<?php echo htmlspecialchars($cat['bobot_risiko'], ENT_QUOTES); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <textarea id="edit_jurnal_catatan" name="catatan" rows="3" class="w-full p-2.5 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800 outline-none dark:text-white" required></textarea>
                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" onclick="closeModalEditJurnal()" class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold py-2 px-4 rounded-xl text-xs">Batal</button>
                    <button type="submit" class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold py-2 px-5 rounded-xl text-xs shadow">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const userBisaLihatRiwayat = <?php echo count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0 ? 'true' : 'false'; ?>;
    let chartInstanceGlobal = null;

    function toggleDarkMode() {
        const htmlNode = document.documentElement;
        const isDarkNow = htmlNode.classList.toggle('dark');
        localStorage.setItem('theme', isDarkNow ? 'dark' : 'light');
        if (chartInstanceGlobal) {
            const textCol = isDarkNow ? '#94a3b8' : '#64748b';
            const gridCol = isDarkNow ? '#334155' : '#f1f5f9';
            if(chartInstanceGlobal.options.scales && chartInstanceGlobal.options.scales.y) {
                chartInstanceGlobal.options.scales.y.ticks.color = textCol;
                chartInstanceGlobal.options.scales.x.ticks.color = textCol;
                chartInstanceGlobal.options.scales.y.grid.color = gridCol;
                chartInstanceGlobal.update();
            }
        }
    }

    function bukaModalEdit(tipe, id, nama, p1 = '', p2 = '', p3 = '') {
        const modal = document.getElementById('modal_edit_master');
        const form = document.getElementById('form_edit_master');
        const title = document.getElementById('edit_master_title');
        const label = document.getElementById('edit_master_label');
        const inputNama = document.getElementById('edit_master_nama');
        const inputId = document.getElementById('edit_master_id');
        const extraField = document.getElementById('edit_master_extra_field');
        const selectBobot = document.getElementById('edit_master_bobot');
        const guruFields = document.getElementById('edit_master_guru_fields');
        const inputUsername = document.getElementById('edit_guru_username');
        const inputPassword = document.getElementById('edit_guru_password');
        const selectRole = document.getElementById('edit_guru_role');

        if (!modal) return;
        inputId.value = id;
        inputNama.value = nama;
        extraField.classList.add('hidden');
        if (guruFields) guruFields.classList.add('hidden');

        // v3.0.0 Architecture Paths
        if (tipe === 'kelas') {
            title.innerText = '⚙️ Edit Struktur Kelas';
            label.innerText = 'Nama Kelas Baru';
            form.action = 'actions/proses_admin.php?aksi=edit_kelas';
        } else if (tipe === 'guru') {
            title.innerText = '⚙️ Edit Otoritas Akun Pengguna';
            label.innerText = 'Nama Lengkap Personel';
            form.action = 'actions/proses_admin.php?aksi=edit_guru';
            if (guruFields) guruFields.classList.remove('hidden');
            if (inputUsername) inputUsername.value = p1;
            if (inputPassword) inputPassword.value = '';
            if (selectRole) selectRole.value = p3;
        } else if (tipe === 'kategori') {
            title.innerText = '⚙️ Edit Buku Aturan Pelanggaran';
            label.innerText = 'Nama Kejadian / Kasus';
            form.action = 'actions/proses_admin.php?aksi=edit_kategori';
            extraField.classList.remove('hidden');
            if (selectBobot) selectBobot.value = p1;
        }
        modal.classList.remove('hidden');
    }

    function closeModalEditMaster() {
        if (document.getElementById('modal_edit_master')) document.getElementById('modal_edit_master').classList.add('hidden');
    }

    function switchMenu(targetSectionId) {
        const targetNode = document.getElementById(targetSectionId);
        if (!targetNode) return;

        document.querySelectorAll('.target-content-section').forEach(sc => sc.classList.add('hidden'));
        targetNode.classList.remove('hidden');

        document.querySelectorAll('.target-menu-btn').forEach(btn => {
            btn.classList.remove('bg-slate-900', 'dark:bg-white', 'text-white', 'dark:text-slate-900', 'font-bold');
            btn.classList.add('text-slate-500', 'dark:text-slate-400', 'font-semibold');
        });

        const activeSidebarButton = document.getElementById('btn_nav_' + targetSectionId);
        if (activeSidebarButton) {
            activeSidebarButton.classList.add('bg-slate-900', 'dark:bg-white', 'text-white', 'dark:text-slate-900', 'font-bold');
        }

        const headerTitles = {
            'section_dashboard': 'Dashboard Overview',
            'section_jurnal': 'Buku Jurnal Insiden Harian',
            'section_mandat': 'Mandat Pengawasan Lapangan',
            'section_master_data': 'Manajemen Master Data Sekolah',
            'section_radar_bk': 'Radar Urgent BK',
            'section_cetak_bk': 'Pusat Administrasi & Cetak Surat BK',
            'section_kajur': 'Dashboard Kepala Jurusan',
            'section_waka': 'Otoritas Validasi Waka Kesiswaan'
        };
        document.getElementById('page_title').innerText = headerTitles[targetSectionId] || 'SinergiCare';
        if (window.innerWidth < 1024 && document.getElementById('sidebar_links')) {
            document.getElementById('sidebar_links').classList.add('hidden');
        }
    }

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar_links');
        if (sidebar) sidebar.classList.toggle('hidden');
    }

    const tingkat = document.getElementById('filter_tingkat');
    const r_jurusan = document.getElementById('filter_jurusan');
    const r_kelas = document.getElementById('filter_kelas');
    const bodyTabelSiswa = document.getElementById('body_tabel_siswa');
    const inputCariNama = document.getElementById('input_cari_nama');
    const topSearchInput = document.getElementById('global_top_search');
    const sortIcon = document.getElementById('sort_icon');

    let masterSiswaLokal = [];
    let dataTabelAktif = [];
    let statusSortirAsc = true;

    document.addEventListener('DOMContentLoaded', function() {
        if (bodyTabelSiswa) {
            ConnetionEngineRun();
        }
        const canvasCtx = document.getElementById('chartStatusSiswa');
        if (canvasCtx) {
            try {
                chartInstanceGlobal = new Chart(canvasCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Aman', 'Waspada', 'Kritis'],
                        datasets: [{
                            data: [<?php echo (int)$count_hijau; ?>, <?php echo (int)$count_kuning; ?>, <?php echo (int)$count_merah; ?>],
                            backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                            borderRadius: 10,
                            barThickness: 40
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });
            } catch (e) {
                console.error(e);
            }
        }
    });

    function ConnetionEngineRun() {
        if (!bodyTabelSiswa) return;
        // v3.0.0 Architecture Paths: Masuk folder api/
        fetch('api/get_siswa.php')
            .then(response => response.json())
            .then(data => {
                masterSiswaLokal = data;
                dataTabelAktif = [...masterSiswaLokal];
                renderDataKeTabel(dataTabelAktif);
            }).catch(err => console.error(err));
    }

    function renderDataKeTabel(dataSiswa) {
        if (!bodyTabelSiswa) return;
        bodyTabelSiswa.innerHTML = '';
        if (dataSiswa.length > 0) {
            dataSiswa.forEach((siswa, index) => {
                const namaAman = siswa.nama.replace(/'/g, "\\'");
                let tombolRiwayat = '';
                if (userBisaLihatRiwayat) {
                    tombolRiwayat = `<button onclick="openModalBK('${siswa.id}', '${namaAman}')" class="bg-indigo-50 text-indigo-600 text-[11px] font-bold py-1.5 px-3 rounded-xl border border-indigo-100 transition">📋 Riwayat</button>`;
                }
                bodyTabelSiswa.innerHTML += `
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition border-b">
                            <td class="px-4 py-3 font-bold text-slate-300 text-center">${index + 1}</td>
                            <td class="px-4 py-3 font-mono text-slate-400">${siswa.nisn}</td>
                            <td class="px-4 py-3"><span class="bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-[10px] font-bold">${siswa.nama_kelas ? siswa.nama_kelas : '-'}</span></td>
                            <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">${siswa.nama}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button onclick="bukaModalCatatSiswa(${index})" class="bg-slate-100 text-slate-800 text-[11px] font-bold py-1.5 px-3 rounded-xl border transition">✏️ Catat</button>
                                    ${tombolRiwayat}
                                </div>
                            </td>
                        </tr>`;
            });
        } else {
            bodyTabelSiswa.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400 italic">Siswa tidak ditemukan.</td></tr>';
        }
    }

    function bukaModalCatatSiswa(indeksLokal) {
        const dataSiswa = dataTabelAktif[indeksLokal];
        if (!dataSiswa) return;
        document.getElementById('modal_student_id').value = dataSiswa.id;
        document.getElementById('modal_nama_siswa').innerText = dataSiswa.nama;
        document.getElementById('modal_catat').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modal_catat').classList.add('hidden');
    }

    function aksiCariNama() {
        if (!inputCariNama) return;
        const keyword = inputCariNama.value.toLowerCase();
        dataTabelAktif = masterSiswaLokal.filter(s => s.nama.toLowerCase().includes(keyword) || s.nisn.toLowerCase().includes(keyword));
        renderDataKeTabel(dataTabelAktif);
    }

    function aksiCariNamaGlobal() {
        if (!topSearchInput) return;
        const keyword = topSearchInput.value.toLowerCase();
        switchMenu('section_jurnal');
        if (inputCariNama) {
            inputCariNama.value = topSearchInput.value;
        }
        dataTabelAktif = masterSiswaLokal.filter(s => s.nama.toLowerCase().includes(keyword) || s.nisn.toLowerCase().includes(keyword));
        renderDataKeTabel(dataTabelAktif);
    }

    function aksiSortirNama() {
        if (dataTabelAktif.length === 0) return;
        statusSortirAsc = !statusSortirAsc;
        if (sortIcon) sortIcon.innerText = statusSortirAsc ? '🔼 A-Z' : '🔽 Z-A';
        dataTabelAktif.sort((a, b) => statusSortirAsc ? a.nama.localeCompare(b.nama) : b.nama.localeCompare(a.nama));
        renderDataKeTabel(dataTabelAktif);
    }

    function updateFilterKelas() {
        const valTingkat = tingkat ? tingkat.value : "";
        const valJurusan = r_jurusan ? r_jurusan.value : "";
        const valKelas = r_kelas ? r_kelas.value : "";

        if (!valTingkat && !valJurusan && !valKelas) {
            dataTabelAktif = [...masterSiswaLokal];
        } else {
            dataTabelAktif = masterSiswaLokal.filter(siswa => {
                const namaKelas = siswa.nama_kelas ? siswa.nama_kelas.trim().toUpperCase() : "";
                if (!namaKelas) return false;
                const parts = namaKelas.split(/\s+/);
                if (valTingkat && parts[0] !== valTingkat.toUpperCase()) return false;
                if (valJurusan && !namaKelas.includes(valJurusan.toUpperCase())) return false;
                if (valKelas && parts[parts.length - 1] !== valKelas.toUpperCase()) return false;
                return true;
            });
        }
        if (inputCariNama) inputCariNama.value = "";
        renderDataKeTabel(dataTabelAktif);
    }

    if (tingkat) {
        tingkat.addEventListener('change', function() {
            if (this.value) {
                if (r_jurusan) r_jurusan.disabled = false;
            } else {
                if (r_jurusan) { r_jurusan.disabled = true; r_jurusan.value = ""; }
                if (r_kelas) { r_kelas.disabled = true; r_kelas.value = ""; }
            }
            updateFilterKelas();
        });
        if (r_jurusan) {
            r_jurusan.addEventListener('change', function() {
                if (this.value) {
                    if (r_kelas) r_kelas.disabled = false;
                } else {
                    if (r_kelas) { r_kelas.disabled = true; r_kelas.value = ""; }
                }
                updateFilterKelas();
            });
        }
        if (r_kelas) {
            r_kelas.addEventListener('change', function() {
                updateFilterKelas();
            });
        }
    }

    const modalBK = document.getElementById('modal_bk');

    function openModalBK(id, nama) {
        if (!modalBK) return;
        document.getElementById('bk_student_id').value = id;
        if (document.getElementById('bk_nama_siswa')) document.getElementById('bk_nama_siswa').innerText = nama;
        const riwayatBox = document.getElementById('bk_riwayat_box');
        riwayatBox.innerHTML = '<p class="text-[11px] text-slate-400 font-medium p-2">Menarik data kasus...</p>';
        modalBK.classList.remove('hidden');

        // v3.0.0 Architecture Paths: Masuk folder api/
        fetch(`api/get_riwayat.php?student_id=${id}&t=${Date.now()}`).then(res => res.json()).then(data => {
            riwayatBox.innerHTML = '';
            if (data.length > 0) {
                data.forEach(row => {
                    const badgeStyle = row.bobot_risiko === 'berat' ?
                        'bg-rose-50 text-rose-700 border-rose-100' : (row.bobot_risiko === 'sedang' ?
                            'bg-amber-50 text-amber-700 border-amber-100' :
                            'bg-blue-50 text-blue-700 border-blue-100');
                    riwayatBox.innerHTML += `<div class="bg-white dark:bg-slate-900 p-2 rounded-xl border text-[11px] mb-1"><strong>${row.nama_kejadian}</strong> <span class="px-1.5 py-0.2 rounded font-bold border ${badgeStyle}">${row.bobot_risiko}</span><p class="text-slate-400 italic mt-0.5">"${row.catatan}"</p></div>`;
                });
            } else {
                riwayatBox.innerHTML = '<p class="text-[11px] text-slate-400 text-center py-3">Siswa bersih.</p>';
            }
        }).catch(err => {
            riwayatBox.innerHTML = '<p class="text-[11px] text-rose-500 text-center py-3">Gagal memuat riwayat.</p>';
        });
    }

    function closeModalBK() {
        if (modalBK) modalBK.classList.add('hidden');
    }

    function aksiCetak() {
        const sid = document.getElementById('bk_student_id').value;
        // v3.0.0 Architecture Paths: Cetak riwayat dialihkan ke folder prints/
        if (sid) window.open(`prints/cetak_riwayat.php?student_id=${sid}`, '_blank');
    }

    function aksiCetakAdministrasiBK() {
        const studentId = document.getElementById('bk_student_id').value;
        const tanggal = document.getElementById('bk_tgl_panggil').value;
        const jam = document.getElementById('bk_jam_panggil').value;
        const tipeSurat = document.getElementById('bk_tipe_surat').value;
        if (!tanggal || !jam) {
            alert("⚠️ Tentukan Parameter Tanggal & Jam!");
            return;
        }
        if (studentId) {
            kirimLogCetakKeDatabase(studentId, tipeSurat, tanggal, jam);
            // v3.0.0 Architecture Paths: Berkas dialihkan ke folder prints/
            window.open(`prints/cetak_administrasi.php?tipe=${tipeSurat}&student_id=${studentId}&tanggal=${tanggal}&jam=${jam}`, '_blank');
            closeModalBK();
        }
    }

    function aksiCetakPusatBK() {
        const studentId = document.getElementById('pusat_student_id').value;
        const tanggal = document.getElementById('pusat_tgl').value;
        const jam = document.getElementById('pusat_jam').value;
        const tipeSurat = document.getElementById('pusat_tipe_surat').value;

        if (!studentId) {
            alert("⚠️ Mohon pilih nama siswa terlebih dahulu!");
            return;
        }
        if (!tanggal || !jam) {
            alert("⚠️ Mohon tentukan Parameter Tanggal & Jam pelaksanaan surat!");
            return;
        }
        kirimLogCetakKeDatabase(studentId, tipeSurat, tanggal, jam);
        // v3.0.0 Architecture Paths: Berkas dialihkan ke folder prints/
        window.open(`prints/cetak_administrasi.php?tipe=${tipeSurat}&student_id=${studentId}&tanggal=${tanggal}&jam=${jam}`, '_blank');
    }

    function filterSiswaPusat(keyword) {
        const dropdown = document.getElementById('pusat_dropdown_hasil');
        if (!dropdown) return;
        dropdown.classList.remove('hidden');
        const items = document.querySelectorAll('.item-siswa-pusat');
        let cocok = 0;
        items.forEach(item => {
            const namaDanKelas = item.getAttribute('data-nama').toLowerCase();
            if (namaDanKelas.includes(keyword.toLowerCase())) {
                item.classList.remove('hidden');
                cocok++;
            } else {
                item.classList.add('hidden');
            }
        });
        if (cocok === 0) dropdown.classList.add('hidden');
    }

    function bukaDropdownPusat() {
        filterSiswaPusat(document.getElementById('pusat_student_search').value);
    }

    function pilihSiswaPusat(id, labelLengkap) {
        document.getElementById('pusat_student_id').value = id;
        document.getElementById('pusat_student_search').value = labelLengkap;
        const dropdown = document.getElementById('pusat_dropdown_hasil');
        if (dropdown) dropdown.classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('pusat_dropdown_hasil');
        const input = document.getElementById('pusat_student_search');
        if (dropdown && input && e.target !== input && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    function bukaModalEditJurnal(id, categoryId, catatan) {
        document.getElementById('edit_jurnal_id').value = id;
        document.getElementById('edit_jurnal_category_id').value = categoryId;
        document.getElementById('edit_jurnal_catatan').value = catatan;
        document.getElementById('modal_edit_jurnal').classList.remove('hidden');
    }

    function closeModalEditJurnal() {
        document.getElementById('modal_edit_jurnal').classList.add('hidden');
    }

    function kirimLogCetakKeDatabase(studentId, tipe, tgl, jam) {
        let formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('tipe_surat', tipe);
        formData.append('tanggal', tgl);
        formData.append('jam', jam);
        // v3.0.0 Architecture Paths: Log Cetak masuk folder api/
        fetch('api/log_cetak.php', { method: 'POST', body: formData }).catch(err => console.error(err));
    }
    </script>
</body>

</html>