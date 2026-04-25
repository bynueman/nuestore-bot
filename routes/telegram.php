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
$bot->onText('🛒 Pesan Sekarang', function (Nutgram $bot) {
    \Illuminate\Support\Facades\Log::info('Button Clicked: Pesan Sekarang', ['user' => $bot->userId()]);
    $bot->endConversation();
    CustomerOrderConversation::begin($bot);
});
$bot->onText('📋 Status Pesanan',  CustomerStatusHandler::class);
$bot->onText('❓ Bantuan',          CustomerHelpHandler::class);

// =============================================
// GLOBAL CALLBACK HANDLERS
// =============================================

// 1. Handle Batal Global (co_cancel)
$bot->onCallbackQueryData('co_cancel', function (Nutgram $bot) {
    \Illuminate\Support\Facades\Log::info('Callback: co_cancel triggered', ['user' => $bot->userId()]);
    
    // Cari order terakhir user ini yang masih nunggu bayar
    $userId = (string) $bot->userId();
    $customer = NuestoreCustomer::where('telegram_id', $userId)->first();
    
    if ($customer) {
        $order = NuestoreOrder::where('customer_id', $customer->id)
            ->where('status', 'PENDING_PAYMENT')
            ->orderByDesc('created_at')
            ->first();

        if ($order) {
            $order->update(['status' => 'CANCELLED']);
            // Kirim notif ke Admin
            (new \App\Telegram\Handlers\Admin\NotificationService())->notifyOrderCancelledByCustomer($order);
            \Illuminate\Support\Facades\Log::info('Admin notified of global cancellation', ['order_id' => $order->id]);
        }
    }

    $bot->endConversation();
    $bot->answerCallbackQuery(text: "Dibatalkan");
    $bot->editMessageText("❌ Pesanan dibatalkan.");
});

// 2. Handle Cancel Order dari Menu Status
$bot->onCallbackQueryData('customer_cancel:{id}', function (Nutgram $bot, string $id) {
    \Illuminate\Support\Facades\Log::info('Callback: customer_cancel triggered', ['order_id' => $id]);
    $order = NuestoreOrder::find($id);
    if ($order && in_array($order->status, ['PENDING_PAYMENT', 'PROOF_SUBMITTED'])) {
        $order->update(['status' => 'CANCELLED']);
        
        \Illuminate\Support\Facades\Log::info('Notifying admin about cancellation', ['order_id' => $id]);
        (new \App\Telegram\Handlers\Admin\NotificationService())->notifyOrderCancelledByCustomer($order);

        $bot->answerCallbackQuery(text: "Pesanan dibatalkan.");
        $bot->editMessageText("❌ Pesanan berhasil dibatalkan.");
    } else {
        \Illuminate\Support\Facades\Log::warning('customer_cancel failed: Order not found or invalid status', ['order_id' => $id]);
        $bot->answerCallbackQuery(text: "Gagal membatalkan.");
    }
});