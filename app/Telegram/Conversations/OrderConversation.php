<?php

namespace App\Telegram\Conversations;

use App\Models\NuestoreTransaction;
use App\Models\NuestoreSetting;
use App\Services\LollipopSmmService;
use App\Telegram\Handlers\Admin\NotificationService;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class OrderConversation extends Conversation
{
    // ─── State ────────────────────────────────────────────────────────────────
    protected ?string $platform       = null;
    protected ?string $category       = null;
    protected ?int    $serviceId      = null;
    protected ?string $serviceName    = null;
    protected ?string $serviceDesc    = null;
    protected ?string $serviceAvgTime = null;
    protected ?float  $serviceRate    = null;
    protected ?int    $serviceMin     = null;
    protected ?int    $serviceMax     = null;
    protected ?string $targetLink     = null;
    protected ?int    $quantity       = null;
    protected ?string $customerNote   = null;
    protected ?float  $totalPrice     = null;
    protected ?float  $modalCost      = null;

    // ─── Helper: cek apakah user mengetik perintah batal ──────────────────────
    private function isCancelText(string $text): bool
    {
        return in_array(strtolower(trim($text)), ['batal', '/batal', 'cancel', '/cancel', '❌', 'keluar', '/keluar']);
    }

    private function sendCancelled(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "❌ Order dibatalkan.\n\nKetik /order atau tap 🛒 *Order* untuk mulai lagi.",
            parse_mode: 'Markdown'
        );
        $this->end();
    }

    // ─── Helper: tampilkan pilihan platform (reusable) ────────────────────────
    private function sendPlatformKeyboard(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "🛒 *Input Order Baru*\n\nPilih platform pelanggan:",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('📸 Instagram', callback_data: 'sv_platform_instagram'),
                    InlineKeyboardButton::make('🎵 TikTok',   callback_data: 'sv_platform_tiktok'),
                )
                ->addRow(
                    InlineKeyboardButton::make('❌ Batalkan', callback_data: 'order_cancel_all'),
                )
        );
    }

    // ─── Helper: tampilkan pilihan kategori (reusable) ────────────────────────
    private function sendCategoryKeyboard(Nutgram $bot): void
    {
        $categories = $this->platform === 'instagram'
            ? [
                ['label' => '👥 Followers', 'key' => 'followers'],
                ['label' => '❤️ Likes',     'key' => 'likes'],
                ['label' => '▶️ Views',     'key' => 'views'],
                ['label' => '📖 Story',     'key' => 'story'],
            ]
            : [
                ['label' => '👥 Followers', 'key' => 'followers'],
                ['label' => '❤️ Likes',     'key' => 'likes'],
                ['label' => '▶️ Views',     'key' => 'views'],
                ['label' => '🔖 Saves',     'key' => 'saves'],
                ['label' => '🔁 Shares',    'key' => 'shares'],
            ];

        $keyboard = InlineKeyboardMarkup::make();
        $row = [];
        foreach ($categories as $i => $cat) {
            $row[] = InlineKeyboardButton::make($cat['label'], callback_data: "sv_cat_{$cat['key']}");
            if (count($row) === 2 || $i === count($categories) - 1) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }
        $keyboard->addRow(
            InlineKeyboardButton::make('🔙 Kembali ke Platform', callback_data: 'order_back_to_platform'),
        );

        $platformLabel = ucfirst($this->platform);
        $bot->sendMessage(
            text: "📂 Pilih kategori layanan *{$platformLabel}*:",
            parse_mode: 'Markdown',
            reply_markup: $keyboard
        );
    }

    // ─── Helper: tampilkan daftar layanan (reusable) ─────────────────────────
    private function sendServiceListKeyboard(Nutgram $bot): bool
    {
        $whitelistRaw = NuestoreSetting::get('whitelisted_service_ids', '');
        $whitelist    = array_map('intval', explode(',', $whitelistRaw));

        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();

        if (!$services) {
            $bot->sendMessage('❌ Gagal mengambil daftar layanan. Coba lagi nanti.');
            $this->end();
            return false;
        }

        $filtered = collect($services)->filter(function ($s) use ($whitelist) {
            if (!in_array((int)$s['service'], $whitelist)) return false;

            $name     = strtolower($s['name']);
            $platform = strtolower($this->platform);
            $category = strtolower($this->category);

            $platformMatch = str_contains($name, $platform) ||
                             str_contains(strtolower($s['category'] ?? ''), $platform);

            $categoryKeywords = [
                'followers' => ['follower'], 'likes' => ['like'], 'views' => ['view'],
                'story'     => ['story'],    'saves' => ['save'], 'shares' => ['share'],
            ];
            $keywords      = $categoryKeywords[$category] ?? [$category];
            $categoryMatch = false;
            foreach ($keywords as $kw) {
                if (str_contains($name, $kw)) { $categoryMatch = true; break; }
            }
            return $platformMatch && $categoryMatch;
        })->values();

        if ($filtered->isEmpty()) {
            $bot->sendMessage(
                text: "⚠️ Tidak ada layanan tersedia untuk kategori ini.\n\nCoba pilih kategori lain.",
                reply_markup: InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make('🔙 Kembali ke Kategori', callback_data: 'order_back_to_category'),
                )
            );
            return false;
        }

        $markup   = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
        $keyboard = InlineKeyboardMarkup::make();
        $text     = "📋 *Pilih Layanan " . ucfirst($this->platform) . " — " . ucfirst($this->category) . ":*\n\n";

        foreach ($filtered as $s) {
            $hargaJual   = (float)$s['rate'] * $markup;
            $hargaFormat = number_format($hargaJual, 0, ',', '.');
            $text       .= "▪️ `#{$s['service']}` — {$s['name']}\n";
            $text       .= "   💰 Rp {$hargaFormat}/1000 | Min: {$s['min']} | Max: {$s['max']}\n\n";
            $keyboard->addRow(
                InlineKeyboardButton::make("#{$s['service']} — {$s['name']}", callback_data: "sv_pick_{$s['service']}")
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('🔙 Kembali ke Kategori', callback_data: 'order_back_to_category'),
        );

        $bot->sendMessage(text: $text, parse_mode: 'Markdown', reply_markup: $keyboard);
        return true;
    }

    // =========================================================================
    // STEP 1: Pilih Platform
    // =========================================================================
    public function start(Nutgram $bot): void
    {
        $this->sendPlatformKeyboard($bot);
        $this->next('selectCategory');
    }

    // =========================================================================
    // STEP 2: Pilih Kategori
    //   Menerima: sv_platform_xxx | order_cancel_all
    // =========================================================================
    public function selectCategory(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        if ($cb === 'order_cancel_all') {
            $bot->answerCallbackQuery();
            $this->sendCancelled($bot);
            return;
        }

        if (!str_starts_with($cb, 'sv_platform_')) {
            $bot->sendMessage('⚠️ Pilih platform menggunakan tombol di atas.');
            return;
        }

        $this->platform = str_replace('sv_platform_', '', $cb);
        $bot->answerCallbackQuery();

        $this->sendCategoryKeyboard($bot);
        $this->next('selectService');
    }

    // =========================================================================
    // STEP 3: Pilih Layanan
    //   Menerima: sv_cat_xxx | order_back_to_platform | order_cancel_all
    // =========================================================================
    public function selectService(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        // ── Kembali ke platform ──────────────────────────────────────────────
        if ($cb === 'order_back_to_platform') {
            $bot->answerCallbackQuery();
            $this->platform = null;
            $this->category = null;
            $this->sendPlatformKeyboard($bot);
            $this->next('selectCategory');
            return;
        }

        if ($cb === 'order_cancel_all') {
            $bot->answerCallbackQuery();
            $this->sendCancelled($bot);
            return;
        }

        if (!str_starts_with($cb, 'sv_cat_')) {
            $bot->sendMessage('⚠️ Pilih kategori menggunakan tombol di atas.');
            return;
        }

        $this->category = str_replace('sv_cat_', '', $cb);
        $bot->answerCallbackQuery();

        $ok = $this->sendServiceListKeyboard($bot);
        if ($ok) {
            $this->next('showServiceDetail');
        } else {
            // Layanan kosong — tetap di selectService, tunggu kembali
            $this->next('selectService');
        }
    }

    // =========================================================================
    // STEP 3b: Detail Layanan
    //   Menerima: sv_pick_xxx | sv_detail_confirm | sv_detail_back
    //             | order_back_to_category | order_back_to_platform | order_cancel_all
    // =========================================================================
    public function showServiceDetail(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        // ── Kembali ke kategori ──────────────────────────────────────────────
        if ($cb === 'order_back_to_category') {
            $bot->answerCallbackQuery();
            $this->resetServiceState();
            $this->sendCategoryKeyboard($bot);
            $this->next('selectService');
            return;
        }

        // ── Kembali ke platform ──────────────────────────────────────────────
        if ($cb === 'order_back_to_platform') {
            $bot->answerCallbackQuery();
            $this->resetServiceState();
            $this->platform = null;
            $this->category = null;
            $this->sendPlatformKeyboard($bot);
            $this->next('selectCategory');
            return;
        }

        if ($cb === 'order_cancel_all') {
            $bot->answerCallbackQuery();
            $this->sendCancelled($bot);
            return;
        }

        // ── Kembali ke daftar layanan (dari detail) ──────────────────────────
        if ($cb === 'sv_detail_back') {
            $bot->answerCallbackQuery();
            $this->resetServiceState();
            $ok = $this->sendServiceListKeyboard($bot);
            if ($ok) {
                $this->next('showServiceDetail');
            } else {
                $this->next('selectService');
            }
            return;
        }

        // ── Konfirmasi pilih layanan → minta link ────────────────────────────
        if ($cb === 'sv_detail_confirm') {
            $bot->answerCallbackQuery();

            $platformHint = $this->platform === 'instagram'
                ? "Contoh: `https://instagram.com/username`"
                : "Contoh: `https://tiktok.com/@username`";

            if (in_array($this->category, ['likes', 'views', 'saves', 'shares', 'story'])) {
                $platformHint = $this->platform === 'instagram'
                    ? "Contoh: `https://instagram.com/p/XXXX/`"
                    : "Contoh: `https://tiktok.com/@user/video/XXXX`";
            }

            $markup      = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
            $hargaJual   = $this->serviceRate * $markup;
            $hargaFormat = number_format($hargaJual, 0, ',', '.');

            $bot->sendMessage(
                text: "✅ *{$this->serviceName}* dipilih.\n"
                    . "💰 Rp {$hargaFormat}/1000 | Min: {$this->serviceMin} | Max: {$this->serviceMax}\n\n"
                    . "🔗 *Masukkan link target pelanggan:*\n_{$platformHint}_\n\n"
                    . "_(Ketik `batal` untuk membatalkan order)_",
                parse_mode: 'Markdown'
            );

            $this->next('inputLink');
            return;
        }

        // ── Pilih layanan dari daftar ─────────────────────────────────────────
        if (!str_starts_with($cb, 'sv_pick_')) {
            $bot->sendMessage('⚠️ Pilih layanan menggunakan tombol di atas.');
            return;
        }

        $this->serviceId = (int) str_replace('sv_pick_', '', $cb);
        $bot->answerCallbackQuery();

        $lollipop      = new LollipopSmmService();
        $services      = $lollipop->getServices();
        $serviceDetail = collect($services)->firstWhere('service', $this->serviceId);

        if (!$serviceDetail) {
            $bot->sendMessage('❌ Layanan tidak ditemukan. Ulangi dari awal.');
            $this->end();
            return;
        }

        $this->serviceName    = $serviceDetail['name'];
        $this->serviceDesc    = $serviceDetail['description'] ?? null;
        $this->serviceAvgTime = isset($serviceDetail['average_time'])
            ? $serviceDetail['average_time'] . ' menit'
            : null;
        $this->serviceRate = (float) $serviceDetail['rate'];
        $this->serviceMin  = (int) $serviceDetail['min'];
        $this->serviceMax  = (int) $serviceDetail['max'];

        $markup      = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
        $hargaJual   = $this->serviceRate * $markup;
        $profitEst   = $hargaJual - $this->serviceRate;

        $hargaFormat  = number_format($hargaJual, 0, ',', '.');
        $modalFormat  = number_format($this->serviceRate, 0, ',', '.');
        $profitFormat = number_format($profitEst, 0, ',', '.');

        $detailText  = "📦 *{$this->serviceName}*\n";
        $detailText .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        if ($this->serviceDesc) {
            $detailText .= "📝 _{$this->serviceDesc}_\n\n";
        }
        if ($this->serviceAvgTime) {
            $detailText .= "⏱️ Rata-rata waktu: *{$this->serviceAvgTime}*\n";
        }
        $detailText .= "📊 Min: *{$this->serviceMin}* | Max: *" . number_format($this->serviceMax, 0, ',', '.') . "*\n\n";
        $detailText .= "💰 Harga jual ke pelanggan: *Rp {$hargaFormat}/1000*\n";
        $detailText .= "📦 Modal kamu: Rp {$modalFormat}/1000\n";
        $detailText .= "📈 Est. profit/1000: *Rp {$profitFormat}*\n";

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Pilih Layanan Ini', callback_data: 'sv_detail_confirm'),
            )
            ->addRow(
                InlineKeyboardButton::make('🔙 Kembali ke Daftar',   callback_data: 'sv_detail_back'),
                InlineKeyboardButton::make('🏠 Kembali ke Kategori', callback_data: 'order_back_to_category'),
            );

        $bot->sendMessage(text: $detailText, parse_mode: 'Markdown', reply_markup: $keyboard);
        $this->next('showServiceDetail');
    }

    // =========================================================================
    // STEP 4: Input Link Target
    //   Menerima: teks URL | "batal"
    // =========================================================================
    public function inputLink(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($this->isCancelText($text)) {
            $this->sendCancelled($bot);
            return;
        }

        if (empty($text) || !filter_var($text, FILTER_VALIDATE_URL)) {
            $bot->sendMessage(
                text: "⚠️ Link tidak valid. Masukkan URL lengkap yang benar (dimulai https://).\n\n"
                    . "_(Ketik `batal` untuk membatalkan)_",
                parse_mode: 'Markdown'
            );
            return;
        }

        $this->targetLink = $text;

        $markup      = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
        $hargaJual   = $this->serviceRate * $markup;
        $hargaFormat = number_format($hargaJual, 0, ',', '.');

        $bot->sendMessage(
            text: "✅ Link: `{$this->targetLink}`\n\n"
                . "🔢 *Masukkan jumlah (quantity):*\n"
                . "Min: *{$this->serviceMin}* | Max: *" . number_format($this->serviceMax, 0, ',', '.') . "*\n"
                . "💰 Harga: Rp {$hargaFormat}/1000\n\n"
                . "_(Ketik `batal` untuk membatalkan)_",
            parse_mode: 'Markdown'
        );

        $this->next('inputQuantity');
    }

    // =========================================================================
    // STEP 5: Input Quantity
    //   Menerima: angka | "batal"
    // =========================================================================
    public function inputQuantity(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($this->isCancelText($text)) {
            $this->sendCancelled($bot);
            return;
        }

        $qty = (int) $text;

        if ($qty < $this->serviceMin || $qty > $this->serviceMax) {
            $bot->sendMessage(
                text: "⚠️ Jumlah harus antara *{$this->serviceMin}* dan *" . number_format($this->serviceMax, 0, ',', '.') . "*.\n\n"
                    . "_(Ketik `batal` untuk membatalkan)_",
                parse_mode: 'Markdown'
            );
            return;
        }

        $this->quantity = $qty;

        $markup           = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
        $this->totalPrice = (float) ceil(($this->serviceRate * $markup / 1000) * $qty);
        $this->modalCost  = (float) ($this->serviceRate / 1000 * $qty);

        $totalFormat = number_format($this->totalPrice, 0, ',', '.');
        $modalFormat = number_format($this->modalCost, 0, ',', '.');

        $bot->sendMessage(
            text: "✅ Qty: *{$qty}*\n"
                . "💰 Estimasi tagih ke pelanggan: *Rp {$totalFormat}*\n"
                . "📦 Modal: Rp {$modalFormat}\n\n"
                . "📝 *Nama atau catatan pelanggan:*\n"
                . "_(Ketik `-` jika tidak ada catatan | Ketik `batal` untuk membatalkan)_",
            parse_mode: 'Markdown'
        );

        $this->next('inputCustomerNote');
    }

    // =========================================================================
    // STEP 6: Input Catatan Pelanggan
    //   Menerima: teks catatan | "-" | "batal"
    // =========================================================================
    public function inputCustomerNote(Nutgram $bot): void
    {
        $text = trim($bot->message()?->text ?? '');

        if ($this->isCancelText($text)) {
            $this->sendCancelled($bot);
            return;
        }

        $this->customerNote = ($text === '-') ? null : $text;

        $totalFormat  = number_format($this->totalPrice, 0, ',', '.');
        $modalFormat  = number_format($this->modalCost, 0, ',', '.');
        $profitEst    = $this->totalPrice - $this->modalCost;
        $profitFormat = number_format($profitEst, 0, ',', '.');

        $noteText = $this->customerNote ? "\n📝 Pelanggan: {$this->customerNote}" : '';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('✅ Submit ke Lollipop', callback_data: 'order_confirm'),
                InlineKeyboardButton::make('❌ Batal',              callback_data: 'order_cancel'),
            );

        $bot->sendMessage(
            text: "📋 *RINGKASAN ORDER*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "🏷️ Layanan: {$this->serviceName}\n"
                . "🔗 Target: `{$this->targetLink}`\n"
                . "🔢 Qty: " . number_format($this->quantity, 0, ',', '.') . "\n"
                . $noteText . "\n\n"
                . "💰 Tagih ke pelanggan: *Rp {$totalFormat}*\n"
                . "📦 Modal Lollipop: Rp {$modalFormat}\n"
                . "📈 Est. Profit: *Rp {$profitFormat}*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                . "Lanjut submit order ke Lollipop?",
            parse_mode: 'Markdown',
            reply_markup: $keyboard
        );

        $this->next('submitOrder');
    }

    // =========================================================================
    // STEP 7: Submit ke Lollipop
    //   Menerima: order_confirm | order_cancel
    // =========================================================================
    public function submitOrder(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        if ($cb === 'order_cancel') {
            $bot->answerCallbackQuery();
            $this->sendCancelled($bot);
            return;
        }

        if ($cb !== 'order_confirm') {
            $bot->sendMessage('⚠️ Gunakan tombol ✅ Submit atau ❌ Batal.');
            return;
        }

        $bot->answerCallbackQuery();
        $bot->sendMessage('⏳ Mengirim order ke Lollipop...');

        $lollipop = new LollipopSmmService();
        $result   = $lollipop->createOrder(
            serviceId: $this->serviceId,
            link:      $this->targetLink,
            quantity:  $this->quantity
        );

        if ($result && isset($result['order'])) {
            $transaction = NuestoreTransaction::create([
                'provider_order_id' => (string) $result['order'],
                'target_link'       => $this->targetLink,
                'service_id'        => $this->serviceId,
                'quantity'          => $this->quantity,
                'amount_paid'       => $this->totalPrice,
                'modal_cost'        => $this->modalCost,
                'profit_estimated'  => $this->totalPrice - $this->modalCost,
                'customer_note'     => $this->customerNote,
                'status'            => 'PROCESSING',
            ]);

            $bot->sendMessage(
                text: "✅ *Order Berhasil Disubmit!*\n\n"
                    . "🆔 Order ID: `{$transaction->id}`\n"
                    . "📦 Provider ID: `{$result['order']}`\n"
                    . "🏷️ {$this->serviceName}\n"
                    . "🔗 `{$this->targetLink}`\n"
                    . "🔢 Qty: " . number_format($this->quantity, 0, ',', '.') . "\n\n"
                    . "Gunakan `/status {$transaction->id}` untuk cek progress.",
                parse_mode: 'Markdown'
            );

            $notify = new NotificationService();
            $notify->notifyNewOrder($transaction->id, $this->serviceName, $this->targetLink, (int) $this->totalPrice);

        } else {
            $errorDetail = json_encode($result);
            $bot->sendMessage(
                text: "❌ *Gagal submit ke Lollipop!*\n\nResponse: `{$errorDetail}`\n\nCek saldo atau coba lagi dengan /order.",
                parse_mode: 'Markdown'
            );
        }

        $this->end();
    }

    // ─── Private: reset state layanan ─────────────────────────────────────────
    private function resetServiceState(): void
    {
        $this->serviceId      = null;
        $this->serviceName    = null;
        $this->serviceDesc    = null;
        $this->serviceAvgTime = null;
        $this->serviceRate    = null;
        $this->serviceMin     = null;
        $this->serviceMax     = null;
    }
}