<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing with Guzzle default (HTTP/1.1)...\n";
$response = Http::withHeaders([
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
    'store-identifier' => '1481',
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
    'x-requested-with' => 'XMLHttpRequest',
])->get('https://api.salla.dev/store/v1/products', ['limit' => 4]);

echo "Status: " . $response->status() . "\n";
echo "Body snippet: " . substr($response->body(), 0, 100) . "\n\n";

echo "Testing with Guzzle HTTP/2...\n";
$response2 = Http::withOptions(['version' => 2.0])->withHeaders([
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
    'store-identifier' => '1481',
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
    'x-requested-with' => 'XMLHttpRequest',
])->get('https://api.salla.dev/store/v1/products', ['limit' => 4]);

echo "Status: " . $response2->status() . "\n";
echo "Body snippet: " . substr($response2->body(), 0, 100) . "\n\n";

