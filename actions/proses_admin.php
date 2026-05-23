<?php
// proses_admin.php (VERSI FINAL & UTUH)
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder actions/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Hak Akses Keamanan Admin
if (!isset($_SESSION['user_id']) || !count(array_intersect(['admin', 'super_admin'], $_SESSION['user_roles'] ?? [])) > 0) {
    // PERBAIKAN PATH: Kembali ke root utama jika bukan admin
    header("Location: ../index.php");
    exit();
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

// ====================================================================================\
// 1. KASUS: IMPOR MASSAL (SISWA DAN STAF VIA CSV)
// ====================================================================================\
if ($aksi === 'import_csv') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === 0) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        $tipe_impor = isset($_POST['tipe_impor']) ? $_POST['tipe_impor'] : 'siswa';
        $handle = fopen($file_tmp, "r");
        
        if ($handle !== FALSE) {
            $baris_pertama = fgets($handle);
if (strpos($baris_pertama, 'sep=') === false) { rewind($handle); }
fgetcsv($handle, 1000, ","); // Lewati header kolom baris pertama

            try {
                $conn->beginTransaction();

                if ($tipe_impor === 'siswa') {
                    $stmt_kelas = $conn->prepare("SELECT id FROM classes WHERE nama_kelas = ? LIMIT 1");
                    $stmt_ins   = $conn->prepare("INSERT INTO students (nisn, nama, class_id, status_warna, level_eskalasi) VALUES (?, ?, ?, 'hijau', 'teguran') ON DUPLICATE KEY UPDATE nama=VALUES(nama), class_id=VALUES(class_id)");

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) < 3) continue;
                        $nisn   = trim($data[0]);
                        $nama   = trim($data[1]);
                        $kelas  = trim($data[2]);

                        $stmt_kelas->execute([$kelas]);
                        $class_id = $stmt_kelas->fetchColumn();

                        if (!$class_id) {
                            $stmt_new_class = $conn->prepare("INSERT INTO classes (nama_kelas) VALUES (?)");
                            $stmt_new_class->execute([$kelas]);
                            $class_id = $conn->lastInsertId();
                        }
                        $stmt_ins->execute([$nisn, $nama, $class_id]);
                    }
                    $_SESSION['notif'] = ['type' => 'success', 'message' => '🚀 Data siswa dan standarisasi kelas berhasil diimpor!'];
                } 
                elseif ($tipe_impor === 'staf') {
                    $stmt_staf = $conn->prepare("INSERT INTO staf_sekolah (nama, username, password, roles) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nama=VALUES(nama), password=VALUES(password), roles=VALUES(roles)");

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) < 4) continue;
                        $nama     = trim($data[0]);
                        $username = trim($data[1]);
                        $password = trim($data[2]);
                        $role     = strtolower(trim($data[3]));

                        $stmt_staf->execute([$nama, $username, $password, $role]);
                    }
                    $_SESSION['notif'] = ['type' => 'success', 'message' => '🚀 Data akun staf & hak akses multi-role berhasil diimpor!'];
                }

                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal Impor CSV: ' . $e->getMessage()];
            }
            fclose($handle);
        }
    }
    // PERBAIKAN PATH: Kembali ke root utama
    header("Location: ../index.php");
    exit();
}

// ====================================================================================\
// 2. KASUS: MANAJEMEN DATA KELAS BARU (CREATE)
// ====================================================================================\
elseif ($aksi === 'tambah_kelas') {
    $nama_kelas = trim($_POST['nama_kelas']);
    if (!empty($nama_kelas)) {
        $stmt = $conn->prepare("INSERT INTO classes (nama_kelas) VALUES (?)");
        $stmt->execute([$nama_kelas]);
        $_SESSION['notif'] = ['type' => 'success', 'message' => '🏫 Ruang kelas baru berhasil ditambahkan.'];
    }
    // PERBAIKAN PATH: Kembali ke root utama
    header("Location: ../index.php");
    exit();
}

// ====================================================================================\
// 3. KASUS: MODIFIKASI DATA KELAS (UPDATE)
// ====================================================================================\
elseif ($aksi === 'edit_kelas') {
    $id = $_POST['id']; $nama = trim($_POST['nama']);
    $stmt = $conn->prepare("UPDATE classes SET nama_kelas = ? WHERE id = ?");
    $stmt->execute([$nama, $id]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '🏫 Identitas nama kelas berhasil diubah.'];
    // PERBAIKAN PATH: Kembali ke root utama
    header("Location: ../index.php");
    exit();
}

// ====================================================================================\
// 4. KASUS: REVISI HAK AKSES STAF/GURU (UPDATE)
// ====================================================================================\
elseif ($aksi === 'edit_staf') {
    $id = $_POST['id']; $nama = trim($_POST['nama']); $role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE staf_sekolah SET nama = ?, roles = ? WHERE id = ?");
    $stmt->execute([$nama, $role, $id]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '👤 Hak akses otoritas staf berhasil disesuaikan.'];
    // PERBAIKAN PATH: Kembali ke root utama
    header("Location: ../index.php");
    exit();
}

// ====================================================================================\
// 5. KASUS: REVISI BUKU PEDOMAN ATURAN (UPDATE)
// ====================================================================================\
elseif ($aksi === 'edit_kategori') {
    $id = $_POST['id']; $nama = trim($_POST['nama']); $bobot_risiko = $_POST['bobot_risiko'];
    $stmt = $conn->prepare("UPDATE violation_categories SET nama_kejadian = ?, bobot_risiko = ? WHERE id = ?");
    $stmt->execute([$nama, $bobot_risiko, $id]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '⚖️ Buku pedoman aturan berhasil direvisi.'];
    // PERBAIKAN PATH: Kembali ke root utama
    header("Location: ../index.php");
    exit();
}

// ====================================================================================\
// 6. KASUS: UPLOAD LOGO INSTANSI SEKOLAH
// ====================================================================================\
elseif ($aksi === 'upload_logo') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === 0) {
        $ekstensi = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
        if (strtolower($ekstensi) === 'png') {
            // PERBAIKAN PATH: Menggunakan dirname(__DIR__) agar gambar tersimpan di folder utama (root) luar actions/
            $tujuan = dirname(__DIR__) . '/assets/logo_sekolah.png';
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $tujuan)) {
                $_SESSION['notif'] = ['type' => 'success', 'message' => '🖼️ Logo instansi sekolah berhasil diperbarui!'];
            } else { $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menyimpan berkas logo.']; }
        } else { $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Format logo ditolak. Ekstensi wajib .PNG!']; }
    }
    // PERBAIKAN PATH: Kembali ke root utama
    header("Location: ../index.php");
    exit();
}
?>