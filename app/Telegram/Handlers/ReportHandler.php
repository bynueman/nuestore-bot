<?php

namespace App\Telegram\Handlers;

use App\Models\NuestoreTransaction;
use Carbon\Carbon;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Input\InputFile;

class ReportHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('📅 Hari Ini',   callback_data: 'report_period:today'),
                InlineKeyboardButton::make('📅 Minggu Ini', callback_data: 'report_period:week'),
            )
            ->addRow(
                InlineKeyboardButton::make('📅 Bulan Ini',  callback_data: 'report_period:month'),
                InlineKeyboardButton::make('📅 Semua',      callback_data: 'report_period:all'),
            );

        $bot->sendMessage(
            text: "📊 *Laporan Nuestore*\n\nPilih periode laporan:",
            parse_mode: 'Markdown',
            reply_markup: $keyboard
        );
    }

    public static function handleCallback(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data ?? '';

        if (!str_starts_with($data, 'report_period:')) {
            $bot->answerCallbackQuery();
            return;
        }

        $period = str_replace('report_period:', '', $data);
        $bot->answerCallbackQuery(text: '⏳ Memproses laporan...');

        // Tentukan rentang tanggal
        $now   = Carbon::now();
        $query = NuestoreTransaction::query();

        $periodLabel = match($period) {
            'today' => 'Hari Ini — ' . $now->format('d/m/Y'),
            'week'  => 'Minggu Ini — ' . $now->startOfWeek()->format('d/m') . ' s/d ' . Carbon::now()->endOfWeek()->format('d/m/Y'),
            'month' => $now->translatedFormat('F Y'),
            'all'   => 'Semua Waktu',
            default => 'Semua Waktu',
        };

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'week':
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereYear('created_at', $now->year)
                      ->whereMonth('created_at', $now->month);
                break;
            // 'all' — no filter
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        if ($transactions->isEmpty()) {
            $bot->sendMessage(
                text: "📊 *Laporan: {$periodLabel}*\n\nBelum ada data order pada periode ini.",
                parse_mode: 'Markdown'
            );
            return;
        }

        // Hitung statistik
        $total      = $transactions->count();
        $completed  = $transactions->where('status', 'COMPLETED')->count();
        $processing = $transactions->where('status', 'PROCESSING')->count();
        $failed     = $transactions->whereIn('status', ['FAILED_PROVIDER', 'CANCELED'])->count();

        $totalTagih  = $transactions->sum('amount_paid');
        $totalModal  = $transactions->sum('modal_cost');
        $totalProfit = $transactions->sum('profit_estimated');

        $tagiFormat   = number_format($totalTagih, 0, ',', '.');
        $modalFormat  = number_format($totalModal, 0, ',', '.');
        $profitFormat = number_format($totalProfit, 0, ',', '.');

        // Teks ringkasan
        $summary = "📊 *LAPORAN NUESTORE — {$periodLabel}*\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━\n"
                 . "Total Order    : {$total}\n"
                 . "✅ Completed   : {$completed}\n"
                 . "⏳ Processing  : {$processing}\n"
                 . "❌ Failed      : {$failed}\n\n"
                 . "💰 Total Tagih : Rp {$tagiFormat}\n"
                 . "📦 Total Modal : Rp {$modalFormat}\n"
                 . "📈 Total Profit: Rp {$profitFormat}\n"
                 . "━━━━━━━━━━━━━━━━━━━━━━━";

        $bot->sendMessage(text: $summary, parse_mode: 'Markdown');

        // Generate CSV
        $csvFilename = 'laporan_' . str_replace([' ', '/'], ['_', '-'], strtolower($periodLabel)) . '_' . Carbon::now()->format('Ymd_His') . '.csv';
        $csvPath     = storage_path("app/{$csvFilename}");

        $csvHandle = fopen($csvPath, 'w');

        // BOM for UTF-8 Excel compatibility
        fprintf($csvHandle, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header
        fputcsv($csvHandle, [
            'Order ID',
            'Provider Order ID',
            'Service ID',
            'Link Target',
            'Quantity',
            'Catatan Pelanggan',
            'Harga Tagih (Rp)',
            'Modal (Rp)',
            'Est. Profit (Rp)',
            'Status',
            'Tanggal',
        ]);

        foreach ($transactions as $t) {
            fputcsv($csvHandle, [
                $t->id,
                $t->provider_order_id ?? '-',
                $t->service_id,
                $t->target_link,
                $t->quantity ?? '-',
                $t->customer_note ?? '-',
                $t->amount_paid,
                $t->modal_cost,
                $t->profit_estimated ?? 0,
                $t->status,
                $t->created_at->format('d/m/Y H:i'),
            ]);
        }

        fclose($csvHandle);

        // Kirim file CSV ke bot
        $bot->sendDocument(
            document: InputFile::make($csvPath, $csvFilename),
            caption: "📎 *{$csvFilename}*\n{$total} order | Rp {$tagiFormat} omzet",
            parse_mode: 'Markdown'
        );

        // Hapus file setelah terkirim
        @unlink($csvPath);
    }
}
