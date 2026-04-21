<?php

use App\Telegram\Conversations\OrderConversation;
use App\Telegram\Handlers\AdminHandler;
use App\Telegram\Handlers\HelpHandler;
use App\Telegram\Handlers\ServicesHandler;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Handlers\StatusHandler;
use SergiX44\Nutgram\Nutgram;

/** @var Nutgram $bot */

// Commands
$bot->onCommand('start',    StartHandler::class);
$bot->onCommand('order',    OrderConversation::class);
$bot->onCommand('status',   StatusHandler::class);
$bot->onCommand('services', ServicesHandler::class);
$bot->onCommand('help',     HelpHandler::class);

// Admin Commands
$bot->onCommand('retry_queue', [AdminHandler::class, 'retryQueue']);
$bot->onCommand('balance',     [AdminHandler::class, 'checkBalance']);
$bot->onCommand('queued',      [AdminHandler::class, 'listQueued']);

// Persistent keyboard buttons
$bot->onText('🛒 Order',      OrderConversation::class);
$bot->onText('📋 Cek Status', function (Nutgram $bot) {
    $bot->sendMessage(
        text: "📋 Masukkan Order ID pesananmu:\n_(Contoh: /status uuid-order-id)_",
        parse_mode: 'Markdown'
    );
});

// Handle callback query
$bot->onCallbackQuery(function (Nutgram $bot) {
    $data = $bot->callbackQuery()?->data ?? '';

    if (str_starts_with($data, 'sv_')) {
        ServicesHandler::handleCallback($bot);
        return;
    }

    $bot->currentConversation()?->continue($bot);
});