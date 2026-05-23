<?php
// download_template.php (Versi Pendekatan CSV Ramah Excel Tanpa Library)
if (isset($_GET['type'])) {
    $type = $_GET['type'];

    if ($type === 'siswa') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=template_import_siswa.csv');
        
        // Trik rahasia: Beritahu Excel bahwa file ini dipisahkan oleh titik koma (Standar Windows Indonesia)
        echo "sep=;\n"; 
        echo "nisn;nama_siswa;nama_kelas\n";
        echo "202601;Bambang Pamungkas;XI DKV 1\n";
        exit();
    }

   // Pastikan blok ini ada di dalam download_template.php milikmu
if ($type === 'staf') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_import_staf.csv');
    
    echo "sep=;\n";
    // Header kolom diganti menjadi lebih komunikatif bagi admin
    echo "nama_staf;email;password_default;role_akses_pisahkan_koma\n";
    // Contoh diubah menggunakan teks langsung (guru,bk) bukan angka (2,3)
    echo "Siti Aminah, S.Pd;siti@smk.sch.id;siti123;guru,bk\n";
    exit();
}
}
header("Location: index.php");
exit();
?>