<?php

namespace App\Telegram\Handlers;

use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Nutgram;

class BalanceHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $lollipop = new LollipopSmmService();
        $balance  = $lollipop->getBalance();

        if (!$balance) {
            $bot->sendMessage(
                text: "❌ Gagal mengambil saldo Lollipop. Periksa koneksi atau API key.",
                parse_mode: 'Markdown'
            );
            return;
        }

        $saldo    = (float) $balance['balance'];
        $currency = $balance['currency'] ?? 'IDR';
        $formSaldo = number_format($saldo, 2, ',', '.');

        $alertText = '';
        if ($saldo < 50000) {
            $alertText = "\n\n⚠️ *PERINGATAN: Saldo menipis!*\nSegera deposit agar pesanan tidak terhambat.";
        } elseif ($saldo < 100000) {
            $alertText = "\n\n⚡ Saldo mulai berkurang. Pertimbangkan deposit segera.";
        }

        $bot->sendMessage(
            text: "💰 *Saldo Lollipop*\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "💵 Saldo: *{$formSaldo} {$currency}*"
                . $alertText,
            parse_mode: 'Markdown'
        );
    }
}
