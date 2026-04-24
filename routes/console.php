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
    $adminId  = config('nutgram.admin_telegram_id');

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
            $profitActual = $transaction->amount_paid - $transaction->modal_cost;

            $transaction->update([
                'status'        => 'COMPLETED',
                'profit_actual' => $profitActual,
            ]);

            $profitFormat = number_format($profitActual, 0, ',', '.');
            $tagiFormat   = number_format($transaction->amount_paid, 0, ',', '.');

            // Notif ke admin
            $bot->sendMessage(
                text: "🎉 *Pesanan Selesai!*\n\n"
                    . "🆔 Order ID: `{$transaction->id}`\n"
                    . "📦 Service: {$transaction->service_id}\n"
                    . "🔗 Target: {$transaction->target_link}\n"
                    . ($transaction->customer_note ? "📝 Catatan: {$transaction->customer_note}\n" : '')
                    . "\n💰 Tagih: Rp {$tagiFormat}\n"
                    . "📈 Profit: Rp {$profitFormat}",
                parse_mode: 'Markdown',
                chat_id: $adminId
            );

        } elseif (in_array(strtolower($result['status']), ['canceled', 'failed'])) {
            $transaction->update(['status' => 'FAILED_PROVIDER']);

            $tagiFormat = number_format($transaction->amount_paid, 0, ',', '.');

            // Notif ke admin
            $bot->sendMessage(
                text: "🚨 *Pesanan Gagal di Provider!*\n\n"
                    . "🆔 Order ID: `{$transaction->id}`\n"
                    . "📦 Service: {$transaction->service_id}\n"
                    . "🔗 Target: {$transaction->target_link}\n"
                    . ($transaction->customer_note ? "📝 Catatan: {$transaction->customer_note}\n" : '')
                    . "\n💰 Tagih: Rp {$tagiFormat}\n\n"
                    . "Status Provider: *{$result['status']}*",
                parse_mode: 'Markdown',
                chat_id: $adminId
            );
        }
    }
})->everyFifteenMinutes()->name('sync-order-status');

// Auto-expire pesanan pelanggan yang tidak dibayar dalam 15 menit
Schedule::command('orders:expire')->everyMinute()->name('expire-customer-orders');