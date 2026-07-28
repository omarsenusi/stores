<?php

use App\Models\ScrapedStore;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$stats = ScrapedStore::selectRaw('
    COUNT(*) as total,
    SUM(is_found = 1) as found_count,
    SUM(is_found = 0) as not_found,
    SUM(JSON_EXTRACT(full_settings, "$.data.maintenance") = true) as maintenance,
    SUM(is_found = 1 AND (JSON_EXTRACT(full_settings, "$.data.maintenance") = false OR JSON_EXTRACT(full_settings, "$.data.maintenance") IS NULL)) as active
')->first();

dump($stats->toArray());
