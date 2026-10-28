# Matcha House POS

Aplikasi Point of Sale (POS) berbasis web yang dirancang untuk membantu proses pemesanan, pembayaran, dan pengelolaan transaksi pada Matcha House.

## Fitur

* Dashboard pemesanan
* Pengelolaan menu Matcha, Non Matcha, dan Pastry
* Pilihan grade dan origin matcha
* Pilihan varian dan topping
* Keranjang pesanan
* Checkout
* Metode pembayaran Cash dan QRIS
* Perhitungan uang diterima dan kembalian
* Pengaturan tipe pesanan Dine In dan Take Away
* Informasi nomor meja dan nomor pager
* Riwayat transaksi
* Laporan penjualan
* Sidebar navigasi responsif

## Teknologi

* PHP
* Laravel
* MySQL
* Blade
* Tailwind CSS
* JavaScript
* Vite
* Git & GitHub

## Cara Menjalankan

### Prasyarat

Pastikan perangkat telah memiliki:

* PHP
* Composer
* Node.js & npm
* MySQL
* Git

### 1. Clone repository

```bash
git clone https://github.com/EvaristusEP/pos-l8.git
cd pos-l8
```

### 2. Install dependency PHP

```bash
composer install
```

### 3. Install dependency frontend

```bash
npm install
```

### 4. Konfigurasi environment

Salin file `.env.example` menjadi `.env`.

```bash
cp .env.example .env
```

Kemudian generate application key:

```bash
php artisan key:generate
```

### 5. Konfigurasi database

Buat database MySQL dengan nama:

```text
matcha_pos
```

Kemudian sesuaikan konfigurasi database pada file `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=matcha_pos
DB_USERNAME=root
DB_PASSWORD=
```

### 6. Jalankan migration dan seeder

```bash
php artisan migrate
php artisan db:seed
```

### 7. Build asset frontend

```bash
npm run build
```

### 8. Jalankan aplikasi

```bash
php artisan serve
```

Aplikasi dapat diakses melalui:

```text
http://127.0.0.1:8000
```

## Struktur Project

```text
app/
├── Http/
├── Models/
└── Providers/

database/
├── migrations/
└── seeders/

resources/
├── css/
├── js/
└── views/

routes/
└── web.php
```

## Database

Project menggunakan MySQL sebagai database utama.

Struktur database dikelola menggunakan Laravel Migration, sedangkan data awal aplikasi disediakan melalui Database Seeder.

## Demo

Live demo akan tersedia setelah proses deployment selesai.

## Catatan

Project ini dibuat sebagai aplikasi POS berbasis web untuk mensimulasikan proses pemesanan, pembayaran, dan pengelolaan transaksi pada bisnis minuman dan makanan.
