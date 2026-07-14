<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$id = '100951';

// Test Store Settings
$response = Http::withoutVerifying()->withHeaders([
    'accept' => 'application/json, text/plain, */*',
    'store-identifier' => $id,
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
])->get('https://api.salla.dev/store/v1/store/settings');

echo "Settings Status: " . $response->status() . PHP_EOL;
echo "Settings Body: " . json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// Test Products
$response2 = Http::withoutVerifying()->withHeaders([
    'accept' => 'application/json, text/plain, */*',
    'store-identifier' => $id,
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
])->get('https://api.salla.dev/store/v1/products?limit=1');

echo "Products Status: " . $response2->status() . PHP_EOL;
echo "Products Body: " . substr($response2->body(), 0, 300) . PHP_EOL;
