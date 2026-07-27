<?php

/**
 * OpenSERP Debugger for /google/search route
 * Run via CLI: php test_openserp.php
 */

header('Content-Type: text/plain; charset=utf-8');

$openSerpUrl = getenv('OPENSERP_URL') ?: 'http://127.0.0.1:7000';
$query = isset($_GET['q']) ? $_GET['q'] : 'site:salla.sa';

echo "=== OpenSERP /google/search Route Debugger ===\n";
echo "Base URL: {$openSerpUrl}\n";
echo "Query: {$query}\n\n";

$routesToTest = [
    '/google/search?text=' . urlencode($query) . '&lang=AR',
    '/google/search?text=' . urlencode($query) . '&lang=AR&limit=50',
    '/search?engine=google&q=' . urlencode($query),
];

foreach ($routesToTest as $route) {
    $targetUrl = rtrim($openSerpUrl, '/') . $route;
    echo "Testing Route: {$targetUrl}\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status: {$httpCode}\n";
    if ($error) {
        echo "cURL Error: {$error}\n";
    } else {
        echo "Response Length: " . strlen($response) . " bytes\n";
        echo "Sample: " . substr($response, 0, 300) . "\n";
    }
    echo "--------------------------------------------------\n";
}
