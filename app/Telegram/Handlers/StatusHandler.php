<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreTransaction;
use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Nutgram;

class StatusHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $text    = $bot->message()->text;
        $parts   = explode(' ', $text, 2);
        $orderId = trim($parts[1] ?? '');

        if (!$orderId) {
            $bot->sendMessage(
                text: "❌ Format salah. Gunakan:\n`/status [Order ID]`\n\nContoh:\n`/status uuid-order-id`\n\nAtau ketik 📋 *Cek Status* di menu, lalu kirimkan Order ID.",
                parse_mode: 'Markdown'
            );
            return;
        }

        // Private admin bot: tidak perlu filter by user_id
        $transaction = NuestoreTransaction::where('id', $orderId)->first();

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
}