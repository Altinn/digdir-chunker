<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => $this->faker->url(),
            'metadata' => [],
            'sha256' => $this->faker->sha256(),
            'size' => $this->faker->numberBetween(1000, 10000000),
            'markdown' => '# Test Document\n\nThis is a test document with some content.',
        ];
    }
}
