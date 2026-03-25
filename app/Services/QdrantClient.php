<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class QdrantClient
{
    public function recreateCollection(): void
    {
        $collection = $this->collection();
        $dimension = $this->dimension();

        $this->delete("collections/{$collection}");

        $this->put("collections/{$collection}", [
            'vectors' => [
                'size' => $dimension,
                'distance' => 'Cosine',
            ],
        ]);
    }

    /**
     * @param  array<int, array{source: string, chunk_index: int, content: string, embedding: array<int, float>}>  $chunks
     */
    public function upsertChunks(array $chunks): void
    {
        if ($chunks === []) {
            return;
        }

        $collection = $this->collection();

        $points = array_map(function (array $chunk): array {
            return [
                'id' => (string) Str::uuid(),
                'vector' => $chunk['embedding'],
                'payload' => [
                    'source' => $chunk['source'],
                    'chunk_index' => $chunk['chunk_index'],
                    'content' => $chunk['content'],
                ],
            ];
        }, $chunks);

        $this->put("collections/{$collection}/points?wait=true", [
            'points' => $points,
        ]);
    }

    /**
     * @return array<int, array{source: string, chunk_index: int, content: string}>
     */
    public function search(array $vector, int $limit, float $minSimilarity): array
    {
        $collection = $this->collection();

        $response = $this->post("collections/{$collection}/points/search", [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'score_threshold' => $minSimilarity,
        ]);

        $results = data_get($response, 'result', []);

        if (! is_array($results)) {
            return [];
        }

        return array_values(array_filter(array_map(function (array $item): ?array {
            $payload = $item['payload'] ?? null;

            if (! is_array($payload)) {
                return null;
            }

            $source = $payload['source'] ?? null;
            $chunkIndex = $payload['chunk_index'] ?? null;
            $content = $payload['content'] ?? null;

            if (! is_string($source) || ! is_int($chunkIndex) || ! is_string($content)) {
                return null;
            }

            return [
                'source' => $source,
                'chunk_index' => $chunkIndex,
                'content' => $content,
            ];
        }, $results)));
    }

    /**
     * @return array<string, mixed>
     */
    private function put(string $path, array $payload): array
    {
        return $this->request('put', $path, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        return $this->request('post', $path, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(string $path): array
    {
        return $this->request('delete', $path, allowNotFound: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = [], bool $allowNotFound = false): array
    {
        $url = rtrim($this->baseUrl(), '/').'/'.$path;

        $response = Http::withHeaders($this->headers())
            ->{$method}($url, $payload);

        if ($allowNotFound && $response->status() === 404) {
            return [];
        }

        if (! $response->successful()) {
            throw new RuntimeException('Qdrant request failed: '.$response->body());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Qdrant response was not JSON.');
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $apiKey = config('services.qdrant.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Missing Qdrant API key.');
        }

        return [
            'api-key' => $apiKey,
        ];
    }

    private function baseUrl(): string
    {
        $url = config('services.qdrant.url');

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Missing Qdrant URL.');
        }

        return $url;
    }

    private function collection(): string
    {
        return (string) config('services.qdrant.collection');
    }

    private function dimension(): int
    {
        return (int) config('services.qdrant.dimension', 768);
    }
}
