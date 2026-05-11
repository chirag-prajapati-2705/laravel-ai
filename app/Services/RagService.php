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
    ) {}

    public function ingest(bool $recreateCollection = false): int
    {
        $disk = Storage::disk('rag');
        $paths = $disk->allFiles('rag');

        $paths = array_values(array_filter($paths, function (string $path): bool {
            return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['txt', 'md'], true);
        }));

        if ($paths === []) {
            return 0; // Don't throw error here to allow other document types or empty starts
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
    public function answer(string $question, int|array|null $knowledgeBaseFileId = null): array
    {
        $embedding = $this->geminiClient->embedText($question);
        $matches = $this->retrieveSimilarChunks($embedding, $knowledgeBaseFileId);

        if ($matches->isEmpty()) {
            throw new RuntimeException('No RAG chunks match your question. Please upload more relevant documents.');
        }

        $context = $matches
            ->map(fn (\App\Models\RagChunk $chunk): string => $chunk->content)
            ->implode("\n\n");

        $prompt = $this->buildPrompt($question, $context);
        $answer = $this->geminiClient->generateText($prompt);

        return [
            'answer' => $answer,
            'sources' => $matches
                ->map(fn (\App\Models\RagChunk $chunk): string => $chunk->source.'#'.$chunk->chunk_index)
                ->values()
                ->all(),
        ];
    }

    public function streamAnswer(string $question, callable $onChunk, int|array|null $knowledgeBaseFileId = null, ?callable $onThinking = null): array
    {
        if ($onThinking) {
            $onThinking('🔍 Searching knowledge base...');
        }

        $embedding = $this->geminiClient->embedText($question);
        $matches = $this->retrieveSimilarChunks($embedding, $knowledgeBaseFileId);

        if ($matches->isEmpty()) {
            throw new RuntimeException('No RAG chunks match your question. Please upload more relevant documents.');
        }

        if ($onThinking) {
            $onThinking('📊 Analyzing '.$matches->count().' matching snippets...');
        }

        $context = $matches
            ->map(fn (\App\Models\RagChunk $chunk): string => $chunk->content)
            ->implode("\n\n");

        $prompt = $this->buildPrompt($question, $context);

        $this->geminiClient->streamGenerateContent($prompt, $onChunk);

        return [
            'sources' => $matches
                ->map(fn (\App\Models\RagChunk $chunk): string => $chunk->source.'#'.$chunk->chunk_index)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array{source: string, content: string}>  $documents
     */
    public function ingestDocuments(array $documents, bool $recreateCollection = false, ?int $knowledgeBaseFileId = null): int
    {
        if ($documents === []) {
            return 0;
        }

        if ($recreateCollection) {
            \App\Models\RagChunk::query()->delete();
        }

        $count = 0;
        $batchSize = 50; // Process 50 chunks at a time

        foreach ($documents as $document) {
            $chunks = $this->chunkText($document['content']);

            $chunkBatches = array_chunk($chunks, $batchSize);

            foreach ($chunkBatches as $batchIndex => $chunkBatch) {
                $embeddings = $this->geminiClient->batchEmbedTexts($chunkBatch);

                foreach ($chunkBatch as $indexInBatch => $chunk) {
                    $globalIndex = ($batchIndex * $batchSize) + $indexInBatch;
                    $embedding = $embeddings[$indexInBatch];

                    \App\Models\RagChunk::create([
                        'knowledge_base_file_id' => $knowledgeBaseFileId,
                        'source' => $document['source'],
                        'chunk_index' => $globalIndex,
                        'content' => $chunk,
                        'embedding' => '['.implode(',', $embedding).']',
                    ]);

                    $count++;
                }
            }
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
     * @return Collection<int, \App\Models\RagChunk>
     */
    private function retrieveSimilarChunks(array $queryEmbedding, int|array|null $knowledgeBaseFileId = null): Collection
    {
        $maxResults = (int) config('rag.max_results', 4);
        $minSimilarity = (float) config('rag.min_similarity', 0.2);

        $vectorString = '['.implode(',', $queryEmbedding).']';

        // PostgreSQL pgvector cosine similarity search
        $query = \App\Models\RagChunk::query()
            ->select('*')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$vectorString])
            ->whereRaw('1 - (embedding <=> ?) >= ?', [$vectorString, $minSimilarity]);

        if ($knowledgeBaseFileId !== null) {
            if (is_array($knowledgeBaseFileId)) {
                $query->whereIn('knowledge_base_file_id', $knowledgeBaseFileId);
            } else {
                $query->where('knowledge_base_file_id', $knowledgeBaseFileId);
            }
        }

        return $query
            ->orderBy('similarity', 'desc')
            ->limit($maxResults)
            ->get();
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
