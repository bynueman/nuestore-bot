<?php

use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\RunningMode\Webhook;

Route::post('/telegram/webhook', function () {
    $bot = app(\SergiX44\Nutgram\Nutgram::class);
    $bot->setRunningMode(Webhook::class);
    require base_path('routes/telegram.php');
    $bot->run();
});