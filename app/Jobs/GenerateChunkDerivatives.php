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

    public function __construct(
        public File $file
    ) {
    }

    public function handle(): void
    {
        $activePrompts = Prompt::where('is_active', true)->get();

        foreach ($this->file->chunks as $chunk) {
            foreach ($activePrompts as $prompt) {
                // Skip if derivative already exists for this chunk and prompt
                if ($chunk->derivatives()->where('prompt_id', $prompt->id)->exists()) {
                    continue;
                }

                $generatedContent = $this->generateContent($chunk->text, $prompt);
                
                // Check if the generated content is an array or a single string
                $generatedContent = (is_array(json_decode($generatedContent))) ? json_decode($generatedContent) : [(string) $generatedContent];

                foreach ($generatedContent as $content) {
                     $chunk->derivatives()->create([
                        'prompt_id' => $prompt->id,
                        'type' => $prompt->type,
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
            ->withPrompt($prompt->content . ":\r\n\r\n" . $chunkText)
            ->asText()
            ->text;
    }
}