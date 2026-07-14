<?php
require __DIR__.'/vendor/autoload.php';
use Illuminate\Support\Facades\Http;
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$headers = [
    'accept' => 'application/json',
    'store-identifier' => '1082915046',
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
];

$response = Http::withoutVerifying()->withHeaders($headers)->get("https://api.salla.dev/store/v1/store/settings");
echo json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
