<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests cannot access rag routes', function () {
    $this->get(route('rag-qa'))
        ->assertRedirect(route('login'));

    $this->post(route('rag-question'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the rag q&a page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('rag-qa'))
        ->assertOk();
});

test('rag questions require a question', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('rag-question'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

test('authenticated users can ask a rag question', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/*:embedContent*' => Http::response([
            'embedding' => [
                'values' => [1.0, 0.0, 0.0],
            ],
        ], 200),
        'https://qdrant.test/collections/policy-doc/points/search' => Http::response([
            'result' => [
                [
                    'payload' => [
                        'source' => 'rag/guide.txt',
                        'chunk_index' => 0,
                        'content' => 'Laravel is a PHP framework.',
                    ],
                ],
            ],
        ], 200),
        'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Laravel is a PHP framework.'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    config([
        'services.gemini.key' => 'test-key',
        'services.qdrant.url' => 'https://qdrant.test',
        'services.qdrant.api_key' => 'qdrant-key',
        'services.qdrant.collection' => 'policy-doc',
        'rag.min_similarity' => 0.0,
    ]);

    $this->postJson(route('rag-question'), [
        'question' => 'What is Laravel?',
    ])->assertSuccessful()
        ->assertJsonStructure(['answer', 'sources']);
});
