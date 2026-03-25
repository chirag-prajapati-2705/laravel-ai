<?php

namespace Database\Seeders;

use App\Models\RagChunk;
use Illuminate\Database\Seeder;

class RagChunkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RagChunk::factory()->count(3)->create();
    }
}
