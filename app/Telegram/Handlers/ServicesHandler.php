<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreSetting;
use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ServicesHandler
{
    private array $platformMap = [
        'instagram' => '📸 Instagram',
        'tiktok'    => '🎵 TikTok',
    ];

    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "📦 *Daftar Layanan Nuestore*\n\nPilih platform untuk melihat layanan:",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('📸 Instagram', callback_data: 'sv_platform:instagram'),
                    InlineKeyboardButton::make('🎵 TikTok',    callback_data: 'sv_platform:tiktok'),
                )
        );
    }

    public static function handleCallback(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;
        if (!$data) {
            $bot->answerCallbackQuery();
            return;
        }

        // Pilih platform
        if (str_starts_with($data, 'sv_platform:')) {
            $platform = str_replace('sv_platform:', '', $data);
            $bot->answerCallbackQuery();
            self::showCategories($bot, $platform);
            return;
        }

        // Pilih kategori
        if (str_starts_with($data, 'sv_cat:')) {
            $parts    = explode('|', str_replace('sv_cat:', '', $data));
            $platform = $parts[0];
            $cat      = $parts[1] ?? '';
            $bot->answerCallbackQuery();
            self::showServices($bot, $platform, $cat);
            return;
        }

        // Kembali ke platform
        if ($data === 'sv_back:platform') {
            $bot->answerCallbackQuery();
            $bot->editMessageText(
                text: "📦 *Daftar Layanan Nuestore*\n\nPilih platform untuk melihat layanan:",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('📸 Instagram', callback_data: 'sv_platform:instagram'),
                        InlineKeyboardButton::make('🎵 TikTok',    callback_data: 'sv_platform:tiktok'),
                    ),
                chat_id: $bot->callbackQuery()->message->chat->id,
                message_id: $bot->callbackQuery()->message->message_id,
            );
            return;
        }

        // Kembali ke kategori
        if (str_starts_with($data, 'sv_back:cat:')) {
            $platform = str_replace('sv_back:cat:', '', $data);
            $bot->answerCallbackQuery();
            self::showCategories($bot, $platform);
            return;
        }

        $bot->answerCallbackQuery();
    }

    private static function showCategories(Nutgram $bot, string $platform): void
    {
        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();

        if (!$services) {
            $bot->sendMessage(text: "❌ Gagal memuat layanan. Coba lagi nanti.");
            return;
        }

        $whitelist = array_filter(
            array_map('trim', explode(',', NuestoreSetting::get('whitelisted_service_ids', '')))
        );

        // Ambil kategori unik dari whitelist untuk platform ini
        $categories = collect($services)
            ->filter(function ($s) use ($whitelist, $platform) {
                if (!empty($whitelist) && !in_array((string)$s['service'], $whitelist)) return false;
                return str_contains(strtolower($s['category']), strtolower($platform)) ||
                       str_contains(strtolower($s['name']), strtolower($platform === 'tiktok' ? 'tiktok' : 'instagram'));
            })
            ->pluck('category')
            ->unique()
            ->values();

        if ($categories->isEmpty()) {
            $bot->sendMessage(text: "⚠️ Tidak ada layanan tersedia.");
            return;
        }

        $platformLabel = $platform === 'instagram' ? '📸 Instagram' : '🎵 TikTok';
        $keyboard      = InlineKeyboardMarkup::make();

        foreach ($categories as $cat) {
            // Singkat nama kategori untuk tombol
            $shortName = self::shortenCategory($cat);
            $keyboard->addRow(
                InlineKeyboardButton::make($shortName, callback_data: "sv_cat:{$platform}|{$cat}")
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('« Kembali', callback_data: 'sv_back:platform')
        );

        $chatId    = $bot->callbackQuery()?->message->chat->id ?? $bot->message()?->chat->id;
        $messageId = $bot->callbackQuery()?->message->message_id;

        if ($messageId) {
            $bot->editMessageText(
                text: "📦 *Layanan {$platformLabel}*\n\nPilih kategori:",
                parse_mode: 'Markdown',
                reply_markup: $keyboard,
                chat_id: $chatId,
                message_id: $messageId,
            );
        } else {
            $bot->sendMessage(
                text: "📦 *Layanan {$platformLabel}*\n\nPilih kategori:",
                parse_mode: 'Markdown',
                reply_markup: $keyboard,
            );
        }
    }

    private static function showServices(Nutgram $bot, string $platform, string $cat): void
    {
        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();
        $markup   = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);

        $whitelist = array_filter(
            array_map('trim', explode(',', NuestoreSetting::get('whitelisted_service_ids', '')))
        );

        $filtered = collect($services)->filter(function ($s) use ($whitelist, $cat) {
            if (!empty($whitelist) && !in_array((string)$s['service'], $whitelist)) return false;
            return $s['category'] === $cat;
        });

        if ($filtered->isEmpty()) {
            $bot->answerCallbackQuery(text: "Tidak ada layanan di kategori ini.");
            return;
        }

        $text = "📦 *" . self::shortenCategory($cat) . "*\n\n";

        foreach ($filtered as $s) {
            $harga = number_format((float)$s['rate'] * $markup, 0, ',', '.');
            $text .= "• ID: `{$s['service']}`\n";
            $text .= "  {$s['name']}\n";
            $text .= "  💰 Rp {$harga} / 1000\n";
            $text .= "  📊 Min: {$s['min']} | Max: {$s['max']}\n\n";
        }

        $text .= "Gunakan /order untuk memesan.";

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('« Kembali', callback_data: "sv_back:cat:{$platform}")
            );

        $bot->editMessageText(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
            chat_id: $bot->callbackQuery()->message->chat->id,
            message_id: $bot->callbackQuery()->message->message_id,
        );
    }

    private static function shortenCategory(string $cat): string
    {
        // Hapus emoji flag dan teks berlebih agar tombol tidak terlalu panjang
        $cat = preg_replace('/\s*🇮🇩|\s*♻️|\s*⚡|\s*🔥|\s*💧|\s*⛔/', '', $cat);
        return trim($cat);
    }
}