<?php

namespace Database\Factories;

use App\Enums\ChunkingMethod;
use App\Enums\ConversionBackend;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversion_backend' => ConversionBackend::Marker,
            'chunking_method' => ChunkingMethod::Semantic,
            'chunk_size' => 1024,
            'chunk_overlap' => 256,
            'task_status' => TaskStatus::Created,
            'metadata' => [],
        ];
    }
}
