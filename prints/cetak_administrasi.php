<?php
// cetak_administrasi.php (MASTER GENERATOR DOKUMEN BK)
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder prints/
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { die("Akses ditolak."); }
// ... sisa kode di bawahnya tetap biarkan sama seperti aslinya

$tipe       = $_GET['tipe'] ?? '';
$student_id = $_GET['student_id'] ?? '';
$tanggal    = $_GET['tanggal'] ?? date('Y-m-d');
$jam        = $_GET['jam'] ?? date('H:i');

// Ambil data siswa
$stmt = $conn->prepare("SELECT s.*, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
$stmt->execute([$student_id]);
$data_siswa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data_siswa) { die("Siswa tidak ditemukan."); }

// --- FUNGSI HEADER SURAT ---
function printHeader() {
    return '
    <div style="text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; position: relative;">
        
        <img src="../assets/logo_sekolah.png" style="position: absolute; left: 20px; top: 0; height: 75px; object-contain;" onerror="this.style.display=\'none\'">
        
        <h2 style="margin: 0; font-size: 18px; padding-left: 60px;">YAYASAN PEMBINA SMK SINERGICARE</h2>
        <h1 style="margin: 5px 0 0 0; font-size: 22px; padding-left: 60px;">SMK SINERGICARE INDONESIA</h1>
        <p style="margin: 5px 0 0 0; font-size: 11px; color: #555; padding-left: 60px;">Jl. Pendidikan No. 45, Distrik Kesiswaan, Kode Pos 12345</p>
    </div>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Dokumen - <?php echo $tipe; ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; padding: 40px; line-height: 1.5; font-size: 14px; }
        .ttd { margin-top: 50px; display: flex; justify-content: space-between; }
        .box-ttd { text-align: center; width: 220px; }
    </style>
</head>
<body onload="window.print()">

<?php echo printHeader(); ?>

<?php if ($tipe === 'panggilan'): ?>
    <h3 style="text-align: center; text-decoration: underline;">SURAT PANGGILAN ORANG TUA / WALI</h3>
    <p>Dengan hormat, kami mengharapkan kehadiran Bapak/Ibu orang tua/wali dari:</p>
    <p><strong>Nama: <?php echo $data_siswa['nama']; ?> | Kelas: <?php echo $data_siswa['nama_kelas']; ?></strong></p>
    <p>Untuk hadir pada <?php echo $tanggal; ?> pukul <?php echo $jam; ?> WIB di Ruang BK guna membicarakan perkembangan kedisiplinan siswa terkait.</p>

<?php elseif ($tipe === 'izin'): ?>
    <h3 style="text-align: center; text-decoration: underline;">SURAT IZIN MENINGGALKAN SEKOLAH</h3>
    <p>Diberikan izin kepada siswa:</p>
    <p><strong>Nama: <?php echo $data_siswa['nama']; ?> | Kelas: <?php echo $data_siswa['nama_kelas']; ?></strong></p>
    <p>Untuk meninggalkan lingkungan sekolah pada jam <?php echo $jam; ?> dengan alasan yang telah terverifikasi oleh pihak sekolah.</p>

<?php elseif ($tipe === 'pernyataan'): ?>
    <h3 style="text-align: center; text-decoration: underline;">SURAT PERNYATAAN KEDISIPLINAN</h3>
    <p>Saya yang bertanda tangan di bawah ini menyatakan dengan sadar untuk tidak mengulangi pelanggaran yang telah dilakukan dan siap menerima konsekuensi lebih lanjut jika melanggar kembali.</p>
    <p><strong>Nama: <?php echo $data_siswa['nama']; ?></strong></p>

<?php elseif (strpos($tipe, 'sp') !== false): 
    $level = substr($tipe, -1); ?>
    <h3 style="text-align: center; text-decoration: underline;">SURAT PERINGATAN <?php echo $level; ?> (SP - <?php echo $level; ?>)</h3>
    <p>Surat Peringatan ini diterbitkan kepada <strong><?php echo $data_siswa['nama']; ?></strong> kelas <strong><?php echo $data_siswa['nama_kelas']; ?></strong> dikarenakan telah mencapai ambang batas pelanggaran pada Zona Merah. Surat ini merupakan langkah pembinaan sebelum tindakan struktural oleh Waka Kesiswaan.</p>
<?php endif; ?>

<div class="ttd">
    <div class="box-ttd">
        <p>Orang Tua / Wali Murid</p><br><br><br>
        <p>(______________________)</p>
    </div>
    <div class="box-ttd">
        <p>Jember, <?php echo date('d-m-Y'); ?><br>Koordinator BK</p><br><br><br>
        <p><strong>( ______________________ )</strong></p>
    </div>
</div>

</body>
</html>