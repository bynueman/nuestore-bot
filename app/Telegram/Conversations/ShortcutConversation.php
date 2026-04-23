<?php

namespace App\Telegram\Conversations;

use App\Models\NuestoreSetting;
use App\Models\NuestoreTransaction;
use App\Services\LollipopSmmService;
use App\Telegram\Handlers\Admin\NotificationService;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ShortcutConversation extends Conversation
{
    protected ?string $platform     = null;
    protected ?string $category     = null;
    protected ?string $targetLink   = null;
    protected ?int    $quantity     = null;
    protected ?string $customerNote = null;

    protected ?int    $serviceId    = null;
    protected ?string $serviceName  = null;
    protected ?float  $serviceRate  = null;
    protected ?int    $serviceMin   = null;
    protected ?int    $serviceMax   = null;

    protected ?float  $totalPrice   = null;
    protected ?float  $modalCost    = null;

    public function start(Nutgram $bot): void
    {
        $text = $bot->message()?->text ?? '';

        // Extract using regex
        if (preg_match('/Platform:\s*(.+)/i', $text, $m)) $this->platform = trim($m[1]);
        if (preg_match('/Layanan:\s*(.+)/i', $text, $m)) $this->category = trim($m[1]);
        if (preg_match('/Target:\s*(.+)/i', $text, $m)) $this->targetLink = trim($m[1]);
        if (preg_match('/Jumlah:\s*(\d+)/i', $text, $m)) $this->quantity = (int) $m[1];
        if (preg_match('/Catatan:\s*(.*)/i', $text, $m)) $this->customerNote = trim($m[1]);

        if (empty($this->platform) || empty($this->category) || empty($this->targetLink) || empty($this->quantity)) {
            $bot->sendMessage("❌ Gagal membaca format order. Pastikan field Platform, Layanan, Target, dan Jumlah wajib diisi.");
            $this->end();
            return;
        }

        if (!filter_var($this->targetLink, FILTER_VALIDATE_URL)) {
            $bot->sendMessage("❌ Target harus berupa URL (https://...).");
            $this->end();
            return;
        }

        if ($this->customerNote === '-' || $this->customerNote === '') {
            $this->customerNote = null;
        }

        $bot->sendMessage("⏳ _Menganalisa dan mencari layanan termurah..._", parse_mode: 'Markdown');

        $this->findAndMatchService($bot);
    }

    private function findAndMatchService(Nutgram $bot): void
    {
        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();

        if (!$services) {
            $bot->sendMessage('❌ Gagal mengambil layanan dari API Lollipop. Coba lagi.');
            $this->end();
            return;
        }

        $filtered = collect($services)->filter(function ($s) {
            $name     = strtolower($s['name']);
            $platform = strtolower($this->platform);
            
            // Map category to base and region
            $catLower     = strtolower($this->category);
            $region       = 'all';
            if (str_contains($catLower, ' id')) $region = 'id';
            if (str_contains($catLower, ' ww')) $region = 'ww';

            // Find base category keyword (followers, likes, views, saves, shares, story)
            $baseCategory = '';
            foreach (['followers', 'likes', 'views', 'saves', 'shares', 'story'] as $kw) {
                if (str_contains($catLower, $kw)) {
                    $baseCategory = $kw;
                    break;
                }
            }
            if (!$baseCategory) {
                // Return false if unsupported category format
                return false;
            }

            $platformMatch = str_contains($name, $platform) ||
                             str_contains(strtolower($s['category'] ?? ''), $platform);

            $categoryKeywords = [
                'followers' => ['follower'], 'likes' => ['like'], 'views' => ['view'],
                'story'     => ['story'],    'saves' => ['save'], 'shares' => ['share'],
            ];
            $keywords      = $categoryKeywords[$baseCategory] ?? [$baseCategory];
            $categoryMatch = false;
            foreach ($keywords as $kw) {
                if (str_contains($name, $kw)) { $categoryMatch = true; break; }
            }
            
            if (!$platformMatch || (!$categoryMatch && !str_contains(strtolower($s['category'] ?? ''), $baseCategory))) return false;

            // Region filter
            $isIndo = str_contains($name, 'indonesia') || str_contains($name, 'indo ') || str_contains(strtolower($s['category'] ?? ''), 'indonesia');
            
            if ($region === 'id' && !$isIndo) return false;
            if ($region === 'ww' && $isIndo) return false;

            return true;
        })
        ->sortBy('rate') // Urutkan dari yang paling murah
        ->values();

        if ($filtered->isEmpty()) {
            $bot->sendMessage("❌ Tidak ada layanan yang cocok untuk Platform: {$this->platform} dan Layanan: {$this->category}");
            $this->end();
            return;
        }

        // Ambil yang paling murah (#1)
        $bestService = $filtered->first();

        if ($this->quantity < $bestService['min'] || $this->quantity > $bestService['max']) {
            $bot->sendMessage(
                text: "❌ *Jumlah tidak memenuhi syarat API.*\n\nLayanan Termurah: `#{$bestService['service']} {$bestService['name']}`\nSyarat API: Min {$bestService['min']}, Max " . number_format($bestService['max'], 0, ',', '.') . "\nJumlah mu: {$this->quantity}",
                parse_mode: 'Markdown'
            );
            $this->end();
            return;
        }

        $this->serviceId   = (int) $bestService['service'];
        $this->serviceName = $bestService['name'];
        $this->serviceRate = (float) $bestService['rate'];
        $this->serviceMin  = (int) $bestService['min'];
        $this->serviceMax  = (int) $bestService['max'];

        $markup           = (float) NuestoreSetting::get('global_markup_multiplier', '2.0');
        $this->totalPrice = (float) ceil(($this->serviceRate * $markup / 1000) * $this->quantity);
        $this->modalCost  = (float) ($this->serviceRate / 1000 * $this->quantity);

        $totalFormat  = number_format($this->totalPrice, 0, ',', '.');
        $modalFormat  = number_format($this->modalCost, 0, ',', '.');
        $profitFormat = number_format($this->totalPrice - $this->modalCost, 0, ',', '.');

        $noteText = $this->customerNote ? "\n📝 Catatan Pelanggan: {$this->customerNote}" : '';

        $bot->sendMessage(
            text: "✅ *LAYANAN TERMURAH DITEMUKAN*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "🏷️ Layanan: `#{$this->serviceId}` {$this->serviceName}\n"
                . "🔗 Target: `{$this->targetLink}`\n"
                . "🔢 Qty: " . number_format($this->quantity, 0, ',', '.') . "\n"
                . $noteText . "\n\n"
                . "💰 Tagih ke pelanggan: *Rp {$totalFormat}*\n"
                . "📦 Modal Lollipop: Rp {$modalFormat}\n"
                . "📈 Est. Profit: *Rp {$profitFormat}*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                . "Lanjut submit order ke Lollipop?",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ Submit ke Lollipop', callback_data: 'sc_confirm'),
                    InlineKeyboardButton::make('❌ Batal', callback_data: 'sc_cancel')
                )
        );

        $this->next('submitOrder');
    }

    public function submitOrder(Nutgram $bot): void
    {
        $cb = $bot->callbackQuery()?->data ?? '';

        if ($cb === 'sc_cancel') {
            $bot->answerCallbackQuery();
            $bot->sendMessage("❌ Shortcut order dibatalkan.");
            $this->end();
            return;
        }

        if ($cb !== 'sc_confirm') {
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
                    . "🏷️ `#{$this->serviceId}` {$this->serviceName}\n"
                    . "🔗 `{$this->targetLink}`\n"
                    . "🔢 Qty: " . number_format($this->quantity, 0, ',', '.') . "\n\n"
                    . "Gunakan `/status {$result['order']}` untuk cek progress.",
                parse_mode: 'Markdown'
            );

            $notify = new NotificationService();
            $notify->notifyNewOrder($transaction->id, $this->serviceName, $this->targetLink, (int) $this->totalPrice);
        } else {
            $errorDetail = json_encode($result);
            $bot->sendMessage(
                text: "❌ *Gagal submit ke Lollipop!*\n\nResponse: `{$errorDetail}`\n\nCek saldo atau jalankan fungsi order manual.",
                parse_mode: 'Markdown'
            );
        }

        $this->end();
    }
}
