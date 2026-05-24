<?php
// edit_profile.php
require_once 'config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$user_nama  = $_SESSION['user_nama'] ?? '';
$user_roles = $_SESSION['user_roles'] ?? [];

$user_data = [];
if ($conn !== null) {
    $stmt = $conn->prepare("SELECT id, nama, email, username, roles FROM staf_sekolah WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$notif = [];
if (!empty($_SESSION['notif'])) {
    $notif = $_SESSION['notif'];
    unset($_SESSION['notif']);
}

$role_label = [
    'super_admin'    => 'Super Admin',
    'admin'          => 'Administrator',
    'bk'             => 'Guru BK',
    'guru'           => 'Guru',
    'waka_kesiswaan' => 'Waka Kesiswaan',
    'kepala_jurusan' => 'Kepala Jurusan',
    'yayasan'        => 'Yayasan',
];
$role_display = $role_label[$user_data['roles'] ?? ''] ?? ucwords(str_replace('_', ' ', $user_data['roles'] ?? '-'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — SinergiCare</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Animasi masuk halaman */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.45s cubic-bezier(.22,.68,0,1.2) both; }
        .fade-up-2 { animation: fadeUp 0.45s 0.1s cubic-bezier(.22,.68,0,1.2) both; }
        .fade-up-3 { animation: fadeUp 0.45s 0.2s cubic-bezier(.22,.68,0,1.2) both; }

        /* Input focus ring kustom */
        .field-input {
            @apply w-full px-4 py-3 text-sm border border-slate-200 dark:border-slate-700
                   rounded-2xl bg-slate-50 dark:bg-slate-800/60
                   text-slate-900 dark:text-slate-100
                   outline-none transition-all duration-200
                   placeholder:text-slate-400 dark:placeholder:text-slate-500;
        }
        .field-input:focus {
            @apply border-indigo-400 dark:border-indigo-500 bg-white dark:bg-slate-800
                   ring-4 ring-indigo-100 dark:ring-indigo-900/40;
        }
        .field-input:read-only {
            @apply bg-slate-100 dark:bg-slate-800/30 text-slate-400 dark:text-slate-500 cursor-not-allowed;
        }

        /* Password strength bar */
        #strength-bar { transition: width 0.4s ease, background-color 0.4s ease; }

        /* Scrollbar tipis */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark ::-webkit-scrollbar-thumb { background: #334155; }
    </style>
    <script>
        // Terapkan dark mode sebelum render untuk menghindari flash
        if (localStorage.getItem('theme') === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="min-h-screen bg-[#f4f4f6] dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased transition-colors duration-300">

    <!-- ===== TOPBAR ===== -->
    <header class="sticky top-0 z-30 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200/60 dark:border-slate-800">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2.5 group">
                <div class="h-8 w-8 rounded-xl bg-slate-900 dark:bg-white flex items-center justify-center text-white dark:text-slate-900 font-black text-sm transition group-hover:scale-105">S</div>
                <span class="font-extrabold text-sm tracking-tight hidden sm:block">SinergiCare</span>
                <span class="text-slate-300 dark:text-slate-600 hidden sm:block">·</span>
                <span class="text-xs font-semibold text-slate-400 dark:text-slate-500 hidden sm:block">Edit Profile</span>
            </a>
            <div class="flex items-center gap-2">
                <button onclick="toggleDark()" class="h-8 w-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-sm hover:scale-105 transition" title="Toggle dark mode">
                    <span class="dark:hidden">🌙</span>
                    <span class="hidden dark:block">☀️</span>
                </button>
                <a href="index.php" class="h-8 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                    ← Dashboard
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8 space-y-5">

        <!-- ===== NOTIFIKASI ===== -->
        <?php if (!empty($notif)): ?>
        <div class="fade-up flex items-start gap-3 p-4 rounded-2xl border text-sm font-semibold
            <?php echo $notif['type'] === 'success'
                ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300'
                : 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300'; ?>">
            <span class="text-base leading-none mt-0.5"><?php echo $notif['type'] === 'success' ? '✅' : '❌'; ?></span>
            <span class="flex-1"><?php echo htmlspecialchars($notif['message'], ENT_QUOTES, 'UTF-8'); ?></span>
            <button onclick="this.closest('div').remove()" class="opacity-40 hover:opacity-100 text-lg leading-none transition">×</button>
        </div>
        <?php endif; ?>

        <!-- ===== KARTU IDENTITAS ===== -->
        <div class="fade-up bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm overflow-hidden">
            <!-- Header avatar -->
            <div class="relative bg-gradient-to-br from-slate-900 to-slate-700 dark:from-slate-800 dark:to-slate-900 px-6 pt-8 pb-16">
                <!-- Dekorasi lingkaran -->
                <div class="absolute top-0 right-0 w-40 h-40 rounded-full bg-white/5 -translate-y-1/2 translate-x-1/4 pointer-events-none"></div>
                <div class="absolute bottom-0 left-10 w-24 h-24 rounded-full bg-white/5 translate-y-1/2 pointer-events-none"></div>
                <p class="text-[10px] font-bold tracking-widest text-slate-400 uppercase mb-1">Akun Aktif</p>
                <h1 class="text-xl font-extrabold text-white leading-tight">
                    <?php echo htmlspecialchars($user_data['nama'] ?? $user_nama, ENT_QUOTES, 'UTF-8'); ?>
                </h1>
                <p class="text-xs text-slate-400 mt-1 font-semibold"><?php echo htmlspecialchars($role_display, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <!-- Avatar circle overlapping header -->
            <div class="relative -mt-10 px-6 pb-6 flex items-end gap-4">
                <div class="h-20 w-20 rounded-[1.2rem] bg-white dark:bg-slate-800 border-4 border-white dark:border-slate-900 shadow-md flex items-center justify-center text-2xl font-black text-slate-900 dark:text-white select-none shrink-0">
                    <?php echo mb_strtoupper(mb_substr($user_data['nama'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="pb-1">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Username</p>
                    <p class="font-bold text-slate-700 dark:text-slate-300 text-sm font-mono">
                        @<?php echo htmlspecialchars($user_data['username'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- ===== FORM EDIT ===== -->
        <form action="actions/proses_profile_edit.php" method="POST" id="editForm" novalidate>

            <!-- SEKSI: Data Diri -->
            <div class="fade-up-2 bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm p-6 space-y-5">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-base">👤</span>
                    <h2 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Data Diri</h2>
                </div>

                <!-- Nama -->
                <div class="space-y-1.5">
                    <label for="nama" class="block text-xs font-bold text-slate-600 dark:text-slate-400">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" id="nama" name="nama" class="field-input"
                           value="<?php echo htmlspecialchars($user_data['nama'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="Nama lengkap Anda" required maxlength="150">
                </div>

                <!-- Email -->
                <div class="space-y-1.5">
                    <label for="email" class="block text-xs font-bold text-slate-600 dark:text-slate-400">Email</label>
                    <input type="email" id="email" name="email" class="field-input"
                           value="<?php echo htmlspecialchars($user_data['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="email@sekolah.sch.id" maxlength="100">
                </div>

                <!-- Username (readonly) -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-400">Username <span class="text-slate-400 font-medium">(tidak dapat diubah)</span></label>
                    <input type="text" class="field-input" value="<?php echo htmlspecialchars($user_data['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
            </div>

            <!-- SEKSI: Ubah Password -->
            <div class="fade-up-3 bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-200/60 dark:border-slate-800 shadow-sm p-6 mt-5 space-y-5">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-base">🔒</span>
                    <h2 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Ubah Password</h2>
                </div>

                <!-- Password sekarang -->
                <div class="space-y-1.5">
                    <label for="password_lama" class="block text-xs font-bold text-slate-600 dark:text-slate-400">
                        Password Saat Ini <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="password_lama" name="password_lama" class="field-input pr-11"
                               placeholder="Wajib diisi untuk verifikasi" required>
                        <button type="button" onclick="togglePwd('password_lama', this)"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition text-sm select-none">👁</button>
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">Masukkan password Anda saat ini untuk konfirmasi identitas.</p>
                </div>

                <!-- Password baru -->
                <div class="space-y-1.5">
                    <label for="password_baru" class="block text-xs font-bold text-slate-600 dark:text-slate-400">Password Baru <span class="text-slate-400 font-medium">(opsional)</span></label>
                    <div class="relative">
                        <input type="password" id="password_baru" name="password_baru" class="field-input pr-11"
                               placeholder="Minimal 6 karakter" oninput="cekKekuatan(this.value)" maxlength="100">
                        <button type="button" onclick="togglePwd('password_baru', this)"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition text-sm select-none">👁</button>
                    </div>
                    <!-- Password strength -->
                    <div id="strength-wrap" class="hidden space-y-1">
                        <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div id="strength-bar" class="h-full rounded-full w-0 bg-rose-400"></div>
                        </div>
                        <p id="strength-label" class="text-[10px] font-bold text-slate-400"></p>
                    </div>
                </div>

                <!-- Konfirmasi password baru -->
                <div class="space-y-1.5">
                    <label for="password_confirm" class="block text-xs font-bold text-slate-600 dark:text-slate-400">Konfirmasi Password Baru</label>
                    <div class="relative">
                        <input type="password" id="password_confirm" name="password_confirm" class="field-input pr-11"
                               placeholder="Ketik ulang password baru" oninput="cekKonfirmasi()" maxlength="100">
                        <button type="button" onclick="togglePwd('password_confirm', this)"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition text-sm select-none">👁</button>
                    </div>
                    <p id="confirm-msg" class="text-[10px] font-bold hidden"></p>
                </div>
            </div>

            <!-- TOMBOL AKSI -->
            <div class="fade-up-3 flex flex-col sm:flex-row gap-3 mt-5">
                <button type="submit" id="btn-submit"
                        class="flex-1 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold py-3.5 px-6 rounded-2xl text-sm hover:opacity-90 active:scale-95 transition-all shadow-sm">
                    💾 Simpan Perubahan
                </button>
                <a href="index.php"
                   class="flex-1 text-center bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold py-3.5 px-6 rounded-2xl text-sm hover:bg-slate-200 dark:hover:bg-slate-700 transition-all">
                    ← Kembali
                </a>
            </div>

        </form>

    </main>

    <script>
    function toggleDark() {
        const html = document.documentElement;
        const isDark = html.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    }

    // Toggle tampil/sembunyikan password
    function togglePwd(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.textContent = isHidden ? '🙈' : '👁';
    }

    // Cek kekuatan password
    function cekKekuatan(val) {
        const wrap  = document.getElementById('strength-wrap');
        const bar   = document.getElementById('strength-bar');
        const label = document.getElementById('strength-label');
        if (!wrap) return;

        if (!val) { wrap.classList.add('hidden'); return; }
        wrap.classList.remove('hidden');

        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [
            { pct: '20%',  color: '#f43f5e', text: 'Sangat Lemah' },
            { pct: '40%',  color: '#f97316', text: 'Lemah' },
            { pct: '60%',  color: '#eab308', text: 'Cukup' },
            { pct: '80%',  color: '#22c55e', text: 'Kuat' },
            { pct: '100%', color: '#10b981', text: 'Sangat Kuat 💪' },
        ];
        const lvl = levels[Math.min(score - 1, 4)] || levels[0];
        bar.style.width = lvl.pct;
        bar.style.backgroundColor = lvl.color;
        label.textContent = lvl.text;
        label.style.color = lvl.color;

        // Juga re-cek konfirmasi
        cekKonfirmasi();
    }

    // Cek kecocokan konfirmasi
    function cekKonfirmasi() {
        const baru    = document.getElementById('password_baru').value;
        const konfirm = document.getElementById('password_confirm').value;
        const msg     = document.getElementById('confirm-msg');
        if (!msg || !konfirm) { msg && msg.classList.add('hidden'); return; }

        msg.classList.remove('hidden');
        if (baru === konfirm) {
            msg.textContent = '✔ Password cocok';
            msg.style.color = '#10b981';
        } else {
            msg.textContent = '✘ Password tidak cocok';
            msg.style.color = '#f43f5e';
        }
    }

    // Validasi sebelum submit
    document.getElementById('editForm').addEventListener('submit', function(e) {
        const nama     = document.getElementById('nama').value.trim();
        const lama     = document.getElementById('password_lama').value;
        const baru     = document.getElementById('password_baru').value;
        const konfirm  = document.getElementById('password_confirm').value;
        const btn      = document.getElementById('btn-submit');

        if (!nama) {
            e.preventDefault();
            alert('⚠️ Nama tidak boleh kosong!');
            document.getElementById('nama').focus();
            return;
        }
        if (!lama) {
            e.preventDefault();
            alert('⚠️ Password saat ini wajib diisi!');
            document.getElementById('password_lama').focus();
            return;
        }
        if (baru && baru.length < 6) {
            e.preventDefault();
            alert('⚠️ Password baru minimal 6 karakter!');
            document.getElementById('password_baru').focus();
            return;
        }
        if (baru && baru !== konfirm) {
            e.preventDefault();
            alert('⚠️ Konfirmasi password tidak cocok!');
            document.getElementById('password_confirm').focus();
            return;
        }

        // Loading state
        btn.textContent = '⏳ Menyimpan...';
        btn.disabled = true;
    });
    </script>
</body>
</html>
