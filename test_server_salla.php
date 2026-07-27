<?php

/**
 * Fast Salla 403 & Store ID Tester (Fast Timeout + HTTP/2)
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

$testUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
];

echo "=== Fast Salla Store ID Extraction Test (Fast HTTP/2) ===\n\n";

foreach ($testUrls as $url) {
    $startTime = microtime(true);
    echo "--- Testing URL: {$url} ---\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 6,
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
    $curlErr = curl_error($ch);
    curl_close($ch);

    $duration = round(microtime(true) - $startTime, 2);

    echo "HTTP Status: {$httpCode} | Length: ".strlen($response)." bytes | Time: {$duration}s\n";
    if ($curlErr) {
        echo "cURL Error: {$curlErr}\n";
    }

    if (! empty($response)) {
        if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $response, $m)) {
            echo "--> SUCCESS! Store ID Found: {$m[1]}\n";
        } elseif (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $response, $m)) {
            echo "--> SUCCESS! Store ID Found: {$m[1]}\n";
        } elseif (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $response, $m)) {
            echo "--> SUCCESS! Store ID Found: {$m[1]}\n";
        } elseif (preg_match('/salla\.sa\/[^\/]+\/(\d{5,12})/i', $response, $m)) {
            echo "--> SUCCESS! Store ID Found: {$m[1]}\n";
        } else {
            echo "--> Store ID NOT found in HTML.\n";
        }
    }

    echo "--------------------------------------------------\n\n";
}
