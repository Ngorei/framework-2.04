# TDS (Table Database System)

## Deskripsi

TDS adalah sistem manajemen database berbasis command-line yang menyediakan perintah-perintah untuk mengelola tabel MySQL dengan mudah.

## Persyaratan Sistem

- MySQL Server 5.7 atau lebih baru
- PowerShell 5.1 atau lebih baru
- Akses ke database MySQL

## Perintah Dasar

### Manajemen Tabel

- tds tables # Menampilkan analisis dan daftar semua tabel
- tds "desc nama_tabel" # Menampilkan struktur tabel tertentu
- tds "create nama_tabel" # Membuat tabel baru dari definisi tabel.json
- tds "alter nama_tabel" # Memperbarui struktur tabel sesuai tabel.json
- tds "drop nama_tabel" # Menghapus tabel dari database

### Perintah SQL Views

- tds "create-view nama_view" # Membuat/update view dari views.json
- tds "show-view nama_view" # Menampilkan definisi view
- tds list-views # Menampilkan daftar semua view

### Manajemen Data

- tds "last nama_tabel" # Menampilkan data terakhir dari tabel
- tds "top nama_tabel 5" # Menampilkan 5 data teratas dari tabel
- tds "count nama_tabel" # Menghitung jumlah record dalam tabel
- tds "size nama_tabel" # Menampilkan ukuran tabel dalam MB

### Backup & Restore

- tds "export nama_tabel" # Export tabel ke file SQL
- tds export-all # Export semua tabel ke satu file SQL
- tds "import nama_tabel" # Import data dari file SQL
- tds "backup nama_tabel" # Backup tabel dengan timestamp

### Pencarian & Analisis

- tds "search tabel kata_kunci" # Mencari data di semua kolom
- tds "stats tabel kolom" # Menampilkan statistik kolom
- tds "validate tabel" # Validasi integritas data tabel
- tds "summary tabel" # Ringkasan komprehensif tabel

### Utilitas

- tds "duplicate tabel1 to tabel2" # Menduplikasi tabel
- tds "truncate nama_tabel" # Mengosongkan isi tabel
- tds env # Menampilkan konfigurasi .env
- tds dir # Menampilkan isi direktori
