<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;

class HelpHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "ℹ️ *Bantuan Nuestore SMM Bot*\n\n" .
                  "📋 *Perintah Tersedia:*\n" .
                  "/start — Mulai bot\n" .
                  "/services — Lihat semua layanan\n" .
                  "/order — Buat pesanan baru\n" .
                  "/status [Order ID] — Cek status pesanan\n" .
                  "/help — Tampilkan bantuan ini\n\n" .
                  "💬 *Cara Order:*\n" .
                  "1. Ketik /services untuk lihat layanan\n" .
                  "2. Catat ID layanan yang diinginkan\n" .
                  "3. Ketik /order dan ikuti instruksi\n" .
                  "4. Bayar via QRIS yang dikirim bot\n" .
                  "5. Pesanan diproses otomatis!\n\n" .
                  "📞 *Butuh bantuan?* Hubungi admin.",
            parse_mode: 'Markdown'
        );
    }
}