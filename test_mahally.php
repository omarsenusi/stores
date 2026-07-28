<?php

/**
 * Debug: Find where exactly the JWT appears in the RSC stream
 * The DevTools screenshot shows it on line 1 of a specific RSC call
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

// The DevTools showed the request to browse/?query=sa
// but it had NO next-action header - it was a navigation RSC request
// Key: it also had next-router-state-tree header

// The screenshot shows the request was to /ar/browse/?query=sa
// with Content-Type: text/x-component in RESPONSE
// and the request had no next-action

// Let's try to find which RSC action hash leads to token being returned
// Try different combos with next-router-state-tree

$attempts = [
    // No RSC headers (plain POST to mahally browse)
    [
        'url' => 'https://mahally.com/ar/browse/',
        'method' => 'POST',
        'headers' => [
            'accept' => 'text/x-component',
            'content-type' => 'text/plain;charset=UTF-8',
            'origin' => 'https://mahally.com',
            'referer' => 'https://mahally.com/',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
        ],
        'body' => '[]',
        'params' => ['query' => 'sa'],
        'label' => 'POST /ar/browse/?query=sa no RSC headers',
    ],
    // With RSC: 1 header only
    [
        'url' => 'https://mahally.com/ar/browse/',
        'method' => 'GET',
        'headers' => [
            'accept' => 'text/x-component',
            'RSC' => '1',
            'origin' => 'https://mahally.com',
            'referer' => 'https://mahally.com/',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
        ],
        'params' => ['query' => 'sa'],
        'label' => 'GET /ar/browse/?query=sa RSC:1',
    ],
];

foreach ($attempts as $attempt) {
    echo "=== {$attempt['label']} ===\n";

    $req = Http::withoutVerifying()->withOptions(['version' => 2.0])
        ->withHeaders($attempt['headers']);

    if ($attempt['method'] === 'POST') {
        $res = $req->withBody($attempt['body'], 'text/plain;charset=UTF-8')
            ->post($attempt['url'], $attempt['params'] ?? []);
    } else {
        $res = $req->get($attempt['url'], $attempt['params'] ?? []);
    }

    $body = $res->body();
    echo 'Status: '.$res->status().', Size: '.strlen($body)."\n";

    // Print first 20 lines of RSC to see structure
    $lines = explode("\n", $body);
    echo "First 10 RSC lines:\n";
    foreach (array_slice($lines, 0, 10) as $i => $line) {
        echo "  [{$i}] ".substr($line, 0, 120)."\n";
    }

    // Search for JWT
    if (preg_match_all('/eyJ[A-Za-z0-9\-_]{20,}\.[A-Za-z0-9\-_]{20,}\.[A-Za-z0-9\-_]{20,}/', $body, $m)) {
        echo '  🔥 JWT Found! Count: '.count($m[0])."\n";
        echo '  Token: '.substr($m[0][0], 0, 80)."...\n";
    } else {
        echo "  No JWT found\n";
    }

    echo "\n";
}
