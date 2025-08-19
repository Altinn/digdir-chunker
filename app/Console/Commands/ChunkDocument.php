<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ChunkDocument extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:chunk-document {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Split a document into chunks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (! $this->argument('file')) {
            $this->error('Please provide a file to chunk');

            return;
        }

        $string = file_get_contents($this->argument('file'));

        $chunks = $this->splitMarkdownIntoChunks($string, 1000);

        foreach ($chunks[1] as $chunk) {

        }

    }
}
