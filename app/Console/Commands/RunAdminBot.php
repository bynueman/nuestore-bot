<?php

namespace App\Console\Commands;

use App\Telegram\Bots\AdminBot;
use Illuminate\Console\Command;

class RunAdminBot extends Command
{
    protected $signature   = 'telegram:admin';
    protected $description = 'Jalankan Admin Bot Telegram';

    public function handle(): void
    {
        $this->info('Admin Bot berjalan...');
        $bot = new AdminBot();
        $bot->run();
    }
}