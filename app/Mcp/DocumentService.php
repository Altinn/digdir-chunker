<?php

namespace App\Mcp;

use App\Http\Controllers\TaskController;
use App\Http\Resources\TaskResource;
use App\Models\File;
use App\Models\Task;
use PhpMcp\Server\Attributes\{McpResource};
use PhpMcp\Server\Attributes\{McpResourceTemplate};
use PhpMcp\Server\Attributes\{McpTool};
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Illuminate\Http\Request;

class DocumentService
{
    /**
     * Import a PDF document
     */
    #[McpTool(name: 'import_document', description: 'Import a document from the provided URL')]
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
            ->using(Provider::OpenAI, 'gpt-4o')
            ->withPrompt('Write a concise summary of the following text. Ignore image files.' . $text)
            ->asText()->text;
        
        return [
            'uuid' => $file_uuid,
            'summary' => $summary,
        ];
    }
}