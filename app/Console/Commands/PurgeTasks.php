<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:purge-tasks {--include-null-dates : Include tasks with null delete_at dates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge tasks that are scheduled for deletion';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Task::where('delete_at', '<=', Carbon::now());

        if ($this->option('include-null-dates')) {
            $query->orWhere('delete_at', null);
        }

        $tasks = $query->get();

        foreach ($tasks as $task) {
            $task->file()->delete();
            $task->delete();
            $this->info("Purged task with ID: {$task->id}");
        }

    }
}
