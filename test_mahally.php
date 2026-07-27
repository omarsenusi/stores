<?php

/**
 * Mahally & Salla Store Search API Simulation
 * Run on server: php test_mahally.php
 */
header('Content-Type: text/plain; charset=utf-8');

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$query = isset($argv[1]) ? trim($argv[1]) : 'قهوة';

echo "=== Mahally & Salla Store Search API Simulation ===\n";
echo "Search Query: {$query}\n\n";

// ----------------------------------------------------------------------
// Test 1: Direct Mahally Search API (api.salla.dev/mahally/v1/stores/search)
// ----------------------------------------------------------------------
echo "--- Test 1: Salla Mahally Stores Search API ---\n";

$bearerToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL3NhbGxhLnNhL2FwaS92MS9hdXRoL3JlZnJlc2giLCJpYXQiOjE3ODUxOTM2NDEsImV4cCI6MTc4NTE5NDE4NiwibmJmIjoxNzg1MTkzNjQxLCJqdGkiOiJvSUVidDFrY1lDOVV1dlJiIiwic3ViIjoibWFobHktYXBwLWd1ZXN0IiwicHJ2IjoiZGZlY2IzMWY5MTFmZjI1NDI3NTkxYjZmNDdhZDM4YTk1MTY5YzRlYyIsImlzbiI6dHJ1ZSwidm5jIjoic21zIiwiZ3VlIjoxfQ.Zgx0uutN2psF9l9s6KrRvIywA4k-0WexMgUe82QRpUg';

$res1 = Http::withoutVerifying()->withOptions([
    'version' => 2.0,
])->withHeaders([
    'accept' => 'application/json',
    'accept-language' => 'ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
    'authorization' => 'Bearer '.$bearerToken,
    'currency' => 'SAR',
    'mahly-app-version' => '5.4.3',
    'mahly-environment' => 'production',
    'origin' => 'https://mahally.com',
    'referer' => 'https://mahally.com/',
    's-app-name' => 'mahly',
    's-app-version' => '3.3.9',
    's-source' => 'web',
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
])->get('https://api.salla.dev/mahally/v1/stores/search/', [
    'q' => $query,
]);

echo 'API Status: '.$res1->status()."\n";

if ($res1->successful()) {
    $json = $res1->json();
    echo 'Response Success: '.($json['success'] ? 'TRUE' : 'FALSE')."\n";

    $stores = $json['data'] ?? [];
    echo 'Stores Found Count: '.count($stores)."\n\n";

    foreach (array_slice($stores, 0, 10) as $idx => $store) {
        $id = $store['id'] ?? $store['store_id'] ?? 'N/A';
        $name = $store['name'] ?? $store['title'] ?? 'N/A';
        $domain = $store['domain'] ?? $store['url'] ?? $store['username'] ?? 'N/A';
        echo ' ['.($idx + 1)."] Store ID: {$id} | Name: {$name} | Domain/Slug: {$domain}\n";
    }
} else {
    echo 'API Call Failed: '.substr($res1->body(), 0, 300)."\n";
}

echo "\n--------------------------------------------------\n\n";

// ----------------------------------------------------------------------
// Test 2: Mahally Next.js Category / Browse Server Action
// ----------------------------------------------------------------------
echo "--- Test 2: Mahally Next.js Category Browse Action ---\n";

$res2 = Http::withoutVerifying()->withOptions([
    'version' => 2.0,
])->withHeaders([
    'accept' => 'text/x-component',
    'accept-language' => 'ar-AE,ar;q=0.9,en-US;q=0.8,en;q=0.7',
    'content-type' => 'text/plain;charset=UTF-8',
    'next-action' => '40f14656a69e03a393fe028d553f38dfd03fb96b97',
    'next-router-state-tree' => '%5B%22%22%2C%7B%22children%22%3A%5B%5B%22lng%22%2C%22en%22%2C%22d%22%2Cnull%5D%2C%7B%22children%22%3A%5B%22(main)%22%2C%7B%22children%22%3A%5B%22browse%22%2C%7B%22children%22%3A%5B%5B%22slugs%22%2C%22%25D8%25A3%25D8%25B2%25D9%258A%25D8%25A7%25D8%25A1%22%2C%22oc%22%2Cnull%5D%2C%7B%22children%22%3A%5B%22__PAGE__%22%2C%7B%7D%2Cnull%2Cnull%2C0%5D%7D%2Cnull%2Cnull%2C0%5D%7D%2Cnull%2Cnull%2C0%5D%7D%2Cnull%2Cnull%2C0%5D%7D%2Cnull%2Cnull%2C0%5D%7D%2Cnull%2Cnull%2C16%5D',
    'origin' => 'https://mahally.com',
    'referer' => 'https://mahally.com/en/browse/%D8%A3%D8%B2%D9%8A%D8%A7%D8%A1/',
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
])->withBody('[]', 'text/plain;charset=UTF-8')
    ->post('https://mahally.com/en/browse/%D8%A3%D8%B2%D9%8A%D8%A7%D8%A1/');

echo 'Next.js Status: '.$res2->status().' | Body Length: '.strlen($res2->body())." bytes\n";
echo "Sample Payload (first 300 chars):\n".substr($res2->body(), 0, 300)."\n";
