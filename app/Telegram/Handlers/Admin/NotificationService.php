<?php

namespace App\Telegram\Handlers\Admin;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private string $token;
    private string $adminId;

    public function __construct()
    {
        $this->token   = config('nutgram.admin_bot_token');
        $this->adminId = config('nutgram.admin_telegram_id');
    }

    public function send(string $text, array $keyboard = []): void
    {
        if (empty($this->token) || empty($this->adminId)) {
            Log::warning('Admin bot token or admin ID not configured.');
            return;
        }

        $payload = [
            'chat_id'    => $this->adminId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];

        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $keyboard,
            ]);
        }

        try {
            Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
        } catch (\Exception $e) {
            Log::error('Admin notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function notifyNewOrder(string $orderId, string $serviceName, string $targetLink, int $amount): void
    {
        $amountFormatted = number_format($amount, 0, ',', '.');
        $this->send(
            "🆕 *Pesanan Baru Masuk!*\n\n" .
            "🆔 Order ID: `{$orderId}`\n" .
            "📦 Layanan: {$serviceName}\n" .
            "🔗 Target: {$targetLink}\n" .
            "💰 Total: Rp {$amountFormatted}\n" .
            "⚙️ Status: Sedang diproses..."
        );
    }

    public function notifyOrderStuck(string $orderId, int $serviceId, string $targetLink, int $amount): void
    {
        $amountFormatted = number_format($amount, 0, ',', '.');
        $this->send(
            "🚨 *URGENT: Pesanan Nyangkut!*\n\n" .
            "🆔 Order ID: `{$orderId}`\n" .
            "📦 Service ID: {$serviceId}\n" .
            "🔗 Target: {$targetLink}\n" .
            "💰 Rp {$amountFormatted}\n\n" .
            "❗ Saldo Lollipop mungkin habis atau API down.\n" .
            "Ketik /retry untuk proses ulang.",
            [[['text' => '🔄 Retry Queue', 'callback_data' => 'admin:retry_queue']]]
        );
    }

    public function notifyOrderFailed(string $orderId, int $serviceId, int $amount): void
    {
        $amountFormatted = number_format($amount, 0, ',', '.');
        $this->send(
            "❌ *Pesanan Gagal di Provider!*\n\n" .
            "🆔 Order ID: `{$orderId}`\n" .
            "📦 Service ID: {$serviceId}\n" .
            "💰 Rp {$amountFormatted}\n\n" .
            "User mungkin butuh refund."
        );
    }

    public function notifyLowBalance(string $balance, string $currency): void
    {
        $this->send(
            "⚠️ *ALERT: Saldo Lollipop Menipis!*\n\n" .
            "💰 Saldo saat ini: {$balance} {$currency}\n\n" .
            "Segera deposit agar pesanan tidak terhambat."
        );
    }

    public function notifyMissingServices(array $missingIds): void
    {
        $ids = implode(', ', $missingIds);
        $this->send(
            "⚠️ *ALERT: Service ID Hilang dari Provider!*\n\n" .
            "ID tidak ditemukan: {$ids}\n\n" .
            "Kemungkinan di-hide atau dihapus. Update whitelist segera."
        );
    }

    public function notifyAllServicesDown(): void
    {
        $this->send(
            "🚨 *CRITICAL: Semua Service Whitelist Hilang!*\n\n" .
            "Provider mungkin sedang down atau semua service ID berubah.\n" .
            "Cek panel Lollipop segera!"
        );
    }
}