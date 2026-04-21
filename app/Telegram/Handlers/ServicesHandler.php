<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreSetting;
use App\Services\LollipopSmmService;
use SergiX44\Nutgram\Nutgram;

class ServicesHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: "⏳ Mengambil daftar layanan, mohon tunggu...",
            parse_mode: 'Markdown'
        );

        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();
        $markup   = (float) NuestoreSetting::get('global_markup_multiplier', 2.0);

        if (!$services) {
            $bot->sendMessage(
                text: "❌ Gagal mengambil daftar layanan. Coba lagi nanti.",
                parse_mode: 'Markdown'
            );
            return;
        }

        // Ambil whitelist
        $whitelist = array_filter(
            array_map('trim', explode(',', NuestoreSetting::get('whitelisted_service_ids', '')))
        );

        $collection = collect($services);

        // Filter whitelist kalau ada, skip ID yang sudah hilang dari provider
        if (!empty($whitelist)) {
            $collection = $collection->filter(
                fn($s) => in_array((string)$s['service'], $whitelist)
            );
        }

        // Kalau semua ID whitelist hilang dari provider
        if ($collection->isEmpty()) {
            $bot->sendMessage(
                text: "⚠️ Layanan sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.",
                parse_mode: 'Markdown'
            );

            // Alert ke admin
            $bot->sendMessage(
                text: "🚨 *ALERT: Semua service whitelist tidak ditemukan di provider!*\n\n" .
                      "Kemungkinan provider sedang down atau service ID sudah berubah.\n" .
                      "Cek panel Lollipop dan update whitelist segera.",
                parse_mode: 'Markdown',
                chat_id: config('nutgram.admin_telegram_id')
            );
            return;
        }

        // Deteksi ID yang hilang, alert admin tapi tetap lanjut
        if (!empty($whitelist)) {
            $availableIds = $collection->pluck('service')->map('strval')->toArray();
            $missingIds   = array_diff($whitelist, $availableIds);

            if (!empty($missingIds)) {
                $bot->sendMessage(
                    text: "⚠️ *ALERT: Beberapa service ID tidak ditemukan!*\n\n" .
                          "ID hilang: " . implode(', ', $missingIds) . "\n\n" .
                          "Kemungkinan di-hide atau dihapus provider. Update whitelist segera.",
                    parse_mode: 'Markdown',
                    chat_id: config('nutgram.admin_telegram_id')
                );
            }
        }

        // Grouping by category
        $grouped = $collection->groupBy('category');

        $text = "📦 *Daftar Layanan Nuestore*\n\n";

        foreach ($grouped as $category => $items) {
            $text .= "━━━━━━━━━━━━━━━\n";
            $text .= "📁 *{$category}*\n";
            foreach ($items as $item) {
                $harga = number_format((float)$item['rate'] * $markup, 0, ',', '.');
                $text .= "• `{$item['service']}` — {$item['name']}\n";
                $text .= "  💰 Rp {$harga} / 1000\n";
                $text .= "  📊 Min: {$item['min']} | Max: {$item['max']}\n\n";
            }
        }

        $text .= "\n✅ Gunakan /order untuk memesan.";

        // Kirim per chunk karena Telegram limit 4096 karakter
        $chunks = str_split($text, 4000);
        foreach ($chunks as $chunk) {
            $bot->sendMessage(
                text: $chunk,
                parse_mode: 'Markdown'
            );
        }
    }
}