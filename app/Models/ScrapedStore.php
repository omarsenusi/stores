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
        'error_log',
        'is_found',
    ];

    protected $casts = [
        'is_found' => 'boolean',
    ];
}
