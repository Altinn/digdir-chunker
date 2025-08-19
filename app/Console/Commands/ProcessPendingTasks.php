<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Jobs\ChunkFile;
use App\Jobs\ConvertFileToMarkdown;
use App\Jobs\GenerateChunkDerivatives;
use App\Jobs\GenerateEmbeddings;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ProcessPendingTasks extends Command
{
    protected $signature = 'tasks:process-pending 
                            {--limit=50 : Maximum number of tasks to process (default: 50)}
                            {--source= : Only process tasks from specific source (e.g., kudos)}
                            {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Process imported tasks that are in Pending status';

    public function handle()
    {
        $this->info('Finding pending tasks to process...');

        $query = Task::where('task_status', TaskStatus::Pending)
            ->whereHas('file') // Only process tasks that have an associated file
            ->with('file');

        if ($source = $this->option('source')) {
            $query->where('external_source', $source);
        }

        $limit = (int) $this->option('limit');
        $tasks = $query->orderBy('created_at', 'asc') // Process oldest first
            ->limit($limit)
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No pending tasks found.');

            return 0;
        }

        $this->info("Found {$tasks->count()} pending tasks to process.");

        if ($this->option('dry-run')) {
            $this->displayTasks($tasks);

            return 0;
        }

        $processed = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            try {
                $this->processTask($task);
                $processed++;
                $this->info("Processing task {$task->id}: {$this->getTaskDescription($task)}");
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to process task {$task->id}: ".$e->getMessage());

                // Update task status to Failed
                $task->update(['task_status' => TaskStatus::Failed]);
            }
        }

        $this->info("Processing complete. Processed: {$processed}, Failed: {$failed}");

        return 0;
    }

    private function displayTasks($tasks): void
    {
        $tableData = [];

        foreach ($tasks as $task) {
            $tableData[] = [
                $task->id,
                $task->external_source ?? 'local',
                $task->external_id ?? 'N/A',
                $this->getTaskDescription($task),
                $task->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['Task ID', 'Source', 'External ID', 'Description', 'Created'],
            $tableData
        );
    }

    private function getTaskDescription(Task $task): string
    {
        if ($task->external_source === 'kudos' && isset($task->metadata['kudos_document']['title'])) {
            $title = $task->metadata['kudos_document']['title'];

            return substr($title, 0, 40).(strlen($title) > 40 ? '...' : '');
        }

        if ($task->file && isset($task->file->metadata['filename'])) {
            return $task->file->metadata['filename'];
        }

        return $task->url ? basename($task->url) : 'Unknown';
    }

    private function processTask(Task $task): void
    {
        $file = $task->file;

        if (! $file) {
            throw new \Exception("Task {$task->id} has no associated file");
        }

        // Update task status to Starting to indicate processing has begun
        $task->update(['task_status' => TaskStatus::Starting]);

        $shouldGenerateDerivatives = config('tasks.generate_chunk_derivatives', false);
        $shouldGenerateEmbeddings = config('tasks.generate_embeddings', false);

        $jobs = [
            new ConvertFileToMarkdown($file),
            new ChunkFile($file, $task->chunking_method, $task->chunk_size, $task->chunk_overlap),
        ];

        if ($shouldGenerateDerivatives) {
            $jobs[] = new GenerateChunkDerivatives($file);
        }

        if ($shouldGenerateEmbeddings) {
            $jobs[] = new GenerateEmbeddings($file);
        }

        Bus::chain($jobs)->dispatch();
    }
}
