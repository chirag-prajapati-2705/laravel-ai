<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RagService
{
    public function __construct(
        private GeminiClient $geminiClient,
        private QdrantClient $qdrantClient,
    ) {}

    public function ingest(bool $recreateCollection = false): int
    {
        $disk = Storage::disk('rag');
        $paths = $disk->allFiles('rag');

        $paths = array_values(array_filter($paths, function (string $path): bool {
            return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['txt', 'md'], true);
        }));

        if ($paths === []) {
            throw new RuntimeException('No RAG documents found in storage/app/rag.');
        }

        $documents = [];

        foreach ($paths as $path) {
            $content = trim((string) $disk->get($path));

            if ($content === '') {
                continue;
            }

            $documents[] = [
                'source' => $path,
                'content' => $content,
            ];
        }

        return $this->ingestDocuments($documents, $recreateCollection);
    }

    /**
     * @return array{answer: string, sources: array<int, string>}
     */
    public function answer(string $question): array
    {
        $embedding = $this->geminiClient->embedText($question);
        $matches = $this->retrieveSimilarChunks($embedding);

        if ($matches->isEmpty()) {
            throw new RuntimeException('No RAG chunks are available. Run the rag:ingest command first.');
        }

        $context = $matches
            ->map(fn (array $chunk): string => $chunk['content'])
            ->implode("\n\n");

        $prompt = $this->buildPrompt($question, $context);
        $answer = $this->geminiClient->generateText($prompt);

        return [
            'answer' => $answer,
            'sources' => $matches
                ->map(fn (array $chunk): string => $chunk['source'].'#'.$chunk['chunk_index'])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array{source: string, content: string}>  $documents
     */
    public function ingestDocuments(array $documents, bool $recreateCollection = false): int
    {
        if ($documents === []) {
            throw new RuntimeException('No documents were provided for ingestion.');
        }
        if ($recreateCollection) {
            $this->qdrantClient->recreateCollection();
        }
        
        $count = 0;

        foreach ($documents as $document) {
            $chunks = $this->chunkText($document['content']);
            $payloads = [];
            foreach ($chunks as $index => $chunk) {
                $embedding = $this->geminiClient->embedText($chunk);
                $payloads[] = [
                    'source' => $document['source'],
                    'chunk_index' => $index,
                    'content' => $chunk,
                    'embedding' => $embedding,
                ];
                $count++;
            }

            $this->qdrantClient->upsertChunks($payloads);
        }

        return $count;
    }

    /**
     * @return array<int, string>
     */
    private function chunkText(string $text): array
    {
        $chunkSize = (int) config('rag.chunk_size', 1000);
        $overlap = (int) config('rag.chunk_overlap', 200);

        if ($chunkSize <= 0) {
            return [$text];
        }

        $step = max(1, $chunkSize - $overlap);
        $length = strlen($text);
        $chunks = [];

        for ($start = 0; $start < $length; $start += $step) {
            $chunk = trim(substr($text, $start, $chunkSize));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /**
     * @return Collection<int, array{source: string, chunk_index: int, content: string}>
     */
    private function retrieveSimilarChunks(array $queryEmbedding): Collection
    {
        $maxResults = (int) config('rag.max_results', 4);
        $minSimilarity = (float) config('rag.min_similarity', 0.2);

        $results = $this->qdrantClient->search($queryEmbedding, $maxResults, $minSimilarity);

        return collect($results);
    }

    private function buildPrompt(string $question, string $context): string
    {
        return <<<PROMPT
You are a helpful assistant. Answer using only the context below.

Context:
{$context}

Question:
{$question}

Answer:
PROMPT;
    }
}
