<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignStoreError extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'store_id',
        'store_url',
        'error_message',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
