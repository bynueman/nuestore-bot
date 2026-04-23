<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class StartHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "👋 Halo, *Owner!*\n\n🤖 *Nuestore Admin Bot* siap digunakan.\n\nGunakan menu di bawah untuk input order pelanggan secara cepat tanpa perlu buka website.",
            parse_mode: 'Markdown',
            reply_markup: ReplyKeyboardMarkup::make(resize_keyboard: true)
                ->addRow(
                    KeyboardButton::make('🛒 Order'),
                    KeyboardButton::make('📋 Cek Status'),
                )
                ->addRow(
                    KeyboardButton::make('💰 Saldo'),
                    KeyboardButton::make('📊 Laporan'),
                )
        );
    }
}