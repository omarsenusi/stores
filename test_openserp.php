<?php

/**
 * OpenSERP & Google Search Debugging Script
 * Run on server via CLI: php test_openserp.php
 * Or access via browser if placed in public folder.
 */

header('Content-Type: text/plain; charset=utf-8');

$openSerpUrl = getenv('OPENSERP_URL') ?: 'http://127.0.0.1:7000';
$query = isset($_GET['q']) ? $_GET['q'] : 'site:salla.sa';

echo "=== OpenSERP Debugger ===\n";
echo "OpenSERP Base URL: {$openSerpUrl}\n";
echo "Query: {$query}\n\n";

for ($page = 1; $page <= 3; $page++) {
    $targetUrl = rtrim($openSerpUrl, '/') . '/search?engine=google&q=' . urlencode($query) . '&page=' . $page;
    echo "--- Testing Page {$page} ---\n";
    echo "URL: {$targetUrl}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status: {$httpCode}\n";

    if ($error) {
        echo "cURL Error: {$error}\n";
        echo "--> ERROR: Could not connect to OpenSERP. Make sure OpenSERP container is running on port 7000!\n\n";
        break;
    }

    echo "Raw Response Length: " . strlen($response) . " bytes\n";
    echo "Raw Response Sample (first 500 chars):\n";
    echo substr($response, 0, 500) . "\n\n";

    $json = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        if (is_array($json)) {
            echo "Parsed Results Count: " . count($json) . "\n";
            foreach (array_slice($json, 0, 5) as $i => $item) {
                $title = isset($item['title']) ? $item['title'] : (isset($item['name']) ? $item['name'] : 'N/A');
                $url = isset($item['url']) ? $item['url'] : (isset($item['link']) ? $item['link'] : 'N/A');
                echo "  [{$i}] Title: {$title}\n";
                echo "      URL: {$url}\n";
            }
        } else {
            echo "JSON is not an array: " . var_export($json, true) . "\n";
        }
    } else {
        echo "JSON Parse Error: " . json_last_error_msg() . "\n";
    }

    echo "\n--------------------------------------------------\n\n";

    if (empty($json)) {
        echo "Stopped because page {$page} returned empty results.\n";
        break;
    }
}
