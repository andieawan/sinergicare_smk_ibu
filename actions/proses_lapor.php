<?php
// proses_lapor.php (VERSI HYBRID SYSTEM ENGINE)
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id_login = $_SESSION['user_id'];
$user_roles    = $_SESSION['user_roles'] ?? [];
$is_bk_admin   = count(array_intersect(['bk', 'admin', 'super_admin'], $user_roles)) > 0;

// ====================================================================================
// FUNGSI: Hitung Ulang Radar Status Warna Siswa
// ====================================================================================
function hitungUlangRadarSiswa($conn, $student_id) {
    $stmt = $conn->prepare("SELECT vc.bobot_risiko FROM incidents i
                            JOIN violation_categories vc ON i.category_id = vc.id
                            WHERE i.student_id = ?
                            ORDER BY FIELD(vc.bobot_risiko, 'berat', 'sedang', 'ringan') LIMIT 1");
    $stmt->execute([$student_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    $warna_baru = 'hijau';
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

    $conn->prepare("UPDATE students SET status_warna = ?, level_eskalasi = ? WHERE id = ?")->execute([$warna_baru, $skor_level, $student_id]);
}

// ====================================================================================
// PROSES 1: TAMBAH JURNAL BARU (POST dengan form_aksi=tambah)
// ====================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_aksi'] ?? '') === 'tambah') {
    $student_id  = $_POST['student_id']  ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $catatan     = trim($_POST['catatan'] ?? '');

    if (empty($student_id) || empty($category_id)) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Data input tidak lengkap.'];
        header("Location: ../index.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO incidents (student_id, category_id, user_id, catatan, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$student_id, $category_id, $user_id_login, $catatan]);
        hitungUlangRadarSiswa($conn, $student_id);
        $_SESSION['notif'] = ['type' => 'success', 'message' => 'Laporan insiden berhasil dicatat!'];
    } catch (PDOException $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }

    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// PROSES 2: EDIT JURNAL
// BUG FIX: Form edit di index.php mengirim POST ke proses_lapor.php?aksi=edit
// tapi handler lama mengecek $_POST['form_aksi'] === 'edit' yang tidak pernah terkirim.
// Sekarang handler menerima KEDUANYA: POST dengan form_aksi=edit ATAU GET aksi=edit.
// ====================================================================================
$is_edit_request = ($_SERVER['REQUEST_METHOD'] === 'POST') &&
    (($_POST['form_aksi'] ?? '') === 'edit' || ($_GET['aksi'] ?? '') === 'edit');

if ($is_edit_request) {
    $id          = $_POST['id']          ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $catatan     = trim($_POST['catatan'] ?? '');

    try {
        $stmt_check = $conn->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt_check->execute([$id]);
        $incident = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$incident) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Data tidak ditemukan.'];
            header("Location: ../index.php");
            exit();
        }

        $is_owner    = ($incident['user_id'] == $user_id_login);
        $within_time = (time() - strtotime($incident['created_at']) <= 1800);

        if (!$is_bk_admin && !($is_owner && $within_time)) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '🔒 Akses edit ditolak. Batas waktu 30 menit telah habis.'];
            header("Location: ../index.php");
            exit();
        }

        $conn->prepare("UPDATE incidents SET category_id = ?, catatan = ? WHERE id = ?")->execute([$category_id, $catatan, $id]);
        hitungUlangRadarSiswa($conn, $incident['student_id']);

        $_SESSION['notif'] = ['type' => 'success', 'message' => 'Catatan jurnal berhasil diperbarui!'];
    } catch (PDOException $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }

    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// PROSES 3: HAPUS JURNAL (GET aksi=hapus)
// ====================================================================================
if (($_GET['aksi'] ?? '') === 'hapus') {
    $id = $_GET['id'] ?? '';

    try {
        $stmt_check = $conn->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt_check->execute([$id]);
        $incident = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$incident) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Data tidak ditemukan.'];
            header("Location: ../index.php");
            exit();
        }

        $is_owner    = ($incident['user_id'] == $user_id_login);
        $within_time = (time() - strtotime($incident['created_at']) <= 1800);

        if (!$is_bk_admin && !($is_owner && $within_time)) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '🔒 Akses hapus ditolak.'];
            header("Location: ../index.php");
            exit();
        }

        $conn->prepare("DELETE FROM incidents WHERE id = ?")->execute([$id]);
        hitungUlangRadarSiswa($conn, $incident['student_id']);

        $_SESSION['notif'] = ['type' => 'success', 'message' => 'Laporan insiden berhasil dihapus dari sistem.'];
    } catch (PDOException $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }

    header("Location: ../index.php");
    exit();
}

// Jika tidak ada aksi yang cocok, kembali ke index
header("Location: ../index.php");
exit();
?>
