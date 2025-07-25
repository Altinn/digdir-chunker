<?php

namespace App\Mcp;

use App\Http\Controllers\TaskController;
use App\Http\Resources\TaskResource;
use App\Models\Chunk;
use App\Models\File;
use App\Models\Task;
use PhpMcp\Server\Attributes\{McpResource};
use PhpMcp\Server\Attributes\{McpResourceTemplate};
use PhpMcp\Server\Attributes\{McpTool};
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Illuminate\Http\Request;
use Storage;

class DocumentService
{
    /**
     * Import a PDF document
     */
    #[McpTool(name: 'import_document', description: 'Import a document from the provided URL. After calling this tool, you can use the `get_document_task_info` tool to check the status of the import task. Check the task status after 3 seconds.')]
    public function importDocument(string $url): TaskResource
    {
        $controller = new TaskController();
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
        $controller = new TaskController();
        $task = Task::where('uuid', $task_uuid)->first();

        if ( empty($task)) {
            return [
                'uuid' => $task_uuid,
                'error' => 'The specified task does not exist.',
            ];
        }
        $response = $controller->show($task);

        return $response;
    }

    /**
     * Get a summary of the document identified by file_id.
     */
    #[McpTool(name: 'get_summary', description: 'Get a summary of the document identified by file_uuid')]
    public function getSummary(string $file_uuid): array
    {
        $file = $chunks = File::where('uuid', $file_uuid)
            ->get()
            ->first();

        if (empty($file)) {
            return [
                'uuid' => $file_uuid,
                'error' => 'The specified file does not exist.',
            ];
        }

        $chunks = $file
            ->chunks()
            ->get()
            ->pluck('text')
            ->toArray();

        if (empty($chunks)) {
            return [
                'uuid' => $file_uuid,
                'error' => 'No content available for summarization. The specified file may not have been processed yet.',
            ];
        }

        $text = implode('', $chunks);
        $summary = Prism::text()
            ->using(Provider::Anthropic, 'claude-3-7-sonnet-latest')
            ->withPrompt("Write a short and concise summary of the following text:\r\n\r\n" . $text)
            ->asText()->text;
        
        return [
            'uuid' => $file_uuid,
            'summary' => $summary,
        ];
    }

    /**
     * Get a summary of the document identified by file_id.
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
     * Search for documents based on a query.
     */
    #[McpTool(name: 'search_documents', description: 'Search for documents based on a query. This tool allows you to search through indexed documents using a specific query.')]
    public function searchDocuments(string $query): array
    {
        return Chunk::search($query)->get()->toArray();
    }

    #[McpResource(name: 'get_image', description: 'Get an image from the provided URL. This tool can be used to retrieve images stored in the application\'s storage, referenced from the document markdown or other sources.')]
    public function getImage(string $url): array {

        $parsedUrl = parse_url($url);
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $segments = explode('/', trim($path, '/')); // trim to remove leading/trailing slashes
        array_shift($segments); // remove the first segment
        $remainingPath = implode('/', $segments);

        $data = Storage::get($remainingPath);

        return [
            'image_html_tag' => '<img src="data:image/jpeg;base64,' . base64_encode($data) . '" />',
        ];
    }
}