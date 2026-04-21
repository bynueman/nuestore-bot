<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreSetting;
use App\Models\NuestoreTransaction;
use App\Models\NuestoreUser;
use App\Services\DuitkuService;
use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Conversations\Conversation;

class OrderConversation extends Conversation
{
    private ?int $serviceId = null;
    private ?string $targetLink = null;
    private ?int $quantity = null;
    private ?array $selectedService = null;

    public function start(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "🛒 *Buat Pesanan Baru*\n\n" .
                  "Masukkan *ID layanan* yang ingin dipesan.\n" .
                  "_(Gunakan /services untuk melihat daftar ID layanan)_",
            parse_mode: 'Markdown'
        );

        $this->next('askLink');
    }

    public function askLink(Nutgram $bot): void
    {
        $input = $bot->message()->text;

        if (!is_numeric($input)) {
            $bot->sendMessage(text: "❌ ID layanan harus berupa angka. Coba lagi:");
            return;
        }

        $this->serviceId = (int) $input;

        // Validasi service ID ke Lollipop
        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();

        if (!$services) {
            $bot->sendMessage(text: "❌ Gagal memuat layanan. Coba lagi nanti.");
            $this->end();
            return;
        }

        $found = collect($services)->firstWhere('service', $this->serviceId);

        if (!$found) {
            $bot->sendMessage(text: "❌ ID layanan tidak ditemukan. Gunakan /services untuk melihat ID yang valid.");
            $this->end();
            return;
        }

        $this->selectedService = $found;
        $markup = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);
        $harga  = number_format((float)$found['rate'] * $markup, 0, ',', '.');

        $bot->sendMessage(
            text: "✅ *Layanan ditemukan:*\n\n" .
                  "📦 {$found['name']}\n" .
                  "💰 Rp {$harga} / 1000\n" .
                  "📊 Min: {$found['min']} | Max: {$found['max']}\n\n" .
                  "Sekarang masukkan *link target* (contoh: https://instagram.com/username):",
            parse_mode: 'Markdown'
        );

        $this->next('askQuantity');
    }

    public function askQuantity(Nutgram $bot): void
    {
        $link = $bot->message()->text;

        if (!filter_var($link, FILTER_VALIDATE_URL)) {
            $bot->sendMessage(text: "❌ Link tidak valid. Masukkan URL yang benar (contoh: https://instagram.com/username):");
            return;
        }

        $this->targetLink = $link;

        $bot->sendMessage(
            text: "📊 Masukkan *jumlah* yang ingin dipesan:\n" .
                  "_(Min: {$this->selectedService['min']} | Max: {$this->selectedService['max']})_",
            parse_mode: 'Markdown'
        );

        $this->next('confirmOrder');
    }

    public function confirmOrder(Nutgram $bot): void
    {
        $qty = $bot->message()->text;

        if (!is_numeric($qty)) {
            $bot->sendMessage(text: "❌ Jumlah harus berupa angka. Coba lagi:");
            return;
        }

        $qty = (int) $qty;

        if ($qty < (int)$this->selectedService['min'] || $qty > (int)$this->selectedService['max']) {
            $bot->sendMessage(
                text: "❌ Jumlah harus antara {$this->selectedService['min']} - {$this->selectedService['max']}. Coba lagi:"
            );
            return;
        }

        $this->quantity = $qty;

        $markup       = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);
        $hargaPer1000 = (float)$this->selectedService['rate'] * $markup;
        $totalHarga   = (int) ceil(($hargaPer1000 / 1000) * $qty);
        $totalRupiah  = number_format($totalHarga, 0, ',', '.');

        $bot->sendMessage(
            text: "📋 *Konfirmasi Pesanan:*\n\n" .
                  "📦 Layanan: {$this->selectedService['name']}\n" .
                  "🔗 Target: {$this->targetLink}\n" .
                  "📊 Jumlah: " . number_format($qty, 0, ',', '.') . "\n" .
                  "💰 Total: Rp {$totalRupiah}\n\n" .
                  "Ketik *YA* untuk konfirmasi atau *BATAL* untuk membatalkan.",
            parse_mode: 'Markdown'
        );

        $this->next('processPayment');
    }

    public function processPayment(Nutgram $bot): void
    {
        $input = strtoupper(trim($bot->message()->text));

        if ($input === 'BATAL') {
            $bot->sendMessage(text: "❌ Pesanan dibatalkan.");
            $this->end();
            return;
        }

        if ($input !== 'YA') {
            $bot->sendMessage(text: "Ketik *YA* untuk konfirmasi atau *BATAL* untuk membatalkan.", parse_mode: 'Markdown');
            return;
        }

        $bot->sendMessage(text: "⏳ Membuat invoice pembayaran...");

        $markup       = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);
        $hargaPer1000 = (float)$this->selectedService['rate'] * $markup;
        $totalHarga   = (int) ceil(($hargaPer1000 / 1000) * $this->quantity);
        $modalCost    = (float)$this->selectedService['rate'] / 1000 * $this->quantity;
        $pgFee        = $totalHarga * 0.007;
        $profitEst    = $totalHarga - $modalCost - $pgFee;

        $telegramId = $bot->userId();
        $user = NuestoreUser::firstOrCreate(
            ['telegram_id' => $telegramId],
            ['username'    => $bot->user()->username ?? null]
        );

        $transaction = NuestoreTransaction::create([
            'user_id'          => $user->id,
            'target_link'      => $this->targetLink,
            'service_id'       => $this->serviceId,
            'amount_paid'      => $totalHarga,
            'modal_cost'       => $modalCost,
            'pg_fee_estimated' => $pgFee,
            'profit_estimated' => $profitEst,
            'status'           => 'UNPAID',
        ]);

        $duitku   = new DuitkuService();
        $response = $duitku->createTransaction(
            orderId:        $transaction->id,
            amount:         $totalHarga,
            productDetails: "Order - {$this->selectedService['name']}",
            email:          'customer@nuestore.id',
            phoneNumber:    '08000000000',
            customerName:   $bot->user()->first_name ?? 'Customer',
            returnUrl:      config('app.url'),
            callbackUrl:    config('app.url') . '/api/webhook/duitku'
        );

        if (!$response || !isset($response['paymentUrl'])) {
            $bot->sendMessage(text: "❌ Gagal membuat invoice. Coba lagi nanti.");
            $transaction->delete();
            $this->end();
            return;
        }

        $transaction->update(['duitku_ref' => $response['merchantOrderId'] ?? $transaction->id]);

        $totalFormatted = number_format($totalHarga, 0, ',', '.');

        $bot->sendMessage(
            text: "✅ *Invoice Berhasil Dibuat!*\n\n" .
                  "📋 Order ID: `{$transaction->id}`\n" .
                  "💰 Total: Rp {$totalFormatted}\n\n" .
                  "🔗 *Link Pembayaran:*\n{$response['paymentUrl']}\n\n" .
                  "⏰ Invoice berlaku *60 menit*.\n" .
                  "Setelah bayar, pesanan diproses otomatis!\n\n" .
                  "Gunakan /status `{$transaction->id}` untuk cek status.",
            parse_mode: 'Markdown'
        );

        $this->end();
    }
}