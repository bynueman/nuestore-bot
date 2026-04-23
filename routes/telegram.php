<?php

use App\Telegram\Conversations\OrderConversation;
use App\Telegram\Handlers\BalanceHandler;
use App\Telegram\Handlers\HelpHandler;
use App\Telegram\Handlers\ReportHandler;
use App\Telegram\Handlers\ServicesHandler;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Handlers\StatusHandler;
use SergiX44\Nutgram\Nutgram;

/** @var Nutgram $bot */

// =============================================
// GUARD: Hanya owner yang bisa pakai bot ini
// =============================================
$bot->middleware(function (Nutgram $bot, $next) {
    $allowedId = (string) config('nutgram.admin_telegram_id');

    if ((string) $bot->userId() !== $allowedId) {
        $bot->sendMessage('⛔ Bot ini bersifat private.');
        return;
    }

    $next($bot);
});

// =============================================
// Commands
// =============================================
$bot->onCommand('start',    StartHandler::class);
$bot->onCommand('order',    OrderConversation::class);
$bot->onCommand('status {id?}', StatusHandler::class); // {id?} agar bisa match /status uuid
$bot->onCommand('services', ServicesHandler::class);
$bot->onCommand('balance',  BalanceHandler::class);
$bot->onCommand('report',   ReportHandler::class);
$bot->onCommand('help',     HelpHandler::class);
$bot->onCommand('format', function (Nutgram $bot) {
    $text = "📋 FORMAT ORDER\n"
          . "Platform: Instagram\n"
          . "Layanan: Followers ID\n"
          . "Target: https://instagram.com/targetku\n"
          . "Jumlah: 1000\n"
          . "Catatan: -";

    $bot->sendMessage("Salin format di bawah ini dan bagikan ke pelanggan/reseller. Jika sudah diisi, *paste* kembali ke saya untuk otomatis order!", parse_mode: 'Markdown');
    $bot->sendMessage("`{$text}`\n\n_(Ketuk untuk menyalin)_", parse_mode: 'Markdown');
});

// =============================================
// Persistent keyboard buttons & Regex Shortcuts
// =============================================
$bot->onText('(?is).*FORMAT ORDER.*', \App\Telegram\Conversations\ShortcutConversation::class);
$bot->onText('🛒 Order',      OrderConversation::class);
$bot->onText('💰 Saldo',      BalanceHandler::class);
$bot->onText('📊 Laporan',    ReportHandler::class);

$bot->onText('📋 Cek Status', StatusHandler::class);

// =============================================
// Handle semua callback query
// =============================================
$bot->onCallbackQuery(function (Nutgram $bot) {
    $data = $bot->callbackQuery()?->data ?? '';

    // ServicesHandler callbacks (sv_platform: sv_cat: sv_back:)
    if (str_starts_with($data, 'sv_platform:') ||
        str_starts_with($data, 'sv_cat:') ||
        str_starts_with($data, 'sv_back:')) {
        ServicesHandler::handleCallback($bot);
        return;
    }

    // ReportHandler callbacks
    if (str_starts_with($data, 'report_period:')) {
        ReportHandler::handleCallback($bot);
        return;
    }

    // OrderConversation callbacks (sv_platform_ sv_cat_ sv_pick_ sv_detail_ order_)
    $bot->currentConversation()?->continue($bot);
});