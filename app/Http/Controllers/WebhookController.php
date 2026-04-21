<?php

namespace App\Http\Controllers;

use App\Models\NuestoreTransaction;
use App\Services\DuitkuService;
use App\Services\LollipopSmmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class WebhookController extends Controller
{
    public function duitku(Request $request): \Illuminate\Http\JsonResponse
    {
        Log::info('Duitku Webhook Received', $request->all());

        $data = $request->all();

        // Verifikasi signature
        $duitku = new DuitkuService();
        if (!$duitku->verifyCallback($data)) {
            Log::warning('Duitku Webhook Invalid Signature', $data);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // Cek status pembayaran
        if ($data['resultCode'] !== '00') {
            return response()->json(['message' => 'Payment not success'], 200);
        }

        $orderId = $data['merchantOrderId'];

        // Idempotency check - cegah double processing
        $transaction = NuestoreTransaction::where('id', $orderId)
            ->where('status', 'UNPAID')
            ->first();

        if (!$transaction) {
            Log::info('Duitku Webhook: Transaction already processed or not found', ['orderId' => $orderId]);
            return response()->json(['message' => 'Already processed'], 200);
        }

        // Amankan dulu statusnya
        $transaction->update(['status' => 'PAID_QUEUED']);

        // Coba submit ke Lollipop
        $lollipop = new LollipopSmmService();
        $result   = $lollipop->createOrder(
            $transaction->service_id,
            $transaction->target_link,
            null
        );

        $bot   = app(Nutgram::class);
        $user  = $transaction->user;

        if ($result && isset($result['order'])) {
            // Sukses
            $transaction->update([
                'provider_order_id' => $result['order'],
                'status'            => 'PROCESSING',
            ]);

            // Notif ke user
            $bot->sendMessage(
                text: "✅ *Pembayaran Diterima!*\n\n" .
                      "Pesanan kamu sedang diproses.\n" .
                      "🆔 Order ID: `{$transaction->id}`\n" .
                      "⚙️ Provider ID: `{$result['order']}`\n\n" .
                      "Gunakan `/status {$transaction->id}` untuk cek progress.",
                parse_mode: 'Markdown',
                chat_id: $user->telegram_id
            );
        } else {
            // Gagal - masuk antrean
            $transaction->update([
                'status'          => 'PAID_QUEUED',
                'retry_error_log' => json_encode($result),
            ]);

            // Notif ke user - UX hack
            $bot->sendMessage(
                text: "✅ *Pembayaran Diterima!*\n\n" .
                      "Server sedang padat, pesanan kamu masuk antrean prioritas.\n" .
                      "🆔 Order ID: `{$transaction->id}`\n" .
                      "⏰ Estimasi proses: 1-6 jam\n\n" .
                      "Kami akan konfirmasi setelah pesanan diproses.",
                parse_mode: 'Markdown',
                chat_id: $user->telegram_id
            );

            // Notif urgent ke admin
            $bot->sendMessage(
                text: "🚨 *URGENT: Pesanan Nyangkut!*\n\n" .
                      "🆔 Order ID: `{$transaction->id}`\n" .
                      "📦 Service: {$transaction->service_id}\n" .
                      "🔗 Target: {$transaction->target_link}\n" .
                      "💰 Rp " . number_format($transaction->amount_paid, 0, ',', '.') . "\n\n" .
                      "❗ Saldo Lollipop mungkin habis atau API down.\n" .
                      "Gunakan /retry_queue setelah deposit.",
                parse_mode: 'Markdown',
                chat_id: config('nutgram.admin_telegram_id')
            );
        }

        return response()->json(['message' => 'OK'], 200);
    }
}