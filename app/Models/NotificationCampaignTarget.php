<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationCampaignTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_campaign_id',
        'scraped_store_id',
        'email',
        'store_name',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'notification_campaign_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ScrapedStore::class, 'scraped_store_id');
    }
}
