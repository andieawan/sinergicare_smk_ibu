<?php
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder prints/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : 0;
$siswa = null;
$riwayat = [];

if ($conn !== null && $student_id > 0) {
    // Ambil data profil siswa
    $stmt_siswa = $conn->prepare("SELECT s.*, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = :id");
    $stmt_siswa->execute(['id' => $student_id]);
    $siswa = $stmt_siswa->fetch(PDO::FETCH_ASSOC);

    // Ambil semua daftar riwayat insiden
    $stmt_insiden = $conn->prepare("SELECT i.*, vc.nama_kejadian, vc.bobot_risiko FROM incidents i JOIN violation_categories vc ON i.category_id = vc.id WHERE i.student_id = :student_id ORDER BY i.id DESC");
    $stmt_insiden->execute(['student_id' => $student_id]);
    while ($row = $stmt_insiden->fetch(PDO::FETCH_ASSOC)) {
        $riwayat[] = $row;
    }
}

if (!$siswa) {
    die("Data siswa tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SinergiCare Laporan - <?php echo htmlspecialchars($siswa['nama'], ENT_QUOTES); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        @media print {
            .no-print { display: none; }
            body { background-color: white; color: black; }
        }
    </style>
</head>
<body class="bg-slate-50 p-8 text-slate-800">

    <div class="max-w-3xl mx-auto bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
        <div class="no-print flex justify-between items-center mb-6 bg-slate-50 p-3 rounded-xl border">
            <span class="text-xs font-semibold text-slate-500">📄 Dokumen Resmi Cetak Lembar Kasus</span>
            <div class="flex gap-2">
                <button onclick="window.close()" class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs rounded-lg transition">Tutup Halaman</button>
                <button onclick="window.print()" class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-lg transition">🖨️ Mulai Cetak</button>
            </div>
        </div>

        <div class="text-center border-b-2 border-slate-900 pb-4 mb-6 relative flex items-center justify-center">
            <img src="../assets/logo_sekolah.png" class="absolute left-0 top-0 h-14 w-14 object-contain" onerror="this.style.display='none'">
            <div>
                <h1 class="text-xl font-extrabold tracking-tight uppercase">SinergiCare Sistem Radar Karakter v2</h1>
                <p class="text-xs font-medium text-slate-500 mt-0.5">Laporan Komparatif Kedisiplinan & Penegakan Aturan Siswa SMK</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 text-xs bg-slate-50 p-4 rounded-xl border mb-6">
            <div>
                <p class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Nama Lengkap Murid</p>
                <p class="font-bold text-slate-900 text-sm mt-0.5"><?php echo htmlspecialchars($siswa['nama'], ENT_QUOTES); ?></p>
            </div>
            <div>
                <p class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Nomor Induk Siswa Nasional (NISN)</p>
                <p class="font-mono font-bold text-slate-700 text-sm mt-0.5"><?php echo htmlspecialchars($siswa['nisn'], ENT_QUOTES); ?></p>
            </div>
            <div>
                <p class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Rombongan Belajar / Kelas</p>
                <p class="font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-', ENT_QUOTES); ?></p>
            </div>
            <div>
                <p class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Status Kerawanan Karakter Saat Ini</p>
                <p class="font-extrabold uppercase mt-0.5 text-xs <?php echo $siswa['status_warna'] === 'merah' ? 'text-rose-600' : ($siswa['status_warna'] === 'kuning' ? 'text-amber-600' : 'text-emerald-600'); ?>">
                    Zona <?php echo $siswa['status_warna']; ?>
                </p>
            </div>
        </div>

        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">📜 Daftar Pelanggaran & Catatan Kronologi</h3>
        <div class="border rounded-xl overflow-hidden text-xs">
            <table class="min-w-full text-left">
                <thead>
                    <tr class="bg-slate-900 text-white font-bold uppercase text-[10px] tracking-wider">
                        <th class="px-4 py-2.5" width="30%">Indikasi Kejadian</th>
                        <th class="px-4 py-2.5">Kronologi / Catatan Lapangan</th>
                        <th class="px-4 py-2.5 text-center" width="15%">Bobot</th>
                    </tr>
                </thead>
                <tbody class="divide-y font-medium text-slate-700">
                    <?php if (empty($riwayat)): ?>
                        <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400 italic">Siswa bersih. Tidak ada catatan insiden negatif di sekolah.</td></tr>
                    <?php else: ?>
                        <?php foreach ($riwayat as $row): ?>
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-900"><?php echo htmlspecialchars($row['nama_kejadian'], ENT_QUOTES); ?></td>
                                <td class="px-4 py-3 text-slate-600 italic">"<?php echo htmlspecialchars($row['catatan'], ENT_QUOTES); ?>"</td>
                                <td class="px-4 py-3 text-center uppercase font-bold text-[10px] <?php echo $row['bobot_risiko'] === 'berat' ? 'text-rose-600' : ($row['bobot_risiko'] === 'sedang' ? 'text-amber-600' : 'text-emerald-600'); ?>">
                                    <?php echo $row['bobot_risiko']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-12 flex justify-between text-xs font-semibold px-4">
            <div class="text-center">
                <p class="text-slate-400 mb-14">Petugas / Konselor BK</p>
                <p class="border-t border-slate-400 pt-1 w-40 mx-auto">____________________</p>
            </div>
            <div class="text-center">
                <p class="text-slate-400 mb-14">Orang Tua / Wali Murid</p>
                <p class="border-t border-slate-400 pt-1 w-40 mx-auto">____________________</p>
            </div>
        </div>
    </div>

</body>
</html>