<?php

namespace App\Console\Commands;

use App\Models\NuestoreOrder;
use App\Telegram\Handlers\Admin\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpireOrders extends Command
{
    protected $signature   = 'orders:expire';
    protected $description = 'Expire pending orders that have passed their expiry time (run every minute)';

    public function handle(): void
    {
        $expired = NuestoreOrder::where('status', 'PENDING_PAYMENT')
            ->where('expires_at', '<', now())
            ->with('customer')
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        $notif = new NotificationService();

        foreach ($expired as $order) {
            // Update status
            $order->update(['status' => 'EXPIRED']);

            $customer = $order->customer;

            // Increment expired count
            $customer->increment('expired_today_count');

            // Kirim notif ke pelanggan via Customer Bot
            try {
                Http::post("https://api.telegram.org/bot" . config('nutgram.token') . "/sendMessage", [
                    'chat_id'    => $customer->telegram_id,
                    'text'       => "⏰ *Pesanan Kamu Expired*\n\n"
                                  . "Batas waktu pembayaran 15 menit telah habis.\n\n"
                                  . "📦 {$order->service_name}\n"
                                  . "💰 Rp " . number_format($order->total_amount, 0, ',', '.') . "\n\n"
                                  . "Kamu bisa membuat pesanan baru sekarang.",
                    'parse_mode' => 'Markdown',
                ]);
            } catch (\Exception $e) {
                Log::error("ExpireOrders: gagal notif pelanggan {$customer->telegram_id}", ['error' => $e->getMessage()]);
            }

            // Kirim notif ke Admin
            $notif->notifyOrderExpired($order);

            // Auto-warning ke admin jika expired 3x hari ini
            if ($customer->expired_today_count >= 3) {
                $notif->notifySuspiciousUser(
                    $customer->telegram_id,
                    $customer->username ?? 'unknown',
                    "Order expired {$customer->expired_today_count}x hari ini tanpa bukti bayar."
                );
            }

            Log::info("Order expired: {$order->id}");
        }

        $this->info("Expired {$expired->count()} order(s).");
    }
}
