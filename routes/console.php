<?php

use App\Models\NuestoreTransaction;
use App\Services\LollipopSmmService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use SergiX44\Nutgram\Nutgram;

// Cek saldo Lollipop setiap 6 jam
Schedule::call(function () {
    $lollipop = new LollipopSmmService();
    $balance  = $lollipop->getBalance();

    if (!$balance) {
        Log::error('Cron: Gagal mengambil saldo Lollipop');
        return;
    }

    $saldo = (float) $balance['balance'];

    Log::info('Cron: Saldo Lollipop', ['balance' => $saldo]);

    // Alert kalau saldo di bawah 50000
    if ($saldo < 50000) {
        $bot = app(Nutgram::class);
        $bot->sendMessage(
            text: "⚠️ *ALERT: Saldo Lollipop Menipis!*\n\n" .
                  "💰 Saldo saat ini: {$balance['balance']} {$balance['currency']}\n\n" .
                  "Segera deposit agar pesanan tidak terhambat.",
            parse_mode: 'Markdown',
            chat_id: config('nutgram.admin_telegram_id')
        );
    }
})->everySixHours()->name('check-lollipop-balance');

// Sync status pesanan PROCESSING setiap 15 menit
Schedule::call(function () {
    $processing = NuestoreTransaction::where('status', 'PROCESSING')
        ->whereNotNull('provider_order_id')
        ->get();

    if ($processing->isEmpty()) {
        return;
    }

    $lollipop = new LollipopSmmService();
    $bot      = app(Nutgram::class);

    foreach ($processing as $transaction) {
        sleep(1); // Hindari rate limit

        $result = $lollipop->getStatus($transaction->provider_order_id);

        if (!$result) {
            Log::warning('Cron: Gagal cek status', ['order_id' => $transaction->id]);
            continue;
        }

        Log::info('Cron: Status pesanan', [
            'order_id' => $transaction->id,
            'status'   => $result['status'],
        ]);

        if (strtolower($result['status']) === 'completed') {
            $transaction->update([
                'status'        => 'COMPLETED',
                'profit_actual' => $transaction->amount_paid - $transaction->modal_cost - $transaction->pg_fee_estimated,
            ]);

            // Notif ke user
            $bot->sendMessage(
                text: "🎉 *Pesanan Selesai!*\n\n" .
                      "🆔 Order ID: `{$transaction->id}`\n" .
                      "📦 Service: {$transaction->service_id}\n" .
                      "🔗 Target: {$transaction->target_link}\n\n" .
                      "Terima kasih telah menggunakan Nuestore SMM! 🙏",
                parse_mode: 'Markdown',
                chat_id: $transaction->user->telegram_id
            );
        } elseif (in_array(strtolower($result['status']), ['canceled', 'failed'])) {
            $transaction->update(['status' => 'FAILED_PROVIDER']);

            // Notif ke user
            $bot->sendMessage(
                text: "❌ *Pesanan Gagal*\n\n" .
                      "🆔 Order ID: `{$transaction->id}`\n\n" .
                      "Mohon hubungi admin untuk refund.",
                parse_mode: 'Markdown',
                chat_id: $transaction->user->telegram_id
            );

            // Notif ke admin
            $bot->sendMessage(
                text: "🚨 *Pesanan Gagal di Provider!*\n\n" .
                      "🆔 Order ID: `{$transaction->id}`\n" .
                      "📦 Service: {$transaction->service_id}\n" .
                      "💰 Rp " . number_format($transaction->amount_paid, 0, ',', '.') . "\n\n" .
                      "User mungkin butuh refund.",
                parse_mode: 'Markdown',
                chat_id: config('nutgram.admin_telegram_id')
            );
        }
    }
})->everyFifteenMinutes()->name('sync-order-status');