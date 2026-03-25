<?php

use App\Neuron\WindowsPdfReader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/*:embedContent*' => Http::response([
            'embedding' => [
                'values' => [0.1, 0.2, 0.3],
            ],
        ], 200),
        'https://qdrant.test/collections/policy-doc/points*' => Http::response(['result' => true], 200),
    ]);

    config([
        'services.gemini.key' => 'test-key',
        'services.qdrant.url' => 'https://qdrant.test',
        'services.qdrant.api_key' => 'qdrant-key',
        'services.qdrant.collection' => 'policy-doc',
        'services.qdrant.dimension' => 3,
    ]);

    $this->artisan('rag:ingest-pdf')
        ->assertExitCode(0);
});
