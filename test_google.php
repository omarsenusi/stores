<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

$query = 'site:salla.sa تمور';
$searchUrl = 'https://www.google.com/search?q='.urlencode($query).'&hl=ar';

echo "--- Step 1: Initial search request ---\n";
$jar = new CookieJar;
$client = Http::withoutVerifying()->withOptions(['cookies' => $jar])->withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Accept-Language' => 'ar-SA,ar;q=0.9,en;q=0.8',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
]);

$resp1 = $client->get($searchUrl);
$html1 = $resp1->body();
echo 'Step 1 status: '.$resp1->status().' | Length: '.strlen($html1)."\n";

if (preg_match('/href="(\/httpservice\/retry\/enablejs\?[^"]+)"/i', $html1, $m)) {
    $retryUrl = 'https://www.google.com'.html_entity_decode($m[1]);
    echo "Found enablejs redirect URL: {$retryUrl}\n";

    echo "--- Step 2: Following enablejs retry URL ---\n";
    $resp2 = $client->withHeaders(['Referer' => $searchUrl])->get($retryUrl);
    $html2 = $resp2->body();
    echo 'Step 2 status: '.$resp2->status().' | Length: '.strlen($html2)."\n";
    file_put_contents(__DIR__.'/google_enablejs.html', $html2);

    preg_match_all('/https?:\/\/[a-zA-Z0-9\.-]+\.salla\.sa[^\s"\'<>&]*/i', $html2, $m2);
    echo 'Salla links after enablejs: '.count($m2[0])."\n";
    print_r(array_slice(array_unique($m2[0]), 0, 10));
} else {
    echo "No enablejs link found\n";
}
