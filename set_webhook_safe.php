<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use SergiX44\Nutgram\Nutgram;

$bot = app(Nutgram::class);
$url = "https://nuestore.web.id/api/bot";
$secret = env('TELEGRAM_WEBHOOK_SECRET');

echo "Mendaftarkan Webhook ke: $url\n";
echo "Dengan Secret Token: $secret\n\n";

$result = $bot->setWebhook($url, [
    'secret_token' => $secret
]);

if ($result) {
    echo "✅ BERHASIL! Webhook sudah aktif dan terlindungi.\n";
} else {
    echo "❌ GAGAL! Periksa koneksi atau token bot Anda.\n";
}
