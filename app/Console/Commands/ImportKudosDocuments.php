<?php

namespace App\Console\Commands;

use App\Enums\ChunkingMethod;
use App\Enums\ConversionBackend;
use App\Enums\TaskStatus;
use App\Jobs\ChunkFile;
use App\Jobs\ConvertFileToMarkdown;
use App\Jobs\GenerateChunkDerivatives;
use App\Jobs\GenerateEmbeddings;
use App\Models\File;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class ImportKudosDocuments extends Command
{
    protected $signature = 'kudos:import 
                            {--from-date= : Import documents from this date (YYYY-MM-DD)}
                            {--to-date= : Import documents until this date (YYYY-MM-DD)}
                            {--actor-id= : Filter by specific actor ID}
                            {--query= : Search query string}
                            {--type=* : Document types to import (e.g., Tildelingsbrev, Studie)}
                            {--sort= : Sort documents (date-descending, date-ascending, relevance)}
                            {--limit=100 : Maximum number of documents to import (default: 100)}
                            {--dry-run : Show what would be imported without actually importing}
                            {--process-documents : Automatically process imported documents (conversion + chunking)}';

    protected $description = 'Import documents from the Kudos API';

    private const KUDOS_BASE_URL = 'https://kudos.dfo.no/api/v0';

    public function handle()
    {
        $this->info('Starting Kudos document import...');

        $documents = $this->fetchDocuments();

        if (empty($documents)) {
            $this->warn('No documents found matching the criteria.');
            return 0;
        }

        $this->info('Found ' . count($documents) . ' documents.');

        if ($this->option('dry-run')) {
            $this->displayDocuments($documents);
            return 0;
        }

        $imported = 0;
        $failed = 0;

        foreach ($documents as $document) {
            try {
                $task = $this->importDocument($document);
                
                if ($task && $this->option('process-documents')) {
                    $this->processDocument($task);
                }
                
                $imported++;
                $this->info("Imported: {$document['title']} (ID: {$document['id']})");
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to import document {$document['id']}: " . $e->getMessage());
            }
        }

        $this->info("Import complete. Imported: {$imported}, Failed: {$failed}");
        return 0;
    }

    private function fetchDocuments(): array
    {
        $params = [
            'page' => 1,
        ];

        if ($fromDate = $this->option('from-date')) {
            $params['from_date'] = $fromDate;
        }

        if ($toDate = $this->option('to-date')) {
            $params['to_date'] = $toDate;
        }

        if ($actorId = $this->option('actor-id')) {
            $params['actor_id'] = $actorId;
        }

        // Determine endpoint and set up sorting
        $endpoint = '/documents';
        $sortOption = $this->option('sort');
        $useSearchEndpoint = false;
        
        if ($query = $this->option('query')) {
            $endpoint = '/documents/search';
            $params['query'] = $query;
            $params['search_type'] = 'fulltext';
            $useSearchEndpoint = true;
        }

        // Handle sorting - search endpoint supports sorting, regular endpoint doesn't
        if ($sortOption) {
            $validSortOptions = ['date-descending', 'date-ascending', 'relevance'];
            
            if (!in_array($sortOption, $validSortOptions)) {
                $this->error("Invalid sort option. Valid options are: " . implode(', ', $validSortOptions));
                return [];
            }

            if (!$useSearchEndpoint && $sortOption) {
                // If sorting is requested but we're not using search endpoint, switch to search endpoint
                $endpoint = '/documents/search';
                $params['query'] = ''; // Empty query to get all documents
                $params['search_type'] = 'fulltext';
                $useSearchEndpoint = true;
            }
            
            if ($useSearchEndpoint) {
                $params['sort'] = $sortOption;
            }
        }

        // Add document type filters
        if ($types = $this->option('type')) {
            $filters = ['type' => $types];
            $params['filters'] = json_encode($filters);
        }

        $limit = (int) $this->option('limit');
        $documents = [];
        $page = 1;

        $this->info('Fetching documents from Kudos API...');

        do {
            $params['page'] = $page;
            
            $response = Http::timeout(30)->get(self::KUDOS_BASE_URL . $endpoint, $params);

            if (!$response->successful()) {
                $this->error('Failed to fetch documents: ' . $response->status());
                break;
            }

            $data = $response->json();
            $pageDocuments = $data['data'] ?? [];
            
            if (empty($pageDocuments)) {
                break;
            }

            $documents = array_merge($documents, $pageDocuments);
            
            if (count($documents) >= $limit) {
                $documents = array_slice($documents, 0, $limit);
                break;
            }

            $page++;
            $lastPage = (int) ($data['meta']['last_page'] ?? 1);
            
        } while ($page <= $lastPage);

        return $documents;
    }

    private function displayDocuments(array $documents): void
    {
        $this->table(
            ['ID', 'Title', 'Type', 'Publish Date', 'Files'],
            array_map(function ($doc) {
                return [
                    $doc['id'],
                    substr($doc['title'] ?? 'No title', 0, 50) . (strlen($doc['title'] ?? '') > 50 ? '...' : ''),
                    $doc['type'] ?? 'Unknown',
                    $doc['publish_date'] ? Carbon::parse($doc['publish_date'])->format('Y-m-d') : 'Unknown',
                    count($doc['files'] ?? []),
                ];
            }, $documents)
        );
    }

    private function importDocument(array $document): ?Task
    {
        // Check if we already have this document
        $existingTask = Task::where('external_id', (string) $document['id'])
                            ->where('external_source', 'kudos')
                            ->first();

        if ($existingTask) {
            $this->warn("Document {$document['id']} already exists, skipping.");
            return $existingTask;
        }

        // Find the primary PDF file to import
        $pdfFile = collect($document['files'] ?? [])
            ->filter(fn($file) => $file['mimetype'] === 'application/pdf')
            ->sortByDesc('size') // Get the largest PDF if multiple
            ->first();

        if (!$pdfFile) {
            $this->warn("No PDF file found for document {$document['id']}, skipping.");
            return null;
        }

        // Create task
        $task = Task::create([
            'url' => $pdfFile['url'],
            'task_status' => TaskStatus::Pending,
            'conversion_backend' => ConversionBackend::from(config('tasks.default_conversion_backend')),
            'chunking_method' => ChunkingMethod::from(config('tasks.default_chunking_method')),
            'chunk_size' => config('tasks.default_chunk_size'),
            'chunk_overlap' => config('tasks.default_chunk_overlap'),
            'expires_at' => now()->addMinutes((int) config('tasks.default_deletion_delay_minutes')),
            'external_id' => (string) $document['id'],
            'external_source' => 'kudos',
            'metadata' => [
                'kudos_document' => $document,
                'source_file' => $pdfFile,
            ],
        ]);

        // Create associated file record
        File::create([
            'task_id' => $task->id,
            'url' => $pdfFile['url'],
            'size' => $pdfFile['size'],
            'sha256' => $pdfFile['sha256'],
            'metadata' => [
                'filename' => $pdfFile['filename'],
                'mimetype' => $pdfFile['mimetype'],
                'pages' => $pdfFile['pages'],
                'description' => $pdfFile['description'],
            ],
        ]);

        return $task;
    }

    private function processDocument(Task $task): void
    {
        $this->info("Starting processing for task {$task->id}");
        
        $file = $task->file;
        if (!$file) {
            $this->error("No file associated with task {$task->id}");
            return;
        }

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