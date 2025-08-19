<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChunkDerivative extends Model
{
    protected $fillable = [
        'chunk_id',
        'prompt_id',
        'type',
        'content',
        'llm_provider',
        'llm_model',
    ];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(Chunk::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function embeddings()
    {
        return $this->morphMany(Embedding::class, 'embeddable');
    }
}
