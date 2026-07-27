<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignStoreError;
use App\Models\ScrapedStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessSerpApiCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public int $campaignId,
        public ?string $customApiKey = null
    ) {
        $this->onQueue('campaigns');
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        $campaign = Campaign::find($this->campaignId);
        if (! $campaign || $campaign->status === 'cancelled') {
            return;
        }

        $campaign->update([
            'status' => 'processing',
            'status_message' => 'جارٍ البدأ في بحث Google عبر SerpApi...',
        ]);

        $query = $campaign->search_query;
        if (! $query) {
            $campaign->update([
                'status' => 'failed',
                'error_message' => 'استعلام البحث فارغ',
            ]);

            return;
        }

        $apiKey = $this->customApiKey ?: env('SERPAPI_KEY');
        if (! $apiKey) {
            $campaign->update([
                'status' => 'failed',
                'status_message' => 'مفتاح SerpApi غير متوفر',
                'error_message' => 'لم يتم العثور على SerpApi API Key. يرجى إدخال المفتاح عند إنشاء الحملة أو تعيين SERPAPI_KEY في ملف .env',
            ]);

            return;
        }

        $processedDomains = [];
        $page = 1;
        $start = 0;
        $maxPages = 50; // Read up to 50 pages (500 organic results)

        try {
            while ($page <= $maxPages) {
                // Re-check cancellation
                if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                    return;
                }

                $campaign->update([
                    'status_message' => "جارٍ طلب نتائج Google عبر SerpApi (الصفحة {$page})...",
                ]);

                $response = Http::withoutVerifying()->timeout(30)->get('https://serpapi.com/search.json', [
                    'engine' => 'google',
                    'q' => $query,
                    'hl' => 'ar',
                    'gl' => 'sa',
                    'start' => $start,
                    'num' => 10,
                    'api_key' => $apiKey,
                ]);

                $campaign->increment('google_pages_scraped');

                if ($response->failed()) {
                    $errBody = $response->json();
                    $errMsg = $errBody['error'] ?? 'فشل الاتصال بـ SerpApi (HTTP '.$response->status().')';
                    Log::error("SerpApi error on page {$page} for campaign {$this->campaignId}: {$errMsg}");

                    $campaign->update([
                        'status' => 'failed',
                        'status_message' => 'خطأ في استجابة SerpApi: '.$errMsg,
                        'error_message' => $errMsg,
                    ]);

                    return;
                }

                $json = $response->json();

                if (isset($json['error'])) {
                    $errMsg = $json['error'];
                    Log::error("SerpApi API Error for campaign {$this->campaignId}: {$errMsg}");

                    $campaign->update([
                        'status' => 'failed',
                        'status_message' => 'خطأ في SerpApi: '.$errMsg,
                        'error_message' => $errMsg,
                    ]);

                    return;
                }

                $organic = $json['organic_results'] ?? [];

                if (empty($organic) || ! is_array($organic)) {
                    Log::info("SerpApi reached last page of Google results at page {$page}. Ending loop.");
                    break;
                }

                foreach ($organic as $item) {
                    // Check cancellation
                    if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                        return;
                    }

                    $rawUrl = $item['link'] ?? null;
                    if (! $rawUrl) {
                        continue;
                    }

                    $domain = $this->normalizeDomain($rawUrl);
                    if (! $domain || isset($processedDomains[$domain])) {
                        continue;
                    }

                    $processedDomains[$domain] = true;
                    $campaign->increment('google_links_found');

                    // Check if domain already exists in ScrapedStore
                    $alreadyExists = ScrapedStore::where('domain', $domain)
                        ->orWhere('domain', 'like', "%{$domain}%")
                        ->exists();

                    if ($alreadyExists) {
                        $campaign->increment('already_exists_count');
                        $campaign->increment('google_links_processed');

                        continue;
                    }

                    // Visit site to extract store ID
                    $storeId = $this->extractStoreIdFromWebsite($rawUrl);

                    if ($storeId) {
                        $campaign->increment('google_links_processed');
                        $campaign->increment('total_stores');

                        CheckStoreJob::dispatch($storeId, $campaign->id);
                    } else {
                        $campaign->increment('google_links_processed');
                        $campaign->increment('failure_count');

                        CampaignStoreError::create([
                            'campaign_id' => $campaign->id,
                            'store_url' => $rawUrl,
                            'error_message' => "لم يتم العثور على store id في كود الصفحة لموقع: {$domain}",
                        ]);
                    }
                }

                // Check if next page exists
                $nextPage = $json['serpapi_pagination']['next'] ?? null;
                if (! $nextPage) {
                    Log::info("No serpapi_pagination.next link on page {$page}. Reached end of Google results.");
                    break;
                }

                $start += 10;
                $page++;
                usleep(300000); // 0.3s pause
            }

            $campaign->refresh();
            $msg = "تم اكتشاف {$campaign->google_links_found} رابط من Google عبر SerpApi في {$campaign->google_pages_scraped} صفحة، منها {$campaign->already_exists_count} متجر موجود سابقاً و {$campaign->total_stores} متجر جديد جارٍ فحصه.";

            $campaign->update([
                'status_message' => $msg,
            ]);

            if ($campaign->total_stores === 0) {
                $campaign->update([
                    'status' => 'completed',
                    'status_message' => $msg.' (اكتمل البحث)',
                ]);
            }

        } catch (\Throwable $e) {
            Log::error("ProcessSerpApiCampaignJob error for campaign {$this->campaignId}: ".$e->getMessage());
            if (isset($campaign)) {
                $campaign->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessSerpApiCampaignJob failed exception for campaign {$this->campaignId}: ".$exception->getMessage());
        $campaign = Campaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update([
                'status' => 'failed',
                'error_message' => 'فشلت المهمة: '.$exception->getMessage(),
            ]);
        }
    }

    private function normalizeDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        $host = strtolower(trim($host));
        $host = preg_replace('/^www\./', '', $host);

        $excluded = ['google.com', 'google.com.sa', 'youtube.com', 'wikipedia.org', 'facebook.com', 'twitter.com', 'instagram.com', 'salla.sa', 'community.salla.sa', 'help.salla.sa', 'apps.salla.sa'];
        if (in_array($host, $excluded, true)) {
            return null;
        }

        return $host;
    }

    private function extractStoreIdFromWebsite(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Language' => 'ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            ])
                ->withoutVerifying()
                ->timeout(12)
                ->get($url);

            if ($response->failed()) {
                return null;
            }

            $html = $response->body();

            if (preg_match('/(?:storeId|store_id|store-id)\s*[:=]\s*["\']?(\d+)["\']?/i', $html, $matches)) {
                return $matches[1];
            }

            if (preg_match('/salla\.sa\/[^\/]+\/(\d+)/i', $html, $matches)) {
                return $matches[1];
            }

            if (preg_match('/data-store-id=["\'](\d+)["\']/i', $html, $matches)) {
                return $matches[1];
            }

            if (preg_match('/"id"\s*:\s*(\d{5,10})/', $html, $matches)) {
                return $matches[1];
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
