<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'last_store_id'],
            ['value' => '1061244541']
        );

        Setting::updateOrCreate(
            ['key' => 'telegram_chat_id'],
            ['value' => '-5389164818']
        );
    }
}
