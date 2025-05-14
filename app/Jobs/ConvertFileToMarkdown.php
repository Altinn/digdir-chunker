<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\File;
use Carbon\Carbon;
use Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Log;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ConvertFileToMarkdown implements ShouldQueue
{
    use Queueable;

    public File $file;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 7200;

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
        $directory = (new TemporaryDirectory())->create();
        $file_path = $directory->path("downloaded_file.pdf");

        $response = Http::get($this->file->url);
        if ($response->failed()) {
            Log::error("Failed to download file: " . $this->file->url);
            $this->file->task->task_status = TaskStatus::Failed;
            $this->file->task->save();
            return;
        }

        // Save the file to the temporary directory
        file_put_contents($file_path, $response->body());

        $this->file->task->started_at = Carbon::now();
        $this->file->task->task_status = TaskStatus::Processing;
        $this->file->task->save();


        $command = "/opt/marker/bin/marker_single {$file_path} --disable_image_extraction --paginate_output --output_dir {$directory->path()}";
        exec($command, $outputLines, $exitCode);

        if ($exitCode !== 0) {
            Log::error("Command failed with exit code {$exitCode}: " . $command);
            $this->file->task->task_status = TaskStatus::Failed;
            $this->file->task->save();
            return;
        } else {
            Log::debug("Command succeeded: " . implode("\n", $outputLines));
        }
        
        $markdown_file_path = $directory->path() . DIRECTORY_SEPARATOR . "downloaded_file" . DIRECTORY_SEPARATOR . "downloaded_file.md";

        if (file_exists($markdown_file_path)) {
            $this->file->markdown = file_get_contents($markdown_file_path);
            $this->file->save();
        } else {
            Log::error("Markdown file not found: " . $markdown_file_path);
            $this->file->task->task_status = TaskStatus::Failed;
            $this->file->task->save();
        }

         $directory->delete();
    }
}
