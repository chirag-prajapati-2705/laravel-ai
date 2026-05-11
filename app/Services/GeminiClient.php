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

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function batchEmbedTexts(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $model = (string) config('services.gemini.embeddings_model', 'gemini-embedding-001');

        $requests = array_map(fn (string $text) => [
            'model' => "models/{$model}",
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
        ], $texts);

        $response = $this->post("models/{$model}:batchEmbedContents", [
            'requests' => $requests,
        ]);

        $embeddings = data_get($response, 'embeddings');

        if (! is_array($embeddings)) {
            throw new RuntimeException('Gemini did not return batch embeddings.');
        }

        return array_map(function ($embedding) {
            $values = data_get($embedding, 'values');
            if (! is_array($values)) {
                throw new RuntimeException('Gemini did not return embedding values in batch response.');
            }

            return array_map('floatval', $values);
        }, $embeddings);
    }

    public function generateText(string $prompt): string
    {
        $model = (string) config('services.gemini.model', 'gemini-1.5-flash');

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

    public function streamGenerateContent(string $prompt, callable $onChunk): void
    {
        $model = (string) config('services.gemini.model', 'gemini-1.5-flash');
        $apiKey = config('services.gemini.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Missing Gemini API key.');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key={$apiKey}&alt=sse";

        $payload = [
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
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($onChunk) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    $chunk = json_decode($json, true);
                    $text = data_get($chunk, 'candidates.0.content.parts.0.text');
                    if ($text) {
                        $onChunk($text);
                    }
                }
            }

            return strlen($data);
        });

        curl_exec($ch);

        if (curl_errno($ch)) {
            throw new RuntimeException('Curl error: '.curl_error($ch));
        }

        curl_close($ch);
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
