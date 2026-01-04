# Adira Report

A Laravel-based reporting application used to manage daily and monthly vehicle reports for Adira.

## Ringkasan
- Framework: Laravel
- Bahasa: PHP
- Tujuan: Mengumpulkan, mengolah, dan menampilkan laporan harian dan bulanan penjualan kendaraan.

## Prasyarat
- PHP >= 8.x
- Composer
- MySQL atau database lain yang didukung Laravel
- Node.js & npm (untuk asset build)

## Instalasi (lokal)
1. Clone repository:

```powershell
git clone <repo-url>
cd <repo-directory>
```

2. Install dependency PHP dan Node:

```powershell
composer install
npm install
```

3. Salin file environment dan konfigurasi:

```powershell
cp .env.example .env
php artisan key:generate
```

4. Sesuaikan konfigurasi database di `.env` dan jalankan migrasi:

```powershell
php artisan migrate
php artisan db:seed
```

5. Build assets (development):

```powershell
npm run dev
```

6. Jalankan server lokal:

```powershell
php artisan serve
```

## Menjalankan Test
- Project menggunakan Pest/PHPUnit. Jalankan:

```powershell
./vendor/bin/pest
```

atau

```powershell
vendor\bin\phpunit
```

## Struktur Penting
- `app/Livewire/DailyReportForm.php` — komponen Livewire untuk formulir laporan harian
- `app/Models/DailyReport.php` — model laporan harian
- `app/Services/ReportService.php` — logika pembuatan laporan dan agregasi
- `database/migrations/` — migrasi tabel laporan dan target
- `composer.json` — dependency PHP
- `package.json` — dependency frontend

## Skema Database (Ringkasan)
- `car_reports`: `id`, `role_id` (FK -> `roles.id`), `date`, `units`, `amount`, `submitted_by` (FK -> `users.id`). Unique: (`role_id`, `date`, `submitted_by`).
- `motor_reports`: sama struktur dengan `car_reports`.
- `monthly_car_targets`: `id`, `user_id` (FK -> `users.id`), `role_id` (FK -> `roles.id`), `year`, `month`, `target_units`, `target_amount`. Unique: (`user_id`, `year`, `month`).
- `monthly_motor_targets`: sama struktur dengan `monthly_car_targets`.
- Referensi: `users`, `roles`.

Lihat `docs/schema.sql` untuk DDL lengkap dan `docs/er-diagram.puml` untuk diagram ER.

## LOGIN_INFO
- File `LOGIN_INFO.txt` berisi contoh akun login dari seeder (data dummy). Jangan gunakan untuk produksi.

## Kontribusi
- Silakan fork repository, buat branch fitur, lalu ajukan pull request.

## Lisensi
- Tambahkan file `LICENSE` sesuai kebutuhan.
