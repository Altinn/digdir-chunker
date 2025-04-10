<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $file = $this->file;

        $output = shell_exec("marker_single {$file->path} --paginate_output --output_ --disable_image_extraction");
    }
}
