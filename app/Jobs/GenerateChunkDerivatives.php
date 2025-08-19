<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\Prompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

class GenerateChunkDerivatives implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 7200;

    public function __construct(
        public File $file
    ) {}

    public function handle(): void
    {
        $prompts = Prompt::whereIn('name', [
            'default_summarize',
            'default_create_questions',
            // 'default_extract_metadata',
        ])->get();

        foreach ($prompts as $prompt) {

            foreach ($this->file->chunks as $chunk) {

                $generatedContent = trim($this->generateContent($chunk->text, $prompt), '`json');

                // Check if the generated content is an array or a single string
                $generatedContent = (is_array(json_decode($generatedContent))) ? json_decode($generatedContent) : [(string) $generatedContent];

                foreach ($generatedContent as $content) {
                    $chunk->derivatives()->create([
                        'prompt_id' => $prompt->id,
                        'type' => $prompt->type ?? null,
                        'content' => $content,
                        'llm_provider' => $prompt->llm_provider,
                        'llm_model' => $prompt->llm_model,
                    ]);
                }
            }
        }
    }

    private function generateContent(string $chunkText, Prompt $prompt): string
    {
        return Prism::text()
            ->using(Provider::{$prompt->llm_provider}, $prompt->llm_model)
             // ->withSystemPrompt()
            ->withPrompt($prompt->content.":\r\n\r\n".$chunkText)
            ->asText()
            ->text;
    }
}
