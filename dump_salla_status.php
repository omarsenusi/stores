<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$storeId = '1082915046';

// An extensive list of possible endpoints we gathered from previous runs
$endpoints = [
    'products', 'categories', 'brands', 'tags', 'settings', 'setting',
    'users', 'customers', 'cart', 'checkout', 'orders', 'invoices',
    'coupons', 'discounts', 'pages', 'blogs', 'articles', 'auth',
    'login', 'register', 'profile', 'shipping', 'payment', 'reviews',
    'ratings', 'wishlist', 'favorites', 'currency', 'languages',
    'locations', 'branches', 'contact', 'search', 'banners', 'sliders',
    'themes', 'apps', 'plugins', 'webhooks', 'notifications', 'analytics',
    'reports', 'taxes', 'offers', 'promotions', 'social', 'info',
    'about', 'faq', 'terms', 'privacy', 'store', 'cities', 'countries',
    'regions', 'zones', 'variants', 'options', 'attributes', 'manufacturers',
    'home', 'main', 'layout', 'header', 'footer', 'menu', 'navigation',
    'features', 'services', 'testimonials', 'gallery', 'media', 'files',
    'uploads', 'images', 'videos', 'documents', 'downloads', 'subscriptions',
    'plans', 'pricing', 'tickets', 'support', 'messages', 'chat', 'conversations',
    'wallet', 'points', 'rewards', 'loyalty', 'affiliates', 'referrals',
    'store/settings' // The special one we found
];

$headers = [
    'accept' => 'application/json, text/plain, */*',
    'accept-language' => 'ar',
    'cache-control' => 'no-cache',
    'currency' => 'SAR',
    'store-identifier' => $storeId,
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
];

echo "Testing Endpoints for Store ID: {$storeId}...\n";

$endpointStatuses = [];
$chunkedEndpoints = array_chunk($endpoints, 30); // 30 concurrent requests at a time

foreach ($chunkedEndpoints as $chunk) {
    $responses = Http::pool(function (Pool $pool) use ($headers, $chunk) {
        $requests = [];
        foreach ($chunk as $endpoint) {
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
            $endpointStatuses[$endpoint] = 'Error: ' . $response->getMessage();
            continue;
        }
        
        $endpointStatuses[$endpoint] = $response->status();
        echo "Endpoint: /store/v1/$endpoint -> Status: {$response->status()}\n";
    }
    
    sleep(1); // small delay between chunks
}

$outputFile = __DIR__ . "/salla_endpoints_status_dump.json";
file_put_contents($outputFile, json_encode($endpointStatuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\nDone! Saved just the statuses to:\n{$outputFile}\n";
