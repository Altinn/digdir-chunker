<?php

namespace App\Jobs;

use App\Models\Chunk;
use App\Models\File;
use App\Services\ChunkerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ChunkFile implements ShouldQueue
{
    use Queueable;

    protected File $file;

    /**
     * Create a new job instance.
     */
    public function __construct(File $file)
    {
        $this->file = $file;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = $this->file;
        $content = $file->markdown ?? "";
        $chunk_arrays = ChunkerService::chunkMarkdown($content, 1000);
        $chunks = ChunkerService::parsePageNumbers($chunk_arrays);

        foreach ($chunks as $key => $chunk_array)
        {
            Chunk::create([
                'text' => $chunk_array['text'],
                'type' => 'paragraph',
                'chunk_number' => $key,
                'page_number' => $chunk_array['page_number'],
                'file_id' => $file->id,
            ]);
        }
    }
}
