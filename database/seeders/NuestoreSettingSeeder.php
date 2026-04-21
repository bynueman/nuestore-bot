<?php

namespace Database\Seeders;

use App\Models\NuestoreSetting;
use Illuminate\Database\Seeder;

class NuestoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'   => 'global_markup_multiplier',
                'value' => '2.0',
            ],
            [
                'key'   => 'min_balance_alert',
                'value' => '50000',
            ],
            [
                'key'   => 'max_retry_count',
                'value' => '5',
            ],
            [
                'key'   => 'whitelisted_service_ids',
                'value' => '659,12,20,882,16,843,820,50,177,401,821,1,751,876,232,767,611,729,961,115,728,785,730,609',
            ],
        ];

        foreach ($settings as $setting) {
            NuestoreSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}