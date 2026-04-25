<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::post('/telegram-webhook', function () {
    $bot = app(\SergiX44\Nutgram\Nutgram::class);
    $bot->setRunningMode(\SergiX44\Nutgram\RunningMode\Webhook::class);
    require base_path('routes/telegram.php');
    $bot->run();
});

Route::get('/bot-test', function () {
    return "WEB BOT IS ALIVE";
});
