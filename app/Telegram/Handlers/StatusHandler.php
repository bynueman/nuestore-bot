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
        $orderId = $parts[1] ?? null;

        if (!$orderId) {
            $bot->sendMessage(
                text: "❌ Format salah. Gunakan:\n`/status [Order ID]`\n\nContoh:\n`/status uuid-order-id`",
                parse_mode: 'Markdown'
            );
            return;
        }

        $transaction = NuestoreTransaction::where('id', $orderId)
            ->where('user_id', function ($query) use ($bot) {
                $query->select('id')
                    ->from('nuestore_users')
                    ->where('telegram_id', $bot->userId());
            })
            ->first();

        if (!$transaction) {
            $bot->sendMessage(text: "❌ Order tidak ditemukan atau bukan milik Anda.");
            return;
        }

        $statusEmoji = match($transaction->status) {
            'UNPAID'           => '⏳',
            'PAID_QUEUED'      => '🕐',
            'PROCESSING'       => '⚙️',
            'COMPLETED'        => '✅',
            'FAILED_PG'        => '❌',
            'FAILED_PROVIDER'  => '❌',
            'REFUND_REQUESTED' => '🔄',
            'DISPUTED'         => '⚠️',
            'CANCELED'         => '🚫',
            default            => '❓',
        };

        $statusText = match($transaction->status) {
            'UNPAID'           => 'Belum Dibayar',
            'PAID_QUEUED'      => 'Dibayar - Dalam Antrean',
            'PROCESSING'       => 'Sedang Diproses',
            'COMPLETED'        => 'Selesai',
            'FAILED_PG'        => 'Gagal (Payment)',
            'FAILED_PROVIDER'  => 'Gagal (Provider)',
            'REFUND_REQUESTED' => 'Refund Diminta',
            'DISPUTED'         => 'Dalam Sengketa',
            'CANCELED'         => 'Dibatalkan',
            default            => 'Unknown',
        };

        $totalFormatted = number_format($transaction->amount_paid, 0, ',', '.');

        $text = "📋 *Detail Pesanan*\n\n" .
                "🆔 Order ID: `{$transaction->id}`\n" .
                "📦 Service ID: {$transaction->service_id}\n" .
                "🔗 Target: {$transaction->target_link}\n" .
                "💰 Total: Rp {$totalFormatted}\n" .
                "{$statusEmoji} Status: {$statusText}\n" .
                "📅 Tanggal: {$transaction->created_at->format('d/m/Y H:i')}";

        if ($transaction->provider_order_id && $transaction->status === 'PROCESSING') {
            $lollipop       = new LollipopSmmService();
            $providerStatus = $lollipop->getStatus($transaction->provider_order_id);

            if ($providerStatus) {
                $text .= "\n\n⚙️ *Status Provider:*\n";
                $text .= "Status: {$providerStatus['status']}\n";
                if (isset($providerStatus['remains'])) {
                    $text .= "Sisa: {$providerStatus['remains']}";
                }
            }
        }

        $bot->sendMessage(text: $text, parse_mode: 'Markdown');
    }
}