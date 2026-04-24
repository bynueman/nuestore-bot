<?php

namespace App\Telegram\Handlers\Admin;

use App\Models\NuestoreOrder;
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

    // ─────────────────────────────────────────────
    // Core send methods
    // ─────────────────────────────────────────────

    public function send(string $text, array $keyboard = []): ?int
    {
        if (empty($this->token) || empty($this->adminId)) {
            Log::warning('Admin bot token or admin ID not configured.');
            return null;
        }

        $payload = [
            'chat_id'                  => $this->adminId,
            'text'                     => $text,
            'parse_mode'               => 'Markdown',
            'disable_web_page_preview' => true,
        ];

        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }

        try {
            $resp = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", $payload);
            return $resp->json('result.message_id');
        } catch (\Exception $e) {
            Log::error('Admin notification failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function sendPhoto(string $fileId, string $caption, array $keyboard = []): ?int
    {
        if (empty($this->token) || empty($this->adminId)) return null;

        $payload = [
            'chat_id'    => $this->adminId,
            'photo'      => $fileId,
            'caption'    => $caption,
            'parse_mode' => 'Markdown',
        ];

        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }

        try {
            $resp = Http::post("https://api.telegram.org/bot{$this->token}/sendPhoto", $payload);
            return $resp->json('result.message_id');
        } catch (\Exception $e) {
            Log::error('Admin sendPhoto failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function editMessageReplyMarkup(int $messageId, array $keyboard = []): void
    {
        if (empty($this->token) || empty($this->adminId)) return;

        try {
            Http::post("https://api.telegram.org/bot{$this->token}/editMessageReplyMarkup", [
                'chat_id'      => $this->adminId,
                'message_id'   => $messageId,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            ]);
        } catch (\Exception $e) {
            Log::error('Admin editMarkup failed', ['error' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────
    // Customer Order Notifications
    // ─────────────────────────────────────────────

    /**
     * Kirim notifikasi bukti bayar ke Admin Bot, dengan tombol Approve/Reject/Blacklist.
     */
    public function notifyProofSubmitted(NuestoreOrder $order): ?int
    {
        $customer     = $order->customer;
        $totalFmt     = number_format($order->total_amount, 0, ',', '.');
        $profitFmt    = number_format($order->profit_estimated, 0, ',', '.');
        $orderId      = $order->id;
        $fileId       = $order->proof_file_id;

        // --- FIXED: Cross-bot photo sending (Download & Send) ---
        $custToken = config('nutgram.token');
        $fileUrl   = null;
        $tempPath  = storage_path("app/public/temp_proof_{$orderId}.jpg");
        
        try {
            $fileInfo = Http::get("https://api.telegram.org/bot{$custToken}/getFile", ['file_id' => $fileId])->json();
            if (isset($fileInfo['result']['file_path'])) {
                $remotePath = "https://api.telegram.org/file/bot{$custToken}/" . $fileInfo['result']['file_path'];
                
                // Download file locally
                $imageContent = file_get_contents($remotePath);
                if ($imageContent) {
                    file_put_contents($tempPath, $imageContent);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to download proof for admin', ['error' => $e->getMessage()]);
        }

        $caption = "📸 *BUKTI BAYAR MASUK*\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "👤 Pelanggan: @{$customer->username} (`{$customer->telegram_id}`)\n"
                 . "🆔 Order ID: `{$orderId}`\n\n"
                 . "📦 Layanan: {$order->service_name}\n"
                 . "🔗 Target: `{$order->target_link}`\n"
                 . "🔢 Qty: " . number_format($order->quantity, 0, ',', '.') . "\n\n"
                 . "💰 Dibayar: *Rp {$totalFmt}*\n"
                 . "📈 Est. Profit: Rp {$profitFmt}\n\n"
                 . "⚠️ Cek mutasi GoPay sebelum Approve!";

        $keyboard = [
            [
                ['text' => '✅ Approve', 'callback_data' => "cust_approve:{$orderId}"],
                ['text' => '❌ Reject',  'callback_data' => "cust_reject:{$orderId}"],
            ],
            [
                ['text' => '🔨 Blacklist User', 'callback_data' => "cust_blacklist:{$customer->id}"],
            ],
        ];

        // Kirim foto jika berhasil didownload
        if (file_exists($tempPath)) {
            try {
                // Gunakan CURL manual agar lebih pasti terkirim ke Bot Admin
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$this->token}/sendPhoto");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'chat_id'    => $this->adminId,
                    'photo'      => new \CURLFile($tempPath),
                    'caption'    => $caption,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);
                $result = curl_exec($ch);
                curl_close($ch);
                
                // Hapus file temp setelah terkirim
                unlink($tempPath);
                
                return json_decode($result, true)['result']['message_id'] ?? null;
            } catch (\Exception $e) {
                Log::error('CURL SendPhoto failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->send($caption, $keyboard);
    }

    /**
     * Kirim notifikasi order baru (belum ada bukti) ke Admin.
     */
    public function notifyNewCustomerOrder(NuestoreOrder $order): void
    {
        $customer  = $order->customer;
        $totalFmt  = number_format($order->total_amount, 0, ',', '.');

        $this->send(
            "🆕 *Order Baru dari Pelanggan*\n\n"
            . "👤 @{$customer->username} (`{$customer->telegram_id}`)\n"
            . "📦 {$order->service_name}\n"
            . "🔗 `{$order->target_link}`\n"
            . "💰 Total: Rp {$totalFmt}\n"
            . "⏰ Expired dalam 15 menit"
        );
    }

    /**
     * Kirim notif ke admin jika order expired tanpa bukti.
     */
    public function notifyOrderExpired(NuestoreOrder $order): void
    {
        $customer = $order->customer;
        $this->send(
            "⏰ *Order Expired*\n\n"
            . "👤 @{$customer->username} (`{$customer->telegram_id}`)\n"
            . "🆔 `{$order->id}`\n"
            . "📦 {$order->service_name}\n"
            . "_(Tidak ada bukti dikirim dalam 15 menit)_"
        );
    }

    /**
     * Alert ke admin jika aktivitas user mencurigakan.
     */
    public function notifySuspiciousUser(string $telegramId, string $username, string $reason): void
    {
        $this->send(
            "🚨 *SUSPICIOUS USER*\n\n"
            . "👤 @{$username} (`{$telegramId}`)\n"
            . "⚠️ Alasan: {$reason}\n\n"
            . "Pertimbangkan untuk blacklist user ini."
        );
    }

    // ─────────────────────────────────────────────
    // Admin Bot Internal Notifications (existing)
    // ─────────────────────────────────────────────

    public function notifyNewOrder(string $orderId, string $serviceName, string $targetLink, int $amount): void
    {
        $amountFormatted = number_format($amount, 0, ',', '.');
        $this->send(
            "🆕 *Pesanan Baru Masuk!*\n\n"
            . "🆔 Order ID: `{$orderId}`\n"
            . "📦 Layanan: {$serviceName}\n"
            . "🔗 Target: `{$targetLink}`\n"
            . "💰 Total: Rp {$amountFormatted}\n"
            . "⚙️ Status: Sedang diproses..."
        );
    }

    public function notifyOrderStuck(string $orderId, int $serviceId, string $targetLink, int $amount): void
    {
        $amountFormatted = number_format($amount, 0, ',', '.');
        $this->send(
            "🚨 *URGENT: Pesanan Nyangkut!*\n\n"
            . "🆔 Order ID: `{$orderId}`\n"
            . "📦 Service ID: {$serviceId}\n"
            . "🔗 Target: `{$targetLink}`\n"
            . "💰 Rp {$amountFormatted}\n\n"
            . "❗ Saldo Lollipop mungkin habis atau API down.\n"
            . "Ketik /retry untuk proses ulang.",
            [[['text' => '🔄 Retry Queue', 'callback_data' => 'admin:retry_queue']]]
        );
    }

    public function notifyOrderFailed(string $orderId, int $serviceId, int $amount): void
    {
        $amountFormatted = number_format($amount, 0, ',', '.');
        $this->send(
            "❌ *Pesanan Gagal di Provider!*\n\n"
            . "🆔 Order ID: `{$orderId}`\n"
            . "📦 Service ID: {$serviceId}\n"
            . "💰 Rp {$amountFormatted}\n\n"
            . "User mungkin butuh refund."
        );
    }

    public function notifyLowBalance(string $balance, string $currency): void
    {
        $this->send(
            "⚠️ *ALERT: Saldo Lollipop Menipis!*\n\n"
            . "💰 Saldo saat ini: {$balance} {$currency}\n\n"
            . "Segera deposit agar pesanan tidak terhambat."
        );
    }

    public function notifyMissingServices(array $missingIds): void
    {
        $ids = implode(', ', $missingIds);
        $this->send(
            "⚠️ *ALERT: Service ID Hilang dari Provider!*\n\n"
            . "ID tidak ditemukan: {$ids}\n\n"
            . "Kemungkinan di-hide atau dihapus. Update whitelist segera."
        );
    }

    public function notifyAllServicesDown(): void
    {
        $this->send(
            "🚨 *CRITICAL: Semua Service Whitelist Hilang!*\n\n"
            . "Provider mungkin sedang down atau semua service ID berubah.\n"
            . "Cek panel Lollipop segera!"
        );
    }
}