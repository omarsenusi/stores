<?php

/**
 * Standalone Salla 403 & Store ID Tester (Zero-Dependency Native cURL)
 * Run on server: php test_server_salla.php
 */

header('Content-Type: text/plain; charset=utf-8');

$testUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
];

echo "=== Standalone Salla 403 & Store ID Tester (TLS Options) ===\n\n";

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
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_SSL_CIPHER_LIST => 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305',
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer: https://www.google.com/',
            'Sec-Ch-Ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: cross-site',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    curl_close($ch);

    echo "Final HTTP Status: {$httpCode}\n";
    echo "Effective Final URL: {$effectiveUrl}\n";

    if ($error) {
        echo "cURL Error: {$error}\n";
        echo "--------------------------------------------------\n\n";
        continue;
    }

    echo "Response Length: " . strlen($response) . " bytes\n";

    // Extract Store ID
    $storeId = null;

    if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $response, $m)) {
        $storeId = $m[1];
    } elseif (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $response, $m)) {
        $storeId = $m[1];
    } elseif (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $response, $m)) {
        $storeId = $m[1];
    } elseif (preg_match('/salla\.sa\/[^\/]+\/(\d{5,12})/i', $response, $m)) {
        $storeId = $m[1];
    }

    if ($storeId) {
        echo "--> SUCCESS! Store ID Extracted: {$storeId}\n";
    } else {
        echo "--> FAILED to find Store ID in HTML response.\n";
    }

    echo "--------------------------------------------------\n\n";
}
