<?php

use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

test('rag ingest indexes static documents', function () {
    Storage::fake('rag');
    Storage::disk('rag')->put('rag/guide.txt', 'Laravel is a PHP framework.');

    Embeddings::fake([
        [[0.1, 0.2, 0.3]],
    ]);

    config([
        'services.gemini.embeddings_dimension' => 3,
    ]);

    $this->artisan('rag:ingest --recreate')
        ->assertExitCode(0);

    $this->assertDatabaseCount('rag_chunks', 1);

    $chunk = \App\Models\RagChunk::query()->firstOrFail();

    expect($chunk->source)->toBe('rag/guide.txt')
        ->and($chunk->chunk_index)->toBe(0)
        ->and($chunk->content)->toBe('Laravel is a PHP framework.')
        ->and($chunk->embedding)->toBe('[0.1,0.2,0.3]');
});
