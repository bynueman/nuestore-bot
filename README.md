# 🛒 Nuestore Telegram Bot System

![Laravel](https://img.shields.io/badge/laravel-%23FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white)

Nuestore Bot adalah sistem manajemen pesanan SMM (Social Media Marketing) otomatis berbasis Telegram. Sistem ini menggunakan arsitektur **Dual-Bot** untuk memisahkan interaksi pelanggan dengan panel administrasi.

## 🚀 Fitur Utama

### 👤 Customer Bot
- **Alur Pemesanan Cerdas:** Pilihan platform, kategori layanan, dan jumlah otomatis.
- **Dynamic Pricing:** Kalkulasi harga otomatis secara real-time.
- **Sistem Pembayaran:** Generasi kode unik pembayaran dan upload bukti bayar (khusus format foto).
- **Anti-Spam:** Proteksi rate-limiting (2 pesan/detik) untuk menjaga stabilitas server.

### 🛠 Admin Bot
- **Dashboard Real-time:** Pantau statistik pesanan dan antrean langsung dari Telegram.
- **Manajemen Pesanan:** Setujui atau tolak pesanan manual dengan notifikasi otomatis ke pelanggan.
- **Otomatisasi SMM:** Integrasi API untuk fulfillment pesanan secara instan.
- **Laporan Transaksi:** Laporan omzet harian, mingguan, dan bulanan.
- **Keamanan:** Sistem Blacklist manual/otomatis dan proteksi Webhook Secret Token.

## 🏗 Arsitektur Sistem
- **Framework:** Laravel 11
- **Bot Engine:** Nutgram
- **Database:** MySQL / MariaDB

## ⚙️ Instalasi

1. **Clone Repository**
   ```bash
   git clone https://github.com/bynueman/nuestore-bot.git
   cd nuestore-bot
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Konfigurasi Environment**
   Salin `.env.example` ke `.env` dan lengkapi kredensial Bot Token, Admin ID, dan API Key SMM Anda.

4. **Migrasi Database**
   ```bash
   php artisan migrate
   ```

5. **Set Webhook**
   Gunakan script bantuan yang sudah disediakan:
   ```bash
   php set_webhook_safe.php
   ```

---
Developed with ❤️ by [Bynueman](https://github.com/bynueman)
