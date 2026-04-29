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
        $categories = $platform === 'instagram'
            ? [
                'followers_id' => '🇮🇩 Followers ID',
                'followers_ww' => '🌍 Followers WW',
                'likes_id'     => '🇮🇩 Likes ID',
                'likes_ww'     => '🌍 Likes WW',
                'views'        => '▶️ Views',
                'story'        => '📖 Story'
            ]
            : [
                'followers_id' => '🇮🇩 Followers ID',
                'followers_ww' => '🌍 Followers WW',
                'likes_id'     => '🇮🇩 Likes ID',
                'likes_ww'     => '🌍 Likes WW',
                'views'        => '▶️ Views',
                'saves'        => '🔖 Saves',
                'shares'       => '🔁 Shares'
            ];

        $platformLabel = $platform === 'instagram' ? '📸 Instagram' : '🎵 TikTok';
        $keyboard      = InlineKeyboardMarkup::make();

        foreach ($categories as $catKey => $catLabel) {
            $keyboard->addRow(
                InlineKeyboardButton::make($catLabel, callback_data: "sv_cat:{$platform}|{$catKey}")
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

        if (!$services) {
            $bot->answerCallbackQuery(text: "❌ Gagal memuat layanan. Coba lagi nanti.");
            return;
        }

        $markup   = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);

        $filtered = collect($services)->filter(function ($s) use ($platform, $cat) {
            $name          = strtolower($s['name']);
            $platformLower = strtolower($platform);
            
            $catParts      = explode('_', strtolower($cat));
            $baseCategory  = $catParts[0];
            $region        = $catParts[1] ?? 'all';

            $platformMatch = str_contains($name, $platformLower) ||
                             str_contains(strtolower($s['category'] ?? ''), $platformLower);

            $categoryKeywords = [
                'followers' => ['follower'], 'likes' => ['like'], 'views' => ['view'],
                'story'     => ['story'],    'saves' => ['save'], 'shares' => ['share'],
            ];
            $keywords      = $categoryKeywords[$baseCategory] ?? [$baseCategory];
            $categoryMatch = false;
            foreach ($keywords as $kw) {
                if (str_contains($name, $kw)) { $categoryMatch = true; break; }
            }
            
            if (!$platformMatch || !$categoryMatch) return false;

            // Region filter
            $isIndo = str_contains($name, 'indonesia') || str_contains($name, 'indo ') || str_contains(strtolower($s['category'] ?? ''), 'indonesia');
            
            if ($region === 'id' && !$isIndo) return false;
            if ($region === 'ww' && $isIndo) return false;

            return true;
        })
        ->map(function ($s) {
            $name = strtolower($s['name']);
            $score = 0;
            
            // Prioritize Refill
            if (str_contains($name, 'refill') && !str_contains($name, 'no refill')) $score += 20;
            if (str_contains($name, '♻️')) $score += 20;
            
            // Prioritize High Quality & No/Low Drop
            if (str_contains($name, 'hq') || str_contains($name, 'high quality')) $score += 20;
            if (str_contains($name, 'low drop') || str_contains($name, 'no drop') || str_contains($name, 'non drop')) $score += 20;
            if (str_contains($name, 'real')) $score += 15;
            
            // Prioritize Speed
            if (str_contains($name, 'fast') || str_contains($name, 'instant') || str_contains($name, '⚡') || str_contains($name, '🚀')) $score += 10;
            
            // Penalize Low Quality
            if (str_contains($name, 'no refill') || str_contains($name, '🚫')) $score -= 50;
            if (str_contains($name, 'lq') || str_contains($name, 'low quality') || str_contains($name, 'bot')) $score -= 50;
            if (str_contains($name, 'slow')) $score -= 20;

            // If API returns "refill" field as true
            if (!empty($s['refill'])) $score += 10;

            $s['quality_score'] = $score;
            return $s;
        })
        ->sortByDesc(function ($s) {
            return [$s['quality_score'], $s['rate']];
        })
        ->take(10)       // Ambil 10 teratas
        ->values();

        if ($filtered->isEmpty()) {
            $bot->answerCallbackQuery(text: "Tidak ada layanan di kategori ini.");
            return;
        }

        $displayCategory = (str_contains($cat, '_id') ? strtoupper(str_replace('_', ' ', $cat)) : (str_contains($cat, '_ww') ? strtoupper(str_replace('_ww', ' WORLD WIDE', $cat)) : ucfirst($cat)));
        $text = "📦 *" . $displayCategory . "*\n\n";

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