<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class UpdateKudosMetadata extends Command
{
    protected $signature = 'kudos:update-metadata 
                            {--document-id= : Update metadata for a specific Kudos document ID}
                            {--all : Update metadata for all existing Kudos documents}
                            {--dry-run : Show what would be updated without actually updating}';

    protected $description = 'Update metadata for documents that have already been imported from Kudos';

    private const KUDOS_BASE_URL = 'https://kudos.dfo.no/api/v0';

    public function handle()
    {
        $this->info('Starting Kudos metadata update...');

        if ($documentId = $this->option('document-id')) {
            return $this->updateSingleDocument($documentId);
        }

        if ($this->option('all')) {
            return $this->updateAllDocuments();
        }

        $this->error('Please specify either --document-id=ID or --all');
        return 1;
    }

    private function updateSingleDocument(string $documentId): int
    {
        // Find the task with this external ID
        $task = Task::where('external_id', $documentId)
            ->where('external_source', 'kudos')
            ->first();

        if (!$task) {
            $this->error("No imported document found with Kudos ID: {$documentId}");
            return 1;
        }

        $file = $task->file;
        if (!$file) {
            $this->error("No file associated with task for Kudos ID: {$documentId}");
            return 1;
        }

        return $this->updateFileMetadata($file, $documentId) ? 0 : 1;
    }

    private function updateAllDocuments(): int
    {
        $tasks = Task::where('external_source', 'kudos')
            ->whereNotNull('external_id')
            ->with('file')
            ->get();

        if ($tasks->isEmpty()) {
            $this->warn('No Kudos documents found to update.');
            return 0;
        }

        $this->info('Found ' . $tasks->count() . ' Kudos documents to update.');

        $updated = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            if (!$task->file) {
                $this->warn("Skipping task {$task->id} - no associated file");
                $failed++;
                continue;
            }

            if ($this->updateFileMetadata($task->file, $task->external_id)) {
                $updated++;
            } else {
                $failed++;
            }

            // Add a small delay to avoid overwhelming the API
            usleep(100000); // 100ms delay
        }

        $this->info("Update complete. Updated: {$updated}, Failed: {$failed}");
        return $failed > 0 ? 1 : 0;
    }

    private function updateFileMetadata(File $file, string $kudosId): bool
    {
        try {
            // Fetch document from Kudos API
            $response = Http::timeout(30)->get(self::KUDOS_BASE_URL . "/documents/{$kudosId}");

            if (!$response->successful()) {
                if ($response->status() === 404) {
                    $this->error("Document {$kudosId} not found in Kudos API.");
                } else {
                    $this->error("Failed to fetch document {$kudosId}: " . $response->status());
                }
                return false;
            }

            $document = $response->json();

            if (!$document) {
                $this->error("Invalid response for document {$kudosId}.");
                return false;
            }

            if ($this->option('dry-run')) {
                $this->info("Would update file {$file->id} with metadata from Kudos document {$kudosId}:");
                $this->displayMetadata($document);
                return true;
            }

            // Update file with extracted metadata
            $file->update([
                'title' => $document['title'] ?? $file->title,
                'subtitle' => $document['subtitle'] ?? $file->subtitle,
                'type' => $document['type'] ?? $file->type,
                'authors' => $this->extractAuthors($document) ?? $file->authors,
                'owners' => $this->extractActorNames($document, 'owners') ?? $file->owners,
                'recipients' => $this->extractActorNames($document, 'recipients') ?? $file->recipients,
                'publishers' => $this->extractActorNames($document, 'publishers') ?? $file->publishers,
                'authoring_actors' => $this->extractActorNames($document, 'authoring_actors') ?? $file->authoring_actors,
                'published_date' => $this->parseDate($document['publish_date'] ?? null) ?? $file->published_date,
                'authored_date' => $this->parseDate($document['authored_date'] ?? null) ?? $file->authored_date,
                'isbn' => $document['isbn'] ?? $file->isbn,
                'issn' => $document['issn'] ?? $file->issn,
                'document_id' => $document['document_id'] ?? $file->document_id,
                'kudos_id' => (string) $document['id'],
                'concerned_year' => $document['concerned_year'] ?? $file->concerned_year,
                'source_page_url' => $document['permalink'] ?? $file->source_page_url,
                'metadata_analyzed_at' => now(),
            ]);

            $this->info("Updated metadata for file {$file->id} (Kudos ID: {$kudosId})");
            return true;

        } catch (\Exception $e) {
            $this->error("Error updating metadata for Kudos document {$kudosId}: " . $e->getMessage());
            return false;
        }
    }

    private function displayMetadata(array $document): void
    {
        $metadata = [
            'Title' => $document['title'] ?? 'null',
            'Type' => $document['type'] ?? 'null',
            'Authors' => $this->extractAuthors($document) ? implode(', ', $this->extractAuthors($document)) : 'null',
            'Owners' => $this->extractActorNames($document, 'owners') ? implode(', ', $this->extractActorNames($document, 'owners')) : 'null',
            'Publishers' => $this->extractActorNames($document, 'publishers') ? implode(', ', $this->extractActorNames($document, 'publishers')) : 'null',
            'Published Date' => $document['publish_date'] ?? 'null',
            'ISBN' => $document['isbn'] ?? 'null',
        ];

        foreach ($metadata as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
    }

    // Reuse methods from ImportKudosDocuments
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

    private function extractActorNames(array $document, string $fieldName): ?array
    {
        if (empty($document[$fieldName])) {
            return null;
        }

        $actors = $document[$fieldName];

        if (!is_array($actors)) {
            return null;
        }

        $names = array_map(function ($actor) {
            if (is_string($actor)) {
                return $actor;
            }

            if (is_array($actor)) {
                return $actor['name'] ?? $actor['title'] ?? null;
            }

            return null;
        }, $actors);

        $names = array_filter($names); // Remove null values

        return empty($names) ? null : array_values($names);
    }

    private function parseDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }
}
