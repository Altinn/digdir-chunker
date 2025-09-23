<?php

namespace App\Console\Commands;

use App\Enums\ChunkingMethod;
use App\Jobs\ChunkFile;
use App\Jobs\ConvertFileToMarkdown;
use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ChunkUnprocessedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:chunk-unprocessed 
                            {--chunk-size= : Override default chunk size}
                            {--chunk-overlap= : Override default chunk overlap}
                            {--chunking-method= : Override default chunking method (semantic/recursive)}
                            {--limit= : Limit number of files to process}
                            {--batch-size=100 : Number of files to process in each batch}
                            {--no-batch : Dispatch jobs individually instead of in batches}
                            {--convert-first : Also convert files without markdown before chunking}
                            {--queue=default : Queue to use for conversion jobs (default, long, gpu)}
                            {--dry-run : Show what would be processed without actually queuing jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue chunking jobs for all files that have not been chunked yet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chunkSize = $this->option('chunk-size') ?? config('tasks.default_chunk_size');
        $chunkOverlap = $this->option('chunk-overlap') ?? config('tasks.default_chunk_overlap');
        
        // Handle chunking method as enum
        $chunkingMethodOption = $this->option('chunking-method') ?? config('tasks.default_chunking_method');
        if (is_string($chunkingMethodOption)) {
            $chunkingMethod = ChunkingMethod::tryFrom($chunkingMethodOption) ?? ChunkingMethod::Semantic;
        } else {
            $chunkingMethod = $chunkingMethodOption;
        }
        
        $limit = $this->option('limit');
        $batchSize = (int) $this->option('batch-size');
        $noBatch = $this->option('no-batch');
        $dryRun = $this->option('dry-run');
        $convertFirst = $this->option('convert-first');
        $queue = $this->option('queue');

        // First, handle files without markdown if requested
        if ($convertFirst) {
            $this->handleFilesWithoutMarkdown($limit, $batchSize, $noBatch, $dryRun, $queue);
        }

        $query = File::query()
            ->select(['id', 'uuid', 'task_id', 'markdown'])
            ->whereNotNull('markdown')
            ->where('markdown', '!=', '')
            ->whereDoesntHave('chunks');

        if ($limit) {
            $query->limit($limit);
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No unchunked files found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} files to chunk.");
        
        if ($dryRun) {
            $this->info('Dry run mode - no jobs will be queued.');
            $this->info("Would process {$totalCount} files in batches of {$batchSize}");
            
            $this->withProgressBar($query->cursor(), function ($file) {
                // Just iterate to show progress
            });
            
            $this->newLine();
            return Command::SUCCESS;
        }

        $this->info("Processing {$totalCount} files in batches of {$batchSize}...");
        $this->info("Chunking parameters:");
        $this->info("  - Method: {$chunkingMethod->value}");
        $this->info("  - Chunk Size: {$chunkSize}");
        $this->info("  - Chunk Overlap: {$chunkOverlap}");
        $this->newLine();

        $processedCount = 0;
        $jobs = [];

        $this->withProgressBar($query->cursor(), function ($file) use (
            &$jobs,
            &$processedCount,
            $chunkingMethod,
            $chunkSize,
            $chunkOverlap,
            $batchSize,
            $noBatch
        ) {
            if ($noBatch) {
                // Dispatch immediately without batching
                ChunkFile::dispatch($file, $chunkingMethod, $chunkSize, $chunkOverlap);
                $processedCount++;
            } else {
                // Collect jobs for batch processing
                $jobs[] = new ChunkFile($file, $chunkingMethod, $chunkSize, $chunkOverlap);
                
                // When we reach batch size, dispatch the batch
                if (count($jobs) >= $batchSize) {
                    Bus::batch($jobs)
                        ->name('Chunk Unprocessed Files - Batch')
                        ->dispatch();
                    
                    $processedCount += count($jobs);
                    $jobs = [];
                }
            }
        });

        // Dispatch any remaining jobs in the final batch
        if (!$noBatch && count($jobs) > 0) {
            Bus::batch($jobs)
                ->name('Chunk Unprocessed Files - Final Batch')
                ->dispatch();
            
            $processedCount += count($jobs);
        }

        $this->newLine(2);
        $this->info("Successfully queued {$processedCount} chunking jobs.");

        return Command::SUCCESS;
    }

    /**
     * Handle files without markdown by converting them first
     */
    private function handleFilesWithoutMarkdown($limit, $batchSize, $noBatch, $dryRun, $queue)
    {
        $query = File::query()
            ->select(['id', 'uuid', 'task_id', 'url'])
            ->where(function($q) {
                $q->whereNull('markdown')
                  ->orWhere('markdown', '=', '');
            })
            ->whereNotNull('url')
            ->where('url', '!=', '');

        if ($limit) {
            $query->limit($limit);
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No files without markdown found.');
            return;
        }

        $this->info("Found {$totalCount} files without markdown to convert.");
        
        if ($dryRun) {
            $this->info('Dry run mode - would queue conversion jobs for these files.');
            
            $this->withProgressBar($query->cursor(), function ($file) {
                // Just iterate to show progress
            });
            
            $this->newLine();
            return;
        }

        $this->info("Converting {$totalCount} files to markdown first...");
        $this->info("Using queue: {$queue}");
        $this->newLine();

        $processedCount = 0;
        $jobs = [];

        $this->withProgressBar($query->cursor(), function ($file) use (
            &$jobs,
            &$processedCount,
            $batchSize,
            $noBatch,
            $queue
        ) {
            if ($noBatch) {
                // Dispatch immediately without batching
                if ($queue === 'default') {
                    ConvertFileToMarkdown::dispatch($file);
                } else {
                    ConvertFileToMarkdown::dispatch($file)->onQueue($queue);
                }
                $processedCount++;
            } else {
                // Collect jobs for batch processing
                $jobs[] = new ConvertFileToMarkdown($file);
                
                // When we reach batch size, dispatch the batch
                if (count($jobs) >= $batchSize) {
                    if ($queue === 'default') {
                        Bus::batch($jobs)
                            ->name('Convert Files to Markdown - Batch')
                            ->dispatch();
                    } else {
                        Bus::batch($jobs)
                            ->name('Convert Files to Markdown - Batch')
                            ->onQueue($queue)
                            ->dispatch();
                    }
                    
                    $processedCount += count($jobs);
                    $jobs = [];
                }
            }
        });

        // Dispatch any remaining jobs in the final batch
        if (!$noBatch && count($jobs) > 0) {
            if ($queue === 'default') {
                Bus::batch($jobs)
                    ->name('Convert Files to Markdown - Final Batch')
                    ->dispatch();
            } else {
                Bus::batch($jobs)
                    ->name('Convert Files to Markdown - Final Batch')
                    ->onQueue($queue)
                    ->dispatch();
            }
            
            $processedCount += count($jobs);
        }

        $this->newLine(2);
        $this->info("Successfully queued {$processedCount} conversion jobs.");
        $this->info("Note: Chunking will need to be run separately after conversion completes.");
        $this->newLine();
    }
}