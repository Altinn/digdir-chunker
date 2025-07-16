<?php

namespace App\Mcp;

use PhpMcp\Server\Attributes\{McpResource};
use PhpMcp\Server\Attributes\{McpResourceTemplate};
use PhpMcp\Server\Attributes\{McpTool};
use App\Models\Chunk;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

class DocumentService
{
    /**
     * Import a PDF document
     */
    #[McpTool(name: 'import_document')]
    public function importDocument(string $url): string
    {
        return "The document at {$url} has been imported successfully.";
    }

    /**
     * Get a summary of the document identified by file_id.
     */
    #[McpTool(name: 'get_summary')]
    public function getSummary(int $file_id): array
    {
        $chunks = Chunk::where('file_id', $file_id)->get()->pluck('text')->toArray();

        $text = implode('', $chunks);
        $summary = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o')
            ->withPrompt('Write a concise summary of the following text in its original language: ' . $text)
            ->asText()->text;
        
        return [
            'id' => $file_id,
            'summary' => $summary,
        ];
    }
}