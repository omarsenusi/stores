<?php
require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$concurrentRequests = 500;

echo "Testing Salla API Rate Limit...\n";
echo "Sending $concurrentRequests concurrent requests...\n";

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
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
    'x-requested-with' => 'XMLHttpRequest',
];

$startTime = microtime(true);

$responses = Http::pool(function (Pool $pool) use ($headers, $concurrentRequests) {
    $requests = [];
    for ($i = 0; $i < $concurrentRequests; $i++) {
        $storeId = 1481 + $i;
        $reqHeaders = array_merge($headers, ['store-identifier' => (string)$storeId]);
        
        $requests[] = $pool->as((string)$storeId)
                           ->withoutVerifying()
                           ->withOptions(['version' => 2.0])
                           ->withHeaders($reqHeaders)
                           ->get('https://api.salla.dev/store/v1/products', ['limit' => 4]);
    }
    return $requests;
});

$endTime = microtime(true);
$timeTaken = round($endTime - $startTime, 2);

$statusCounts = [];
foreach ($responses as $storeId => $response) {
    if ($response instanceof \Exception) {
        $status = 'Exception: ' . $response->getMessage();
    } else {
        $status = $response->status();
    }
    
    if (!isset($statusCounts[$status])) {
        $statusCounts[$status] = 0;
    }
    $statusCounts[$status]++;
}

echo "\nResults after $timeTaken seconds:\n";
foreach ($statusCounts as $status => $count) {
    echo "- Status $status: $count requests\n";
}

if (isset($statusCounts[429])) {
    echo "\n=> Rate limit hit! (429 Too Many Requests)\n";
} elseif (isset($statusCounts[403])) {
    echo "\n=> Cloudflare Blocked! (403 Forbidden)\n";
} else {
    echo "\n=> No rate limit hit at $concurrentRequests concurrent requests.\n";
}
