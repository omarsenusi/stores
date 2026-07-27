<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'status',
        'status_message',
        'search_query',
        'file_path',
        'total_stores',
        'processed_stores',
        'success_count',
        'failure_count',
        'already_exists_count',
        'google_links_found',
        'google_links_processed',
        'google_pages_scraped',
        'error_message',
    ];

    protected $casts = [
        'total_stores' => 'integer',
        'processed_stores' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'already_exists_count' => 'integer',
        'google_links_found' => 'integer',
        'google_links_processed' => 'integer',
        'google_pages_scraped' => 'integer',
    ];

    public function errors(): HasMany
    {
        return $this->hasMany(CampaignStoreError::class);
    }

    public function checkCompletion(): void
    {
        $this->refresh();
        if ($this->status === 'processing' && $this->total_stores > 0 && $this->processed_stores >= $this->total_stores) {
            $this->update([
                'status' => 'completed',
                'status_message' => 'اكتملت الحملة بنجاح',
            ]);
        }
    }
}
