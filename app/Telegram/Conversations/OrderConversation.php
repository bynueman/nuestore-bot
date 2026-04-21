<?php

namespace App\Telegram\Conversations;

use App\Models\NuestoreSetting;
use App\Models\NuestoreTransaction;
use App\Models\NuestoreUser;
use App\Services\DuitkuService;
use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class OrderConversation extends Conversation
{
    protected ?string $platform        = null;
    protected ?string $category        = null;
    protected ?int    $serviceId       = null;
    protected ?array  $selectedService = null;
    protected ?string $targetLink      = null;
    protected ?int    $quantity        = null;

    protected array $categories = [
        'instagram' => [
            'ig_followers_id' => ['label' => '👥 Followers 🇮🇩 Indonesia', 'keywords' => ['followers', 'indonesia'], 'exclude' => ['buzzer']],
            'ig_followers_ww' => ['label' => '👥 Followers 🌍 Worldwide',  'keywords' => ['followers'],              'exclude' => ['indonesia', 'buzzer']],
            'ig_likes_id'     => ['label' => '❤️ Likes 🇮🇩 Indonesia',     'keywords' => ['likes', 'indonesia'],     'exclude' => ['buzzer', 'auto']],
            'ig_likes_ww'     => ['label' => '❤️ Likes 🌍 Worldwide',      'keywords' => ['likes'],                  'exclude' => ['indonesia', 'reels', 'split', 'real users', 'power', 'auto', 'buzzer']],
            'ig_views'        => ['label' => '▶️ Views',                   'keywords' => ['video views'],            'exclude' => []],
            'ig_story'        => ['label' => '📖 Story Views',             'keywords' => ['story views'],            'exclude' => []],
        ],
        'tiktok' => [
            'tt_followers_id' => ['label' => '👥 Followers 🇮🇩 Indonesia', 'keywords' => ['followers', 'indonesian'], 'exclude' => ['buzzer']],
            'tt_followers_ww' => ['label' => '👥 Followers 🌍 Worldwide',  'keywords' => ['followers'],               'exclude' => ['indonesian', 'indonesia', 'buzzer']],
            'tt_likes_id'     => ['label' => '❤️ Likes 🇮🇩 Indonesia',     'keywords' => ['likes', 'indonesian'],     'exclude' => ['buzzer']],
            'tt_likes_ww'     => ['label' => '❤️ Likes 🌍 Worldwide',      'keywords' => ['likes'],                   'exclude' => ['indonesian', 'indonesia', 'buzzer']],
            'tt_views_id'     => ['label' => '▶️ Views 🇮🇩 Indonesia',     'keywords' => ['views', 'indonesian'],     'exclude' => ['buzzer']],
            'tt_views_ww'     => ['label' => '▶️ Views 🌍 Worldwide',      'keywords' => ['views'],                   'exclude' => ['indonesian', 'indonesia', 'live', 'saves', 'buzzer']],
            'tt_saves'        => ['label' => '🔖 Saves',                   'keywords' => ['saves'],                   'exclude' => ['live', 'buzzer']],
            'tt_shares'       => ['label' => '↗️ Shares',                  'keywords' => ['shares'],                  'exclude' => ['live', 'buzzer']],
        ],
    ];

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "🛒 *Buat Pesanan Baru*\n\nPilih platform:",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('📸 Instagram', callback_data: 'platform:instagram'),
                    InlineKeyboardButton::make('🎵 TikTok',    callback_data: 'platform:tiktok'),
                )
        );

        $this->next('selectPlatform');
    }

    public function selectPlatform(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if (!$data || !str_starts_with($data, 'platform:')) {
            $bot->answerCallbackQuery();
            return;
        }

        $platform = str_replace('platform:', '', $data);

        if (!isset($this->categories[$platform])) {
            $bot->answerCallbackQuery();
            return;
        }

        $this->platform = $platform;
        $bot->answerCallbackQuery();

        $bot->editMessageText(
            text: $this->getCategoryText(),
            parse_mode: 'Markdown',
            reply_markup: $this->buildCategoryKeyboard(),
            chat_id: $bot->callbackQuery()->message->chat->id,
            message_id: $bot->callbackQuery()->message->message_id,
        );

        $this->next('selectCategory');
    }

    public function selectCategory(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if (!$data) {
            $bot->answerCallbackQuery();
            return;
        }

        if ($data === 'back:platform') {
            $bot->answerCallbackQuery();
            $this->platform = null;
            $bot->editMessageText(
                text: "🛒 *Buat Pesanan Baru*\n\nPilih platform:",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('📸 Instagram', callback_data: 'platform:instagram'),
                        InlineKeyboardButton::make('🎵 TikTok',    callback_data: 'platform:tiktok'),
                    ),
                chat_id: $bot->callbackQuery()->message->chat->id,
                message_id: $bot->callbackQuery()->message->message_id,
            );
            $this->next('selectPlatform');
            return;
        }

        if (!str_starts_with($data, 'category:')) {
            $bot->answerCallbackQuery();
            return;
        }

        $category = str_replace('category:', '', $data);

        if (!isset($this->categories[$this->platform][$category])) {
            $bot->answerCallbackQuery();
            $bot->sendMessage(text: "❌ Kategori tidak valid. Ketik /order untuk mulai ulang.");
            $this->end();
            return;
        }

        $this->category = $category;
        $bot->answerCallbackQuery();

        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();

        if (!$services) {
            $bot->sendMessage(text: "❌ Gagal memuat layanan. Coba lagi nanti.");
            $this->end();
            return;
        }

        $whitelist = array_filter(
            array_map('trim', explode(',', NuestoreSetting::get('whitelisted_service_ids', '')))
        );

        $catConfig = $this->categories[$this->platform][$this->category];
        $markup    = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);

        $filtered = collect($services)->filter(function ($s) use ($whitelist, $catConfig) {
            if (!empty($whitelist) && !in_array((string)$s['service'], $whitelist)) {
                return false;
            }
            $name = strtolower($s['name']);
            foreach ($catConfig['keywords'] as $kw) {
                if (!str_contains($name, strtolower($kw))) return false;
            }
            foreach ($catConfig['exclude'] ?? [] as $ex) {
                if (str_contains($name, strtolower($ex))) return false;
            }
            return true;
        });

        if ($filtered->isEmpty()) {
            $bot->sendMessage(text: "⚠️ Tidak ada layanan tersedia untuk kategori ini. Ketik /order untuk mulai ulang.");
            $this->end();
            return;
        }

        $keyboard = InlineKeyboardMarkup::make();
        foreach ($filtered as $s) {
            $harga = number_format((float)$s['rate'] * $markup, 0, ',', '.');
            $label = "#{$s['service']} • Rp {$harga}/1000 • Min:{$s['min']}";
            $keyboard->addRow(
                InlineKeyboardButton::make($label, callback_data: "service:{$s['service']}")
            );
        }
        $keyboard->addRow(InlineKeyboardButton::make('« Kembali', callback_data: 'back:category'));

        $catLabel = $this->categories[$this->platform][$this->category]['label'];

        $bot->editMessageText(
            text: "🛒 *Buat Pesanan Baru*\n\nKategori: *{$catLabel}*\nPilih layanan:",
            parse_mode: 'Markdown',
            reply_markup: $keyboard,
            chat_id: $bot->callbackQuery()->message->chat->id,
            message_id: $bot->callbackQuery()->message->message_id,
        );

        $this->next('selectService');
    }

    public function selectService(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if (!$data) {
            $bot->answerCallbackQuery();
            return;
        }

        if ($data === 'back:category') {
            $bot->answerCallbackQuery();
            $this->category = null;
            $bot->editMessageText(
                text: $this->getCategoryText(),
                parse_mode: 'Markdown',
                reply_markup: $this->buildCategoryKeyboard(),
                chat_id: $bot->callbackQuery()->message->chat->id,
                message_id: $bot->callbackQuery()->message->message_id,
            );
            $this->next('selectCategory');
            return;
        }

        if (!str_starts_with($data, 'service:')) {
            $bot->answerCallbackQuery();
            return;
        }

        $this->serviceId = (int) str_replace('service:', '', $data);
        $bot->answerCallbackQuery();

        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();
        $found    = collect($services)->firstWhere('service', $this->serviceId);

        if (!$found) {
            $bot->sendMessage(text: "❌ Layanan tidak ditemukan. Ketik /order untuk mulai ulang.");
            $this->end();
            return;
        }

        $this->selectedService = $found;
        $markup = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);
        $harga  = number_format((float)$found['rate'] * $markup, 0, ',', '.');

        $text = "✅ *Layanan dipilih:*\n\n" .
                "📦 {$found['name']}\n" .
                "💰 Rp {$harga} / 1000\n" .
                "📊 Min: {$found['min']} | Max: {$found['max']}\n\n";

        if ($this->platform === 'instagram' && str_contains($this->category, 'followers')) {
            $text .= "⚠️ *Penting sebelum order:*\n" .
                     "• Pastikan akun *tidak di-private*\n" .
                     "• Matikan *Flag for Review* di Settings Instagram\n" .
                     "  _(Settings → Following and Followers → Flag for Review → OFF)_\n\n";
        }

        $text .= "Masukkan *link target* akun/konten:\n_(Contoh: https://instagram.com/username)_";

        $bot->editMessageText(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: null,
            chat_id: $bot->callbackQuery()->message->chat->id,
            message_id: $bot->callbackQuery()->message->message_id,
        );

        $this->next('askLink');
    }

    public function askLink(Nutgram $bot): void
    {
        $link = $bot->message()?->text;
        if (!$link) return;

        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            $bot->sendMessage(
                text: "❌ Link tidak valid. Masukkan URL yang benar:\n_(Contoh: https://instagram.com/username)_",
                parse_mode: 'Markdown'
            );
            return;
        }

        $this->targetLink = $link;

        $bot->sendMessage(
            text: "📊 Masukkan *jumlah* yang ingin dipesan:\n_(Min: {$this->selectedService['min']} | Max: {$this->selectedService['max']})_",
            parse_mode: 'Markdown'
        );

        $this->next('askQuantity');
    }

    public function askQuantity(Nutgram $bot): void
    {
        $qty = $bot->message()?->text;
        if (!$qty) return;

        if (!is_numeric($qty)) {
            $bot->sendMessage(text: "❌ Jumlah harus berupa angka. Coba lagi:");
            return;
        }

        $qty = (int) $qty;

        if ($qty < (int)$this->selectedService['min'] || $qty > (int)$this->selectedService['max']) {
            $bot->sendMessage(
                text: "❌ Jumlah harus antara {$this->selectedService['min']} - {$this->selectedService['max']}. Coba lagi:"
            );
            return;
        }

        $this->quantity = $qty;

        $markup       = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);
        $hargaPer1000 = (float)$this->selectedService['rate'] * $markup;
        $totalHarga   = (int) ceil(($hargaPer1000 / 1000) * $qty);
        $totalRupiah  = number_format($totalHarga, 0, ',', '.');

        $bot->sendMessage(
            text: "📋 *Konfirmasi Pesanan:*\n\n" .
                  "📦 {$this->selectedService['name']}\n" .
                  "🔗 Target: {$this->targetLink}\n" .
                  "📊 Jumlah: " . number_format($qty, 0, ',', '.') . "\n" .
                  "💰 Total: Rp {$totalRupiah}\n\n" .
                  "Lanjutkan pembayaran?",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ YA, Bayar Sekarang', callback_data: 'confirm:yes'),
                    InlineKeyboardButton::make('❌ Batal',              callback_data: 'confirm:no'),
                )
        );

        $this->next('processPayment');
    }

    public function processPayment(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if (!$data) {
            $bot->answerCallbackQuery();
            return;
        }

        if ($data === 'confirm:no') {
            $bot->answerCallbackQuery();
            $bot->editMessageText(
                text: "❌ Pesanan dibatalkan. Ketik /order untuk mulai ulang.",
                chat_id: $bot->callbackQuery()->message->chat->id,
                message_id: $bot->callbackQuery()->message->message_id,
            );
            $this->end();
            return;
        }

        if ($data !== 'confirm:yes') {
            $bot->answerCallbackQuery();
            return;
        }

        $bot->answerCallbackQuery();
        $bot->editMessageText(
            text: "⏳ Membuat invoice pembayaran...",
            chat_id: $bot->callbackQuery()->message->chat->id,
            message_id: $bot->callbackQuery()->message->message_id,
        );

        $markup       = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);
        $hargaPer1000 = (float)$this->selectedService['rate'] * $markup;
        $totalHarga   = (int) ceil(($hargaPer1000 / 1000) * $this->quantity);
        $modalCost    = (float)$this->selectedService['rate'] / 1000 * $this->quantity;
        $pgFee        = $totalHarga * 0.007;
        $profitEst    = $totalHarga - $modalCost - $pgFee;

        $telegramId = $bot->userId();
        $user = NuestoreUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['username'    => $bot->user()->username ?? null]
        );

        $transaction = NuestoreTransaction::create([
            'user_id'          => $user->id,
            'target_link'      => $this->targetLink,
            'service_id'       => $this->serviceId,
            'amount_paid'      => $totalHarga,
            'modal_cost'       => $modalCost,
            'pg_fee_estimated' => $pgFee,
            'profit_estimated' => $profitEst,
            'status'           => 'UNPAID',
        ]);

        $duitku   = new DuitkuService();
        $response = $duitku->createTransaction(
            orderId:        $transaction->id,
            amount:         $totalHarga,
            productDetails: "Order - {$this->selectedService['name']}",
            email:          'customer@nuestore.id',
            phoneNumber:    '08000000000',
            customerName:   $bot->user()->first_name ?? 'Customer',
            returnUrl:      config('app.url'),
            callbackUrl:    config('app.url') . '/api/webhook/duitku'
        );

        if (!$response || !isset($response['paymentUrl'])) {
            $bot->sendMessage(text: "❌ Gagal membuat invoice. Coba lagi nanti.");
            $transaction->delete();
            $this->end();
            return;
        }

        $transaction->update(['duitku_ref' => $response['merchantOrderId'] ?? $transaction->id]);

        $totalFormatted = number_format($totalHarga, 0, ',', '.');

        $bot->sendMessage(
            text: "✅ *Invoice Berhasil Dibuat!*\n\n" .
                  "📋 Order ID: `{$transaction->id}`\n" .
                  "💰 Total: Rp {$totalFormatted}\n\n" .
                  "🔗 *Link Pembayaran:*\n{$response['paymentUrl']}\n\n" .
                  "⏰ Invoice berlaku *60 menit*.\n" .
                  "Setelah bayar, pesanan diproses otomatis!\n\n" .
                  "Gunakan `/status {$transaction->id}` untuk cek status.",
            parse_mode: 'Markdown'
        );

        $this->end();
    }

    // ── Helper methods ──────────────────────────────────────────

    private function getCategoryText(): string
    {
        $platformLabel = $this->platform === 'instagram' ? '📸 Instagram' : '🎵 TikTok';
        return "🛒 *Buat Pesanan Baru*\n\nPlatform: *{$platformLabel}*\nPilih kategori:";
    }

    private function buildCategoryKeyboard(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();
        $row      = [];

        foreach ($this->categories[$this->platform] as $key => $cat) {
            $row[] = InlineKeyboardButton::make($cat['label'], callback_data: "category:{$key}");
            if (count($row) === 2) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }

        if (!empty($row)) {
            $keyboard->addRow(...$row);
        }

        $keyboard->addRow(InlineKeyboardButton::make('« Kembali', callback_data: 'back:platform'));

        return $keyboard;
    }
}