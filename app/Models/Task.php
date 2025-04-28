<?php

namespace App\Models;

use App\Enums\ChunkingMethod;
use App\Enums\ConversionBackend;
use Dyrynda\Database\Support\BindsOnUuid;
use Dyrynda\Database\Support\GeneratesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use GeneratesUuid, BindsOnUuid;

    protected $guarded = [];

    protected $casts = [
        'conversion_backend' => ConversionBackend::class,
        'chunking_method' => ChunkingMethod::class,
    ];

    protected $dates = [
        'created_at',
        'started_at',
        'finished_at',
        'expires_at',
        'delete_at',
    ];

    public function file(): HasOne
    {
        return $this->hasOne(File::class);
    }
}