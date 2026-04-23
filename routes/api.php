<?php

use Illuminate\Support\Facades\Route;

// Webhook Duitku DIHAPUS — tidak pakai payment gateway

Route::post('/telegram/webhook', function () {
    $bot = app(\SergiX44\Nutgram\Nutgram::class);
    $bot->run();
});