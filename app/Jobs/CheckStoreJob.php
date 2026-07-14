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
        // We will throttle at 30 requests every 60 seconds to ensure long-term stability without hitting rate limits.
        Redis::throttle('salla-api')
            ->allow(30)
            ->every(60)
            ->then(function () {
                $this->processStore();
            }, function () {
                // If limit reached, release the job back to the queue to try again after 10 seconds
                $this->release(10);
            });
    }

    protected function processStore(): void
    {
        try {
            $response = Http::withoutVerifying()->withOptions([
                'version' => 2.0,
            ])->withHeaders([
                'accept' => 'application/json, text/plain, */*',
                'accept-language' => 'ar',
                'cache-control' => 'no-cache',
                'currency' => 'SAR',
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
            ])->get('https://api.salla.dev/store/v1/store/settings');

            $status = $response->status();
            $data = $response->json();

            $isFound = false;
            $domain = null;
            $storeName = null;
            $storeLogo = null;
            $storeDescription = null;
            $contacts = null;
            $features = null;
            $fullSettings = null;
            $errorLog = null;

            $productName = null;
            $productDescription = null;
            $productUrl = null;
            $productImage = null;

            if ($status === 200 && isset($data['success']) && $data['success']) {
                $isFound = true;
                $store = $data['data']['store'] ?? null;
                if ($store) {
                    $storeName = !empty($store['meta']['title']) ? $store['meta']['title'] : ($store['name'] ?? null);
                    $storeDescription = !empty($store['meta']['description']) ? $store['meta']['description'] : ($store['description'] ?? null);
                    $storeLogo = !empty($store['logo']) ? $store['logo'] : ($store['avatar'] ?? null);
                    $contacts = $store['contacts'] ?? null;
                    $features = $store['features'] ?? null;
                    $fullSettings = $data;

                    $trackUrl = $data['data']['jitsu']['track_url'] ?? ($store['url'] ?? null);
                    if ($trackUrl) {
                        $parsedUrl = parse_url($trackUrl);
                        if (isset($parsedUrl['host'])) {
                            $domain = $parsedUrl['host'];
                        } else if (isset($parsedUrl['path'])) {
                            // in case it's just a domain string without scheme
                            $domain = $parsedUrl['path'];
                        }
                    }
                }

                // Fetch products to verify active products exist
                $productsResponse = Http::withoutVerifying()->withOptions([
                    'version' => 2.0,
                ])->withHeaders([
                    'accept' => 'application/json, text/plain, */*',
                    'accept-language' => 'ar',
                    'cache-control' => 'no-cache',
                    'currency' => 'SAR',
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
                    'limit' => 3,
                ]);

                if ($productsResponse->status() === 200) {
                    $prodData = $productsResponse->json();
                    if (isset($prodData['success']) && $prodData['success'] && !empty($prodData['data'])) {
                        $firstProduct = $prodData['data'][0];
                        $productName = $firstProduct['name'] ?? null;
                        $productDescription = $firstProduct['description'] ?? null;
                        $productUrl = $firstProduct['url'] ?? null;
                        $productImage = $firstProduct['original_image'] ?? ($firstProduct['image']['url'] ?? null);
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
                    'product_image' => $productImage,
                    'store_name' => $storeName,
                    'store_logo' => $storeLogo,
                    'store_description' => $storeDescription,
                    'contacts' => $contacts,
                    'features' => $features,
                    'full_settings' => $fullSettings,
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
