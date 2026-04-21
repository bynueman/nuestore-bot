<?php

use App\Telegram\Handlers\AdminHandler;
use App\Telegram\Handlers\HelpHandler;
use App\Telegram\Handlers\OrderConversation;
use App\Telegram\Handlers\ServicesHandler;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Handlers\StatusHandler;
use SergiX44\Nutgram\Nutgram;

/** @var Nutgram $bot */

// User Commands
$bot->onCommand('start', StartHandler::class);
$bot->onCommand('services', ServicesHandler::class);
$bot->onCommand('order', OrderConversation::class);
$bot->onCommand('status', StatusHandler::class);
$bot->onCommand('help', HelpHandler::class);

// Admin Commands
$bot->onCommand('retry_queue', [AdminHandler::class, 'retryQueue']);
$bot->onCommand('balance', [AdminHandler::class, 'checkBalance']);
$bot->onCommand('queued', [AdminHandler::class, 'listQueued']);