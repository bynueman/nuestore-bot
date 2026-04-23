<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreTransaction;
use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Nutgram;

class StatusHandler
{
    public function __invoke(Nutgram $bot, ?string $orderId = null): void
    {
        if (!$orderId) {
            $text    = $bot->message()?->text ?? '';
            $parts   = explode(' ', $text, 2);
            $orderId = trim($parts[1] ?? '');
        }

        if (!$orderId) {
            $this->showActiveOrders($bot);
            return;
        }

        // Private admin bot: bisa cari pakai UUID lokal maupun ID Lollipop (provider_order_id)
        $transaction = NuestoreTransaction::where('id', $orderId)
            ->orWhere('provider_order_id', $orderId)
            ->first();

        if (!$transaction) {
            $bot->sendMessage(text: "❌ Order tidak ditemukan. Periksa kembali Order ID-nya.");
            return;
        }

        $statusEmoji = match($transaction->status) {
            'PROCESSING'      => '⚙️',
            'COMPLETED'       => '✅',
            'FAILED_PROVIDER' => '❌',
            'CANCELED'        => '🚫',
            default           => '❓',
        };

        $statusText = match($transaction->status) {
            'PROCESSING'      => 'Sedang Diproses',
            'COMPLETED'       => 'Selesai',
            'FAILED_PROVIDER' => 'Gagal (Provider)',
            'CANCELED'        => 'Dibatalkan',
            default           => $transaction->status,
        };

        $totalFormatted  = number_format($transaction->amount_paid, 0, ',', '.');
        $modalFormatted  = number_format($transaction->modal_cost, 0, ',', '.');
        $profitEstFormat = number_format($transaction->profit_estimated ?? 0, 0, ',', '.');

        $noteText = $transaction->customer_note
            ? "\n📝 Catatan: {$transaction->customer_note}"
            : '';

        $text = "📋 *Detail Pesanan*\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━\n"
              . "🆔 Order ID: `{$transaction->id}`\n"
              . "📦 Service ID: {$transaction->service_id}\n"
              . "🔗 Target: {$transaction->target_link}\n"
              . "🔢 Qty: " . number_format($transaction->quantity ?? 0, 0, ',', '.') . "\n"
              . $noteText . "\n\n"
              . "💰 Tagih ke pelanggan: Rp {$totalFormatted}\n"
              . "📦 Modal: Rp {$modalFormatted}\n"
              . "📈 Est. Profit: Rp {$profitEstFormat}\n\n"
              . "{$statusEmoji} Status: *{$statusText}*\n"
              . "📅 Dibuat: {$transaction->created_at->format('d/m/Y H:i')}";

        // Kalau masih PROCESSING, query live ke Lollipop
        if ($transaction->provider_order_id && $transaction->status === 'PROCESSING') {
            $lollipop       = new LollipopSmmService();
            $providerStatus = $lollipop->getStatus($transaction->provider_order_id);

            if ($providerStatus) {
                $text .= "\n\n⚙️ *Status Provider (Live):*\n";
                $text .= "Status: *{$providerStatus['status']}*\n";

                if (isset($providerStatus['start_count'])) {
                    $text .= "🔢 Start count: {$providerStatus['start_count']}\n";
                }
                if (isset($providerStatus['remains'])) {
                    $text .= "📊 Sisa: {$providerStatus['remains']}";
                }
            }
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown');
    }

    private function showActiveOrders(Nutgram $bot): void
    {
        $bot->sendMessage(text: "⏳ Mencari transaksi yang sedang diproses...", parse_mode: 'Markdown');

        $activeOrders = NuestoreTransaction::where('status', 'PROCESSING')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        if ($activeOrders->isEmpty()) {
            $bot->sendMessage(text: "🎉 Tidak ada transaksi yang sedang diproses. Semua selesai!");
            return;
        }

        $lollipop = new LollipopSmmService();
        $providerIds = $activeOrders->pluck('provider_order_id')->filter()->toArray();
        $liveStatuses = [];

        if (!empty($providerIds)) {
            $response = $lollipop->getStatuses($providerIds);
            if ($response && is_array($response)) {
                $liveStatuses = $response;
            }
        }

        $text = "⏳ *TRANSAKSI SEDANG DIPROSES*\n━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        foreach ($activeOrders as $idx => $order) {
            $num = $idx + 1;
            $pid = $order->provider_order_id ?? '-';
            // Limit link length
            $link = strlen($order->target_link) > 30 ? substr($order->target_link, 0, 30) . '...' : $order->target_link;
            
            $text .= "{$num}. *ID:* `{$order->id}`\n";
            $text .= "   🔗 Target: {$link}\n";
            $text .= "   📦 Service: {$order->service_id} | Qty: {$order->quantity}\n";

            if ($pid !== '-' && isset($liveStatuses[$pid])) {
                $live = $liveStatuses[$pid];
                $statusString = $live['status'] ?? 'PROCESSING';
                $remains = $live['remains'] ?? '?';
                $start   = $live['start_count'] ?? '?';
                $text .= "   📊 *Provider ID:* {$pid} (Status: {$statusString})\n";
                $text .= "   🔢 Start: {$start} | Sisa: {$remains}\n";
            } else {
                $text .= "   📊 *Provider ID:* {$pid}\n";
            }
            $text .= "\n";
        }

        $text .= "_(Menampilkan maks 15 order terbaru yang sedang proses)_\n";
        $text .= "Untuk cek detail satu order, ketik:\n`/status [Order ID]`";

        $bot->sendMessage(text: $text, parse_mode: 'Markdown');
    }
}