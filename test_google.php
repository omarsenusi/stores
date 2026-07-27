<?php

require __DIR__.'/vendor/autoload.php';

function normalizeSallaDomain(string $url): ?string
{
    $parts = parse_url($url);
    $host = isset($parts['host']) ? strtolower(trim($parts['host'])) : '';
    $host = preg_replace('/^www\./', '', $host);
    $path = isset($parts['path']) ? trim($parts['path'], '/') : '';

    if (! $host) {
        return null;
    }

    $globalExcludedHosts = ['google.com', 'google.com.sa', 'youtube.com', 'wikipedia.org', 'facebook.com', 'twitter.com', 'instagram.com'];
    if (in_array($host, $globalExcludedHosts, true)) {
        return null;
    }

    if ($host === 'salla.sa') {
        $pathSegments = explode('/', $path);
        $firstSegment = strtolower($pathSegments[0] ?? '');
        $excludedPaths = ['', 'appstore-sa', 'community', 'help', 'developer', 'apps', 'blog', 'privacy', 'terms', 'complaint', 'affiliates'];

        if (empty($firstSegment) || in_array($firstSegment, $excludedPaths, true)) {
            return null;
        }

        return "{$firstSegment}.salla.sa";
    }

    if (str_ends_with($host, '.salla.sa')) {
        $sub = str_replace('.salla.sa', '', $host);
        $excludedSubs = ['community', 'help', 'developer', 'apps', 'complaint', 'affiliates', 'demo'];
        if (in_array($sub, $excludedSubs, true)) {
            return null;
        }

        return $host;
    }

    return $host;
}

function extractStoreId(string $html): ?string
{
    // Pattern 1: "store":{"id":1347911590
    if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $html, $m)) {
        return $m[1];
    }

    // Pattern 2: "store_id": 1347911590
    if (preg_match('/["\']store_?id["\']\s*:\s*["\']?(\d{5,12})["\']?/i', $html, $m)) {
        return $m[1];
    }

    // Pattern 3: merchant_id: 1347911590
    if (preg_match('/["\']merchant_?id["\']\s*:\s*["\']?(\d{5,12})["\']?/i', $html, $m)) {
        return $m[1];
    }

    // Pattern 4: data-store-id="1347911590"
    if (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $html, $m)) {
        return $m[1];
    }

    return null;
}

$urlsToTest = [
    'https://salla.sa/mzajistore/ZlmRBW',
    'https://mzajistore.salla.sa',
    'https://salla.sa/foodworldmarket',
    'https://salla.sa/appstore-sa',
];

foreach ($urlsToTest as $u) {
    $norm = normalizeSallaDomain($u);
    echo "URL: {$u} --> Normalized Domain: ".($norm ?: 'EXCLUDED')."\n";
}

$html = file_get_contents(__DIR__.'/mzajistore.html');
echo 'Extracted Store ID from mzajistore.html: '.(extractStoreId($html) ?: 'NONE')."\n";
