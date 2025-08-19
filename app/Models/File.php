<?php

namespace App\Models;

use Dyrynda\Database\Support\BindsOnUuid;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    use BindsOnUuid, GeneratesUuid;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'authors' => 'array',
        'owners' => 'array',
        'recipients' => 'array',
        'publishers' => 'array',
        'authoring_actors' => 'array',
        'published_date' => 'date',
        'authored_date' => 'date',
        'metadata_analyzed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function chunks()
    {
        return $this->hasMany(Chunk::class);
    }
}
