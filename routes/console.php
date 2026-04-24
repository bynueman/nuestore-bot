<?php

use App\Models\NuestoreOrder;
use App\Services\LollipopSmmService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

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

    if ($saldo < 50000) {
        Http::post("https://api.telegram.org/bot" . config('nutgram.admin_bot_token') . "/sendMessage", [
            'chat_id'    => config('nutgram.admin_telegram_id'),
            'text'       => "⚠️ *ALERT: Saldo Lollipop Menipis!*\n\n💰 Saldo saat ini: {$balance['balance']} {$balance['currency']}\n\nSegera deposit agar pesanan tidak terhambat.",
            'parse_mode' => 'Markdown',
        ]);
    }
})->everySixHours()->name('check-lollipop-balance');

// Sync status pesanan PROCESSING setiap 15 menit
Schedule::call(function () {
    $processing = NuestoreOrder::where('status', 'PROCESSING')
        ->whereNotNull('provider_order_id')
        ->with('customer')
        ->get();

    if ($processing->isEmpty()) return;

    $lollipop   = new LollipopSmmService();
    $adminToken = config('nutgram.admin_bot_token');
    $adminId    = config('nutgram.admin_telegram_id');
    $custToken  = config('nutgram.token');

    foreach ($processing as $order) {
        sleep(1); // Hindari rate limit

        $result = $lollipop->getStatus($order->provider_order_id);
        if (!$result) {
            Log::warning('Cron: Gagal cek status', ['order_id' => $order->id]);
            continue;
        }

        Log::info('Cron: Status pesanan', ['order_id' => $order->id, 'status' => $result['status']]);

        if (strtolower($result['status']) === 'completed') {
            $order->update(['status' => 'COMPLETED']);

            // Notif ke pelanggan via Customer Bot
            Http::post("https://api.telegram.org/bot{$custToken}/sendMessage", [
                'chat_id'    => $order->customer->telegram_id,
                'text'       => "🎉 *Pesanan Selesai!*\n\n"
                              . "📦 {$order->service_name}\n"
                              . "🔗 `{$order->target_link}`\n"
                              . "🔢 " . number_format($order->quantity, 0, ',', '.') . "\n\n"
                              . "Terima kasih sudah berbelanja di Nuestore! 🛍️",
                'parse_mode' => 'Markdown',
            ]);

            // Notif ke admin
            Http::post("https://api.telegram.org/bot{$adminToken}/sendMessage", [
                'chat_id'    => $adminId,
                'text'       => "🎉 *Pesanan Selesai!*\n\n"
                              . "🆔 `{$order->id}`\n"
                              . "📦 {$order->service_name}\n"
                              . "💰 Rp " . number_format($order->total_amount, 0, ',', '.') . "\n"
                              . "📈 Est. Profit: Rp " . number_format($order->profit_estimated, 0, ',', '.'),
                'parse_mode' => 'Markdown',
            ]);

        } elseif (in_array(strtolower($result['status']), ['canceled', 'failed'])) {
            $order->update(['status' => 'FAILED_PROVIDER']);

            Http::post("https://api.telegram.org/bot{$adminToken}/sendMessage", [
                'chat_id'    => $adminId,
                'text'       => "🚨 *Pesanan Gagal di Provider!*\n\n"
                              . "🆔 `{$order->id}`\n"
                              . "📦 {$order->service_name}\n"
                              . "🔗 `{$order->target_link}`\n\n"
                              . "Status Provider: *{$result['status']}*",
                'parse_mode' => 'Markdown',
            ]);
        }
    }
})->everyFifteenMinutes()->name('sync-order-status');

// Auto-expire pesanan pelanggan yang tidak dibayar dalam 15 menit
Schedule::command('orders:expire')->everyMinute()->name('expire-customer-orders');
