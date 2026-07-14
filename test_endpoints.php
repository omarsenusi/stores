<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

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
    'wallet', 'points', 'rewards', 'loyalty', 'affiliates', 'referrals'
];

echo "Testing Salla API Endpoints...\n";
echo "Wordlist size: " . count($endpoints) . " endpoints\n\n";

$headers = [
    'accept' => 'application/json, text/plain, */*',
    'accept-language' => 'ar',
    'cache-control' => 'no-cache',
    'currency' => 'SAR',
    'origin' => 'https://najd7.com',
    'priority' => 'u=1, i',
    'referer' => 'https://najd7.com/',
    's-anonymous-id' => 'a0112d9b-77c9-4f9c-b300-4ef13266460a',
    's-app-os' => 'browser',
    's-app-version' => '2.14.499',
    's-country' => 'EG',
    's-ray' => '50',
    's-source' => 'twilight',
    's-store-api-version' => 'swoole',
    's-user-id' => 'FfuSe8KENcaVTETwoNKfS0CLbCDXBTnNIvIDplKz',
    's-version-id' => '1307728351',
    'sec-ch-ua' => '"Not;A=Brand";v="8", "Chromium";v="150", "Google Chrome";v="150"',
    'sec-ch-ua-mobile' => '?0',
    'sec-ch-ua-platform' => '"Windows"',
    'sec-fetch-dest' => 'empty',
    'sec-fetch-mode' => 'cors',
    'sec-fetch-site' => 'cross-site',
    'store-identifier' => '1082915046', // Test on an active store identifier
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
    'x-requested-with' => 'XMLHttpRequest',
];

$validEndpoints = [];
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
            echo "[-] Error for $endpoint: " . $response->getMessage() . "\n";
            continue;
        }

        $status = $response->status();
        
        // Let's filter out 404 (Not Found) or 410 (Gone) or 405 (Method Not Allowed)
        // Anything else like 200, 417, 403, 401, 500 implies the endpoint might exist.
        if (!in_array($status, [404])) {
            $validEndpoints[$endpoint] = $status;
            echo "[+] Found endpoint: /store/v1/$endpoint -> Status: $status\n";
        }
    }
    
    // Slight delay to avoid rate limiting
    sleep(1);
}

echo "\nSummary of discovered endpoints:\n";
foreach ($validEndpoints as $ep => $st) {
    echo str_pad("/store/v1/$ep", 30) . " : HTTP $st\n";
}

if (empty($validEndpoints)) {
    echo "No valid endpoints found in the wordlist.\n";
}
