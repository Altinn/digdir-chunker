<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Resources\TaskResource;
use App\Jobs\ChunkFile;
use App\Jobs\ConvertFileToMarkdown;
use App\Models\File;
use App\Models\Task;
use Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class TaskController extends Controller
{
    /**
     * Create a new task
     */
    public function create(Request $request): TaskResource
    {
        // Validate the request
        $validated = $request->validate([
            /**
             * The URL of the file to be processed.
             * 
             * @var string
             * @example "https://example.com/file.pdf"
             */
            "url" => "required|string",
            /**
             * Which chunking method to use.
             * 
             * @var string
             * @example "semantic"
             */
            "chunking_method" => "nullable|string|in:semantic,recursive",
            /**
             * The maximum size of each chunk in characters.
             * 
             * @var int
             * @example 512
             */
            "chunk_size" => "nullable|integer|min:1",
            /**
             * The overlap between chunks in characters (if using the recursive method).
             * 
             * @var int
             * @example 512
             */            
            "chunk_overlap" => "nullable|integer|min:0",
            /**
             * Which conversion backend to use.
             * 
             * @var string
             * @example "marker"
             */ 
            "conversion_backend" => "nullable|string|in:marker",
        ]);

        if ( ! isset($validated['url']) )
        {
            return response()->json([
                'message' => 'Missing or invalid URL',
            ], 422);
        }

        // Create a Task and an associated File
        $task = Task::create();
        $task->chunking_method = $validated['chunking_method'] ?? config('tasks.default_chunking_method');
        $task->chunk_size = $validated['chunk_size'] ?? config('tasks.default_chunk_size');
        $task->chunk_overlap = $validated['chunk_overlap'] ?? config('tasks.default_chunk_overlap');
        $task->conversion_backend = config('tasks.default_conversion_backend');
        $task->task_status = TaskStatus::Starting;
        $task->save();

        $file = new File(['url' => $validated['url']]);
        $task->file()->save($file);

        $response = Http::get($file->url);
        if ($response->failed()) {
            return response()->json([
                'message' => 'Failed to download file',
            ], 422);
        }

        $content = $response->body();

        $file->size = strlen($content);

        $file->sha256 = hash('sha256', $content);
        $file->save();

        // Dispatch jobs to process the file
        Bus::chain([
            new ConvertFileToMarkdown($file),
            new ChunkFile($file, $task->chunking_method, $task->chunk_size, $task->chunk_overlap),
        ])->dispatch();

        return new TaskResource($task);
    }

    public function cancel(Task $task)
    {
        if ($task->task_status === TaskStatus::Succeeded || $task->task_status === TaskStatus::Failed) {
            return response()->json([
                'message' => 'Task is already completed or failed, cannot cancel.',
            ], 422);
        }

        // Cancel the task
        $task->task_status = TaskStatus::Cancelled;
        $task->save();

        // Optionally, you can dispatch a job to clean up resources related to the task
        // CleanupJob::dispatch($task);

        return response()->json([
            'message' => 'Task cancelled successfully.',
        ]);
    }

    /**
     * Get a task by UUID
     */
    public function show(Task $task)
    {
        return new TaskResource($task);
    }

    private function averageProcessingTimePerPage()
    {
        $sql = "SELECT 
                    SUM(page_counts.pages * (TIMESTAMPDIFF(SECOND, t.started_at, t.finished_at) / page_counts.pages)) / SUM(page_counts.pages) as weighted_avg_seconds_per_page,
                    AVG(TIMESTAMPDIFF(SECOND, t.started_at, t.finished_at) / page_counts.pages) as simple_avg_seconds_per_page,
                    COUNT(*) as total_tasks,
                    SUM(page_counts.pages) as total_pages
                FROM tasks t
                JOIN (
                    SELECT 
                        file_id,
                        MAX(CAST(page_num AS UNSIGNED)) + 1 as pages
                    FROM chunks
                    CROSS JOIN JSON_TABLE(
                        page_numbers,
                        '$[*]' COLUMNS (page_num VARCHAR(10) PATH '$')
                    ) jt
                    WHERE file_id IS NOT NULL 
                        AND page_numbers IS NOT NULL 
                        AND JSON_VALID(page_numbers)
                    GROUP BY file_id
                ) page_counts ON t.id = page_counts.file_id
                WHERE t.started_at IS NOT NULL 
                    AND t.finished_at IS NOT NULL 
                    AND t.task_status = 'Succeeded';";

        $result = \DB::select($sql);

        return $result[0]->weighted_avg_seconds_per_page;
    }
}
