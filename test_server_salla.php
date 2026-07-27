<?php

/**
 * Testing Google Proxy & Salla Mirror Resolution for 403 Bypass on Datacenter IPs
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

$sallaUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
];

echo "=== Testing Google Proxy Bypass for Salla 403 Datacenter Blocks ===\n\n";

foreach ($sallaUrls as $url) {
    $parsed = parse_url($url);
    $path = trim($parsed['path'] ?? '', '/');
    $slug = explode('/', $path)[0] ?? '';

    echo "--- Store Slug: {$slug} (URL: {$url}) ---\n";

    // Method 1: Google Translate Proxy (Translates / Proxies the request through Google IPs!)
    $proxyUrl = "https://salla--sa.translate.goog/{$slug}?_x_tr_sl=auto&_x_tr_tl=ar&_x_tr_hl=ar";
    echo "1. Google Translate Proxy URL: {$proxyUrl}\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $proxyUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    echo "   Proxy HTTP Status: {$httpCode} | Final URL: {$effectiveUrl} | Length: ".strlen($response)." bytes\n";

    $storeId = null;
    if ($response) {
        if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $response, $m)) {
            $storeId = 'Pattern 1 (store.id): '.$m[1];
        } elseif (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $response, $m)) {
            $storeId = 'Pattern 2 (store_id): '.$m[1];
        } elseif (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $response, $m)) {
            $storeId = 'Pattern 3 (data-store-id): '.$m[1];
        } elseif (preg_match('/salla\.sa\/[^\/]+\/(\d{5,12})/i', $response, $m)) {
            $storeId = 'Pattern 4 (salla.sa path): '.$m[1];
        }
    }

    if ($storeId) {
        echo "   --> SUCCESS VIA GOOGLE PROXY! {$storeId}\n";
    } else {
        echo "   --> Store ID not found in proxy response.\n";
    }

    echo "--------------------------------------------------\n\n";
}
