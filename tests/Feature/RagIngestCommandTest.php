<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('rag ingest indexes static documents', function () {
    Storage::fake('rag');
    Storage::disk('rag')->put('rag/guide.txt', 'Laravel is a PHP framework.');

    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/*:embedContent*' => Http::response([
            'embedding' => [
                'values' => [0.1, 0.2, 0.3],
            ],
        ], 200),
        'https://qdrant.test/collections/policy-doc' => Http::response(['result' => true], 200),
        'https://qdrant.test/collections/policy-doc/points*' => Http::response(['result' => true], 200),
    ]);

    config([
        'services.gemini.key' => 'test-key',
        'services.qdrant.url' => 'https://qdrant.test',
        'services.qdrant.api_key' => 'qdrant-key',
        'services.qdrant.collection' => 'policy-doc',
        'services.qdrant.dimension' => 3,
    ]);

    $this->artisan('rag:ingest --recreate')
        ->assertExitCode(0);

    $this->assertDatabaseCount('rag_chunks', 0);
});
