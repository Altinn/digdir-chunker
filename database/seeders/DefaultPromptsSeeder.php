<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultPromptsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prompts = [
            [
                'name' => 'default_create_questions',
                'type' => 'question',
                'llm_provider' => config('prism.completions_provider'),
                'llm_model' => config('prism.completions_model'),
                'version' => 1,
                'content' => 'Which questions does the following content answer? Write the questions as if they were search queries in a large database. Please provide a list of questions that cover the main points and details of the content. Maximum 7 questions. Only write questions that would be useful in a search index to find the original content. Write the questions in the same language as the content. Respond with a JSON array of the questions, and no additional text. Do not escape the JSON.',
            ],
            [
                'name' => 'default_summarize',
                'type' => 'summary',
                'llm_provider' => config('prism.completions_provider'),
                'llm_model' => config('prism.completions_model'),
                'version' => 1,
                'content' => 'Create a concise summary of the following content. The summary should capture the main points and key details, ideally in 1 sentence and less than 200 characters. Write the summary in the same language as the content. Respond with the summary only.',
            ],
        ];

        foreach ($prompts as $promptData) {
            Prompt::firstOrCreate(
                [
                    'name' => $promptData['name'],
                    'version' => $promptData['version'],
                ],
                $promptData
            );
        }
    }
}
