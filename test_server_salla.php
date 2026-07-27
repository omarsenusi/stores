<?php

/**
 * Standalone Salla 403 & Store ID Tester (HTTP/3 & HTML Analysis)
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

$testUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
];

$http3Version = defined('CURL_HTTP_VERSION_3') ? CURL_HTTP_VERSION_3 : (defined('CURL_HTTP_VERSION_3ONLY') ? CURL_HTTP_VERSION_3ONLY : 30);

echo "=== Testing HTTP/3 (Version Constant: {$http3Version}) & Deep HTML Parsing ===\n\n";

foreach ($testUrls as $url) {
    echo "--- Testing URL: {$url} ---\n";

    // Attempt 1: HTTP/3
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => $http3Version,
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
    $curlErr = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status: {$httpCode} | Length: ".strlen($response)." bytes\n";
    if ($curlErr) {
        echo "cURL Error (HTTP/3): {$curlErr}\n";
    }

    if (empty($response)) {
        echo "--------------------------------------------------\n\n";

        continue;
    }

    // 1. Search for Salla store ID in any JSON object or window.salla or script
    $storeId = null;

    if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $response, $m)) {
        $storeId = 'Pattern 1 (store.id): '.$m[1];
    } elseif (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $response, $m)) {
        $storeId = 'Pattern 2 (store_id): '.$m[1];
    } elseif (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $response, $m)) {
        $storeId = 'Pattern 3 (data-store-id): '.$m[1];
    } elseif (preg_match('/salla\.sa\/[^\/]+\/(\d{5,12})/i', $response, $m)) {
        $storeId = 'Pattern 4 (salla.sa path): '.$m[1];
    }

    if ($storeId) {
        echo "--> SUCCESS! {$storeId}\n";
    } else {
        echo "--> Store ID NOT matched by standard patterns.\n";
    }

    // 2. Extract window.Salla or window.App or inline JS config data
    if (preg_match_all('/window\.[a-zA-Z0-9_\.]+\s*=\s*(\{.*?\});/s', $response, $jsObjMatches)) {
        echo 'JS Window Config Objects Found: '.count($jsObjMatches[0])."\n";
        foreach ($jsObjMatches[1] as $idx => $jsonSnippet) {
            if (str_contains($jsonSnippet, 'id') || str_contains($jsonSnippet, 'store')) {
                echo 'Snippet '.($idx + 1).': '.substr($jsonSnippet, 0, 300)."...\n";
            }
        }
    }

    // 3. Extract canonical link or target store URL
    if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $response, $canMatch)) {
        echo 'Canonical Target URL: '.$canMatch[1]."\n";
    }

    echo "--------------------------------------------------\n\n";
}
