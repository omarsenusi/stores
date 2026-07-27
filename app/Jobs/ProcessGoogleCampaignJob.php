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

    public int $timeout = 600;

    public function __construct(
        public int $campaignId
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
            'status_message' => 'جارٍ البدأ في بحث Google...',
        ]);

        $query = $campaign->search_query;
        if (! $query) {
            $campaign->update([
                'status' => 'failed',
                'error_message' => 'استعلام البحث فارغ',
            ]);

            return;
        }

        $openSerpUrl = rtrim(env('OPENSERP_URL', 'http://127.0.0.1:7000'), '/');
        $processedDomains = [];
        $page = 1;
        $maxPages = 50;
        $consecutiveEmptyPages = 0;

        try {
            while ($page <= $maxPages) {
                if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                    return;
                }

                $campaign->update([
                    'status_message' => "جارٍ كشط نتائج بحث Google (الصفحة {$page})...",
                ]);

                $links = [];
                $serpResponse = Http::timeout(30)->get("{$openSerpUrl}/google/search", [
                    'text' => $query,
                    'lang' => 'AR',
                    'page' => $page,
                ]);

                $campaign->increment('google_pages_scraped');

                if ($serpResponse->successful()) {
                    $json = $serpResponse->json();
                    if (is_array($json)) {
                        if (isset($json['error'])) {
                            $errMsg = $json['message'] ?? $json['error'] ?? 'Google CAPTCHA / Circuit Open';
                            Log::warning("OpenSERP Google Error: {$errMsg}");

                            $campaign->update([
                                'status' => 'failed',
                                'status_message' => "خطأ في محرك Google: {$errMsg}",
                                'error_message' => "OpenSERP Google Error: {$errMsg}. يرجى توفير Proxy لـ OpenSERP أو استخدام SerpApi.",
                            ]);

                            return;
                        }

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
                        'error_message' => "OpenSERP HTTP {$serpResponse->status()}: {$errMsg}",
                    ]);

                    return;
                }

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
                    if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                        return;
                    }

                    $domain = $this->normalizeDomain($rawUrl);
                    if (! $domain || isset($processedDomains[$domain])) {
                        continue;
                    }

                    $processedDomains[$domain] = true;
                    $campaign->increment('google_links_found');

                    $alreadyExists = ScrapedStore::where('domain', $domain)
                        ->orWhere('domain', 'like', "%{$domain}%")
                        ->exists();

                    if ($alreadyExists) {
                        $campaign->increment('already_exists_count');
                        $campaign->increment('google_links_processed');

                        continue;
                    }

                    // Extract store identifier: if salla.sa/slug -> slug, else visit website to extract store ID
                    $storeIdentifier = null;
                    $extractError = null;

                    if (str_contains($rawUrl, 'salla.sa/')) {
                        $parsed = parse_url($rawUrl);
                        $path = trim($parsed['path'] ?? '', '/');
                        $segments = explode('/', $path);
                        $slug = strtolower($segments[0] ?? '');
                        $excludedPaths = ['', 'appstore-sa', 'community', 'help', 'developer', 'apps', 'blog', 'privacy', 'terms', 'complaint', 'affiliates'];
                        if (! empty($slug) && ! in_array($slug, $excludedPaths, true)) {
                            $storeIdentifier = $slug;
                        }
                    }

                    if (! $storeIdentifier) {
                        $extractResult = $this->extractStoreIdFromWebsite($rawUrl);
                        $storeIdentifier = $extractResult['store_id'];
                        $extractError = $extractResult['error'];
                    }

                    if ($storeIdentifier) {
                        $campaign->increment('google_links_processed');
                        $campaign->increment('total_stores');

                        CheckStoreJob::dispatch($storeIdentifier, $campaign->id);
                    } else {
                        $campaign->increment('google_links_processed');
                        $campaign->increment('failure_count');

                        CampaignStoreError::create([
                            'campaign_id' => $campaign->id,
                            'store_url' => $rawUrl,
                            'error_message' => "لم يتم استخراج Store ID أو Slug لموقع ({$domain}): ".($extractError ?: 'سبب غير معروف'),
                        ]);
                    }
                }

                $page++;
                usleep(500000);
            }

            $campaign->refresh();
            $msg = "تم اكتشاف {$campaign->google_links_found} رابط من Google في {$campaign->google_pages_scraped} صفحة، منها {$campaign->already_exists_count} متجر موجود سابقاً، و {$campaign->total_stores} متجر تم استخراج store_id بنجاح وجارٍ فحصه، و {$campaign->failure_count} متجر تعذر استخراج بياناتها.";

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
            Log::error("ProcessGoogleCampaignJob error for campaign {$this->campaignId}: ".$e->getMessage());
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
        Log::error("ProcessGoogleCampaignJob failed callback for campaign {$this->campaignId}: ".$exception->getMessage());
        $campaign = Campaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update([
                'status' => 'failed',
                'status_message' => 'فشلت حملة البحث: '.$exception->getMessage(),
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeDomain(string $url): ?string
    {
        $parts = parse_url($url);
        $host = isset($parts['host']) ? strtolower(trim($parts['host'])) : '';
        $host = preg_replace('/^www\./', '', $host);
        $path = isset($parts['path']) ? trim($parts['path'], '/') : '';

        if (! $host) {
            return null;
        }

        $globalExcludedHosts = ['google.com', 'google.com.sa', 'youtube.com', 'wikipedia.org', 'facebook.com', 'twitter.com', 'instagram.com', 'tiktok.com', 'snapchat.com'];
        if (in_array($host, $globalExcludedHosts, true)) {
            return null;
        }

        if ($host === 'salla.sa') {
            $pathSegments = explode('/', $path);
            $storeSlug = strtolower($pathSegments[0] ?? '');
            $excludedPaths = ['', 'appstore-sa', 'community', 'help', 'developer', 'apps', 'blog', 'privacy', 'terms', 'complaint', 'affiliates'];

            if (empty($storeSlug) || in_array($storeSlug, $excludedPaths, true)) {
                return null;
            }

            return "salla.sa/{$storeSlug}";
        }

        return $host;
    }

    private function extractStoreIdFromWebsite(string $url): array
    {
        try {
            $response = Http::withOptions([
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                    CURLOPT_ENCODING => '',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                ],
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'ar-SA,ar;q=0.9,en-US;q=0.8,en;q=0.7',
                'Referer' => 'https://www.google.com/',
            ])
                ->withoutVerifying()
                ->timeout(12)
                ->get($url);

            if ($response->failed()) {
                return ['store_id' => null, 'error' => "فشل زيارة الموقع (HTTP {$response->status()})"];
            }

            $html = $response->body();

            // Pattern 1: "store":{"id":1347911590
            if (preg_match('/["\']store["\']\s*:\s*\{\s*["\']id["\']\s*:\s*(\d{5,12})/i', $html, $matches)) {
                return ['store_id' => $matches[1], 'error' => null];
            }

            // Pattern 2: "store_id": 1347911590 or storeId: 1347911590
            if (preg_match('/["\']?(?:store_id|storeId|merchant_id|merchantId)["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $html, $matches)) {
                return ['store_id' => $matches[1], 'error' => null];
            }

            // Pattern 3: data-store-id="1347911590"
            if (preg_match('/data-store-id=["\'](\d{5,12})["\']/i', $html, $matches)) {
                return ['store_id' => $matches[1], 'error' => null];
            }

            // Pattern 4: salla.sa/.../123456
            if (preg_match('/salla\.sa\/[^\/]+\/(\d{5,12})/i', $html, $matches)) {
                return ['store_id' => $matches[1], 'error' => null];
            }

            return ['store_id' => null, 'error' => 'تعذر العثور على store_id في كود الصفحة (HTML) للمتجر'];
        } catch (\Throwable $e) {
            return ['store_id' => null, 'error' => 'خطأ أثناء الاتصال بالمتجر: '.$e->getMessage()];
        }
    }
}
