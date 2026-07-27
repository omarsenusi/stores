<?php

/**
 * Clean Salla Store Resolver Test (Starting STRICTLY from https://salla.sa/{store_name})
 * Testing Googlebot, Bingbot, and XMLHttpRequest headers to bypass Cloudflare 403 on datacenter IPs.
 * Run on server: php test_server_salla.php
 */
header('Content-Type: text/plain; charset=utf-8');

$testUrls = [
    'https://salla.sa/kawnroaster',
    'https://salla.sa/caltpro',
    'https://salla.sa/bassamtune',
];

echo "=== Salla Store Resolver Test (Strictly https://salla.sa/{store_name}) ===\n\n";

foreach ($testUrls as $url) {
    echo "--- Testing URL: {$url} ---\n";

    // Attempt 1: Googlebot User-Agent (Cloudflare Whitelisted Bot)
    echo "1. Attempting Googlebot User-Agent...\n";
    $ch1 = curl_init();
    curl_setopt_array($ch1, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar-SA,ar;q=0.9,en;q=0.8',
        ],
    ]);
    $res1 = curl_exec($ch1);
    $code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
    $eff1 = curl_getinfo($ch1, CURLINFO_EFFECTIVE_URL);
    curl_close($ch1);

    echo "   Status: {$code1} | Final URL: {$eff1} | Length: ".strlen($res1)." bytes\n";
    $id1 = extractStoreId($res1);
    if ($id1) {
        echo "   --> SUCCESS VIA GOOGLEBOT! Store ID: {$id1}\n\n";

        continue;
    }

    // Attempt 2: Bingbot User-Agent
    echo "2. Attempting Bingbot User-Agent...\n";
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
    ]);
    $res2 = curl_exec($ch2);
    $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $eff2 = curl_getinfo($ch2, CURLINFO_EFFECTIVE_URL);
    curl_close($ch2);

    echo "   Status: {$code2} | Final URL: {$eff2} | Length: ".strlen($res2)." bytes\n";
    $id2 = extractStoreId($res2);
    if ($id2) {
        echo "   --> SUCCESS VIA BINGBOT! Store ID: {$id2}\n\n";

        continue;
    }

    // Attempt 3: Fetch with X-Requested-With / AJAX headers
    echo "3. Attempting AJAX / Frontend Header...\n";
    $ch3 = curl_init();
    curl_setopt_array($ch3, [
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
            'Accept: application/json, text/plain, */*',
            'X-Requested-With: XMLHttpRequest',
            'Referer: https://salla.sa/',
        ],
    ]);
    $res3 = curl_exec($ch3);
    $code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    $eff3 = curl_getinfo($ch3, CURLINFO_EFFECTIVE_URL);
    curl_close($ch3);

    echo "   Status: {$code3} | Final URL: {$eff3} | Length: ".strlen($res3)." bytes\n";
    $id3 = extractStoreId($res3);
    if ($id3) {
        echo "   --> SUCCESS VIA AJAX! Store ID: {$id3}\n\n";

        continue;
    }

    echo "   --> ALL ATTEMPTS FAILED to bypass Cloudflare 403 on salla.sa.\n";
    echo "--------------------------------------------------\n\n";
}

function extractStoreId(?string $html): ?string
{
    if (empty($html)) {
        return null;
    }
    if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/salla\.sa\/[^\/]+\/(\d{5,12})/i', $html, $m)) {
        return $m[1];
    }

    return null;
}
