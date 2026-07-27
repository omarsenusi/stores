<?php

/**
 * Salla URL Redirect & Header Inspection Test
 * Starting STRICTLY from https://salla.sa/{slug} without any hardcoded custom domains!
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

$sallaUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
];

echo "=== Testing Salla URL Redirects & Headers (No Hardcoded Domains) ===\n\n";

foreach ($sallaUrls as $url) {
    echo "--- Input URL: {$url} ---\n";

    // Test A: Get HTTP Headers only (curl -I / CURLOPT_NOBODY)
    $chHead = curl_init();
    curl_setopt_array($chHead, [
        CURLOPT_URL => $url,
        CURLOPT_NOBODY => true,
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: */*',
            'Referer: https://www.google.com/',
        ],
    ]);
    $headResponse = curl_exec($chHead);
    $headCode = curl_getinfo($chHead, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($chHead, CURLINFO_REDIRECT_URL);
    curl_close($chHead);

    echo "HEAD Request Status: {$headCode}\n";
    echo 'Redirect URL (Location header): '.($redirectUrl ?: 'None')."\n";
    echo "HEAD Response Sample:\n".substr($headResponse, 0, 300)."\n\n";

    // Test B: GET with Follow Location
    $chGet = curl_init();
    curl_setopt_array($chGet, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer: https://www.google.com/',
        ],
    ]);

    $getResponse = curl_exec($chGet);
    $getCode = curl_getinfo($chGet, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($chGet, CURLINFO_EFFECTIVE_URL);
    curl_close($chGet);

    echo "GET Request Status: {$getCode}\n";
    echo "Effective Final URL reached: {$effectiveUrl}\n";

    $storeId = null;
    if ($getResponse) {
        if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $getResponse, $m)) {
            $storeId = $m[1];
        } elseif (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $getResponse, $m)) {
            $storeId = $m[1];
        } elseif (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $getResponse, $m)) {
            $storeId = $m[1];
        }
    }

    if ($storeId) {
        echo "--> SUCCESS! Store ID: {$storeId}\n";
    } else {
        echo "--> Failed to extract Store ID.\n";
    }

    echo "--------------------------------------------------\n\n";
}
