<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Duitku Payment Webhook
Route::post('/webhook/duitku', [WebhookController::class, 'duitku']);

// Telegram Webhook
Route::post('/telegram/webhook', function () {
    $bot = app(\SergiX44\Nutgram\Nutgram::class);
    $bot->run();
});