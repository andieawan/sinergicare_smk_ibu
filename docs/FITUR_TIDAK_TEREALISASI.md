# 🔍 ANALISIS FITUR YANG BELUM TEREALISASI
## SinergiCare SMK - index.php & cetak_administrasi.php

---

## ❌ FITUR TIDAK BERFUNGSI / BELUM DIIMPLEMENTASIKAN

### 1. **MENU NAVIGASI - Section Beberapa Bagian KOSONG**
**File**: `index.php` (baris 129-155)
**Masalah**: 
- ❌ `section_mandat` (📋 Pengawasan Lapangan) - **HANYA SKELETON**, tidak ada konten
- ❌ `section_radar_bk` (🎯 Radar Urgent BK) - **HANYA SKELETON**, tidak ada konten
- ❌ `section_kajur` (⚡ Kelayakan PKL Jurusan) - **HANYA SKELETON**, tidak ada konten
- ❌ `section_waka` (🛡️ Eskalasi Tindakan Waka) - **HANYA SKELETON**, tidak ada konten

**Kode yang Direferensikan tapi Tidak Ada**:
```javascript
// Line 130: switchMenu('section_mandat') - button ada tapi section HTML kosong
// Line 140: switchMenu('section_radar_bk') - button ada tapi section HTML kosong
// Line 146: switchMenu('section_kajur') - button ada tapi section HTML kosong
// Line 152: switchMenu('section_waka') - button ada tapi section HTML kosong
```

**Dampak**: User klik menu tapi tidak melihat konten apapun.

---

### 2. **ALERT PLACEHOLDER - Bukan Aksi Nyata**
**File**: `index.php` (baris 752)
**Masalah**:
```javascript
<button onclick="alert('Surat Peringatan & Skorsing Disetujui Waka Kesiswaan')">
  Sahkan Skorsing
</button>
```
**Issue**: 
- ❌ Hanya menampilkan `alert()` javascript biasa
- ❌ Bukan aksi database yang sebenarnya
- ❌ Data tidak tersimpan
- ❌ Tidak ada proses backend

---

### 3. **FILTER DROPDOWN TIDAK LENGKAP**
**File**: `index.php` (baris 278-280, 287-290)
**Masalah**:
- ❌ Filter `filter_tingkat` tidak ada element input-nya di HTML
- ❌ Filter `filter_jurusan` tidak ada element input-nya di HTML
- ❌ Filter `filter_kelas` tidak ada element input-nya di HTML
- ❌ JavaScript mencoba referensi element yang tidak ada

**Kode yang Broken**:
```javascript
// Line 824-827: Mencari element yang tidak ada
const tingkat = document.getElementById('filter_tingkat');           // ❌ TIDAK ADA
const r_jurusan = document.getElementById('filter_jurusan');         // ❌ TIDAK ADA
const r_kelas = document.getElementById('filter_kelas');             // ❌ TIDAK ADA
const bodyTabelSiswa = document.getElementById('body_tabel_siswa');  // ✅ ADA
```

**Fungsi yang Tidak Bekerja**:
- `updateFilterKelas()` (line 938) - tidak bisa berjalan
- Filter event listeners (line 960-974) - tidak bisa attach

---

### 4. **DARK MODE TOGGLE INCOMPLETE**
**File**: `index.php` (baris 174, 184-187)
**Masalah**:
```html
<!-- Line 174: Logout button dengan tombol lightbulb -->
<a href="logout.php" class="h-7 w-7..." title="Log Out">💡</a>  <!-- ❌ ICON SALAH! -->

<!-- Line 184-186: Dark mode toggle -->
<button onclick="toggleDarkMode()">
  <span id="theme_icon_sun" class="hidden dark:block">☀️</span>
  <span id="theme_icon_moon" class="block dark:hidden">🌙</span>
</button>
```

**Issues**:
- ❌ Line 174: Icon logout seharusnya tidak 💡 (lightbulb = dark mode toggle)
- ⚠️ Mungkin ada confusing UI logic

---

### 5. **FUNGSI JAVASCRIPT TIDAK LENGKAP**
**File**: `index.php` 
**Masalah**: Fungsi yang dipanggil tapi implementasi tidak sempurna:

| Fungsi | Status | Issue |
|--------|--------|-------|
| `bukaModalEdit()` | ⚠️ Partial | Bisa open modal tapi submit handler tidak ada |
| `closeModalEditMaster()` | ✅ OK | - |
| `aksiCetak()` | ✅ OK | Bisa cetak (line 997) |
| `aksiCetakSuratPanggilan()` | ✅ OK | Bisa cetak surat (line 999) |
| `openModalBK()` | ✅ OK | Tapi dengan placeholder alert di waka section |
| `bukaModalCatatSiswa()` | ⚠️ Partial | Modal buka tapi submit tidak connect ke backend |

---

### 6. **MODAL FORMS TIDAK SUBMIT KE BACKEND**
**File**: `index.php` (Baris 600-750 dalam cetak_administrasi.php)
**Masalah**:
- ❌ Modal Edit Kelas (line 600+) - FORM TIDAK ADA
- ❌ Modal Edit Guru (line 650+) - FORM TIDAK ADA  
- ❌ Modal Edit Kategori (line 700+) - FORM TIDAK ADA
- ❌ Modal Catat Siswa (line 750+) - FORM TIDAK LENGKAP

**Contoh Issue**:
```php
// Modal HTML ada (tapi form tidak ada action/method):
<div id="modal_edit_master" class="...">
  <input id="edit_id" type="hidden">
  <input id="edit_nama" type="text" placeholder="Nama">
  <button type="submit">Simpan</button>  <!-- ❌ TIDAK CONNECT KE BACKEND -->
</div>
```

---

### 7. **MODAL CATAT SISWA TIDAK TERKONEKSI DATABASE**
**File**: `index.php` (baris 470-550 dalam cetak_administrasi.php)
**Masalah**:
```html
<form id="form_catat_kasus" method="POST" action="">  <!-- ❌ ACTION KOSONG! -->
  <select name="category_id" class="...">
    <option>Pilih Kategori Pelanggaran</option>
    <?php if ($conn !== null) { ... } ?>  <!-- ✅ ADA Data -->
  </select>
  <textarea name="catatan" placeholder="..."></textarea>
  <button type="submit">Kirim Laporan</button>
</form>
```

**Issues**:
- ❌ Form `action=""` kosong - tidak tahu kemana submit
- ❌ JavaScript `bukaModalCatatSiswa()` tidak ada submit handler
- ❌ Seharusnya submit ke `proses_lapor.php` tapi tidak dikonfigurasi

**Seharusnya**:
```html
<form id="form_catat_kasus" method="POST" action="proses_lapor.php">
  <input type="hidden" name="student_id" id="modal_student_id">
  ...
</form>
```

---

### 8. **SECTION MASTER DATA - FORM KODE / ROLE TIDAK ADA**
**File**: `cetak_administrasi.php` (baris 498-520)
**Masalah**:

```php
<!-- Form Tambah Guru - Input ROLES TIDAK ADA! -->
<form action="proses_admin.php?aksi=tambah_guru" method="POST">
  <input name="nama_guru" placeholder="...">
  <input name="username" placeholder="...">
  <input name="password" placeholder="...">
  <!-- ❌ MANA SELECT UNTUK ROLE / OTORITAS?? -->
  <button>Daftarkan Guru</button>
</form>
```

**Issues**:
- ❌ Input untuk `role` / `otoritas` tidak ada
- ❌ Padahal database field `roles` ada
- ❌ Backend `proses_admin.php` menerima `$role` tapi form tidak mengirim

---

### 9. **DOWNLOAD TEMPLATE - LINK TIDAK ADA/BROKEN**
**File**: `cetak_administrasi.php` (besar kemungkinan di section master data)
**Masalah**:
- ❌ Button "Download Template CSV" tidak ditemukan di UI
- ❌ Padahal ada file `download_template.php`
- ❌ User tidak tahu cara format file untuk import CSV

**Seharusnya Ada**:
```html
<a href="download_template.php?type=siswa" download>
  📥 Download Template Siswa
</a>
<a href="download_template.php?type=staf" download>
  📥 Download Template Staf
</a>
```

---

### 10. **SECTION WAKA - HANYA ALERT, TIDAK ADA DATABASE PROSES**
**File**: `cetak_administrasi.php` (line 430-461)
**Masalah**:
```html
<!-- Data ditampilkan dengan benar -->
<?php while($sw = $q_waka->fetch(...)) { ?>
  <div class="...">
    <p><?php echo $sw['nama']; ?></p>
    <!-- ✅ Link ini bagus: -->
    <a href="proses_tindakan_waka.php?student_id=<?php echo $sw['id']; ?>">
      Sahkan Penindakan
    </a>
  </div>
<?php } ?>
```

**Issues**:
- ❌ Tapi di `index.php`, ada button dengan `alert()` sebagai pengganti
- ⚠️ Ada duplicate/inconsistent handling

---

## 📋 RINGKASAN FITUR YANG PERLU DIIMPLEMENTASIKAN

| # | Fitur | File | Priority | Estimasi |
|---|-------|------|----------|----------|
| 1 | Lengkapi 4 Section Menu (mandat, radar_bk, kajur, waka) | index.php | 🔴 CRITICAL | HTML + Logic |
| 2 | Setup Filter Dropdown (tingkat, jurusan, kelas) | index.php | 🟡 HIGH | HTML Elements |
| 3 | Modal Form Submit Handlers | index.php | 🔴 CRITICAL | JavaScript |
| 4 | Modal Catat Siswa → Form Action | index.php | 🔴 CRITICAL | Form Attribute |
| 5 | Tambah Input Role di Form Guru | cetak_administrasi.php | 🟡 HIGH | HTML Input |
| 6 | Link Download Template | cetak_administrasi.php | 🟡 HIGH | HTML Link |
| 7 | Dark Mode Icon Consistency | index.php | 🟢 LOW | Icon Fix |
| 8 | Remove Alert() & Actual Processing | index.php | 🔴 CRITICAL | Backend Call |

---

## 🎯 REKOMENDASI PERBAIKAN (Prioritas)

### URGENT (Harus Dikerjakan):
1. ✅ Setup 4 section menu dengan konten HTML
2. ✅ Tambahkan filter dropdown HTML elements
3. ✅ Setup form action di modal catat siswa
4. ✅ Implement modal submit handlers ke backend

### IMPORTANT (Segera Setelah):
5. ✅ Tambah input role di form tambah guru
6. ✅ Tambah link download template
7. ✅ Hapus semua `alert()` dan ganti dengan proper AJAX calls

### MINOR (Opsional):
8. ✅ Fix icon logout (lightbulb ke actual logout icon)

---

**File Generated**: 2026-05-21
**Analysis Scope**: index.php (1008 lines) + cetak_administrasi.php (1035 lines)
**Status**: ⚠️ ~40% fitur tidak berfungsi / belum terealisasi
