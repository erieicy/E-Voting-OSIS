# Sistem E-Voting Pemilihan Ketua OSIS Berbasis Jaringan Lokal (LAN)

Aplikasi web pemungutan suara elektronik (*E-Voting*) untuk Pemilihan Ketua & Wakil Ketua OSIS yang berjalan 100% secara lokal (Wi-Fi/LAN) tanpa memerlukan koneksi internet saat pemilihan berlangsung. Menggunakan mekanisme **One-Time Token** (kode sekali pakai) yang menjamin kerahasiaan suara (**LUBER JURDIL**).

---

## Fitur Utama

1. **Web Installer Mandiri (`install.php`):** Pengaturan database, pembuatan tabel otomatis, pengisian identitas sekolah, dan pembuatan akun Admin langsung dari browser tanpa perlu membuka phpMyAdmin. Otomatis mengunci instalasi dengan `installed.lock`.
2. **One-Time Token Generator & Cetak Siap Gunting:** Pembuatan hingga 1.000 token per eksekusi (6 karakter huruf kapital & angka tanpa karakter ambigu `O`/`0` dan `I`/`1`) serta halaman cetak khusus kertas A4.
3. **Pencegahan Double-Submit (Transaksi Atomik):** Validasi dan penghangusan token dilakukan menggunakan transaksi MySQL (`PDO::beginTransaction`) sehingga aman dari *race condition*.
4. **Kerahasiaan Suara (Voter Anonymity):** Tabel `tokens` tidak menyimpan relasi ke kandidat yang dipilih. Panitia dapat memverifikasi kapan token digunakan, namun tidak dapat melacak pilihan pemilih.
5. **CRUD Kandidat & Upload Foto Kustom:** Unggah foto kandidat langsung dari halaman Admin dengan pratinjau langsung (*live preview*), penamaan file kustom/otomatis (*slugify*), dan penghapusan otomatis file foto lama (`unlink`).
6. **Mode Kunci Hasil (*Lock Quick Count*) & Reset Data:** Grafik perolehan suara dapat disembunyikan selama pemungutan suara berlangsung, serta tersedia fitur reset data yang dilindungi password Admin.
7. **Antarmuka Minimalis (White & Dark Theme):** Dibangun menggunakan *Modular Vanilla CSS* ringan (tanpa CDN/framework berat) dengan pilihan tema **White (Terang)** dan **Dark (Gelap)** yang dapat diubah langsung melalui menu **Pengaturan**.

---

## Spesifikasi Teknis

- **Backend:** PHP Native (Kompatibel PHP 7.4 hingga PHP 8.3+)
- **Database:** MySQL 5.7+ / MariaDB 10.4+ (PDO Prepared Statements)
- **Frontend:** HTML5, Modular Vanilla CSS3 (`assets/css/`), Vanilla JavaScript (`assets/js/`)
- **Server Lokal:** XAMPP, Laragon, WAMP, atau Apache/Nginx

---

## Struktur Direktori

```text
E-Voting-OSIS/
├── assets/
│   ├── css/
│   │   ├── base.css                # Variabel tema (White/Dark), reset, & komponen dasar
│   │   ├── voter-login.css         # Halaman input token pemilih & login admin
│   │   ├── bilik-suara.css         # Kartu kandidat, modal visi-misi, & konfirmasi
│   │   ├── admin-layout.css        # Navigasi sidebar, tabel, & form admin
│   │   ├── admin-dashboard.css     # Kartu statistik & grafik suara (CSS Bar Chart)
│   │   ├── print-token.css         # Cetak kertas token A4 (@media print)
│   │   └── installer.css           # Wizard instalasi mandiri (install.php)
│   ├── js/
│   │   ├── bilik-suara.js          # Interaktivitas modal visi-misi, konfirmasi, & timer
│   │   └── admin.js                # Live preview gambar upload, slug, & tema
│   └── uploads/
│       ├── .htaccess               # Blokir eksekusi skrip & directory listing di folder upload
│       ├── .gitkeep
│       └── default-candidate.png   # Foto cadangan default kandidat
├── config/
│   ├── .htaccess                   # Blokir akses HTTP langsung ke file konfigurasi
│   ├── config.example.php          # Template konfigurasi database
│   └── database.php                # Koneksi PDO & helper keamanan
├── admin/
│   ├── index.php                   # Dashboard statistik real-time
│   ├── login.php                   # Login panitia
│   ├── kandidat.php                # CRUD kandidat & upload foto kustom
│   ├── token.php                   # Generator & manajemen token
│   ├── cetak_token.php             # Halaman cetak lembar token siap gunting
│   ├── hasil.php                   # Grafik perolehan suara & kunci hasil
│   ├── pengaturan.php              # Pengaturan sekolah, tema White/Dark, & reset simulasi
│   └── logout.php
├── database/
│   ├── .htaccess                   # Blokir akses HTTP langsung ke file skema SQL
│   └── database.sql                # Dump SQL untuk import manual
├── index.php                       # Halaman depan bilik suara (input token)
├── bilik_suara.php                 # Halaman surat suara & daftar kandidat
├── proses_vote.php                 # Eksekusi transaksi suara & penghangusan token
├── install.php                     # Wizard instalasi otomatis
├── .htaccess                       # Blokir akses ke .git/, file sensitif, & directory listing
├── .gitignore
├── LICENSE
└── README.md
```

---

## Panduan Persiapan untuk Pemula (Step-by-Step dari Nol)

> **Catatan untuk Panitia OSIS/MPK:** Anda **tidak perlu paham bahasa pemrograman** untuk menjalankan aplikasi ini. Cukup ikuti langkah berurutan dari **Langkah 1** sampai **Langkah 3** di bawah ini di laptop/komputer yang akan dijadikan server panitia.

### Langkah 1: Download & Install Aplikasi Server Lokal (Pilih Salah Satu)

Aplikasi website seperti E-Voting ini tidak bisa langsung dibuka seperti file Word/PDF. Laptop panitia membutuhkan satu aplikasi gratis bernama **XAMPP** atau **Laragon** agar bisa berfungsi sebagai "server" mini untuk menyimpan data dan menampilkan halaman voting ke HP/laptop bilik suara.

**Cukup pilih dan install SALAH SATU saja** (disarankan **XAMPP** karena paling umum digunakan di sekolah):

#### Opsi A: Download & Install XAMPP (Direkomendasikan)

1. Buka browser (Chrome / Microsoft Edge), lalu buka situs resmi XAMPP berikut:
   ```text
   https://www.apachefriends.org/download.html
   ```
2. Cari bagian **XAMPP for Windows**, pilih versi PHP 8.2 (atau versi paling atas), lalu klik tombol **Download (64 bit)**.
3. Tunggu proses download selesai. File yang terunduh bernama seperti `xampp-windows-x64-...-installer.exe` (biasanya masuk di folder **Downloads**).
4. Buka folder **Downloads**, lalu klik dua kali file installer tersebut untuk mulai menginstall:
   - Jika muncul pertanyaan *"Do you want to allow this app to make changes to your device?"*, klik **Yes**.
   - Jika muncul jendela peringatan *(Warning)* tentang antivirus/UAC, cukup klik **OK**.
5. Pada layar **Setup - XAMPP**, klik tombol **Next**.
6. Pada bagian **Select Components** (pilihan komponen), biarkan pengaturan bawaan (pastikan **Apache**, **MySQL**, **PHP**, dan **phpMyAdmin** sudah tercentang), lalu klik **Next**.
7. Pada bagian **Installation folder**, **biarkan lokasi default** di `C:\xampp` (jangan diubah agar mudah mengikuti panduan ini), lalu klik **Next**.
8. Pilih bahasa (English), klik **Next** -> klik **Next** lagi untuk memulai instalasi, lalu tunggu beberapa menit hingga bar hijau penuh.
9. Setelah selesai, biarkan centang *"Do you want to start the Control Panel now?"* aktif, lalu klik **Finish**.
10. Jendela **XAMPP Control Panel** akan terbuka. *(Jika suatu saat tertutup, Anda bisa membukanya lagi lewat menu Start Windows lalu ketik `XAMPP Control Panel`)*.

#### Opsi B: Download & Install Laragon (Alternatif Lebih Ringan)

1. Buka browser, lalu buka situs resmi Laragon berikut:
   ```text
   https://laragon.org/download/
   ```
2. Pilih dan klik tombol download untuk **Laragon - Full** (berisi paket lengkap Apache, MySQL, dan PHP).
3. Setelah file installer `.exe` selesai diunduh di folder **Downloads**, klik dua kali untuk menjalankannya. Jika muncul pertanyaan izin Windows, klik **Yes**.
4. Pilih bahasa instalasi (**English** atau **Indonesia** jika tersedia), lalu klik **OK**.
5. Pada pilihan lokasi instalasi, **biarkan default** di `C:\laragon`, lalu klik **Next** -> **Next** -> **Install**.
6. Tunggu hingga selesai, lalu klik **Finish**.
7. Buka aplikasi **Laragon** dari ikon di Desktop atau melalui menu Start Windows.

---

### Langkah 2: Ambil Kode Aplikasi dari GitHub

Ada **2 cara** untuk mengambil file aplikasi E-Voting ini dari GitHub. Pilih cara yang menurut Anda paling mudah:

- **Cara A (Download ZIP):** Paling mudah untuk pemula, cukup pakai browser, **tanpa perlu menginstall aplikasi tambahan atau mengetik perintah**.
- **Cara B (`git clone`):** Menggunakan perintah terminal/CMD (cocok jika ingin lebih rapi dan mudah memperbarui kode di kemudian hari).

#### Cara A: Download Langsung Berupa File ZIP (Tanpa Aplikasi Tambahan)

1. Buka halaman GitHub proyek ini di browser:
   ```text
   https://github.com/erieicy/E-Voting-OSIS
   ```
2. Di halaman utama proyek, cari tombol berwarna hijau bertuliskan **`<> Code`** (di sebelah kanan atas daftar file), lalu klik tombol tersebut.
3. Pada menu kecil yang muncul, klik **`Download ZIP`**.
4. Tunggu hingga file **`E-Voting-OSIS-main.zip`** selesai terunduh di folder **Downloads**.
5. Buka folder **Downloads**, klik kanan pada file **`E-Voting-OSIS-main.zip`**, lalu pilih **Extract All...** -> klik **Extract** (atau klik kanan -> *Extract Here* jika memakai WinRAR / 7-Zip).
6. **PERHATIAN PENTING (Sering Terjadi Kesalahan di Tahap Ini):**
   - Setelah diekstrak, ubah nama (*Rename*) folder **`E-Voting-OSIS-main`** menjadi **`E-Voting-OSIS`** (hapus akhiran `-main`).
   - Klik dua kali masuk ke dalam folder `E-Voting-OSIS` untuk mengecek isinya. Pastikan isinya **langsung** berupa file seperti `index.php`, `install.php`, `README.md`, serta folder `admin`, `assets`, `config`, dan `database`.
   - **Hindari folder ganda** seperti `E-Voting-OSIS\E-Voting-OSIS\index.php`. Jika terlanjur ada folder ganda di dalamnya, keluarkan isinya agar tidak bertumpuk.
7. Pindahkan (*Cut* lalu *Paste*) folder **`E-Voting-OSIS`** ke dalam folder server sesuai aplikasi yang Anda install di Langkah 1:
   - **Jika memakai XAMPP:** pindahkan ke `C:\xampp\htdocs\` sehingga alamat foldernya menjadi:
     ```text
     C:\xampp\htdocs\E-Voting-OSIS
     ```
   - **Jika memakai Laragon:** pindahkan ke `C:\laragon\www\` sehingga alamat foldernya menjadi:
     ```text
     C:\laragon\www\E-Voting-OSIS
     ```

#### Cara B: Menggunakan `git clone` (Via Command Prompt / Git Bash)

Jika Anda ingin mengambil proyek langsung memakai Git (atau ingin bisa melakukan update otomatis nanti):

1. **Install Aplikasi Git terlebih dahulu** (jika komputer belum punya Git):
   - Buka browser dan download Git for Windows dari situs resminya:
     ```text
     https://git-scm.com/downloads/win
     ```
   - Klik **Click here to download** (64-bit Git for Windows Setup).
   - Buka file `.exe` yang terunduh, lalu klik **Next** terus dengan pengaturan bawaan hingga tombol **Install**, tunggu selesai, lalu klik **Finish**.
2. **Buka Command Prompt (CMD) atau Git Bash:**
   - Tekan tombol **Windows** di keyboard, ketik `cmd` (atau `Git Bash`), lalu tekan **Enter**.
3. **Arahkan ke folder web server Anda** dengan mengetik salah satu perintah di bawah ini lalu tekan **Enter**:
   - **Jika memakai XAMPP:**
     ```bat
     cd C:\xampp\htdocs
     ```
   - **Jika memakai Laragon:**
     ```bat
     cd C:\laragon\www
     ```
4. **Jalankan perintah `git clone` berikut** (ketik atau *copy-paste*, lalu tekan **Enter**):
   ```bash
   git clone https://github.com/erieicy/E-Voting-OSIS.git
   ```
5. Tunggu hingga muncul tulisan `done.` (100%). Folder **`E-Voting-OSIS`** akan otomatis dibuat di dalam folder `htdocs` (XAMPP) atau `www` (Laragon) tanpa perlu memindahkan atau mengubah nama folder apa pun.
   *(Catatan: Untuk mengambil pembaruan terbaru di masa mendatang, buka CMD di dalam folder `E-Voting-OSIS` lalu ketik `git pull`.)*

---

### Langkah 3: Nyalakan Server & Jalankan Instalasi Web (`install.php`)

#### Cara 1: Instalasi Otomatis via Web Installer (Direkomendasikan)

1. Pastikan folder aplikasi sudah berada di lokasi yang benar:
   - **XAMPP:** `C:\xampp\htdocs\E-Voting-OSIS`
   - **Laragon:** `C:\laragon\www\E-Voting-OSIS`
2. **Nyalakan modul Apache dan MySQL:**
   - **Jika memakai XAMPP:** Buka **XAMPP Control Panel**, lalu klik tombol **Start** pada baris **Apache** dan tombol **Start** pada baris **MySQL** hingga kedua tulisan berubah warna menjadi **hijau**.
   - **Jika memakai Laragon:** Buka **Laragon**, lalu klik tombol **Start All** hingga Apache dan MySQL berstatus aktif/berjalan.
   - *(Penting: Jika Windows memunculkan pop-up **Windows Security Alert / Firewall**, centang **Private networks** dan **Public networks**, lalu klik **Allow access** agar HP/perangkat lain di Wi-Fi yang sama bisa mengakses bilik suara).*
3. Buka browser (Chrome/Edge) di laptop server, lalu ketik alamat berikut di kolom alamat atas (*address bar*):
   ```text
   http://localhost/E-Voting-OSIS/install.php
   ```
4. Isi formulir instalasi yang muncul di layar:
   - **Koneksi Database:** Host `localhost`, Nama Database `db_evoting_osis`, Username `root`, dan Password kosongkan *(jika menggunakan bawaan XAMPP/Laragon)*.
   - **Identitas Sekolah & Tema:** Isi Nama Sekolah, Periode Kepengurusan (contoh: `2026/2027`), dan pilih Tema Tampilan (`White` atau `Dark`).
   - **Akun Administrator Pertama:** Buat Nama Lengkap Panitia, Username login, dan Password login Admin (minimal 5 karakter) lalu catat baik-baik agar tidak lupa.
5. Klik **Jalankan Instalasi Sekarang**. Sistem akan otomatis membuat database, seluruh tabel, file `config/config.php`, dan mengunci halaman instalasi dengan file `installed.lock`.
6. Setelah muncul pesan **Instalasi Berhasil**, klik **Masuk Panel Admin** untuk mulai menambahkan data kandidat dan mencetak token.

#### Cara 2: Instalasi Manual via phpMyAdmin (Alternatif)

1. Buat database baru bernama `db_evoting_osis` di `http://localhost/phpmyadmin`.
2. Import file `database/database.sql`.
3. Salin file `config/config.example.php` menjadi `config/config.php` dan sesuaikan kredensial database jika diperlukan.
4. Buat file kosong bernama `installed.lock` di direktori utama proyek.
5. Login ke halaman Admin (`http://localhost/E-Voting-OSIS/admin/login.php`) menggunakan akun bawaan:
   - **Username:** `admin`
   - **Password:** `admin123`

---

## Panduan Konfigurasi Jaringan Lokal (LAN / Wi-Fi)

Agar perangkat di bilik suara (Smartphone, Tablet, atau Laptop klien) dapat mengakses server panitia tanpa internet:

1. Hubungkan **Laptop Server Panitia** dan seluruh **Perangkat Bilik Suara** ke jaringan Wi-Fi / Router LAN / Hotspot yang sama.
2. Pada Laptop Server (Windows), buka **Command Prompt (CMD)** atau **PowerShell**, lalu jalankan perintah:
   ```powershell
   ipconfig
   ```
3. Cari bagian **Wireless LAN adapter Wi-Fi** atau **Ethernet adapter**, lalu catat baris **IPv4 Address**, misalnya:
   ```text
   IPv4 Address. . . . . . . . . . . : 192.168.1.10
   ```
   *(Tips: Alamat link bilik suara beserta IP lokal ini juga ditampilkan langsung di halaman **Dashboard Admin** sehingga tinggal disalin atau diketik).*
4. Pada browser perangkat di setiap bilik suara, buka alamat berikut (ganti `192.168.1.10` sesuai IP laptop server Anda):
   ```text
   http://192.168.1.10/E-Voting-OSIS/
   ```
5. Pastikan **Windows Firewall** pada Laptop Server mengizinkan koneksi masuk untuk port `80` (Apache HTTP Server).

---

## Mengubah Tema Tampilan (White / Dark)

1. Masuk ke panel Administrator (`/admin/login.php`).
2. Buka menu **Pengaturan** (`/admin/pengaturan.php`).
3. Pada kolom **Tema Tampilan Antarmuka (UI)**, pilih **White (Terang / Bersih)** atau **Dark (Gelap / Kontras Rendah Cahaya)**.
4. Klik **Simpan Pengaturan**. Seluruh halaman Admin maupun Bilik Suara akan otomatis menggunakan tema yang dipilih.

---

## Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE). Bebas digunakan dan dimodifikasi oleh kepanitiaan OSIS/MPK sekolah mana pun.
