<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use SergiX44\Nutgram\Nutgram;

$bot = app(Nutgram::class);
$url = "https://nuestore.web.id/api/bot";

// Ambil langsung dari file .env agar tidak kena cache
$envFile = file_get_contents(__DIR__.'/.env');
preg_match('/TELEGRAM_WEBHOOK_SECRET=(.*)/', $envFile, $matches);
$secret = trim($matches[1] ?? '');

if (!$secret) {
    echo "❌ ERROR: TELEGRAM_WEBHOOK_SECRET tidak ditemukan di .env!\n";
    exit;
}

echo "Mendaftarkan Webhook ke: $url\n";
echo "Dengan Secret Token: $secret\n\n";

// Menggunakan Named Arguments (PHP 8.0+)
$result = $bot->setWebhook(
    url: $url,
    secret_token: $secret,
    drop_pending_updates: true
);

if ($result) {
    echo "✅ BERHASIL! Webhook sudah aktif dan terlindungi.\n";
} else {
    echo "❌ GAGAL! Periksa koneksi atau token bot Anda.\n";
}
