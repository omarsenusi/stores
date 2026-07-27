<?php

/**
 * OpenSERP Google Engine Test Script
 * Run on server: php test_openserp.php
 */
header('Content-Type: text/plain; charset=utf-8');

$openSerpUrl = getenv('OPENSERP_URL') ?: 'http://194.163.187.51:7000';
$query = isset($_GET['q']) ? $_GET['q'] : 'site:salla.sa';

echo "=== OpenSERP Google Engine Tester ===\n";
echo "Base URL: {$openSerpUrl}\n";
echo "Query: {$query}\n\n";

for ($page = 1; $page <= 3; $page++) {
    $targetUrl = rtrim($openSerpUrl, '/').'/google/search?text='.urlencode($query).'&lang=AR&page='.$page;
    echo "--- Testing Google Page {$page} ---\n";
    echo "URL: {$targetUrl}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status: {$httpCode}\n";
    if ($error) {
        echo "cURL Error: {$error}\n";
        break;
    }

    echo 'Raw Response Length: '.strlen($response)." bytes\n";
    echo "Raw Response Sample:\n".substr($response, 0, 500)."\n\n";

    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($json['error'])) {
            echo 'Google Engine Error: '.($json['message'] ?? $json['error'])."\n";
            echo 'Details: '.var_export($json, true)."\n";
        } elseif (is_array($json)) {
            $items = isset($json['results']) ? $json['results'] : $json;
            echo 'Parsed Google Results Count: '.count($items)."\n";
            foreach (array_slice($items, 0, 5) as $i => $item) {
                $title = isset($item['title']) ? $item['title'] : 'N/A';
                $url = isset($item['url']) ? $item['url'] : (isset($item['link']) ? $item['link'] : 'N/A');
                echo "  [{$i}] Title: {$title}\n";
                echo "      URL: {$url}\n";
            }
        }
    } else {
        echo 'JSON Parse Error: '.json_last_error_msg()."\n";
    }

    echo "\n--------------------------------------------------\n\n";
}
