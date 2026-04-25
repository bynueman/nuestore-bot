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

            $pendingCount = NuestoreOrder::where('status', 'PROOF_SUBMITTED')->count();
            $processing   = NuestoreOrder::where('status', 'PROCESSING')->count();

            $bot->sendMessage(
                text: "🤴 *NUESTORE ADMIN PANEL*\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                    . "Selamat datang kembali, Boss! 👋\n\n"
                    . "📊 *Status Saat Ini:*\n"
                    . "📸 Menunggu Review: *{$pendingCount} Order*\n"
                    . "⚙️ Sedang Diproses: *{$processing} Order*\n\n"
                    . "Gunakan menu di bawah untuk mengelola bot:",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('📊 Dashboard',   callback_data: 'admin:dashboard'),
                        InlineKeyboardButton::make('💰 Saldo SMM',   callback_data: 'admin:balance'),
                    )
                    ->addRow(
                        InlineKeyboardButton::make('⏳ Menunggu ACC (' . $pendingCount . ')', callback_data: 'admin:pending'),
                    )
                    ->addRow(
                        InlineKeyboardButton::make('🕐 Antrean Provider', callback_data: 'admin:queued'),
                        InlineKeyboardButton::make('🔄 Retry Gagal', callback_data: 'admin:retry_queue'),
                    )
            );

            // Menu Bawah (Reply Keyboard)
            $bot->sendMessage(
                text: "⌨️ *Menu Navigasi:*",
                parse_mode: 'Markdown',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup::make(
                    resize_keyboard: true,
                    one_time_keyboard: false
                )->addRow(
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton::make('📊 Dashboard'),
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton::make('💰 Saldo SMM')
                )->addRow(
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton::make('⏳ Order Pending'),
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton::make('🕐 Sedang Proses')
                )->addRow(
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton::make('📊 Laporan'),
                    \SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton::make('🔨 Blacklist Manual')
                )
            );
        });

        // Register Reply Keyboard Handlers
        $bot->onText('📊 Dashboard', function (Nutgram $bot) { $this->sendDashboard($bot); });
        $bot->onText('💰 Saldo SMM',   function (Nutgram $bot) { $this->sendBalance($bot); });
        $bot->onText('⏳ Order Pending', function (Nutgram $bot) { $this->sendPendingOrders($bot); });
        $bot->onText('🕐 Sedang Proses', function (Nutgram $bot) { $this->sendProcessingOrders($bot); });
        $bot->onText('📊 Laporan',       function (Nutgram $bot) { $this->sendReports($bot); });
        $bot->onText('🔨 Blacklist Manual', function (Nutgram $bot) {
            $bot->sendMessage(
                text: "🔨 *Manajemen Blacklist*\n\n"
                    . "• Blokir: `/blacklist_id [ID]`\n"
                    . "• Buka Blokir: `/unblacklist_id [ID]`\n\n"
                    . "Contoh: `/blacklist_id 123456789`",
                parse_mode: 'Markdown'
            );
        });

        // Handle direct blacklist command
        $bot->onCommand('blacklist_id {id}', function (Nutgram $bot, $id) {
            if (!$this->isAdmin($bot)) return;
            $this->blacklistCustomer($bot, $id, true);
        });

        // Handle direct unblacklist command
        $bot->onCommand('unblacklist_id {id}', function (Nutgram $bot, $id) {
            if (!$this->isAdmin($bot)) return;
            $this->unblacklistCustomer($bot, $id);
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

        $bot->onCommand('blacklist', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $bot->sendMessage(
                text: "🔨 *Mode Blacklist Manual*\n\nBalas pesan ini dengan **ID Telegram** user yang ingin diblokir.",
                parse_mode: 'Markdown'
            );
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
                $data === 'admin:reports'     => $this->sendReports($bot),
                $data === 'admin:processing'  => $this->sendProcessingOrders($bot),

                // ── Customer order callbacks ──
                str_starts_with($data, 'cust_approve:')   => $this->approveOrder($bot, substr($data, 13)),
                str_starts_with($data, 'cust_reject:')    => $this->rejectOrder($bot, substr($data, 12)),
                str_starts_with($data, 'cust_blacklist:') => $this->blacklistCustomer($bot, substr($data, 15)),
                str_starts_with($data, 'cust_show_proof:') => $this->showProof($bot, substr($data, 16)),

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
                "Bukti bayar di-reject {$customer->failed_proofs_count}x. Kemungkinan mencoba bukti palsu.",
                $customer->id
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

    private function blacklistCustomer(Nutgram $bot, string $id, bool $byTelegramId = false): void
    {
        $customer = $byTelegramId 
            ? NuestoreCustomer::where('telegram_id', $id)->first() 
            : NuestoreCustomer::find($id);

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

    private function unblacklistCustomer(Nutgram $bot, string $telegramId): void
    {
        $customer = NuestoreCustomer::where('telegram_id', $telegramId)->first();

        if (!$customer) {
            $bot->sendMessage("❌ Customer dengan ID `{$telegramId}` tidak ditemukan.");
            return;
        }

        $customer->update([
            'is_blacklisted'   => false,
            'blacklist_reason' => null,
        ]);

        $bot->sendMessage(
            text: "✅ *Akses Dipulihkan!*\n\n"
                . "👤 @{$customer->username} (`{$customer->telegram_id}`)\n"
                . "User ini sekarang sudah bisa order lagi.",
            parse_mode: 'Markdown'
        );
    }

    private function sendProcessingOrders(Nutgram $bot): void
    {
        if (!$this->isAdmin($bot)) return;

        $orders = NuestoreOrder::where('status', 'PROCESSING')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        if ($orders->isEmpty()) {
            $bot->sendMessage("✅ Tidak ada pesanan yang sedang diproses.");
            return;
        }

        $text = "🕐 *DAFTAR PESANAN SEDANG PROSES*\n"
              . "_(Menampilkan 10 data terbaru)_\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        foreach ($orders as $order) {
            $text .= "🆔 `{$order->id}`\n"
                  . "👤 " . ($order->customer?->username ? "@".$order->customer->username : $order->customer_id) . "\n"
                  . "📦 {$order->service_name}\n"
                  . "🔗 [Target Link]({$order->target_link})\n"
                  . "🔢 Jumlah: *{$order->quantity}*\n"
                  . "🕒 Update: *{$order->updated_at->format('H:i:s')}*\n"
                  . "━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        }

        $bot->sendMessage(
            text: $text,
            parse_mode: 'Markdown',
            disable_web_page_preview: true,
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🔄 Refresh List', callback_data: 'admin:processing'))
        );
    }

    private function sendReports(Nutgram $bot): void
    {
        if (!$this->isAdmin($bot)) return;

        // --- STATS MINGGUAN (7 HARI TERAKHIR) ---
        $weeklyOrders = NuestoreOrder::where('created_at', '>=', now()->subDays(7))
            ->where('status', 'COMPLETED')
            ->get();
        
        $weeklyCount   = $weeklyOrders->count();
        $weeklyRevenue = $weeklyOrders->sum('total_amount');
        $weeklyProfit  = $weeklyOrders->sum('profit_estimated');

        // --- STATS BULANAN (BULAN BERJALAN) ---
        $monthlyOrders = NuestoreOrder::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'COMPLETED')
            ->get();
            
        $monthlyCount   = $monthlyOrders->count();
        $monthlyRevenue = $monthlyOrders->sum('total_amount');
        $monthlyProfit  = $monthlyOrders->sum('profit_estimated');

        // --- SUCCESS RATE ---
        $totalAttempt = NuestoreOrder::whereMonth('created_at', now()->month)->count();
        $successRate  = $totalAttempt > 0 ? round(($monthlyCount / $totalAttempt) * 100, 1) : 0;

        $text = "📊 *LAPORAN TRANSAKSI NUESTORE*\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━\n\n"
              . "🗓 *7 HARI TERAKHIR:*\n"
              . "📦 Pesanan Sukses: *{$weeklyCount}*\n"
              . "💰 Total Omzet: *Rp " . number_format($weeklyRevenue, 0, ',', '.') . "*\n"
              . "📈 Est. Profit: *Rp " . number_format($weeklyProfit, 0, ',', '.') . "*\n\n"
              . "📅 *BULAN INI (" . now()->format('F') . "):*\n"
              . "📦 Pesanan Sukses: *{$monthlyCount}*\n"
              . "💰 Total Omzet: *Rp " . number_format($monthlyRevenue, 0, ',', '.') . "*\n"
              . "📈 Est. Profit: *Rp " . number_format($monthlyProfit, 0, ',', '.') . "*\n"
              . "✅ Success Rate: *{$successRate}%*\n\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━\n"
              . "_Data dihitung hanya dari pesanan COMPLETED._";

        $bot->sendMessage(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🔄 Refresh Laporan', callback_data: 'admin:reports'))
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
                    InlineKeyboardButton::make("🖼️ Lihat Bukti", callback_data: "cust_show_proof:{$o->id}"),
                    InlineKeyboardButton::make("✅ Acc",         callback_data: "cust_approve:{$o->id}")
                );
                $markup->addRow(
                    InlineKeyboardButton::make("❌ Reject",      callback_data: "cust_reject:{$o->id}")
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

    private function showProof(Nutgram $bot, string $orderId): void
    {
        $order = NuestoreOrder::find($orderId);
        if (!$order || !$order->proof_file_id) {
            $bot->answerCallbackQuery(text: "❌ Bukti tidak ditemukan.");
            return;
        }

        $bot->answerCallbackQuery(text: "⌛ Mengambil foto...");

        // --- FIXED: Cross-bot photo sending (Download & Send) ---
        $custToken = config('nutgram.token');
        $tempPath  = storage_path("app/public/view_proof_{$orderId}.jpg");
        $fileId    = $order->proof_file_id;
        
        try {
            $fileInfo = Http::get("https://api.telegram.org/bot{$custToken}/getFile", ['file_id' => $fileId])->json();
            if (isset($fileInfo['result']['file_path'])) {
                $remotePath = "https://api.telegram.org/file/bot{$custToken}/" . $fileInfo['result']['file_path'];
                $imageContent = @file_get_contents($remotePath);
                if ($imageContent) {
                    file_put_contents($tempPath, $imageContent);
                }
            }
        } catch (\Exception $e) {
            Log::error('showProof download failed', ['error' => $e->getMessage()]);
        }

        if (file_exists($tempPath)) {
            $caption = "📸 *Bukti Bayar: {$order->id}*\n"
                     . "👤 @{$order->customer->username}\n"
                     . "💰 Rp " . number_format($order->total_amount, 0, ',', '.');

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make("✅ Approve", callback_data: "cust_approve:{$order->id}"),
                    InlineKeyboardButton::make("❌ Reject",  callback_data: "cust_reject:{$order->id}")
                );

            // Kirim via CURL untuk keandalan upload
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . config('nutgram.admin_bot_token') . "/sendPhoto");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'chat_id'    => config('nutgram.admin_telegram_id'),
                'photo'      => new \CURLFile($tempPath),
                'caption'    => $caption,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard->inline_keyboard])
            ]);
            curl_exec($ch);
            curl_close($ch);

            unlink($tempPath);
        } else {
            $bot->sendMessage("❌ Gagal memuat foto bukti. Silakan cek manual atau hubungi pelanggan.");
        }
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