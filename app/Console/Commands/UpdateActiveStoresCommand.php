<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateActiveStoresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:update-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update details for all active stores by re-scraping them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting update for active stores...');

        $stores = \App\Models\ScrapedStore::where('is_found', true)->get();

        $bar = $this->output->createProgressBar($stores->count());

        foreach ($stores as $store) {
            try {
                $response = \Illuminate\Support\Facades\Http::withOptions([
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
                    'store-identifier' => $store->store_id,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    'x-requested-with' => 'XMLHttpRequest',
                ])->get('https://api.salla.dev/store/v1/store/settings');

                $status = $response->status();
                $data = $response->json();

                if ($status === 200 && isset($data['success']) && $data['success']) {
                    $storeData = $data['data']['store'] ?? null;
                    if ($storeData) {
                        $storeName = $storeData['meta']['title'] ?? null;
                        $storeDescription = $storeData['meta']['description'] ?? null;
                        $storeLogo = $storeData['logo'] ?? null;
                        $contacts = $storeData['contacts'] ?? null;
                        $features = $storeData['features'] ?? null;
                        $fullSettings = $data;

                        $trackUrl = $data['data']['jitsu']['track_url'] ?? null;
                        $domain = $store->domain;
                        if ($trackUrl) {
                            $parsedUrl = parse_url($trackUrl);
                            if (isset($parsedUrl['host'])) {
                                $domain = $parsedUrl['host'];
                            }
                        }

                        $store->update([
                            'domain' => $domain,
                            'store_name' => $storeName,
                            'store_logo' => $storeLogo,
                            'store_description' => $storeDescription,
                            'contacts' => $contacts,
                            'features' => $features,
                            'full_settings' => $fullSettings,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Ignore and continue
            }
            
            $bar->advance();
            // Small sleep to avoid instant rate limiting if list is large
            usleep(200000); 
        }

        $bar->finish();
        $this->newLine();
        $this->info('Finished updating active stores.');
    }
}
