<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

class GenerateEmbeddings implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public File $file
    ) {
    }

    public function handle(): void
    {
        foreach ($this->file->chunks as $chunk) {
            if ($chunk->embeddings()->exists()) {
                continue;
            }

            $embedding = $this->generateEmbedding($chunk->text);

            $chunk->embeddings()->create([
                'provider' => config('prism.embeddings_provider'),
                'model' => config('prism.embeddings_model'),
                'embedding' => $embedding,
            ]);
        }
    }

    private function generateEmbedding(string $text): array
    {
        $response = Prism::embeddings()->using(Provider::{config('prism.embeddings_provider')}, config('prism.embeddings_model'))
            ->frominput($text)
            ->asEmbeddings();
    
        return $response->embeddings[0]->embedding;
    }
}
