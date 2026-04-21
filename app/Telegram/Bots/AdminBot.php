<?php

namespace App\Telegram\Bots;

use App\Models\NuestoreTransaction;
use App\Services\LollipopSmmService;
use App\Telegram\Handlers\Admin\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class AdminBot
{
    private Nutgram $bot;

    public function __construct()
    {
        $this->bot = new Nutgram(config('nutgram.admin_bot_token'));
    }

    public function register(): void
    {
        $bot = $this->bot;

        // /start
        $bot->onCommand('start', function (Nutgram $bot) {
            $adminId = (string) config('nutgram.admin_telegram_id');
            if ((string) $bot->userId() !== $adminId) {
                $bot->sendMessage(text: "❌ Unauthorized.");
                return;
            }

            $bot->sendMessage(
                text: "👨‍💼 *Nuestore Admin Panel*\n\n" .
                      "Selamat datang, Admin!\n\n" .
                      "📋 *Menu:*\n" .
                      "/dashboard — Ringkasan hari ini\n" .
                      "/queued — Pesanan dalam antrean\n" .
                      "/retry — Proses ulang antrean\n" .
                      "/balance — Cek saldo Lollipop\n" .
                      "/pending — Pesanan belum bayar\n",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('📊 Dashboard',    callback_data: 'admin:dashboard'),
                        InlineKeyboardButton::make('💰 Cek Saldo',    callback_data: 'admin:balance'),
                    )
                    ->addRow(
                        InlineKeyboardButton::make('🕐 Antrean',      callback_data: 'admin:queued'),
                        InlineKeyboardButton::make('🔄 Retry Queue',  callback_data: 'admin:retry_queue'),
                    )
            );
        });

        // /dashboard
        $bot->onCommand('dashboard', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->sendDashboard($bot);
        });

        // /queued
        $bot->onCommand('queued', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->sendQueued($bot);
        });

        // /retry
        $bot->onCommand('retry', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->retryQueue($bot);
        });

        // /balance
        $bot->onCommand('balance', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $this->sendBalance($bot);
        });

        // /pending
        $bot->onCommand('pending', function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) return;
            $pending = NuestoreTransaction::where('status', 'UNPAID')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            if ($pending->isEmpty()) {
                $bot->sendMessage(text: "✅ Tidak ada pesanan pending.");
                return;
            }

            $text = "⏳ *Pesanan Belum Bayar ({$pending->count()})*\n\n";
            foreach ($pending as $t) {
                $text .= "🆔 `{$t->id}`\n";
                $text .= "📦 Service: {$t->service_id}\n";
                $text .= "💰 Rp " . number_format($t->amount_paid, 0, ',', '.') . "\n";
                $text .= "🕐 " . $t->created_at->diffForHumans() . "\n\n";
            }

            $bot->sendMessage(text: $text, parse_mode: 'Markdown');
        });

        // Handle callback query
        $bot->onCallbackQuery(function (Nutgram $bot) {
            if (!$this->isAdmin($bot)) {
                $bot->answerCallbackQuery(text: "Unauthorized");
                return;
            }

            $data = $bot->callbackQuery()?->data;
            $bot->answerCallbackQuery();

            match ($data) {
                'admin:dashboard'   => $this->sendDashboard($bot),
                'admin:balance'     => $this->sendBalance($bot),
                'admin:queued'      => $this->sendQueued($bot),
                'admin:retry_queue' => $this->retryQueue($bot),
                default             => null,
            };
        });
    }

    public function run(): void
    {
        $this->register();
        $this->bot->run();
    }

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

        $totalToday    = NuestoreTransaction::where('created_at', '>=', $today)->count();
        $paidToday     = NuestoreTransaction::where('created_at', '>=', $today)->where('status', '!=', 'UNPAID')->count();
        $completedToday= NuestoreTransaction::where('created_at', '>=', $today)->where('status', 'COMPLETED')->count();
        $revenueToday  = NuestoreTransaction::where('created_at', '>=', $today)->where('status', '!=', 'UNPAID')->sum('amount_paid');
        $profitToday   = NuestoreTransaction::where('created_at', '>=', $today)->where('status', '!=', 'UNPAID')->sum('profit_estimated');
        $queued        = NuestoreTransaction::where('status', 'PAID_QUEUED')->count();
        $processing    = NuestoreTransaction::where('status', 'PROCESSING')->count();

        $bot->sendMessage(
            text: "📊 *Dashboard Hari Ini*\n\n" .
                  "📅 " . now()->format('d/m/Y') . "\n\n" .
                  "📝 Total Order: {$totalToday}\n" .
                  "✅ Dibayar: {$paidToday}\n" .
                  "🎉 Selesai: {$completedToday}\n" .
                  "🕐 Antrean: {$queued}\n" .
                  "⚙️ Diproses: {$processing}\n\n" .
                  "💰 Revenue: Rp " . number_format($revenueToday, 0, ',', '.') . "\n" .
                  "📈 Est. Profit: Rp " . number_format($profitToday, 0, ',', '.'),
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('🔄 Refresh',      callback_data: 'admin:dashboard'),
                    InlineKeyboardButton::make('💰 Cek Saldo',    callback_data: 'admin:balance'),
                )
                ->addRow(
                    InlineKeyboardButton::make('🕐 Lihat Antrean', callback_data: 'admin:queued'),
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
            text: "💰 *Saldo Lollipop SMM*\n\n" .
                  "Balance: *{$balance['balance']} {$balance['currency']}*",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🔄 Refresh', callback_data: 'admin:balance'))
        );
    }

    private function sendQueued(Nutgram $bot): void
    {
        $queued = NuestoreTransaction::where('status', 'PAID_QUEUED')
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
        foreach ($queued as $t) {
            $text .= "🆔 `{$t->id}`\n";
            $text .= "📦 Service: {$t->service_id}\n";
            $text .= "🔄 Retry: {$t->retry_count}x\n";
            $text .= "💰 Rp " . number_format($t->amount_paid, 0, ',', '.') . "\n\n";
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

    private function retryQueue(Nutgram $bot): void
    {
        $queued = NuestoreTransaction::where('status', 'PAID_QUEUED')
            ->where('retry_count', '<', 5)
            ->get();

        if ($queued->isEmpty()) {
            $bot->sendMessage(text: "✅ Tidak ada pesanan dalam antrean.");
            return;
        }

        $bot->sendMessage(text: "⏳ Memproses {$queued->count()} pesanan...");

        $lollipop = new LollipopSmmService();
        $success  = 0;
        $failed   = 0;

        foreach ($queued as $transaction) {
            sleep(1);

            $result = $lollipop->createOrder(
                $transaction->service_id,
                $transaction->target_link,
                null
            );

            $transaction->increment('retry_count');
            $transaction->update(['last_retried_at' => now()]);

            if ($result && isset($result['order'])) {
                $transaction->update([
                    'provider_order_id' => $result['order'],
                    'status'            => 'PROCESSING',
                ]);
                $success++;
            } else {
                $transaction->update(['retry_error_log' => json_encode($result)]);
                $failed++;
            }
        }

        $bot->sendMessage(
            text: "✅ *Retry Selesai*\n\n" .
                  "✅ Berhasil: {$success}\n" .
                  "❌ Gagal: {$failed}",
            parse_mode: 'Markdown'
        );
    }
}