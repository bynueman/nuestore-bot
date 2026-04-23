<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;

class HelpHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "ℹ️ *Bantuan Nuestore Admin Bot*\n\n" .
                  "📋 *Perintah Tersedia:*\n" .
                  "/start — Mulai bot & tampilkan menu\n" .
                  "/services — Lihat semua layanan tersedia\n" .
                  "/order — Buat pesanan baru untuk pelanggan\n" .
                  "/status [Order ID] — Cek status pesanan\n" .
                  "/balance — Cek saldo Lollipop\n" .
                  "/report — Laporan rekap omzet & profit\n" .
                  "/help — Tampilkan bantuan ini\n\n" .
                  "📌 *Menu Keyboard:*\n" .
                  "🛒 Order → Input order baru\n" .
                  "📋 Cek Status → Cek progress pesanan\n" .
                  "💰 Saldo → Cek saldo Lollipop\n" .
                  "📊 Laporan → Rekap order & profit\n\n" .
                  "⚡ *Cara Order:*\n" .
                  "1. Tap 🛒 Order atau ketik /order\n" .
                  "2. Pilih platform → kategori → layanan\n" .
                  "3. Lihat detail layanan & konfirmasi\n" .
                  "4. Masukkan link target & quantity\n" .
                  "5. Konfirmasi → order otomatis terkirim ke Lollipop!\n\n" .
                  "📞 *Butuh bantuan?* Hubungi developer.",
            parse_mode: 'Markdown'
        );
    }
}