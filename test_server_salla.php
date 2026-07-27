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

echo "=== Inspection of Server 403 Payload & Store ID Patterns ===\n\n";

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
    curl_close($ch);

    echo "HTTP Status: {$httpCode}\n";
    echo "Response Length: " . strlen($response) . " bytes\n";
    echo "Sample Header/Title: " . (preg_match('/<title>(.*?)<\/title>/is', $response, $t) ? trim($t[1]) : 'No title tag') . "\n";

    // Regex 1: store object ID
    if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $response, $m)) {
        echo "Regex 1 Matched: " . $m[1] . "\n";
    }

    // Regex 2: store_id or merchant_id
    if (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $response, $m)) {
        echo "Regex 2 Matched: " . $m[1] . "\n";
    }

    // Regex 3: data-store-id
    if (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $response, $m)) {
        echo "Regex 3 Matched: " . $m[1] . "\n";
    }

    // Regex 4: salla.sa app / script / image store id links
    if (preg_match_all('/(\d{7,11})/', $response, $allMatches)) {
        $uniqueDigits = array_slice(array_unique($allMatches[1]), 0, 10);
        echo "Sample Potential Store IDs (7-11 digits found): " . implode(', ', $uniqueDigits) . "\n";
    }

    echo "First 400 chars of Response:\n" . substr(strip_tags($response), 0, 400) . "\n";
    echo "--------------------------------------------------\n\n";
}
