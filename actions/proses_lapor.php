<?php
// proses_lapor.php (VERSI HYBRID SYSTEM ENGINE)
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder actions/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // PERBAIKAN PATH: Kembali ke root utama
    header("Location: ../login.php");
    exit();
}

$user_id_login = $_SESSION['user_id'];
$user_roles    = $_SESSION['user_roles']; 
$is_bk_admin   = count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0;

// ====================================================================================\
// FUNGSI OTOMATIS: Hitung Ulang Radar Status Warna Siswa Secara Akurat
// ====================================================================================\
function hitungUlangRadarSiswa($conn, $student_id) {
    // Ambil risiko tertinggi dari kasus yang TERSISA milik siswa ini
    $stmt = $conn->prepare("SELECT vc.bobot_risiko FROM incidents i 
                            JOIN violation_categories vc ON i.category_id = vc.id 
                            WHERE i.student_id = ? 
                            ORDER BY FIELD(vc.bobot_risiko, 'berat', 'sedang', 'ringan') LIMIT 1");
    $stmt->execute([$student_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    $warna_baru = 'hijau'; // Jika tidak ada sisa kasus, otomatis kembali putih/hijau
    $skor_level = 'teguran';

    if ($res) {
        if ($res['bobot_risiko'] === 'berat') {
            $warna_baru = 'merah';
            $skor_level = 'skorsing_drop';
        } elseif ($res['bobot_risiko'] === 'sedang') {
            $warna_baru = 'kuning';
            $skor_level = 'konseling';
        }
    }

    // Update status fisik siswa di tabel students
    $stmt_up = $conn->prepare("UPDATE students SET status_warna = ?, level_eskalasi = ? WHERE id = ?");
    $stmt_up->execute([$warna_baru, $skor_level, $student_id]);
}

// ====================================================================================\
// PROSES 1: PENAMBAHAN JURNAL BARU (CREATE)
// ====================================================================================\
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_aksi']) && $_POST['form_aksi'] === 'tambah') {
    $student_id   = $_POST['student_id'] ?? '';
    $category_id  = $_POST['category_id'] ?? '';
    $catatan      = trim($_POST['catatan'] ?? '');

    if (empty($student_id) || empty($category_id)) {
        die("Data input tidak lengkap.");
    }

    try {
        $stmt = $conn->prepare("INSERT INTO incidents (student_id, category_id, user_id, catatan, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$student_id, $category_id, $user_id_login, $catatan]);

        // Jalankan hitung ulang radar otomatis untuk siswa ini
        hitungUlangRadarSiswa($conn, $student_id);

        $_SESSION['notif'] = ['type' => 'success', 'message' => 'Laporan insiden berhasil dicatat dan masuk radar pemantauan!'];
        // PERBAIKAN PATH: Kembali ke root utama
        header("Location: ../index.php");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// ====================================================================================\
// PROSES 2: EDIT DATA JURNAL (UPDATE)
// ====================================================================================\
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_aksi']) && $_POST['form_aksi'] === 'edit') {
    $id          = $_POST['id'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $catatan     = trim($_POST['catatan'] ?? '');

    try {
        $stmt_check = $conn->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt_check->execute([$id]);
        $incident = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$incident) { die("Data tidak ditemukan."); }

        $is_owner    = ($incident['user_id'] == $user_id_login);
        $within_time = (time() - strtotime($incident['created_at']) <= 1800);

        if (!$is_bk_admin && !($is_owner && $within_time)) {
            die("Akses edit ditolak. Batas waktu 30 urung modifikasi telah habis.");
        }

        $stmt_update = $conn->prepare("UPDATE incidents SET category_id = ?, catatan = ? WHERE id = ?");
        $stmt_update->execute([$category_id, $catatan, $id]);

        // Sinkronisasi ulang radar warna karena kategori mungkin berubah risiko
        hitungUlangRadarSiswa($conn, $incident['student_id']);

        $_SESSION['notif'] = ['type' => 'success', 'message' => 'Catatan jurnal berhasil diperbarui!'];
        // PERBAIKAN PATH: Kembali ke root utama
        header("Location: ../index.php");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// ====================================================================================\
// PROSES 3: PENGHAPUSAN DATA JURNAL (DELETE)
// ====================================================================================\
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus') {
    $id = $_GET['id'] ?? '';

    try {
        $stmt_check = $conn->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt_check->execute([$id]);
        $incident = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$incident) { die("Data tidak ditemukan."); }

        $is_owner    = ($incident['user_id'] == $user_id_login);
        $within_time = (time() - strtotime($incident['created_at']) <= 1800);

        if (!$is_bk_admin && !($is_owner && $within_time)) {
            die("Akses hapus ditolak.");
        }

        $stmt_delete = $conn->prepare("DELETE FROM incidents WHERE id = ?");
        $stmt_delete->execute([$id]);

        hitungUlangRadarSiswa($conn, $incident['student_id']);

        $_SESSION['notif'] = ['type' => 'success', 'message' => 'Laporan insiden berhasil dihapus dari sistem.'];
        // PERBAIKAN PATH: Kembali ke root utama
        header("Location: ../index.php");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>