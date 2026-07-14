<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use App\Models\ScrapedStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeNextSallaStoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:scrape-next {--batch=20}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrapes the next sequential Salla store IDs in the background';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        $setting = Setting::firstOrCreate(['key' => 'last_store_id'], ['value' => '1061244541']);
        $lastId = (int) $setting->value;

        $telegramChatIdSetting = Setting::firstOrCreate(['key' => 'telegram_chat_id'], ['value' => '']);
        $chatId = $telegramChatIdSetting->value;
        $botToken = '7261497209:AAFzaaMruLYSTUUl0_2SNwAJO70rb93-sVA';

        for ($i = 0; $i < $batchSize; $i++) {
            $currentId = $lastId + 1;
            
            $this->info("Checking store ID: {$currentId}");
            
            try {
                $response = Http::withoutVerifying()->withOptions([
                    'version' => 2.0,
                ])->withHeaders($this->getHeaders($currentId))
                ->get('https://api.salla.dev/store/v1/store/settings', [
                    'store_identifier' => $currentId,
                ]);

                $status = $response->status();
                $data = $response->json();

                if ($status === 200 && isset($data['success']) && $data['success']) {
                    $this->info("Store ID {$currentId} found!");
                    $this->processFoundStore($currentId, $data, $chatId, $botToken);
                    
                    // Update lastId in DB only if we found it
                    $lastId = $currentId;
                    $setting->update(['value' => (string) $lastId]);
                } else {
                    $this->warn("Store ID {$currentId} not found (Status: {$status}). Reached the end. Stopping loop.");
                    // Stop the loop! We will check this exact same ID again next minute.
                    break;
                }
            } catch (\Exception $e) {
                $this->error("Error checking store ID {$currentId}: " . $e->getMessage());
                // Stop on error to retry later
                break;
            }
            
            // Small delay to be gentle on the API
            usleep(500000); // 0.5 seconds
        }

        $this->info("Finished batch. Last ID is now {$lastId}");
    }

    protected function processFoundStore($storeId, $data, $chatId, $botToken)
    {
        $store = $data['data']['store'] ?? null;
        if (!$store) return;

        $storeName = !empty($store['meta']['title']) ? $store['meta']['title'] : ($store['name'] ?? null);
        $storeDescription = !empty($store['meta']['description']) ? $store['meta']['description'] : ($store['description'] ?? null);
        $storeLogo = !empty($store['logo']) ? $store['logo'] : ($store['avatar'] ?? null);
        $contacts = $store['contacts'] ?? null;
        $features = $store['features'] ?? null;
        
        $domain = null;
        $trackUrl = $data['data']['jitsu']['track_url'] ?? ($store['url'] ?? null);
        if ($trackUrl) {
            $parsedUrl = parse_url($trackUrl);
            if (isset($parsedUrl['host'])) {
                $domain = $parsedUrl['host'];
            } else if (isset($parsedUrl['path'])) {
                $domain = $parsedUrl['path'];
            }
        }

        // Fetch products to verify active products exist
        $productName = null;
        $productDescription = null;
        $productUrl = null;
        $productImage = null;

        try {
            $productsResponse = Http::withoutVerifying()->withOptions([
                'version' => 2.0,
            ])->withHeaders($this->getHeaders($storeId))
            ->get('https://api.salla.dev/store/v1/products', [
                'limit' => 3,
                'page' => 1,
            ]);

            $productsData = $productsResponse->json();
            if ($productsResponse->status() === 200 && isset($productsData['data']) && count($productsData['data']) > 0) {
                $firstProduct = $productsData['data'][0];
                $productName = $firstProduct['name'] ?? null;
                $productDescription = strip_tags($firstProduct['description'] ?? '');
                $productUrl = $firstProduct['urls']['customer'] ?? null;
                $productImage = $firstProduct['images'][0]['url'] ?? null;
            }
        } catch (\Exception $e) {
            $this->error("Failed to fetch products for store {$storeId}");
        }

        // Save to Database
        $scrapedStore = ScrapedStore::updateOrCreate(
            ['store_id' => $storeId],
            [
                'domain' => $domain,
                'store_name' => $storeName,
                'store_description' => $storeDescription,
                'store_logo' => $storeLogo,
                'contacts' => $contacts,
                'features' => $features,
                'full_settings' => $data,
                'product_name' => $productName,
                'product_description' => $productDescription,
                'product_url' => $productUrl,
                'product_image' => $productImage,
                'is_found' => true,
                'error_log' => null,
            ]
        );

        $this->info("Saved store {$storeId} to database.");

        // Send Telegram message
        if (!empty($chatId)) {
            $this->sendTelegramMessage($scrapedStore, $chatId, $botToken);
        } else {
            $this->warn("No Telegram Chat ID found in settings. Skipping notification.");
        }
    }

    protected function sendTelegramMessage($store, $chatId, $botToken)
    {
        $domain = $store->domain ? $store->domain : 'غير متوفر';
        $fullUrl = $store->full_settings['data']['store']['url'] ?? "https://salla.sa/{$domain}";
        $storeName = $store->store_name ?? 'بدون اسم';
        $description = $store->store_description ? \Illuminate\Support\Str::limit($store->store_description, 100) : 'لا يوجد وصف';
        
        $contacts = is_array($store->contacts) ? $store->contacts : json_decode($store->contacts, true) ?? [];
        $whatsapp = $contacts['whatsapp'] ?? 'غير متوفر';
        $mobile = $contacts['mobile'] ?? 'غير متوفر';
        
        $themeName = $store->full_settings['data']['theme']['name'] ?? 'غير متوفر';
        $maintenance = (isset($store->full_settings['data']['maintenance']) && $store->full_settings['data']['maintenance']) ? 'نعم 🔒' : 'لا 🔓';

        $text = "🎉 *متجر جديد تم اكتشافه!*\n\n";
        $text .= "🏬 *اسم المتجر:* {$storeName}\n";
        $text .= "🔗 *الرابط:* {$fullUrl}\n";
        $text .= "📝 *الوصف:* {$description}\n";
        $text .= "📞 *واتساب:* {$whatsapp}\n";
        $text .= "📱 *جوال:* {$mobile}\n";
        $text .= "🎨 *رقم القالب:* {$themeName}\n";
        $text .= "🛠 *وضع الصيانة:* {$maintenance}\n\n";
        
        if ($store->product_name) {
            $text .= "📦 *أحدث منتج:* {$store->product_name}\n\n";
        }

        $text .= "#متجر_جديد #سلة #Salla #Scraper";

        try {
            Http::withoutVerifying()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => false,
            ]);
            $this->info("Telegram message sent successfully!");
        } catch (\Exception $e) {
            $this->error("Failed to send Telegram message: " . $e->getMessage());
        }
    }

    protected function getHeaders($storeId)
    {
        return [
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
            'store-identifier' => (string) $storeId,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
            'x-requested-with' => 'XMLHttpRequest',
        ];
    }
}
