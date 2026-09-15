# SpaceXStat — Tugas Rutin 7: Autentikasi & Dashboard

**Automated Checks & Deployment:** [![Test & Deploy (Git-FTP)](https://github.com/Realitaa/TugasWeb-Pertemuan7-LoginRegister/actions/workflows/deploy.yml/badge.svg)](https://github.com/Realitaa/TugasWeb-Pertemuan7-LoginRegister/actions/workflows/deploy.yml)

Aplikasi web sistem autentikasi modern dan dashboard statistik misi luar angkasa SpaceX berbasis **PHP murni (Plain PHP)** yang diintegrasikan dengan **Vite 8** dan **Tailwind CSS v4**. Dilengkapi fitur lengkap registrasi pengguna, enkripsi password `bcrypt` (`PASSWORD_DEFAULT`), validasi email ketat dengan `filter_var()`, penyimpanan berkas JSON persisten, manajemen sesi login aman, fitur "Remember Me" berbasis cookie token aman, proteksi otentikasi halaman, pembaruan profil pengguna, notifikasi Toast interaktif, visualisasi grafik data SpaceX menggunakan Chart.js, serta dark mode toggle. Dibuat untuk memenuhi kriteria penilaian **Tugas Rutin 7 (Login & Register)** mata kuliah Pemrograman Web.

Website live: [spacexstat.realitaa.dev](https://spacexstat.realitaa.dev)  
Repository: [github.com/Realitaa/TugasWeb-Pertemuan7-LoginRegister](https://github.com/Realitaa/TugasWeb-Pertemuan7-LoginRegister)

---

## Daftar Kebutuhan Tugas & Pemenuhannya

Proyek ini telah mengimplementasikan seluruh kebutuhan fungsional tugas pertemuan 7 secara komprehensif dan teruji melalui 45 automated test assertions:

### Requirements Utama & Pengujian

1. **Halaman Form Registrasi & Pendaftaran Lengkap**
   - Halaman `register.php` menyediakan antarmuka form pendaftaran responsif dengan kolom: **Nama Lengkap**, **Email**, **Password**, dan **Konfirmasi Password**.
   - Dilengkapi validasi kecocokan password dengan konfirmasi password sebelum data diproses.
   - Teruji pada unit test `testRegistrationPageDisplaysAndSubmitsSuccessfully`.

2. **Validasi Email Menggunakan `filter_var()`**
   - Setiap pendaftaran dan pembaruan profil memvalidasi format email menggunakan fungsi standar PHP `filter_var($email, FILTER_VALIDATE_EMAIL)`.
   - Format email tidak valid (seperti `plainaddress`, `user@.invalid.com`, dll.) ditolak dengan pesan kesalahan yang jelas.
   - Teruji pada unit test `testRegistrationValidatesEmailWithFilterVar`.

3. **Enkripsi / Hashing Password Pengguna**
   - Seluruh password pengguna tidak pernah disimpan dalam bentuk teks polos (_plaintext_).
   - Menggunakan hashing standar industri PHP `password_hash($password, PASSWORD_DEFAULT)` dan diverifikasi secara aman menggunakan `password_verify()`.
   - Teruji pada unit test `testRegisteredUserPasswordIsHashed`.

4. **Penyimpanan Data Pengguna ke Berkas JSON**
   - Data akun pengguna disimpan secara terstruktur dan persisten pada berkas flat-file JSON (`data/users.json`).
   - Setiap entri menyimpan atribut unik `id`, `name`, `email`, `password` (hash), `created_at`, serta `remember_token`.
   - Teruji pada unit test `testRegisteredDataIsSavedToJson`.

5. **Pencegahan Duplikasi Akun (Email Unik)**
   - Sistem memeriksa ketersediaan email sebelum pendaftaran dilakukan (_case-insensitive_).
   - Jika email sudah terdaftar, pendaftaran digagalkan dan menampilkan pesan kesalahan bahwa email telah digunakan.
   - Teruji pada unit test `testRegistrationFailsOnDuplicateEmail`.

6. **Ketersediaan & Manajemen Session Login**
   - Setelah kredensial valid diverifikasi pada `index.php`, session login aktif diinisialisasi melalui `$_SESSION['user']` dan `PHPSESSID`.
   - Pengguna diarahkan ke `dashboard.php` dan nama pengguna yang sedang aktif ditampilkan di layar.
   - Teruji pada unit test `testLoginSessionAvailability`.

7. **Proteksi Akses Dashboard (Route Guard / Middleware)**
   - Halaman `dashboard.php` dilindungi otorisasi (`$auth->requireAuth('index.php')`).
   - Pengguna tanpa sesi login aktif atau cookie Remember Me valid otomatis dialihkan (_redirect HTTP 302_) kembali ke halaman login `index.php`.
   - Teruji pada unit test `testProtectedDashboardRedirectsToLogin`.

8. **Pembersihan Session & Cookie Saat Logout**
   - Skrip `logout.php` memusnahkan sesi login secara menyeluruh (`session_destroy()`, pembersihan `$_SESSION`, penghapusan cookie `PHPSESSID`).
   - Token pada `data/users.json` dan cookie `spacex_remember` dihapus dan di-set kedaluwarsa.
   - Sesi lama tidak dapat digunakan kembali untuk mengakses dashboard.
   - Teruji pada unit test `testLogoutDestroysSessionAndCookies`.

9. **Umpan Balik Notifikasi Toast Interaktif**
   - Menggunakan sistem flash notification berbasis sesi yang ditampilkan lewat **Toastify.js**.
   - Memberikan feedback visual berwarna dan ikonik saat login berhasil ("Welcome Back!"), kesalahan autentikasi ("Authentication Failed"), registrasi sukses, maupun saat pembaruan profil.
   - Teruji pada unit test `testToastBehaviorOnAuthentication`.

10. **Fitur "Remember Me" Berbasis Token Cookie Aman**
    - Opsi "Remember Me" pada form login menghasilkan token acak aman (`bin2hex(random_bytes(32))`) yang disimpan di database JSON dan dikirim ke browser via cookie `spacex_remember` (HTTPOnly, Secure, SameSite Lax).
    - Jika sesi browser tertutup atau kedaluwarsa, pengguna tetap dapat login otomatis melalui validasi cookie token tersebut.
    - Teruji pada unit test `testRememberMeBehavior`.

11. **Pembaruan Profil Pengguna (Edit Profile)**
    - Modal / form edit profil di `dashboard.php` memungkinkan pengguna memperbarui **Nama Lengkap**, **Email**, serta **Password**.
    - Jika kolom password dikosongkan, hash password lama tetap dipertahankan dan tidak ditimpa. Jika diisi, sistem membuat hash password baru.
    - Teruji pada unit test `testEditProfileSuccessAndOptionalPassword`.

---

## Fitur Unggulan Tambahan

- 🚀 **Dashboard SpaceX Analytics Interaktif:**
  Visualisasi metrik penerbangan antariksa komprehensif, mencakup jumlah peluncuran Falcon 9 & Heavy, tren tahunan, status reuse booster dan kapsul Dragon, statistik landing site, hingga misi ke Bulan & Mars.
- 📊 **Visualisasi Chart.js Terintegrasi:**
  Grafik interaktif peluncuran per tahun dan perbandingan situs peluncuran dengan palet warna dark/light mode yang dinamis.
- 🎨 **Arsitektur Komponen UI PHP Modular:**
  Komponen antarmuka yang bersih dan dapat digunakan ulang (`Input.php`, `Button.php`, `ThemeSwitch.php`, `AuthImage.php`).
- 🌗 **Dark Mode & Light Mode:**
  Dukungan tema gelap dan terang terintegrasi dengan Tailwind CSS v4 dan Preline UI, tersimpan otomatis di `localStorage`.
- 🖼️ **Integrasi Avatar Gravatar Otomatis:**
  Foto profil dinamis menggunakan hash MD5 email pengguna dari Gravatar API.

---

## Teknologi yang Digunakan

### Backend & Core
- **PHP 8.2+ / 8.3 / 8.5** — Native Plain PHP dengan PSR-4 Autoloading dan tipe data ketat (`declare(strict_types=1)`).
- **Composer** — Manajemen dependensi PHP dan testing framework.
- **vlucas/phpdotenv** — Manajemen konfigurasi environment `.env`.
- **spatie/fork** — Eksekusi proses concurrent dev server PHP dan Vite secara bersamaan.

### Frontend
- **Tailwind CSS v4** — Modern utility-first styling engine dengan `@tailwindcss/vite`.
- **Vite 8** — Next-generation frontend tooling dengan Fast HMR dan automated production asset pipeline.
- **Preline UI & Iconify** — Komponen UI interaktif dan ikon vektor responsif.
- **Chart.js** — Engine grafik visualisasi data peluncuran.
- **Toastify.js** — Notifikasi toast mengambang non-intrusif.

### Quality Assurance & DevOps
- **PHPUnit 11** — Pengujian unit dan integrasi otomatis (45 tests, 226 assertions).
- **GitHub Actions** — CI/CD pipeline otomatis untuk menjalankan tes PHPUnit, build Vite, dan deploy ke FTP server via `git-ftp`.

---

## Automated Testing (PHPUnit)

Proyek ini dilengkapi rangkaian pengujian unit dan integrasi berbasis PHPUnit yang memverifikasi seluruh fungsionalitas aplikasi.

### Menjalankan Pengujian

Jalankan rangkaian tes melalui Composer:

```bash
composer test
# atau langsung melalui binary PHPUnit:
./vendor/bin/phpunit --colors=always
```

### Ringkasan Hasil Pengujian

```text
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.x
Configuration: /path/to/phpunit.xml

.............................................                     45 / 45 (100%)

Time: 00:06.638, Memory: 8.00 MB

OK (45 tests, 226 assertions)
```

Suite pengujian mencakup:
- [`tests/RequirementTest.php`](tests/RequirementTest.php): Seluruh 11 butir evaluasi tugas autentikasi & profil.
- [`tests/AuthServiceTest.php`](tests/AuthServiceTest.php): Unit test logika autentikasi, hashing, cookie, dan verifikasi session.
- [`tests/SpaceXServiceTest.php`](tests/SpaceXServiceTest.php): Unit test data fetching dan aggregation data SpaceX.
- [`tests/ViteTest.php`](tests/ViteTest.php): Unit test tag generator dan asset manifest resolver.

---

## Menjalankan Proyek Secara Lokal

### 1. Prasyarat Sistem

- **PHP** 8.2 atau lebih baru (ekstensi `curl`, `mbstring`, `json` aktif)
- **Composer** 2.x
- **Node.js** 20 atau lebih baru
- **pnpm** (atau npm)

### 2. Instalasi Dependensi

Clone repository dan pasang dependensi:

```bash
git clone https://github.com/Realitaa/TugasWeb-Pertemuan7-LoginRegister.git
cd TugasWeb-Pertemuan7-LoginRegister
pnpm install
composer install
```

### 3. Konfigurasi Environment

Salin berkas konfigurasi template:

```bash
cp .env.example .env
```

### 4. Menjalankan Server Pengembangan (Dev Mode)

Jalankan perintah tunggal yang menjalankan dev server Vite dan web server PHP secara concurrent:

```bash
composer dev
# atau: composer run dev
```

Buka browser Anda pada tautan: **`http://localhost:8000`**

### 5. Membangun untuk Produksi (Production Build)

Kompilasi aset statis frontend dan jalankan server produksi:

```bash
# Build bundle produksi Vite
pnpm run build

# Jalankan server PHP
php -S localhost:8000
```

---

## Struktur Direktori Proyek

```text
├── .github/workflows/    # CI/CD GitHub Actions (Test & Git-FTP Deploy)
├── app/                  # Logika backend PHP (Auth, SpaceX, Vite Adapter)
│   ├── Auth/             # AuthService & manajemen sesi / users.json
│   ├── SpaceX/           # SpaceXService API & cache agregasi data
│   └── Vite/             # Vite helper & manifest resolver
├── bin/                  # Skrip dev server concurrent (dev.php)
├── data/                 # Berkas penyimpanan flat-file (users.json)
├── dist/                 # Hasil build produksi Vite (manifest & assets)
├── public/               # File statis publik
├── src/                  # Kode sumber frontend (Tailwind CSS, JS, UI components)
│   ├── components/       # Komponen UI PHP reusable
│   ├── js/               # Skrip JS (dashboard charts, toast init)
│   └── style.css         # Styling Tailwind CSS v4
├── tests/                # PHPUnit automated tests
├── dashboard.php         # Halaman dashboard terproteksi
├── index.php             # Halaman login & landing autentikasi
├── logout.php            # Handler pembersihan sesi & cookie
├── register.php          # Halaman formulir pendaftaran akun
├── composer.json         # Konfigurasi dependensi PHP & scripts
├── package.json          # Konfigurasi dependensi frontend Vite
└── vite.config.js        # Konfigurasi Vite bundler
```

---

## Lisensi

Proyek ini dirilis di bawah lisensi [MIT License](LICENSE).
