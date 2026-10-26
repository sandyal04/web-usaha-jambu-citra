# SISTEM INFORMASI PENGELOLAAN BIAYA OPERASIONAL DAN ANALISIS KEUNTUNGAN USAHA TANI JAMBU CITRA

Aplikasi web berbasis **PHP Native 8.2+**, **MySQL / MariaDB**, **PDO**, **Bootstrap 5**, dan **Chart.js** yang dirancang secara terstruktur, aman, responsif, dan siap dijalankan secara lokal menggunakan **XAMPP**.

Aplikasi ini ditujukan untuk mempermudah pencatatan, pemisahan arus fisik panen vs omset penjualan, pengelolaan pengeluaran operasional kebun, pengawasan piutang tagihan pelanggan, dan penghitungan laba rugi operasional secara akurat.

---

## 1. Fitur Utama Aplikasi

1. **Autentikasi Pengguna Tunggal (Single User System):**
   - Tidak ada pembagian role yang rumit; satu akun pengelola utama mengendalikan seluruh sistem.
   - Dilengkapi *in-browser initial setup* (`auth/setup.php`) dengan hashing password `password_hash()` (Bcrypt).
   - Dilindungi pembatasan percobaan gagal (*rate-limiting/throttling*) dan regenerasi ID sesi `session_regenerate_id(true)`.

2. **Dashboard Keuangan Interaktif:**
   - Ringkasan total pendapatan (*confirmed*), total biaya operasional, keuntungan bersih (*operating profit*), total berat panen (Kg), dan transaksi aktif.
   - Grafik interaktif Chart.js: perbandingan pendapatan vs biaya bulanan dan volume hasil panen.
   - Tabel ringkasan 5 transaksi penjualan dan pengeluaran biaya terbaru.
   - Filter periode tahun dan bulan.

3. **Manajemen Data Kebun (Farms):**
   - Pencatatan lahan kebun jambu citra: nama kebun, lokasi, luas lahan (m²/hektar/bata), dan jumlah pohon jambu produktif.
   - Proteksi integritas relasional: kebun tidak dapat dihapus jika memiliki riwayat pengeluaran atau panen.

4. **Kategori Biaya Operasional:**
   - 9 Kategori awal standar pertanian: Pupuk, Pestisida & Fungisida, Tenaga Kerja, Pengairan, Pembungkusan Buah, Peralatan, Transportasi, Perawatan Kebun, dan Biaya Lainnya.
   - Pencegahan penghapusan kategori yang sedang digunakan oleh transaksi.

5. **Pengelolaan Biaya Operasional Kebun:**
   - Pencatatan pengeluaran pupuk, obat semprot, upah kerja, dan perlengkapan.
   - Otomatis menghitung: `Total Biaya = Kuantitas × Harga Satuan` (JavaScript live calculation & server validation).
   - Mendukung pengeluaran borongan/gelondongan langsung tanpa kuantitas satuan.
   - Filter tanggal, kebun, kategori, dan pencarian nama kebutuhan.

6. **Pencatatan Hasil Panen (Fisik Produksi):**
   - Pencatatan hasil petik buah jambu citra (Kg) per grade kualitas (Grade Super, Grade A, Grade B, Grade Standar, Sortiran BS).
   - **Aturan Finansial Ketat:** Hasil panen dipisahkan secara tegas dari omset keuangan. Panen tidak dihitung sebagai pendapatan sebelum ada transaksi penjualan resmi.

7. **Data Pelanggan (Customers):**
   - Pencatatan pembeli, pedagang pasar, toko buah, atau pelanggan eceran (nama, no. HP/WhatsApp, alamat pengiriman, catatan preferensi).
   - Pelanggan tidak memerlukan akun login.

8. **Transaksi Penjualan Jambu Citra:**
   - Nomor faktur unik otomatis berurutan (`JC-YYYYMMDD-XXXX`).
   - Rincian multi-item per grade buah (berat Kg × harga per Kg = subtotal).
   - Status transaksi: `draft`, `confirmed`, `cancelled`.
   - **Prinsip Akuntansi:** Hanya transaksi berstatus `confirmed` yang diakui sebagai pendapatan usaha tani. Transaksi `draft` dan `cancelled` tidak menambah omset.
   - Transaksi terkonfirmasi dilindungi dari pengeditan diam-diam dengan mekanisme pembatalan resmi (*cancellation trail*).

9. **Pencatatan Pembayaran & Tagihan (Kas Masuk):**
   - Pencatatan pelunasan faktur: `Sisa Tagihan = Total Penjualan − Total Pembayaran yang Sah`.
   - Validasi ketat di mana pembayaran tidak boleh melebihi sisa tagihan.
   - Pembayaran dicatat sebagai pelunasan piutang/kas masuk dan **tidak dihitung ulang sebagai pendapatan**, mencegah penggandaan omset.

10. **Analisis Keuntungan & Laporan Keuangan:**
    - Perhitungan otomatis pada rentang tanggal yang dipilih:
      - $\text{Total Pendapatan} = \sum \text{Penjualan Confirmed}$
      - $\text{Total Biaya Operasional} = \sum \text{Biaya Periode}$
      - $\text{Keuntungan Operasional} = \text{Total Pendapatan} - \text{Total Biaya}$
      - $\text{Margin Keuntungan (\%)} = \left(\frac{\text{Keuntungan Operasional}}{\text{Total Pendapatan}}\right) \times 100\%$ (Dilengkapi pengaman pembagian nol jika pendapatan Rp 0).
    - Laporan Khusus:
      1. Laporan Biaya Operasional
      2. Laporan Hasil Panen Buah
      3. Laporan Penjualan Buah
      4. Laporan Penerimaan Kas & Piutang Berjalan
      5. Analisis Laba Rugi Operasional
    - Seluruh laporan dapat dicetak langsung menggunakan print layout CSS (`@media print`) dan diekspor ke file CSV yang aman.

---

## 2. Struktur Direktori Proyek

```
refisi usaha jambu citra/ (atau usaha-tani-jambu-citra/)
├── assets/
│   ├── css/
│   │   └── style.css            # Desain tema pertanian modern & print CSS
│   └── js/
│       └── app.js               # Kalkulator live biaya/penjualan & interaktivitas
├── config/
│   └── database.php             # Singleton PDO MySQL connection
├── includes/
│   ├── auth.php                 # Autentikasi sesi & proteksi single-user
│   ├── csrf.php                 # Token CSRF & validasi keamanan POST
│   ├── functions.php            # Helper Rupiah, tanggal Indo, URL dinamis
│   ├── header.php               # Layout atas (Bootstrap 5, Icons, Chart.js)
│   ├── sidebar.php              # Navigasi modul & active link indicator
│   └── footer.php               # Layout bawah & bundle script
├── auth/
│   ├── login.php                # Form login pengelola
│   ├── logout.php               # Handler logout & pembersihan sesi
│   └── setup.php                # Inisialisasi awal akun pengelola utama
├── farms/
│   ├── index.php                # Daftar kebun
│   ├── create.php               # Tambah kebun
│   ├── edit.php                 # Edit kebun
│   └── delete.php               # Hapus kebun aman
├── cost-categories/
│   ├── index.php                # Daftar kategori biaya
│   ├── create.php               # Tambah kategori
│   ├── edit.php                 # Edit kategori
│   └── delete.php               # Hapus kategori (dicek penggunaan)
├── costs/
│   ├── index.php                # Daftar pengeluaran operasional
│   ├── create.php               # Catat biaya baru (kalkulator otomatis)
│   ├── edit.php                 # Edit biaya
│   ├── detail.php               # Rincian biaya
│   └── delete.php               # Hapus biaya
├── harvests/
│   ├── index.php                # Daftar catatan hasil panen
│   ├── create.php               # Catat panen baru
│   ├── edit.php                 # Edit catatan panen
│   └── delete.php               # Hapus catatan panen
├── customers/
│   ├── index.php                # Daftar pelanggan
│   ├── create.php               # Tambah pelanggan
│   ├── edit.php                 # Edit pelanggan
│   └── delete.php               # Hapus pelanggan
├── sales/
│   ├── index.php                # Daftar transaksi penjualan
│   ├── create.php               # Faktur baru (multi-item & kalkulasi)
│   ├── edit.php                 # Edit draft penjualan / proteksi confirmed
│   ├── detail.php               # Rincian faktur & riwayat pelunasan
│   ├── status.php               # Konfirmasi & pembatalan aman
│   └── delete.php               # Hapus faktur draft
├── payments/
│   ├── index.php                # Daftar pembayaran masuk
│   ├── create.php               # Form pelunasan tagihan (validasi sisa piutang)
│   └── delete.php               # Hapus pembayaran
├── reports/
│   ├── profit-analysis.php      # Analisis Laba Rugi Operasional & Margin %
│   ├── costs-report.php         # Laporan pengeluaran operasional
│   ├── harvests-report.php      # Laporan produksi panen buah
│   ├── sales-report.php         # Laporan penjualan & omset
│   ├── payments-report.php      # Laporan kas & sisa piutang
│   └── export-csv.php           # Ekspor CSV aman (Anti-Formula Injection)
├── database/
│   └── database_usaha_tani_jambu_citra.sql # Skema database MySQL lengkap
├── index.php                    # Dashboard ringkasan usaha tani
└── README.md                    # Dokumentasi lengkap & panduan instalasi
```

---

## 3. Panduan Instalasi Menggunakan XAMPP

### Langkah 1: Tempatkan Folder Proyek
1. Buka folder instalasi XAMPP di komputer Anda, biasanya di:
   ```
   C:\xampp\htdocs\
   ```
2. Salin atau pastikan folder proyek berada di:
   ```
   C:\xampp\htdocs\usaha-tani-jambu-citra
   ```
   *(Catatan: Aplikasi telah dirancang dengan fungsi `url()` dinamis. Jika Anda menggunakan nama folder lain seperti `refisi usaha jambu citra`, seluruh tautan dan aset tetap berjalan sempurna tanpa error 404).*

### Langkah 2: Jalankan Layanan XAMPP
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache**.
3. Klik tombol **Start** pada modul **MySQL**.
4. Pastikan status kedua modul berwarna hijau (*Running*).

### Langkah 3: Buat dan Impor Database MySQL
1. Buka browser web (Google Chrome, Mozilla Firefox, atau Microsoft Edge).
2. Akses halaman phpMyAdmin di:
   ```
   http://localhost/phpmyadmin/
   ```
3. Klik menu **Databases** (atau **Basis Data**).
4. Masukkan nama database: `db_usaha_jambu_citra`, pilih collation `utf8mb4_unicode_ci`, lalu klik **Create**.
5. Pilih database `db_usaha_jambu_citra` di panel sebelah kiri.
6. Klik tab **Import** di bagian atas.
7. Klik tombol **Choose File** (Pilih Berkas), lalu arahkan ke file:
   ```
   database/database_usaha_tani_jambu_citra.sql
   ```
8. Gulir ke bawah dan klik tombol **Import** (atau **Kirim**).
9. Seluruh 9 tabel beserta relasi foreign key dan kategori bawaan akan berhasil dibuat.

### Langkah 4: Sesuaikan Konfigurasi Database (Jika Diperlukan)
File konfigurasi database berada di [config/database.php](file:///d:/web/refisi%20usaha%20jambu%20citra/config/database.php):
```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'db_usaha_jambu_citra');
define('DB_USER', 'root');
define('DB_PASS', ''); // Kosongkan sesuai standar default XAMPP
```
Jika MySQL Anda menggunakan password khusus atau port non-standar (misal `3307`), cukup ubah konstanta di atas.

### Langkah 5: Inisialisasi Akun Pengelola Utama
1. Buka browser dan kunjungi:
   ```
   http://localhost/usaha-tani-jambu-citra/
   ```
2. Sistem secara otomatis mendeteksi bahwa belum ada akun terdaftar dan mengarahkan Anda ke halaman **Inisialisasi Akun Utama** (`auth/setup.php`).
3. Masukkan data:
   - **Username**: (contoh: `pengelola` atau `ahmad`)
   - **Nama Lengkap**: (contoh: `H. Ahmad Subandi`)
   - **Nomor Telepon/WA**: (contoh: `081234567890`)
   - **Kata Sandi**: (minimal 6 karakter)
   - **Konfirmasi Kata Sandi**
4. Klik **Simpan & Buat Akun**.
5. Setelah berhasil, silakan login dengan akun yang baru Anda buat.
6. Halaman setup otomatis terkunci setelah akun dibuat untuk menjamin keamanan sistem.

---

## 4. Panduan Pengujian Fitur (Data Uji Terstruktur)

Gunakan alur data uji berikut untuk menguji seluruh fungsi keuangan:

### Skenario 1: Menambahkan Lahan Kebun
1. Buka menu **Data Kebun** &rarr; klik **Tambah Kebun Baru**.
2. Masukkan:
   - Nama Kebun: `Kebun Jambu Citra Blok Selatan`
   - Lokasi: `Desa Wonosari RT 03 RW 01, Demak`
   - Luas: `2500` Satuan: `m²`
   - Jumlah Pohon: `120` Pohon
   - Catatan: `Pohon umur 5 tahun, varietas okulasi unggul`
3. Klik **Simpan**.

### Skenario 2: Mencatat Biaya Operasional
1. Buka menu **Biaya Operasional** &rarr; klik **Catat Biaya Baru**.
2. Masukkan:
   - Kebun: `Kebun Jambu Citra Blok Selatan`
   - Kategori: `Pupuk`
   - Tanggal: Tanggal hari ini
   - Nama Kebutuhan: `Pupuk NPK Mutiara 16-16-16`
   - Kuantitas: `4` Satuan: `karung (50kg)`
   - Harga Satuan: `850000`
   - Perhatikan field **Total Biaya** otomatis terisi `Rp 3.400.000`.
3. Klik **Simpan**.

### Skenario 3: Mencatat Hasil Panen (Bukan Pendapatan)
1. Buka menu **Pencatatan Panen** &rarr; klik **Catat Hasil Panen Baru**.
2. Masukkan:
   - Kebun: `Kebun Jambu Citra Blok Selatan`
   - Tanggal: Tanggal hari ini
   - Kualitas/Grade: `Grade A`
   - Berat: `450` Kg
   - Catatan: `Panen pagi hari, buah mulus siap seleksi pasar`
3. Klik **Simpan**.
4. Periksa Dashboard: Total hasil panen bertambah 450 Kg, namun **Total Pendapatan Finansial tetap Rp 0** (karena hasil panen belum dijual).

### Skenario 4: Menambah Pelanggan & Melakukan Penjualan
1. Buka menu **Data Pelanggan** &rarr; Tambah:
   - Nama: `Toko Buah Barokah Pasar Induk`
   - Telepon: `081399887766`
   - Alamat: `Pasar Induk Sayur & Buah Blok C No. 5`
2. Buka menu **Penjualan Buah** &rarr; klik **Transaksi Penjualan Baru**:
   - Pelanggan: `Toko Buah Barokah Pasar Induk`
   - Tanggal: Tanggal hari ini
   - Status: `Confirmed`
   - Baris 1: `Jambu Citra Grade A`, Berat: `300` Kg, Harga/Kg: `Rp 28.000` (Subtotal: Rp 8.400.000)
   - Klik **Tambah Baris Produk**:
   - Baris 2: `Jambu Citra Grade Super`, Berat: `100` Kg, Harga/Kg: `Rp 35.000` (Subtotal: Rp 3.500.000)
   - Total Penjualan: `Rp 11.900.000`.
   - Masukkan Pembayaran Awal: `Rp 5.000.000` via `Transfer Bank`.
3. Klik **Simpan Transaksi Penjualan**.
4. Halaman faktur akan menampilkan:
   - Total Penjualan: `Rp 11.900.000`
   - Terbayar: `Rp 5.000.000`
   - Sisa Tagihan: `Rp 6.900.000` (Belum Lunas).

### Skenario 5: Pelunasan Sisa Tagihan
1. Dari halaman detail faktur atau menu **Pembayaran Tagihan**, klik **Catat Pembayaran Baru**.
2. Pilih faktur `Toko Buah Barokah`.
3. Input pembayaran sisa: `Rp 6.900.000`.
4. Jika Anda memasukkan nominal lebih besar dari Rp 6.900.000, sistem akan menolak dan memberi peringatan.
5. Simpan & status faktur berubah menjadi **LUNAS**.

### Skenario 6: Evaluasi Analisis Keuntungan Operasional
1. Buka menu **Analisis Keuntungan**.
2. Hasil analisis periode:
   - **Total Pendapatan:** Rp 11.900.000
   - **Total Biaya Operasional:** Rp 3.400.000
   - **Keuntungan Operasional:** Rp 8.500.000
   - **Margin Keuntungan:** 71,43%
3. Klik tombol **Cetak Laporan** untuk melihat tampilan cetak ramah kertas.
4. Klik tombol **Ekspor CSV** untuk mengunduh rekapan format spreadsheet.

---

## 5. Pemecahan Masalah (Troubleshooting)

1. **Error: Can't connect to MySQL server on 'localhost' (10061)**
   - *Penyebab*: Layanan MySQL pada XAMPP Control Panel belum diaktifkan.
   - *Solusi*: Buka XAMPP Control Panel dan klik tombol **Start** pada baris MySQL.

2. **Error: Table 'db_usaha_jambu_citra.farms' doesn't exist**
   - *Penyebab*: File SQL database belum diimpor ke MySQL.
   - *Solusi*: Buka `http://localhost/phpmyadmin/`, pilih database `db_usaha_jambu_citra`, lalu impor file `database/database_usaha_tani_jambu_citra.sql`.

3. **Port MySQL Bentrok (Port 3306 in use)**
   - *Penyebab*: Port 3306 digunakan oleh instans MySQL lain di Windows.
   - *Solusi*: Ubah port MySQL di XAMPP (misal ke 3307), lalu sesuaikan konstanta `DB_PORT` di [config/database.php](file:///d:/web/refisi%20usaha%20jambu%20citra/config/database.php).

4. **Karakter Tanggal atau Rupiah Tidak Sesuai**
   - *Solusi*: Sistem secara native telah menyertakan fungsi pembantu bahasa Indonesia di [includes/functions.php](file:///d:/web/refisi%20usaha%20jambu%20citra/includes/functions.php) yang tidak bergantung pada locale sistem operasi Windows (`intl`/`strftime`).

---

## 6. Standar Keamanan Sistem

- **Prepared Statements (PDO):** Menjamin seluruh query bebas dari bahaya SQL Injection.
- **CSRF Token:** Setiap formulir yang mengirimkan metode POST diverifikasi menggunakan token acak bersandi `hash_equals()`.
- **Sanitasi Output:** Seluruh data yang ditampilkan ke browser dibungkus fungsi `e()` (`htmlspecialchars` dengan flag `ENT_QUOTES`).
- **Pencegahan Formula Injection:** Seluruh ekspor CSV disaring agar karakter formula (`=`, `+`, `-`, `@`) tidak dapat mengeksekusi perintah di komputer pengguna.
- **Integritas Finansial:** Mencegah modifikasi diam-diam pada transaksi yang sudah sah dan membatasi pembayaran melebihi sisa tagihan.
