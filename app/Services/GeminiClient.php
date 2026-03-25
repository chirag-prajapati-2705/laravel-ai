<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClient
{
    public function embedText(string $text): array
    {
        $model = (string) config('services.gemini.embeddings_model', 'gemini-embedding-001');

        $response = $this->post("models/{$model}:embedContent", [
            'model' => "models/{$model}",
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
        ]);

        $values = data_get($response, 'embedding.values');

        if (! is_array($values) || $values === []) {
            throw new RuntimeException('Gemini did not return embeddings.');
        }

        return array_map('floatval', $values);
    }

    public function generateText(string $prompt): string
    {
        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');

        $response = $this->post("models/{$model}:generateContent", [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ]);

        $text = data_get($response, 'candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini did not return a response.');
        }

        return trim($text);
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $apiKey = config('services.gemini.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Missing Gemini API key.');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/'.$path;

        $response = Http::withQueryParameters(['key' => $apiKey])
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini request failed: '.$response->body());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Gemini response was not JSON.');
        }

        return $data;
    }
}
