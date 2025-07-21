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

        // Estimate the time it will take to process the file
        $this->file->task->estimated_finished_at = now()->addSeconds($this->averageProcessingTimePerPage() * $this->numberOfPages($file_path));

        $this->file->task->save();


        $command = "marker_single {$file_path} --paginate_output --output_dir {$directory->path()}";
        exec($command, $outputLines, $exitCode);

        if ($exitCode !== 0) {
            Log::error("Command failed with exit code {$exitCode}: " . $command);
            $this->file->task->task_status = TaskStatus::Failed;
            $this->file->task->save();
            return;
        } else {
            Log::debug("Command succeeded: " . $command . "\n\n" . implode("\n", $outputLines));
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

    private function averageProcessingTimePerPage(): float
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

        return (float) $result[0]->weighted_avg_seconds_per_page;
    }

    private function numberOfPages(string $file_path): int {
        $output = shell_exec("pdfinfo '{$file_path}' | grep Pages");
        
        if ($output) {
            preg_match('/Pages:\s*(\d+)/', $output, $matches);
            return (int) isset($matches[1]) ? (int)$matches[1] : 0;
        }

        return 1;
    }
}
