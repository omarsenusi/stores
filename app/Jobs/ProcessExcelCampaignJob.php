<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessExcelCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $campaignId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
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

            $spreadsheet = IOFactory::load($fullPath);
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

            $storeIds = array_unique($storeIds);
            $totalCount = count($storeIds);

            if ($totalCount === 0) {
                $campaign->update([
                    'status' => 'failed',
                    'error_message' => 'لم يتم العثور على أرقام معرفات متاجر صحيحة في الملف',
                ]);

                return;
            }

            $campaign->update([
                'status' => 'processing',
                'total_stores' => $totalCount,
                'status_message' => "تم اكتشاف عدد {$totalCount} متاجر",
            ]);

            foreach ($storeIds as $storeId) {
                // Re-check status in loop in case cancelled
                if (Campaign::where('id', $campaign->id)->value('status') === 'cancelled') {
                    break;
                }
                CheckStoreJob::dispatch($storeId, $campaign->id);
            }

        } catch (\Exception $e) {
            Log::error("ProcessExcelCampaignJob error for campaign {$this->campaignId}: ".$e->getMessage());
            $campaign->update([
                'status' => 'failed',
                'error_message' => 'خطأ في معالجة الملف: '.$e->getMessage(),
            ]);
        }
    }
}
