<?php

use App\Neuron\WindowsPdfReader;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

test('rag pdf ingest indexes pdf documents', function () {
    Storage::fake('rag');
    Storage::disk('rag')->put('rag-pdf/handbook.pdf', 'fake-pdf');

    $reader = \Mockery::mock(WindowsPdfReader::class);
    $reader->shouldReceive('setPdf')
        ->with(\Mockery::type('string'))
        ->andReturnSelf();
    $reader->shouldReceive('text')
        ->andReturn('Employee handbook content.');

    app()->instance(WindowsPdfReader::class, $reader);

    Embeddings::fake([
        [[0.1, 0.2, 0.3]],
    ]);

    config([
        'services.gemini.embeddings_dimension' => 3,
    ]);

    $this->artisan('rag:ingest-pdf')
        ->assertExitCode(0);

    $this->assertDatabaseCount('rag_chunks', 1);

    $chunk = \App\Models\RagChunk::query()->firstOrFail();

    expect($chunk->source)->toBe('rag-pdf/handbook.pdf')
        ->and($chunk->content)->toBe('Employee handbook content.')
        ->and($chunk->embedding)->toBe('[0.1,0.2,0.3]');
});
