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
    $userId = (string) $bot->userId();
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
    $bot->killCurrentConversation();
    $bot->sendMessage("❌ Dibatalkan. Kamu bisa mulai lagi kapan saja.");
});

// =============================================
// Persistent keyboard buttons & Safety Net
// =============================================
$bot->onText('🛒 Pesan Sekarang',  CustomerOrderConversation::class);
$bot->onText('📋 Status Pesanan',  CustomerStatusHandler::class);
$bot->onText('❓ Bantuan',          CustomerHelpHandler::class);

// Safety net: Jika user klik tombol keyboard bawah tapi conversation belum start
$bot->onText('📸 Instagram', function(Nutgram $bot) {
    CustomerOrderConversation::begin($bot, data: ['platform' => 'Instagram']);
});
$bot->onText('🎵 TikTok', function(Nutgram $bot) {
    CustomerOrderConversation::begin($bot, data: ['platform' => 'TikTok']);
});

// =============================================
// Handle Callback Queries (Pemicu Utama)
// =============================================
$bot->onCallbackQuery(function (Nutgram $bot) {
    $data = $bot->callbackQuery()?->data ?? '';

    // 1. Handle Batal Global
    if ($data === 'co_cancel') {
        $bot->killCurrentConversation();
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

    // 3. Handle Percakapan (PENTING: Gunakan continue & answer)
    $bot->currentConversation()?->continue($bot);
    $bot->answerCallbackQuery();
});