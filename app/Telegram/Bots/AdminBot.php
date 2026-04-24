<?php

namespace App\Telegram\Bots;

use App\Models\NuestoreCustomer;
use App\Models\NuestoreOrder;
use App\Services\LollipopSmmService;
use App\Telegram\Handlers\Admin\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AdminBot
{
    private ?Nutgram $bot = null;

    public function __construct()
    {
        $token = config('nutgram.admin_bot_token');
        if (empty($token)) {
            Log::error('AdminBot: TELEGRAM_ADMIN_BOT_TOKEN is missing in .env');
            return;
        }
        $this->bot = new Nutgram($token);
    }

    public function register(): void
    {
        if (!isset($this->bot)) return;
        $bot = $this->bot;

        // /start
        $bot->onCommand('start', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $bot->sendMessage(
                text: "👨‍💼 *Nuestore Admin Panel*\n\n"
                    . "Selamat datang, Boss! 🎉\n\n"
                    . "📋 *Menu:*\n"
                    . "/dashboard — Ringkasan hari ini\n"
                    . "/pending   — Order pelanggan belum bayar\n"
                    . "/queued    — Pesanan dalam antrean\n"
                    . "/retry     — Proses ulang antrean\n"
                    . "/balance   — Cek saldo Lollipop\n",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('📊 Dashboard',   callback_data: 'admin:dashboard'),
                        InlineKeyboardButton::make('💰 Cek Saldo',   callback_data: 'admin:balance'),
                    )
                    ->addRow(
                        InlineKeyboardButton::make('🕐 Antrean',     callback_data: 'admin:queued'),
                        InlineKeyboardButton::make('🔄 Retry Queue', callback_data: 'admin:retry_queue'),
                    )
                    ->addRow(
                        InlineKeyboardButton::make('⏳ Pending Bayar', callback_data: 'admin:pending'),
                    )
            );
        });

        $bot->onCommand('dashboard', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->sendDashboard($bot);
        });

        $bot->onCommand('queued', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->sendQueued($bot);
        });

        $bot->onCommand('retry', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->retryQueue($bot);
        });

        $bot->onCommand('balance', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->sendBalance($bot);
        });

        $bot->onCommand('pending', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->sendPendingOrders($bot);
        });

        // Handle semua callback query
        $bot->onCallbackQuery(function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Unauthorized");
                return;
            }

            $data = $bot->callbackQuery()?->data ?? '';
            $bot->answerCallbackQuery();

            // ── Admin panel callbacks ──
            match (true) {
                $data === 'admin:dashboard'   => $this->sendDashboard($bot),
                $data === 'admin:balance'     => $this->sendBalance($bot),
                $data === 'admin:queued'      => $this->sendQueued($bot),
                $data === 'admin:retry_queue' => $this->retryQueue($bot),
                $data === 'admin:pending'     => $this->sendPendingOrders($bot),

                // ── Customer order callbacks ──
                str_starts_with($data, 'cust_approve:')   => $this->approveOrder($bot, substr($data, 13)),
                str_starts_with($data, 'cust_reject:')    => $this->rejectOrder($bot, substr($data, 12)),
                str_starts_with($data, 'cust_blacklist:') => $this->blacklistCustomer($bot, substr($data, 15)),

                default => null,
            };
        });
    }

    public function run(): void
    {
        if ($this->bot === null) {
            echo "❌ Gagal menjalankan Admin Bot: TELEGRAM_ADMIN_BOT_TOKEN kosong di .env\n";
            return;
        }
        $this->register();
        $this->bot->run();
    }

    // ─────────────────────────────────────────────
    // Customer Order Actions
    // ─────────────────────────────────────────────

    private function approveOrder(Nutgram $bot, string $orderId): void
    {
        $order = NuestoreOrder::find($orderId);

        if (!$order) {
            $bot->sendMessage("❌ Order tidak ditemukan: `{$orderId}`", parse_mode: 'Markdown');
            return;
        }

        if (!in_array($order->status, ['PROOF_SUBMITTED'])) {
            $bot->sendMessage("⚠️ Order ini sudah diproses sebelumnya (Status: {$order->status}).");
            return;
        }

        // RACE CONDITION FIX: Lock the order before submitting to Lollipop
        $updated = NuestoreOrder::where('id', $orderId)
            ->where('status', 'PROOF_SUBMITTED')
            ->update(['status' => 'APPROVED']);

        if (!$updated) {
            $bot->sendMessage("⚠️ Order ini sedang diproses oleh admin lain.");
            return;
        }

        // Submit ke Lollipop
        $lollipop = new LollipopSmmService();
        $result   = $lollipop->createOrder($order->service_id, $order->target_link, $order->quantity);

        if ($result && isset($result['order'])) {
            $order->update([
                'status'            => 'PROCESSING',
                'provider_order_id' => (string) $result['order'],
            ]);

            // Edit tombol jadi disabled
            if ($order->admin_message_id) {
                $this->removeOrderButtons($order->admin_message_id);
            }

            $bot->sendMessage(
                text: "✅ *Order Diapprove & Diproses!*\n\n"
                    . "🆔 Order: `{$orderId}`\n"
                    . "📦 Provider ID: `{$result['order']}`\n"
                    . "📦 {$order->service_name}",
                parse_mode: 'Markdown'
            );

            // Notifikasi ke pelanggan via Customer Bot
            $this->notifyCustomer(
                $order->customer->telegram_id,
                "✅ *Pembayaran Dikonfirmasi!*\n\n"
                . "Order kamu sedang diproses sekarang.\n\n"
                . "📦 {$order->service_name}\n"
                . "🔗 Target: `{$order->target_link}`\n"
                . "🔢 Qty: " . number_format($order->quantity, 0, ',', '.') . "\n\n"
                . "Terima kasih sudah berbelanja di Nuestore! 🎉"
            );
        } else {
            // Lollipop submit failed — keep status APPROVED for retry queue
            $errorDetail = json_encode($result);
            $bot->sendMessage(
                text: "⚠️ *Approved tapi Gagal Submit ke Lollipop!*\n\n"
                    . "Response: `{$errorDetail}`\n\n"
                    . "Cek saldo Lollipop dan retry manual.",
                parse_mode: 'Markdown'
            );
        }
    }

    private function rejectOrder(Nutgram $bot, string $orderId): void
    {
        $order = NuestoreOrder::find($orderId);

        if (!$order) {
            $bot->sendMessage("❌ Order tidak ditemukan: `{$orderId}`", parse_mode: 'Markdown');
            return;
        }

        if ($order->status !== 'PROOF_SUBMITTED') {
            $bot->sendMessage("⚠️ Order ini sudah diproses (Status: {$order->status}).");
            return;
        }

        $order->update(['status' => 'REJECTED', 'rejected_reason' => 'Bukti pembayaran tidak valid']);

        // Increment failed_proofs_count untuk deteksi penipuan
        $customer = $order->customer;
        $customer->increment('failed_proofs_count');

        // Edit tombol
        if ($order->admin_message_id) {
            $this->removeOrderButtons($order->admin_message_id);
        }

        $bot->sendMessage("❌ Order `{$orderId}` di-reject.", parse_mode: 'Markdown');

        // Auto-warning ke admin jika user sudah reject 2x
        if ($customer->failed_proofs_count >= 2) {
            $notif = new NotificationService();
            $notif->notifySuspiciousUser(
                $customer->telegram_id,
                $customer->username ?? 'unknown',
                "Bukti bayar di-reject {$customer->failed_proofs_count}x. Kemungkinan mencoba bukti palsu."
            );
        }

        // Notifikasi ke pelanggan
        $this->notifyCustomer(
            $order->customer->telegram_id,
            "❌ *Pembayaran Tidak Valid*\n\n"
            . "Maaf, bukti pembayaran kamu tidak dapat dikonfirmasi.\n\n"
            . "Kemungkinan penyebab:\n"
            . "• Screenshot bukan dari GoPay\n"
            . "• Nominal tidak sesuai (Rp " . number_format($order->total_amount, 0, ',', '.') . ")\n"
            . "• Pembayaran ke nomor yang salah\n\n"
            . "Kamu bisa order ulang sekarang dengan ketik /order atau klik tombol menu."
        );
    }

    private function blacklistCustomer(Nutgram $bot, string $customerId): void
    {
        $customer = NuestoreCustomer::find($customerId);

        if (!$customer) {
            $bot->sendMessage("❌ Customer tidak ditemukan.");
            return;
        }

        $customer->update([
            'is_blacklisted'   => true,
            'blacklist_reason' => 'Diblacklist oleh admin',
        ]);

        $bot->sendMessage(
            text: "🔨 *User Diblacklist!*\n\n"
                . "👤 @{$customer->username} (`{$customer->telegram_id}`)\n"
                . "User ini tidak akan bisa order lagi.",
            parse_mode: 'Markdown'
        );
    }

    private function removeOrderButtons(int $messageId): void
    {
        // Edit caption pesan bukti bayar, hapus tombol
        try {
            Http::post("https://api.telegram.org/bot" . config('nutgram.admin_bot_token') . "/editMessageReplyMarkup", [
                'chat_id'      => config('nutgram.admin_telegram_id'),
                'message_id'   => $messageId,
                'reply_markup' => json_encode(['inline_keyboard' => []]),
            ]);
        } catch (\Exception $e) {
            Log::error('removeOrderButtons failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Kirim pesan ke pelanggan via Customer Bot (TELEGRAM_TOKEN).
     */
    private function notifyCustomer(string $telegramId, string $text): void
    {
        try {
            Http::post("https://api.telegram.org/bot" . config('nutgram.token') . "/sendMessage", [
                'chat_id'                  => $telegramId,
                'text'                     => $text,
                'parse_mode'               => 'Markdown',
                'disable_web_page_preview' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('notifyCustomer failed', ['error' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────
    // Admin Panel Views
    // ─────────────────────────────────────────────

    private function isAdmin(Nutgram $bot): bool
    {
        $isAdmin = (string) $bot->userId() === (string) config('nutgram.admin_telegram_id');
        if (!$isAdmin) {
            $bot->sendMessage(text: "❌ Unauthorized.");
        }
        return $isAdmin;
    }

    private function sendDashboard(Nutgram $bot): void
    {
        $today = now()->startOfDay();

        $totalToday     = NuestoreOrder::where('created_at', '>=', $today)->count();
        $approvedToday  = NuestoreOrder::where('created_at', '>=', $today)->whereIn('status', ['APPROVED', 'PROCESSING', 'COMPLETED'])->count();
        $completedToday = NuestoreOrder::where('created_at', '>=', $today)->where('status', 'COMPLETED')->count();
        $revenueToday   = NuestoreOrder::where('created_at', '>=', $today)->whereIn('status', ['APPROVED', 'PROCESSING', 'COMPLETED'])->sum('total_amount');
        $profitToday    = NuestoreOrder::where('created_at', '>=', $today)->whereIn('status', ['APPROVED', 'PROCESSING', 'COMPLETED'])->sum('profit_estimated');
        $pendingPayment = NuestoreOrder::where('status', 'PENDING_PAYMENT')->count();
        $proofSubmitted = NuestoreOrder::where('status', 'PROOF_SUBMITTED')->count();
        $processing     = NuestoreOrder::where('status', 'PROCESSING')->count();

        $bot->sendMessage(
            text: "📊 *Dashboard Hari Ini*\n\n"
                . "📅 " . now()->format('d/m/Y') . "\n\n"
                . "👥 *Bot Pelanggan*\n"
                . "📝 Total Order: {$totalToday}\n"
                . "✅ Diapprove: {$approvedToday}\n"
                . "🎉 Selesai: {$completedToday}\n"
                . "⚙️ Diproses: {$processing}\n"
                . "📸 Menunggu Review: {$proofSubmitted}\n"
                . "💳 Belum Bayar: {$pendingPayment}\n"
                . "💰 Revenue: Rp " . number_format($revenueToday, 0, ',', '.') . "\n"
                . "📈 Est. Profit: Rp " . number_format($profitToday, 0, ',', '.'),
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🔄 Refresh',        callback_data: 'admin:dashboard'),
                    InlineKeyboardButton::make('💰 Cek Saldo',      callback_data: 'admin:balance'),
                )
                ->addRow(
                    InlineKeyboardButton::make('🕐 Lihat Antrean',  callback_data: 'admin:queued'),
                    InlineKeyboardButton::make('⏳ Pending Bayar',  callback_data: 'admin:pending'),
                )
        );
    }

    private function sendBalance(Nutgram $bot): void
    {
        $lollipop = new LollipopSmmService();
        $balance  = $lollipop->getBalance();

        if (!$balance) {
            $bot->sendMessage(text: "❌ Gagal mengambil saldo.");
            return;
        }

        $bot->sendMessage(
            text: "💰 *Saldo Lollipop SMM*\n\n"
                . "Balance: *{$balance['balance']} {$balance['currency']}*",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🔄 Refresh', callback_data: 'admin:balance'))
        );
    }

    private function sendQueued(Nutgram $bot): void
    {
        $queued = NuestoreOrder::where('status', 'APPROVED')
            ->whereNull('provider_order_id')
            ->with('customer')
            ->orderBy('created_at')
            ->get();

        if ($queued->isEmpty()) {
            $bot->sendMessage(
                text: "✅ Tidak ada pesanan dalam antrean.",
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('🔄 Refresh', callback_data: 'admin:queued'))
            );
            return;
        }

        $text = "🕐 *Pesanan Dalam Antrean ({$queued->count()})*\n\n";
        foreach ($queued as $o) {
            $text .= "🆔 `{$o->id}`\n";
            $text .= "👤 @{$o->customer->username}\n";
            $text .= "📦 {$o->service_name}\n";
            $text .= "💰 Rp " . number_format($o->total_amount, 0, ',', '.') . "\n\n";
        }

        $bot->sendMessage(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🔄 Retry Semua', callback_data: 'admin:retry_queue'),
                    InlineKeyboardButton::make('🔄 Refresh',     callback_data: 'admin:queued'),
                )
        );
    }

    private function sendPendingOrders(Nutgram $bot): void
    {
        $pending = NuestoreOrder::whereIn('status', ['PENDING_PAYMENT', 'PROOF_SUBMITTED'])
            ->with('customer')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($pending->isEmpty()) {
            $bot->sendMessage(text: "✅ Tidak ada order pelanggan yang pending.", parse_mode: 'Markdown');
            return;
        }

        $text   = "⏳ *Order Pelanggan Pending ({$pending->count()})*\n\n";
        $markup = InlineKeyboardMarkup::make();

        foreach ($pending as $o) {
            $statusEmoji = $o->status === 'PROOF_SUBMITTED' ? '📸' : '💳';
            $text .= "{$statusEmoji} `{$o->id}`\n";
            $text .= "👤 @{$o->customer->username}\n";
            $text .= "💰 Rp " . number_format($o->total_amount, 0, ',', '.') . "\n";
            $text .= "⏰ " . $o->created_at->diffForHumans() . "\n\n";

            if ($o->status === 'PROOF_SUBMITTED') {
                $markup->addRow(
                    InlineKeyboardButton::make("✅ Acc " . substr($o->id, 0, 8), callback_data: "cust_approve:{$o->id}"),
                    InlineKeyboardButton::make("❌ Reject", callback_data: "cust_reject:{$o->id}")
                );
            }
        }

        $markup->addRow(InlineKeyboardButton::make('🔄 Refresh', callback_data: 'admin:pending'));

        $bot->sendMessage(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: $markup
        );
    }

    private function retryQueue(Nutgram $bot): void
    {
        $queued = NuestoreOrder::where('status', 'APPROVED')
            ->whereNull('provider_order_id')
            ->get();

        if ($queued->isEmpty()) {
            $bot->sendMessage(text: "✅ Tidak ada pesanan dalam antrean.");
            return;
        }

        $bot->sendMessage(text: "⏳ Memproses {$queued->count()} pesanan...");

        $lollipop = new LollipopSmmService();
        $success  = 0;
        $failed   = 0;

        foreach ($queued as $order) {
            sleep(1);
            $result = $lollipop->createOrder($order->service_id, $order->target_link, $order->quantity);

            if ($result && isset($result['order'])) {
                $order->update([
                    'provider_order_id' => (string) $result['order'],
                    'status'            => 'PROCESSING',
                ]);
                $this->notifyCustomer(
                    $order->customer->telegram_id,
                    "✅ *Pembayaran Dikonfirmasi!*\n\nOrder kamu sedang diproses.\n\n📦 {$order->service_name}\n🔗 `{$order->target_link}`"
                );
                $success++;
            } else {
                $failed++;
            }
        }

        $bot->sendMessage(
            text: "✅ *Retry Selesai*\n\n✅ Berhasil: {$success}\n❌ Gagal: {$failed}",
            parse_mode: 'Markdown'
        );
    }
}