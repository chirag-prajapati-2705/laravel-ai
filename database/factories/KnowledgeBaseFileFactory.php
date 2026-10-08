<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KnowledgeBaseFile>
 */
class KnowledgeBaseFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->slug().'.pdf',
            'path' => 'knowledge-base/'.fake()->uuid().'.pdf',
            'size' => fake()->numberBetween(1024, 1024 * 1024),
            'status' => 'completed',
        ];
    }
}
