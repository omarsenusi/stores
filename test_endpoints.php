<?php

require __DIR__.'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// -----------------------------------------------------------------------
// Helper: Fetch Webshare Proxies
// -----------------------------------------------------------------------
function getWebshareProxies(string $apiKey): array
{
    $cacheFile = __DIR__.'/.webshare_proxies_cache';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $json = json_decode(file_get_contents($cacheFile), true);
        if (! empty($json)) {
            echo '✅ Loaded '.count($json)." proxies from Webshare cache.\n";

            return $json;
        }
    }

    echo "🔄 Fetching fresh proxies from Webshare API...\n";
    $response = Http::withoutVerifying()->withHeaders([
        'Authorization' => 'Token '.$apiKey,
    ])->get('https://proxy.webshare.io/api/v2/proxy/list/?mode=direct&page=1&page_size=250');

    if ($response->successful()) {
        $results = $response->json()['results'] ?? [];
        $proxies = [];
        foreach ($results as $p) {
            if (! empty($p['valid'])) {
                $proxies[] = sprintf(
                    'http://%s:%s@%s:%d',
                    $p['username'],
                    $p['password'],
                    $p['proxy_address'],
                    $p['port']
                );
            }
        }
        if (! empty($proxies)) {
            file_put_contents($cacheFile, json_encode($proxies));
            echo '✅ Successfully fetched '.count($proxies)." proxies from Webshare!\n";

            return $proxies;
        }
    }

    echo '⚠️ Failed to fetch Webshare proxies: '.$response->status()."\n";

    return [];
}

$webshareApiKey = '3d0ci86gwiokksux2yi5ajzuh80k3fd55st3av3k';
$proxies = getWebshareProxies($webshareApiKey);

// -----------------------------------------------------------------------
// Helper: Get fresh Mahally token (Cache or Playwright)
// -----------------------------------------------------------------------
function getMahallyToken(): string
{
    $tokenFile = __DIR__.'/.mahally_token_cache';
    $tokenExpiry = 500;

    if (file_exists($tokenFile) && (time() - filemtime($tokenFile)) < $tokenExpiry) {
        $token = trim(file_get_contents($tokenFile));
        if (! empty($token) && str_contains($token, '.')) {
            echo "✅ Mahally token loaded from cache.\n";

            return $token;
        }
    }

    echo "🔄 Fetching fresh Mahally token via Playwright (get_mahally_token.js)...\n";
    $nodeScript = __DIR__.'/get_mahally_token.js';
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open("node \"{$nodeScript}\"", $descriptors, $pipes);

    if ($proc) {
        $token = trim(stream_get_contents($pipes[1]));
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        if (! empty($token) && ! str_starts_with($token, 'ERROR') && str_contains($token, '.')) {
            file_put_contents($tokenFile, $token);
            echo "✅ Fresh Mahally token obtained and cached!\n";

            return $token;
        }
        echo "⚠️ Playwright failed: {$stderr}\n";
    }

    throw new RuntimeException('Failed to obtain Mahally Bearer token.');
}

$mahallyToken = getMahallyToken();

// Array of API prefixes/namespaces to test
$apiPrefixes = [
    'mahally',
    'store',
    'stores',
    'merchant',
    'admin',
    'users',
    'vendors',
    'sellers',
    'marketplace',
    'client',
    'customer',
    'partner',
];

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
    'stores/search', 'stores/selected', 'stores', 'products/search',
    'stores/all', 'stores/get-all', 'stores/list', 'stores/index',
    'stores/featured', 'stores/popular', 'stores/trending', 'stores/browse',
    'stores/directory', 'stores/catalog', 'stores/top', 'stores/active',
    'stores/latest', 'stores/recommended', 'stores/nearby', 'stores/filter',
];

$headers = [
    'accept' => 'application/json',
    'accept-language' => 'ar',
    'authorization' => 'Bearer '.$mahallyToken,
    'currency' => 'SAR',
    'mahly-app-version' => '5.4.3',
    'mahly-environment' => 'production',
    'origin' => 'https://mahally.com',
    'referer' => 'https://mahally.com/',
    's-app-name' => 'mahly',
    's-app-version' => '3.3.9',
    's-no-cache' => '',
    's-source' => 'web',
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
    'store-identifier' => '1082915046',
];

$discoveredResults = [];
$proxyIndex = 0;

echo "=======================================================\n";
echo 'Testing '.count($apiPrefixes).' API Prefixes with '.count($endpoints)." Endpoints using Proxies\n";
echo "=======================================================\n\n";

foreach ($apiPrefixes as $prefix) {
    echo "🔍 Testing Prefix: /{$prefix}/v1/ ...\n";
    $validForPrefix = [];
    $chunkedEndpoints = array_chunk($endpoints, 25);

    foreach ($chunkedEndpoints as $chunk) {
        // Pick a proxy for this chunk
        $currentProxy = null;
        if (! empty($proxies)) {
            $currentProxy = $proxies[$proxyIndex % count($proxies)];
            $proxyIndex++;
        }

        $responses = Http::pool(function (Pool $pool) use ($headers, $prefix, $chunk, $currentProxy) {
            $requests = [];
            foreach ($chunk as $endpoint) {
                $req = $pool->as($endpoint)
                    ->withoutVerifying()
                    ->withOptions(['version' => 2.0])
                    ->withHeaders($headers);

                if ($currentProxy) {
                    $req->withOptions(['proxy' => $currentProxy]);
                }

                $requests[] = $req->get("https://api.salla.dev/{$prefix}/v1/{$endpoint}");
            }

            return $requests;
        });

        foreach ($responses as $endpoint => $response) {
            if ($response instanceof Exception) {
                continue;
            }
            $status = $response->status();
            // Filter out 404
            if (! in_array($status, [404])) {
                $validForPrefix[$endpoint] = $status;
                echo "[+] /{$prefix}/v1/{$endpoint} -> HTTP {$status}\n";
            }
        }
        usleep(200000);
    }

    $discoveredResults[$prefix] = $validForPrefix;
    echo "\n";
}

// =======================================================================
// SUMMARY
// =======================================================================
echo "=======================================================\n";
echo "SUMMARY OF DISCOVERED ENDPOINTS BY PREFIX\n";
echo "=======================================================\n\n";

foreach ($discoveredResults as $prefix => $results) {
    echo "--- /{$prefix}/v1/ (".count($results)." endpoints found) ---\n";
    if (empty($results)) {
        echo "No non-404 endpoints found.\n\n";

        continue;
    }
    foreach ($results as $ep => $st) {
        echo str_pad("/{$prefix}/v1/{$ep}", 40).' : HTTP '.$st."\n";
    }
    echo "\n";
}
