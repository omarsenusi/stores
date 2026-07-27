<?php

/**
 * SerpApi & Store ID Extraction Tester
 * Usage: php test_serpapi.php [SERPAPI_KEY]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = isset($argv[1]) ? trim($argv[1]) : getenv('SERPAPI_KEY');

echo "=== SerpApi & Store ID Extraction Tester ===\n";

function extractStoreId(string $url): ?string {
    try {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ar-SA,ar;q=0.9,en;q=0.8',
        ])->withoutVerifying()->timeout(15)->get($url);

        if ($response->failed()) {
            return null;
        }

        $html = $response->body();

        // Pattern 1: Salla store id in JS config: store: { id: 12345 } or "store":{"id":12345}
        if (preg_match('/["\']store["\']\s*:\s*\{[^}]*["\']id["\']\s*:\s*["\']?(\d+)["\']?/i', $html, $m)) {
            return $m[1];
        }

        // Pattern 2: storeId: 12345 or store_id = 12345
        if (preg_match('/(?:storeId|store_id|store-id)\s*[:=]\s*["\']?(\d+)["\']?/i', $html, $m)) {
            return $m[1];
        }

        // Pattern 3: meta tag <meta name="store-id" content="12345">
        if (preg_match('/<meta[^>]+name=["\']store-id["\'][^>]+content=["\'](\d+)["\']/i', $html, $m)) {
            return $m[1];
        }

        // Pattern 4: data-store-id="12345"
        if (preg_match('/data-store-id=["\'](\d+)["\']/i', $html, $m)) {
            return $m[1];
        }

        // Pattern 5: URL pattern salla.sa/xyz/12345
        if (preg_match('/salla\.sa\/[^\/]+\/(\d{4,10})/i', $html, $m)) {
            return $m[1];
        }

        return null;
    } catch (\Throwable $e) {
        return null;
    }
}

if (!$apiKey) {
    echo "Notice: No SerpApi Key passed. Testing Store ID extraction on sample Salla stores:\n\n";
    $testUrls = [
        'https://salla.sa/foodworldmarket',
        'https://salla.sa/remiperfumes',
        'https://salla.sa/dima-home',
    ];

    foreach ($testUrls as $url) {
        echo "Testing URL: {$url}\n";
        $storeId = extractStoreId($url);
        if ($storeId) {
            echo "--> SUCCESS! Extracted Store ID: {$storeId}\n\n";
        } else {
            echo "--> FAILED: Could not extract store ID.\n\n";
        }
    }

    echo "To test SerpApi search + extraction, run:\n";
    echo "php test_serpapi.php YOUR_SERPAPI_KEY\n";
    exit(0);
}

// If SerpApi Key is provided, perform live Google Search via SerpApi!
$query = 'site:salla.sa تمور';
$start = 0;
$page = 1;

echo "Searching Google via SerpApi for: {$query}\n\n";

while ($page <= 3) {
    echo "--- SerpApi Page {$page} (start={$start}) ---\n";

    $res = Http::withoutVerifying()->timeout(30)->get('https://serpapi.com/search.json', [
        'engine' => 'google',
        'q' => $query,
        'hl' => 'ar',
        'gl' => 'sa',
        'start' => $start,
        'num' => 10,
        'api_key' => $apiKey,
    ]);

    if ($res->failed()) {
        echo "SerpApi Error (HTTP " . $res->status() . "): " . $res->body() . "\n";
        break;
    }

    $json = $res->json();
    if (isset($json['error'])) {
        echo "SerpApi Error: " . $json['error'] . "\n";
        break;
    }

    $organic = $json['organic_results'] ?? [];
    echo "Google Results Found: " . count($organic) . "\n";

    if (empty($organic)) {
        echo "Reached end of Google search results.\n";
        break;
    }

    foreach ($organic as $i => $item) {
        $link = $item['link'] ?? null;
        $title = $item['title'] ?? 'N/A';
        echo "\n[" . ($start + $i + 1) . "] Title: {$title}\n";
        echo "    Link: {$link}\n";

        if ($link) {
            $storeId = extractStoreId($link);
            if ($storeId) {
                echo "    --> ✅ Store ID Extracted: {$storeId}\n";
            } else {
                echo "    --> ❌ Store ID not found in HTML\n";
            }
        }
    }

    $next = $json['serpapi_pagination']['next'] ?? null;
    if (!$next) {
        echo "\nEnd of pagination (no next page link).\n";
        break;
    }

    $start += 10;
    $page++;
    echo "\n--------------------------------------------------\n";
}
