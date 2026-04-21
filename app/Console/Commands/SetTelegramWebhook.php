<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

class SetTelegramWebhook extends Command
{
    protected $signature   = 'telegram:set-webhook {url?}';
    protected $description = 'Set Telegram Bot Webhook URL';

    public function handle(Nutgram $bot): void
    {
        $url = $this->argument('url') ?? config('app.url') . '/telegram/webhook';

        $result = $bot->setWebhook($url);

        if ($result) {
            $this->info("✅ Webhook berhasil di-set ke: {$url}");
        } else {
            $this->error("❌ Gagal set webhook.");
        }
    }
}