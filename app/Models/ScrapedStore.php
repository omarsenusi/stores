<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapedStore extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'domain',
        'product_name',
        'product_description',
        'product_url',
        'store_name',
        'store_logo',
        'store_description',
        'contacts',
        'features',
        'full_settings',
        'error_log',
        'is_found',
    ];

    protected $casts = [
        'is_found' => 'boolean',
        'contacts' => 'array',
        'features' => 'array',
        'full_settings' => 'array',
    ];
}
