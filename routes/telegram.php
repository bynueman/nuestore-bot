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

    $customer = \App\Models\NuestoreCustomer::where('telegram_id', $userId)->first();
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
// Persistent keyboard buttons
// =============================================
$bot->onText('🛒 Pesan Sekarang',  CustomerOrderConversation::class);
$bot->onText('📋 Status Pesanan',  CustomerStatusHandler::class);
$bot->onText('❓ Bantuan',          CustomerHelpHandler::class);

// =============================================
// Handle semua callback query
// =============================================
$bot->onCallbackQuery(function (Nutgram $bot) {
    $data = $bot->callbackQuery()?->data ?? '';

    // Pembatalan order dari tombol Cek Status atau pesan lama
    if (str_starts_with($data, 'customer_cancel:')) {
        $orderId = substr($data, 16);
        $order   = NuestoreOrder::find($orderId);

        if (!$order || !in_array($order->status, ['PENDING_PAYMENT', 'PROOF_SUBMITTED'])) {
            $bot->answerCallbackQuery(text: "Order tidak bisa dibatalkan.");
            return;
        }

        // Pastikan hanya pemilik yang bisa cancel
        $customer = NuestoreCustomer::where('telegram_id', (string) $bot->userId())->first();
        if (!$customer || $order->customer_id !== $customer->id) {
            $bot->answerCallbackQuery(text: "⛔ Bukan orderanmu.");
            return;
        }

        $order->update(['status' => 'CANCELLED']);
        $bot->answerCallbackQuery(text: "✅ Pesanan dibatalkan");
        $bot->sendMessage(
            text: "✅ *Pesanan berhasil dibatalkan.*\n\nKamu bisa membuat pesanan baru sekarang.",
            parse_mode: 'Markdown'
        );
        return;
    }

    // Order conversation callbacks (co_platform:, co_cat:, co_proof, co_cancel)
    if (str_starts_with($data, 'co_')) {
        $bot->currentConversation()?->continue($bot);
        return;
    }

    $bot->currentConversation()?->continue($bot);
});