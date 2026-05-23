<?php
// proses_admin.php (VERSI FINAL & UTUH)
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Hak Akses Admin
if (!isset($_SESSION['user_id']) || !count(array_intersect(['admin', 'super_admin'], $_SESSION['user_roles'] ?? [])) > 0) {
    header("Location: ../index.php");
    exit();
}

$aksi = $_GET['aksi'] ?? '';

// ====================================================================================
// 1. IMPOR MASSAL VIA CSV
// ====================================================================================
if ($aksi === 'import_csv') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] === 0) {
        $file_tmp   = $_FILES['file_csv']['tmp_name'];
        $tipe_impor = $_POST['tipe_impor'] ?? 'siswa';
        $handle     = fopen($file_tmp, "r");

        if ($handle !== false) {
            // PERBAIKAN: Deteksi separator (bisa titik koma atau koma) secara otomatis
            $baris_pertama = fgets($handle);
            $separator = ',';
            if (strpos($baris_pertama, 'sep=;') !== false || strpos($baris_pertama, 'sep=') === 0) {
                $separator = ';';
                // Sudah habis membaca baris sep=, lanjut baca header kolom
                fgets($handle);
            } elseif (substr_count($baris_pertama, ';') > substr_count($baris_pertama, ',')) {
                // File pakai titik koma tapi tanpa deklarasi sep=
                $separator = ';';
                // baris_pertama adalah header, tidak perlu rewind — skip saja
            } else {
                // Baris pertama adalah header kolom, tidak perlu diproses
                // (sudah terbaca oleh fgets di atas)
            }

            try {
                $conn->beginTransaction();

                if ($tipe_impor === 'siswa') {
                    $stmt_kelas = $conn->prepare("SELECT id FROM classes WHERE nama_kelas = ? LIMIT 1");
                    $stmt_ins   = $conn->prepare("INSERT INTO students (nisn, nama, class_id, status_warna, level_eskalasi)
                                                  VALUES (?, ?, ?, 'hijau', 'teguran')
                                                  ON DUPLICATE KEY UPDATE nama=VALUES(nama), class_id=VALUES(class_id)");

                    while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
                        if (count($data) < 3) continue;
                        $nisn  = trim($data[0]);
                        $nama  = trim($data[1]);
                        $kelas = trim($data[2]);
                        if (empty($nisn) || empty($nama)) continue;

                        $stmt_kelas->execute([$kelas]);
                        $class_id = $stmt_kelas->fetchColumn();

                        if (!$class_id) {
                            $conn->prepare("INSERT INTO classes (nama_kelas) VALUES (?)")->execute([$kelas]);
                            $class_id = $conn->lastInsertId();
                        }
                        $stmt_ins->execute([$nisn, $nama, $class_id]);
                    }
                    $_SESSION['notif'] = ['type' => 'success', 'message' => '🚀 Data siswa berhasil diimpor!'];

                } elseif ($tipe_impor === 'staf') {
                    // PERBAIKAN: Template staf pakai kolom nama;email;password;role
                    // Kolom: nama_staf | email | password_default | role_akses
                    $stmt_staf = $conn->prepare("INSERT INTO staf_sekolah (nama, email, username, password, roles)
                                                 VALUES (?, ?, ?, ?, ?)
                                                 ON DUPLICATE KEY UPDATE nama=VALUES(nama), password=VALUES(password), roles=VALUES(roles)");

                    while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
                        if (count($data) < 4) continue;
                        $nama     = trim($data[0]);
                        $email    = trim($data[1]);
                        $password = trim($data[2]);
                        // PERBAIKAN: Ambil role pertama saja (format bisa "guru,bk" → ambil "guru")
                        $roles_raw = strtolower(trim($data[3]));
                        $role      = explode(',', $roles_raw)[0];
                        // Gunakan email sebagai username jika tidak ada kolom username
                        $username  = strtok($email, '@') ?: $nama;
                        if (empty($nama)) continue;

                        $stmt_staf->execute([$nama, $email, $username, $password, $role]);
                    }
                    $_SESSION['notif'] = ['type' => 'success', 'message' => '🚀 Data akun staf berhasil diimpor!'];
                }

                $conn->commit();
            } catch (PDOException $e) {
                $conn->rollBack();
                $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal Impor CSV: ' . $e->getMessage()];
            }
            fclose($handle);
        }
    }
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 2. TAMBAH SISWA TUNGGAL
// ====================================================================================
elseif ($aksi === 'tambah_siswa') {
    $nisn     = trim($_POST['nisn'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $class_id = (int)($_POST['class_id'] ?? 0);

    if (!empty($nisn) && !empty($nama) && $class_id > 0) {
        try {
            $stmt = $conn->prepare("INSERT INTO students (nisn, nama, class_id, status_warna, level_eskalasi)
                                    VALUES (?, ?, ?, 'hijau', 'teguran')");
            $stmt->execute([$nisn, $nama, $class_id]);
            $_SESSION['notif'] = ['type' => 'success', 'message' => '👤 Siswa baru berhasil didaftarkan.'];
        } catch (PDOException $e) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ NISN sudah terdaftar atau error: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Data siswa tidak lengkap.'];
    }
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 3. TAMBAH KELAS BARU
// ====================================================================================
elseif ($aksi === 'tambah_kelas') {
    $nama_kelas = trim($_POST['nama_kelas'] ?? '');
    if (!empty($nama_kelas)) {
        $conn->prepare("INSERT INTO classes (nama_kelas) VALUES (?)")->execute([$nama_kelas]);
        $_SESSION['notif'] = ['type' => 'success', 'message' => '🏫 Kelas baru berhasil ditambahkan.'];
    }
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 4. EDIT KELAS
// ====================================================================================
elseif ($aksi === 'edit_kelas') {
    $id   = $_POST['id'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $conn->prepare("UPDATE classes SET nama_kelas = ? WHERE id = ?")->execute([$nama, $id]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '🏫 Nama kelas berhasil diubah.'];
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 5. TAMBAH AKUN GURU/STAF
// ====================================================================================
elseif ($aksi === 'tambah_guru') {
    $nama     = trim($_POST['nama_guru'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'guru';

    if (!empty($nama) && !empty($username) && !empty($password)) {
        try {
            $conn->prepare("INSERT INTO staf_sekolah (nama, username, password, roles) VALUES (?, ?, ?, ?)")
                 ->execute([$nama, $username, $password, $role]);
            $_SESSION['notif'] = ['type' => 'success', 'message' => '👤 Akun staf berhasil didaftarkan.'];
        } catch (PDOException $e) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Username sudah dipakai atau error: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Data staf tidak lengkap.'];
    }
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 6. EDIT STAF
// ====================================================================================
elseif ($aksi === 'edit_staf' || $aksi === 'edit_guru') {
    $id   = $_POST['id'] ?? '';
    $nama = trim($_POST['nama'] ?? '');
    $role = $_POST['role'] ?? 'guru';

    $conn->prepare("UPDATE staf_sekolah SET nama = ?, roles = ? WHERE id = ?")
         ->execute([$nama, $role, $id]);

    // Update password jika diisi
    $password_baru = $_POST['password'] ?? '';
    if (!empty($password_baru)) {
        $conn->prepare("UPDATE staf_sekolah SET password = ? WHERE id = ?")->execute([$password_baru, $id]);
    }

    $_SESSION['notif'] = ['type' => 'success', 'message' => '👤 Data staf berhasil diperbarui.'];
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 7. TAMBAH KATEGORI PELANGGARAN
// ====================================================================================
elseif ($aksi === 'tambah_kategori') {
    $nama_kejadian = trim($_POST['nama_kejadian'] ?? '');
    $bobot_risiko  = $_POST['bobot_risiko'] ?? 'ringan';

    if (!empty($nama_kejadian)) {
        $conn->prepare("INSERT INTO violation_categories (nama_kejadian, bobot_risiko) VALUES (?, ?)")
             ->execute([$nama_kejadian, $bobot_risiko]);
        $_SESSION['notif'] = ['type' => 'success', 'message' => '⚖️ Kategori pelanggaran berhasil ditambahkan.'];
    }
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 8. EDIT KATEGORI PELANGGARAN
// ====================================================================================
elseif ($aksi === 'edit_kategori') {
    $id           = $_POST['id'] ?? '';
    $nama         = trim($_POST['nama'] ?? '');
    $bobot_risiko = $_POST['bobot_risiko'] ?? 'ringan';

    $conn->prepare("UPDATE violation_categories SET nama_kejadian = ?, bobot_risiko = ? WHERE id = ?")
         ->execute([$nama, $bobot_risiko, $id]);
    $_SESSION['notif'] = ['type' => 'success', 'message' => '⚖️ Buku pedoman aturan berhasil direvisi.'];
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 9. UPLOAD LOGO SEKOLAH
// ====================================================================================
elseif ($aksi === 'upload_logo') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === 0) {
        $ekstensi = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if ($ekstensi === 'png') {
            // Pastikan folder assets ada
            $assets_dir = dirname(__DIR__) . '/assets';
            if (!is_dir($assets_dir)) {
                mkdir($assets_dir, 0755, true);
            }
            $tujuan = $assets_dir . '/logo_sekolah.png';
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $tujuan)) {
                $_SESSION['notif'] = ['type' => 'success', 'message' => '🖼️ Logo instansi sekolah berhasil diperbarui!'];
            } else {
                $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menyimpan berkas logo. Periksa permission folder assets/'];
            }
        } else {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Format logo ditolak. Ekstensi wajib .PNG!'];
        }
    }
    header("Location: ../index.php");
    exit();
}

// ====================================================================================
// 10. MANAGE STAF (Create/Edit) - ADMIN PANEL
// ====================================================================================
elseif ($aksi === 'manage_staf') {
    // Proteksi: hanya admin
    if (!in_array('admin', $_SESSION['user_roles'] ?? [])) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Anda tidak memiliki akses untuk operasi ini.'];
        header("Location: ../admin_panel.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn !== null) {
        $staf_id = $_POST['staf_id'] ?? '';
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $roles = trim($_POST['roles'] ?? 'guru');
        $password = $_POST['password'] ?? '';

        // Validasi
        if (empty($nama) || empty($email) || empty($username) || empty($roles)) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Semua field wajib diisi!'];
            header("Location: ../admin_panel.php?tab=staf");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Format email tidak valid!'];
            header("Location: ../admin_panel.php?tab=staf");
            exit();
        }

        try {
            $conn->beginTransaction();

            if ($staf_id) {
                // Edit staf yang ada
                if ($password) {
                    // Hash password jika ada yang baru
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("UPDATE staf_sekolah SET nama = ?, email = ?, roles = ?, password = ? WHERE id = ?");
                    $stmt->execute([$nama, $email, $roles, $hashed_password, $staf_id]);
                } else {
                    // Tidak ubah password
                    $stmt = $conn->prepare("UPDATE staf_sekolah SET nama = ?, email = ?, roles = ? WHERE id = ?");
                    $stmt->execute([$nama, $email, $roles, $staf_id]);
                }
                $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Data staf berhasil diperbarui!'];
            } else {
                // Buat staf baru
                if (empty($password) || strlen($password) < 6) {
                    throw new Exception('Password baru harus minimal 6 karakter!');
                }

                // Cek jika username atau email sudah ada
                $stmt_check = $conn->prepare("SELECT id FROM staf_sekolah WHERE username = ? OR email = ? LIMIT 1");
                $stmt_check->execute([$username, $email]);
                if ($stmt_check->fetch()) {
                    throw new Exception('Username atau email sudah terdaftar!');
                }

                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO staf_sekolah (nama, email, username, password, roles) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama, $email, $username, $hashed_password, $roles]);
                $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Staf baru berhasil ditambahkan!'];
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
        }
    }
    header("Location: ../admin_panel.php?tab=staf");
    exit();
}

// ====================================================================================
// 11. DELETE STAF - ADMIN PANEL
// ====================================================================================
elseif ($aksi === 'delete_staf') {
    // Proteksi: hanya admin
    if (!in_array('admin', $_SESSION['user_roles'] ?? [])) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Anda tidak memiliki akses untuk operasi ini.'];
        header("Location: ../admin_panel.php");
        exit();
    }

    if ($conn !== null) {
        $staf_id = $_GET['staf_id'] ?? '';
        
        // Jangan boleh hapus diri sendiri
        if ($staf_id == $_SESSION['user_id']) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Anda tidak bisa menghapus akun Anda sendiri!'];
            header("Location: ../admin_panel.php?tab=staf");
            exit();
        }

        try {
            $stmt = $conn->prepare("DELETE FROM staf_sekolah WHERE id = ?");
            $stmt->execute([$staf_id]);
            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Staf berhasil dihapus!'];
        } catch (PDOException $e) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menghapus staf: ' . $e->getMessage()];
        }
    }
    header("Location: ../admin_panel.php?tab=staf");
    exit();
}

// ====================================================================================
// 12. MANAGE SISWA (Create/Edit) - ADMIN PANEL
// ====================================================================================
elseif ($aksi === 'manage_siswa') {
    // Proteksi: hanya admin
    if (!in_array('admin', $_SESSION['user_roles'] ?? [])) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Anda tidak memiliki akses untuk operasi ini.'];
        header("Location: ../admin_panel.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn !== null) {
        $siswa_id = $_POST['siswa_id'] ?? '';
        $nisn = trim($_POST['nisn'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $class_id = (int)($_POST['class_id'] ?? 0);

        // Validasi
        if (empty($nisn) || empty($nama) || $class_id <= 0) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Semua field wajib diisi!'];
            header("Location: ../admin_panel.php?tab=siswa");
            exit();
        }

        try {
            $conn->beginTransaction();

            if ($siswa_id) {
                // Edit siswa yang ada (NISN tidak bisa diubah)
                $stmt = $conn->prepare("UPDATE students SET nama = ?, class_id = ? WHERE id = ?");
                $stmt->execute([$nama, $class_id, $siswa_id]);
                $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Data siswa berhasil diperbarui!'];
            } else {
                // Buat siswa baru
                // Cek jika NISN sudah ada
                $stmt_check = $conn->prepare("SELECT id FROM students WHERE nisn = ? LIMIT 1");
                $stmt_check->execute([$nisn]);
                if ($stmt_check->fetch()) {
                    throw new Exception('NISN sudah terdaftar!');
                }

                $stmt = $conn->prepare("INSERT INTO students (nisn, nama, class_id, status_warna, level_eskalasi) VALUES (?, ?, ?, 'hijau', 'teguran')");
                $stmt->execute([$nisn, $nama, $class_id]);
                $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Siswa baru berhasil ditambahkan!'];
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Error: ' . $e->getMessage()];
        }
    }
    header("Location: ../admin_panel.php?tab=siswa");
    exit();
}

// ====================================================================================
// 13. DELETE SISWA - ADMIN PANEL
// ====================================================================================
elseif ($aksi === 'delete_siswa') {
    // Proteksi: hanya admin
    if (!in_array('admin', $_SESSION['user_roles'] ?? [])) {
        $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Anda tidak memiliki akses untuk operasi ini.'];
        header("Location: ../admin_panel.php");
        exit();
    }

    if ($conn !== null) {
        $siswa_id = $_GET['siswa_id'] ?? '';
        
        try {
            $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$siswa_id]);
            $_SESSION['notif'] = ['type' => 'success', 'message' => '✅ Siswa berhasil dihapus!'];
        } catch (PDOException $e) {
            $_SESSION['notif'] = ['type' => 'error', 'message' => '❌ Gagal menghapus siswa: ' . $e->getMessage()];
        }
    }
    header("Location: ../admin_panel.php?tab=siswa");
    exit();
}

// Jika aksi tidak dikenali
$_SESSION['notif'] = ['type' => 'error', 'message' => '⚠️ Aksi tidak dikenali.'];
header("Location: ../index.php");
exit();
