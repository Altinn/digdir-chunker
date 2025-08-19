<?php

namespace App\Mcp;

use App\Http\Controllers\TaskController;
use App\Http\Resources\TaskResource;
use App\Models\Chunk;
use App\Models\File;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpMcp\Server\Attributes\McpResource;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Storage;

class DocumentService
{
    /**
     * Import a PDF document
     */
    #[McpTool(name: 'import_document', description: 'Import a document from the provided URL. After calling this tool, you can use the `get_document_task_info` tool to check the status of the import task. Check the task status after 3 seconds.')]
    public function importDocument(string $url): TaskResource
    {
        $controller = new TaskController;
        $request = new Request(['url' => $url]);

        $response = $controller->create($request);

        return $response;
    }

    /**
     * Get info about a task related to a document
     */
    #[McpTool(name: 'get_document_task_info', description: 'Get information about a document processing task')]
    public function getDocumentTaskInfo(string $task_uuid): TaskResource|array
    {
        $controller = new TaskController;
        $task = Task::where('uuid', $task_uuid)->first();

        if (empty($task)) {
            return [
                'uuid' => $task_uuid,
                'error' => 'The specified task does not exist.',
            ];
        }
        $response = $controller->show($task);

        return $response;
    }

    /**
     * Get the full content of a file
     */
    #[McpTool(name: 'get_markdown', description: 'Get the markdown content of the document identified by file_uuid. This tool is useful for retrieving the markdown representation of a document.')]
    public function getMarkdown(string $file_uuid): array
    {
        $markdown = File::where('uuid', $file_uuid)
            ->get()
            ->first()
            ->markdown;

        return [
            'uuid' => $file_uuid,
            'markdown' => $markdown ?: 'No markdown content available for this file.',
        ];
    }

    /**
     * Search for documents based on a query with optional filtering.
     */
    #[McpTool(name: 'search_files', description: 'Search for files based on a query with optional filtering. Use the get_available_search_filters tool before using filters. Example filter syntax: [{"file_type": "Evaluering"}]')]
    public function searchFiles(
        #[Schema(type: 'string')]
        string $query,

        #[Schema(type: 'array', description: 'An array of filters to apply on the search. Key is the filter name, value is the filter value.')]
        array $filters = [],
        
        ?string $sort_by = null,
        
        ?string $sort_direction = 'desc',
        
        ?int $limit = 20
    ): array
    {
        $searchBuilder = Chunk::search($query);

        // Get filterable attributes from scout config
        $filterableAttributes = config('scout.meilisearch.index-settings.chunks.filterableAttributes', []);
        $sortableAttributes = config('scout.meilisearch.index-settings.chunks.sortableAttributes', []);

        $appliedFilters = [];
        Log::info('Received filters: ' . json_encode($filters));
        Log::info('Filterable attributes from config: ' . json_encode($filterableAttributes));
        
        // Handle case where filters come as an indexed array of objects
        $normalizedFilters = [];
        if (!empty($filters) && isset($filters[0]) && is_array($filters[0])) {
            // Filters came as [{"file_type": "Studie"}] - flatten to {"file_type": "Studie"}
            foreach ($filters as $filterObject) {
                if (is_array($filterObject)) {
                    $normalizedFilters = array_merge($normalizedFilters, $filterObject);
                }
            }
        } else {
            // Filters came as {"file_type": "Studie"} - use as is
            $normalizedFilters = $filters;
        }
        
        Log::info('Normalized filters: ' . json_encode($normalizedFilters));

        // Dynamically apply filters based on what's provided and what's allowed
        foreach ($normalizedFilters as $attribute => $value) {
            Log::info("Processing filter - Attribute: {$attribute}, Value: " . json_encode($value));
            
            if (!in_array($attribute, $filterableAttributes)) {
                Log::warning("Skipping filter '{$attribute}' - not in filterable attributes");
                continue; // Skip if attribute is not filterable
            }

            if (is_null($value) || (is_array($value) && empty($value))) {
                Log::warning("Skipping filter '{$attribute}' - null or empty value");
                continue; // Skip null or empty values
            }

            if (is_array($value)) {
                // For array values, apply OR logic within the attribute
                Log::info("Applying array filter for '{$attribute}' with values: " . json_encode($value));
                foreach ($value as $item) {
                    $searchBuilder = $searchBuilder->where($attribute, $item);
                }
            } else {
                // For single values
                Log::info("Applying single filter for '{$attribute}' with value: {$value}");
                $searchBuilder = $searchBuilder->where($attribute, $value);
            }

            $appliedFilters[$attribute] = $value;
        }
        
        Log::info('Final applied filters: ' . json_encode($appliedFilters));

        // Apply sorting if specified and allowed
        if ($sort_by && in_array($sort_by, $sortableAttributes)) {
            $direction = in_array(strtolower($sort_direction), ['asc', 'desc']) ? strtolower($sort_direction) : 'desc';
            $searchBuilder = $searchBuilder->orderBy($sort_by, $direction);
        }

        // Apply limit (on chunks, not files)
        $chunkLimit = max(1, min($limit ?? 20, 100)) * 20; // Get more chunks to ensure we have enough files
        
        Log::info("Executing search with query: '{$query}', chunk limit: {$chunkLimit}");
        $chunkResults = $searchBuilder->take($chunkLimit)->get();
        
        // Load file relationships to avoid N+1 queries
        $chunkResults->load('file');
        
        Log::info("Search returned {$chunkResults->count()} chunks");

        // Group chunks by file and construct file results
        $fileGroups = [];
        foreach ($chunkResults as $chunk) {
            $fileId = $chunk->file_id;
            
            if (!isset($fileGroups[$fileId])) {
                $fileGroups[$fileId] = [
                    'file' => $chunk->file,
                    'chunks' => []
                ];
            }
            
            $fileGroups[$fileId]['chunks'][] = [
                'id' => $chunk->id,
                'chunk_number' => $chunk->chunk_number,
                'page_numbers' => $chunk->page_numbers,
                'chunk_type' => $chunk->chunk_type,
            ];
        }

        // Convert to final result format and apply file limit
        $files = [];
        $fileCount = 0;
        $maxFiles = max(1, min($limit ?? 20, 100));
        
        foreach ($fileGroups as $fileGroup) {
            if ($fileCount >= $maxFiles) {
                break;
            }
            
            $file = $fileGroup['file'];
            if (!$file) {
                continue; // Skip if file doesn't exist
            }
            
            $files[] = [
                'uuid' => $file->uuid,
                'url' => $file->url,
                'document_page_url' => $file->kudos_id ? 'https://kudos.dfo.no/dokument/' . $file->kudos_id : null,
                'title' => $file->title,
                'subtitle' => $file->subtitle,
                'type' => $file->type,
                'authors' => $file->authors,
                'owners' => $file->owners,
                'recipients' => $file->recipients,
                'publishers' => $file->publishers,
                'authoring_actors' => $file->authoring_actors,
                'published_date' => $file->published_date?->toDateString(),
                'authored_date' => $file->authored_date?->toDateString(),
                'isbn' => $file->isbn,
                'issn' => $file->issn,
                'document_id' => $file->document_id,
                'kudos_id' => $file->kudos_id,
                'concerned_year' => $file->concerned_year,
                'source_document_url' => $file->source_document_url,
                'source_page_url' => $file->source_page_url,
                'matching_chunks' => $fileGroup['chunks'],
                'total_matching_chunks' => count($fileGroup['chunks']),
            ];
            
            $fileCount++;
        }

        return [
            'query' => $query,
            'available_filters' => $filterableAttributes,
            'available_sorting' => $sortableAttributes,
            'filters_applied' => $appliedFilters,
            'sort_by' => $sort_by,
            'sort_direction' => $sort_direction,
            'limit' => $limit,
            'total_files' => count($files),
            'total_chunks_found' => $chunkResults->count(),
            'results' => $files,
        ];
    }

    #[McpResource(name: 'get_image', description: 'Get an image from the provided URL. This tool can be used to retrieve images stored in the application\'s storage, referenced from the document markdown or other sources.')]
    public function getImage(string $url): array
    {

        $parsedUrl = parse_url($url);
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $segments = explode('/', trim($path, '/')); // trim to remove leading/trailing slashes
        array_shift($segments); // remove the first segment
        $remainingPath = implode('/', $segments);

        $data = Storage::get($remainingPath);

        return [
            'image_html_tag' => '<img src="data:image/jpeg;base64,'.base64_encode($data).'" />',
        ];
    }

    #[McpResource(name: 'get_available_search_filters', description: 'Get the available search filters for the document search.')]
    public function getAvailableSearchFilters(): array
    {
        // Get filterable attributes from scout config
        $filterableAttributes = config('scout.meilisearch.index-settings.chunks.filterableAttributes', []);

        return [
            'available_filters' => $filterableAttributes,
        ];
    }
}
