<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel',
        'status',
        'step',
        'subject',
        'content',
        'custom_emails',
        'total_targets',
        'sent_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'step' => 'integer',
        'custom_emails' => 'array',
        'total_targets' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(NotificationCampaignTarget::class);
    }
}
