<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    protected $guarded = [];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function chunks()
    {
        return $this->hasMany(Chunk::class);
    }
}
