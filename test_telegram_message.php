<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$botToken = '7261497209:AAFzaaMruLYSTUUl0_2SNwAJO70rb93-sVA';
$chatId = '-5389164818';

$text = "🚀 *مرحباً!* \n\nتم ربط البوت ببرنامج الساحب الآلي (Scraper) بنجاح ✅\n\nسنبدأ بإرسال المتاجر الجديدة هنا فور اكتشافها!";

$response = Http::withoutVerifying()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
    'chat_id' => $chatId,
    'text' => $text,
    'parse_mode' => 'Markdown',
]);

if ($response->successful()) {
    echo "Message sent successfully!\n";
} else {
    echo "Failed to send message: " . $response->body() . "\n";
}
