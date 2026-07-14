<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['key' => 'last_store_id'],
            ['value' => '1061244541']
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'telegram_chat_id'],
            ['value' => '-5389164818']
        );
    }
}
