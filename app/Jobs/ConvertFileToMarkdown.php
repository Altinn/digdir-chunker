<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Log;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class ConvertFileToMarkdown implements ShouldQueue
{
    use Queueable;

    public File $file;

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
        $file_path = $directory->path(basename($this->file->url));

        file_put_contents($file_path, file_get_contents($this->file->url));

        $output = shell_exec("source /opt/marker/bin/activate && marker_single {$file_path} --output_dir {$directory->path($this->file->sha256)} --paginate_output --disable_image_extraction");

        Log::info($directory->path($this->file->sha256));

        // $directory->delete();
        
    }
}
