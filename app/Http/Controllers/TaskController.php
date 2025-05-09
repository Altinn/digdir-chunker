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
    public function create(Request $request)
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
        ]);

        if ( ! isset($validated['url']) )
        {
            return response()->json([
                'message' => 'Missing or invalid URL',
            ], 422);
        }

        // Create a Task and an associated File
        $task = Task::create();
        $task->chunking_method = config('tasks.default_chunking_method');
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
            new ChunkFile($file),
        ])->dispatch();

        return new TaskResource($task);
    }

    /**
     * Get a task by UUID
     */
    public function show(Task $task)
    {
        return new TaskResource($task);
    }
}
