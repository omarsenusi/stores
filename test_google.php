<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$urls = [
    'https://demostore.salla.sa',
    'https://complaint.salla.sa',
];

foreach ($urls as $url) {
    echo "--- Testing Subdomain URL: {$url} ---\n";
    $res = Http::withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ])->withoutVerifying()->get($url);

    echo "Status: " . $res->status() . " | Length: " . strlen($res->body()) . "\n";
    $html = $res->body();
    file_put_contents(__DIR__ . '/salla_subdomain.html', $html);

    // Look for Salla Store ID in HTML
    if (preg_match_all('/(?:store_id|storeId|merchant_id|merchantId|store)\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $html, $m)) {
        echo "Found IDs:\n";
        print_r(array_slice(array_unique($m[1]), 0, 10));
    }

    if (preg_match_all('/"id"\s*:\s*(\d{5,10})/', $html, $m3)) {
        echo "Found JSON IDs:\n";
        print_r(array_slice(array_unique($m3[1]), 0, 10));
    }
}
