<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RagChunk>
 */
class RagChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'rag/example.txt',
            'chunk_index' => $this->faker->numberBetween(1, 10),
            'content' => $this->faker->paragraphs(3, true),
            'embedding' => array_fill(0, 5, 0.1),
        ];
    }
}
