<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Embedding extends Model
{
    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'provider',
        'model',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }
}
