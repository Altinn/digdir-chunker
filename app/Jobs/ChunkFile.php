<?php

namespace App\Jobs;

use App\Enums\ChunkingMethod;
use App\Enums\ChunkType;
use App\Enums\TaskStatus;
use App\Models\Chunk;
use App\Models\File;
use App\Services\ChunkerService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
    public function __construct(File $file, $chunkingMethod = null, $chunkSize = null, $chunkOverlap = null)
    {
        $this->file = $file;
        $this->chunkingMethod = $chunkingMethod ?? config('tasks.default_chunking_method');
        $this->chunkSize = $chunkSize ?? config('tasks.default_chunk_size');
        $this->chunkOverlap = $chunkOverlap ?? config('tasks.default_chunk_overlap');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = $this->file;

        $content = $file->markdown ?? '';

        if (empty($file->markdown)) {
            Log::error('File does not have markdown content: '.$file->id);
            $this->file->task->task_status = TaskStatus::Failed;

            return;
        }

        switch ($this->chunkingMethod) {
            case ChunkingMethod::Semantic:
                $chunk_arrays = ChunkerService::chunkSemantic($content, $this->chunkSize);
                break;
            case ChunkingMethod::Recursive:
                $chunk_arrays = ChunkerService::chunkRecursive($content, $this->chunkSize, $this->chunkOverlap);
                break;
            default:
                $chunk_arrays = ChunkerService::chunkSemantic($content, $this->chunkSize);
        }

        $chunks = ChunkerService::parsePageNumbers($chunk_arrays);

        foreach ($chunks as $key => $chunk_array) {
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

    /**
     * Handles job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ChunkFile job failed for file ID {$this->file->id}: ".$exception->getMessage());
        $this->file->task->task_status = TaskStatus::Failed;
        $this->file->task->save();
    }
}
