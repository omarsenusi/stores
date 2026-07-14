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
            $response = Http::withOptions([
                'version' => 2.0,
            ])->withHeaders([
                'accept' => 'application/json, text/plain, */*',
                'accept-language' => 'ar',
                'cache-control' => 'no-cache',
                'currency' => 'SAR',
                'origin' => 'https://najd7.com',
                'priority' => 'u=1, i',
                'referer' => 'https://najd7.com/',
                's-anonymous-id' => 'a0112d9b-77c9-4f9c-b300-4ef13266460a',
                's-app-os' => 'browser',
                's-app-version' => '2.14.499',
                's-country' => 'EG',
                's-ray' => '50',
                's-source' => 'twilight',
                's-store-api-version' => 'swoole',
                's-user-id' => 'FfuSe8KENcaVTETwoNKfS0CLbCDXBTnNIvIDplKz',
                's-version-id' => '1307728351',
                'sec-ch-ua' => '"Not;A=Brand";v="8", "Chromium";v="150", "Google Chrome";v="150"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Windows"',
                'sec-fetch-dest' => 'empty',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-site' => 'cross-site',
                'store-identifier' => $this->storeId,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
                'x-requested-with' => 'XMLHttpRequest',
            ])->get('https://api.salla.dev/store/v1/products', [
                'limit' => 4,
            ]);

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
            } else {
                $isFound = false;
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
