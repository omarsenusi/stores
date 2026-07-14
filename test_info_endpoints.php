<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Expanded wordlist for store profile/info endpoints
$endpoints = [
    'info', 'store-info', 'profile', 'me', 'app', 'config', 'details', 'metadata', 
    'meta', 'summary', 'general', 'about', 'contact', 'store-profile', 'store', 
    'tenant', 'account', 'merchant', 'site', 'website', 'portal', 'app-info', 
    'context', 'global', 'initial', 'bootstrap', 'startup', 'data', 'setup', 
    'identity', 'brand', 'logo', 'design', 'theme-settings', 'theme', 'store-settings',
    'app/details', 'store/info', 'store/profile', 'store/settings', 'store/config',
    'user', 'user/info', 'user/profile', 'owner', 'company', 'business', 'legal',
    'information', 'store_info', 'store_profile', 'store_settings', 'details/all',
    'init', 'boot', 'session', 'auth/me', 'auth/profile', 'auth/info', 'auth/user'
];

echo "Hunting for Store Info API Endpoints...\n";
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
    'store-identifier' => '1082915046', // Active store ID
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'x-requested-with' => 'XMLHttpRequest',
];

$validEndpoints = [];
$chunkedEndpoints = array_chunk($endpoints, 20); // 20 concurrent requests at a time

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
            continue;
        }

        $status = $response->status();
        
        // Let's filter out 404 and 410 which mean 'not found' or 'gone'
        if (!in_array($status, [404, 410])) {
            $validEndpoints[$endpoint] = $status;
            echo "[+] Found endpoint: /store/v1/$endpoint -> Status: $status\n";
            
            // If it returns 200, let's dump a tiny bit of the payload to see if it has logo/name
            if ($status === 200) {
                $body = substr($response->body(), 0, 300); // get first 300 chars
                echo "    -> Payload snippet: " . str_replace("\n", " ", $body) . "\n\n";
            }
        }
    }
    
    // Slight delay to avoid rate limiting
    sleep(1);
}

echo "\nSummary of discovered info endpoints:\n";
foreach ($validEndpoints as $ep => $st) {
    echo str_pad("/store/v1/$ep", 30) . " : HTTP $st\n";
}

if (empty($validEndpoints)) {
    echo "No valid endpoints found in the wordlist.\n";
}
