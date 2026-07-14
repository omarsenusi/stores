<?php

namespace App\Jobs;

use App\Models\ScrapedStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CheckStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $storeId;

    /**
     * Create a new job instance.
     */
    public function __construct($storeId)
    {
        $this->storeId = $storeId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Redis::throttle('salla-api')
            ->allow(3)
            ->every(30)
            ->then(function () {
                $this->processStore();
            }, function () {
                // Could not obtain lock; release back to the queue after 30 seconds
                $this->release(30);
            });
    }

    protected function processStore(): void
    {
        try {
            $response = Http::withHeaders([
                'accept' => 'application/json, text/plain, */*',
                's-anonymous-id' => '5aee9542-9f2a-4393-910d-bbcb3b5c74bb',
                's-app-os' => 'browser',
                's-app-version' => '2.14.499',
                's-country' => 'EG',
                's-ray' => '50',
                's-source' => 'twilight',
                's-store-api-version' => 'swoole',
                'store-identifier' => $this->storeId,
                'user-agent' => 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36',
                'x-requested-with' => 'XMLHttpRequest',
            ])->get('https://api.salla.dev/store/v1/products?limit=3');

            $status = $response->status();
            $data = $response->json();

            $isFound = false;
            $domain = null;
            $productName = null;
            $productDescription = null;
            $productUrl = null;
            $errorLog = null;

            if ($status === 200 && isset($data['success']) && $data['success']) {
                $isFound = true;

                if (! empty($data['data'])) {
                    $firstProduct = $data['data'][0];
                    $productUrl = $firstProduct['url'] ?? null;
                    $productName = $firstProduct['name'] ?? null;
                    $productDescription = $firstProduct['description'] ?? null;

                    if ($productUrl) {
                        $parsedUrl = parse_url($productUrl);
                        if (isset($parsedUrl['host'])) {
                            $domain = $parsedUrl['host'];
                        }
                    }
                }
            } elseif (in_array($status, [404, 405])) {
                $isFound = false;
            } else {
                $errorLog = "Unexpected status {$status}: ".substr($response->body(), 0, 500);
                Log::warning("Unexpected status {$status} for store {$this->storeId}");
            }

            ScrapedStore::updateOrCreate(
                ['store_id' => (string) $this->storeId],
                [
                    'domain' => $domain,
                    'product_name' => $productName,
                    'product_description' => $productDescription,
                    'product_url' => $productUrl,
                    'error_log' => $errorLog,
                    'is_found' => $isFound,
                ]
            );

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            Log::error("Error processing store {$this->storeId}: ".$errorMsg);

            ScrapedStore::updateOrCreate(
                ['store_id' => (string) $this->storeId],
                [
                    'error_log' => $errorMsg,
                ]
            );
            $this->fail($e);
        }
    }
}
