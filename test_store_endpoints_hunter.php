<?php

/**
 * MAHALLY FULL STORE HARVESTER
 * ===================================================
 * Strategy:
 *  1. Get fresh token via Playwright (auto)
 *  2. Fetch /mahally/v1/home → 22 sections → extract store IDs
 *  3. Fetch /mahally/v1/categories → search each by name
 *  4. Search ~100 Arabic/English keywords via /stores/search
 *  5. Batch fetch full details via /stores/selected (20 at a time)
 *  6. Save everything to mahally_stores_harvest.json
 *
 * Usage: php test_store_endpoints_hunter.php [--save-db]
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

use App\Models\ScrapedStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$saveDb = in_array('--save-db', $argv);

// -----------------------------------------------------------------------
// STEP 0: Get fresh token via Playwright
// -----------------------------------------------------------------------
echo "=== [0] Getting fresh Mahally token via Playwright ===\n";

$tokenFile = __DIR__.'/.mahally_token_cache';
$tokenExpiry = 500; // refresh if older than 500s
$token = null;

// Try cache first
if (file_exists($tokenFile) && (time() - filemtime($tokenFile)) < $tokenExpiry) {
    $token = trim(file_get_contents($tokenFile));
    if (! empty($token) && str_contains($token, '.')) {
        echo '✅ Token loaded from cache ('.(time() - filemtime($tokenFile))."s old)\n";
    } else {
        $token = null;
    }
}

// Get fresh token via Playwright
if (! $token) {
    echo "🔄 Running Playwright to capture fresh token...\n";
    $nodeScript = __DIR__.'/get_mahally_token.js';
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open("node \"{$nodeScript}\"", $descriptors, $pipes);
    if ($proc) {
        $token = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $token = trim($token);
        if (! empty($token) && ! str_starts_with($token, 'ERROR') && str_contains($token, '.')) {
            file_put_contents($tokenFile, $token);
            echo "✅ Fresh token obtained and cached!\n";
        } else {
            $token = null;
            echo "⚠️ Playwright failed: {$stderr}\n";
        }
    }
}

if (! $token) {
    exit("❌ Could not get any token. Exiting.\n");
}

echo 'Token: '.substr($token, 0, 50)."...\n\n";

// -----------------------------------------------------------------------
// HTTP Client
// -----------------------------------------------------------------------
$h = Http::withoutVerifying()->withOptions(['version' => 2.0])->withHeaders([
    'accept' => 'application/json',
    'accept-language' => 'ar',
    'authorization' => 'Bearer '.$token,
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
]);

$allStoreIds = [];

// -----------------------------------------------------------------------
// STEP 1: Extract all store IDs from /home sections
// -----------------------------------------------------------------------
echo "=== [1] Fetching /mahally/v1/home sections ===\n";

$homeRes = $h->get('https://api.salla.dev/mahally/v1/home');
if ($homeRes->successful()) {
    $sections = $homeRes->json()['data'] ?? [];
    echo '  Found '.count($sections)." sections\n";
    foreach ($sections as $section) {
        $title = $section['title'] ?? $section['section_name'] ?? '?';
        $items = $section['items'] ?? [];
        $sectionIds = [];
        foreach ($items as $item) {
            $storeId = $item['id'] ?? $item['store_id'] ?? null;
            if ($storeId) {
                $sectionIds[] = (string) $storeId;
            }
            // Items might be nested
            if (isset($item['stores'])) {
                foreach ($item['stores'] as $s) {
                    if ($id = $s['id'] ?? null) {
                        $sectionIds[] = (string) $id;
                    }
                }
            }
        }
        $allStoreIds = array_values(array_unique(array_merge($allStoreIds, $sectionIds)));
        echo "  Section: {$title} | Items: ".count($items).' | Store IDs: '.count($sectionIds)."\n";
    }
} else {
    echo '  ❌ Failed: HTTP '.$homeRes->status()."\n";
}
echo '  Total unique IDs so far: '.count($allStoreIds)."\n\n";

// -----------------------------------------------------------------------
// STEP 2: Search by all 19 category names
// -----------------------------------------------------------------------
echo "=== [2] Fetching categories and searching each ===\n";

$catRes = $h->get('https://api.salla.dev/mahally/v1/categories');
$categories = $catRes->successful() ? ($catRes->json()['data'] ?? []) : [];
echo '  Found '.count($categories)." categories\n";

foreach ($categories as $cat) {
    $catName = $cat['name'] ?? '';
    if (! $catName) {
        continue;
    }

    $catCount = 0;
    for ($page = 1; $page <= 5; $page++) {
        $res = $h->get('https://api.salla.dev/mahally/v1/stores/search/', ['q' => $catName, 'page' => $page]);
        if ($res->successful()) {
            $stores = $res->json()['data'] ?? [];
            if (empty($stores)) {
                break;
            }
            foreach ($stores as $s) {
                if ($id = $s['id'] ?? null) {
                    $allStoreIds[] = (string) $id;
                }
            }
            $allStoreIds = array_values(array_unique($allStoreIds));
            $catCount += count($stores);
        } else {
            break;
        }
        usleep(100000);
    }
    echo "  [{$catName}] → {$catCount} stores across pages | Total unique: ".count($allStoreIds)."\n";
}
echo "\n";

// -----------------------------------------------------------------------
// STEP 3: Search by Arabic + English keywords (with pagination)
// -----------------------------------------------------------------------
echo "=== [3] Keyword search sweep with Pagination ===\n";

$keywords = [
    // Arabic
    'عطور', 'عود', 'مسك', 'بخور', 'ملابس', 'أزياء', 'عباية', 'حجاب',
    'مجوهرات', 'ذهب', 'فضة', 'ساعات', 'حقائب', 'أحذية', 'إكسسوارات',
    'تجميل', 'مكياج', 'عناية', 'شعر', 'بشرة', 'صحة', 'رياضة',
    'إلكترونيات', 'هواتف', 'كمبيوتر', 'منزل', 'ديكور', 'مطبخ',
    'أطفال', 'ألعاب', 'هدايا', 'حلويات', 'قهوة', 'تمر', 'عسل',
    'زعفران', 'أعشاب', 'كتب', 'قرطاسية', 'مركبات', 'سيارة',
    'سفر', 'حيوانات', 'نباتات', 'مزرعة', 'بناء', 'مقاولات',
    'خياطة', 'تصوير', 'تصميم', 'برمجة', 'تعليم', 'دورات',
    'أثاث', 'سجاد', 'ستائر', 'إضاءة', 'حديقة', 'نظارات',
    'رجالي', 'نسائي', 'أطفال', 'رضع', 'مدرسة', 'جامعة',
    'رمضان', 'عيد', 'زواج', 'مناسبات', 'دعوة',

    // English
    'fashion', 'abaya', 'perfume', 'oud', 'beauty', 'skincare',
    'jewelry', 'watches', 'bags', 'shoes', 'accessories',
    'electronics', 'phones', 'food', 'coffee', 'honey', 'dates',
    'sports', 'gym', 'health', 'kids', 'toys', 'books',
    'decor', 'furniture', 'garden', 'gifts', 'travel',
    'handmade', 'organic', 'natural', 'luxury', 'premium',
    'chocolate', 'cake', 'bakery', 'sweets',

    // Short 1-2 letter common store name prefixes (Arabic)
    'سلة', 'متجر', 'مول', 'بوتيك', 'محل', 'دكان', 'معرض',

    // Saudi/Gulf brands
    'نون', 'جرير', 'بنده', 'لولو', 'اكسترا', 'دانوب',
    'سافكو', 'سابك', 'أرامكو',
];

$keywords = array_values(array_unique($keywords));
$found = 0;

foreach ($keywords as $kw) {
    for ($page = 1; $page <= 5; $page++) {
        $res = $h->get('https://api.salla.dev/mahally/v1/stores/search/', ['q' => $kw, 'page' => $page]);
        if ($res->successful()) {
            $stores = $res->json()['data'] ?? [];
            if (empty($stores)) {
                break;
            }
            $newIds = array_map(fn ($s) => (string) ($s['id'] ?? ''), $stores);
            $newIds = array_filter($newIds);
            $before = count($allStoreIds);
            $allStoreIds = array_values(array_unique(array_merge($allStoreIds, $newIds)));
            $new = count($allStoreIds) - $before;
            $found += $new;
        } else {
            break;
        }
        usleep(100000);
    }
    echo "  [{$kw}] → Total unique so far: ".count($allStoreIds)."\n";
}
echo "  Keyword sweep done. New IDs found: {$found} | Total unique: ".count($allStoreIds)."\n\n";

// -----------------------------------------------------------------------
// STEP 4: Fetch full store details via /stores/selected
// -----------------------------------------------------------------------
echo "=== [4] Fetching full store details in batches of 20 ===\n";

$allStores = [];
$chunks = array_chunk($allStoreIds, 20);
$batchNum = 0;

foreach ($chunks as $chunk) {
    $batchNum++;
    $res = $h->get('https://api.salla.dev/mahally/v1/stores/selected', [
        'stores' => implode(',', $chunk),
    ]);

    if ($res->status() === 401) {
        echo "  Batch {$batchNum}: ⚠️ Token expired (401). Refreshing token...\n";
        @unlink($tokenFile);
        // Execute token refresh
        $nodeScript = __DIR__.'/get_mahally_token.js';
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open("node \"{$nodeScript}\"", $descriptors, $pipes);
        if ($proc) {
            $newToken = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            if (! empty($newToken) && ! str_starts_with($newToken, 'ERROR') && str_contains($newToken, '.')) {
                $token = $newToken;
                file_put_contents($tokenFile, $token);
                $h = Http::withoutVerifying()->withOptions(['version' => 2.0])->withHeaders([
                    'accept' => 'application/json',
                    'accept-language' => 'ar',
                    'authorization' => 'Bearer '.$token,
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
                ]);
                // Retry this chunk
                $res = $h->get('https://api.salla.dev/mahally/v1/stores/selected', [
                    'stores' => implode(',', $chunk),
                ]);
            }
        }
    }

    if ($res->successful()) {
        $stores = $res->json()['data'] ?? [];
        $allStores = array_merge($allStores, $stores);
        echo "  Batch {$batchNum}: +".count($stores).' stores | Total: '.count($allStores)."\n";
    } else {
        echo "  Batch {$batchNum}: ❌ HTTP ".$res->status()."\n";
    }
    usleep(150000);
}

// -----------------------------------------------------------------------
// STEP 5: Save results
// -----------------------------------------------------------------------
echo "\n=== [5] Results ===\n";
echo '  Total unique store IDs discovered: '.count($allStoreIds)."\n";
echo '  Total stores with full details: '.count($allStores)."\n\n";

// Show sample
foreach (array_slice($allStores, 0, 10) as $i => $s) {
    $id = $s['id'] ?? 'N/A';
    $name = $s['name'] ?? 'N/A';
    $avatar = isset($s['avatar']) ? '✓' : '✗';
    $rating = $s['rating'] ?? 'N/A';
    $categories = is_array($s['categories'] ?? null) ? $s['categories'] : [];
    $cats = implode(', ', array_column($categories, 'name'));
    echo '  ['.($i + 1)."] ID:{$id} | {$name} | ⭐{$rating} | {$cats}\n";
}

// Save JSON
$outFile = __DIR__.'/mahally_stores_harvest.json';
file_put_contents($outFile, json_encode([
    'harvested_at' => now()->toIso8601String(),
    'total_ids' => count($allStoreIds),
    'total_stores' => count($allStores),
    'store_ids' => $allStoreIds,
    'stores' => $allStores,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n✅ Saved to mahally_stores_harvest.json\n";

// Save to DB if requested
if ($saveDb && ! empty($allStores)) {
    echo "\n=== [6] Saving to database ===\n";
    $saved = 0;
    foreach ($allStores as $store) {
        $storeId = $store['id'] ?? null;
        $name = $store['name'] ?? null;
        if (! $storeId) {
            continue;
        }

        ScrapedStore::updateOrCreate(
            ['store_id' => $storeId],
            [
                'name' => $name,
                'slug' => $store['username'] ?? null,
                'status' => 'active',
                'scraped_at' => now(),
            ]
        );
        $saved++;
    }
    echo "  ✅ Saved {$saved} stores to DB\n";
}
