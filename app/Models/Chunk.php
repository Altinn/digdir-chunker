<?php

namespace App\Models;

use App\Enums\ChunkType;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Chunk extends Model
{
    use Searchable;

    protected $guarded = [];

    protected $casts = [
        'page_numbers' => 'array',
        'chunk_type' => ChunkType::class,
    ];

    public static function boot()
    {
        parent::boot();
    }

    public function toSearchableArray()
    {
        $array = $this->toArray();

        // Flatten the array for search indexing
        return [
            'id' => (int) $array['id'],
            'file_uuid' => $this->file?->uuid,
            'file_title' => $this->file?->title,
            'file_subtitle' => $this->file?->subtitle,
            'file_authors' => $this->file?->authors,
            'file_owners' => $this->file?->owners,
            'file_recipients' => $this->file?->recipients,
            'file_publishers' => $this->file?->publishers,
            'file_authoring_actors' => $this->file?->authoring_actors,
            'file_isbn' => $this->file?->isbn,
            'file_issn' => $this->file?->issn,
            'file_type' => $this->file?->type,
            'file_concerned_year' => $this->file?->concerned_year,
            'file_source_document_url' => $this->file?->source_document_url,
            'file_published_date' => $this->file?->published_date,
            'chunk_type' => $array['chunk_type'],
            'page_numbers' => (array) $array['page_numbers'],
            'text' => $array['text'],
            'chunk_number' => (int) $array['chunk_number'],
            'derivatives' => $this->derivatives->map(fn ($derivative) => [
                'type' => $derivative->type,
                'content' => $derivative->content,
            ])->toArray(),
            'created_at' => $array['created_at'],
            'updated_at' => $array['updated_at'],
        ];
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function embeddings()
    {
        return $this->morphMany(Embedding::class, 'embeddable');
    }

    public function derivatives()
    {
        return $this->hasMany(ChunkDerivative::class);
    }
}
