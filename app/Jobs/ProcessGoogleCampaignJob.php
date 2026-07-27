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

class ProcessGoogleCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes timeout for scraping Google and visiting sites

    public $tries = 3;

    public $campaignId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
        $this->onQueue('campaigns');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (! $campaign || $campaign->status === 'cancelled') {
            return;
        }

        $campaign->update([
            'status' => 'processing',
            'status_message' => 'جارٍ كشط نتائج بحث Google...',
        ]);

        $query = $campaign->search_query;
        if (! $query) {
            $campaign->update([
                'status' => 'failed',
                'error_message' => 'استعلام البحث فارغ',
            ]);

            return;
        }

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
        ];

        $openSerpUrl = rtrim(env('OPENSERP_URL', 'http://127.0.0.1:7000'), '/');
        $processedDomains = [];
        $page = 1;
        $maxPages = 50; // Read up to 50 pages (approx 500-1000 Google search results)
        $consecutiveEmptyPages = 0;

        try {
            while ($page <= $maxPages) {
                // Re-check cancellation
                if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                    return;
                }

                $campaign->update([
                    'status_message' => "جارٍ كشط نتائج بحث Google (الصفحة {$page})...",
                ]);

                // Fetch Google Search results via OpenSERP /google/search
                $links = [];
                $serpResponse = Http::timeout(30)->get("{$openSerpUrl}/google/search", [
                    'text' => $query,
                    'lang' => 'AR',
                    'page' => $page,
                ]);

                $campaign->increment('google_pages_scraped');

                if ($serpResponse->successful()) {
                    $json = $serpResponse->json();

                    // OpenSERP returns JSON array of items on success
                    if (is_array($json)) {
                        // Check if OpenSERP returned an error payload inside JSON
                        if (isset($json['error'])) {
                            $errMsg = $json['message'] ?? $json['error'] ?? 'Google CAPTCHA / Circuit Open';
                            Log::warning("OpenSERP Google Error: {$errMsg}");

                            $campaign->update([
                                'status' => 'failed',
                                'status_message' => "خطأ في محرك Google: {$errMsg} (يتطلب بروكسي في OpenSERP)",
                                'error_message' => "OpenSERP Google Error: {$errMsg}. يرجى توفير Proxy لـ OpenSERP ليتجاوز الـ CAPTCHA الخاصة بـ Google.",
                            ]);

                            return;
                        }

                        // OpenSERP results format: results key or array of items
                        $items = isset($json['results']) ? $json['results'] : $json;
                        if (is_array($items)) {
                            foreach ($items as $item) {
                                $targetUrl = $item['url'] ?? $item['link'] ?? $item['href'] ?? null;
                                if ($targetUrl && is_string($targetUrl)) {
                                    $links[] = $targetUrl;
                                }
                            }
                        }
                    }
                } else {
                    $resJson = $serpResponse->json();
                    $errMsg = $resJson['message'] ?? $resJson['error'] ?? 'OpenSERP HTTP Error '.$serpResponse->status();
                    Log::warning("OpenSERP Google HTTP Error: {$errMsg}");

                    $campaign->update([
                        'status' => 'failed',
                        'status_message' => "فشل الاتصال بـ Google عبر OpenSERP: {$errMsg}",
                        'error_message' => "OpenSERP HTTP {$serpResponse->status()}: {$errMsg}. سبب الخطأ: IP السيرفر محظور من Google (CAPTCHA).",
                    ]);

                    return;
                }

                // If no links found at all for this page, we've reached the last page of Google results!
                if (empty($links)) {
                    $consecutiveEmptyPages++;
                    if ($consecutiveEmptyPages >= 2) {
                        Log::info("OpenSERP reached last page of Google search at page {$page}. Ending loop.");
                        break;
                    }
                } else {
                    $consecutiveEmptyPages = 0;
                }

                foreach ($links as $rawUrl) {
                    // Check cancellation
                    if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                        return;
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

                        // Queue CheckStoreJob for the extracted store ID
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

                $page++;
                // Small delay to prevent rate-limiting OpenSERP / Google
                usleep(500000); // 0.5 sec
            }

            $campaign->refresh();
            $msg = "تم اكتشاف {$campaign->google_links_found} رابط في {$campaign->google_pages_scraped} صفحة، منها {$campaign->already_exists_count} متجر موجود سابقاً و {$campaign->total_stores} متجر جديد جارٍ فحصه.";

            $campaign->update([
                'status_message' => $msg,
            ]);

            // If no new stores were queued for CheckStoreJob, mark completed
            if ($campaign->total_stores === 0) {
                $campaign->update([
                    'status' => 'completed',
                    'status_message' => $msg.' (اكتمل البحث)',
                ]);
            }

        } catch (\Throwable $e) {
            Log::error("ProcessGoogleCampaignJob error for campaign {$this->campaignId}: ".$e->getMessage());
            if (isset($campaign)) {
                $campaign->update([
                    'status' => 'failed',
                    'status_message' => 'فشلت حملة البحث: '.$e->getMessage(),
                    'error_message' => $e->getMessage(),
                ]);
                CampaignStoreError::create([
                    'campaign_id' => $campaign->id,
                    'error_message' => 'فشل كشط محرك البحث Google: '.$e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessGoogleCampaignJob failed callback for campaign {$this->campaignId}: ".$exception->getMessage());
        $campaign = Campaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update([
                'status' => 'failed',
                'status_message' => 'فشلت حملة البحث: '.$exception->getMessage(),
                'error_message' => $exception->getMessage(),
            ]);
            CampaignStoreError::create([
                'campaign_id' => $campaign->id,
                'error_message' => 'تعذر إكمال كشط محرك البحث: '.$exception->getMessage(),
            ]);
        }
    }

    protected function extractLinksFromGoogleHtml(string $html): array
    {
        $links = [];

        // Match Google SERP links: href="/url?q=https://..." or direct href="https://..."
        if (preg_match_all('/href="\/url\?q=(https?:\/\/[^"&]+)/i', $html, $matches)) {
            $links = array_merge($links, $matches[1]);
        }

        if (preg_match_all('/href="(https?:\/\/(?!www\.google|policies\.google|support\.google|maps\.google)[^"]+)"/i', $html, $matches)) {
            $links = array_merge($links, $matches[1]);
        }

        $filtered = [];
        $ignoredDomains = ['google.com', 'google.sa', 'youtube.com', 'gstatic.com', 'schema.org', 'w3.org', 'facebook.com', 'instagram.com', 'twitter.com', 'wikipedia.org'];

        foreach ($links as $url) {
            $url = urldecode($url);
            $host = parse_url($url, PHP_URL_HOST);
            if (! $host) {
                continue;
            }

            $isIgnored = false;
            foreach ($ignoredDomains as $ig) {
                if (str_contains(strtolower($host), $ig)) {
                    $isIgnored = true;
                    break;
                }
            }

            if (! $isIgnored) {
                $filtered[] = $url;
            }
        }

        return array_unique($filtered);
    }

    protected function normalizeDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    protected function extractStoreIdFromWebsite(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->withoutVerifying()->timeout(10)->get($url);

            if ($response->failed()) {
                return null;
            }

            $body = $response->body();

            // Match "store":{"id":1932927446
            if (preg_match('/"store"\s*:\s*\{\s*"id"\s*:\s*(\d+)/i', $body, $m)) {
                return $m[1];
            }

            // Fallback match: "store_id":1932927446 or "storeId":1932927446
            if (preg_match('/"store_?id"\s*:\s*["\']?(\d+)["\']?/i', $body, $m)) {
                return $m[1];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
