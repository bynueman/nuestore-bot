<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreTransaction;
use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Nutgram;

class AdminHandler
{
    private function isAdmin(Nutgram $bot): bool
    {
        return (string) $bot->userId() === (string) config('nutgram.admin_telegram_id');
    }

    public function retryQueue(Nutgram $bot): void
    {
        if (!$this->isAdmin($bot)) {
            $bot->sendMessage(text: "❌ Unauthorized.");
            return;
        }

        $queued = NuestoreTransaction::where('status', 'PAID_QUEUED')
            ->where('retry_count', '<', 5)
            ->get();

        if ($queued->isEmpty()) {
            $bot->sendMessage(text: "✅ Tidak ada pesanan dalam antrean.");
            return;
        }

        $bot->sendMessage(text: "⏳ Memproses {$queued->count()} pesanan dalam antrean...");

        $lollipop = new LollipopSmmService();
        $success  = 0;
        $failed   = 0;

        foreach ($queued as $transaction) {
            sleep(1); // Hindari rate limit

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
                $errorLog = json_encode($result);
                $transaction->update(['retry_error_log' => $errorLog]);
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

    public function checkBalance(Nutgram $bot): void
    {
        if (!$this->isAdmin($bot)) {
            $bot->sendMessage(text: "❌ Unauthorized.");
            return;
        }

        $lollipop = new LollipopSmmService();
        $balance  = $lollipop->getBalance();

        if (!$balance) {
            $bot->sendMessage(text: "❌ Gagal mengambil saldo.");
            return;
        }

        $bot->sendMessage(
            text: "💰 *Saldo Lollipop SMM*\n\n" .
                  "Balance: {$balance['balance']}\n" .
                  "Currency: {$balance['currency']}",
            parse_mode: 'Markdown'
        );
    }

    public function listQueued(Nutgram $bot): void
    {
        if (!$this->isAdmin($bot)) {
            $bot->sendMessage(text: "❌ Unauthorized.");
            return;
        }

        $queued = NuestoreTransaction::where('status', 'PAID_QUEUED')->get();

        if ($queued->isEmpty()) {
            $bot->sendMessage(text: "✅ Tidak ada pesanan dalam antrean.");
            return;
        }

        $text = "📋 *Pesanan Dalam Antrean ({$queued->count()})*\n\n";

        foreach ($queued as $t) {
            $text .= "🆔 `{$t->id}`\n";
            $text .= "📦 Service: {$t->service_id}\n";
            $text .= "🔄 Retry: {$t->retry_count}x\n";
            $text .= "💰 Rp " . number_format($t->amount_paid, 0, ',', '.') . "\n\n";
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown');
    }
}