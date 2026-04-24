<?php

namespace App\Telegram\Customer\Handlers;

use SergiX44\Nutgram\Nutgram;

class CustomerHelpHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $wa = config('nutgram.admin_whatsapp', '62882007207715');

        $bot->sendMessage(
            text: "❓ *Bantuan & FAQ*\n\n"
                . "*Cara Order:*\n"
                . "1. Klik 🛒 Pesan Sekarang\n"
                . "2. Pilih platform & layanan\n"
                . "3. Masukkan link target\n"
                . "4. Masukkan jumlah\n"
                . "5. Bayar via QRIS\n"
                . "6. Kirim screenshot bukti bayar\n"
                . "7. Admin konfirmasi, order jalan!\n\n"
                . "*FAQ:*\n"
                . "❔ Berapa lama proses? _Biasanya 1-24 jam setelah dikonfirmasi_\n"
                . "❔ Aman? _Ya, kami tidak meminta password akun kamu_\n"
                . "❔ Bisa cancel? _Bisa, sebelum bukti dikonfirmasi admin_\n"
                . "❔ Bukti bayar ditolak? _Pastikan nominal PERSIS sesuai yang diminta_\n\n"
                . "📱 Hubungi Admin: [WhatsApp](https://wa.me/{$wa})",
            parse_mode: 'Markdown',
            disable_web_page_preview: true
        );
    }
}
