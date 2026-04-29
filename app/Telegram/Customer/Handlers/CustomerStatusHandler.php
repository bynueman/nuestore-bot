<?php

namespace App\Telegram\Customer\Handlers;

use App\Models\NuestoreCustomer;
use App\Models\NuestoreOrder;
use SergiX44\Nutgram\Nutgram;

class CustomerStatusHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $user     = $bot->user();
        $customer = NuestoreCustomer::fromTelegramUser($user);

        $orders = NuestoreOrder::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($orders->isEmpty()) {
            $bot->sendMessage(
                text: "📋 Belum ada riwayat pesanan.\n\nKlik 🛒 *Pesan Sekarang* untuk mulai order!",
                parse_mode: 'Markdown'
            );
            return;
        }

        $statusMap = [
            'PENDING_PAYMENT' => '💳 Menunggu Pembayaran',
            'PROOF_SUBMITTED' => '📸 Bukti Dikirim, Menunggu Konfirmasi',
            'APPROVED'        => '✅ Disetujui',
            'REJECTED'        => '❌ Ditolak',
            'PROCESSING'      => '⚙️ Sedang Diproses',
            'COMPLETED'       => '🎉 Selesai',
            'EXPIRED'         => '⏰ Expired',
            'CANCELLED'       => '🚫 Dibatalkan',
        ];

        // Kumpulkan ID order Lollipop untuk pesanan yang PROCESSING
        $processingIds = [];
        foreach ($orders as $order) {
            if ($order->status === 'PROCESSING' && $order->provider_order_id) {
                $processingIds[] = $order->provider_order_id;
            }
        }

        // Ambil status real-time secara massal dari Lollipop
        $realtimeData = [];
        if (!empty($processingIds)) {
            $smm = new \App\Services\LollipopSmmService();
            $apiResult = $smm->getStatuses($processingIds);
            if ($apiResult && is_array($apiResult)) {
                $realtimeData = $apiResult;
            }
        }

        $text = "📋 *Riwayat 5 Pesanan Terakhir*\n\n";

        foreach ($orders as $idx => $order) {
            $num    = $idx + 1;
            $status = $statusMap[$order->status] ?? $order->status;
            $total  = number_format($order->total_amount, 0, ',', '.');

            $text .= "{$num}. 📦 {$order->service_name}\n";
            $text .= "   💰 Rp {$total} | {$status}\n";
            
            // Tambahkan info real-time jika ada
            if ($order->status === 'PROCESSING' && isset($realtimeData[$order->provider_order_id])) {
                $rt = $realtimeData[$order->provider_order_id];
                if (!isset($rt['error'])) {
                    $remains    = $rt['remains'] ?? '?';
                    $startCount = $rt['start_count'] ?? '?';
                    // Hanya tampilkan jika angkanya valid
                    if ($remains !== '?' || $startCount !== '?') {
                        $text .= "   📊 _Real-time: Sisa $remains unit (Awal: $startCount)_\n";
                    }
                }
            }

            $text .= "   📅 " . $order->created_at->format('d/m/Y H:i') . "\n\n";
        }

        // Jika ada pending order, tampilkan tombol cancel
        $pending = $customer->pendingOrder();
        if ($pending && $pending->status === 'PENDING_PAYMENT') {
            $bot->sendMessage(
                text: $text . "⚠️ Kamu masih punya pesanan yang belum dibayar. Bayar atau batalkan dulu.",
                parse_mode: 'Markdown',
                reply_markup: \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup::make()
                    ->addRow(
                        \SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton::make(
                            '❌ Batalkan Pesanan',
                            callback_data: "customer_cancel:{$pending->id}"
                        )
                    )
            );
        } else {
            $bot->sendMessage(text: $text, parse_mode: 'Markdown');
        }
    }
}
