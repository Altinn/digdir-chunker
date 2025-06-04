<?php

namespace App\Jobs;

use App\Enums\ChunkingMethod;
use App\Enums\ChunkType;
use App\Enums\TaskStatus;
use App\Models\Chunk;
use App\Models\File;
use App\Services\ChunkerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use Log;

class ChunkFile implements ShouldQueue
{
    use Queueable;

    protected File $file;

    protected ChunkingMethod $chunkingMethod;

    protected int $chunkSize;

    protected int $chunkOverlap;

    /**
     * Create a new job instance.
     */
    public function __construct(File $file, $chunkingMethod, $chunkSize, $chunkOverlap = 0)
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

        if ( empty($file->markdown) )
        {
            Log::error("File does not have markdown content: " . $file->id);
            $this->file->task->task_status = TaskStatus::Failed;
            return;
        }

        $chunk_arrays = ChunkerService::chunkSemantic($content, $this->chunkSize);
        $chunks = ChunkerService::parsePageNumbers($chunk_arrays);

        foreach ($chunks as $key => $chunk_array)
        {
            Chunk::create([
                'text' => $chunk_array['text'],
                'chunk_type' => ChunkType::Paragraph,
                'chunk_number' => $key,
                'page_numbers' => $chunk_array['page_numbers'],
                'file_id' => $file->id,
            ]);
        }

        $file->task->task_status = TaskStatus::Succeeded;
        $file->task->finished_at = Carbon::now();
        $file->task->save();
    }
}
