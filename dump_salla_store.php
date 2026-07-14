<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$storeId = '1082915046';

// List of endpoints we confirmed are working (returning 200)
$endpoints = [
    'store/settings',
    'settings',
    'coupons',
    'languages',
    'categories',
    'reviews',
    'products',
    'brands',
    'offers',
    'branches'
];

$headers = [
    'accept' => 'application/json',
    'accept-language' => 'ar',
    'currency' => 'SAR',
    'store-identifier' => $storeId,
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
    'x-requested-with' => 'XMLHttpRequest',
];

echo "Dumping data for Store ID: {$storeId}...\n";

$storeData = [];

$responses = Http::pool(function (Pool $pool) use ($headers, $endpoints) {
    $requests = [];
    foreach ($endpoints as $endpoint) {
        $requests[] = $pool->as($endpoint)
                           ->withoutVerifying()
                           ->withOptions(['version' => 2.0])
                           ->withHeaders($headers)
                           ->get("https://api.salla.dev/store/v1/{$endpoint}");
    }
    return $requests;
});

foreach ($responses as $endpoint => $response) {
    if ($response instanceof \Exception) {
        echo "[-] Error for /store/v1/$endpoint: " . $response->getMessage() . "\n";
        continue;
    }

    $status = $response->status();
    
    if ($status === 200) {
        echo "[+] Successfully fetched data from: /store/v1/$endpoint\n";
        $storeData[$endpoint] = $response->json();
    } else {
        echo "[-] Failed to fetch data from: /store/v1/$endpoint (Status: $status)\n";
    }
}

$outputFile = __DIR__ . "/salla_store_{$storeId}_dump.json";
file_put_contents($outputFile, json_encode($storeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\nDone! All data has been saved to:\n{$outputFile}\n";
