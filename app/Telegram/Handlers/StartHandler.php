<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreUser;
use SergiX44\Nutgram\Nutgram;

class StartHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $username   = $bot->user()->username ?? null;

        NuestoreUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['username'    => $username]
        );

        $bot->sendMessage(
            text: "👋 Selamat datang di *Nuestore SMM Bot*!\n\n" .
                  "Kami menyediakan layanan peningkatan sosial media terpercaya.\n\n" .
                  "📋 *Menu Tersedia:*\n" .
                  "/services — Lihat daftar layanan\n" .
                  "/order — Buat pesanan baru\n" .
                  "/status — Cek status pesanan\n" .
                  "/help — Bantuan\n\n" .
                  "Ketik /order untuk mulai pesan sekarang! 🚀",
            parse_mode: 'Markdown'
        );
    }
}