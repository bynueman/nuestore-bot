<?php

use App\Telegram\Customer\Conversations\CustomerOrderConversation;
use App\Telegram\Customer\Handlers\CustomerHelpHandler;
use App\Telegram\Customer\Handlers\CustomerStartHandler;
use App\Telegram\Customer\Handlers\CustomerStatusHandler;
use App\Models\NuestoreOrder;
use App\Models\NuestoreCustomer;
use SergiX44\Nutgram\Nutgram;

/** @var Nutgram $bot */

// =============================================
// GUARD: Blokir user yang di-blacklist
// =============================================
$bot->middleware(function (Nutgram $bot, $next) {
    $userId = $bot->userId();
    if ($userId === null) {
        return;
    }
    
    $userId = (string) $userId;
    $customer = NuestoreCustomer::where('telegram_id', $userId)->first();
    if ($customer?->is_blacklisted) {
        $bot->sendMessage('⛔ Akunmu diblokir dari layanan kami.');
        return;
    }
    $next($bot);
});

// =============================================
// Commands
// =============================================
$bot->onCommand('start',  CustomerStartHandler::class);
$bot->onCommand('order',  CustomerOrderConversation::class);
$bot->onCommand('status', CustomerStatusHandler::class);
$bot->onCommand('help',   CustomerHelpHandler::class);
$bot->onCommand('cancel', function (Nutgram $bot) {
    $bot->endConversation();
    $bot->sendMessage("❌ Dibatalkan. Kamu bisa mulai lagi kapan saja.");
});

// =============================================
// Persistent keyboard buttons & Safety Net
// =============================================
$bot->onText('🛒 Pesan Sekarang',  CustomerOrderConversation::class);
$bot->onText('📋 Status Pesanan',  CustomerStatusHandler::class);
$bot->onText('❓ Bantuan',          CustomerHelpHandler::class);

// Safety net dihapus — platform sekarang pakai InlineKeyboard (callback query),
// tidak ada lagi teks "📸 Instagram" / "🎵 TikTok" yang dikirim user.

// =============================================
// Handle Callback Queries
// =============================================
$bot->onCallbackQuery(function (Nutgram $bot) {
    $data = $bot->callbackQuery()?->data ?? '';

    // 1. Handle Batal Global (kill conversation)
    if ($data === 'co_cancel') {
        $bot->endConversation();
        $bot->answerCallbackQuery(text: "Dibatalkan");
        $bot->sendMessage("❌ Pesanan dibatalkan.");
        return;
    }

    // 2. Handle Cancel Order Spesifik (dari Status)
    if (str_starts_with($data, 'customer_cancel:')) {
        $orderId = substr($data, 16);
        $order   = NuestoreOrder::find($orderId);
        if ($order && in_array($order->status, ['PENDING_PAYMENT', 'PROOF_SUBMITTED'])) {
            $order->update(['status' => 'CANCELLED']);
            $bot->answerCallbackQuery(text: "Pesanan dibatalkan");
            $bot->sendMessage("✅ Pesanan berhasil dibatalkan.");
        }
        return;
    }

    // 3. Semua callback lain (co_platform:, co_cat:, co_proof, dll)
    // Nutgram otomatis forward ke active conversation step
    try { $bot->answerCallbackQuery(); } catch (\Throwable $e) { /* query expired, ignore */ }
});