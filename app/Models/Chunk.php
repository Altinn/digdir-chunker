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

        // Customize the array as needed for search indexing
        return [
            'id' => (int) $array['id'],
            'file' => [
                'uuid' => $this->file?->uuid,
                'title' => $this->file?->title,
                'subtitle' => $this->file?->subtitle,
                'authors' => $this->file?->authors,
                'owners' => $this->file?->owners,
                'recipients' => $this->file?->recipients,
                'publishers' => $this->file?->publishers,
                'authoring_actors' => $this->file?->authoring_actors,
                'isbn' => $this->file?->isbn,
                'issn' => $this->file?->issn,
                'type' => $this->file?->type,
                'conerned_year' =>  $this->file?->concerned_year,
                'source_document_url' =>  $this->file?->source_document_url,
                'published_date' => $this->file?->published_date,
            ],
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
