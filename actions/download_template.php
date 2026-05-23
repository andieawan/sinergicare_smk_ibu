<?php
// download_template.php
// PERBAIKAN: Gunakan titik koma sebagai separator (konsisten dengan tipe impor CSV)
if (isset($_GET['type'])) {
    $type = $_GET['type'];

    if ($type === 'siswa') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=template_import_siswa.csv');

        // sep= memberi tahu Excel Windows untuk pakai titik koma
        echo "sep=;\n";
        echo "nisn;nama_siswa;nama_kelas\n";
        echo "202601;Bambang Pamungkas;XI DKV 1\n";
        echo "202602;Siti Aminah;XI DKV 2\n";
        exit();
    }

    if ($type === 'staf') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=template_import_staf.csv');

        echo "sep=;\n";
        // PERBAIKAN: Header kolom disesuaikan dengan urutan yang dibaca proses_admin.php
        // Kolom: nama_staf | email | password_default | role_akses
        echo "nama_staf;email;password_default;role_akses\n";
        echo "Siti Aminah S.Pd;siti@smk.sch.id;siti123;bk\n";
        echo "Joko Susanto S.Kom;joko@smk.sch.id;joko123;guru\n";
        exit();
    }
}

header("Location: ../index.php");
exit();
