<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    protected $fillable = [
        'name',
        'content',
        'type',
        'version',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function chunkDerivatives(): HasMany
    {
        return $this->hasMany(ChunkDerivative::class);
    }
}