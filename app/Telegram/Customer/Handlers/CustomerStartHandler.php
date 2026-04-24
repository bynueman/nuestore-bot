<?php

namespace App\Telegram\Customer\Handlers;

use App\Models\NuestoreCustomer;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;

class CustomerStartHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $user     = $bot->user();
        $customer = NuestoreCustomer::fromTelegramUser($user);

        if ($customer->is_blacklisted) {
            $bot->sendMessage("⛔ Maaf, akunmu diblokir dari layanan kami.");
            return;
        }

        $name = $user->first_name ?? 'Kak';

        $bot->sendMessage(
            text: "👋 Halo, *{$name}!* Selamat datang di *Nuestore* 🎉\n\n"
                . "Kami menyediakan layanan pertumbuhan sosial media terpercaya:\n"
                . "• Instagram 🇮🇩 & Worldwide\n"
                . "• TikTok 🇮🇩 & Worldwide\n"
                . "• YouTube & platform lainnya\n\n"
                . "Gunakan menu di bawah untuk mulai:",
            parse_mode: 'Markdown',
            reply_markup: ReplyKeyboardMarkup::make(resize_keyboard: true)
                ->addRow(
                    KeyboardButton::make('🛒 Pesan Sekarang'),
                    KeyboardButton::make('📋 Status Pesanan'),
                )
                ->addRow(
                    KeyboardButton::make('❓ Bantuan'),
                )
        );
    }
}
