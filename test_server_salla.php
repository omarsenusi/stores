<?php

/**
 * Advanced Salla Store Resolver Tester
 * Testing: Direct Custom Domains, Salla API Endpoints, and Google SERP URLs
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

$stores = [
    'kawnroaster' => 'https://kawnroaster.com',
    'caltpro' => 'https://caltpro.com',
    'bassamtune' => 'https://bassamhp.com',
];

echo "=== Advanced Salla Store Resolver Tester ===\n\n";

foreach ($stores as $slug => $customDomain) {
    echo "--- Testing Store: {$slug} ---\n";

    // Test 1: Direct Custom Domain (which is what Google SERP actually returns!)
    echo "1. Direct Custom Domain: {$customDomain}\n";
    $ch1 = curl_init();
    curl_setopt_array($ch1, [
        CURLOPT_URL => $customDomain,
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
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer: https://www.google.com/',
        ],
    ]);
    $res1 = curl_exec($ch1);
    $code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
    curl_close($ch1);

    echo "   Custom Domain Status: {$code1} | Length: ".strlen($res1)." bytes\n";

    $storeId = null;
    if ($res1 && $code1 === 200) {
        if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $res1, $m)) {
            $storeId = $m[1];
        } elseif (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $res1, $m)) {
            $storeId = $m[1];
        } elseif (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $res1, $m)) {
            $storeId = $m[1];
        }
    }

    if ($storeId) {
        echo "   --> SUCCESS via Custom Domain! Store ID: {$storeId}\n";
    } else {
        echo "   --> Custom Domain failed or store_id not matched.\n";
    }

    // Test 2: Salla Store API Resolution (Public CDN / API Endpoint)
    $cdnApiUrl = "https://salla.sa/api/v1/store/slug/{$slug}";
    echo "2. Public Salla API: {$cdnApiUrl}\n";
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $cdnApiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept: application/json',
        ],
    ]);
    $res2 = curl_exec($ch2);
    $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    echo "   API Status: {$code2} | Response: ".substr($res2, 0, 150)."\n";

    echo "--------------------------------------------------\n\n";
}
