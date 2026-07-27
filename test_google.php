<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

$url = 'https://salla.sa/bassamtune';

echo "--- Testing URL: {$url} ---\n";

$res = Http::withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language' => 'ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
])->withoutVerifying()->timeout(12)->get($url);

echo 'Status: '.$res->status().' | Length: '.strlen($res->body())."\n";
$html = $res->body();

if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $html, $m)) {
    echo 'Pattern 1 Store ID: '.$m[1]."\n";
}

if (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $html, $m)) {
    echo 'Pattern 2 Store ID: '.$m[1]."\n";
}

if (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $html, $m)) {
    echo 'Pattern 3 Store ID: '.$m[1]."\n";
}
