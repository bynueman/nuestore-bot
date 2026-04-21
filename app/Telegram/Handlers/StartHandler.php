<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreUser;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class StartHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $telegramId = $bot->userId();
        $username   = $bot->user()->username ?? null;
        $firstName  = $bot->user()->first_name ?? 'Kamu';

        NuestoreUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['username'    => $username]
        );

        $bot->sendMessage(
            text: "👋 Halo, *{$firstName}!*\n\n" .
                  "Selamat datang di *Nuestore* — layanan peningkatan sosial media terpercaya.\n\n" .
                  "🚀 Proses otomatis, bayar QRIS, selesai dalam menit.\n\n" .
                  "Pilih menu di bawah untuk mulai:",
            parse_mode: 'Markdown',
            reply_markup: ReplyKeyboardMarkup::make(resize_keyboard: true)
                ->addRow(
                    KeyboardButton::make('🛒 Order'),
                    KeyboardButton::make('📋 Cek Status'),
                )
        );
    }
}