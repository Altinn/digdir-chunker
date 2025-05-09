<?php

namespace App\Models;

use App\Enums\ChunkType;
use Illuminate\Database\Eloquent\Model;

class Chunk extends Model
{
    protected $guarded = [];

    protected $casts = [
        'page_numbers' => 'array',
        'chunk_type' => ChunkType::class,
    ];

    public static function boot()
    {
        parent::boot();
    }
}