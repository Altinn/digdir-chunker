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
                            {--document-id= : Import a specific document by ID}
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

        $this->info('Found '.count($documents).' documents.');

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
                $this->error("Failed to import document {$document['id']}: ".$e->getMessage());
            }
        }

        $this->info("Import complete. Imported: {$imported}, Failed: {$failed}");

        return 0;
    }

    private function fetchDocuments(): array
    {
        // Handle single document import by ID
        if ($documentId = $this->option('document-id')) {
            return $this->fetchSingleDocument($documentId);
        }

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

            if (! in_array($sortOption, $validSortOptions)) {
                $this->error('Invalid sort option. Valid options are: '.implode(', ', $validSortOptions));

                return [];
            }

            if (! $useSearchEndpoint && $sortOption) {
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

            $response = Http::timeout(30)->get(self::KUDOS_BASE_URL.$endpoint, $params);

            if (! $response->successful()) {
                $this->error('Failed to fetch documents: '.$response->status());
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

    private function fetchSingleDocument(string $documentId): array
    {
        $this->info("Fetching document {$documentId} from Kudos API...");

        $response = Http::timeout(30)->get(self::KUDOS_BASE_URL."/documents/{$documentId}");

        if (!$response->successful()) {
            if ($response->status() === 404) {
                $this->error("Document {$documentId} not found.");
                return [];
            }
            
            $this->error("Failed to fetch document {$documentId}: ".$response->status());
            return [];
        }

        $document = $response->json();
        
        if (!$document) {
            $this->error("Invalid response for document {$documentId}.");
            return [];
        }

        return [$document];
    }

    private function displayDocuments(array $documents): void
    {
        $this->table(
            ['ID', 'Title', 'Type', 'Publish Date', 'Files'],
            array_map(function ($doc) {
                return [
                    $doc['id'],
                    substr($doc['title'] ?? 'No title', 0, 50).(strlen($doc['title'] ?? '') > 50 ? '...' : ''),
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
            ->filter(fn ($file) => $file['mimetype'] === 'application/pdf')
            ->sortByDesc('size') // Get the largest PDF if multiple
            ->first();

        if (! $pdfFile) {
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

        // Debug: Log document structure for troubleshooting
        if ($this->option('verbose')) {
            $this->info('Document structure:');
            $this->line(json_encode($document, JSON_PRETTY_PRINT));
        }

        // Create associated file record with extracted metadata
        File::create([
            'task_id' => $task->id,
            'url' => $pdfFile['url'],
            'size' => $pdfFile['size'],
            'sha256' => $pdfFile['sha256'],
            // Extract metadata from Kudos document
            'title' => $document['title'] ?? null,
            'subtitle' => $document['subtitle'] ?? null,
            'type' => $document['type'] ?? null,
            'authors' => $this->extractAuthors($document),
            'owners' => $this->extractActorNames($document, 'owners'),
            'recipients' => $this->extractActorNames($document, 'recipients'),
            'publishers' => $this->extractActorNames($document, 'publishers'),
            'authoring_actors' => $this->extractActorNames($document, 'authoring_actors'),
            'published_date' => $this->parseDate($document['publish_date'] ?? null),
            'authored_date' => $this->parseDate($document['authored_date'] ?? null),
            'isbn' => $document['isbn'] ?? null,
            'issn' => $document['issn'] ?? null,
            'document_id' => $document['document_id'] ?? null,
            'kudos_id' => (string) $document['id'],
            'concerned_year' => $document['concerned_year'] ?? null,
            'source_document_url' => $pdfFile['url'],
            'source_page_url' => $document['permalink'] ?? null,
            'metadata_analyzed_at' => now(),
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
        if (! $file) {
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

    /**
     * Extract authors from Kudos document
     */
    private function extractAuthors(array $document): ?array
    {
        if (empty($document['authors'])) {
            return null;
        }

        return array_map(function ($author) {
            if (is_string($author)) {
                return $author;
            }

            return $author['name'] ?? $author['title'] ?? null;
        }, $document['authors']);
    }

    /**
     * Extract actor names from Kudos document field
     */
    private function extractActorNames(array $document, string $fieldName): ?array
    {
        if (empty($document[$fieldName])) {
            if ($this->option('verbose')) {
                $this->warn("Field '{$fieldName}' not found or empty");
            }
            return null;
        }

        $actors = $document[$fieldName];
        
        if (!is_array($actors)) {
            if ($this->option('verbose')) {
                $this->warn("Field '{$fieldName}' is not an array");
            }
            return null;
        }

        $names = array_map(function ($actor) use ($fieldName) {
            if (is_string($actor)) {
                return $actor;
            }
            
            if (is_array($actor)) {
                return $actor['name'] ?? $actor['title'] ?? null;
            }
            
            return null;
        }, $actors);

        $names = array_filter($names); // Remove null values

        if ($this->option('verbose')) {
            $this->info("Extracted " . count($names) . " names from '{$fieldName}': " . implode(', ', $names));
        }

        return empty($names) ? null : array_values($names);
    }

    /**
     * Extract actors by role from Kudos document (returns full actor objects)
     */
    private function extractActorsByRole(array $document, string $role): ?array
    {
        $actors = $document['actors'] ?? [];

        if (empty($actors)) {
            if ($this->option('verbose')) {
                $this->warn("No actors found in document for role: {$role}");
            }
            return null;
        }

        if ($this->option('verbose')) {
            $this->info("Found " . count($actors) . " actors, looking for role: {$role}");
            foreach ($actors as $i => $actor) {
                $this->line("Actor {$i}: role=" . ($actor['role'] ?? 'null') . ", name=" . ($actor['name'] ?? $actor['title'] ?? 'unknown'));
            }
        }

        $filteredActors = array_filter($actors, function ($actor) use ($role) {
            return ($actor['role'] ?? '') === $role;
        });

        if (empty($filteredActors)) {
            if ($this->option('verbose')) {
                $this->warn("No actors found with role: {$role}");
            }
            return null;
        }

        $result = array_map(function ($actor) {
            return [
                'name' => $actor['name'] ?? null,
                'title' => $actor['title'] ?? null,
                'id' => $actor['id'] ?? null,
                'role' => $actor['role'] ?? null,
            ];
        }, array_values($filteredActors));

        if ($this->option('verbose')) {
            $this->info("Extracted " . count($result) . " actors for role: {$role}");
        }

        return $result;
    }

    /**
     * Parse date string to Carbon instance or null
     */
    private function parseDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            $this->warn("Failed to parse date: {$dateString}");

            return null;
        }
    }
}
