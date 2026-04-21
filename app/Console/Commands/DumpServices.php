<?php

namespace App\Console\Commands;

use App\Services\LollipopSmmService;
use Illuminate\Console\Command;

class DumpServices extends Command
{
    protected $signature   = 'services:dump {platform=all}';
    protected $description = 'Dump daftar service dari Lollipop ke file';

    public function handle(): void
    {
        $this->info('Mengambil data service...');

        $lollipop = new LollipopSmmService();
        $services = $lollipop->getServices();

        if (!$services) {
            $this->error('Gagal mengambil service. Cek API key di .env');
            return;
        }

        $platform = strtolower($this->argument('platform'));

        $filtered = collect($services)->filter(function ($s) use ($platform) {
            if ($platform === 'all') return true;
            return str_contains(strtolower($s['category']), $platform) ||
                   str_contains(strtolower($s['name']), $platform);
        });

        $lines = ["ID\t| NAMA\t| HARGA/1000\t| MIN\t| MAX\t| KATEGORI\n"];
        $lines[] = str_repeat('-', 100) . "\n";

        foreach ($filtered as $s) {
            $lines[] = "{$s['service']}\t| {$s['name']}\t| Rp {$s['rate']}\t| {$s['min']}\t| {$s['max']}\t| {$s['category']}\n";
        }

        $filename = storage_path("services_{$platform}.txt");
        file_put_contents($filename, implode('', $lines));

        $this->info("✅ {$filtered->count()} service disimpan ke: {$filename}");
    }
}