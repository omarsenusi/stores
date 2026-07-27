<?php

/**
 * System Exclusion Filter & Salla Store Resolution Tester
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$testUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
    'https://app-maker.salla.sa/',
    'https://salla.sa/appstore-sa',
    'https://salla.sa/developer',
    'https://community.salla.sa/',
];

$excludedSallaSystemNames = [
    '', 'app-maker', 'app-maker-sa', 'appstore', 'appstore-sa', 'apps',
    'developer', 'developers', 'community', 'help', 'blog', 'privacy',
    'terms', 'complaint', 'affiliates', 'accounts', 'auth', 'admin',
    'dashboard', 'merchant', 'partner', 'partners', 'demo', 'api', 's',
    'support', 'status', 'insights', 'academy', 'learn', 'design', 'theme', 'themes',
];

echo "=== System Exclusion Filter & Twilight API Resolution Test ===\n\n";

foreach ($testUrls as $url) {
    echo "--- Testing URL: {$url} ---\n";

    // 1. Normalize Domain / Extract Slug
    $parts = parse_url($url);
    $host = isset($parts['host']) ? strtolower(trim($parts['host'])) : '';
    $host = preg_replace('/^www\./', '', $host);
    $path = isset($parts['path']) ? trim($parts['path'], '/') : '';

    $slug = null;

    if (str_ends_with($host, '.salla.sa')) {
        $sub = str_replace('.salla.sa', '', $host);
        if (! in_array($sub, $excludedSallaSystemNames, true)) {
            $slug = $sub;
        }
    } elseif ($host === 'salla.sa') {
        $pathSegments = explode('/', $path);
        $first = strtolower($pathSegments[0] ?? '');
        if (! empty($first) && ! in_array($first, $excludedSallaSystemNames, true)) {
            $slug = $first;
        }
    }

    if (! $slug) {
        echo "   [EXCLUDED] System Page or Invalid Store URL -> Ignored cleanly!\n";
        echo "--------------------------------------------------\n\n";

        continue;
    }

    echo "   [VALID STORE SLUG] Extracted: {$slug}\n";

    // 2. Query Twilight API (api.salla.dev)
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
        's-ray' => '50',
        's-source' => 'twilight',
        's-store-api-version' => 'swoole',
        'store-identifier' => $slug,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'x-requested-with' => 'XMLHttpRequest',
    ])->get('https://api.salla.dev/store/v1/store/settings');

    echo '   Twilight API Status: '.$response->status()."\n";

    if ($response->successful()) {
        $json = $response->json();
        $store = $json['data']['store'] ?? null;
        $storeId = $store['id'] ?? null;
        $storeName = $store['name'] ?? ($store['meta']['title'] ?? null);
        $storeUrl = $json['data']['jitsu']['track_url'] ?? ($store['url'] ?? null);

        if ($storeId) {
            echo "   --> SUCCESS! Store ID: {$storeId} | Name: {$storeName} | URL: {$storeUrl}\n";
        } else {
            echo "   --> Store ID not found in JSON response.\n";
        }
    } else {
        echo '   --> API call failed: '.substr($response->body(), 0, 150)."\n";
    }

    echo "--------------------------------------------------\n\n";
}
