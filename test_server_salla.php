<?php

/**
 * Standalone Salla 403 & Store ID Tester (Payload & Redirect Extraction)
 * Run on server: php test_server_salla.php
 */

header('Content-Type: text/plain; charset=utf-8');

$testUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
];

echo "=== Deep Inspection of 'جاري تحويلك' Page & Redirect Extraction ===\n\n";

foreach ($testUrls as $url) {
    echo "--- Testing URL: {$url} ---\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer: https://www.google.com/',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: {$httpCode} | Length: " . strlen($response) . " bytes\n";

    // Search for window.location, href, or canonical links in JS/HTML
    if (preg_match_all('/(?:window\.location|location\.href|location\.replace)\s*=\s*["\']([^"\']+)["\']/i', $response, $locMatches)) {
        echo "JS Redirect Locations Found: " . implode(', ', array_unique($locMatches[1])) . "\n";
    }

    if (preg_match_all('/<meta[^>]+http-equiv=["\']refresh["\'][^>]+content=["\']\d+;\s*url=([^"\']+)["\']/i', $response, $metaMatches)) {
        echo "Meta Refresh URLs Found: " . implode(', ', array_unique($metaMatches[1])) . "\n";
    }

    if (preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $response, $hrefMatches)) {
        $storeLinks = array_filter(array_unique($hrefMatches[1]), function($l) {
            return !str_contains($l, 'google') && !str_contains($l, 'gstatic') && !str_contains($l, 'schema.org') && !str_contains($l, 'w3.org');
        });
        echo "Clean Href Links Found: " . implode(' | ', array_slice($storeLinks, 0, 5)) . "\n";
    }

    // Check if store domain or destination URL is embedded in script tags
    if (preg_match_all('/https?:\/\/[a-z0-9\.\-]+\.(?:com|sa|net|org|site|shop)[^\s"\'\`<>]*/i', $response, $allUrls)) {
        $filteredUrls = array_filter(array_unique($allUrls[0]), function($u) {
            return !str_contains($u, 'google') && !str_contains($u, 'gstatic') && !str_contains($u, 'schema.org') && !str_contains($u, 'w3.org') && !str_contains($u, 'salla.network') && !str_contains($u, 'cloudflare');
        });
        echo "Unique Target Domains/URLs in JS: " . implode(' | ', array_slice($filteredUrls, 0, 8)) . "\n";
    }

    // Now test visiting target custom domain or trailing slash directly if extracted!
    $slug = basename(parse_url($url, PHP_URL_PATH));
    echo "\nTesting direct Salla Store Info API: https://salla.sa/api/v1/store/slug/{$slug}\n";
    $chApi = curl_init("https://salla.sa/api/v1/store/slug/{$slug}");
    curl_setopt_array($chApi, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: application/json',
            'Referer: https://salla.sa/',
        ],
    ]);
    $apiRes = curl_exec($chApi);
    $apiCode = curl_getinfo($chApi, CURLINFO_HTTP_CODE);
    curl_close($chApi);
    echo "API Status: {$apiCode} | Response: " . substr($apiRes, 0, 200) . "\n";

    echo "--------------------------------------------------\n\n";
}
