<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignStoreError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessExcelCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes timeout for processing huge excel files (44k+ stores)

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
        ini_set('memory_limit', '1024M'); // Allow up to 1GB RAM for parsing 44k+ row spreadsheets

        $campaign = Campaign::find($this->campaignId);

        if (! $campaign || $campaign->status === 'cancelled') {
            return;
        }

        try {
            $filePath = $campaign->file_path;
            $fullPath = storage_path('app/private/'.$filePath);

            if (! file_exists($fullPath)) {
                $fullPath = storage_path('app/'.$filePath);
            }

            if (! file_exists($fullPath)) {
                $campaign->update([
                    'status' => 'failed',
                    'error_message' => 'ملف Excel غير موجود على الخادم',
                ]);

                return;
            }

            // High performance reading: read data only, skip styles & formatting to save 80% RAM
            $reader = IOFactory::createReaderForFile($fullPath);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }

            $spreadsheet = $reader->load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                $campaign->update([
                    'status' => 'failed',
                    'error_message' => 'ملف Excel فارغ',
                ]);

                return;
            }

            $header = array_shift($rows);
            $storeIdColIndex = 0; // default to first column

            foreach ($header as $index => $colName) {
                if ($colName) {
                    $cleanCol = trim(mb_strtolower((string) $colName));
                    if (str_contains($cleanCol, 'معرف') || str_contains($cleanCol, 'id') || str_contains($cleanCol, 'متجر')) {
                        $storeIdColIndex = $index;
                        break;
                    }
                }
            }

            $storeIds = [];
            foreach ($rows as $row) {
                if (isset($row[$storeIdColIndex])) {
                    $rawVal = trim((string) $row[$storeIdColIndex]);
                    // Only keep numeric / string store IDs
                    if ($rawVal !== '' && (is_numeric($rawVal) || preg_match('/^\d+$/', $rawVal))) {
                        $storeIds[] = $rawVal;
                    }
                }
            }

            // Free memory of raw spreadsheet
            unset($rows, $spreadsheet, $worksheet);
            gc_collect_cycles();

            $storeIds = array_unique($storeIds);
            $totalCount = count($storeIds);

            if ($totalCount === 0) {
                $campaign->update([
                    'status' => 'failed',
                    'status_message' => 'لم يتم العثور على أرقام معرفات متاجر صحيحة في الملف',
                    'error_message' => 'لم يتم العثور على أرقام معرفات متاجر صحيحة في الملف',
                ]);

                return;
            }

            $campaign->update([
                'status' => 'processing',
                'total_stores' => $totalCount,
                'status_message' => "تم اكتشاف عدد {$totalCount} متاجر",
            ]);

            // High-speed bulk queueing into Redis using array_chunk & Queue::bulk
            $jobs = [];
            foreach ($storeIds as $storeId) {
                $jobs[] = new CheckStoreJob($storeId, $campaign->id);
            }

            // Push in chunks of 1000 jobs at a time (e.g. 44k stores = 44 Redis calls instead of 44,000)
            foreach (array_chunk($jobs, 1000) as $chunk) {
                if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                    break;
                }
                Queue::bulk($chunk);
            }

        } catch (\Throwable $e) {
            Log::error("ProcessExcelCampaignJob error for campaign {$this->campaignId}: ".$e->getMessage());
            if (isset($campaign)) {
                $campaign->update([
                    'status' => 'failed',
                    'status_message' => 'فشلت معالجة الملف: '.$e->getMessage(),
                    'error_message' => $e->getMessage(),
                ]);
                CampaignStoreError::create([
                    'campaign_id' => $campaign->id,
                    'error_message' => 'فشلت معالجة ملف Excel: '.$e->getMessage(),
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
        Log::error("ProcessExcelCampaignJob failed callback for campaign {$this->campaignId}: ".$exception->getMessage());
        $campaign = Campaign::find($this->campaignId);
        if ($campaign) {
            $campaign->update([
                'status' => 'failed',
                'status_message' => 'فشلت معالجة الحملة: '.$exception->getMessage(),
                'error_message' => $exception->getMessage(),
            ]);
            CampaignStoreError::create([
                'campaign_id' => $campaign->id,
                'error_message' => 'تعذر إكمال معالجة الحملة: '.$exception->getMessage(),
            ]);
        }
    }
}
