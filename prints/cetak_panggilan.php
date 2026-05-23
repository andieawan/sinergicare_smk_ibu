<?php
// cetak_panggilan.php
// PERBAIKAN PATH: Tambahkan ../ karena file dimasukkan ke dalam folder prints/
require_once '../config/config.php';

if (!isset($_GET['student_id'])) {
    die("Data siswa tidak valid.");
}
// ... sisa kode di bawahnya tetap biarkan sama seperti aslinya
$student_id = $_GET['student_id'];

$tgl_input = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$jam_input = isset($_GET['jam']) ? $_GET['jam'] : '';

function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '........................';
    
    $hari_array = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $bulan_array = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    $timestamp = strtotime($tanggal);
    $num_hari  = date('N', $timestamp);
    $tgl       = date('d', $timestamp);
    $num_bulan = date('n', $timestamp);
    $tahun     = date('Y', $timestamp);
    
    return $hari_array[$num_hari] . ', ' . $tgl . ' ' . $bulan_array[$num_bulan] . ' ' . $tahun;
}

$jadwal_hari_tanggal = formatTanggalIndo($tgl_input);
$jadwal_waktu        = !empty($jam_input) ? $jam_input . ' WIB' : '........................ WIB';

$stmt = $conn->prepare("SELECT s.*, c.nama_kelas FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = :id");
$stmt->execute(['id' => $student_id]);
$siswa = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Panggilan Orang Tua - <?php echo $siswa['nama']; ?></title>
    <style>
        body { font-family: "Times New Roman", Times, serif; font-size: 14px; padding: 40px; line-height: 1.5; color: #000; }
        
        /* RE-DESIGN KOP MENGGUNAKAN FLEXBOX AGAR SEJAJAR LAYOUT DENGAN LOGO */
        .kop { display: flex; align-items: center; justify-content: center; border-bottom: 4px double #000; padding-bottom: 12px; margin-bottom: 30px; gap: 20px; }
        .kop-text { text-align: center; flex-grow: 1; }
        .kop h2 { margin: 0; font-size: 18px; text-transform: uppercase; line-height: 1.3; }
        .kop p { margin: 5px 0 0 0; font-size: 12px; }
        
        /* UKURAN LOGO RESMI DALAM KOP */
        .logo-img { max-height: 85px; max-width: 85px; object-fit: contain; }
        .spacer { width: 85px; } /* Penyeimbang Flexbox kanan agar teks judul tetap center murni */

        .nomor-surat { margin-bottom: 25px; }
        .content { margin-bottom: 30px; text-align: justify; }
        .table-identitas { margin: 15px 40px; }
        .table-identitas td { padding: 4px 10px; }
        .ttd { float: right; text-align: center; width: 250px; margin-top: 40px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">

    <div class="kop">
        <?php if (file_exists('uploads/logo.png')): ?>
            <img src="uploads/logo.png" class="logo-img" alt="Logo Sekolah">
        <?php endif; ?>
        
        <div class="kop-text">
            <h2>PEMERINTAH PROVINSI / YAYASAN PENDIDIKAN<br>SMK PUSAT KEUNGGULAN SINERGICARE</h2>
            <p>Jl. Jenderal Sudirman No. 123, Indonesia | Telp: (021) 555-1234 | Email: info@smk.sch.id</p>
        </div>

        <?php if (file_exists('uploads/logo.png')): ?>
            <div class="spacer"></div>
        <?php endif; ?>
    </div>

    <div class="nomor-surat">
        <table width="100%">
            <tr><td>Nomor</td><td>: 045 / B / SinergiCare-SMK / <?php echo date('Y'); ?></td><td align="right"><?php echo date('d F Y'); ?></td></tr>
            <tr><td>Lampiran</td><td>: -</td></tr>
            <tr><td>Perihal</td><td>: <strong>Undangan Panggilan Orang Tua / Wali Murid</strong></td></tr>
        </table>
    </div>

    <p>Kepada Yth.<br>Orang Tua / Wali Murid dari <strong><?php echo $siswa['nama']; ?></strong><br>Di Tempat</p>

    <div class="content">
        <p>Dengan hormat,</p>
        <p>Sehubungan dengan diperlukannya koordinasi terkait perkembangan kedisiplinan dan perilaku putra/putri Bapak/Ibu di lingkungan sekolah, dengan ini kami mengharapkan kehadiran Bapak/Ibu Wali Murid pada:</p>
        
        <table class="table-identitas">
            <tr><td>Hari / Tanggal</td><td>: <strong><?php echo $jadwal_hari_tanggal; ?></strong></td></tr>
            <tr><td>Waktu</td><td>: <strong><?php echo $jadwal_waktu; ?></strong></td></tr>
            <tr><td>Tempat</td><td>: Ruang Bimbingan Konseling (BK) SMK</td></tr>
            <tr><td>Keperluan</td><td>: Pembahasan Khusus Mengenai Radar Perilaku Sistem (Status: <strong style="text-transform:uppercase;"><?php echo $siswa['status_warna']; ?></strong>)</td></tr>
        </table>

        <p>Mengingat pentingnya permasalahan ini demi masa depan proses belajar mengajar anak didik, kehadiran Bapak/Ibu sangat kami harapkan tepat pada waktunya. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.</p>
    </div>

    <div class="ttd">
        <p>Mengetahui,<br>Guru Pembimbing BK</p>
        <br><br><br><br>
        <p>__________________________<br>NIP / NIPY.</p>
    </div>

</body>
</html>