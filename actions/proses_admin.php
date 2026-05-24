<?php
// proses_admin.php (VERSI BERSIH & MODULAR)
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Hak Akses Keamanan Admin
if (!isset($_SESSION['user_id']) || !in_array('admin', $_SESSION['user_roles'] ?? [])) {
    header("Location: ../index.php");
    exit();
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';

// ===================================================================================
// 1. IMPOR MASSAL (SISWA DAN STAF VIA CSV)
// ===================================================================================
if ($aksi === 'import_csv') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === 0) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        $tipe_impor = $_POST['tipe_impor'] ?? 'siswa';
        $handle = fopen($file_tmp, "r");
        
        if ($handle !== FALSE) {
            $baris_pertama = fgets($handle);
            if (strpos($baris_pertama, 'sep=') === false) { rewind($handle); }
            fgetcsv($handle, 1000, ","); // Lewati header

            try {
                $conn->beginTransaction();
                if ($tipe_impor === 'siswa') {
                    $stmt_kelas = $conn->prepare("SELECT id FROM classes WHERE nama_kelas = ? LIMIT 1");
                    $stmt_ins   = $conn->prepare("INSERT INTO students (nisn, nama, class_id, status_warna, level_eskalasi) VALUES (?, ?, ?, 'hijau', 'teguran') ON DUPLICATE KEY UPDATE nama=VALUES(nama), class_id=VALUES(class_id)");

                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) < 3) continue;
                        $nisn = trim($data[0]); $nama = trim($data[1]); $kelas = trim($data[2]);
                        $stmt_kelas->execute([$kelas]);
                        $class_id = $stmt_kelas->fetchColumn();
                        if (!$class_id) {
                            $stmt_new_class = $conn->prepare("INSERT INTO classes (nama_kelas) VALUES (?)");
                            $stmt_new_class->execute([$kelas]);
                            $class_id = $conn->lastInsertId();
                        }
                        $stmt_ins->execute([$nisn, $nama, $class_id]);
                    }
                    $_SESSION['notif'] = ['type' => 'success', 'message' => '🚀 Data siswa berhasil diimpor!'];
                } elseif ($tipe_impor === 'staf') {
                    $stmt_staf = $conn->prepare("INSERT INTO staf_sekolah (nama, username, password, roles) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nama=VALUES(nama), password=VALUES(password), roles=VALUES(roles)");
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) < 4) continue;
                        $stmt_staf->execute([trim($data[0]), trim($data[1]), trim($data[2]), strtolower(trim($data[3]))]);
                    }
                    $_SESSION['notif'] = ['type' => 'success', 'message' => '🚀 Data staf berhasil diimpor!'];
                }
                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal Impor: ' . $e->getMessage()];
            }
            fclose($handle);
        }
    }
    header("Location: ../index.php"); exit();
}

// ===================================================================================
// 2. KELAS, KATEGORI, DLL
// ===================================================================================
elseif ($aksi === 'tambah_kelas') {
    $nama_kelas = trim($_POST['nama_kelas']);
    if (!empty($nama_kelas)) {
        $stmt = $conn->prepare("INSERT INTO classes (nama_kelas) VALUES (?)");
        $stmt->execute([$nama_kelas]);
        $_SESSION['notif'] = ['type' => 'success', 'message' => '🏫 Kelas berhasil ditambah.'];
    }
    header("Location: ../index.php"); exit();
}
elseif ($aksi === 'edit_kelas') {
    $stmt = $conn->prepare("UPDATE classes SET nama_kelas = ? WHERE id = ?");
    $stmt->execute([trim($_POST['nama']), $_POST['id']]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '🏫 Data kelas diubah.'];
    header("Location: ../index.php"); exit();
}
elseif ($aksi === 'edit_kategori') {
    $stmt = $conn->prepare("UPDATE violation_categories SET nama_kejadian = ?, bobot_risiko = ? WHERE id = ?");
    $stmt->execute([trim($_POST['nama']), $_POST['bobot_risiko'], $_POST['id']]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '⚖️ Aturan berhasil direvisi.'];
    header("Location: ../index.php"); exit();
}

// ===================================================================================
// 3. MANAJEMEN STAF (EDIT & DELETE)
// ===================================================================================
elseif ($aksi === 'manage_staf') {
    $staf_id = $_POST['staf_id'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $roles = trim($_POST['roles'] ?? 'guru');
    $password = $_POST['password'] ?? '';

    try {
        if ($staf_id) {
            if ($password) {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE staf_sekolah SET nama=?, email=?, roles=?, password=? WHERE id=?");
                $stmt->execute([$nama, $email, $roles, $hashed, $staf_id]);
            } else {
                $stmt = $conn->prepare("UPDATE staf_sekolah SET nama=?, email=?, roles=? WHERE id=?");
                $stmt->execute([$nama, $email, $roles, $staf_id]);
            }
            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Staf diperbarui!'];
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO staf_sekolah (nama, email, username, password, roles) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $email, $username, $hashed, $roles]);
            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Staf baru ditambah!'];
        }
    } catch (Exception $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
    }
    header("Location: ../index.php"); exit();
}
elseif ($aksi === 'delete_staf') {
    if (($_GET['staf_id'] ?? '') != $_SESSION['user_id']) {
        $conn->prepare("DELETE FROM staf_sekolah WHERE id = ?")->execute([$_GET['staf_id']]);
        $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Staf dihapus!'];
    }
    header("Location: ../index.php"); exit();
}

// ===================================================================================
// 4. MANAJEMEN SISWA (EDIT & DELETE)
// ===================================================================================
elseif ($aksi === 'manage_siswa') {
    $id = $_POST['siswa_id'] ?? '';
    $nisn = trim($_POST['nisn'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $cid = (int)($_POST['class_id'] ?? 0);

    try {
        if ($id) {
            $conn->prepare("UPDATE students SET nama=?, class_id=? WHERE id=?")->execute([$nama, $cid, $id]);
            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Siswa diperbarui!'];
        } else {
            $conn->prepare("INSERT INTO students (nisn, nama, class_id, status_warna) VALUES (?, ?, ?, 'hijau')")->execute([$nisn, $nama, $cid]);
            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Siswa ditambah!'];
        }
    } catch (Exception $e) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal: ' . $e->getMessage()];
    }
    header("Location: ../index.php"); exit();
}
elseif ($aksi === 'delete_siswa') {
    $conn->prepare("DELETE FROM students WHERE id = ?")->execute([$_GET['siswa_id']]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Siswa dihapus!'];
    header("Location: ../index.php"); exit();
}

header("Location: ../index.php");
exit();
?>