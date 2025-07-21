<?php

namespace App\Models;

use App\Enums\ChunkType;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Chunk extends Model
{
    use Searchable;

    protected $guarded = [];

    protected $searchable = [
        'id',
        'file_id',
        'content',
        'chunk_type',
        'page_numbers',
    ];

    protected $casts = [
        'page_numbers' => 'array',
        'chunk_type' => ChunkType::class,
    ];

    public static function boot()
    {
        parent::boot();
    }
}