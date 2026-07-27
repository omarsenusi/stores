<?php

/**
 * Testing Salla Twilight API (api.salla.dev) for Store Resolution & Store ID Extraction
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$slugs = ['kawnroaster', 'caltpro', 'bassamtune', 'mzajistore'];

echo "=== Salla Twilight API (api.salla.dev) Resolution Test ===\n\n";

foreach ($slugs as $slug) {
    echo "--- Resolving Store Identifier / Slug: {$slug} ---\n";

    $response = Http::withoutVerifying()->withOptions([
        'version' => 2.0,
    ])->withHeaders([
        'accept' => 'application/json, text/plain, */*',
        'accept-language' => 'ar',
        'cache-control' => 'no-cache',
        'currency' => 'SAR',
        's-anonymous-id' => 'a0112d9b-77c9-4f9c-b300-4ef13266460a',
        's-app-os' => 'browser',
        's-app-version' => '2.14.499',
        's-country' => 'EG',
        's-source' => 'twilight',
        's-store-api-version' => 'swoole',
        'store-identifier' => $slug,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'x-requested-with' => 'XMLHttpRequest',
    ])->get('https://api.salla.dev/store/v1/store/settings');

    echo 'API HTTP Status: '.$response->status()."\n";

    if ($response->successful()) {
        $json = $response->json();
        $store = $json['data']['store'] ?? null;
        $storeId = $store['id'] ?? null;
        $storeName = $store['name'] ?? ($store['meta']['title'] ?? null);
        $storeUrl = $json['data']['jitsu']['track_url'] ?? ($store['url'] ?? null);

        if ($storeId) {
            echo "--> SUCCESS! Store ID: {$storeId} | Name: {$storeName} | URL: {$storeUrl}\n";
        } else {
            echo "--> Store ID not found in JSON response.\n";
        }
    } else {
        echo '--> API call failed: '.substr($response->body(), 0, 200)."\n";
    }

    echo "--------------------------------------------------\n\n";
}
