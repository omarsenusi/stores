<?php

/**
 * Run this script via CLI to find the Chat ID of your Telegram group.
 * 
 * 1. Add your bot to the Telegram group.
 * 2. Send any message in the group.
 * 3. Run this script: php get_telegram_chat_id.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$botToken = '7261497209:AAFzaaMruLYSTUUl0_2SNwAJO70rb93-sVA';

echo "Fetching updates from Telegram...\n";

$response = Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/getUpdates");
$data = $response->json();

if (!$response->successful() || !isset($data['ok']) || !$data['ok']) {
    echo "Failed to connect to Telegram API or invalid token.\n";
    exit(1);
}

$updates = $data['result'];

if (empty($updates)) {
    echo "No recent messages found.\n";
    echo "Please go to your Telegram group 'salla stores scraper', send a message like 'Hello', and then run this script again.\n";
    exit(0);
}

$foundGroups = [];

foreach ($updates as $update) {
    if (isset($update['message']['chat'])) {
        $chat = $update['message']['chat'];
        if ($chat['type'] === 'group' || $chat['type'] === 'supergroup') {
            $foundGroups[$chat['id']] = $chat['title'] ?? 'Unknown Group';
        }
    }
}

if (empty($foundGroups)) {
    echo "Found messages, but none were from a group.\n";
    echo "Make sure the bot is added to your group and you have sent a message there recently.\n";
} else {
    echo "\nFound the following groups:\n";
    echo "===========================\n";
    foreach ($foundGroups as $chatId => $title) {
        echo "Group Name: {$title}\n";
        echo "Chat ID:    {$chatId}\n";
        echo "---------------------------\n";
    }
    
    echo "\nTo save this Chat ID to your database, run this command in tinker or directly use this ID in your code:\n";
    echo "\App\Models\Setting::where('key', 'telegram_chat_id')->update(['value' => 'YOUR_CHAT_ID']);\n";
}
