# Dokumentasi & Alur Kerja Program MindCare (SPK - SAW)

Dokumen ini menjelaskan alur kerja, logika perhitungan matematis, struktur database, dan implementasi persentase pada website **MindCare** (Sistem Pendukung Keputusan / SPK Skrining Kesehatan Mental Mahasiswa) berbasis metode **Simple Additive Weighting (SAW)**.

---

## 1. Konsep & Tujuan Program

Aplikasi **MindCare** dirancang untuk membantu perguruan tinggi melakukan skrining dini kesehatan mental mahasiswa secara terstruktur, objektif, dan transparan.
* **Metode SPK:** Simple Additive Weighting (SAW) — metode penjumlahan terbobot.
* **Output:** Skor akhir berupa persentase ($0\% - 100\%$), kategori keputusan (ambang batas $60\%$), dan rekomendasi tindak lanjut.
* **Prinsip Persentase:** Setiap data yang diolah (bobot, capaian kuesioner, normalisasi, kontribusi kriteria, hingga nilai akhir) disajikan dalam bentuk **persentase** agar mudah dipahami baik oleh mahasiswa maupun pihak konseling kampus.

---

## 2. Struktur Database (`db_mindcare.sql`)

Aplikasi menggunakan 9 tabel yang saling berelasi secara terstruktur:

```
[users] ──────── (1:1) ───────> [mahasiswa]
                                     │
                                     ▼ (1:N)
                                [pengisian]
                                     │
         ┌───────────────────────────┴───────────────────────────┐
         ▼ (1:N)                                                 ▼ (1:1)
     [jawaban] <── (N:1) ── [pertanyaan]                    [hasil_spk]
                                  │                              │
                                  │ (N:1)             ┌──────────┴──────────┐
                                  ▼                   ▼ (1:N)               ▼ (1:1)
                             [kriteria] <──── [detail_hasil_spk]      [rekomendasi]
```

### Rincian Tabel:
1. **`users`**: Akun login (username, password bcrypt, role: `admin` atau `mahasiswa`).
2. **`mahasiswa`**: Profil biodata mahasiswa (NIM, Nama, Prodi, Angkatan, Jenis Kelamin, Email).
3. **`kriteria`**: Daftar kriteria penilaian SPK, bobot desimal, jenis benefit/cost.
4. **`pertanyaan`**: Butir kuesioner skala Likert (1–5) yang terikat ke tiap kriteria.
5. **`pengisian`**: Sesi pengisian kuesioner oleh mahasiswa (tanggal dan status selesai).
6. **`jawaban`**: Nilai jawaban (1–5) mahasiswa untuk masing-masing pertanyaan.
7. **`hasil_spk`**: Skor akhir SPK total (desimal & persen), kategori status, dan tanggal kalkulasi.
8. **`detail_hasil_spk`**: Rincian audit matematis SAW (nilai rata-rata kriteria, nilai normalisasi, bobot, kontribusi).
9. **`rekomendasi`**: Teks panduan atau rekomendasi otomatis berdasarkan kategori yang didapat.

---

## 3. Kriteria & Bobot SPK

Sistem menggunakan 5 kriteria berjenis **benefit** (semakin sering mengalami gejala, semakin tinggi skor tekanannya):

| Kode | Nama Kriteria | Bobot Desimal ($W_j$) | Bobot Persentase (%) | Deskripsi Indikator |
| :---: | :--- | :---: | :---: | :--- |
| **C1** | Stres akademik | 0.2500 | **25%** | Tekanan tugas kuliah, beban akademik, dan kekhawatiran nilai |
| **C2** | Kesulitan tidur | 0.2000 | **20%** | Insomnia, sering terbangun, waktu tidur kurang |
| **C3** | Kesulitan emosional | 0.2500 | **25%** | Emosi tidak stabil, cemas berlebih, suasana hati terganggu |
| **C4** | Keterbatasan dukungan sosial | 0.1500 | **15%** | Merasa kesepian, terisolasi, minim tempat bercerita |
| **C5** | Ketidakseimbangan aktivitas | 0.1500 | **15%** | Kesulitan mengatur waktu antara kuliah, istirahat, dan hobi |
| **TOTAL** | | **1.0000** | **100%** | **Tervalidasi ideal** |

Setiap kriteria memiliki **3 pertanyaan** dengan pilihan jawaban skala Likert 1–5:
- `1` = Sangat Tidak Setuju
- `2` = Tidak Setuju
- `3` = Netral / Ragu-ragu
- `4` = Setuju
- `5` = Sangat Setuju

---

## 4. Alur Perhitungan Matematis Metode SAW (Step-by-Step)

Berikut adalah tahapan kalkulasi sistem dari awal kuesioner diisi hingga menghasilkan persentase akhir:

### Tahap 1: Menghitung Rata-rata Nilai Kriteria ($X_{ij}$)
Mahasiswa menjawab 3 pertanyaan untuk kriteria $C_j$. Nilai rata-rata kriteria dihitung:
$$X_{ij} = \frac{\sum \text{Nilai Jawaban Kriteria } j}{\text{Jumlah Pertanyaan Kriteria } j}$$

*Contoh Kasus:*
Untuk kriteria **C1 (Stres akademik)** dengan jawaban: Pertanyaan 1 = 4, Pertanyaan 2 = 3, Pertanyaan 3 = 5:
$$X_{i1} = \frac{4 + 3 + 5}{3} = 4.0000$$

### Tahap 2: Menghitung Tingkat Tekanan Kriteria (%)
Menunjukkan seberapa tinggi tekanan pada aspek kriteria tersebut dalam persen (skala maksimal 5):
$$\text{Tingkat Tekanan } (\%) = \left(\frac{X_{ij}}{5}\right) \times 100\% = \left(\frac{4.0}{5}\right) \times 100\% = \mathbf{80.00\%}$$

### Tahap 3: Normalisasi Matriks ($R_{ij}$)
Karena seluruh kriteria adalah kriteria **benefit** dengan skala maksimum $X_{\max} = 5.0$, rumus normalisasinya:
$$R_{ij} = \frac{X_{ij}}{X_{\max}} = \frac{X_{ij}}{5.0}$$
*Hasil untuk C1:*
$$R_{i1} = \frac{4.0000}{5.0} = \mathbf{0.800000}$$

### Tahap 4: Menghitung Kontribusi Kriteria ke Skor Akhir ($V_{ij}$)
Kontribusi dihitung dengan mengalikan bobot kriteria ($W_j$) dengan nilai normalisasinya ($R_{ij}$):
$$V_{ij} = W_j \times R_{ij}$$
$$\text{Kontribusi } (\%) = V_{ij} \times 100\%$$

*Hasil untuk C1 (Bobot 25% / 0.25):*
$$V_{i1} = 0.25 \times 0.800000 = \mathbf{0.200000} \quad (\mathbf{20.00\%})$$

### Tahap 5: Menghitung Nilai Akhir SPK Total ($V_i$)
Nilai akhir adalah akumulasi penjumlahan dari seluruh kontribusi kriteria:
$$V_i = \sum_{j=1}^{5} (W_j \times R_{ij})$$
$$\text{Nilai Akhir } (\%) = V_i \times 100\%$$

*Contoh Simulasi Lengkap 5 Kriteria:*
| Kriteria | Bobot ($W_j$) | Nilai Rata-rata ($X$) | Tingkat Tekanan (%) | Normalisasi ($R$) | Kontribusi ($W \times R$) | Kontribusi (%) | Porsi ke Skor Akhir (%) |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **C1** | 25% (0.25) | 4.00 / 5 | 80.00% | 0.8000 | 0.2000 | 20.00% | 29.85% |
| **C2** | 20% (0.20) | 3.67 / 5 | 73.33% | 0.7333 | 0.1467 | 14.67% | 21.90% |
| **C3** | 25% (0.25) | 3.33 / 5 | 66.67% | 0.6667 | 0.1667 | 16.67% | 24.88% |
| **C4** | 15% (0.15) | 2.67 / 5 | 53.33% | 0.5333 | 0.0800 | 8.00% | 11.94% |
| **C5** | 15% (0.15) | 2.50 / 5 | 50.00% | 0.5000 | 0.0750 | 7.50% | 11.19% |
| **TOTAL** | **100% (1.00)** | — | — | — | **0.6684** | **66.84%** | **100.00%** |

### Tahap 6: Pengambilan Keputusan (Ambang Batas 60%)
Sistem mengevaluasi skor akhir terhadap ambang batas **60.00% (0.60)**:
1. **$\ge 60.00\%$ (`>= 0.60`) $\rightarrow$ "Prioritas dukungan"**:
   - Menunjukkan mahasiswa mengalami tingkat tekanan yang cukup tinggi dan disarankan segera berkonsultasi dengan konselor/dosen PA kampus.
2. **$< 60.00\%$ (`< 0.60`) $\rightarrow$ "Perlu perhatian"**:
   - Menunjukkan kondisi umum masih terkelola, disarankan menjaga rutinitas istirahat dan pola belajar yang seimbang.

---

## 5. Alur Pengguna (User Workflow)

### A. Alur Mahasiswa (`Role: Mahasiswa`)
1. **Login Mahasiswa:** Masuk menggunakan username `mhs001` (password: `password`).
2. **Dashboard Mahasiswa:**
   - Melihat skor skrining terakhir melalui **SPK Meter Gauge** (visual indikator ambang batas 60%).
   - Melihat tabel kontribusi kriteria terakhir dan riwayat pengisian.
3. **Menu "Isi Kuesioner":**
   - Menjawab 15 butir pertanyaan Likert (1–5) dengan visual live progress bar.
   - Klik **Simpan Jawaban & Hitung Skor SAW**.
4. **Halaman Hasil Skrining (`hasil.php`):**
   - Menampilkan Meter Gauge skor SPK secara interaktif.
   - Kartu analisis persentase per kriteria ($C_1 - C_5$).
   - Tabel Matriks SAW lengkap dengan tahapan matematis.
   - Kotak rekomendasi tindakan.
5. **Menu "Riwayat":**
   - Memantau tren perkembangan skor SPK mahasiswa dari setiap pengisian kuesioner.

### B. Alur Administrator (`Role: Admin`)
1. **Login Admin:** Masuk menggunakan username `admin` (password: `password`).
2. **Dashboard Admin:**
   - Statistik umum mahasiswa dan rata-rata skor SPK se-kampus.
   - Bar persentase distribusi kategori (*Prioritas Dukungan* vs *Perlu Perhatian*).
   - **Rata-rata Tingkat Tekanan per Kriteria (%)** seluruh mahasiswa se-kampus (mengetahui faktor mana yang paling kritis).
3. **Menu "Data Mahasiswa":**
   - Kelola data dan akun mahasiswa.
   - Melihat kolom **Skor Terakhir (%)** dan **Status SPK** tiap mahasiswa.
4. **Menu "Data Kriteria & Pertanyaan":**
   - Mengatur nama kriteria, bobot persen (memastikan total tetap 100%), dan butir pertanyaan kuesioner.
5. **Menu "Hasil SPK":**
   - Tabel seluruh rekap pengisian mahasiswa dengan rincian kontribusi persentase tiap kriteria ($C_1 - C_5$) dan mini-bar skor akhir.
   - Klik tombol **Detail SAW** untuk audit rincian matriks normalisasi per individu.
6. **Menu "Laporan":**
   - Rekap persentase tingkat tekanan per kriteria.
   - Rekap persentase mahasiswa berisiko berdasarkan Program Studi.
   - Fitur **Export CSV** (data lengkap berformat persentase) dan tombol **Cetak Laporan**.

---

## 6. Panduan Menjalankan Program di Laragon

1. Buka aplikasi **Laragon** di komputer Anda.
2. Klik tombol **Start All** untuk mengaktifkan **Apache** (port 80) dan **MySQL** (port 3306).
3. Buka **HeidiSQL** atau **phpMyAdmin** (`http://localhost/phpmyadmin`):
   - Buat database baru bernama: `db_mindcare`.
   - Import file: `c:\laragon\www\SI2\db_mindcare.sql`.
4. Buka browser dan ketik alamat:
   ```
   http://localhost/SI2/
   ```
5. **Akun Pengujian:**
   - **Admin:** Username: `admin` | Password: `password`
   - **Mahasiswa:** Username: `mhs001` s/d `mhs100` | Password: `password`

---

## 7. Struktur Direktori Proyek

```
SI2/
├── admin/                     # Modul panel Administrator
│   ├── dashboard.php          # Statistik kampus & rata-rata tekanan per kriteria
│   ├── detail.php             # Rincian audit matriks SAW & meter gauge mahasiswa
│   ├── hasil.php              # Rekap seluruh data hasil SPK berkolom persentase
│   ├── kriteria.php           # Manajemen kriteria & bobot persen (100%)
│   ├── laporan.php            # Laporan rekapitulasi, prodi, print, dan export CSV
│   ├── mahasiswa.php          # Manajemen data mahasiswa & status skor terakhir
│   └── pertanyaan.php         # Manajemen butir kuesioner skala Likert
├── app/
│   └── saw.php                # Mesin kalkulasi SAW (normalisasi, kontribusi, kategori)
├── assets/
│   ├── css/
│   │   └── app.css            # Styling visual: SPK meter gauge, cards, progress bars
│   └── js/
│       └── app.js             # Skrip interaktif (progress kuesioner, konfirmasi aksi)
├── config/
│   └── database.php           # Koneksi PDO MySQL Laragon
├── includes/
│   ├── auth.php               # Otentikasi login & session
│   ├── footer.php             # Komponen footer template
│   ├── functions.php          # Helper format persentase pct(), badge, rekomendasi
│   └── header.php             # Komponen topbar & sidebar navigasi dinamis
├── mahasiswa/                 # Modul panel Mahasiswa
│   ├── dashboard.php          # Ringkasan skor terakhir & visual kontribusi kriteria
│   ├── hasil.php              # Halaman visualisasi meter SPK & matriks lengkap
│   ├── kuesioner.php          # Formulir pengisian skrining mental (15 soal)
│   ├── profil.php             # Biodata mahasiswa & ganti password
│   └── riwayat.php            # Catatan riwayat seluruh pengisian & tren skor %
├── db_mindcare.sql            # File skema database & 100 data seeder
├── index.php                  # Halaman portal login
├── logout.php                 # Handler logout sistem
└── ALUR_KERJA_PROGRAM.md      # Dokumentasi alur kerja sistem ini
```

