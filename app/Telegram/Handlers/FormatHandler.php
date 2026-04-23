<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class FormatHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('📸 Instagram', callback_data: 'fmt_plat:instagram'),
                InlineKeyboardButton::make('🎵 TikTok', callback_data: 'fmt_plat:tiktok')
            );

        $bot->sendMessage("📝 *Pilih Platform untuk Format Order:*", parse_mode: 'Markdown', reply_markup: $keyboard);
    }

    public static function handleCallback(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';

        if (str_starts_with($data, 'fmt_plat:')) {
            $platform = str_replace('fmt_plat:', '', $data);
            
            $categories = $platform === 'instagram'
                ? ['followers_id' => '🇮🇩 Followers ID', 'followers_ww' => '🌍 Followers WW', 'likes_id' => '🇮🇩 Likes ID', 'likes_ww' => '🌍 Likes WW', 'views' => '▶️ Views', 'story' => '📖 Story']
                : ['followers_id' => '🇮🇩 Followers ID', 'followers_ww' => '🌍 Followers WW', 'likes_id' => '🇮🇩 Likes ID', 'likes_ww' => '🌍 Likes WW', 'views' => '▶️ Views', 'saves' => '🔖 Saves', 'shares' => '🔁 Shares'];

            $keyboard = InlineKeyboardMarkup::make();
            $row = [];
            foreach ($categories as $catKey => $catLabel) {
                // Remove emoji from label to pass clean text, or we can just replace emoji later.
                // It's safer to pass the clean label in the callback data.
                $cleanLabel = trim(preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{25B6}\x{FE0F}]/u', '', $catLabel));
                
                $row[] = InlineKeyboardButton::make($catLabel, callback_data: "fmt_cat:{$platform}|{$cleanLabel}");
                if (count($row) === 2) {
                    $keyboard->addRow(...$row);
                    $row = [];
                }
            }
            if (!empty($row)) {
                $keyboard->addRow(...$row);
            }

            $bot->editMessageText(
                text: "📝 Pilih Layanan untuk platform *" . ucfirst($platform) . "*:",
                parse_mode: 'Markdown',
                reply_markup: $keyboard,
                chat_id: $bot->chatId(),
                message_id: $bot->messageId()
            );
        }

        if (str_starts_with($data, 'fmt_cat:')) {
            $parts = explode('|', str_replace('fmt_cat:', '', $data));
            $platform = ucfirst($parts[0] ?? '');
            $kategori = $parts[1] ?? '';
            
            $linkContoh = strtolower($platform) === 'instagram' ? 'https://instagram.com/username' : 'https://tiktok.com/@username';

            $text = "📋 FORMAT ORDER\n"
                  . "Platform: {$platform}\n"
                  . "Layanan: {$kategori}\n"
                  . "Target: {$linkContoh}\n"
                  . "Jumlah: 1000\n"
                  . "Catatan: -";

            $bot->answerCallbackQuery();
            
            // Hapus menu inline yang diklik biar clean
            $bot->deleteMessage($bot->chatId(), $bot->messageId());

            $bot->sendMessage("👇 *Copy text di bawah ini dan bagikan ke pelanggan:*", parse_mode: 'Markdown');
            $bot->sendMessage("`{$text}`\n\n_(Ketuk untuk langsung menyalin)_", parse_mode: 'Markdown');
        }
    }
}
