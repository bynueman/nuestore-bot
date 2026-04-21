<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;

class SetupBotCommands extends Command
{
    protected $signature   = 'telegram:setup-commands';
    protected $description = 'Setup bot commands menu di Telegram';

    public function handle(Nutgram $bot): void
    {
        $bot->setMyCommands([
            ['command' => 'start',  'description' => '🏠 Mulai / Menu Utama'],
            ['command' => 'order',  'description' => '🛒 Buat Pesanan Baru'],
            ['command' => 'status', 'description' => '📋 Cek Status Pesanan'],
            ['command' => 'help',   'description' => 'ℹ️ Bantuan'],
        ]);

        $this->info('✅ Bot commands berhasil di-setup.');
    }
}