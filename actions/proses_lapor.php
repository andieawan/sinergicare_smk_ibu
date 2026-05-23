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
function hitungUlangRadarSiswa($conn, $student_id)
{
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

    $conn->prepare("UPDATE students SET status_warna = ?, level_eskalasi = ? WHERE id = ?")
         ->execute([$warna_baru, $skor_level, $student_id]);
}

// ====================================================================================
// PROSES 1: PENAMBAHAN INSIDEN BARU (POST)
// ====================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PERBAIKAN: form di index.php tidak mengirim form_aksi, hanya POST biasa → default tambah
    $form_aksi   = $_POST['form_aksi'] ?? 'tambah';
    $student_id  = $_POST['student_id'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $catatan     = trim($_POST['catatan'] ?? '');

    if ($form_aksi === 'tambah') {
        if (empty($student_id) || empty($category_id) || empty($catatan)) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Data jurnal tidak lengkap.'];
            header("Location: ../index.php");
            exit();
        }

        try {
            $conn->prepare("INSERT INTO incidents (student_id, category_id, user_id, catatan, created_at)
                            VALUES (?, ?, ?, ?, NOW())")
                 ->execute([$student_id, $category_id, $user_id_login, $catatan]);

            hitungUlangRadarSiswa($conn, $student_id);

            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Laporan insiden berhasil dicatat dan masuk radar pemantauan!'];
        } catch (PDOException $e) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
        }

        header("Location: ../index.php");
        exit();
    }

    // Edit insiden
    if ($form_aksi === 'edit') {
        $id = $_POST['id'] ?? '';

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
                $_SESSION['notif'] = ['type' => 'error', 'message' => '⛔ Batas waktu 30 menit modifikasi telah habis.'];
                header("Location: ../index.php");
                exit();
            }

            $conn->prepare("UPDATE incidents SET category_id = ?, catatan = ? WHERE id = ?")
                 ->execute([$category_id, $catatan, $id]);

            hitungUlangRadarSiswa($conn, $incident['student_id']);

            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Catatan jurnal berhasil diperbarui!'];
        } catch (PDOException $e) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
        }

        header("Location: ../index.php");
        exit();
    }
}

// ====================================================================================
// PROSES 2: HAPUS INSIDEN (GET)
// ====================================================================================
if (isset($_GET['aksi']) && $_GET['aksi'] === 'hapus') {
    $id = (int)($_GET['id'] ?? 0);

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
            $_SESSION['notif'] = ['type' => 'error', 'message' => '⛔ Akses hapus ditolak. Hubungi BK untuk penghapusan.'];
            header("Location: ../index.php");
            exit();
        }

        $conn->prepare("DELETE FROM incidents WHERE id = ?")->execute([$id]);
        hitungUlangRadarSiswa($conn, $incident['student_id']);

        $_SESSION['notif'] = ['type' => 'success', 'message' => '🗑️ Laporan insiden berhasil dihapus.'];
    } catch (PDOException $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }

    header("Location: ../index.php");
    exit();
}

// Fallback
header("Location: ../index.php");
exit();
