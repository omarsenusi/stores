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
        'is_found',
    ];

    protected $casts = [
        'is_found' => 'boolean',
    ];
}
