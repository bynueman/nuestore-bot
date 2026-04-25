<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::post('/telegram-webhook', function () {
    $bot = app(\SergiX44\Nutgram\Nutgram::class);
    require base_path('routes/telegram.php');
    $bot->run();
});

Route::get('/bot-test', function () {
    return "WEB BOT IS ALIVE";
});
